<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientImportSampleExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function collection(): Collection
    {
        return collect();
    }

    public function headings(): array
    {
        return [
            'name',
            'email',
            'phone',
            'address',
            'gst_number',
            'pan_number',
            'state',
            'city',
        ];
    }
}
