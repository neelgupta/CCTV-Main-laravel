<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TicketExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $tickets)
    {
    }

    public function collection(): Collection
    {
        return $this->tickets->map(function ($ticket) {
            return [
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'type' => $ticket->type_label,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'customer_name' => $ticket->customer_name,
                'customer_email' => $ticket->customer_email,
                'customer_contact_number' => $ticket->customer_contact_number,
                'assigned_to' => $ticket->assigned_user_names ?: ($ticket->assignedUser?->name ?: '-'),
                'created_by' => $ticket->user?->name,
                'referenced_by' => $ticket->referencedUser?->name,
                'amc_contract_no' => $ticket->amc?->contract_no,
                'description' => $ticket->description,
                'created_at' => optional($ticket->created_at)->format('Y-m-d H:i:s'),
                'updated_at' => optional($ticket->updated_at)->format('Y-m-d H:i:s'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'ticket_number',
            'subject',
            'type',
            'priority',
            'status',
            'customer_name',
            'customer_email',
            'customer_contact_number',
            'assigned_to',
            'created_by',
            'referenced_by',
            'amc_contract_no',
            'description',
            'created_at',
            'updated_at',
        ];
    }
}
