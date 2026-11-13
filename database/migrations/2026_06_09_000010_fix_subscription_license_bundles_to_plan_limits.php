<?php

use App\Models\CompanySubscription;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $subscriptions = DB::table('company_subscriptions')
            ->join('subscription_plans', 'subscription_plans.id', '=', 'company_subscriptions.subscription_plan_id')
            ->whereIn('company_subscriptions.status', ['active', 'trialing'])
            ->select([
                'company_subscriptions.id',
                'company_subscriptions.license_key',
                'company_subscriptions.license_count',
                'company_subscriptions.license_keys',
                'subscription_plans.staff_limit as plan_staff_limit',
            ])
            ->orderBy('company_subscriptions.id')
            ->get();

        foreach ($subscriptions as $subscription) {
            if (! is_numeric($subscription->plan_staff_limit) || (int) $subscription->plan_staff_limit <= 0) {
                continue;
            }

            $existingKeys = [];

            if (is_string($subscription->license_keys) && filled($subscription->license_keys)) {
                $decoded = json_decode($subscription->license_keys, true);
                if (is_array($decoded)) {
                    $existingKeys = $decoded;
                }
            } elseif (is_array($subscription->license_keys)) {
                $existingKeys = $subscription->license_keys;
            }

            if ($existingKeys === [] && filled($subscription->license_key)) {
                $existingKeys = [(string) $subscription->license_key];
            }

            $existingKeys = array_values(array_unique(array_filter(
                array_map(static fn ($key) => is_string($key) ? trim($key) : (string) $key, $existingKeys),
                static fn ($key) => filled($key)
            )));

            $targetSeatCount = max((int) $subscription->plan_staff_limit, count($existingKeys));

            while (count($existingKeys) < $targetSeatCount) {
                $candidate = CompanySubscription::generateLicenseKey();

                if (! in_array($candidate, $existingKeys, true)) {
                    $existingKeys[] = $candidate;
                }
            }

            DB::table('company_subscriptions')
                ->where('id', $subscription->id)
                ->update([
                    'license_key' => $existingKeys[0] ?? $subscription->license_key,
                    'license_count' => count($existingKeys) ?: null,
                    'license_keys' => json_encode($existingKeys),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally left blank: the migration only repairs license bundles.
    }
};
