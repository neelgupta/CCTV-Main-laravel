<?php

namespace App\Models;

use App\Support\SubscriptionInvoice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompanySubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'subscription_plan_id',
        'renewed_from_subscription_id',
        'billing_cycle',
        'starts_at',
        'ends_at',
        'status',
        'is_trial',
        'auto_renew',
        'is_recurring',
        'gateway_mode',
        'amount_paid',
        'payment_method',
        'billing_reference',
        'subscriber_email',
        'license_key',
        'license_count',
        'license_keys',
        'notes',
        'trial_ends_at',
        'razorpay_order_id',
        'razorpay_payment_id',
        'razorpay_subscription_id',
        'razorpay_plan_id',
        'razorpay_customer_id',
        'razorpay_status',
        'current_start',
        'current_end',
        'next_charge_at',
        'paid_count',
        'total_count',
        'cancel_at_cycle_end',
        'cancelled_at',
        'payment_failed_at',
        'grace_ends_at',
        'usage_baseline',
        'invoice_snapshot',
    ];

    protected $casts = [
        'billing_cycle' => 'string',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'trial_ends_at' => 'date',
        'is_trial' => 'boolean',
        'auto_renew' => 'boolean',
        'is_recurring' => 'boolean',
        'amount_paid' => 'decimal:2',
        'license_count' => 'integer',
        'license_keys' => 'array',
        'usage_baseline' => 'array',
        'invoice_snapshot' => 'array',
        'current_start' => 'datetime',
        'current_end' => 'datetime',
        'next_charge_at' => 'datetime',
        'paid_count' => 'integer',
        'total_count' => 'integer',
        'cancel_at_cycle_end' => 'boolean',
        'cancelled_at' => 'datetime',
        'payment_failed_at' => 'datetime',
        'grace_ends_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $subscription): void {
            if (empty($subscription->invoice_snapshot)) {
                $subscription->invoice_snapshot = SubscriptionInvoice::snapshot($subscription);
            }
        });

        static::updating(function (self $subscription): void {
            // Switching plan on the same row re-issues it for the new plan.
            if ($subscription->isDirty('subscription_plan_id') && ! empty($subscription->invoice_snapshot)) {
                $snapshot = $subscription->invoice_snapshot;
                $snapshot['plan'] = SubscriptionInvoice::snapshot($subscription->unsetRelation('plan'))['plan'];
                $subscription->invoice_snapshot = $snapshot;
            }
        });
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function renewedFrom()
    {
        return $this->belongsTo(self::class, 'renewed_from_subscription_id');
    }

    public function renewals()
    {
        return $this->hasMany(self::class, 'renewed_from_subscription_id');
    }

    public function payments()
    {
        return $this->hasMany(SubscriptionPayment::class, 'company_subscription_id');
    }

    public function isRecurring(): bool
    {
        return (bool) $this->is_recurring && filled($this->razorpay_subscription_id);
    }

    public function inGracePeriod(): bool
    {
        return $this->payment_failed_at !== null
            && $this->grace_ends_at !== null
            && $this->grace_ends_at->toDateString() >= now()->toDateString();
    }

    /**
     * Whether a Razorpay order or payment has already activated a subscription.
     */
    public static function razorpayPaymentAlreadyUsed(string $orderId, string $paymentId): bool
    {
        return static::query()
            ->where(function ($query) use ($orderId, $paymentId) {
                $query->where('razorpay_order_id', $orderId)
                    ->orWhere('razorpay_payment_id', $paymentId);
            })
            ->exists();
    }

    public static function generateLicensePackage(int $count): array
    {
        $count = max(1, $count);
        $licenseKeys = [];

        for ($attempt = 0; $attempt < $count * 20 && count($licenseKeys) < $count; $attempt++) {
            $candidate = static::generateLicenseKey();

            if (! in_array($candidate, $licenseKeys, true)) {
                $licenseKeys[] = $candidate;
            }
        }

        while (count($licenseKeys) < $count) {
            $candidate = 'LIC-' . strtoupper(\Illuminate\Support\Str::random(24));

            if (! in_array($candidate, $licenseKeys, true)) {
                $licenseKeys[] = $candidate;
            }
        }

        return [
            'license_key' => $licenseKeys[0],
            'license_count' => count($licenseKeys),
            'license_keys' => $licenseKeys,
        ];
    }

    public static function generateLicenseKey(): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $candidate = 'LIC-' . strtoupper(str_replace('-', '', (string) \Illuminate\Support\Str::uuid()));

            if (! static::query()->where('license_key', $candidate)->exists()) {
                return $candidate;
            }
        }

        return 'LIC-' . strtoupper(\Illuminate\Support\Str::random(24));
    }

    public function licenseKeyList(): array
    {
        $keys = $this->license_keys;

        if (is_array($keys) && $keys !== []) {
            return array_values(array_unique(array_filter($keys, fn ($key) => filled($key))));
        }

        if (filled($this->license_key)) {
            return [(string) $this->license_key];
        }

        return [];
    }

    public function licenseSeatCount(): ?int
    {
        if (is_numeric($this->license_count) && (int) $this->license_count > 0) {
            return (int) $this->license_count;
        }

        $keys = $this->licenseKeyList();

        return $keys !== [] ? count($keys) : null;
    }

    public function licenseSeatCountLabel(): string
    {
        $count = $this->licenseSeatCount();

        return $count === null ? 'Unlimited' : (string) $count;
    }

    public function statusBadgeLabel(): string
    {
        if ($this->is_trial || $this->status === 'trialing') {
            return 'Trial';
        }

        if ($this->status === 'active') {
            return 'Paid';
        }

        if ($this->status === 'pending') {
            return 'Pending';
        }

        if ($this->status === 'expired') {
            return 'Expired';
        }

        if ($this->status === 'cancelled') {
            return 'Cancelled';
        }

        return str($this->status)->headline()->toString();
    }

    public function statusBadgeClasses(): string
    {
        if ($this->is_trial || $this->status === 'trialing') {
            return 'bg-blue-100 text-blue-700 border border-blue-200';
        }

        if ($this->status === 'active') {
            return 'bg-emerald-100 text-emerald-700 border border-emerald-200';
        }

        if ($this->status === 'pending') {
            return 'bg-amber-100 text-amber-700 border border-amber-200';
        }

        if ($this->status === 'expired') {
            return 'bg-slate-100 text-slate-700 border border-slate-200';
        }

        if ($this->status === 'cancelled') {
            return 'bg-rose-100 text-rose-700 border border-rose-200';
        }

        return 'bg-slate-100 text-slate-700 border border-slate-200';
    }
}
