<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Model;

class TicketInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'ticket_id',
        'client_id',
        'staff_id',
        'invoice_id',
        'invoice_number',
        'invoice_subtotal',
        'invoice_discount',
        'invoice_gst_amount',
        'invoice_total',
        'total_amount',
        'payment_status',
        'payment_method',
        'received_by_admin',
        'received_by_admin_at',
        'received_by_admin_by',
        'created_by',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'staff_id' => 'integer',
        'invoice_subtotal' => 'decimal:2',
        'invoice_discount' => 'decimal:2',
        'invoice_gst_amount' => 'decimal:2',
        'invoice_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'payment_status' => 'integer',
        'payment_method' => 'integer',
        'received_by_admin' => 'boolean',
        'received_by_admin_at' => 'datetime',
        'received_by_admin_by' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TicketDocument::class, 'invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function income(): HasOne
    {
        return $this->hasOne(Income::class, 'invoice_id');
    }

    public function receivedByAdminBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by_admin_by');
    }
}
