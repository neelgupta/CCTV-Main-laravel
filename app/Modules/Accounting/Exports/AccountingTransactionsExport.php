<?php

namespace App\Modules\Accounting\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AccountingTransactionsExport implements FromCollection, WithHeadings
{
    public function __construct(
        private readonly Collection $rows,
        private readonly array $headings,
    ) {
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
