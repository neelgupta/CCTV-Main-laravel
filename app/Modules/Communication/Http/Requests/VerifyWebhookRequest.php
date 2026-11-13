<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Public endpoint called by Meta. Authenticated session not required.
        return true;
    }

    public function rules(): array
    {
        return [
            'hub_mode' => ['required', 'string', 'in:subscribe'],
            'hub_verify_token' => ['required', 'string'],
            'hub_challenge' => ['required', 'string'],
            'phone_number_id' => ['nullable', 'string'], // Added optionally if we pass it to route/query to scope multi-number checks
        ];
    }
}
