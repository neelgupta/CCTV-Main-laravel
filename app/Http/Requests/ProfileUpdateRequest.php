<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $user = $this->user();
        $isSuperAdmin = (bool) ($user?->isSuperAdmin());

        $emailRules = [
            'required',
            'string',
            'lowercase',
            'email',
            'max:255',
            Rule::unique(User::class)->ignore($user?->id),
        ];

        if ($user && !$isSuperAdmin) {
            $emailRules[] = Rule::unique('companies', 'email')->ignore(optional($user->company)->id);
        }

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ];

        if ($isSuperAdmin) {
            return $rules;
        }

        $rules['phone_number'] = ['required', 'digits:10'];
        $rules['address'] = ['required', 'string', 'max:500'];
        $rules['logo'] = ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'];

        return $rules;
    }

    /**
     * Custom error messages
     */
    public function messages(): array
    {
        return [
            'mobile_number.digits' => 'The mobile number must be exactly 10 digits.',
            'phone_number.digits' => 'The phone number must be exactly 10 digits.',
        ];
    }
}
