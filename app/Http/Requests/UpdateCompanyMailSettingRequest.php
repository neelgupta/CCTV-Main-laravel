<?php

namespace App\Http\Requests;

class UpdateCompanyMailSettingRequest extends StoreCompanyMailSettingRequest
{
    public function rules(): array
    {
        return [
            'mailer' => ['required', 'string', \Illuminate\Validation\Rule::in(['smtp'])],
            'host' => ['required', 'string', 'max:191'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:191'],
            'password' => ['nullable', 'string', 'max:255'],
            'encryption' => ['nullable', 'string', \Illuminate\Validation\Rule::in(['tls', 'ssl', 'none'])],
            'from_email' => ['required', 'email', 'max:191'],
            'from_name' => ['required', 'string', 'max:191'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
