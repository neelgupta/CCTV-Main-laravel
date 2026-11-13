<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterCompanyRequest;
use App\Models\Company;
use App\Models\Role;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

use Illuminate\Support\Facades\Log;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterCompanyRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $user = DB::transaction(function () use ($validated, $request) {
                $company = Company::create([
                    'name' => $validated['company_name'],
                    'email' => $validated['email'],
                    'mobile_number' => $validated['phone_number'],
                    'phone_number' => $validated['phone_number'],
                    'is_active' => true,
                    'is_onboarded' => false,
                    'onboarding_step' => 4,
                    'allow_growth_staff_tracking' => false,
                    'allow_appointment_module' => false,
                    'allow_create_company_from_lead' => false,
                ]);

                $companyAdminRole = Role::firstOrCreate(
                    ['company_id' => $company->id, 'slug' => 'company_admin'],
                    ['name' => 'Company Admin']
                );

                $employeeRole = Role::firstOrCreate(
                    ['company_id' => $company->id, 'slug' => 'employee'],
                    ['name' => 'Employee']
                );

                if ($companyAdminRole->permissions()->count() === 0) {
                    $companyAdminRole->permissions()->sync(\App\Models\Permission::query()->pluck('id')->all());
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
                        \App\Models\Permission::query()->whereIn('key', $employeePermissionKeys)->pluck('id')->all()
                    );
                }

                $existingUser = User::query()
                    ->whereRaw('LOWER(email) = ?', [Str::lower(trim((string) $validated['email']))])
                    ->first();

                if ($existingUser) {
                    $existingUser->update([
                        'company_id' => $company->id,
                        'name' => $validated['company_name'] . ' Admin',
                        'role' => 'company_admin',
                        'role_id' => $companyAdminRole->id,
                        'password' => Hash::make($validated['password']),
                        'is_active' => true,
                        'email_verified_at' => $existingUser->email_verified_at ?? now(),
                        'terms_accepted_at' => now(),
                        'terms_version' => config('legal.terms_version'),
                        'terms_accepted_ip' => $request->ip(),
                    ]);
                    $user = $existingUser;
                } else {
                    $user = User::create([
                        'company_id' => $company->id,
                        'name' => $validated['company_name'] . ' Admin',
                        'email' => $validated['email'],
                        'email_verified_at' => now(),
                        'password' => Hash::make($validated['password']),
                        'role' => 'company_admin',
                        'role_id' => $companyAdminRole->id,
                        'is_active' => true,
                        'terms_accepted_at' => now(),
                        'terms_version' => config('legal.terms_version'),
                        'terms_accepted_ip' => $request->ip(),
                    ]);
                }

                Staff::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'company_id' => $company->id,
                    ],
                    [
                        'employee_id' => Staff::generateEmployeeId($company->id),
                        'name' => $user->name,
                        'contact_number' => $company->mobile_number ?: $company->phone_number,
                        'join_date' => now()->toDateString(),
                        'role_id' => $companyAdminRole->id,
                    ]
                );

                return $user;
            });
        } catch (QueryException $exception) {
            if ($this->isDuplicateCompanyRegistrationException($exception)) {
                $errorField = $this->isDuplicatePhoneException($exception) ? 'phone_number' : 'email';
                $errorMessage = $errorField === 'phone_number'
                    ? 'This company phone number is already registered. Please sign in to your existing account or contact support.'
                    : 'This company email is already registered. Please sign in to your existing account or contact support.';

                return back()
                    ->withErrors([
                        $errorField => $errorMessage,
                    ])
                    ->withInput();
            }

            throw $exception;
        }

        try {
            event(new Registered($user));
        } catch (\Throwable $e) {
            Log::warning('Registration verification email failed to send, continuing redirect.', [
                'user_id' => $user->id ?? null,
                'email' => $validated['email'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        Auth::logout();

        return redirect()->route('login')
            ->with('registered_email', $validated['email'])
            ->with('registration_success', [
                'company_name' => $validated['company_name'],
                'email' => $validated['email'],
            ])
            ->with('status', 'Thank you for registering! Your company workspace for "' . $validated['company_name'] . '" has been created successfully. Please sign in to continue.');
    }

    private function isDuplicateCompanyRegistrationException(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'duplicate')
            && (
                str_contains($message, 'companies.email')
                || str_contains($message, 'companies_email_unique')
                || str_contains($message, 'users.email')
                || str_contains($message, 'users_email_unique')
                || str_contains($message, 'companies.mobile_number')
                || str_contains($message, 'companies_mobile_number_unique')
                || str_contains($message, 'companies.phone_number')
                || str_contains($message, 'companies_phone_number_unique')
                || str_contains($message, 'mobile_number')
                || str_contains($message, 'phone_number')
                || str_contains($message, 'email')
            );
    }

    private function isDuplicatePhoneException(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'mobile_number')
            || str_contains($message, 'phone_number');
    }
}
