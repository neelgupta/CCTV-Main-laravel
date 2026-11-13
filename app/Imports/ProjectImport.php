<?php

namespace App\Imports;

use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStage;
use App\Models\User;
use App\Services\ClientSyncService;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProjectImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
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
        ProjectStage::ensureDefaults($this->company->id);

        $stageMap = ProjectStage::query()
            ->where('company_id', $this->company->id)
            ->orderBy('order')
            ->get()
            ->mapWithKeys(function (ProjectStage $stage) {
                $label = Str::lower(trim((string) $stage->name));
                $key = in_array((string) $stage->key, ['drawing', 'new-project'], true) ? 'new-project' : (string) $stage->key;

                return [$label => $key];
            });
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
        $leadMap = Lead::query()
            ->where('company_id', $this->company->id)
            ->get()
            ->keyBy(fn (Lead $lead) => Str::lower(trim((string) $lead->client_name)));
        $customFieldDefinitions = collect($this->company->projectCustomFieldDefinitions())->values();
        $clientMap = Client::query()
            ->where('company_id', $this->company->id)
            ->get()
            ->keyBy(fn (Client $client) => Str::lower(trim((string) $client->name)));
        $existingReferenceNos = Project::query()
            ->whereNotNull('reference_no')
            ->pluck('reference_no')
            ->map(fn ($value) => Str::lower(trim((string) $value)));
        $seenReferenceNos = collect();

        $prepared = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            $projectName = $this->requiredValue($data, ['project_name', 'name']);
            $clientName = $this->requiredValue($data, ['client_name']);
            $email = $this->requiredValue($data, ['email', 'client_email']);
            $phone = $this->requiredValue($data, ['phone', 'client_phone', 'client_mobile']);
            $address = $this->requiredValue($data, ['address', 'client_address']);

            if ($projectName === null || $clientName === null || $email === null || $phone === null || $address === null) {
                $errors[] = "Row {$rowNumber}: project name, client name, email, phone, and address are required.";
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: email must be a valid email address.";
                continue;
            }

            $stageValue = $this->optionalValue($data, ['current_stage', 'stage']);
            $currentStage = $stageValue !== null
                ? ($stageMap->get(Str::lower($stageValue)) ?? Str::lower(str_replace(' ', '-', $stageValue)))
                : 'new-project';

            if (in_array($currentStage, ['drawing', 'new-project'], true)) {
                $currentStage = 'new-project';
            }

            if ($stageValue !== null && ! in_array($currentStage, $stageMap->values()->unique()->all(), true)) {
                $errors[] = "Row {$rowNumber}: project stage '{$stageValue}' was not found.";
                continue;
            }

            $status = Str::lower((string) $this->optionalValue($data, ['status']));
            $status = in_array($status, ['on_track', 'at_risk', 'delayed', 'completed'], true) ? $status : 'on_track';

            $state = $this->resolveState($stateMap, $data);
            $city = $this->resolveCity($state, $data);

            if ($this->hasStateOrCityValue($data) && (! $state || ! $city)) {
                $errors[] = "Row {$rowNumber}: state and city must match an existing location.";
                continue;
            }

            $manager = $this->resolveUser($users, $data, ['project_manager', 'project_manager_name', 'manager']);
            if ($this->optionalValue($data, ['project_manager', 'project_manager_name', 'manager']) !== null && ! $manager) {
                $errors[] = "Row {$rowNumber}: project manager was not found.";
                continue;
            }

            $teamMembers = $this->resolveTeamMembers($users, $data);
            if ($teamMembers === false) {
                $errors[] = "Row {$rowNumber}: one or more team members were not found.";
                continue;
            }

            $leadName = $this->optionalValue($data, ['lead_name', 'lead']);
            $lead = $leadName !== null ? $leadMap->get(Str::lower($leadName)) : null;
            if ($leadName !== null && ! $lead) {
                $errors[] = "Row {$rowNumber}: linked lead '{$leadName}' was not found.";
                continue;
            }

            $matchingClient = $clientMap->get(Str::lower($clientName));
            $referenceNo = $this->optionalValue($data, ['reference_no']);

            if ($referenceNo !== null) {
                $normalizedReferenceNo = Str::lower(trim($referenceNo));

                if ($existingReferenceNos->contains($normalizedReferenceNo) || $seenReferenceNos->contains($normalizedReferenceNo)) {
                    $errors[] = "Row {$rowNumber}: reference number '{$referenceNo}' already exists.";
                    continue;
                }

                $seenReferenceNos->push($normalizedReferenceNo);
            }

            $startDate = $this->dateValue($data, ['start_date']);
            $endDate = $this->dateValue($data, ['end_date']);
            if ($startDate !== null && $endDate !== null && $endDate < $startDate) {
                $errors[] = "Row {$rowNumber}: end date must be the same as or later than the start date.";
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

                if ($this->isCustomFieldEmpty($rawValue, (string) ($field['type'] ?? 'text'))) {
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

            $normalizedClientEmail = Str::lower($email);
            if ($this->company->isInternalStaffOrAdminEmail($normalizedClientEmail)) {
                $normalizedClientEmail = null;
            }

            $prepared[] = [
                'project' => [
                    'company_id' => $this->company->id,
                    'reference_no' => $referenceNo,
                    'lead_id' => $lead?->id,
                    'name' => $projectName,
                    'client_name' => $clientName,
                    'state_id' => $state?->id ?? $matchingClient?->state_id ?? $lead?->state_id ?? $this->defaultStateId,
                    'city_id' => $city?->id ?? $matchingClient?->city_id ?? $lead?->city_id ?? $this->defaultCityId,
                    'description' => $this->optionalValue($data, ['description']),
                    'current_stage' => $currentStage,
                    'project_manager_id' => $manager?->id ?? $this->user->id,
                    'team_member_ids' => collect($teamMembers)->push($manager?->id)->filter()->unique()->values()->all(),
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $status,
                    'custom_fields' => $customFields ?: null,
                ],
                'client' => [
                    'company_id' => $this->company->id,
                    'name' => $clientName,
                    'email' => $normalizedClientEmail,
                    'mobile' => $phone,
                    'state_id' => $state?->id ?? $matchingClient?->state_id ?? $lead?->state_id ?? $this->defaultStateId,
                    'city_id' => $city?->id ?? $matchingClient?->city_id ?? $lead?->city_id ?? $this->defaultCityId,
                    'address' => $address,
                ],
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'project_import_file' => $errors,
            ]);
        }

        DB::transaction(function () use ($prepared) {
            $subscriptionLimitService = app(SubscriptionLimitService::class);
            $clientSyncService = app(ClientSyncService::class);

            foreach ($prepared as $item) {
                $subscriptionLimitService->ensureWithinLimit((int) $this->company->id, 'projects', 1, 'name');

                $project = Project::create($item['project']);
                $clientSyncService->upsert($item['client']);

                ProjectActivity::create([
                    'project_id' => $project->id,
                    'user_id' => $this->user->id,
                    'type' => 'project',
                    'description' => 'Project imported.',
                    'occurred_at' => now(),
                ]);
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

    private function requiredValue(array $data, array $keys): ?string
    {
        return $this->optionalValue($data, $keys);
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

    private function dateValue(array $data, array $keys): ?string
    {
        $value = $this->optionalValue($data, $keys);

        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
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

    private function hasStateOrCityValue(array $data): bool
    {
        return $this->optionalValue($data, ['state']) !== null || $this->optionalValue($data, ['city']) !== null;
    }

    private function resolveUser(Collection $users, array $data, array $keys): ?User
    {
        $value = $this->optionalValue($data, $keys);

        return $value !== null ? $users->get(Str::lower($value)) : null;
    }

    private function resolveTeamMembers(Collection $users, array $data): array|false
    {
        $value = $this->optionalValue($data, ['team_members', 'team_member_names']);
        if ($value === null) {
            return [];
        }

        $resolved = collect(explode(',', $value))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->map(fn ($name) => $users->get(Str::lower($name))?->id);

        if ($resolved->contains(null)) {
            return false;
        }

        return $resolved->unique()->values()->all();
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

        if ($type === 'date') {
            try {
                return Carbon::parse($value)->toDateString();
            } catch (\Throwable) {
                return $value;
            }
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
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
