<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateTaskPartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->exists('part_name')) {
            $this->merge([
                'part_name' => trim((string) $this->input('part_name')),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'part_name' => ['required', 'string', 'max:191'],
            'qty' => ['required', 'numeric', 'gt:0'],
            'gst' => ['required', 'numeric', 'gte:0'],
            'price' => ['required', 'numeric', 'gte:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'part_name.required' => 'The part name field is required.',
            'part_name.string' => 'The part name field must be a string.',
            'part_name.max' => 'The part name field may not be greater than 191 characters.',
            'qty.required' => 'The qty field is required.',
            'qty.numeric' => 'The qty field must be a number.',
            'qty.gt' => 'The qty field must be greater than 0.',
            'gst.required' => 'The gst field is required.',
            'gst.numeric' => 'The gst field must be a number.',
            'gst.gte' => 'The gst field must not be negative.',
            'price.required' => 'The price field is required.',
            'price.numeric' => 'The price field must be a number.',
            'price.gte' => 'The price field must be greater than or equal to 0.',
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
