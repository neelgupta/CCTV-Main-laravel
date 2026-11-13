<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class ConnectRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Enforce company / tenant authorization context.
        // User must be authenticated and associated with a company.
        return Auth::check() && Auth::user()->company_id !== null;
    }

    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'phone_number' => ['required', 'string', 'max:50'],
            'phone_number_id' => ['required', 'string', 'max:255'],
            'business_account_id' => ['required', 'string', 'max:255'],
            'app_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['required', 'string'],
            'verify_token' => ['required', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:255'],
        ];
    }
}
