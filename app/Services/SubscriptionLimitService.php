<?php

namespace App\Services;

use App\Models\CompanySubscription;
use App\Models\Company;
use App\Models\Staff;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubscriptionLimitService
{
    private const ACTIVE_STATUSES = ['active', 'trialing'];

    public function subscriptionIsActive(?CompanySubscription $subscription): bool
    {
        if (! $subscription) {
            return false;
        }

        if (! in_array((string) $subscription->status, self::ACTIVE_STATUSES, true)) {
            return false;
        }

        $today = now()->toDateString();

        if ($subscription->starts_at !== null && $subscription->starts_at->toDateString() > $today) {
            return false;
        }

        if ($subscription->ends_at !== null && $subscription->ends_at->toDateString() < $today) {
            return false;
        }

        return true;
    }

    public function userHasAssignedSubscriptionAccess(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isCompanyAdmin()) {
            return true;
        }

        $staff = $user->staff;

        if (! $staff) {
            return true;
        }

        return $this->subscriptionIsActive($staff->companySubscription);
    }

    public function expiredAssignedSubscriptionMessage(?User $user): string
    {
        $planName = $user?->staff?->companySubscription?->plan?->name ?? 'assigned subscription plan';

        return sprintf(
            'Your assigned subscription plan (%s) is expired. Please contact your company admin.',
            $planName
        );
    }

    public function activeSubscriptions(?int $companyId): Collection
    {
        if (! $companyId) {
            return collect();
        }

        return CompanySubscription::query()
            ->with('plan')
            ->where('company_id', $companyId)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (CompanySubscription $subscription) => $this->subscriptionIsActive($subscription))
            ->values();
    }

    public function expiredSubscriptions(?int $companyId): Collection
    {
        if (! $companyId) {
            return collect();
        }

        $today = now()->toDateString();

        return CompanySubscription::query()
            ->with('plan')
            ->where('company_id', $companyId)
            ->whereDoesntHave('renewals')
            ->where(function (Builder $query) use ($today) {
                $query->where(function (Builder $expiredQuery) use ($today) {
                    $expiredQuery->whereIn('status', self::ACTIVE_STATUSES)
                        ->whereNotNull('ends_at')
                        ->whereDate('ends_at', '<', $today);
                })->orWhere('status', 'expired');
            })
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Expired subscriptions that still deserve a warning banner. Once the
     * company has at least one currently-active subscription, the expired
     * plan history is no longer a problem worth nagging about.
     */
    public function unresolvedExpiredSubscriptions(?int $companyId): Collection
    {
        if ($this->activeSubscriptions($companyId)->isNotEmpty()) {
            return collect();
        }

        return $this->expiredSubscriptions($companyId);
    }

    public function currentSubscription(?int $companyId): ?CompanySubscription
    {
        return $this->activeSubscriptions($companyId)->first();
    }

    public function companyHasActivePlan(?Company $company): bool
    {
        return (bool) ($company?->id) && $this->activeSubscriptions((int) $company->id)->isNotEmpty();
    }

    public function trialEligibilityForPlan(?Company $company, SubscriptionPlan $plan, ?string $billingCycle = null): array

    {
        $email = Str::lower(trim((string) ($company?->email ?? '')));
        $normalizedBillingCycle = strtolower(trim((string) ($billingCycle ?: $plan->billing_cycle ?: 'monthly')));

        if ($normalizedBillingCycle !== 'monthly') {
            return [
                'available' => false,
                'eligible' => false,
                'reason' => 'Free trial is only available on monthly billing cycle.',
            ];
        }

        if (! $company || ! $company->id) {
            return [
                'available' => (bool) $plan->has_free_trial,
                'eligible' => false,
                'reason' => 'Company account was not found.',
            ];
        }

        if (! $plan->is_active) {
            return [
                'available' => (bool) $plan->has_free_trial,
                'eligible' => false,
                'reason' => 'This subscription plan is not active.',
            ];
        }

        if (! $plan->has_free_trial) {
            return [
                'available' => false,
                'eligible' => false,
                'reason' => 'Free trial is not available on this plan.',
            ];
        }

        $trialExists = CompanySubscription::query()
            ->where('is_trial', true)
            ->where(function (Builder $query) use ($company, $email) {
                $query->where('company_id', $company->id);

                if ($email !== '') {
                    $query->orWhereRaw('LOWER(subscriber_email) = ?', [$email]);
                }
            })
            ->exists();

        if ($trialExists) {
            return [
                'available' => true,
                'eligible' => false,
                'reason' => 'This company has already used its free trial.',
            ];
        }

        return [
            'available' => true,
            'eligible' => true,
            'reason' => null,
        ];
    }

    public function companyCanStartTrial(?Company $company, SubscriptionPlan $plan, ?string $billingCycle = null): bool
    {
        return (bool) ($this->trialEligibilityForPlan($company, $plan, $billingCycle)['eligible'] ?? false);
    }

    public function shouldRestrictWritesForCompany(?Company $company): bool
    {
        if (! $company || ! $company->id || ! $company->is_onboarded) {
            return false;
        }

        return $this->activeSubscriptions((int) $company->id)->isEmpty();
    }

    public function shouldBlockModulesForCompany(?Company $company): bool
    {
        if (! $company || ! $company->id) {
            return false;
        }

        return ! $this->companyHasActivePlan($company);
    }

    public function expiredAccessMessage(?Company $company): string
    {
        $companyName = $company?->name ?: 'Your company';

        return sprintf(
            '%s does not have an active subscription right now. You can view all modules, but add, edit, and delete actions are locked until you activate a paid plan or eligible trial.',
            $companyName
        );
    }

    public function expiredPlanMessage(?int $companyId): ?string
    {
        $expiredSubscriptions = $this->unresolvedExpiredSubscriptions($companyId);

        if ($expiredSubscriptions->isEmpty()) {
            return null;
        }

        $plans = $expiredSubscriptions->map(function (CompanySubscription $subscription): string {
            $planName = $subscription->plan?->name ?? 'Subscription plan';
            $expiredOn = $subscription->ends_at?->format('d M Y') ?? 'an earlier date';

            return sprintf('%s expired on %s', $planName, $expiredOn);
        })->values();

        if ($plans->count() === 1) {
            return 'Your plan is expired. ' . $plans->first() . '. Please renew or purchase a new plan from the Subscription page.';
        }

        return 'Some of your plans are expired: ' . $plans->implode(', ') . '. Please renew or purchase a new plan from the Subscription page.';
    }

    public function noActivePlanPopupMessage(): string
    {
        return "You don't have any active plan. First purchase any plan.";
    }

    public function currentPlan(?int $companyId): ?SubscriptionPlan
    {
        return $this->currentSubscription($companyId)?->plan;
    }

    public function trackingIsDisabledForCompany(?Company $company): bool
    {
        if (! $company || ! $company->id) {
            return false;
        }

        $plan = $this->currentPlan((int) $company->id);

        if ($plan?->slug === 'growth') {
            return ! (bool) $company->allow_growth_staff_tracking;
        }

        return $this->trackingIsDisabledForPlan($plan);
    }

    public function trackingIsDisabledForUser(?User $user): bool
    {
        if ($user?->staff) {
            return $this->trackingIsDisabledForStaff($user->staff);
        }

        return $this->trackingIsDisabledForCompany($user?->company);
    }

    public function trackingIsDisabledForStaff(?Staff $staff): bool
    {
        if (! $staff) {
            return true;
        }

        $subscription = $staff->companySubscription;

        if (! $this->subscriptionIsActive($subscription)) {
            return true;
        }

        return $this->trackingIsDisabledForPlan($subscription?->plan);
    }

    public function staffTrackingEnabledForStaff(?Staff $staff): bool
    {
        return ! $this->trackingIsDisabledForStaff($staff);
    }

    public function hasDeactivatedCurrentPlan(?int $companyId): bool
    {
        $plan = $this->currentPlan($companyId);

        return $plan !== null && ! $plan->is_active;
    }

    public function deactivatedPlanMessage(?int $companyId): string
    {
        $planName = $this->currentPlan($companyId)?->name ?? 'current';

        return sprintf(
            'Your current subscription plan (%s) is deactivated. Please upgrade to another subscription plan.',
            $planName
        );
    }

    private function trackingIsDisabledForPlan(?SubscriptionPlan $plan): bool
    {
        return (bool) $plan?->shouldDisableStaffTracking();
    }

    public function ensureCurrentPlanIsActive(?int $companyId, ?string $field = null): void
    {
        if (! $this->hasDeactivatedCurrentPlan($companyId)) {
            return;
        }

        throw ValidationException::withMessages([
            $field ?: 'subscription' => $this->deactivatedPlanMessage($companyId),
        ]);
    }

    public function limitForCompany(?int $companyId, string $key): ?int
    {
        if ($key !== 'staff') {
            return null;
        }

        $activeLicenseKeys = $this->activeLicenseKeys($companyId);

        if ($activeLicenseKeys !== []) {
            return count($activeLicenseKeys);
        }

        $activeSubscriptions = $this->activeSubscriptions($companyId);

        if ($activeSubscriptions->isEmpty()) {
            return null;
        }

        $totalLimit = 0;

        foreach ($activeSubscriptions as $subscription) {
            $planLimit = $subscription->plan?->limitFor('staff');

            if ($planLimit === null) {
                return null;
            }

            $totalLimit += (int) $planLimit;
        }

        return $totalLimit > 0 ? $totalLimit : null;
    }

    public function storageLimitBytes(?int $companyId): int
    {
        $this->ensureCurrentPlanIsActive($companyId);

        $activeSubscriptions = $this->activeSubscriptions($companyId);
        $baseStorageLimitMb = $activeSubscriptions->isEmpty()
            ? (int) SubscriptionPlan::storageLimitMb()
            : (int) $activeSubscriptions->sum(function (CompanySubscription $subscription): int {
                return (int) ($subscription->plan?->planStorageLimitMb() ?? SubscriptionPlan::storageLimitMb());
            });

        if (! $companyId) {
            return (int) (max(1, (int) $baseStorageLimitMb) * 1024 * 1024);
        }

        $company = Company::query()->select(['id', 'storage_addon_mb'])->find($companyId);
        $totalMb = $company ? (int) $company->totalStorageLimitMb($baseStorageLimitMb) : (int) $baseStorageLimitMb;

        return (int) (max(1, (int) round($totalMb)) * 1024 * 1024);
    }

    public function usageCount(int $companyId, string $key): int
    {
        return $this->totalUsageCount($companyId, $key);
    }

    public function ensureWithinLimit(int $companyId, string $key, int $additional = 1, ?string $field = null): void
    {
        $this->ensureCurrentPlanIsActive($companyId, $field);

        if ($key !== 'staff') {
            return;
        }

        $limit = $this->limitForCompany($companyId, $key);

        if ($limit === null) {
            return;
        }

        $current = $this->usageCount($companyId, $key);

        if (($current + $additional) <= $limit) {
            return;
        }

        $definition = SubscriptionPlan::quotaDefinitions()[$key] ?? ['label' => ucfirst($key)];
        $planName = $this->currentPlan($companyId)?->name ?? 'current';
        $message = sprintf(
            'Your %s plan allows up to %s %s. Please upgrade your subscription to increase this limit.',
            $planName,
            $limit,
            strtolower((string) $definition['label'])
        );

        throw ValidationException::withMessages([
            $field ?: $key => $message,
        ]);
    }

    public function currentUsageSummary(int $companyId): array
    {
        $used = $this->usageCount($companyId, 'staff');
        $limit = $this->limitForCompany($companyId, 'staff');

        return [
            'staff' => $used,
            'staff_limit' => $limit,
            'staff_limit_label' => $limit === null ? 'Unlimited' : number_format($limit),
            'remaining_members' => $limit === null ? null : max($limit - $used, 0),
            'license_keys' => $this->activeLicenseKeys($companyId),
        ];
    }

    public function subscriptionUsageSummary(int $companyId, ?int $companySubscriptionId = null): array
    {
        $subscription = null;

        if ($companySubscriptionId !== null) {
            $subscription = $this->activeSubscriptions($companyId)
                ->firstWhere('id', $companySubscriptionId);
        }

        if (! $subscription) {
            $subscription = $this->activeSubscriptions($companyId)->first();
        }

        if (! $subscription) {
            return [
                'company_subscription_id' => null,
                'plan_name' => null,
                'billing_cycle' => null,
                'total_members' => null,
                'total_members_label' => 'Unlimited',
                'used_members' => 0,
                'remaining_members' => null,
                'license_keys' => [],
            ];
        }

        return $this->subscriptionStats($companyId, $subscription);
    }

    public function snapshotUsageBaseline(int $companyId): array
    {
        return [
            'staff' => $this->totalUsageCount($companyId, 'staff'),
        ];
    }

    private function baselineForSubscription(CompanySubscription $subscription): array
    {
        $baseline = is_array($subscription->usage_baseline) ? $subscription->usage_baseline : null;

        if ($baseline !== null) {
            return $baseline;
        }

        $baseline = $this->snapshotUsageBaseline((int) $subscription->company_id);

        $subscription->forceFill([
            'usage_baseline' => $baseline,
        ])->saveQuietly();

        return $baseline;
    }

    public function activeLicenseKeys(?int $companyId): array
    {
        return $this->activeSubscriptions($companyId)
            ->flatMap(function (CompanySubscription $subscription): Collection {
                return collect($subscription->licenseKeyList());
            })
            ->values()
            ->all();
    }

    public function activeLicenseKeyOptions(?int $companyId): array
    {
        return $this->activeSubscriptions($companyId)
            ->flatMap(function (CompanySubscription $subscription): Collection {
                return collect($subscription->licenseKeyList())
                    ->map(fn (string $licenseKey) => [
                        'license_key' => $licenseKey,
                        'company_subscription_id' => $subscription->id,
                        'subscription_plan_id' => $subscription->subscription_plan_id,
                        'plan_name' => $subscription->plan?->name ?? 'Subscription',
                        'billing_cycle' => $subscription->plan?->billing_cycle ?? 'monthly',
                        'status' => $subscription->status,
                        'starts_at' => $subscription->starts_at,
                        'ends_at' => $subscription->ends_at,
                    ]);
            })
            ->values()
            ->all();
    }

    public function activeSubscriptionsWithUsage(?int $companyId): Collection
    {
        if (! $companyId) {
            return collect();
        }

        $usedLicenseKeys = Staff::query()
            ->where('company_id', $companyId)
            ->whereNotNull('license_key')
            ->pluck('license_key')
            ->map(fn ($licenseKey) => (string) $licenseKey)
            ->all();

        return $this->activeSubscriptions($companyId)
            ->map(function (CompanySubscription $subscription) use ($companyId, $usedLicenseKeys): array {
                return [
                    'subscription' => $subscription,
                    'usage' => $this->subscriptionStats($companyId, $subscription, $usedLicenseKeys),
                ];
            })
            ->values();
    }

    public function availableSubscriptionOptions(?int $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        $usedLicenseKeys = Staff::query()
            ->where('company_id', $companyId)
            ->whereNotNull('license_key')
            ->pluck('license_key')
            ->map(fn ($licenseKey) => (string) $licenseKey)
            ->all();

        return $this->activeSubscriptions($companyId)
            ->map(function (CompanySubscription $subscription) use ($companyId, $usedLicenseKeys): array {
                return $this->subscriptionStats($companyId, $subscription, $usedLicenseKeys);
            })
            ->filter(fn (array $option) => $option['remaining_members'] === null || (int) ($option['remaining_members'] ?? 0) > 0)
            ->values()
            ->all();
    }

    public function availableLicenseKeyOptions(?int $companyId): array
    {
        if (! $companyId) {
            return [];
        }

        $usedLicenseKeys = Staff::query()
            ->where('company_id', $companyId)
            ->whereNotNull('license_key')
            ->pluck('license_key')
            ->map(fn ($licenseKey) => (string) $licenseKey)
            ->all();

        return array_values(array_filter(
            $this->activeLicenseKeyOptions($companyId),
            fn (array $option) => ! in_array($option['license_key'], $usedLicenseKeys, true)
        ));
    }

    public function nextAvailableLicenseKey(int $companyId, int $companySubscriptionId): ?string
    {
        $subscription = $this->activeSubscriptions($companyId)
            ->firstWhere('id', $companySubscriptionId);

        if (! $subscription) {
            return null;
        }

        $usedLicenseKeys = Staff::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($subscription) {
                $query->where('company_subscription_id', $subscription->id)
                    ->orWhereIn('license_key', $subscription->licenseKeyList());
            })
            ->pluck('license_key')
            ->map(fn ($licenseKey) => (string) $licenseKey)
            ->all();

        foreach ($subscription->licenseKeyList() as $licenseKey) {
            if (! in_array($licenseKey, $usedLicenseKeys, true)) {
                return $licenseKey;
            }
        }

        return null;
    }

    private function subscriptionStats(int $companyId, CompanySubscription $subscription, ?array $usedLicenseKeys = null): array
    {
        $allLicenseKeys = collect($subscription->licenseKeyList());
        $usedLicenseKeys ??= Staff::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($subscription) {
                $query->where('company_subscription_id', $subscription->id)
                    ->orWhereIn('license_key', $subscription->licenseKeyList());
            })
            ->pluck('license_key')
            ->map(fn ($licenseKey) => (string) $licenseKey)
            ->all();

        $matchedUsedLicenseKeys = $allLicenseKeys
            ->filter(fn (string $licenseKey) => in_array($licenseKey, $usedLicenseKeys, true))
            ->values();

        $availableLicenseKeys = $allLicenseKeys
            ->filter(fn (string $licenseKey) => ! in_array($licenseKey, $usedLicenseKeys, true))
            ->values();

        $subscriptionAssignedStaffCount = Staff::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($subscription) {
                $query->where('company_subscription_id', $subscription->id)
                    ->orWhereIn('license_key', $subscription->licenseKeyList());
            })
            ->count();

        $totalMembers = $subscription->licenseSeatCount() ?? $subscription->plan?->limitFor('staff');
        $usedMembers = $subscriptionAssignedStaffCount;
        $remainingMembers = $totalMembers === null
            ? null
            : max((int) $totalMembers - $usedMembers, 0);

        return [
            'company_subscription_id' => $subscription->id,
            'subscription_plan_id' => $subscription->subscription_plan_id,
            'plan_name' => $subscription->plan?->name ?? 'Subscription',
            'billing_cycle' => $subscription->billing_cycle ?? $subscription->plan?->billing_cycle ?? 'monthly',
            'status' => $subscription->status,
            'starts_at' => $subscription->starts_at,
            'ends_at' => $subscription->ends_at,
            'total_members' => $totalMembers,
            'total_members_label' => $totalMembers === null ? 'Unlimited' : number_format((int) $totalMembers),
            'used_members' => $usedMembers,
            'remaining_members' => $remainingMembers,
            'remaining_members_label' => $remainingMembers === null ? 'Unlimited' : number_format((int) $remainingMembers),
            'all_license_keys' => $allLicenseKeys->values()->all(),
            'used_license_keys' => $matchedUsedLicenseKeys->all(),
            'remaining_license_keys' => $availableLicenseKeys->all(),
            'available_license_keys' => $availableLicenseKeys->all(),
            'license_keys' => $availableLicenseKeys->all(),
            'label' => sprintf(
                '%s (%s) - %s remaining',
                $subscription->plan?->name ?? 'Subscription',
                ucfirst((string) ($subscription->billing_cycle ?? $subscription->plan?->billing_cycle ?? 'monthly')),
                $remainingMembers === null ? 'Unlimited' : number_format((int) $remainingMembers)
            ),
        ];
    }

    private function totalUsageCount(int $companyId, string $key): int
    {
        return match ($key) {
            'staff' => Staff::query()->where('company_id', $companyId)->whereHas('user')->count(),
            default => 0,
        };
    }
}
