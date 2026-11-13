<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ContactExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $contacts)
    {
    }

    public function collection(): Collection
    {
        return $this->contacts->map(function ($contact) {
            return [
                // 'contact_type' => $contact->contact_type === 'client' ? 'Client Record' : 'Lead',
                'name' => $contact->contact_type === 'client' ? $contact->name : $contact->client_name,
                'email' => $contact->email,
                'phone' => $contact->mobile ?: $contact->phone,
                // 'status' => $contact->contact_type === 'client' ? 'Client Record' : ($contact->status?->name ?? null),
                // 'assigned_to' => $contact->contact_type === 'client' ? ($contact->user?->name ?? null) : ($contact->assignedTo?->name ?? null),
                'address' => $contact->address,
                'state' => $contact->state?->name,
                'city' => $contact->city?->name,
                // 'source' => $contact->contact_type === 'client' ? 'Client Record' : ($contact->contact_source_label ?? null),
                'created_at' => optional($contact->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => optional($contact->updated_at)->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            // 'contact_type',
            'name',
            'email',
            'phone',
            // 'status',
            // 'assigned_to',
            'address',
            'state',
            'city',
            // 'source',
            'created_at',
            'updated_at',
        ];
    }
}
