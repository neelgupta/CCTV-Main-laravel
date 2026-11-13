<?php

namespace Tests\Unit;

use App\Support\SubscriptionPricing;
use PHPUnit\Framework\TestCase;

class SubscriptionPricingTest extends TestCase
{
    public function test_gst_is_added_on_top_of_plan_price(): void
    {
        $this->assertSame(
            ['total' => 234.82, 'subtotal' => 199.0, 'gst_rate' => 18.0, 'gst_amount' => 35.82, 'unit_total' => 234.82],
            SubscriptionPricing::forPlan(199, 1, 18)
        );
    }

    public function test_multiple_licenses_match_per_license_amount_times_quantity(): void
    {
        foreach ([[199, 3], [399, 2], [2388, 5], [4788, 1], [123.45, 7]] as [$price, $quantity]) {
            $pricing = SubscriptionPricing::forPlan($price, $quantity, 18);

            // Razorpay charges unit_total x quantity; the one-time total must be identical.
            $this->assertSame(round($pricing['unit_total'] * $quantity, 2), $pricing['total']);
            $this->assertSame(round($pricing['subtotal'] + $pricing['gst_amount'], 2), $pricing['total']);
        }
    }

    public function test_trial_and_zero_rate(): void
    {
        $this->assertSame(0.0, SubscriptionPricing::forPlan(199, 0, 18)['total']);
        $this->assertSame(199.0, SubscriptionPricing::forPlan(199, 1, 0)['total']);
    }

    public function test_from_total_splits_a_gst_inclusive_payment(): void
    {
        $this->assertSame(
            ['total' => 234.82, 'subtotal' => 199.0, 'gst_rate' => 18.0, 'gst_amount' => 35.82],
            SubscriptionPricing::fromTotal(234.82, 18)
        );
    }
}
