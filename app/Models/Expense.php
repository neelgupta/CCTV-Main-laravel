<?php

namespace App\Models;

use App\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

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
        'staff_id',
        'vendor_name',
        'title',
        'description',
        'amount',
        'payment_method',
        'transaction_no',
        'expense_date',
        'attachment',
        'approval_status',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'staff_id' => 'integer',
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'approved_by' => 'integer',
        'approved_at' => 'datetime',
        'created_by' => 'integer',
    ];

    public static function paymentMethods(): array
    {
        return self::PAYMENT_METHODS;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (blank($this->attachment)) {
            return null;
        }

        return route('accounting.expenses.attachment', $this);
    }
}
