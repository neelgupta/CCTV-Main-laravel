<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class BoqProductImportSampleExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function collection(): Collection
    {
        return collect();
    }

    public function headings(): array
    {
        return [
            'category',
            'name',
            'brand',
            'model',
            'unit',
            'price',
            'gst',
            'description',
            'is_active',
        ];
    }
}
