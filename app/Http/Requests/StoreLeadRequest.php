<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Models\LeadStatus;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $company = $this->company();
        $companyId = $company?->id;

        $assignedTo = $this->input('assigned_to', []);
        if (!is_array($assignedTo)) {
            $assignedTo = array_filter([$assignedTo], static fn ($value) => $value !== null && $value !== '');
        }

        $assignedTo[] = auth()->id();

        $leadStatusId = $companyId
            ? LeadStatus::ensureNewLead((int) $companyId)->id
            : $this->input('lead_status_id');

        $this->merge([
            'assigned_to' => array_values(array_unique($assignedTo)),
            'existing_client_id' => is_numeric($this->input('existing_client_id')) ? (int) $this->input('existing_client_id') : null,
            'client_name' => trim((string) $this->input('client_name', '')),
            'email' => $this->filled('email') ? strtolower(trim((string) $this->input('email'))) : null,
            'mobile' => $this->filled('mobile') ? trim((string) $this->input('mobile')) : null,
            'lead_status_id' => $leadStatusId,
            'gst_number' => $company?->show_lead_gst_field === false ? null : strtoupper(trim((string) $this->input('gst_number', ''))),
            'pan_number' => $company?->show_lead_pan_field === false ? null : strtoupper(trim((string) $this->input('pan_number', ''))),
            'source' => $this->normalizedOptionalText('source'),
            'referral_person_name' => $this->normalizedOptionalText('referral_person_name'),
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
        $company = $this->company();
        $companyId = $company?->id;
        $leadSourceRules = ['nullable', 'string', 'max:100'];

        if (! empty($this->availableLeadSources($company))) {
            $leadSourceRules[] = Rule::in($this->availableLeadSources($company));
        }

        $rules = [
            'existing_client_id' => [
                'nullable',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'client_name' => 'required|string|max:191',
            'email' => 'nullable|email:rfc|max:191',
            'mobile' => 'nullable|regex:/^[0-9]{10}$/',
            'gst_number' => $company?->show_lead_gst_field === false
                ? 'nullable'
                : 'nullable|string|max:20|regex:/^[0-9A-Z]{15}$/',
            'pan_number' => $company?->show_lead_pan_field === false
                ? 'nullable'
                : 'nullable|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
            'address' => 'nullable|string',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',

            'state_id' => 'required|exists:states,id',
            'city_id' => [
                'required',
                Rule::exists('cities', 'id')->where(fn ($query) => $query->where('state_id', $this->input('state_id'))),
            ],
            'source' => $leadSourceRules,
            'referral_person_name' => 'nullable|string|max:191',
            'custom_fields' => 'nullable|array',
            'lead_category_id' => [
                'required',
                Rule::exists('lead_categories', 'id')->where(function ($query) use ($companyId) {
                    $query->whereNull('company_id')->orWhere('company_id', $companyId);
                }),
            ],
            'lead_status_id' => 'required|exists:lead_statuses,id',
            'loss_reason' => 'nullable|string|max:1000',
            'assigned_to' => 'required|array|min:1',
            'assigned_to.*' => [
                'exists:users,id',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'estimated_value' => 'nullable|numeric|min:0',
            'priority' => 'required|in:low,medium,high',
            'next_follow_up' => 'nullable|date',
        ];

        foreach ($company?->leadCustomFieldDefinitions() ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            if ($key === '') {
                continue;
            }

            $rules["custom_fields.$key"] = $this->customFieldRules($field);
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [
            'client_name' => 'Client Name',
            'email' => 'Email',
            'mobile' => 'Mobile',
            'gst_number' => 'GST Number',
            'pan_number' => 'PAN Number',
            'state_id' => 'State',
            'city_id' => 'City',
            'lead_category_id' => 'Lead Category',
            'lead_status_id' => 'Lead Status',
            'assigned_to' => 'Assigned Employees',
            'estimated_value' => 'Estimated Value',
            'priority' => 'Priority',
            'next_follow_up' => 'Next Follow Up',
            'source' => 'Lead Source',
            'referral_person_name' => 'Referral Person Name',
            'address' => 'Address',
        ];

        foreach ($this->company()?->leadCustomFieldDefinitions() ?? [] as $field) {
            $key = (string) ($field['key'] ?? '');
            $label = trim((string) ($field['label'] ?? ''));

            if ($key !== '' && $label !== '') {
                $attributes["custom_fields.$key"] = $label;
            }
        }

        return $attributes;
    }

    private function company(): ?Company
    {
        $user = auth()->user();
        $companyId = $user?->company_id ?: $user?->roleRecord?->company_id;

        return $companyId ? Company::query()->find($companyId) : null;
    }

    private function availableLeadSources(?Company $company): array
    {
        return $company?->leadSourceNames() ?? [];
    }

    private function normalizedOptionalText(string $key): ?string
    {
        $value = trim((string) $this->input($key, ''));

        return $value === '' ? null : $value;
    }

    private function normalizeCustomFieldsForValidation(?Company $company): array
    {
        $definitions = $company?->leadCustomFieldDefinitions() ?? [];
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
}
