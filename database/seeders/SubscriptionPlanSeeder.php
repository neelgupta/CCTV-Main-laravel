<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'price' => 999,
                'monthly_price' => 999,
                'yearly_price' => 9999,
                'billing_cycle' => 'monthly',
                'description' => 'For single-user operations.',
                'staff_limit' => 5,
                // Retired: only Growth and Enterprise are sold going forward.
                // Kept inactive (not deleted) so historical subscriptions stay intact.
                'is_active' => false,
                'is_popular' => false,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'price' => 499,
                'monthly_price' => 499,
                'yearly_price' => 4999,
                'billing_cycle' => 'monthly',
                'description' => 'For growing teams handling more clients and staff.',
                'staff_limit' => 100,
                'is_active' => true,
                'is_popular' => true,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'price' => 9999,
                'monthly_price' => 9999,
                'yearly_price' => 99999,
                'billing_cycle' => 'yearly',
                'description' => 'For large multi-user operations.',
                'staff_limit' => null,
                'is_active' => true,
                'is_popular' => false,
            ],
        ];

        foreach ($plans as $plan) {
            SubscriptionPlan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
