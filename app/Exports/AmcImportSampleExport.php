<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AmcImportSampleExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function collection(): Collection
    {
        return collect();
    }

    public function headings(): array
    {
        return [
            'client_name',
            'email',
            'phone',
            'address',
            'project_name',
            'plan',
            'amount',
            'gst',
            'discount',
            'amc_type',
            'start_date',
        ];
    }
}
