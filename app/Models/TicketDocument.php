<?php

namespace App\Models;

use App\Support\BillingDocumentSnapshot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'client_id',
        'company_id',
        'created_by',
        'document_type',
        'invoice_or_quote',
        'document_number',
        'subject',
        'items',
        'subtotal',
        'discount',
        'gst_amount',
        'total',
        'has_additional_notes',
        'additional_notes',
        'payment_status',
        'payment_method',
        'notes',
        'terms_conditions',
        'billing_snapshot',
    ];

    protected $casts = [
        'items' => 'array',
        'subtotal' => 'decimal:2',
        'discount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'has_additional_notes' => 'boolean',
        'billing_snapshot' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            if (BillingDocumentSnapshot::shouldRefresh($document)) {
                $document->billing_snapshot = BillingDocumentSnapshot::capture($document);
            }
        });
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
