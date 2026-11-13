<?php

namespace App\Imports;

use App\Models\City;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadCategory;
use App\Models\LeadStatus;
use App\Models\State;
use App\Models\User;
use App\Services\ClientSyncService;
use App\Services\LeadAssignmentService;
use App\Services\LeadProjectConversionService;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class LeadImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function __construct(
        private readonly User $user,
        private readonly Company $company,
        private readonly ?int $defaultStateId = null,
        private readonly ?int $defaultCityId = null,
    ) {
    }

    public function collection(Collection $rows): void
    {
        LeadStatus::ensureWon($this->company->id);

        $statuses = LeadStatus::query()->orderBy('order')->get()->keyBy(
            fn (LeadStatus $status) => Str::lower(trim((string) $status->name))
        );
        $categories = LeadCategory::query()
            ->where(function ($query) {
                $query->whereNull('company_id')->orWhere('company_id', $this->company->id);
            })
            ->get()
            ->keyBy(fn (LeadCategory $category) => Str::lower(trim((string) $category->name)));
        $users = User::query()
            ->where('company_id', $this->company->id)
            ->where('role', '!=', 'client')
            ->get()
            ->keyBy(fn (User $user) => Str::lower(trim((string) $user->name)));
        $stateMap = \App\Models\State::query()
            ->with('cities:id,state_id,name')
            ->orderBy('name')
            ->get()
            ->keyBy(fn ($state) => Str::lower(trim((string) $state->name)));

        $newLeadStatus = LeadStatus::ensureNewLead($this->company->id);
        $availableSources = collect($this->company->leadSourceNames())
            ->mapWithKeys(fn ($source) => [Str::lower(trim((string) $source)) => $source]);
        $customFieldDefinitions = collect($this->company->leadCustomFieldDefinitions())->values();

        $prepared = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            $name = $this->requiredValue($data, ['name', 'client_name']);
            $email = $this->optionalValue($data, ['email']);
            $phone = $this->requiredValue($data, ['phone', 'mobile']);
            $address = $this->optionalValue($data, ['address']);
            $categoryName = $this->requiredValue($data, ['lead_category', 'category']);
            $nextFollowUpRaw = $this->optionalValue($data, ['next_follow_up']);
            $nextFollowUp = $this->dateValue($nextFollowUpRaw);

            if ($name === null || $phone === null || $categoryName === null) {
                $errors[] = "Row {$rowNumber}: name, phone, and lead category are required.";
                continue;
            }

            if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: email must be a valid email address.";
                continue;
            }

            if ($email !== null && $this->company->isInternalStaffOrAdminEmail($email)) {
                $errors[] = "Row {$rowNumber}: email '{$email}' belongs to a company administrator or staff member and cannot be assigned to a lead.";
                continue;
            }

            $category = $categories->get(Str::lower($categoryName));
            if (! $category) {
                $errors[] = "Row {$rowNumber}: lead category '{$categoryName}' was not found.";
                continue;
            }

            $source = $this->optionalValue($data, ['source']);
            if ($source !== null && $availableSources->isNotEmpty()) {
                $normalizedSource = $availableSources->get(Str::lower($source));
                if (! $normalizedSource) {
                    $errors[] = "Row {$rowNumber}: lead source '{$source}' is not available for this company.";
                    continue;
                }
                $source = $normalizedSource;
            }

            $statusName = $this->optionalValue($data, ['status']);
            $status = $statusName !== null ? $statuses->get(Str::lower($statusName)) : null;
            if ($statusName !== null && ! $status) {
                $errors[] = "Row {$rowNumber}: lead status '{$statusName}' was not found.";
                continue;
            }

            $state = $this->resolveState($stateMap, $data);
            $city = $this->resolveCity($stateMap, $state, $data);

            if ($nextFollowUpRaw !== null && $nextFollowUp === null) {
                $errors[] = "Row {$rowNumber}: next follow up date '{$nextFollowUpRaw}' is invalid. Use a valid date like 2026-06-26.";
                continue;
            }

            $assignedUserIds = $this->resolveAssignedUsers($users, $data);
            if ($assignedUserIds === false) {
                $errors[] = "Row {$rowNumber}: one or more assigned employees were not found.";
                continue;
            }

            $customFields = [];
            foreach ($customFieldDefinitions as $field) {
                $fieldKey = $this->customFieldLookupKey($field);
                if ($fieldKey === '') {
                    continue;
                }

                $rawValue = $this->customFieldRawValue($data, $field);
                if (($field['type'] ?? null) === 'checkbox' && ! empty($field['required'])) {
                    $isChecked = filter_var($rawValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
                    if ($isChecked !== true) {
                        $errors[] = "Row {$rowNumber}: custom field '{$field['label']}' must be checked.";
                        continue 2;
                    }
                }

                if ($this->isCustomFieldEmpty($rawValue, $field['type'] ?? 'text')) {
                    if (! empty($field['required'])) {
                        $errors[] = "Row {$rowNumber}: custom field '{$field['label']}' is required.";
                        continue 2;
                    }

                    $customFields[$fieldKey] = ($field['type'] ?? '') === 'checkbox' ? false : null;
                    continue;
                }

                $normalizedValue = $this->normalizeCustomFieldValue($field, $rawValue);

                if (! $this->customFieldValueIsValid($field, $normalizedValue)) {
                    $errors[] = "Row {$rowNumber}: custom field '{$field['label']}' is invalid.";
                    continue 2;
                }

                $customFields[$fieldKey] = $normalizedValue;
            }

            $prepared[] = [
                'payload' => [
                    'company_id' => $this->company->id,
                    'lead_status_id' => $status?->id ?? $newLeadStatus->id,
                    'lead_category_id' => $category->id,
                    'assigned_to' => $assignedUserIds[0] ?? null,
                    'created_by' => $this->user->id,
                    'client_name' => $name,
                    'email' => $email !== null ? Str::lower($email) : null,
                    'mobile' => $phone,
                    'address' => $address,
                    'source' => $source,
                    'referral_person_name' => $this->optionalValue($data, ['referral_person_name']),
                    'gst_number' => $this->normalizeUpper($this->optionalValue($data, ['gst_number'])),
                    'pan_number' => $this->normalizeUpper($this->optionalValue($data, ['pan_number'])),
                    'state_id' => $state?->id ?? $this->defaultStateId,
                    'city_id' => $city?->id ?? $this->defaultCityId,
                    'estimated_value' => $this->numericValue($data, ['estimated_value']) ?? 0,
                    'priority' => $this->priorityValue($data, ['priority']) ?? 'medium',
                    'next_follow_up' => $nextFollowUp,
                    'custom_fields' => $customFields ?: null,
                ],
                'assigned_user_ids' => $assignedUserIds,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'lead_import_file' => $errors,
            ]);
        }

        DB::transaction(function () use ($prepared) {
            $subscriptionLimitService = app(SubscriptionLimitService::class);
            $clientSyncService = app(ClientSyncService::class);
            $projectConversionService = app(LeadProjectConversionService::class);

            foreach ($prepared as $item) {
                $payload = $item['payload'];
                $assignedUserIds = collect($item['assigned_user_ids'])
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                $subscriptionLimitService->ensureWithinLimit((int) $this->company->id, 'leads', 1, 'client_name');

                $lead = Lead::create($payload);
                if ($assignedUserIds !== []) {
                    $lead->assignedUsers()->sync($assignedUserIds);
                }

                $clientSyncService->upsert([
                    'company_id' => $lead->company_id,
                    'name' => $lead->client_name,
                    'email' => $lead->email,
                    'phone' => $lead->mobile,
                    'gst_number' => $lead->gst_number,
                    'pan_number' => $lead->pan_number,
                    'state_id' => $lead->state_id,
                    'city_id' => $lead->city_id,
                    'address' => $lead->address,
                ]);

                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $this->user->id,
                    'type' => 'status_change',
                    'description' => 'Lead imported with status: ' . ($lead->status?->name ?? 'New Lead'),
                ]);

                if (strtolower((string) $lead->status?->name) === 'won') {
                    $projectConversionService->convertLeadToProject($lead);
                }
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

    private function customFieldLookupKey(array $field): string
    {
        $key = Str::lower(trim((string) ($field['key'] ?? '')));

        if ($key !== '') {
            return $key;
        }

        return Str::of((string) ($field['label'] ?? ''))
            ->trim()
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();
    }

    private function customFieldRawValue(array $data, array $field): mixed
    {
        $possibleKeys = array_filter(array_unique([
            $this->customFieldLookupKey($field),
            Str::of((string) ($field['label'] ?? ''))->trim()->lower()->replace(['-', ' '], '_')->toString(),
        ]));

        foreach ($possibleKeys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return null;
    }

    private function requiredValue(array $data, array $keys): ?string
    {
        $value = $this->optionalValue($data, $keys);

        return $value !== null ? trim($value) : null;
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

    private function isCustomFieldEmpty(mixed $value, string $type): bool
    {
        if ($type === 'checkbox') {
            return false;
        }

        if ($value === null) {
            return true;
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        return trim((string) $value) === '';
    }

    private function normalizeCustomFieldValue(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'checkbox') {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if ($type === 'date') {
            return $this->dateValue($value);
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if ($type === 'number') {
            return is_numeric($value) ? (0 + $value) : $value;
        }

        return $value;
    }

    private function customFieldValueIsValid(array $field, mixed $value): bool
    {
        $type = (string) ($field['type'] ?? 'text');
        $options = collect($field['options'] ?? [])
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->values()
            ->all();

        return match ($type) {
            'email' => is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($value) <= 191,
            'textarea' => is_string($value) && mb_strlen($value) <= 2000,
            'number' => is_numeric($value),
            'date' => $this->isValidDateValue($value),
            'dropdown', 'radiobutton' => is_string($value) && mb_strlen($value) <= 191 && ($options === [] || in_array($value, $options, true)),
            'checkbox' => is_bool($value),
            default => is_string($value) && mb_strlen($value) <= 191,
        };
    }

    private function isValidDateValue(mixed $value): bool
    {
        return $this->parseDateValue($value) !== null;
    }

    private function numericValue(array $data, array $keys): ?float
    {
        $value = $this->optionalValue($data, $keys);

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    private function dateValue(mixed $value): ?string
    {
        return $this->parseDateValue($value);
    }

    private function priorityValue(array $data, array $keys): ?string
    {
        $value = Str::lower((string) $this->optionalValue($data, $keys));

        return in_array($value, ['low', 'medium', 'high'], true) ? $value : null;
    }

    private function normalizeUpper(?string $value): ?string
    {
        return $value !== null ? Str::upper($value) : null;
    }

    private function parseDateValue(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->toDateString();
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable) {
                // Fall through to string parsing below.
            }
        }

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        foreach ([
            'Y-m-d',
            'Y/m/d',
            'm/d/Y',
            'd/m/Y',
            'M j, Y',
            'F j, Y',
            'j M Y',
            'd-m-Y',
            'm-d-Y',
        ] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->toDateString();
            } catch (\Throwable) {
                // Try the next format.
            }
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveAssignedUsers(Collection $users, array $data): array|false
    {
        $assigned = $this->optionalValue($data, ['assigned_to', 'assigned_users', 'assigned_employees']);
        if ($assigned === null) {
            $defaultAssignee = app(LeadAssignmentService::class)->resolveLeadAssignee($this->company);
            if ($defaultAssignee) {
                return [$defaultAssignee->id];
            }

            return [];
        }

        $resolved = collect(explode(',', $assigned))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->map(function ($name) use ($users) {
                return $users->get(Str::lower($name))?->id;
            });

        if ($resolved->contains(null)) {
            return false;
        }

        return $resolved->filter()->unique()->values()->all();
    }

    private function resolveState(Collection $stateMap, array $data): ?State
    {
        $stateValue = $this->optionalValue($data, ['state']);

        if ($stateValue === null) {
            return null;
        }

        $lookupKey = Str::lower($stateValue);
        $state = $stateMap->get($lookupKey);

        if (! $state) {
            $existing = State::withTrashed()
                ->whereRaw('LOWER(name) = ?', [$lookupKey])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $state = $existing;
            } else {
                $state = State::create([
                    'name' => $stateValue,
                ]);
            }

            if (! $state->relationLoaded('cities')) {
                $state->setRelation('cities', collect());
            }

            $stateMap->put($lookupKey, $state);
        }

        return $state;
    }

    private function resolveCity(Collection $stateMap, ?State $state, array $data): ?City
    {
        $cityValue = $this->optionalValue($data, ['city']);

        if ($cityValue === null) {
            return null;
        }

        $cityLookupKey = Str::lower($cityValue);

        // If no state was provided in the row, check if the city matches any existing city in any state
        if (! $state) {
            foreach ($stateMap as $existingState) {
                $matchedCity = $existingState->cities->first(
                    fn ($c) => Str::lower(trim((string) $c->name)) === $cityLookupKey
                );
                if ($matchedCity) {
                    return $matchedCity;
                }
            }

            $existingCity = City::withTrashed()
                ->whereRaw('LOWER(name) = ?', [$cityLookupKey])
                ->first();

            if ($existingCity) {
                if ($existingCity->trashed()) {
                    $existingCity->restore();
                }
                return $existingCity;
            }

            return null;
        }

        // State is present
        if (! $state->relationLoaded('cities')) {
            $state->load('cities');
        }

        $city = $state->cities->first(
            fn ($c) => Str::lower(trim((string) $c->name)) === $cityLookupKey
        );

        if (! $city) {
            $existing = City::withTrashed()
                ->where('state_id', $state->id)
                ->whereRaw('LOWER(name) = ?', [$cityLookupKey])
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                $city = $existing;
            } else {
                $city = City::create([
                    'state_id' => $state->id,
                    'name' => $cityValue,
                ]);
            }

            $state->cities->push($city);
        }

        return $city;
    }
}
