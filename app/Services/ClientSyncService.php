<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientSyncService
{
    public function __construct(private readonly SubscriptionLimitService $subscriptionLimitService)
    {
    }

    public function upsert(array $data, bool $forceNewClient = false): Client
    {
        $companyId = (int) $data['company_id'];
        $email = isset($data['email']) ? Str::lower(trim((string) $data['email'])) : null;
        $mobile = $this->normalizePhone($data['mobile'] ?? null);
        $phone = $this->normalizePhone($data['phone'] ?? null);
        $payload = [
            'name' => trim((string) ($data['name'] ?? '')),
            'email' => $email ?: null,
            'mobile' => $mobile,
            'phone' => $phone,
            'gst_number' => $data['gst_number'] ?? null,
            'pan_number' => $data['pan_number'] ?? null,
            'state_id' => $data['state_id'] ?? null,
            'city_id' => $data['city_id'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => array_key_exists('latitude', $data) ? $data['latitude'] : null,
            'longitude' => array_key_exists('longitude', $data) ? $data['longitude'] : null,
        ];

        return DB::transaction(function () use ($companyId, $email, $mobile, $phone, $payload, $forceNewClient) {
            $client = $this->resolveExistingClient(
                $companyId,
                $payload['name'],
                $email,
                $mobile,
                $phone,
                ! $forceNewClient
            );
            $user = null;
            $emailBelongsToAnotherCompany = false;
            $emailBelongsToInternalStaffOrAdmin = false;

            if ($email) {
                $company = Company::query()->find($companyId);
                if ($company && $company->isInternalStaffOrAdminEmail($email)) {
                    $emailBelongsToInternalStaffOrAdmin = true;
                }

                if ($emailBelongsToInternalStaffOrAdmin) {
                    $isEmailRequired = !empty($data['email_required']);
                    if ($isEmailRequired) {
                        throw ValidationException::withMessages([
                            'email' => 'This email belongs to a company administrator or staff member and cannot be assigned to a client.',
                        ]);
                    }

                    // For flows where email is optional (Lead, Project, AMC, imports, direct client creation),
                    // skip/omit the email field instead of saving or converting staff/admin
                    $email = null;
                    $payload['email'] = null;
                }
            }

            $needsNewClient = ! $client;
            $needsNewUser = false;

            if ($email && ! $emailBelongsToInternalStaffOrAdmin) {
                $needsNewUser = ! ($client?->user ?: $user);
            }

            if ($needsNewClient) {
                $this->subscriptionLimitService->ensureWithinLimit($companyId, 'clients', 1, 'name');
            }

            if ($needsNewUser) {
                $existingGlobalUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->first();

                if ($existingGlobalUser && (int) $existingGlobalUser->company_id !== $companyId) {
                    $emailBelongsToAnotherCompany = true;
                    $needsNewUser = false;
                }
            }

            if ($needsNewUser) {
                $this->subscriptionLimitService->ensureWithinLimit($companyId, 'users', 1, 'email');
            }

            $client ??= new Client(['company_id' => $companyId]);

            $client->fill($payload);
            $client->company_id = $companyId;
            if (!empty($data['is_direct_client'])) {
                $client->is_direct_client = true;
            }
            $client->save();

            if ($email && ! $emailBelongsToAnotherCompany && ! $emailBelongsToInternalStaffOrAdmin) {
                $user = $client->user ?: $user;
                if (! $user) {
                    $clientRole = Role::firstOrCreate(
                        ['company_id' => $companyId, 'slug' => 'client'],
                        ['name' => 'Client']
                    );

                    $user = new User([
                        'company_id' => $companyId,
                        'email' => $email,
                        'password' => Hash::make(Str::random(32)),
                        'role' => 'client',
                        'role_id' => $clientRole->id,
                        'is_active' => true,
                    ]);
                }

                $user->fill([
                    'company_id' => $companyId,
                    'name' => $payload['name'],
                    'email' => $email,
                    'role' => 'client',
                    'is_active' => true,
                ]);

                if (! $user->exists || empty($user->password)) {
                    $user->password = Hash::make(Str::random(32));
                }

                $user->save();

                if ((int) $client->user_id !== (int) $user->id) {
                    $client->user_id = $user->id;
                    $client->save();
                }
            }

            return $client;
        });
    }

    private function resolveExistingClient(
        int $companyId,
        string $name,
        ?string $email,
        ?string $mobile,
        ?string $phone,
        bool $allowNameFallback = true
    ): ?Client
    {
        $emailClient = $email
            ? Client::query()->where('company_id', $companyId)->whereRaw('LOWER(email) = ?', [$email])->first()
            : null;

        $phoneClient = null;
        foreach (array_filter([$mobile, $phone]) as $contactNumber) {
            $match = Client::query()
                ->where('company_id', $companyId)
                ->where(function ($query) use ($contactNumber) {
                    $query->where('mobile', $contactNumber)
                        ->orWhere('phone', $contactNumber);
                })
                ->first();

            if ($match) {
                $phoneClient = $match;
                break;
            }
        }

        if ($emailClient && $phoneClient && (int) $emailClient->id !== (int) $phoneClient->id) {
            throw ValidationException::withMessages([
                'email' => 'This email is already linked to another client.',
                'phone' => 'This phone number is already linked to another client.',
            ]);
        }

        if ($emailClient || $phoneClient) {
            return $emailClient ?: $phoneClient;
        }

        if ($email || $mobile || $phone || ! $allowNameFallback) {
            return null;
        }

        return Client::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->first();
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    public function sendPasswordSetup(Client $client): void
    {
        if (! $client->email) {
            return;
        }

        $linkedUser = $client->user;

        if (! $linkedUser) {
            return;
        }

        if (Str::lower((string) $linkedUser->email) !== Str::lower((string) $client->email)) {
            Log::warning('Skipped password setup email because linked client user email does not match client email.', [
                'client_id' => $client->id,
                'user_id' => $linkedUser->id,
                'client_email' => $client->email,
                'user_email' => $linkedUser->email,
            ]);
            return;
        }

        Password::broker()->sendResetLink(['email' => $client->email]);
    }
}
