<?php

namespace App\Exports;

use App\Models\Company;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProjectImportSampleExport implements FromCollection, WithHeadings, ShouldAutoSize
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
            'project_name',
            'client_name',
            'email',
            'phone',
            'address',
            'reference_no',
            'lead_name',
            'current_stage',
            'status',
            'project_manager',
            'state',
            'city',
            'start_date',
            'end_date',
            'description',
            'team_members',
        ];

        foreach ($this->company?->projectCustomFieldDefinitions() ?? [] as $field) {
            $label = trim((string) ($field['label'] ?? ''));
            if ($label !== '') {
                $headings[] = $label;
            }
        }

        return $headings;
    }
}
