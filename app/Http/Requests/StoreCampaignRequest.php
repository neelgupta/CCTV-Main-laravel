<?php

namespace App\Http\Requests;

use App\Models\Campaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Campaign::TYPE_NORMAL, Campaign::TYPE_FESTIVAL])],
            'name' => ['required', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'email_template_id' => ['nullable', 'integer', 'exists:marketing_email_templates,id'],
            'whatsapp_template_name' => ['nullable', 'string', 'max:255'],
            'festival_campaign_id' => ['nullable', 'integer', 'exists:festival_campaigns,id'],
            'generated_banner_id' => ['nullable', 'integer', 'exists:generated_banners,id'],
            'festival_name' => ['nullable', 'string', 'max:255'],
            'festival_date' => ['nullable', 'date'],
            'festival_description' => ['nullable', 'string'],
            'greetings_text' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'brand_logo_path' => ['nullable', 'string', 'max:500'],
            'send_type' => ['nullable', Rule::in([Campaign::SEND_TYPE_IMMEDIATE, Campaign::SEND_TYPE_SCHEDULED])],
            'status' => ['nullable', Rule::in([Campaign::STATUS_DRAFT, Campaign::STATUS_SCHEDULED])],
            'scheduled_at' => ['nullable', 'date', 'required_if:send_type,scheduled'],
            'send_email' => ['nullable', 'boolean'],
            'send_whatsapp' => ['nullable', 'boolean'],
            'email_body' => ['nullable', 'string'],
            'batch_size' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'batch_delay_seconds' => ['nullable', 'integer', 'min:1', 'max:3600'],
            'client_ids' => ['nullable', 'array'],
            'client_ids.*' => ['integer', 'exists:clients,id'],
            'recipients' => ['nullable', 'array'],
            'recipients.*.name' => ['nullable', 'string', 'max:255'],
            'recipients.*.email' => ['nullable', 'email', 'max:255'],
            'recipients.*.phone' => ['nullable', 'string', 'max:50'],
            'recipients.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
