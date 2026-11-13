<?php

namespace App\Exports;

use App\Models\Company;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class LeadImportSampleExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly ?Company $company = null)
    {
    }

    public function collection(): Collection
    {
        return collect();
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
        ];

        foreach ($this->company?->leadCustomFieldDefinitions() ?? [] as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label !== '') {
                $headings[] = $label;
            }
        }

        return $headings;
    }
}
