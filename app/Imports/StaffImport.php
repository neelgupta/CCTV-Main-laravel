<?php

namespace App\Imports;

use App\Models\Role;
use App\Models\Staff;
use App\Models\StaffActivity;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class StaffImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    public function __construct(private readonly User $user)
    {
    }

    public function collection(Collection $rows): void
    {
        $companyId = (int) $this->user->company_id;
        $roles = Role::query()
            ->where('company_id', $companyId)
            ->where('slug', '!=', 'client')
            ->orderBy('name')
            ->get();
        $rolesByName = $roles->keyBy(fn (Role $role) => Str::lower(trim((string) $role->name)));
        $rolesBySlug = $roles->keyBy(fn (Role $role) => Str::lower(trim((string) $role->slug)));
        $defaultRole = $roles->first(fn (Role $role) => Str::lower((string) $role->slug) === 'employee')
            ?? $roles->first(fn (Role $role) => ! $role->isCompanyAdmin())
            ?? $roles->first();

        if (! $defaultRole) {
            throw ValidationException::withMessages([
                'staff_import_file' => ['No staff role is available for this company. Please create a role first.'],
            ]);
        }

        $existingUsers = User::query()
            ->where('company_id', $companyId)
            ->get()
            ->keyBy(fn (User $user) => Str::lower(trim((string) $user->email)));

        $prepared = [];
        $errors = [];
        $seenEmails = collect();

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $data = $this->normalizeRow($row->toArray());

            $name = $this->requiredValue($data, ['name']);
            $email = $this->requiredValue($data, ['email']);
            $password = $this->requiredValue($data, ['password']);
            $phone = $this->requiredValue($data, ['phone', 'contact_number']);

            if ($name === null || $email === null || $password === null || $phone === null) {
                $errors[] = "Row {$rowNumber}: name, email, password, and phone are required.";
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$rowNumber}: email must be a valid email address.";
                continue;
            }

            if (mb_strlen($password) < 8) {
                $errors[] = "Row {$rowNumber}: password must be at least 8 characters.";
                continue;
            }

            $phone = preg_replace('/\D+/', '', $phone);
            if (strlen((string) $phone) !== 10) {
                $errors[] = "Row {$rowNumber}: phone must be exactly 10 digits.";
                continue;
            }

            $normalizedEmail = Str::lower(trim($email));

            if ($seenEmails->contains($normalizedEmail) || $existingUsers->has($normalizedEmail)) {
                $errors[] = "Row {$rowNumber}: email '{$email}' is already in use.";
                continue;
            }

            $roleValue = $this->optionalValue($data, ['role', 'role_name']);
            $role = $roleValue !== null
                ? ($rolesByName->get(Str::lower($roleValue)) ?? $rolesBySlug->get(Str::lower($roleValue)))
                : $defaultRole;

            if (! $role) {
                $errors[] = "Row {$rowNumber}: role '{$roleValue}' was not found.";
                continue;
            }

            $joinDate = $this->dateValue($data, ['join_date']) ?? now()->toDateString();
            if ($joinDate > now()->toDateString()) {
                $errors[] = "Row {$rowNumber}: join date cannot be in the future.";
                continue;
            }

            $seenEmails->push($normalizedEmail);

            $prepared[] = [
                'user' => [
                    'company_id' => $companyId,
                    'name' => Str::limit(trim($name), 191, ''),
                    'email' => Str::limit($normalizedEmail, 191, ''),
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                    'role_id' => $role->id,
                    'role' => $role->slug,
                    'is_active' => true,
                ],
                'staff' => [
                    'company_id' => $companyId,
                    'name' => Str::limit(trim($name), 191, ''),
                    'role_id' => $role->id,
                    'department' => filled($this->optionalValue($data, ['department']))
                        ? Str::limit(trim((string) $this->optionalValue($data, ['department'])), 191, '')
                        : null,
                    'join_date' => $joinDate,
                    'contact_number' => $phone,
                ],
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages([
                'staff_import_file' => $errors,
            ]);
        }

        DB::transaction(function () use ($prepared, $companyId) {
            $subscriptionLimits = app(SubscriptionLimitService::class);

            foreach ($prepared as $item) {
                $subscriptionLimits->ensureWithinLimit($companyId, 'users', 1, 'email');
                $subscriptionLimits->ensureWithinLimit($companyId, 'staff', 1, 'name');

                $user = User::create($item['user']);

                $staff = Staff::create([
                    'user_id' => $user->id,
                    'employee_id' => Staff::generateEmployeeId($companyId),
                    ...$item['staff'],
                ]);

                StaffActivity::create([
                    'staff_id' => $staff->id,
                    'type' => 'Staff Imported',
                    'description' => 'Staff imported from spreadsheet.',
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
            return \Illuminate\Support\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
