<?php

namespace App\Services;

use App\Models\Amc;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Ticket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClientDuplicateMergeService
{
    public function __construct(private readonly ClientSyncService $clientSyncService)
    {
    }

    public function mergeIntoTarget(int $companyId, string $targetType, int $targetId): Client
    {
        return DB::transaction(function () use ($companyId, $targetType, $targetId) {
            $targetLead = null;
            $targetClient = null;
            $preservedClientProfile = null;
            $mergeIntoClient = $targetType === 'client';

            if ($targetType === 'client') {
                $targetClient = Client::query()
                    ->where('company_id', $companyId)
                    ->findOrFail($targetId);

                $preservedClientProfile = $this->clientProfileSnapshot($targetClient);
            } elseif ($targetType === 'lead') {
                $targetLead = Lead::query()
                    ->where('company_id', $companyId)
                    ->findOrFail($targetId);

                $targetClient = $this->resolveOrCreateClientFromLead($companyId, $targetLead);
            } else {
                throw ValidationException::withMessages([
                    'target' => 'Invalid duplicate merge target selected.',
                ]);
            }

            $duplicateSet = $this->findDuplicateSet($companyId, $targetClient, $targetLead);

            $duplicateClients = $duplicateSet['clients'];
            $duplicateLeads = $duplicateSet['leads'];

            if ($duplicateClients->count() + $duplicateLeads->count() < 2) {
                throw ValidationException::withMessages([
                    'phone' => 'No duplicate records were found for the selected contact number.',
                ]);
            }

            if ($targetType === 'client') {
                $this->preserveTargetClientProfile($targetClient, $preservedClientProfile ?? []);
            } else {
                $this->fillTargetClientFromDuplicates($targetClient, $targetLead, $duplicateClients, $duplicateLeads);
            }

            $canonicalLead = null;

            if ($mergeIntoClient) {
                foreach ($duplicateLeads as $lead) {
                    $this->syncLeadIdentityToClient($lead, $targetClient);
                }
            } else {
                $canonicalLead = $targetLead
                    ?: $duplicateLeads
                        ->sortBy('id')
                        ->first();

                if ($canonicalLead) {
                    $this->alignLeadWithClient($canonicalLead, $targetClient);
                }

                foreach ($duplicateLeads as $lead) {
                    if ($canonicalLead && (int) $lead->id === (int) $canonicalLead->id) {
                        continue;
                    }

                    if ($canonicalLead) {
                        $this->mergeLeadIntoLead($lead, $canonicalLead, $targetClient);
                        continue;
                    }

                    $this->updateTicketsForIdentity(
                        $companyId,
                        $this->identityPayloadFromLead($lead),
                        $this->identityPayloadFromClient($targetClient)
                    );

                    $lead->delete();
                }
            }

            foreach ($duplicateClients as $client) {
                if ((int) $client->id === (int) $targetClient->id) {
                    continue;
                }

                $this->mergeClientIntoClient($client, $targetClient);
            }

            if ($canonicalLead) {
                $this->alignLeadWithClient($canonicalLead->fresh(), $targetClient->fresh());
            }

            if ($mergeIntoClient) {
                $targetClient = $targetClient->fresh();
                $this->preserveTargetClientProfile($targetClient, $preservedClientProfile ?? []);
            }

            return $targetClient->fresh(['user:id,name,email', 'state:id,name', 'city:id,name,state_id']);
        });
    }

    private function findDuplicateSet(int $companyId, Client $targetClient, ?Lead $targetLead): array
    {
        $phoneKeys = collect([
            $this->normalizePhoneForGrouping($targetClient->mobile),
            $this->normalizePhoneForGrouping($targetClient->phone),
            $targetLead ? $this->normalizePhoneForGrouping($targetLead->mobile) : null,
        ])->filter()->unique()->values();

        if ($phoneKeys->isEmpty()) {
            throw ValidationException::withMessages([
                'phone' => 'The selected record does not have a mobile number to merge by.',
            ]);
        }

        $clients = Client::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(function (Client $client) use ($phoneKeys) {
                return collect([$client->mobile, $client->phone])
                    ->map(fn ($value) => $this->normalizePhoneForGrouping($value))
                    ->filter()
                    ->intersect($phoneKeys)
                    ->isNotEmpty();
            })
            ->values();

        $leads = Lead::query()
            ->where('company_id', $companyId)
            ->get()
            ->filter(function (Lead $lead) use ($phoneKeys) {
                return collect([$lead->mobile])
                    ->map(fn ($value) => $this->normalizePhoneForGrouping($value))
                    ->filter()
                    ->intersect($phoneKeys)
                    ->isNotEmpty();
            })
            ->values();

        return ['clients' => $clients, 'leads' => $leads];
    }

    private function resolveOrCreateClientFromLead(int $companyId, Lead $lead): Client
    {
        $email = filled($lead->email) ? strtolower(trim((string) $lead->email)) : null;
        $mobile = filled($lead->mobile) ? trim((string) $lead->mobile) : null;
        $phone = filled($lead->phone) ? trim((string) $lead->phone) : null;

        $client = null;

        if ($mobile || $phone) {
            $client = Client::query()
                ->where('company_id', $companyId)
                ->where(function ($query) use ($mobile, $phone) {
                    if ($mobile) {
                        $query->where('mobile', $mobile)
                            ->orWhere('phone', $mobile);
                    }

                    if ($phone) {
                        $query->orWhere('mobile', $phone)
                            ->orWhere('phone', $phone);
                    }
                })
                ->first();
        }

        if (! $client && $email) {
            $client = Client::query()
                ->where('company_id', $companyId)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        }

        if ($client) {
            return $client;
        }

        return $this->clientSyncService->upsert([
            'company_id' => $companyId,
            'name' => $lead->client_name,
            'email' => $lead->email,
            'mobile' => $lead->mobile,
            'phone' => $lead->mobile ?: $lead->phone,
            'gst_number' => $lead->gst_number,
            'pan_number' => $lead->pan_number,
            'state_id' => $lead->state_id,
            'city_id' => $lead->city_id,
            'address' => $lead->address,
            'is_direct_client' => true,
        ]);
    }

    private function fillTargetClientFromDuplicates(Client $targetClient, ?Lead $targetLead, Collection $clients, Collection $leads): void
    {
        $clientCandidates = $clients
            ->map(fn (Client $client) => [
                'name' => $client->name,
                'email' => $client->email,
                'mobile' => $client->mobile,
                'phone' => $client->phone,
                'gst_number' => $client->gst_number,
                'pan_number' => $client->pan_number,
                'state_id' => $client->state_id,
                'city_id' => $client->city_id,
                'address' => $client->address,
                'is_direct_client' => $client->is_direct_client,
            ]);

        $leadCandidates = $leads
            ->map(fn (Lead $lead) => [
                'name' => $lead->client_name,
                'email' => $lead->email,
                'mobile' => $lead->mobile,
                'phone' => $lead->mobile ?: $lead->phone,
                'gst_number' => $lead->gst_number,
                'pan_number' => $lead->pan_number,
                'state_id' => $lead->state_id,
                'city_id' => $lead->city_id,
                'address' => $lead->address,
                'is_direct_client' => true,
            ]);

        $candidates = collect()
            ->when($targetLead, fn (Collection $collection) => $collection->push([
                'name' => $targetLead->client_name,
                'email' => $targetLead->email,
                'mobile' => $targetLead->mobile,
                'phone' => $targetLead->mobile ?: $targetLead->phone,
                'gst_number' => $targetLead->gst_number,
                'pan_number' => $targetLead->pan_number,
                'state_id' => $targetLead->state_id,
                'city_id' => $targetLead->city_id,
                'address' => $targetLead->address,
                'is_direct_client' => true,
            ]))
            ->concat($clientCandidates)
            ->concat($leadCandidates)
            ->values();

        $targetClient->fill([
            'name' => $this->preferredValue($targetClient->name, $candidates->pluck('name')->all()),
            'email' => $this->preferredValue($targetClient->email, $candidates->pluck('email')->all()),
            'mobile' => $this->preferredValue($targetClient->mobile, $candidates->pluck('mobile')->all()),
            'phone' => $this->preferredValue($targetClient->phone, $candidates->pluck('phone')->all()),
            'gst_number' => $this->preferredValue($targetClient->gst_number, $candidates->pluck('gst_number')->all()),
            'pan_number' => $this->preferredValue($targetClient->pan_number, $candidates->pluck('pan_number')->all()),
            'state_id' => $this->preferredValue($targetClient->state_id, $candidates->pluck('state_id')->all()),
            'city_id' => $this->preferredValue($targetClient->city_id, $candidates->pluck('city_id')->all()),
            'address' => $this->preferredValue($targetClient->address, $candidates->pluck('address')->all()),
            'is_direct_client' => (bool) ($targetClient->is_direct_client || $candidates->pluck('is_direct_client')->contains(true)),
        ]);

        $targetClient->save();
    }

    private function preserveTargetClientProfile(Client $targetClient, array $profile): void
    {
        $targetClient->fill([
            'name' => $profile['name'] ?? $targetClient->name,
            'email' => $profile['email'] ?? $targetClient->email,
            'mobile' => $profile['mobile'] ?? $targetClient->mobile,
            'phone' => $profile['phone'] ?? $targetClient->phone,
            'gst_number' => $profile['gst_number'] ?? $targetClient->gst_number,
            'pan_number' => $profile['pan_number'] ?? $targetClient->pan_number,
            'state_id' => $profile['state_id'] ?? $targetClient->state_id,
            'city_id' => $profile['city_id'] ?? $targetClient->city_id,
            'address' => $profile['address'] ?? $targetClient->address,
            'is_direct_client' => (bool) ($profile['is_direct_client'] ?? $targetClient->is_direct_client),
        ]);

        $targetClient->save();
    }

    private function clientProfileSnapshot(Client $client): array
    {
        return [
            'name' => $client->name,
            'email' => $client->email,
            'mobile' => $client->mobile,
            'phone' => $client->phone,
            'gst_number' => $client->gst_number,
            'pan_number' => $client->pan_number,
            'state_id' => $client->state_id,
            'city_id' => $client->city_id,
            'address' => $client->address,
            'is_direct_client' => $client->is_direct_client,
        ];
    }

    private function alignLeadWithClient(Lead $lead, Client $client): void
    {
        $lead->fill([
            'client_name' => $this->preferredValue($lead->client_name, [$client->name]),
            'email' => $this->preferredValue($lead->email, [$client->email]),
            'mobile' => $this->preferredValue($lead->mobile, [$client->mobile, $client->phone]),
            'gst_number' => $this->preferredValue($lead->gst_number, [$client->gst_number]),
            'pan_number' => $this->preferredValue($lead->pan_number, [$client->pan_number]),
            'state_id' => $this->preferredValue($lead->state_id, [$client->state_id]),
            'city_id' => $this->preferredValue($lead->city_id, [$client->city_id]),
            'address' => $this->preferredValue($lead->address, [$client->address]),
        ]);

        $lead->save();
    }

    private function syncLeadIdentityToClient(Lead $lead, Client $client): void
    {
        $leadIdentity = $this->identityPayloadFromLead($lead);

        $lead->fill([
            'client_name' => $client->name,
            'email' => $client->email,
            'mobile' => $client->mobile ?: $client->phone,
            'gst_number' => $client->gst_number,
            'pan_number' => $client->pan_number,
            'state_id' => $client->state_id,
            'city_id' => $client->city_id,
            'address' => $client->address,
        ]);
        $lead->save();

        Project::query()
            ->where('lead_id', $lead->id)
            ->update(['client_name' => $client->name]);

        $this->updateTicketsForIdentity(
            (int) $lead->company_id,
            $leadIdentity,
            $this->identityPayloadFromClient($client)
        );
    }

    private function mergeLeadIntoLead(Lead $sourceLead, Lead $targetLead, Client $targetClient): void
    {
        DB::table('lead_activities')->where('lead_id', $sourceLead->id)->update(['lead_id' => $targetLead->id]);
        DB::table('lead_surveys')->where('lead_id', $sourceLead->id)->update(['lead_id' => $targetLead->id]);
        DB::table('lead_quotations')->where('lead_id', $sourceLead->id)->update(['lead_id' => $targetLead->id]);
        DB::table('lead_comments')->where('lead_id', $sourceLead->id)->update(['lead_id' => $targetLead->id]);
        DB::table('lead_documents')->where('lead_id', $sourceLead->id)->update(['lead_id' => $targetLead->id]);
        DB::table('lead_materials')->where('lead_id', $sourceLead->id)->update(['lead_id' => $targetLead->id]);

        $assignedUserIds = DB::table('lead_user_assignments')
            ->where('lead_id', $sourceLead->id)
            ->pluck('user_id');

        foreach ($assignedUserIds as $userId) {
            DB::table('lead_user_assignments')->updateOrInsert(
                ['lead_id' => $targetLead->id, 'user_id' => $userId],
                ['updated_at' => now(), 'created_at' => now()]
            );
        }

        DB::table('lead_user_assignments')->where('lead_id', $sourceLead->id)->delete();

        Project::query()
            ->where('lead_id', $sourceLead->id)
            ->update([
                'lead_id' => $targetLead->id,
                'client_name' => $targetClient->name,
            ]);

        Project::query()
            ->where('company_id', $sourceLead->company_id)
            ->where('client_name', trim((string) $sourceLead->client_name))
            ->update(['client_name' => $targetClient->name]);

        $this->updateTicketsForIdentity(
            (int) $sourceLead->company_id,
            $this->identityPayloadFromLead($sourceLead),
            $this->identityPayloadFromClient($targetClient)
        );

        $sourceLead->delete();
    }

    private function mergeClientIntoClient(Client $sourceClient, Client $targetClient): void
    {
        Amc::query()
            ->where(function ($query) use ($sourceClient) {
                $query->where('client_id', $sourceClient->id);

                if (filled($sourceClient->mobile)) {
                    $query->orWhere('mobile_number', trim((string) $sourceClient->mobile));
                }

                if (filled($sourceClient->phone)) {
                    $query->orWhere('mobile_number', trim((string) $sourceClient->phone));
                }
            })
            ->update([
                'client_id' => $targetClient->id,
                'customer_name' => $targetClient->name,
                'mobile_number' => $targetClient->mobile ?: $targetClient->phone,
                'email' => $targetClient->email,
                'gst_number' => $targetClient->gst_number,
            ]);

        Project::query()
            ->where('company_id', $sourceClient->company_id)
            ->where('client_name', trim((string) $sourceClient->name))
            ->update(['client_name' => $targetClient->name]);

        Lead::query()
            ->where('company_id', $sourceClient->company_id)
            ->where(function ($query) use ($sourceClient) {
                $query->where('client_name', trim((string) $sourceClient->name));

                if (filled($sourceClient->email)) {
                    $query->orWhereRaw('LOWER(email) = ?', [strtolower(trim((string) $sourceClient->email))]);
                }

                if (filled($sourceClient->mobile)) {
                    $query->orWhere('mobile', trim((string) $sourceClient->mobile));
                }

                if (filled($sourceClient->phone)) {
                    $query->orWhere('mobile', trim((string) $sourceClient->phone));
                }
            })
            ->update([
                'client_name' => $targetClient->name,
                'email' => $targetClient->email,
                'mobile' => $targetClient->mobile ?: $targetClient->phone,
                'gst_number' => $targetClient->gst_number,
                'pan_number' => $targetClient->pan_number,
                'state_id' => $targetClient->state_id,
                'city_id' => $targetClient->city_id,
                'address' => $targetClient->address,
            ]);

        $this->updateTicketsForIdentity(
            (int) $sourceClient->company_id,
            $this->identityPayloadFromClient($sourceClient),
            $this->identityPayloadFromClient($targetClient)
        );

        $this->reassignClientUser($sourceClient, $targetClient);

        $sourceClient->delete();
    }

    private function reassignClientUser(Client $sourceClient, Client $targetClient): void
    {
        $sourceUser = $sourceClient->user;

        if (! $sourceUser) {
            return;
        }

        if (! $targetClient->user_id) {
            $targetClient->user_id = $sourceUser->id;
            $targetClient->save();

            $sourceUser->fill([
                'company_id' => $targetClient->company_id,
                'name' => $targetClient->name,
                'email' => $targetClient->email ?: $sourceUser->email,
            ]);
            $sourceUser->save();

            return;
        }

        if ($sourceUser->hasRole('client')) {
            $sourceUser->delete();
        }
    }

    private function updateTicketsForIdentity(int $companyId, array $sourceIdentity, array $targetIdentity): void
    {
        if ($sourceIdentity === []) {
            return;
        }

        Ticket::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($sourceIdentity) {
                $hasCondition = false;

                foreach (['contact_number' => 'customer_contact_number', 'email' => 'customer_email', 'name' => 'customer_name'] as $key => $column) {
                    if (! filled($sourceIdentity[$key] ?? null)) {
                        continue;
                    }

                    if ($hasCondition) {
                        $query->orWhere($column, $sourceIdentity[$key]);
                    } else {
                        $query->where($column, $sourceIdentity[$key]);
                        $hasCondition = true;
                    }
                }

                if (! $hasCondition && filled($sourceIdentity['address'] ?? null)) {
                    $query->where('customer_address', $sourceIdentity['address']);
                }
            })
            ->update([
                'customer_name' => $targetIdentity['name'] ?? null,
                'customer_contact_number' => $targetIdentity['contact_number'] ?? null,
                'customer_email' => $targetIdentity['email'] ?? null,
                'customer_address' => $targetIdentity['address'] ?? null,
            ]);
    }

    private function identityPayloadFromClient(Client $client): array
    {
        return array_filter([
            'name' => filled($client->name) ? trim((string) $client->name) : null,
            'email' => filled($client->email) ? trim((string) $client->email) : null,
            'contact_number' => filled($client->mobile) ? trim((string) $client->mobile) : (filled($client->phone) ? trim((string) $client->phone) : null),
            'address' => filled($client->address) ? trim((string) $client->address) : null,
        ], fn ($value) => filled($value));
    }

    private function identityPayloadFromLead(Lead $lead): array
    {
        return array_filter([
            'name' => filled($lead->client_name) ? trim((string) $lead->client_name) : null,
            'email' => filled($lead->email) ? trim((string) $lead->email) : null,
            'contact_number' => filled($lead->mobile) ? trim((string) $lead->mobile) : null,
            'address' => filled($lead->address) ? trim((string) $lead->address) : null,
        ], fn ($value) => filled($value));
    }

    private function preferredValue(mixed $current, array $candidates): mixed
    {
        if (filled($current)) {
            return $current;
        }

        foreach ($candidates as $candidate) {
            if (filled($candidate)) {
                return is_string($candidate) ? trim($candidate) : $candidate;
            }
        }

        return $current;
    }

    private function normalizePhoneForGrouping(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $trimmed = trim((string) $value);
        $digits = preg_replace('/\D+/', '', $trimmed);

        if ($digits !== null && $digits !== '') {
            return $digits;
        }

        return $trimmed !== '' ? strtolower($trimmed) : null;
    }
}
