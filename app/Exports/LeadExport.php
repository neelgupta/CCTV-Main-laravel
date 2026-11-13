<?php

namespace App\Exports;

use App\Models\Company;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LeadExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly Collection $leads,
        private readonly ?Company $company = null,
    )
    {
    }

    public function collection(): Collection
    {
        $fields = $this->company?->leadCustomFieldDefinitions() ?? [];

        return $this->leads->map(function ($lead) use ($fields) {
            $row = [
                $lead->client_name,
                $lead->email,
                $lead->mobile,
                $lead->address,
                $lead->category?->name,
                $lead->status?->name,
                $lead->priority,
                $lead->estimated_value,
                optional($lead->next_follow_up)->format('Y-m-d'),
                $lead->source,
                $lead->referral_person_name,
            ];

            foreach ($fields as $field) {
                $row[] = $lead->formatCustomFieldValue($field, data_get($lead->custom_fields ?? [], $field['key'] ?? ''));
            }

            $row[] = $lead->gst_number;
            $row[] = $lead->pan_number;
            $row[] = $lead->state?->name;
            $row[] = $lead->city?->name;
            $row[] = $lead->assignedUsers->pluck('name')->join(', ');
            $row[] = optional($lead->created_at)->format('Y-m-d H:i:s');
            $row[] = optional($lead->updated_at)->format('Y-m-d H:i:s');

            return $row;
        });
    }

    public function headings(): array
    {
        $headings = [
            'name',
            'email',
            'phone',
            'address',
            'lead_category',
            'status',
            'priority',
            'estimated_value',
            'next_follow_up',
            'source',
            'referral_person_name',
            'gst_number',
            'pan_number',
            'state',
            'city',
            'assigned_to',
            'created_at',
            'updated_at',
        ];

        $customFieldHeadings = collect($this->company?->leadCustomFieldDefinitions() ?? [])
            ->map(fn (array $field) => trim((string) ($field['label'] ?? '')))
            ->filter()
            ->values()
            ->all();

        array_splice($headings, 11, 0, $customFieldHeadings);

        return $headings;
    }

}
