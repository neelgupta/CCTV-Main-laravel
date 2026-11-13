<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\ProjectStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProjectRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $company = $this->company();

        $this->merge([
            'custom_fields' => $this->normalizeCustomFieldsForValidation($company),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $companyId = (int) auth()->user()?->company_id;
        if ($companyId > 0) {
            ProjectStage::ensureDefaults($companyId);
        }
        $allowedStages = $companyId > 0
            ? array_values(array_unique(array_merge(
                ProjectStage::query()
                    ->where('company_id', $companyId)
                    ->pluck('key')
                    ->filter()
                    ->map(fn ($key) => in_array((string) $key, ['drawing', 'new-project'], true) ? 'new-project' : (string) $key)
                    ->all(),
                ['drawing']
            )))
            : ['new-project', 'drawing'];

        return [
            'existing_client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'name' => 'required|string|min:2|max:191',
            'client_name' => 'required|string|min:2|max:191',
            'client_email' => 'nullable|email|max:191',
            'client_mobile' => 'nullable|string|max:20',
            'client_phone' => 'nullable|string|max:20',
            'client_address' => 'nullable|string|min:5|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'state_id' => 'required|exists:states,id',
            'city_id' => [
                'required',
                Rule::exists('cities', 'id')->where(fn ($query) => $query->where('state_id', $this->input('state_id'))),
            ],
            'reference_no' => 'nullable|string|max:191',
            'lead_id' => 'nullable|exists:leads,id',
            'contractor_id' => [
                'nullable',
                Rule::exists('contractors', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'commission_type' => ['sometimes', 'nullable', Rule::in(['fixed', 'percentage'])],
            'commission_value' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'current_stage' => ['required', Rule::in($allowedStages)],
            'project_manager_id' => 'nullable|exists:users,id',
            'team_member_ids' => 'nullable|array',
            'team_member_ids.*' => 'exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:on_track,at_risk,delayed,completed',
            'description' => 'nullable|string|min:2|max:2000',
            'custom_fields' => 'nullable|array',
        ];

        foreach ($company?->projectCustomFieldDefinitions() ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $rules["custom_fields.$key"] = $this->customFieldRules($field);
        }

        return $rules;
    }

    private function company(): ?Company
    {
        $user = auth()->user();
        $companyId = $user?->company_id ?: $user?->roleRecord?->company_id;

        return $companyId ? Company::query()->find($companyId) : null;
    }

    private function normalizeCustomFieldsForValidation(?Company $company): array
    {
        $definitions = $company?->projectCustomFieldDefinitions() ?? [];
        $submitted = $this->input('custom_fields', []);
        if (! is_array($submitted)) {
            $submitted = [];
        }

        $normalized = [];

        foreach ($definitions as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $normalized[$key] = $this->normalizeCustomFieldValue($field, $submitted[$key] ?? null);
        }

        return $normalized;
    }

    private function normalizeCustomFieldValue(array $field, mixed $value): mixed
    {
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'checkbox') {
            return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        }

        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = reset($value);
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if ($type === 'number') {
            return is_numeric($value) ? (0 + $value) : $value;
        }

        return $value;
    }

    private function customFieldRules(array $field): array
    {
        $type = (string) ($field['type'] ?? 'text');
        $required = (bool) ($field['required'] ?? false);
        $options = collect($field['options'] ?? [])
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->values()
            ->all();

        $rules = [];

        if ($required) {
            $rules[] = $type === 'checkbox' ? 'accepted' : 'required';
        } else {
            $rules[] = 'nullable';
        }

        return array_merge($rules, match ($type) {
            'email' => ['email:rfc', 'max:191'],
            'textarea' => ['string', 'max:2000'],
            'number' => ['numeric'],
            'date' => ['date'],
            'dropdown', 'radiobutton' => array_filter([
                'string',
                'max:191',
                $options !== [] ? Rule::in($options) : null,
            ]),
            'checkbox' => ['boolean'],
            default => ['string', 'max:191'],
        });
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'name.min' => 'Project Name must be at least 2 characters.',
            'name.max' => 'Project Name may not be greater than 191 characters.',
            'client_name.min' => 'Client must be at least 2 characters.',
            'client_name.max' => 'Client may not be greater than 191 characters.',
            'client_address.min' => 'Client Address must be at least 5 characters.',
            'client_address.max' => 'Client Address may not be greater than 1000 characters.',
            'state_id.required' => 'State is required.',
            'city_id.required' => 'City is required.',
            'city_id.exists' => 'Selected city does not belong to the selected state.',
            'description.min' => 'Description must be at least 2 characters.',
            'description.max' => 'Description may not be greater than 2000 characters.',
            'end_date.after_or_equal' => 'The Deadline date must be the same as or later than the Start Date.',
        ];
    }

    /**
     * Custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'start_date' => 'Start Date',
            'end_date' => 'Deadline',
        ];
    }
}
