<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Staff;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SubscriptionRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_subscription_can_be_renewed_and_keeps_history_link(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createPlan();

        $expiredSubscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-05-31',
            'status' => 'expired',
            'auto_renew' => false,
            'amount_paid' => 0,
            'billing_reference' => 'SUB-OLD1234',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-OLD1234567890',
            'license_count' => 2,
            'license_keys' => ['LIC-OLD1234567890', 'LIC-OLD1234567891'],
        ]);

        $response = $this->actingAs($user)->post(route('subscription.process'), [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'billing_reference' => 'SUB-NEW1234',
            'renew_subscription_id' => $expiredSubscription->id,
        ]);

        $response->assertRedirect(route('subscription.show'));

        $this->assertDatabaseHas('company_subscriptions', [
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_reference' => 'SUB-NEW1234',
            'renewed_from_subscription_id' => $expiredSubscription->id,
            'status' => 'active',
            'notes' => 'Renewed by company account.',
        ]);
    }

    public function test_create_order_auto_detects_latest_expired_subscription_for_same_plan(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createPlan();

        CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-04-30',
            'status' => 'expired',
            'auto_renew' => false,
            'amount_paid' => 0,
            'billing_reference' => 'SUB-FIRST',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-FIRST1234567890',
            'license_count' => 2,
            'license_keys' => ['LIC-FIRST1234567890', 'LIC-FIRST1234567891'],
        ]);

        $latestExpiredSubscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-05-01',
            'ends_at' => '2026-05-31',
            'status' => 'expired',
            'auto_renew' => false,
            'amount_paid' => 0,
            'billing_reference' => 'SUB-LATEST',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-LATEST1234567890',
            'license_count' => 2,
            'license_keys' => ['LIC-LATEST1234567890', 'LIC-LATEST1234567891'],
        ]);

        $response = $this->actingAs($user)->postJson(route('subscription.create-order'), [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ]);

        $response->assertOk()
            ->assertJson([
                'free' => true,
                'plan_id' => $plan->id,
                'renew_subscription_id' => $latestExpiredSubscription->id,
            ]);
    }

    public function test_company_can_open_invoice_for_its_subscription(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createPlan();

        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-06-09',
            'ends_at' => '2026-07-08',
            'status' => 'active',
            'auto_renew' => true,
            'amount_paid' => 0,
            'billing_reference' => 'SUB-INVOICE',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-INVOICE1234567890',
            'license_count' => 2,
            'license_keys' => ['LIC-INVOICE1234567890', 'LIC-INVOICE1234567891'],
        ]);

        $this->actingAs($user)
            ->get(route('subscription.invoice', $subscription))
            ->assertOk()
            ->assertSee('SUB-INVOICE')
            ->assertSee('Subscription Invoice');
    }

    public function test_onboarding_purchase_assigns_one_plan_seat_and_license_to_company_admin(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        [$company, $user] = $this->createCompanyUser([
            'is_onboarded' => false,
            'onboarding_step' => 4,
        ]);
        $plan = $this->createPlan();

        $response = $this->actingAs($user)
            ->withSession([
                'onboarding.subscription_plan_id' => $plan->id,
                'onboarding.billing_cycle' => 'monthly',
                'onboarding.subscription_mode' => 'paid',
                'onboarding.billing_reference' => 'ONB-SEAT01',
            ])
            ->post(route('onboarding.payment.finalize'));

        $response->assertRedirect(route('dashboard'));

        $subscription = CompanySubscription::query()
            ->where('company_id', $company->id)
            ->latest('id')
            ->firstOrFail();

        $staff = Staff::query()->where('user_id', $user->id)->first();

        $this->assertNotNull($staff);
        $this->assertSame($subscription->id, $staff->company_subscription_id);
        $this->assertContains($staff->license_key, $subscription->licenseKeyList());
        $this->assertSame(
            1,
            app(SubscriptionLimitService::class)->subscriptionUsageSummary($company->id, $subscription->id)['used_members']
        );
    }

    public function test_buying_another_active_plan_does_not_move_company_admin_to_new_plan(): void
    {
        Carbon::setTestNow('2026-06-09 10:00:00');

        [$company, $user] = $this->createCompanyUser();
        $firstPlan = $this->createPlan(['slug' => 'growth-first']);
        $secondPlan = $this->createPlan(['slug' => 'growth-second']);

        $firstSubscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $firstPlan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => '2026-06-09',
            'ends_at' => '2026-07-08',
            'status' => 'active',
            'auto_renew' => true,
            'amount_paid' => 0,
            'billing_reference' => 'SUB-FIRSTACTIVE',
            'subscriber_email' => $company->email,
            'license_key' => 'LIC-FIRSTACTIVE000001',
            'license_count' => 2,
            'license_keys' => ['LIC-FIRSTACTIVE000001', 'LIC-FIRSTACTIVE000002'],
        ]);

        Staff::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'company_subscription_id' => $firstSubscription->id,
            'employee_id' => 'EMP-ADMIN-001',
            'name' => $user->name,
            'join_date' => '2026-06-09',
            'license_key' => 'LIC-FIRSTACTIVE000001',
        ]);

        $response = $this->actingAs($user)->post(route('subscription.process'), [
            'subscription_plan_id' => $secondPlan->id,
            'billing_cycle' => 'monthly',
            'billing_reference' => 'SUB-SECONDPLAN',
        ]);

        $response->assertRedirect(route('subscription.show'));

        $secondSubscription = CompanySubscription::query()
            ->where('company_id', $company->id)
            ->where('subscription_plan_id', $secondPlan->id)
            ->latest('id')
            ->firstOrFail();

        $user->refresh();
        $user->load('staff');

        $this->assertSame($firstSubscription->id, $user->staff?->company_subscription_id);
        $this->assertSame('LIC-FIRSTACTIVE000001', $user->staff?->license_key);
        $this->assertSame(
            0,
            app(SubscriptionLimitService::class)->subscriptionUsageSummary($company->id, $secondSubscription->id)['used_members']
        );
    }

    private function createCompanyUser(array $companyOverrides = []): array
    {
        $company = Company::create(array_merge([
            'name' => 'Acme CCTV',
            'email' => 'acme@example.com',
            'is_active' => true,
            'is_onboarded' => true,
        ], $companyOverrides));

        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'owner@example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return [$company, $user];
    }

    private function createPlan(array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'name' => 'Growth',
            'slug' => 'growth',
            'price' => 0,
            'monthly_price' => 0,
            'yearly_price' => 0,
            'billing_cycle' => 'monthly',
            'description' => 'Growth plan',
            'staff_limit' => 2,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ], $overrides));
    }
}
