<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AmcExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $amcs)
    {
    }

    public function collection(): Collection
    {
        return $this->amcs->map(function ($amc) {
            return [
                'contract_no' => $amc->contract_no,
                'name' => $amc->customer_name,
                'email' => $amc->email,
                'phone' => $amc->mobile_number,
                'address' => $amc->address,
                'project_name' => $amc->project_name,
                'price' => $amc->amc_amount,
                'amount' => $amc->total_amount,
                'gst' => $amc->tax_percent,
                'discount' => $amc->discount,
                'start_date' => optional($amc->start_date)->format('Y-m-d'),
                'end_date' => optional($amc->end_date)->format('Y-m-d'),
                'payment_status' => $amc->payment_status,
                'created_at' => optional($amc->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => optional($amc->updated_at)->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'contract_no',
            'name',
            'email',
            'phone',
            'address',
            'project_name',
            'price',
            'amount',
            'gst',
            'discount',
            'start_date',
            'end_date',
            'payment_status',
            'created_at',
            'updated_at',
        ];
    }
}
