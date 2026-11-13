<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTaskPartsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $parts = $this->input('parts');

        if (! is_array($parts)) {
            return;
        }

        $normalizedParts = [];

        foreach ($parts as $part) {
            if (! is_array($part)) {
                $normalizedParts[] = $part;
                continue;
            }

            $normalizedParts[] = [
                'part_name' => isset($part['part_name']) ? trim((string) $part['part_name']) : null,
                'qty' => $part['qty'] ?? null,
                'gst' => $part['gst'] ?? null,
                'price' => $part['price'] ?? null,
            ];
        }

        $this->merge([
            'parts' => $normalizedParts,
        ]);
    }

    public function rules(): array
    {
        return [
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.part_name' => ['required', 'string', 'max:191'],
            'parts.*.qty' => ['required', 'numeric', 'gt:0'],
            'parts.*.gst' => ['required', 'numeric', 'gte:0'],
            'parts.*.price' => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'parts.required' => 'Parts are required.',
            'parts.array' => 'Parts must be sent as an array.',
            'parts.min' => 'At least one part is required.',
            'parts.*.part_name.required' => 'The part name field is required.',
            'parts.*.part_name.string' => 'The part name field must be a string.',
            'parts.*.part_name.max' => 'The part name field may not be greater than 191 characters.',
            'parts.*.qty.required' => 'The qty field is required.',
            'parts.*.qty.numeric' => 'The qty field must be a number.',
            'parts.*.qty.gt' => 'The qty field must be greater than 0.',
            'parts.*.gst.required' => 'The gst field is required.',
            'parts.*.gst.numeric' => 'The gst field must be a number.',
            'parts.*.gst.gte' => 'The gst field must not be negative.',
            'parts.*.price.required' => 'The price field is required.',
            'parts.*.price.numeric' => 'The price field must be a number.',
            'parts.*.price.gte' => 'The price field must be greater than or equal to 0.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => collect($validator->errors())->flatten()->first(),
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}
