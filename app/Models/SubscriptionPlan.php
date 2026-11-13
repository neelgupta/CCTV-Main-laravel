<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'monthly_price',
        'yearly_price',
        'razorpay_plan_ids',
        'billing_cycle',
        'description',
        'staff_limit',
        'is_active',
        'is_popular',
        'has_free_trial',
        'disable_staff_tracking',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'monthly_price' => 'decimal:2',
        'yearly_price' => 'decimal:2',
        'staff_limit' => 'integer',
        'is_active' => 'boolean',
        'is_popular' => 'boolean',
        'has_free_trial' => 'boolean',
        'disable_staff_tracking' => 'boolean',
        'features' => 'array',
        'razorpay_plan_ids' => 'array',
    ];

    public static function quotaDefinitions(): array
    {
        return [
            'staff' => ['label' => 'Staff Members'],
        ];
    }

    public function companySubscriptions()
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public static function storageLimitMb(): int
    {
        return (int) config('subscription.storage_limit_mb', 500);
    }

    public function planStorageLimitMb(): int
    {
        if ($this->staff_limit === null) {
            return (int) (100 * 1024); // 100 GB for unlimited plans
        }

        return (int) (max(1, (int) $this->staff_limit) * 100);
    }

    public function storageLimitLabel(): string
    {
        if ($this->staff_limit === null) {
            return 'Unlimited';
        }

        $mb = $this->planStorageLimitMb();

        return $mb >= 1024
            ? number_format($mb / 1024, 1) . ' GB'
            : $mb . ' MB';
    }

    public function priceForCycle(?string $billingCycle = null): float
    {
        $cycle = strtolower((string) ($billingCycle ?: $this->billing_cycle ?: 'monthly'));
        $monthlyPrice = $this->getRawOriginal('monthly_price');
        $yearlyPrice = $this->getRawOriginal('yearly_price');
        $legacyPrice = $this->getRawOriginal('price');

        return (float) ($cycle === 'yearly'
            ? ($yearlyPrice ?? $monthlyPrice ?? $legacyPrice ?? 0)
            : ($monthlyPrice ?? $yearlyPrice ?? $legacyPrice ?? 0));
    }

    /**
     * Stored Razorpay plan for a gateway mode and billing cycle: ['id' => 'plan_x', 'amount' => paise].
     */
    public function razorpayPlanFor(string $mode, string $billingCycle): ?array
    {
        $plan = $this->razorpay_plan_ids[$mode][$billingCycle] ?? null;

        return is_array($plan) && filled($plan['id'] ?? null) ? $plan : null;
    }

    public function getPriceAttribute($value): float
    {
        return $this->priceForCycle();
    }

    public function getMonthlyPriceLabelAttribute(): string
    {
        return number_format((float) ($this->getRawOriginal('monthly_price') ?? 0), 2);
    }

    public function getYearlyPriceLabelAttribute(): string
    {
        return number_format((float) ($this->getRawOriginal('yearly_price') ?? 0), 2);
    }

    public function yearlyDiscountPercentage(): ?float
    {
        $monthlyPrice = (float) ($this->getRawOriginal('monthly_price') ?? 0);
        $yearlyPrice = (float) ($this->getRawOriginal('yearly_price') ?? 0);
        $fullYearPrice = $monthlyPrice * 12;

        if ($monthlyPrice <= 0 || $yearlyPrice <= 0 || $yearlyPrice >= $fullYearPrice) {
            return null;
        }

        return round((($fullYearPrice - $yearlyPrice) / $fullYearPrice) * 100, 1);
    }

    public function yearlyDiscountLabel(): ?string
    {
        $discount = $this->yearlyDiscountPercentage();

        if ($discount === null) {
            return null;
        }

        $formatted = fmod($discount, 1.0) === 0.0
            ? number_format($discount, 0)
            : number_format($discount, 1);

        return $formatted . '% discount';
    }

    public function staffLimitLabel(): string
    {
        return $this->staff_limit === null ? 'Unlimited' : (string) $this->staff_limit;
    }

    public function limitFor(string $key): ?int
    {
        if ($key !== 'staff') {
            return null;
        }

        return is_numeric($this->staff_limit) ? (int) $this->staff_limit : null;
    }

    public function limitValueLabel(string $key): string
    {
        if ($key !== 'staff') {
            return 'Unlimited';
        }

        return $this->staffLimitLabel();
    }

    public function quotaSummary(): array
    {
        return [
            [
                'key' => 'staff',
                'label' => 'Staff Members',
                'value' => $this->staffLimitLabel(),
            ],
        ];
    }

    public function shouldDisableStaffTracking(): bool
    {
        return (bool) $this->disable_staff_tracking;
    }
}
