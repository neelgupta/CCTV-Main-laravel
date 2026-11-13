<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\RazorpayWebhookEvent;
use App\Models\SubscriptionPayment;
use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class RazorpayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const WEBHOOK_SECRET = 'webhook-test-secret';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.razorpay.webhook_secret', self::WEBHOOK_SECRET);
    }

    public function test_rejects_invalid_signature(): void
    {
        $body = json_encode(['event' => 'subscription.activated', 'payload' => []]);

        $this->sendWebhook($body, 'evt_bad', 'not-a-valid-signature')->assertStatus(400);

        $this->assertDatabaseCount('razorpay_webhook_events', 0);
    }

    public function test_charged_event_extends_subscription_and_records_payment(): void
    {
        $subscription = $this->createRecurringSubscription();
        $periodEnd = now()->addMonth()->startOfDay();

        $this->sendEvent('evt_charged_1', 'subscription.charged', [
            'subscription' => $this->subscriptionEntity($subscription, [
                'status' => 'active',
                'current_start' => now()->timestamp,
                'current_end' => $periodEnd->timestamp,
                'paid_count' => 1,
            ]),
            'payment' => ['entity' => [
                'id' => 'pay_001',
                'amount' => 118000,
                'currency' => 'INR',
                'status' => 'captured',
                'method' => 'card',
                'invoice_id' => 'inv_001',
                'created_at' => now()->timestamp,
            ]],
        ])->assertOk()->assertJson(['status' => 'processed']);

        $subscription->refresh();
        $licenseKeys = $subscription->licenseKeyList();

        $this->assertSame('active', $subscription->status);
        $this->assertSame($periodEnd->toDateString(), $subscription->ends_at->toDateString());
        $this->assertSame(1, $subscription->paid_count);
        $this->assertSame('1180.00', (string) $subscription->amount_paid);
        $this->assertSame('pay_001', $subscription->razorpay_payment_id);

        $payment = SubscriptionPayment::query()->where('razorpay_payment_id', 'pay_001')->firstOrFail();
        $this->assertSame(SubscriptionPayment::STATUS_CAPTURED, $payment->status);
        $this->assertSame('1180.00', (string) $payment->amount);
        $this->assertSame('180.00', (string) $payment->gst_amount);

        // Second cycle: same row, same license keys, cumulative amount.
        $nextPeriodEnd = now()->addMonths(2)->startOfDay();
        $this->sendEvent('evt_charged_2', 'subscription.charged', [
            'subscription' => $this->subscriptionEntity($subscription, [
                'status' => 'active',
                'current_start' => $periodEnd->timestamp,
                'current_end' => $nextPeriodEnd->timestamp,
                'paid_count' => 2,
            ]),
            'payment' => ['entity' => ['id' => 'pay_002', 'amount' => 118000, 'currency' => 'INR']],
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame($nextPeriodEnd->toDateString(), $subscription->ends_at->toDateString());
        $this->assertSame('2360.00', (string) $subscription->amount_paid);
        $this->assertSame($licenseKeys, $subscription->licenseKeyList());
        $this->assertSame(1, CompanySubscription::query()->count());
    }

    public function test_duplicate_event_is_processed_once(): void
    {
        $subscription = $this->createRecurringSubscription();
        $payload = [
            'subscription' => $this->subscriptionEntity($subscription, ['status' => 'active', 'current_end' => now()->addMonth()->timestamp]),
            'payment' => ['entity' => ['id' => 'pay_dup', 'amount' => 118000]],
        ];

        $this->sendEvent('evt_dup', 'subscription.charged', $payload)->assertJson(['status' => 'processed']);
        $this->sendEvent('evt_dup', 'subscription.charged', $payload)->assertJson(['status' => 'duplicate']);

        $this->assertSame(1, SubscriptionPayment::query()->count());
        $this->assertSame('1180.00', (string) $subscription->fresh()->amount_paid);
        $this->assertSame(1, RazorpayWebhookEvent::query()->firstOrFail()->attempts);
    }

    public function test_pending_event_starts_grace_period_once(): void
    {
        config()->set('subscription.recurring.grace_days', 3);
        $paidThrough = now()->addDay()->startOfDay();
        $subscription = $this->createRecurringSubscription(['status' => 'active', 'ends_at' => $paidThrough->toDateString()]);

        $this->sendEvent('evt_pending_1', 'subscription.pending', [
            'subscription' => $this->subscriptionEntity($subscription, ['status' => 'pending']),
        ])->assertOk();

        $subscription->refresh();
        $expectedGraceEnd = $paidThrough->copy()->addDays(3)->toDateString();

        $this->assertNotNull($subscription->payment_failed_at);
        $this->assertSame($expectedGraceEnd, $subscription->grace_ends_at->toDateString());
        $this->assertSame($expectedGraceEnd, $subscription->ends_at->toDateString());
        $this->assertSame('active', $subscription->status);

        $this->sendEvent('evt_pending_2', 'subscription.pending', [
            'subscription' => $this->subscriptionEntity($subscription, ['status' => 'pending']),
        ])->assertOk();

        $this->assertSame($expectedGraceEnd, $subscription->fresh()->ends_at->toDateString());
    }

    public function test_halted_event_expires_subscription_after_grace(): void
    {
        $subscription = $this->createRecurringSubscription([
            'status' => 'active',
            'ends_at' => now()->subDays(10)->toDateString(),
            'payment_failed_at' => now()->subDays(10),
            'grace_ends_at' => now()->subDays(7)->toDateString(),
        ]);

        $this->sendEvent('evt_halted', 'subscription.halted', [
            'subscription' => $this->subscriptionEntity($subscription, ['status' => 'halted']),
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame('expired', $subscription->status);
        $this->assertFalse($subscription->auto_renew);
        $this->assertSame('halted', $subscription->razorpay_status);
    }

    public function test_cancelled_event_keeps_access_until_period_end(): void
    {
        $endsAt = now()->addDays(12)->toDateString();
        $subscription = $this->createRecurringSubscription(['status' => 'active', 'ends_at' => $endsAt]);

        $this->sendEvent('evt_cancelled', 'subscription.cancelled', [
            'subscription' => $this->subscriptionEntity($subscription, ['status' => 'cancelled']),
        ])->assertOk();

        $subscription->refresh();
        $this->assertSame('active', $subscription->status);
        $this->assertSame($endsAt, $subscription->ends_at->toDateString());
        $this->assertFalse($subscription->auto_renew);
        $this->assertNotNull($subscription->cancelled_at);
    }

    public function test_unknown_subscription_is_acknowledged(): void
    {
        $this->sendEvent('evt_unknown', 'subscription.activated', [
            'subscription' => ['entity' => ['id' => 'sub_does_not_exist', 'status' => 'active']],
        ])->assertOk()->assertJson(['status' => 'ignored']);
    }

    private function sendEvent(string $eventId, string $event, array $payload): TestResponse
    {
        $body = json_encode([
            'entity' => 'event',
            'event' => $event,
            'contains' => array_keys($payload),
            'payload' => $payload,
            'created_at' => now()->timestamp,
        ]);

        return $this->sendWebhook($body, $eventId, hash_hmac('sha256', $body, self::WEBHOOK_SECRET));
    }

    private function sendWebhook(string $body, string $eventId, string $signature): TestResponse
    {
        return $this->call('POST', route('api.razorpay.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature,
            'HTTP_X_RAZORPAY_EVENT_ID' => $eventId,
        ], $body);
    }

    private function subscriptionEntity(CompanySubscription $subscription, array $overrides = []): array
    {
        return ['entity' => array_merge([
            'id' => $subscription->razorpay_subscription_id,
            'entity' => 'subscription',
            'plan_id' => 'plan_test_001',
            'customer_id' => 'cust_001',
            'quantity' => 1,
            'total_count' => 120,
            'paid_count' => 0,
        ], $overrides)];
    }

    private function createRecurringSubscription(array $overrides = []): CompanySubscription
    {
        $company = Company::create([
            'name' => 'Recurring Co',
            'company_code' => 'RC-' . Str::upper(Str::random(4)),
            'email' => 'recurring-' . Str::lower(Str::random(4)) . '@example.com',
            'address' => '1 Test Street',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'is_active' => true,
            'is_onboarded' => true,
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-' . Str::lower(Str::random(4)),
            'price' => 1000,
            'monthly_price' => 1000,
            'yearly_price' => 10000,
            'billing_cycle' => 'monthly',
            'staff_limit' => 10,
            'is_active' => true,
        ]);

        return CompanySubscription::create(array_merge([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => 'monthly',
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->toDateString(),
            'status' => 'pending',
            'auto_renew' => true,
            'is_recurring' => true,
            'gateway_mode' => 'test',
            'razorpay_subscription_id' => 'sub_' . Str::lower(Str::random(10)),
            'license_key' => 'LIC-RECURRING-001',
            'license_count' => 2,
            'license_keys' => ['LIC-RECURRING-001', 'LIC-RECURRING-002'],
        ], $overrides));
    }
}
