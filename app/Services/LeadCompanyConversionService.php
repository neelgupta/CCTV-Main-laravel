<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LeadCompanyConversionService
{
    /**
     * Convert an eligible lead into a new company.
     *
     * @param Lead $lead
     * @param array $overrideData Optional custom inputs from user (e.g. password, company_name, phone_number, etc.)
     * @return Company
     * @throws ValidationException
     */
    public function convertLeadToCompany(Lead $lead, array $overrideData = []): Company
    {
        $lead->loadMissing('company', 'createdCompany');

        $parentCompany = $lead->company;
        if (! $parentCompany || ! (bool) $parentCompany->allow_create_company_from_lead) {
            throw ValidationException::withMessages([
                'lead' => 'Create company from lead functionality is not allowed for your organization. Please contact the super admin.',
            ]);
        }

        if ($lead->createdCompany) {
            throw ValidationException::withMessages([
                'lead' => 'A company has already been created from this lead: ' . $lead->createdCompany->name,
            ]);
        }

        $email = Str::lower(trim((string) ($overrideData['email'] ?? $lead->email)));
        if (blank($email)) {
            throw ValidationException::withMessages([
                'email' => 'Lead must have a valid email address to create a company.',
            ]);
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Please provide a valid email address.',
            ]);
        }

        // Check unique constraints for company email and phone/mobile number
        $emailExists = Company::query()->where('email', $email)->exists();
        $cleanedPhone = preg_replace('/\D+/', '', (string) ($overrideData['phone_number'] ?? $lead->mobile ?? $lead->phone));
        $phoneExists = filled($cleanedPhone) && Company::query()
            ->where('mobile_number', $cleanedPhone)
            ->orWhere('phone_number', $cleanedPhone)
            ->exists();

        if ($emailExists && $phoneExists) {
            throw ValidationException::withMessages([
                'email' => 'A company with this email and phone number already exists.',
                'phone_number' => 'A company with this email and phone number already exists.',
            ]);
        }

        if ($emailExists) {
            throw ValidationException::withMessages([
                'email' => 'A company with this email already exists.',
            ]);
        }

        $existingUser = User::query()->where('email', $email)->first();
        if ($existingUser) {
            // If the user account is an internal staff member, company admin, or super admin of another organization,
            // they cannot be converted into a new company.
            if (! $existingUser->isClient()) {
                throw ValidationException::withMessages([
                    'email' => 'A company staff or administrator account with this email already exists.',
                ]);
            }
        }

        $companyName = trim((string) ($overrideData['company_name'] ?? $lead->client_name));
        if (blank($companyName) || strlen($companyName) < 2) {
            throw ValidationException::withMessages([
                'company_name' => 'Please provide a valid company name (at least 2 characters).',
            ]);
        }

        // Mobile / Phone number
        if (blank($cleanedPhone) || strlen($cleanedPhone) !== 10) {
            throw ValidationException::withMessages([
                'phone_number' => 'A valid 10-digit mobile number is required to create a company.',
            ]);
        }

        if ($phoneExists) {
            throw ValidationException::withMessages([
                'phone_number' => 'A company with this mobile/phone number already exists.',
            ]);
        }

        $rawPassword = trim((string) ($overrideData['password'] ?? ''));
        if (blank($rawPassword) || strlen($rawPassword) < 8) {
            throw ValidationException::withMessages([
                'password' => 'Password is required and must be at least 8 characters.',
            ]);
        }
        $password = $rawPassword;

        // Address and location details
        $address = filled($overrideData['address'] ?? null)
            ? trim((string) $overrideData['address'])
            : ($lead->address ? trim((string) $lead->address) : null);

        $state = filled($overrideData['state'] ?? null)
            ? trim((string) $overrideData['state'])
            : ($lead->state?->name ?? null);

        $city = filled($overrideData['city'] ?? null)
            ? trim((string) $overrideData['city'])
            : ($lead->city?->name ?? null);

        $adminName = filled($overrideData['admin_name'] ?? null)
            ? trim((string) $overrideData['admin_name'])
            : $companyName . ' Admin';

        return DB::transaction(function () use (
            $lead,
            $companyName,
            $email,
            $cleanedPhone,
            $password,
            $address,
            $state,
            $city,
            $adminName,
            $existingUser
        ) {
            // Create company configured to enter onboarding step 4
            $company = Company::create([
                'name' => $companyName,
                'email' => $email,
                'mobile_number' => $cleanedPhone,
                'phone_number' => $cleanedPhone,
                'address' => $address,
                'city' => $city,
                'state' => $state,
                'business_type' => 'CCTV',
                'gst_number' => $lead->gst_number ?: null,
                'pan_number' => $lead->pan_number ?: null,
                'is_active' => true,
                'is_onboarded' => false,
                'onboarding_step' => 4,
                'allow_growth_staff_tracking' => false,
                'allow_appointment_module' => false,
                'allow_create_company_from_lead' => false,
            ]);

            // Set up default roles
            $companyAdminRole = Role::firstOrCreate(
                ['company_id' => $company->id, 'slug' => 'company_admin'],
                ['name' => 'Company Admin']
            );

            $employeeRole = Role::firstOrCreate(
                ['company_id' => $company->id, 'slug' => 'employee'],
                ['name' => 'Employee']
            );

            if ($companyAdminRole->permissions()->count() === 0) {
                $companyAdminRole->permissions()->sync(Permission::query()->pluck('id')->all());
            }

            if ($employeeRole->permissions()->count() === 0) {
                $employeePermissionKeys = [
                    'dashboard.view',
                    'leads.view', 'leads.create', 'leads.edit', 'leads.status.update', 'leads.comment',
                    'leads.quotation.manage', 'leads.survey.upload', 'leads.documents.upload', 'leads.summary.download',
                    'projects.view', 'projects.create', 'projects.edit', 'projects.stage.update', 'projects.assets.manage', 'projects.comment',
                    'support.view', 'support.create', 'support.edit',
                    'service.view',
                    'subscription.view', 'integrations.view', 'clients.view', 'amc.view', 'gallery.view',
                ];
                $employeeRole->permissions()->sync(
                    Permission::query()->whereIn('key', $employeePermissionKeys)->pluck('id')->all()
                );
            }

            // Create or update Company Admin User
            // If the user already exists as a client, upgrade them into the new company's admin
            if ($existingUser) {
                $existingUser->update([
                    'company_id' => $company->id,
                    'name' => $adminName,
                    'role' => 'company_admin',
                    'role_id' => $companyAdminRole->id,
                    'password' => Hash::make($password),
                    'is_active' => true,
                    'email_verified_at' => $existingUser->email_verified_at ?? now(),
                    'terms_accepted_at' => $existingUser->terms_accepted_at ?? now(),
                    'terms_version' => $existingUser->terms_version ?? config('legal.terms_version'),
                    'terms_accepted_ip' => request()->ip(),
                ]);
                $user = $existingUser;
            } else {
                $user = User::create([
                    'company_id' => $company->id,
                    'name' => $adminName,
                    'email' => $email,
                    'email_verified_at' => now(),
                    'password' => Hash::make($password),
                    'role' => 'company_admin',
                    'role_id' => $companyAdminRole->id,
                    'is_active' => true,
                    'terms_accepted_at' => now(),
                    'terms_version' => config('legal.terms_version'),
                    'terms_accepted_ip' => request()->ip(),
                ]);
            }

            // Create Staff Profile for Admin
            Staff::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'company_id' => $company->id,
                ],
                [
                    'employee_id' => Staff::generateEmployeeId($company->id),
                    'name' => $user->name,
                    'contact_number' => $cleanedPhone,
                    'join_date' => now()->toDateString(),
                    'role_id' => $companyAdminRole->id,
                ]
            );

            // Link lead to the created company
            $lead->update([
                'created_company_id' => $company->id,
            ]);

            // Log activity on lead
            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'company_conversion',
                'description' => "Company '{$company->name}' was created from this lead with admin email {$company->email}.",
                'occurred_at' => now(),
            ]);

            try {
                event(new Registered($user));
            } catch (\Throwable $e) {
                Log::warning('Registration email failed when converting lead to company: ' . $e->getMessage(), [
                    'lead_id' => $lead->id,
                    'company_id' => $company->id,
                    'user_id' => $user->id,
                ]);
            }

            return $company;
        });
    }
}

