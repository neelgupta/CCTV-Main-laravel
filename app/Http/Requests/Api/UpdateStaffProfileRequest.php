<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateStaffProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'name' => fn () => $this->normalizeText($this->input('name')),
           
            'phone' => fn () => $this->normalizePhone($this->input('phone')),
            'address' => fn () => $this->normalizeText($this->input('address')),
            'city' => fn () => $this->normalizeText($this->input('city')),
            'state' => fn () => $this->normalizeText($this->input('state')),
            'country' => fn () => $this->normalizeText($this->input('country')),
            'zipcode' => fn () => $this->normalizeText($this->input('zipcode')),
        ] as $field => $normalizer) {
            if ($this->exists($field)) {
                $normalized[$field] = $normalizer();
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'phone' => ['nullable', 'digits:10'],
            'address' => ['nullable', 'string', 'max:1000'],
            'city' => ['nullable', 'string', 'max:191'],
            'state' => ['nullable', 'string', 'max:191'],
            'country' => ['nullable', 'string', 'max:191'],
            'zipcode' => ['nullable', 'string', 'max:20'],
            'profile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Name is required.',
            'phone.digits' => 'Phone number must be exactly 10 digits.',
            'profile_image.image' => 'Profile image must be an image file.',
            'profile_image.mimes' => 'Profile image must be a JPG, JPEG, PNG, or WEBP file.',
            'profile_image.max' => 'Profile image size must not exceed 5 MB.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => collect($validator->errors())->flatten()->first(),
            'error_code' => 'validation_failed',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }

    private function normalizeText(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function normalizePhone(mixed $value): ?string
    {
        $phone = preg_replace('/\D+/', '', trim((string) $value)) ?: '';

        return $phone === '' ? null : $phone;
    }
}
