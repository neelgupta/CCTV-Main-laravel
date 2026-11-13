<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Role;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_on_plan_step_can_access_dashboard_before_subscription_is_completed(): void
    {
        $user = $this->createCompanyAdmin([
            'is_onboarded' => false,
            'onboarding_step' => 4,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Please complete your required profile details');
    }

    public function test_skip_plan_marks_company_as_onboarded(): void
    {
        $user = $this->createCompanyAdmin([
            'is_onboarded' => false,
            'onboarding_step' => 4,
        ]);

        $response = $this->actingAs($user)->post(route('onboarding.skip'));

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertTrue((bool) $user->company->fresh()->is_onboarded);
    }

    public function test_module_access_without_active_plan_redirects_to_dashboard_with_subscription_warning(): void
    {
        $user = $this->createCompanyAdmin([
            'is_onboarded' => true,
            'onboarding_step' => 4,
            'address' => '42 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
        ]);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('subscription_feature_warning', true);
    }

    public function test_module_access_with_active_plan_but_missing_profile_redirects_to_dashboard_with_setup_warning(): void
    {
        $user = $this->createCompanyAdmin([
            'is_onboarded' => true,
            'onboarding_step' => 4,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth',
            'price' => 0,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_cycle' => 'monthly',
            'description' => 'Growth plan',
            'staff_limit' => 5,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ]);

        CompanySubscription::create([
            'company_id' => $user->company_id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
            'status' => 'active',
            'subscriber_email' => $user->company->email,
            'license_key' => 'LIC-SETUP-001',
            'license_count' => 1,
            'license_keys' => ['LIC-SETUP-001'],
        ]);

        $response = $this->actingAs($user)->get('/settings');

        $response->assertRedirect(route('dashboard', absolute: false));
        $response->assertSessionHas('setup_completion_warning', true);
    }

    private function createCompanyAdmin(array $companyOverrides = []): User
    {
        $company = Company::create(array_merge([
            'name' => 'Setup Test Company',
            'email' => 'setup@example.com',
            'mobile_number' => '9876543210',
            'phone_number' => '9876543210',
            'is_active' => true,
            'is_onboarded' => false,
            'onboarding_step' => 4,
        ], $companyOverrides));

        $role = Role::create([
            'company_id' => $company->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
        ]);

        return User::factory()->create([
            'company_id' => $company->id,
            'role_id' => $role->id,
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }
}
