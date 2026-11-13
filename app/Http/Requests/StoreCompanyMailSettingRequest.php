<?php

namespace App\Http\Requests;

use App\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyMailSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return (bool) ($user && ($user->isCompanyAdmin() || $user->isSuperAdmin()));
    }

    public function rules(): array
    {
        $company = $this->resolveCompany();
        $passwordRequired = ! (bool) $company?->mailSetting;

        return $this->baseRules($passwordRequired);
    }

    private function baseRules(bool $passwordRequired): array
    {
        return [
            'mailer' => ['required', 'string', Rule::in(['smtp'])],
            'host' => ['required', 'string', 'max:191'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:191'],
            'password' => [$passwordRequired ? 'required' : 'nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', Rule::in(['tls', 'ssl', 'none'])],
            'from_email' => ['required', 'email', 'max:191'],
            'from_name' => ['required', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    private function resolveCompany(): ?Company
    {
        $user = $this->user();

        if (! $user) {
            return null;
        }

        if ($user->company_id) {
            return Company::query()->with('mailSetting')->find($user->company_id);
        }

        return null;
    }
}
