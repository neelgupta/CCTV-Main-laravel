<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPayment extends Model
{
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'company_id',
        'company_subscription_id',
        'razorpay_payment_id',
        'razorpay_invoice_id',
        'razorpay_order_id',
        'amount',
        'gst_rate',
        'gst_amount',
        'currency',
        'status',
        'method',
        'period_start',
        'period_end',
        'paid_at',
        'failure_reason',
        'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gst_rate' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'period_start' => 'date',
        'period_end' => 'date',
        'paid_at' => 'datetime',
        'payload' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function subscription()
    {
        return $this->belongsTo(CompanySubscription::class, 'company_subscription_id');
    }
}
