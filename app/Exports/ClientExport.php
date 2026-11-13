<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClientExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $clients)
    {
    }

    public function collection(): Collection
    {
        return $this->clients->map(function ($client) {
            return [
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone ?: $client->mobile,
                'address' => $client->address,
                'gst_number' => $client->gst_number,
                'pan_number' => $client->pan_number,
                'state' => $client->state?->name,
                'city' => $client->city?->name,
                'created_at' => optional($client->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => optional($client->updated_at)->format('Y-m-d H:i:s'),
            ];
        });
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
            'created_at',
            'updated_at',
        ];
    }
}
