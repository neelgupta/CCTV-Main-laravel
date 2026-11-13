<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AmcVisit extends Model
{
    use HasFactory;

    protected $fillable = [
        'amc_id',
        'visit_no',
        'visit_type',
        'visit_date',
        'status',
        'technician',
        'support_ticket_id',
        'ticket_invoice_id',
    ];

    protected $casts = [
        'visit_date' => 'date',
    ];

    public function amc()
    {
        return $this->belongsTo(Amc::class);
    }

    public function supportTicket()
    {
        return $this->belongsTo(Ticket::class, 'support_ticket_id');
    }

    public function ticketInvoice()
    {
        return $this->belongsTo(TicketInvoice::class, 'ticket_invoice_id');
    }

    public function histories()
    {
        return $this->hasMany(AmcVisitHistory::class);
    }

    public function assignmentHistories()
    {
        return $this->hasMany(AmcVisitAssignmentHistory::class);
    }

    public function displayTechnicianName(): ?string
    {
        $visitTechnician = trim((string) $this->technician);

        if ($visitTechnician !== '') {
            return $visitTechnician;
        }

        return $this->supportTicket?->assignedUser?->name ?: null;
    }
}
