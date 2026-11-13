<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class StaffImportSampleExport implements FromCollection, WithHeadings, ShouldAutoSize
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
            'password',
            'phone',
            'role',
            'department',
            'join_date',
        ];
    }
}
