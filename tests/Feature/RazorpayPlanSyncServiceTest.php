<?php

namespace Tests\Feature;

use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\GlobalSettingService;
use App\Services\RazorpayPlanSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RazorpayPlanSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.razorpay.key_id', 'rzp_test_key');
        config()->set('services.razorpay.key_secret', 'rzp_test_secret');
        config()->set('services.razorpay.mode', 'test');
    }

    public function test_creates_plan_once_and_reuses_it(): void
    {
        Http::fake(['api.razorpay.com/v1/plans' => Http::response(['id' => 'plan_first'], 200)]);
        $plan = $this->createPlan(1000);

        $service = app(RazorpayPlanSyncService::class);

        $this->assertSame('plan_first', $service->planIdFor($plan, 'monthly'));
        $this->assertSame('plan_first', $service->planIdFor($plan->fresh(), 'monthly'));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['period'] === 'monthly'
            && $request['item']['amount'] === 118000
            && $request['item']['currency'] === 'INR');

        $this->assertSame(
            ['id' => 'plan_first', 'amount' => 118000],
            array_intersect_key($plan->fresh()->razorpayPlanFor('test', 'monthly'), ['id' => true, 'amount' => true])
        );
    }

    public function test_creates_new_plan_when_price_or_gst_changes(): void
    {
        Http::fakeSequence('api.razorpay.com/v1/plans')
            ->push(['id' => 'plan_v1'])
            ->push(['id' => 'plan_v2'])
            ->push(['id' => 'plan_v3']);
        $plan = $this->createPlan(1000);
        $service = app(RazorpayPlanSyncService::class);

        $this->assertSame('plan_v1', $service->planIdFor($plan, 'monthly'));

        $plan->update(['monthly_price' => 1500]);
        $this->assertSame('plan_v2', $service->planIdFor($plan->fresh(), 'monthly'));
        $this->assertSame(177000, $plan->fresh()->razorpayPlanFor('test', 'monthly')['amount']);

        app(GlobalSettingService::class)->put('subscription_gst_percentage', '12');
        $this->assertSame('plan_v3', $service->planIdFor($plan->fresh(), 'monthly'));
        $this->assertSame(168000, $plan->fresh()->razorpayPlanFor('test', 'monthly')['amount']);
    }

    public function test_plans_are_stored_per_mode(): void
    {
        Http::fakeSequence('api.razorpay.com/v1/plans')
            ->push(['id' => 'plan_test'])
            ->push(['id' => 'plan_live']);
        $plan = $this->createPlan(1000);
        $service = app(RazorpayPlanSyncService::class);

        $this->assertSame('plan_test', $service->planIdFor($plan, 'yearly'));

        config()->set('services.razorpay.mode', 'live');
        $this->assertSame('plan_live', $service->planIdFor($plan->fresh(), 'yearly'));

        $stored = $plan->fresh();
        $this->assertSame('plan_test', $stored->razorpayPlanFor('test', 'yearly')['id']);
        $this->assertSame('plan_live', $stored->razorpayPlanFor('live', 'yearly')['id']);
    }

    public function test_manual_plan_is_validated_against_price_and_gst(): void
    {
        Http::fake([
            'api.razorpay.com/v1/plans/plan_good' => Http::response($this->remotePlan('plan_good', 'monthly', 118000)),
            'api.razorpay.com/v1/plans/plan_cheap' => Http::response($this->remotePlan('plan_cheap', 'monthly', 100000)),
            'api.razorpay.com/v1/plans/plan_yearly' => Http::response($this->remotePlan('plan_yearly', 'yearly', 118000)),
            'api.razorpay.com/v1/plans/plan_missing' => Http::response(['error' => ['description' => 'The id provided does not exist']], 400),
        ]);
        $service = app(RazorpayPlanSyncService::class);

        $this->assertNull($service->validateManualPlan('plan_good', 'monthly', 1000));
        $this->assertStringContainsString('this price plus GST is Rs. 1,180.00', (string) $service->validateManualPlan('plan_cheap', 'monthly', 1000));
        $this->assertStringContainsString('bills every 1 year; the Monthly Plan ID needs a plan billed every 1 month', (string) $service->validateManualPlan('plan_yearly', 'monthly', 1000));
        $this->assertStringContainsString('could not be found', (string) $service->validateManualPlan('plan_missing', 'monthly', 1000));
    }

    public function test_linked_manual_plan_is_used_instead_of_creating_one(): void
    {
        Http::fake();
        $plan = $this->createPlan(1000);
        $service = app(RazorpayPlanSyncService::class);

        $service->linkManualPlans($plan, ['monthly' => 'plan_dashboard']);

        $this->assertSame('plan_dashboard', $service->planIdFor($plan->fresh(), 'monthly'));
        $this->assertSame('manual', $plan->fresh()->razorpayPlanFor('test', 'monthly')['source']);
        Http::assertNothingSent();
    }

    public function test_manual_plan_with_outdated_price_blocks_checkout(): void
    {
        Http::fake();
        $plan = $this->createPlan(1000);
        $service = app(RazorpayPlanSyncService::class);
        $service->linkManualPlans($plan, ['monthly' => 'plan_dashboard']);

        $plan->update(['monthly_price' => 1500]);

        try {
            $service->planIdFor($plan->fresh(), 'monthly');
            $this->fail('Expected an outdated manual plan to be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('Link an updated Razorpay plan', $exception->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_clearing_manual_link_falls_back_to_auto_and_keeps_auto_plans(): void
    {
        Http::fake(['api.razorpay.com/v1/plans' => Http::response(['id' => 'plan_auto_yearly'])]);
        $plan = $this->createPlan(1000);
        $service = app(RazorpayPlanSyncService::class);

        $service->planIdFor($plan, 'yearly');
        $service->linkManualPlans($plan->fresh(), ['monthly' => 'plan_dashboard']);
        $service->linkManualPlans($plan->fresh(), ['monthly' => '', 'yearly' => '']);

        $stored = $plan->fresh();
        $this->assertNull($stored->razorpayPlanFor('test', 'monthly'));
        $this->assertSame('plan_auto_yearly', $stored->razorpayPlanFor('test', 'yearly')['id']);
    }

    public function test_plan_edit_links_valid_plan_and_rejects_wrong_amount(): void
    {
        Http::fake([
            'api.razorpay.com/v1/plans/plan_good' => Http::response($this->remotePlan('plan_good', 'monthly', 118000)),
            'api.razorpay.com/v1/plans/plan_wrong' => Http::response($this->remotePlan('plan_wrong', 'monthly', 100000)),
        ]);
        $superAdmin = $this->createSuperAdmin();
        $plan = $this->createPlan(1000);

        $this->actingAs($superAdmin)
            ->patch(route('super-admin.plans.update', $plan), $this->planFormData($plan, ['razorpay_plan_id_monthly' => 'plan_wrong']))
            ->assertSessionHasErrorsIn('planEditForm', ['razorpay_plan_id_monthly']);
        $this->assertNull($plan->fresh()->razorpayPlanFor('test', 'monthly'));

        $this->actingAs($superAdmin)
            ->patch(route('super-admin.plans.update', $plan), $this->planFormData($plan, ['razorpay_plan_id_monthly' => 'plan_good', 'razorpay_plan_id_yearly' => '']))
            ->assertSessionHasNoErrors();
        $this->assertSame('plan_good', $plan->fresh()->razorpayPlanFor('test', 'monthly')['id']);
    }

    public function test_unchanged_matching_link_is_kept_without_calling_razorpay(): void
    {
        Http::fake();
        $superAdmin = $this->createSuperAdmin();
        $plan = $this->createPlan(1000);
        app(RazorpayPlanSyncService::class)->linkManualPlans($plan, ['monthly' => 'plan_dashboard']);

        $this->actingAs($superAdmin)
            ->patch(route('super-admin.plans.update', $plan), $this->planFormData($plan, ['razorpay_plan_id_monthly' => 'plan_dashboard', 'razorpay_plan_id_yearly' => '']))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('warning');

        Http::assertNothingSent();
        $this->assertSame('plan_dashboard', $plan->fresh()->razorpayPlanFor('test', 'monthly')['id']);
    }

    public function test_price_change_unlinks_stale_plan_instead_of_blocking_save(): void
    {
        Http::fake();
        $superAdmin = $this->createSuperAdmin();
        $plan = $this->createPlan(1000);
        app(RazorpayPlanSyncService::class)->linkManualPlans($plan, ['monthly' => 'plan_dashboard']);

        // The form still carries the old linked id, but the price changes.
        $this->actingAs($superAdmin)
            ->patch(route('super-admin.plans.update', $plan), $this->planFormData($plan, [
                'monthly_price' => 1500,
                'razorpay_plan_id_monthly' => 'plan_dashboard',
                'razorpay_plan_id_yearly' => '',
            ]))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn (string $warning) => str_contains($warning, 'plan_dashboard was unlinked')
                && str_contains($warning, 'Rs. 1,770.00'));

        Http::assertNothingSent();
        $this->assertSame('1500.00', (string) $plan->fresh()->monthly_price);
        $this->assertNull($plan->fresh()->razorpayPlanFor('test', 'monthly'));
    }

    public function test_activate_toggle_keeps_linked_plan(): void
    {
        Http::fake();
        $superAdmin = $this->createSuperAdmin();
        $plan = $this->createPlan(1000);
        app(RazorpayPlanSyncService::class)->linkManualPlans($plan, ['monthly' => 'plan_dashboard']);

        // The quick Activate/Deactivate form does not send the Razorpay fields.
        $this->actingAs($superAdmin)
            ->patch(route('super-admin.plans.update', $plan), $this->planFormData($plan, ['is_active' => 0]))
            ->assertSessionHasNoErrors();

        $this->assertFalse($plan->fresh()->is_active);
        $this->assertSame('plan_dashboard', $plan->fresh()->razorpayPlanFor('test', 'monthly')['id']);
    }

    public function test_free_plan_cannot_be_recurring(): void
    {
        Http::fake();
        $plan = $this->createPlan(0);

        $this->expectException(\RuntimeException::class);

        app(RazorpayPlanSyncService::class)->planIdFor($plan, 'monthly');
    }

    private function planFormData(SubscriptionPlan $plan, array $overrides = []): array
    {
        return array_merge([
            'name' => $plan->name,
            'monthly_price' => (float) $plan->monthly_price,
            'yearly_price' => (float) $plan->yearly_price,
            'is_active' => 1,
            'is_popular' => 0,
            'has_free_trial' => 0,
            'disable_staff_tracking' => 0,
        ], $overrides);
    }

    private function createSuperAdmin(): User
    {
        return User::factory()->create([
            'company_id' => null,
            'role' => 'super_admin',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
    }

    private function remotePlan(string $id, string $period, int $amountPaise): array
    {
        return [
            'id' => $id,
            'entity' => 'plan',
            'interval' => 1,
            'period' => $period,
            'item' => ['name' => 'Growth', 'amount' => $amountPaise, 'currency' => 'INR'],
        ];
    }

    private function createPlan(float $monthlyPrice): SubscriptionPlan
    {
        return SubscriptionPlan::create([
            'name' => 'Growth',
            'slug' => 'growth-' . uniqid(),
            'price' => $monthlyPrice,
            'monthly_price' => $monthlyPrice,
            'yearly_price' => $monthlyPrice * 10,
            'billing_cycle' => 'monthly',
            'staff_limit' => 10,
            'is_active' => true,
        ]);
    }
}
