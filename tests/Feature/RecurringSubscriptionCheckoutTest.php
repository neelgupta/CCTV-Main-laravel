<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Staff;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\GlobalSettingService;
use App\Services\RecurringSubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RecurringSubscriptionCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const KEY_SECRET = 'rzp_test_secret';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-13 10:00:00');
        config()->set('services.razorpay.key_id', 'rzp_test_key');
        config()->set('services.razorpay.key_secret', self::KEY_SECRET);
        config()->set('services.razorpay.mode', 'test');
        app(GlobalSettingService::class)->put('subscription_recurring_enabled', '1');
    }

    public function test_auto_renew_purchase_creates_pending_row_and_razorpay_subscription(): void
    {
        Http::fake([
            'api.razorpay.com/v1/subscriptions' => Http::response(['id' => 'sub_new001', 'status' => 'created']),
        ]);
        [$company, $user] = $this->createCompanyUser();
        $plan = $this->createLinkedPlan();

        $response = $this->actingAs($user)->post(route('subscription.change'), [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'license_count' => 2,
            'auto_renew' => 1,
        ]);

        $response->assertOk();
        $response->assertSee('sub_new001');
        $response->assertSee('Auto-renew is on');

        Http::assertSent(fn (Request $request) => $request->url() === 'https://api.razorpay.com/v1/subscriptions'
            && $request['plan_id'] === 'plan_linked_monthly'
            && $request['quantity'] === 2
            && $request['total_count'] === 120);

        $subscription = CompanySubscription::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('pending', $subscription->status);
        $this->assertTrue($subscription->is_recurring);
        $this->assertSame('sub_new001', $subscription->razorpay_subscription_id);
        $this->assertSame(2, $subscription->license_count);
        $this->assertSame('test', $subscription->gateway_mode);
    }

    public function test_without_auto_renew_the_one_time_order_flow_is_used(): void
    {
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::response(['id' => 'order_001', 'amount' => 23482, 'currency' => 'INR']),
        ]);
        [, $user] = $this->createCompanyUser();
        $plan = $this->createLinkedPlan();

        $this->actingAs($user)->post(route('subscription.change'), [
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'auto_renew' => 0,
        ])->assertOk()->assertSee('order_001');

        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/v1/subscriptions'));
        $this->assertSame(0, CompanySubscription::query()->count());
    }

    public function test_completing_checkout_activates_subscription_and_records_payment(): void
    {
        $periodEnd = now()->addMonth()->startOfDay();
        Http::fake([
            'api.razorpay.com/v1/subscriptions/sub_chk001' => Http::response([
                'id' => 'sub_chk001',
                'status' => 'active',
                'current_start' => now()->timestamp,
                'current_end' => $periodEnd->timestamp,
                'charge_at' => $periodEnd->timestamp,
                'paid_count' => 1,
            ]),
            'api.razorpay.com/v1/payments/pay_chk001' => Http::response([
                'id' => 'pay_chk001',
                'amount' => 46964,
                'currency' => 'INR',
                'status' => 'captured',
                'method' => 'card',
            ]),
        ]);
        [$company, $user] = $this->createCompanyUser();
        $subscription = $this->createPendingSubscription($company, 'sub_chk001');

        $this->actingAs($user)->post(route('subscription.recurring.complete'), [
            'razorpay_subscription_id' => 'sub_chk001',
            'razorpay_payment_id' => 'pay_chk001',
            'razorpay_signature' => hash_hmac('sha256', 'pay_chk001|sub_chk001', self::KEY_SECRET),
        ])->assertRedirect(route('subscription.show'))->assertSessionHas('success');

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame($periodEnd->toDateString(), $subscription->ends_at->toDateString());
        $this->assertSame('469.64', (string) $subscription->amount_paid);
        $this->assertSame(1, SubscriptionPayment::query()->where('razorpay_payment_id', 'pay_chk001')->count());

        // The company admin gets a seat on the newly active subscription.
        $staff = Staff::query()->where('user_id', $user->id)->first();
        $this->assertSame($subscription->id, $staff?->company_subscription_id);
    }

    public function test_invalid_checkout_signature_keeps_subscription_pending(): void
    {
        Http::fake();
        [$company, $user] = $this->createCompanyUser();
        $subscription = $this->createPendingSubscription($company, 'sub_bad001');

        $this->actingAs($user)->post(route('subscription.recurring.complete'), [
            'razorpay_subscription_id' => 'sub_bad001',
            'razorpay_payment_id' => 'pay_bad001',
            'razorpay_signature' => 'forged',
        ])->assertRedirect(route('subscription.show'))->assertSessionHas('error');

        $this->assertSame('pending', $subscription->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_company_can_cancel_auto_renew_and_keeps_access(): void
    {
        Http::fake([
            'api.razorpay.com/v1/subscriptions/sub_cancel01/cancel' => Http::response(['id' => 'sub_cancel01', 'status' => 'active']),
        ]);
        [$company, $user] = $this->createCompanyUser();
        $subscription = $this->createPendingSubscription($company, 'sub_cancel01', [
            'status' => 'active',
            'ends_at' => now()->addDays(20)->toDateString(),
        ]);

        $this->actingAs($user)
            ->post(route('subscription.cancel-auto-renew', $subscription))
            ->assertRedirect(route('subscription.show'))
            ->assertSessionHas('success');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/sub_cancel01/cancel')
            && $request['cancel_at_cycle_end'] === 1);

        $subscription->refresh();
        $this->assertFalse($subscription->auto_renew);
        $this->assertTrue($subscription->cancel_at_cycle_end);
        $this->assertSame('active', $subscription->status);
    }

    public function test_company_cannot_cancel_another_companys_subscription(): void
    {
        Http::fake();
        [$otherCompany] = $this->createCompanyUser(['email' => 'other@example.com'], 'other-owner@example.com');
        [, $user] = $this->createCompanyUser();
        $subscription = $this->createPendingSubscription($otherCompany, 'sub_other01', ['status' => 'active']);

        $this->actingAs($user)->post(route('subscription.cancel-auto-renew', $subscription))->assertNotFound();
        $this->assertTrue($subscription->fresh()->auto_renew);
    }

    public function test_prune_removes_only_abandoned_unauthorised_checkouts(): void
    {
        Http::fake([
            'api.razorpay.com/v1/subscriptions/sub_old_created' => Http::response(['id' => 'sub_old_created', 'status' => 'created']),
            'api.razorpay.com/v1/subscriptions/sub_old_created/cancel' => Http::response(['id' => 'sub_old_created', 'status' => 'cancelled']),
            'api.razorpay.com/v1/subscriptions/sub_old_authorised' => Http::response(['id' => 'sub_old_authorised', 'status' => 'authenticated']),
        ]);
        [$company] = $this->createCompanyUser();
        $abandoned = $this->createPendingSubscription($company, 'sub_old_created');
        $authorised = $this->createPendingSubscription($company, 'sub_old_authorised');
        CompanySubscription::query()->whereKey([$abandoned->id, $authorised->id])->update(['created_at' => now()->subDays(2)]);
        $recent = $this->createPendingSubscription($company, 'sub_recent');

        $this->assertSame(1, app(RecurringSubscriptionService::class)->prunePending());

        $this->assertNull($abandoned->fresh());
        $this->assertNotNull($authorised->fresh());
        $this->assertNotNull($recent->fresh());
    }

    private function createCompanyUser(array $companyOverrides = [], string $email = 'owner@example.com'): array
    {
        $company = Company::create(array_merge([
            'name' => 'Acme CCTV',
            'email' => 'acme@example.com',
            'is_active' => true,
            'is_onboarded' => true,
        ], $companyOverrides));

        $user = User::factory()->create([
            'company_id' => $company->id,
            'email' => $email,
            'role' => 'company_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        return [$company, $user];
    }

    private function createLinkedPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-' . uniqid(),
            'price' => 199,
            'monthly_price' => 199,
            'yearly_price' => 2388,
            'billing_cycle' => 'monthly',
            'staff_limit' => 10,
            'is_active' => true,
            'razorpay_plan_ids' => [
                'test' => [
                    'monthly' => ['id' => 'plan_linked_monthly', 'amount' => 23482, 'source' => 'manual'],
                ],
            ],
        ]);
    }

    private function createPendingSubscription(Company $company, string $razorpaySubscriptionId, array $overrides = []): CompanySubscription
    {
        $plan = $this->createLinkedPlan();

        return CompanySubscription::create(array_merge([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'status' => 'pending',
            'auto_renew' => true,
            'is_recurring' => true,
            'gateway_mode' => 'test',
            'payment_method' => 'Razorpay',
            'billing_reference' => 'SUB-' . strtoupper(substr(md5($razorpaySubscriptionId), 0, 8)),
            'subscriber_email' => $company->email,
            'razorpay_subscription_id' => $razorpaySubscriptionId,
            'razorpay_status' => 'created',
            'license_key' => 'LIC-' . strtoupper(md5($razorpaySubscriptionId)),
            'license_count' => 2,
            'license_keys' => ['LIC-' . strtoupper(md5($razorpaySubscriptionId)), 'LIC-' . strtoupper(md5($razorpaySubscriptionId . '2'))],
        ], $overrides));
    }
}
