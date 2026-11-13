<?php

namespace App\Services;

use App\Models\CompanySubscription;
use App\Models\Staff;
use App\Models\User;

class CompanyAdminSeatService
{
    public function ensureAdminStaffProfile(int $companyId, ?User $preferredAdmin = null): ?Staff
    {
        $admin = $this->resolveAdminUser($companyId, $preferredAdmin);

        if (! $admin) {
            return null;
        }

        $staff = $admin->staff;

        if (! $staff) {
            $staff = Staff::create([
                'user_id' => $admin->id,
                'company_id' => $companyId,
                'employee_id' => Staff::generateEmployeeId($companyId),
                'name' => $admin->name,
                'contact_number' => $admin->company?->mobile_number ?: $admin->company?->phone_number,
                'join_date' => now()->toDateString(),
                'role_id' => $admin->role_id,
            ]);
        }

        return $staff;
    }

    public function assignSeatIfNeeded(int $companyId, CompanySubscription $subscription, ?User $preferredAdmin = null): void
    {
        $admin = $this->resolveAdminUser($companyId, $preferredAdmin);

        if (! $admin) {
            return;
        }

        $staff = $this->ensureAdminStaffProfile($companyId, $admin);

        if ($this->adminAlreadyHasActiveSeat($companyId, $admin)) {
            return;
        }

        $licenseKey = app(SubscriptionLimitService::class)->nextAvailableLicenseKey($companyId, $subscription->id);

        if (! $licenseKey) {
            return;
        }

        if ($staff) {
            $staff->forceFill([
                'company_subscription_id' => $subscription->id,
                'license_key' => $licenseKey,
                'name' => $staff->name ?: $admin->name,
                'role_id' => $staff->role_id ?: $admin->role_id,
                'join_date' => $staff->join_date ?: now()->toDateString(),
                'contact_number' => $staff->contact_number ?: ($admin->company?->mobile_number ?: $admin->company?->phone_number),
            ])->save();
        }
    }

    private function resolveAdminUser(int $companyId, ?User $preferredAdmin = null): ?User
    {
        if ($preferredAdmin && (int) $preferredAdmin->company_id === $companyId && $preferredAdmin->isCompanyAdmin()) {
            return $preferredAdmin->loadMissing(['staff', 'company']);
        }

        return User::query()
            ->with(['staff', 'company', 'roleRecord'])
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereIn('role', ['company_admin', 'companyadmin'])
                    ->orWhereHas('roleRecord', function ($roleQuery) {
                        $roleQuery->whereIn('slug', ['company_admin', 'companyadmin']);
                    });
            })
            ->orderBy('id')
            ->first();
    }

    private function adminAlreadyHasActiveSeat(int $companyId, User $admin): bool
    {
        $staff = $admin->staff;

        if (! $staff) {
            return false;
        }

        $activeSubscriptions = app(SubscriptionLimitService::class)->activeSubscriptions($companyId);
        $activeSubscriptionIds = $activeSubscriptions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $activeLicenseKeys = $activeSubscriptions
            ->flatMap(fn (CompanySubscription $subscription) => $subscription->licenseKeyList())
            ->map(fn ($licenseKey) => (string) $licenseKey)
            ->all();

        return in_array((int) $staff->company_subscription_id, $activeSubscriptionIds, true)
            || ($staff->license_key !== null && in_array((string) $staff->license_key, $activeLicenseKeys, true));
    }
}
