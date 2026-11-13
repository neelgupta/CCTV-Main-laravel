<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class RegisterCompanyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_name' => filled($this->input('company_name'))
                ? trim((string) $this->input('company_name'))
                : null,
            'email' => filled($this->input('email'))
                ? Str::lower(trim((string) $this->input('email')))
                : null,
            'phone_number' => filled($this->input('phone_number'))
                ? preg_replace('/\D+/', '', (string) $this->input('phone_number'))
                : null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'min:2', 'max:191', 'regex:/^(?=.*[A-Za-z])[A-Za-z0-9&.,()\'\\-\\s]+$/'],
            'email' => [
                'required',
                'string',
                'email',
                'lowercase',
                'max:191',
                Rule::unique('companies', 'email'),
                function ($attribute, $value, $fail) {
                    $normalizedEmail = Str::lower(trim((string) $value));
                    $existingUser = User::query()
                        ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                        ->first();

                    if ($existingUser && ! $existingUser->isClient()) {
                        $fail('This company email is already registered. Please sign in to your existing account or contact support.');
                    }
                },
            ],
            'password' => [
                'required',
                'confirmed',
                Password::min(8)->letters()->mixedCase()->numbers()->symbols(),
            ],
            'phone_number' => [
                'required',
                'digits:10',
                Rule::unique('companies', 'mobile_number'),
                Rule::unique('companies', 'phone_number'),
            ],
            'terms' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.regex' => 'The company name must contain at least one letter and may only include letters, numbers, spaces, and basic punctuation.',
            'email.unique' => 'This company email is already registered. Please sign in to your existing account or contact support.',
            'phone_number.unique' => 'This company phone number is already registered. Please sign in to your existing account or contact support.',
            'phone_number.digits' => 'The phone number must be exactly 10 digits.',
            'terms.accepted' => 'Please accept the Terms & Conditions to create your account.',
        ];
    }
}
