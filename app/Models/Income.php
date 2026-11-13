<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use BelongsToCompany, HasFactory;

    public const PAYMENT_METHODS = [
        'Cash' => 'Cash',
        'Bank' => 'Bank',
        'UPI' => 'UPI',
        'Cheque' => 'Cheque',
        'Card' => 'Card',
        'Online' => 'Online',
    ];

    protected $fillable = [
        'company_id',
        'category_id',
        'invoice_id',
        'client_id',
        'staff_id',
        'title',
        'description',
        'amount',
        'payment_method',
        'transaction_no',
        'received_date',
        'attachment',
        'created_by',
    ];

    protected $casts = [
        'invoice_id' => 'integer',
        'client_id' => 'integer',
        'staff_id' => 'integer',
        'amount' => 'decimal:2',
        'received_date' => 'date',
        'created_by' => 'integer',
    ];

    public static function paymentMethods(): array
    {
        return self::PAYMENT_METHODS;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'category_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TicketInvoice::class, 'invoice_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (blank($this->attachment)) {
            return null;
        }

        return route('accounting.incomes.attachment', $this);
    }
}
