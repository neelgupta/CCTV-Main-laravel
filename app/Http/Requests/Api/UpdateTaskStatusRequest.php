<?php

namespace App\Http\Requests\Api;

use App\Models\StaffTask;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(StaffTask::statusOptions()))],
            'message' => ['nullable', 'string', 'max:1000'],
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
