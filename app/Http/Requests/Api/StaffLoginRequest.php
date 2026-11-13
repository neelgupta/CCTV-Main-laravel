<?php

namespace App\Http\Requests\Api;

use App\Models\Staff;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Contracts\Validation\Validator;

class StaffLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! filled($this->input('mobile_number')) && filled($this->input('mobile'))) {
            $this->merge([
                'mobile_number' => $this->input('mobile'),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'email' => ['required_without_all:employee_id,mobile_number,user_id', 'nullable', 'string', 'email'],
            'employee_id' => ['required_without_all:email,mobile_number,user_id', 'nullable', 'string', 'max:191'],
            'mobile_number' => ['required_without_all:email,employee_id,user_id', 'nullable', 'string', 'max:20'],
            'user_id' => ['required_without_all:email,employee_id,mobile_number', 'nullable', 'string', 'max:191'],
            'company_code' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:191'],
            'platform' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function messages(): array
    {
        $identifierMessage = 'The email field is required.';

        return [
            'email.required_without_all' => $identifierMessage,
            'employee_id.required_without_all' => $identifierMessage,
            'mobile_number.required_without_all' => $identifierMessage,
            'user_id.required_without_all' => $identifierMessage,
        ];
    }

    public function authenticateEmployee(): User
    {
        $this->ensureIsNotRateLimited();

        $user = $this->resolveUser();

        if (! $user || ! Hash::check((string) $this->string('password'), (string) $user->password)) {
            RateLimiter::hit($this->throttleKey());

            $this->throwLoginFailure(
                'Invalid user ID, employee ID, email, mobile number, company code, or password.',
                'invalid_credentials',
                401
            );
        }

        if (! $user->is_active) {
            $this->throwLoginFailure(
                'Your account is inactive. Please contact your administrator.',
                'inactive_account',
                403
            );
        }

        if (! $user->company_id || ! $user->company || ! $user->company->is_active) {
            $this->throwLoginFailure(
                'Your company account is deactivated. Please contact admin.',
                'company_inactive',
                403
            );
        }

        $role = $user->normalizedRole();

        // if ($role !== 'company_admin' && $role !== 'companyadmin' && (! $user->staff || (int) $user->staff->user_id !== (int) $user->id)) {
        //     $this->throwLoginFailure(
        //         'Only linked staff accounts can log in from the mobile app.',
        //         'staff_profile_required',
        //         403
        //     );
        // }

        // if (! in_array($role, ['employee', 'company_admin', 'companyadmin'], true)) {
        //     $this->throwLoginFailure(
        //         'Only company staff and company admin accounts can log in from the mobile app.',
        //         'invalid_staff_role',
        //         403
        //     );
        // }

        if (! app(SubscriptionLimitService::class)->userHasAssignedSubscriptionAccess($user)) {
            $this->throwLoginFailure(
                app(SubscriptionLimitService::class)->expiredAssignedSubscriptionMessage($user),
                'subscription_expired',
                403
            );
        }

        RateLimiter::clear($this->throttleKey());

        return $user;
    }

    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $this->throwLoginFailure(
            trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => (int) ceil($seconds / 60),
            ]),
            'too_many_attempts',
            429
        );
    }

    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->credentialValue().'|'.$this->normalizedCompanyCode().'|'.$this->ip()));
    }

    public function deviceName(): string
    {
        $deviceName = trim((string) $this->input('device_name', ''));

        if ($deviceName !== '') {
            return $deviceName;
        }

        $userAgent = trim((string) $this->userAgent());

        return Str::limit($userAgent !== '' ? $userAgent : 'mobile-app', 100, '');
    }

    private function resolveUser(): ?User
    {
        if (filled($this->input('employee_id'))) {
            return Staff::query()
                ->with(['user.company', 'user.staff', 'user.roleRecord.permissions'])
                ->where('employee_id', trim((string) $this->input('employee_id')))
                ->whereHas('user.company', function ($query): void {
                    $query->where('company_code', $this->normalizedCompanyCode());
                })
                ->first()
                ?->user;
        }

        if (filled($this->input('mobile_number'))) {
            return $this->resolveUserByMobileDigits($this->normalizedMobileDigits());
        }

        if (filled($this->input('user_id'))) {
            $identifier = trim((string) $this->input('user_id'));

            if ($this->isEmailIdentifier($identifier)) {
                return $this->resolveUserByEmail($identifier);
            }

            return $this->resolveUserByMobileDigits($this->normalizeDigits($identifier));
        }

        if (filled($this->input('email'))) {
            return $this->resolveUserByEmail(trim((string) $this->input('email')));
        }

        return null;
    }

    private function resolveUserByEmail(string $email): ?User
    {
        $user = User::query()
            ->with(['company', 'staff', 'roleRecord.permissions'])
            ->where('email', $email)
            ->whereHas('company', function ($companyQuery): void {
                $companyQuery->where('company_code', $this->normalizedCompanyCode());
            })
            ->first();

        if ($user && $user->isCompanyAdmin() && ! $user->staff) {
            app(\App\Services\CompanyAdminSeatService::class)->ensureAdminStaffProfile((int) $user->company_id, $user);
            $user->load('staff');
        }

        return $user;
    }

    private function resolveUserByMobileDigits(string $mobileDigits): ?User
    {
        if ($mobileDigits === '') {
            return null;
        }

        $userFromStaff = Staff::query()
            ->with(['user.company', 'user.staff', 'user.roleRecord.permissions'])
            ->whereHas('user.company', function ($query): void {
                $query->where('company_code', $this->normalizedCompanyCode());
            })
            ->whereNotNull('contact_number')
            ->get()
            ->first(fn (Staff $staff) => $this->mobileMatchesContactNumber($staff->contact_number, $mobileDigits))
            ?->user;

        if ($userFromStaff) {
            return $userFromStaff;
        }

        // Fallback: Check company admin contact numbers directly from company
        $adminUser = User::query()
            ->with(['company', 'staff', 'roleRecord.permissions'])
            ->whereHas('company', function ($companyQuery): void {
                $companyQuery->where('company_code', $this->normalizedCompanyCode());
            })
            ->where(function ($query) {
                $query->whereIn('role', ['company_admin', 'companyadmin'])
                    ->orWhereHas('roleRecord', function ($roleQuery) {
                        $roleQuery->whereIn('slug', ['company_admin', 'companyadmin']);
                    });
            })
            ->get()
            ->first(function (User $user) use ($mobileDigits) {
                $companyMobile = (string) ($user->company?->mobile_number ?: $user->company?->phone_number);
                return $this->mobileMatchesContactNumber($companyMobile, $mobileDigits);
            });

        if ($adminUser && ! $adminUser->staff) {
            app(\App\Services\CompanyAdminSeatService::class)->ensureAdminStaffProfile((int) $adminUser->company_id, $adminUser);
            $adminUser->load('staff');
        }

        return $adminUser;
    }

    private function credentialField(): string
    {
        if (filled($this->input('employee_id'))) {
            return 'employee_id';
        }

        if (filled($this->input('mobile_number')) || filled($this->input('mobile'))) {
            return 'mobile_number';
        }

        if (filled($this->input('user_id'))) {
            return 'user_id';
        }

        return 'email';
    }

    private function credentialValue(): string
    {
        if (filled($this->input('employee_id'))) {
            return trim((string) $this->input('employee_id'));
        }

        if (filled($this->input('mobile_number')) || filled($this->input('mobile'))) {
            return $this->normalizedMobileDigits();
        }

        if (filled($this->input('user_id'))) {
            $identifier = trim((string) $this->input('user_id'));

            return $this->isEmailIdentifier($identifier)
                ? Str::lower($identifier)
                : $this->normalizeDigits($identifier);
        }

        return Str::lower(trim((string) $this->input('email')));
    }

    private function normalizedMobileDigits(): string
    {
        return $this->normalizeDigits(trim((string) $this->input('mobile_number')));
    }

    private function normalizeDigits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }

    private function isEmailIdentifier(string $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function mobileMatchesContactNumber(?string $contactNumber, string $inputDigits): bool
    {
        $storedDigits = preg_replace('/\D+/', '', (string) $contactNumber) ?: '';

        if ($inputDigits === '' || $storedDigits === '') {
            return false;
        }

        if ($inputDigits === $storedDigits) {
            return true;
        }

        $inputLast10 = strlen($inputDigits) > 10 ? substr($inputDigits, -10) : $inputDigits;
        $storedLast10 = strlen($storedDigits) > 10 ? substr($storedDigits, -10) : $storedDigits;

        return $inputLast10 !== '' && $inputLast10 === $storedLast10;
    }

    private function normalizedCompanyCode(): string
    {
        return Str::upper(trim((string) $this->input('company_code')));
    }

    protected function failedValidation(Validator $validator): void
    {
        $errors = $validator->errors()->toArray();

        $credentialFields = [
            'email',
            'employee_id',
            'mobile_number',
            'user_id',
        ];

        $hasCredentialError = collect($credentialFields)
            ->every(fn ($field) => isset($errors[$field]));

        if ($hasCredentialError) {
            $errors = [
                'login' => [
                    'The email field is required.'
                ]
            ];
        }

        throw new HttpResponseException(response()->json([
            'message' => collect($errors)->flatten()->first(),
            'error_code' => 'validation_failed',
            'errors' => $errors,
        ], 422));
    }

    private function throwLoginFailure(string $message, string $errorCode, int $status): never
    {
        throw new HttpResponseException($this->loginFailureResponse($message, $errorCode, $status));
    }

    private function loginFailureResponse(string $message, string $errorCode, int $status): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'error_code' => $errorCode,
            'errors' => [
                $this->credentialField() => [$message],
            ],
        ], $status);
    }
}
