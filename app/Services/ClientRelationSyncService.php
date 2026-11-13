<?php

namespace App\Services;

use App\Models\Amc;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClientRelationSyncService
{
    public function snapshotClient(Client $client): array
    {
        return [
            'name' => $this->normalizeText($client->name),
            'email' => $this->normalizeEmail($client->email),
            'phones' => $this->normalizedPhones([$client->mobile, $client->phone]),
            'address' => $this->normalizeText($client->address),
        ];
    }

    public function snapshotLead(Lead $lead): array
    {
        return [
            'name' => $this->normalizeText($lead->client_name),
            'email' => $this->normalizeEmail($lead->email),
            'phones' => $this->normalizedPhones([$lead->mobile]),
            'address' => $this->normalizeText($lead->address),
        ];
    }

    public function syncClientRecord(Client $client, array $previous): void
    {
        $current = $this->snapshotClient($client);
        $companyId = (int) $client->company_id;
        $primaryPhone = $this->primaryPhone($client->mobile, $client->phone);

        $this->syncLinkedUser($client);

        if (($previous['name'] ?? null) && ($previous['name'] ?? null) !== ($current['name'] ?? null)) {
            Project::query()
                ->where('company_id', $companyId)
                ->where('client_name', $previous['name'])
                ->update([
                    'client_name' => $current['name'],
                ]);
        }

        $this->applyLeadIdentityUpdate(
            $companyId,
            $previous,
            [
                'client_name' => $current['name'],
                'email' => $current['email'],
                'mobile' => $this->primaryPhone($client->mobile, $client->phone),
                'gst_number' => $client->gst_number,
                'pan_number' => $client->pan_number,
                'state_id' => $client->state_id,
                'city_id' => $client->city_id,
                'address' => $this->normalizeText($client->address),
            ]
        );

        $matchedAmcIds = Amc::query()
            ->where('company_id', $companyId)
            ->where('client_id', $client->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if ($matchedAmcIds !== []) {
            Amc::query()
                ->whereIn('id', $matchedAmcIds)
                ->update([
                    'customer_name' => $current['name'],
                    'mobile_number' => $primaryPhone,
                    'email' => $current['email'],
                    'gst_number' => $client->gst_number,
                ]);

            $supportTicketIds = Amc::query()
                ->whereIn('id', $matchedAmcIds)
                ->whereNotNull('support_ticket_id')
                ->pluck('support_ticket_id')
                ->map(fn ($id) => (int) $id)
                ->values()
                ->all();

            if ($supportTicketIds !== []) {
                Ticket::query()
                    ->whereIn('id', $supportTicketIds)
                    ->update([
                        'customer_name' => $current['name'],
                        'customer_contact_number' => $primaryPhone,
                        'customer_email' => $current['email'],
                    ]);
            }
        }
    }

    public function syncLeadRecord(Lead $lead, array $previous): void
    {
        $companyId = (int) $lead->company_id;
        $current = $this->snapshotLead($lead);
        $projectIds = Project::query()
            ->where('company_id', $companyId)
            ->where('lead_id', $lead->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        Project::query()
            ->where('company_id', $companyId)
            ->where('lead_id', $lead->id)
            ->update([
                'client_name' => $current['name'],
            ]);

        $matchedClients = $this->matchedClientsForLead($lead, $previous);

        foreach ($matchedClients as $client) {
            $emailInUseByAnotherClient = $current['email']
                && Str::lower((string) $client->email) !== $current['email']
                && Client::query()
                    ->where('company_id', (int) $lead->company_id)
                    ->whereKeyNot($client->id)
                    ->whereRaw('LOWER(email) = ?', [$current['email']])
                    ->exists();

            $client->fill([
                'name' => $current['name'],
                'email' => $emailInUseByAnotherClient ? $client->email : $current['email'],
                'mobile' => $this->primaryPhone($lead->mobile, $lead->phone),
                'phone' => $this->primaryPhone($lead->mobile, $lead->phone),
                'gst_number' => $lead->gst_number,
                'pan_number' => $lead->pan_number,
                'state_id' => $lead->state_id,
                'city_id' => $lead->city_id,
                'address' => $this->normalizeText($lead->address),
            ]);
            $client->save();
            $this->syncLinkedUser($client);
        }

        $matchedAmcIds = $this->matchedAmcIdsForLead($lead, $previous, $projectIds);

        if ($matchedAmcIds !== []) {
            Amc::query()
                ->whereIn('id', $matchedAmcIds)
                ->update([
                    'customer_name' => $current['name'],
                    'mobile_number' => $this->primaryPhone($lead->mobile, $lead->phone),
                    'email' => $current['email'],
                    'address' => $this->normalizeText($lead->address),
                    'gst_number' => $lead->gst_number,
                ]);
        }

        $supportTicketIds = Amc::query()
            ->whereIn('id', $matchedAmcIds)
            ->whereNotNull('support_ticket_id')
            ->pluck('support_ticket_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $this->applyTicketIdentityUpdate(
            $companyId,
            $previous,
            [
                'customer_name' => $current['name'],
                'customer_contact_number' => $this->primaryPhone($lead->mobile, $lead->phone),
                'customer_email' => $current['email'],
                'customer_address' => $this->normalizeText($lead->address),
            ],
            $projectIds,
            $supportTicketIds,
            $matchedAmcIds
        );
    }

    private function matchedAmcIdsForLead(Lead $lead, array $previous, array $projectIds): array
    {
        return Amc::query()
            ->where('company_id', (int) $lead->company_id)
            ->where(function (Builder $query) use ($projectIds, $previous) {
                if ($projectIds !== []) {
                    $query->whereIn('project_id', $projectIds);
                }

                $this->applyAmcIdentityMatch($query, $previous, $projectIds === []);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function matchedClientsForLead(Lead $lead, array $previous): Collection
    {
        return Client::query()
            ->where('company_id', (int) $lead->company_id)
            ->where(function (Builder $query) use ($previous) {
                $this->applyClientIdentityMatch($query, $previous, true);
            })
            ->get();
    }

    private function applyLeadIdentityUpdate(int $companyId, array $previous, array $payload): void
    {
        Lead::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($previous) {
                $this->applyLeadIdentityMatch($query, $previous, true);
            })
            ->update($payload);
    }

    private function applyTicketIdentityUpdate(
        int $companyId,
        array $previous,
        array $payload,
        array $projectIds = [],
        array $supportTicketIds = [],
        array $generatedFromAmcIds = []
    ): void {
        Ticket::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($previous, $projectIds, $supportTicketIds, $generatedFromAmcIds) {
                $hasBaseCondition = false;

                if ($projectIds !== []) {
                    $query->whereIn('project_id', $projectIds);
                    $hasBaseCondition = true;
                }

                if ($supportTicketIds !== []) {
                    if ($hasBaseCondition) {
                        $query->orWhereIn('id', $supportTicketIds);
                    } else {
                        $query->whereIn('id', $supportTicketIds);
                        $hasBaseCondition = true;
                    }
                }

                if ($generatedFromAmcIds !== []) {
                    if ($hasBaseCondition) {
                        $query->orWhereIn('generated_from_amc_id', $generatedFromAmcIds);
                    } else {
                        $query->whereIn('generated_from_amc_id', $generatedFromAmcIds);
                        $hasBaseCondition = true;
                    }
                }

                $this->applyTicketIdentityMatch($query, $previous, ! $hasBaseCondition);
            })
            ->update($payload);
    }

    private function syncLinkedUser(Client $client): void
    {
        $user = $client->user_id
            ? User::query()->whereKey($client->user_id)->first()
            : null;

        if (! $user) {
            return;
        }

        $user->fill([
            'name' => $this->normalizeText($client->name),
            'email' => $this->normalizeEmail($client->email) ?: $user->email,
        ]);
        $user->save();
    }

    private function applyLeadIdentityMatch(Builder $query, array $identity, bool $allowNameFallback): void
    {
        $this->applyIdentityMatch(
            $query,
            $identity,
            'client_name',
            'email',
            ['mobile'],
            'address',
            $allowNameFallback
        );
    }

    private function applyClientIdentityMatch(Builder $query, array $identity, bool $allowNameFallback): void
    {
        $this->applyIdentityMatch(
            $query,
            $identity,
            'name',
            'email',
            ['mobile', 'phone'],
            'address',
            $allowNameFallback
        );
    }

    private function applyTicketIdentityMatch(Builder $query, array $identity, bool $allowNameFallback): void
    {
        $this->applyIdentityMatch(
            $query,
            $identity,
            'customer_name',
            'customer_email',
            ['customer_contact_number'],
            'customer_address',
            $allowNameFallback,
            true
        );
    }

    private function applyAmcIdentityMatch(Builder $query, array $identity, bool $allowNameFallback): void
    {
        $this->applyIdentityMatch(
            $query,
            $identity,
            'customer_name',
            'email',
            ['mobile_number'],
            'address',
            $allowNameFallback
        );
    }

    private function applyIdentityMatch(
        Builder $query,
        array $identity,
        string $nameColumn,
        string $emailColumn,
        array $phoneColumns,
        string $addressColumn,
        bool $allowNameFallback,
        bool $phoneUsesLike = false
    ): void {
        $conditions = 0;

        if ($identity['email'] ?? null) {
            $query->orWhereRaw('LOWER(' . $emailColumn . ') = ?', [$identity['email']]);
            $conditions++;
        }

        foreach ($identity['phones'] ?? [] as $phone) {
            foreach ($phoneColumns as $column) {
                if ($phoneUsesLike) {
                    $query->orWhere($column, 'like', '%' . $phone . '%');
                } else {
                    $query->orWhere($column, $phone);
                }

                $conditions++;
            }
        }

        if ($identity['address'] ?? null) {
            $query->orWhereRaw('LOWER(TRIM(' . $addressColumn . ')) = ?', [Str::lower($identity['address'])]);
            $conditions++;
        }

        if (($allowNameFallback || $conditions > 0) && ($identity['name'] ?? null)) {
            $query->orWhere($nameColumn, $identity['name']);
            $conditions++;
        }

        if ($conditions === 0) {
            $query->whereRaw('1 = 0');
        }
    }

    private function normalizeText(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function normalizeEmail(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? Str::lower($value) : null;
    }

    private function normalizedPhones(array $values): array
    {
        return collect($values)
            ->map(function ($value) {
                $trimmed = trim((string) $value);

                if ($trimmed === '') {
                    return null;
                }

                return preg_replace('/\D+/', '', $trimmed) ?: $trimmed;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function primaryPhone(?string ...$values): ?string
    {
        foreach ($values as $value) {
            $normalized = $this->normalizeText($value);

            if ($normalized) {
                return $normalized;
            }
        }

        return null;
    }
}
