<?php

namespace App\Services;

use App\Exceptions\RazorpayAuthenticationException;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\DB;
use App\Support\SubscriptionPricing;
use RuntimeException;
use Throwable;

/**
 * Keeps a Razorpay plan per local plan, gateway mode and billing cycle.
 *
 * Plan prices exclude GST, so the Razorpay plan amount is the per-license price plus GST.
 * A Super Admin can link a plan created in the Razorpay dashboard (source "manual"). When no
 * plan is linked, one is created automatically (source "auto"). Razorpay plans cannot be edited,
 * so when the price or GST rate changes an auto plan is re-created, while a manual plan must be
 * re-linked; existing subscriptions keep billing on their original plan.
 */
class RazorpayPlanSyncService
{
    public const CYCLES = ['monthly', 'yearly'];
    public const SOURCE_AUTO = 'auto';
    public const SOURCE_MANUAL = 'manual';

    public function __construct(private readonly RazorpayService $razorpay)
    {
    }

    /**
     * Per-license amount charged by Razorpay, in paise: price plus GST.
     */
    public function amountPaiseFor(SubscriptionPlan $plan, string $billingCycle): int
    {
        return $this->amountPaiseForPrice($plan->priceForCycle($billingCycle));
    }

    public function amountPaiseForPrice(float $price): int
    {
        return (int) round(SubscriptionPricing::forPlan($price)['unit_total'] * 100);
    }

    /**
     * Returns the Razorpay plan id for the current mode, creating it when missing or outdated.
     */
    public function planIdFor(SubscriptionPlan $plan, string $billingCycle): string
    {
        if (! in_array($billingCycle, self::CYCLES, true)) {
            throw new RuntimeException('Unsupported billing cycle for recurring plans: ' . $billingCycle);
        }

        $amountPaise = $this->amountPaiseFor($plan, $billingCycle);

        if ($amountPaise <= 0) {
            throw new RuntimeException('Recurring billing is not available for free plans.');
        }

        $mode = $this->razorpay->mode();

        return DB::transaction(function () use ($plan, $billingCycle, $amountPaise, $mode): string {
            $plan = SubscriptionPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $stored = $plan->razorpayPlanFor($mode, $billingCycle);

            if ($stored && (int) ($stored['amount'] ?? 0) === $amountPaise) {
                return (string) $stored['id'];
            }

            if ($stored && ($stored['source'] ?? null) === self::SOURCE_MANUAL) {
                throw new RuntimeException(sprintf(
                    'The Razorpay plan linked to %s (%s) charges Rs. %s per license, but the plan now costs Rs. %s including GST. Link an updated Razorpay plan in Subscription Management.',
                    $plan->name,
                    $billingCycle,
                    number_format(((int) ($stored['amount'] ?? 0)) / 100, 2),
                    number_format($amountPaise / 100, 2)
                ));
            }

            $created = $this->razorpay->createPlan(
                $billingCycle,
                $amountPaise,
                sprintf('%s (%s)', $plan->name, ucfirst($billingCycle)),
                [
                    'subscription_plan_id' => (string) $plan->id,
                    'billing_cycle' => $billingCycle,
                ]
            );

            $planId = (string) ($created['id'] ?? '');

            if ($planId === '') {
                throw new RuntimeException('Razorpay did not return a plan id.');
            }

            $this->storePlanEntry($plan, $mode, $billingCycle, $planId, $amountPaise, self::SOURCE_AUTO);

            return $planId;
        });
    }

    /**
     * Checks a Razorpay plan created in the dashboard against the local price.
     * Returns an error message, or null when the plan can be linked.
     */
    public function validateManualPlan(string $razorpayPlanId, string $billingCycle, float $price): ?string
    {
        $expectedPaise = $this->amountPaiseForPrice($price);

        if ($expectedPaise <= 0) {
            return 'A Razorpay plan cannot be linked to a free price.';
        }

        if (! $this->razorpay->enabled()) {
            return sprintf('Add the Razorpay %s keys in Global Settings before linking a plan.', $this->razorpay->mode());
        }

        try {
            $remote = $this->razorpay->fetchPlan($razorpayPlanId);
        } catch (RazorpayAuthenticationException $exception) {
            return $exception->getMessage();
        } catch (Throwable $exception) {
            return sprintf('Razorpay plan %s could not be found in %s mode: %s', $razorpayPlanId, $this->razorpay->mode(), $exception->getMessage());
        }

        if (($remote['period'] ?? null) !== $billingCycle || (int) ($remote['interval'] ?? 0) !== 1) {
            return sprintf(
                'Razorpay plan %s bills every %s; the %s Plan ID needs a plan billed every 1 %s.',
                $razorpayPlanId,
                $this->describePeriod((int) ($remote['interval'] ?? 0), (string) ($remote['period'] ?? '')),
                ucfirst($billingCycle),
                $billingCycle === 'yearly' ? 'year' : 'month'
            );
        }

        if (strtoupper((string) ($remote['item']['currency'] ?? '')) !== strtoupper($this->razorpay->currency())) {
            return sprintf('Razorpay plan %s is not in %s.', $razorpayPlanId, $this->razorpay->currency());
        }

        $remoteAmount = (int) ($remote['item']['amount'] ?? 0);

        if ($remoteAmount !== $expectedPaise) {
            return sprintf(
                'Razorpay plan %s charges Rs. %s per license, but this price plus GST is Rs. %s.',
                $razorpayPlanId,
                number_format($remoteAmount / 100, 2),
                number_format($expectedPaise / 100, 2)
            );
        }

        return null;
    }

    /**
     * Saves manually linked plan ids for the current mode. A blank id removes a manual link,
     * so the plan falls back to automatic creation. Cycles missing from the array are untouched.
     *
     * @param  array<string, string|null>  $planIdsByCycle
     */
    public function linkManualPlans(SubscriptionPlan $plan, array $planIdsByCycle): void
    {
        $mode = $this->razorpay->mode();

        foreach ($planIdsByCycle as $billingCycle => $razorpayPlanId) {
            if (! in_array($billingCycle, self::CYCLES, true)) {
                continue;
            }

            $razorpayPlanId = trim((string) $razorpayPlanId);
            $stored = $plan->razorpayPlanFor($mode, $billingCycle);

            if ($razorpayPlanId !== '') {
                $this->storePlanEntry($plan, $mode, $billingCycle, $razorpayPlanId, $this->amountPaiseFor($plan, $billingCycle), self::SOURCE_MANUAL);

                continue;
            }

            if ($stored && ($stored['source'] ?? null) === self::SOURCE_MANUAL) {
                $planIds = $plan->razorpay_plan_ids ?? [];
                unset($planIds[$mode][$billingCycle]);
                $plan->forceFill(['razorpay_plan_ids' => $planIds])->save();
            }
        }
    }

    private function describePeriod(int $interval, string $period): string
    {
        $unit = match ($period) {
            'daily' => 'day',
            'weekly' => 'week',
            'monthly' => 'month',
            'quarterly' => 'quarter',
            'yearly' => 'year',
            default => $period ?: 'period',
        };

        return $interval . ' ' . ($interval === 1 ? $unit : $unit . 's');
    }

    private function storePlanEntry(SubscriptionPlan $plan, string $mode, string $billingCycle, string $razorpayPlanId, int $amountPaise, string $source): void
    {
        $planIds = $plan->razorpay_plan_ids ?? [];
        $planIds[$mode][$billingCycle] = [
            'id' => $razorpayPlanId,
            'amount' => $amountPaise,
            'source' => $source,
            'linked_at' => now()->toIso8601String(),
        ];

        $plan->forceFill(['razorpay_plan_ids' => $planIds])->save();
    }
}
