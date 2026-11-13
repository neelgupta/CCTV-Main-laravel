<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\User;
use App\Services\ClientSyncService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ClientImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function __construct(private readonly User $user)
    {
    }

    public function collection(Collection $rows): void
    {
        $companyId = (int) $this->user->company_id;
        $stateMap = \App\Models\State::query()
            ->with('cities:id,state_id,name')
            ->orderBy('name')
            ->get()
            ->keyBy(fn ($state) => Str::lower(trim((string) $state->name)));
        $existingClients = Client::query()
            ->where('company_id', $companyId)
            ->get();

        $prepared = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            $name = $this->optionalValue($data, ['name', 'client_name']);
            $email = $this->optionalValue($data, ['email']);
            $phone = $this->optionalValue($data, ['phone', 'mobile']);
            $address = $this->optionalValue($data, ['address']);

            $name ??= $email ?: ($phone ?: "Imported Client {$rowNumber}");
            $email = $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL)
                ? $email
                : null;

            $state = $this->resolveState($stateMap, $data);
            $city = $this->resolveCity($state, $data);

            $normalizedEmail = $email !== null ? Str::lower($email) : null;
            $company = $this->user->company;
            if ($normalizedEmail !== null && $company && $company->isInternalStaffOrAdminEmail($normalizedEmail)) {
                $normalizedEmail = null;
            }
            $normalizedPhone = $phone !== null ? trim($phone) : null;
            $normalizedName = Str::lower(trim($name));
            $existingClient = $existingClients->first(function (Client $client) use ($normalizedName, $normalizedEmail, $normalizedPhone) {
                $clientEmail = $client->email ? Str::lower((string) $client->email) : null;
                $clientName = Str::lower(trim((string) $client->name));
                $clientPhone = filled($client->phone) ? trim((string) $client->phone) : null;
                $clientMobile = filled($client->mobile) ? trim((string) $client->mobile) : null;

                return ($normalizedEmail !== null && $clientEmail === $normalizedEmail)
                    || ($normalizedPhone !== null && ($clientPhone === $normalizedPhone || $clientMobile === $normalizedPhone))
                    || ($normalizedEmail === null && $normalizedPhone === null && $clientName === $normalizedName);
            });

            if ($existingClient) {
                $existingPhone = filled($existingClient->phone) ? trim((string) $existingClient->phone) : null;
                $existingMobile = filled($existingClient->mobile) ? trim((string) $existingClient->mobile) : null;
                $existingName = Str::lower(trim((string) $existingClient->name));
                $existingEmail = $existingClient->email ? Str::lower((string) $existingClient->email) : null;

                $phoneMatches = $normalizedPhone !== null
                    ? ($normalizedPhone === $existingPhone || $normalizedPhone === $existingMobile)
                    : true;
                $nameMatches = $existingName === $normalizedName;
                $emailMatches = $normalizedEmail !== null ? $existingEmail === $normalizedEmail : true;

                if ($nameMatches && $emailMatches && $phoneMatches) {
                    continue; // exact duplicate, skip silently
                }

                if ($normalizedPhone !== null && ($existingPhone || $existingMobile) && ! $phoneMatches) {
                    continue;
                }
            }

            $prepared[] = [
                'company_id' => $companyId,
                'name' => $name,
                'email' => $normalizedEmail,
                'mobile' => $normalizedPhone,
                'phone' => $normalizedPhone,
                'address' => $address,
                'gst_number' => $this->truncate($this->normalizeUpper($this->optionalValue($data, ['gst_number'])), 20),
                'pan_number' => $this->truncate($this->normalizeUpper($this->optionalValue($data, ['pan_number'])), 20),
                'state_id' => $state?->id,
                'city_id' => $city?->id,
                'is_direct_client' => true,
            ];
        }

        DB::transaction(function () use ($prepared) {
            $clientSyncService = app(ClientSyncService::class);

            foreach ($prepared as $payload) {
                $clientSyncService->upsert($payload);
            }
        });
    }

    private function normalizeRow(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $normalizedKey = Str::of((string) $key)
                ->trim()
                ->lower()
                ->replace(['-', ' '], '_')
                ->toString();

            $normalized[$normalizedKey] = is_string($value) ? trim($value) : $value;
        }

        return $normalized;
    }

    private function optionalValue(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $data[$key] ?? null;

            if ($value === null) {
                continue;
            }

            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    private function normalizeUpper(?string $value): ?string
    {
        return $value !== null ? Str::upper($value) : null;
    }

    private function truncate(?string $value, int $max): ?string
    {
        return $value !== null ? substr($value, 0, $max) : null;
    }

    private function resolveState(Collection $stateMap, array $data): mixed
    {
        $stateValue = $this->optionalValue($data, ['state']);

        if ($stateValue === null) {
            return null;
        }

        return $stateMap->get(Str::lower($stateValue));
    }

    private function resolveCity(mixed $state, array $data): mixed
    {
        $cityValue = $this->optionalValue($data, ['city']);

        if ($cityValue === null || ! $state) {
            return null;
        }

        return $state->cities->first(fn ($city) => Str::lower(trim((string) $city->name)) === Str::lower($cityValue));
    }

}
