<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionPaymentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_SECRET = 'rzp_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-14 10:00:00');
    }

    public function test_paid_plan_is_not_activated_when_razorpay_keys_are_missing(): void
    {
        $this->setRazorpayKeys('', '');
        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createPaidPlan();

        $this->actingAs($user)->post(route('subscription.process'), [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'billing_reference' => 'SUB-NOKEYS',
            'razorpay_order_id' => 'demo_order_abc',
            'razorpay_payment_id' => 'demo_payment_abc',
            'razorpay_signature' => 'demo-signature',
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('company_subscriptions', ['company_id' => $company->id]);
    }

    public function test_create_order_refuses_paid_checkout_when_razorpay_keys_are_missing(): void
    {
        $this->setRazorpayKeys('', '');
        [, $user] = $this->createCompanyUser();
        $plan = $this->createPaidPlan();

        $this->actingAs($user)->postJson(route('subscription.create-order'), [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ])->assertStatus(503)->assertJsonMissing(['demo' => true]);
    }

    public function test_paid_plan_is_not_activated_with_forged_signature(): void
    {
        $this->setRazorpayKeys('rzp_test_key', self::KEY_SECRET);
        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createPaidPlan();

        $this->actingAs($user)->post(route('subscription.process'), [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'billing_reference' => 'SUB-FORGED',
            'razorpay_order_id' => 'order_001',
            'razorpay_payment_id' => 'pay_001',
            'razorpay_signature' => 'forged',
        ])->assertSessionHas('error');

        $this->assertDatabaseMissing('company_subscriptions', ['company_id' => $company->id]);
    }

    public function test_paid_plan_is_activated_with_valid_signature_for_its_own_order(): void
    {
        $this->setRazorpayKeys('rzp_test_key', self::KEY_SECRET);
        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createPaidPlan();
        $this->createOrder($user, $plan, 'order_001');

        $this->actingAs($user)
            ->post(route('subscription.process'), $this->paymentPayload($plan, 'order_001', 'pay_001', 'SUB-VALID'))
            ->assertRedirect(route('subscription.show'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('company_subscriptions', [
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_reference' => 'SUB-VALID',
            'razorpay_order_id' => 'order_001',
            'razorpay_payment_id' => 'pay_001',
            'status' => 'active',
        ]);
    }

    public function test_valid_signature_is_rejected_for_an_order_this_session_did_not_create(): void
    {
        $this->setRazorpayKeys('rzp_test_key', self::KEY_SECRET);
        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createPaidPlan();

        $this->actingAs($user)
            ->post(route('subscription.process'), $this->paymentPayload($plan, 'order_001', 'pay_001', 'SUB-UNKNOWN'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('company_subscriptions', ['company_id' => $company->id]);
    }

    public function test_payment_for_a_cheaper_plan_cannot_activate_a_more_expensive_plan(): void
    {
        $this->setRazorpayKeys('rzp_test_key', self::KEY_SECRET);
        [$company, $user] = $this->createCompanyUser();
        $cheapPlan = $this->createPaidPlan(['name' => 'Starter', 'slug' => 'starter', 'price' => 99, 'monthly_price' => 99, 'yearly_price' => 999]);
        $expensivePlan = $this->createPaidPlan(['name' => 'Enterprise', 'slug' => 'enterprise', 'price' => 9999, 'monthly_price' => 9999, 'yearly_price' => 99999]);
        $this->createOrder($user, $cheapPlan, 'order_cheap');

        $this->actingAs($user)
            ->post(route('subscription.process'), $this->paymentPayload($expensivePlan, 'order_cheap', 'pay_cheap', 'SUB-SWAP'))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('company_subscriptions', ['company_id' => $company->id]);
    }

    public function test_same_payment_cannot_activate_a_subscription_twice(): void
    {
        $this->setRazorpayKeys('rzp_test_key', self::KEY_SECRET);
        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createPaidPlan();
        $this->createOrder($user, $plan, 'order_001');
        $payload = $this->paymentPayload($plan, 'order_001', 'pay_001', 'SUB-FIRST');

        $this->actingAs($user)->post(route('subscription.process'), $payload)->assertSessionHas('success');
        $this->actingAs($user)->post(route('subscription.process'), $payload)->assertSessionHas('error');

        $this->assertSame(1, CompanySubscription::query()->where('company_id', $company->id)->count());
    }

    private function createOrder(User $user, SubscriptionPlan $plan, string $orderId): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => $orderId, 'amount' => 0, 'currency' => 'INR']),
        ]);

        $this->actingAs($user)->postJson(route('subscription.create-order'), [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
        ])->assertOk()->assertJson(['order_id' => $orderId]);
    }

    private function paymentPayload(SubscriptionPlan $plan, string $orderId, string $paymentId, string $reference): array
    {
        return [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'billing_reference' => $reference,
            'razorpay_order_id' => $orderId,
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => hash_hmac('sha256', $orderId . '|' . $paymentId, self::KEY_SECRET),
        ];
    }

    private function setRazorpayKeys(string $keyId, string $keySecret): void
    {
        config()->set('services.razorpay.key_id', $keyId);
        config()->set('services.razorpay.key_secret', $keySecret);
        config()->set('services.razorpay.mode', 'test');
    }

    private function createCompanyUser(): array
    {
        $company = Company::create([
            'name' => 'Acme CCTV',
            'email' => 'acme@example.com',
            'is_active' => true,
            'is_onboarded' => true,
        ]);

        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => 'owner@example.com',
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return [$company, $user];
    }

    private function createPaidPlan(array $overrides = []): SubscriptionPlan
    {
        return SubscriptionPlan::create(array_merge([
            'name' => 'Growth',
            'slug' => 'growth',
            'price' => 999,
            'monthly_price' => 999,
            'yearly_price' => 9999,
            'billing_cycle' => 'monthly',
            'description' => 'Growth plan',
            'staff_limit' => 2,
            'is_active' => true,
            'is_popular' => false,
            'has_free_trial' => false,
        ], $overrides));
    }
}
