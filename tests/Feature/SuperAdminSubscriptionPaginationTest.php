<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminSubscriptionPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_paginate_subscription_records(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $plan = $this->createPlan();

        $companies = collect();

        for ($i = 1; $i <= 11; $i++) {
            $company = Company::create([
                'name' => 'Subscription Company ' . $i,
                'company_code' => 'SC-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'email' => 'subscription-' . $i . '@example.com',
                'address' => '12 Example Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'is_active' => true,
                'is_onboarded' => true,
            ]);

            CompanySubscription::create([
                'company_id' => $company->id,
                'subscription_plan_id' => $plan->id,
                'billing_cycle' => 'monthly',
                'starts_at' => now()->subDays(11 - $i)->toDateString(),
                'ends_at' => now()->addMonth()->toDateString(),
                'status' => 'active',
                'subscriber_email' => $company->email,
                'license_key' => 'LIC-SUB-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'license_count' => 1,
                'license_keys' => ['LIC-SUB-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
            ]);

            $companies->push($company);
        }

        $response = $this->actingAs($superAdmin)->get(route('super-admin.subscriptions', [
            'per_page' => 10,
            'page' => 2,
        ]));

        $response->assertOk();
        $response->assertSee('Subscription Management (Super Admin)');
        $response->assertSee('Showing 11 to 11 of 11 records');
        $response->assertSee('Subscription Company 1');
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'name' => 'Platform Admin',
            'email' => 'superadmin-subscriptions@example.com',
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function createPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-' . Str::lower(Str::random(4)),
            'price' => 999,
            'monthly_price' => 999,
            'yearly_price' => 9999,
            'billing_cycle' => 'monthly',
            'description' => 'Growth plan',
            'staff_limit' => 10,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ]);
    }
}
