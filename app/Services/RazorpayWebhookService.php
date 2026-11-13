<?php

namespace App\Services;

use App\Models\CompanySubscription;
use App\Models\RazorpayWebhookEvent;
use App\Models\SubscriptionPayment;
use App\Support\SubscriptionPricing;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies Razorpay Subscriptions state to the local company_subscriptions row, from webhook
 * events and from the checkout callback (applyCheckout), using the same rules for both.
 *
 * One local row maps to one Razorpay subscription. Each successful charge extends ends_at,
 * so license keys and staff seat assignments stay attached to the same row across renewals.
 */
class RazorpayWebhookService
{
    public const RESULT_PROCESSED = 'processed';
    public const RESULT_DUPLICATE = 'duplicate';
    public const RESULT_IGNORED = 'ignored';

    /**
     * Stores the event once and applies it. Throws when processing fails so Razorpay retries.
     */
    public function process(string $eventId, array $payload): string
    {
        $event = (string) ($payload['event'] ?? '');

        $record = RazorpayWebhookEvent::query()->firstOrCreate(
            ['event_id' => $eventId],
            [
                'event' => $event,
                'entity_id' => $this->subscriptionEntity($payload)['id']
                    ?? $this->paymentEntity($payload)['id']
                    ?? null,
                'payload' => $payload,
            ]
        );

        if ($record->processed_at !== null) {
            return self::RESULT_DUPLICATE;
        }

        $record->increment('attempts');

        try {
            $result = DB::transaction(fn (): string => $this->apply($event, $payload));

            $record->forceFill(['processed_at' => now(), 'error' => null])->save();

            return $result;
        } catch (Throwable $exception) {
            $record->forceFill(['error' => $exception->getMessage()])->save();

            throw $exception;
        }
    }

    private function apply(string $event, array $payload): string
    {
        if ($event === 'payment.failed') {
            return $this->handlePaymentFailed($payload);
        }

        if (! str_starts_with($event, 'subscription.')) {
            return self::RESULT_IGNORED;
        }

        $entity = $this->subscriptionEntity($payload);
        $subscription = $this->findSubscription($entity['id'] ?? null);

        if (! $subscription) {
            Log::warning('Razorpay webhook for unknown subscription.', [
                'event' => $event,
                'razorpay_subscription_id' => $entity['id'] ?? null,
            ]);

            return self::RESULT_IGNORED;
        }

        $wasActive = $this->isActiveStatus($subscription);
        $this->syncGatewayFields($subscription, $entity);

        match ($event) {
            'subscription.activated' => $this->markActive($subscription, $entity),
            'subscription.charged' => $this->handleCharged($subscription, $entity, $this->paymentEntity($payload)),
            'subscription.pending' => $this->handlePending($subscription),
            'subscription.halted' => $this->handleHalted($subscription),
            'subscription.cancelled' => $this->handleCancelled($subscription),
            'subscription.completed' => $this->handleCompleted($subscription),
            default => null,
        };

        $subscription->save();
        $this->afterActivation($subscription, $wasActive);

        return self::RESULT_PROCESSED;
    }

    /**
     * Activates a subscription right after a successful Razorpay checkout, without waiting for
     * the webhook. Safe to combine with the webhook: the payment is recorded once by its id.
     */
    public function applyCheckout(CompanySubscription $subscription, array $subscriptionEntity, array $payment): void
    {
        DB::transaction(function () use ($subscription, $subscriptionEntity, $payment): void {
            $locked = CompanySubscription::query()->lockForUpdate()->findOrFail($subscription->id);
            $wasActive = $this->isActiveStatus($locked);

            $this->syncGatewayFields($locked, $subscriptionEntity);
            $this->handleCharged($locked, $subscriptionEntity, $payment);
            $locked->save();

            $this->afterActivation($locked, $wasActive);
            $subscription->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function isActiveStatus(CompanySubscription $subscription): bool
    {
        return in_array($subscription->status, ['active', 'trialing'], true);
    }

    /**
     * One-time side effects when a subscription first becomes active (same as a one-time purchase).
     */
    private function afterActivation(CompanySubscription $subscription, bool $wasActive): void
    {
        if ($wasActive || $subscription->status !== 'active') {
            return;
        }

        app(CompanyAdminSeatService::class)->assignSeatIfNeeded((int) $subscription->company_id, $subscription);

        if ($subscription->renewed_from_subscription_id) {
            CompanySubscription::query()
                ->whereKey($subscription->renewed_from_subscription_id)
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'expired', 'updated_at' => now()]);
        }
    }

    private function syncGatewayFields(CompanySubscription $subscription, array $entity): void
    {
        $subscription->forceFill(array_filter([
            'razorpay_status' => $entity['status'] ?? null,
            'razorpay_plan_id' => $entity['plan_id'] ?? null,
            'razorpay_customer_id' => $entity['customer_id'] ?? null,
            'current_start' => $this->timestamp($entity['current_start'] ?? null),
            'current_end' => $this->timestamp($entity['current_end'] ?? null),
            'next_charge_at' => $this->timestamp($entity['charge_at'] ?? null),
            'paid_count' => isset($entity['paid_count']) ? (int) $entity['paid_count'] : null,
            'total_count' => isset($entity['total_count']) ? (int) $entity['total_count'] : null,
        ], fn ($value) => $value !== null));
    }

    private function markActive(CompanySubscription $subscription, array $entity): void
    {
        if (in_array($subscription->status, ['pending', 'expired'], true) || $subscription->is_trial) {
            $subscription->status = 'active';
            $subscription->is_trial = false;
        }

        $this->extendTo($subscription, $this->timestamp($entity['current_end'] ?? null));

        // Razorpay did not report the period end yet: never leave an active row without an end date.
        if ($subscription->ends_at === null || $subscription->ends_at->toDateString() < now()->toDateString()) {
            $subscription->ends_at = ($subscription->billing_cycle === 'yearly' ? now()->addYear() : now()->addMonth())
                ->subDay()
                ->toDateString();
        }
    }

    private function handleCharged(CompanySubscription $subscription, array $entity, array $payment): void
    {
        $this->markActive($subscription, $entity);

        $subscription->forceFill([
            'payment_failed_at' => null,
            'grace_ends_at' => null,
        ]);

        if (! filled($payment['id'] ?? null)) {
            return;
        }

        $pricing = SubscriptionPricing::fromTotal(((int) ($payment['amount'] ?? 0)) / 100);
        $amount = $pricing['total'];
        $gstRate = $pricing['gst_rate'];
        $gstAmount = $pricing['gst_amount'];

        $record = SubscriptionPayment::query()->firstOrNew(['razorpay_payment_id' => $payment['id']]);
        $isNew = ! $record->exists || $record->status !== SubscriptionPayment::STATUS_CAPTURED;

        $record->fill([
            'company_id' => $subscription->company_id,
            'company_subscription_id' => $subscription->id,
            'razorpay_invoice_id' => $payment['invoice_id'] ?? null,
            'razorpay_order_id' => $payment['order_id'] ?? null,
            'amount' => $amount,
            'gst_rate' => $gstRate,
            'gst_amount' => $gstAmount,
            'currency' => $payment['currency'] ?? 'INR',
            'status' => SubscriptionPayment::STATUS_CAPTURED,
            'method' => $payment['method'] ?? null,
            'period_start' => $this->timestamp($entity['current_start'] ?? null)?->toDateString(),
            'period_end' => $this->timestamp($entity['current_end'] ?? null)?->toDateString(),
            'paid_at' => $this->timestamp($payment['created_at'] ?? null) ?? now(),
            'failure_reason' => null,
            'payload' => $payment,
        ])->save();

        $subscription->razorpay_payment_id = $payment['id'];
        $subscription->payment_method = 'Razorpay';

        if ($isNew) {
            $subscription->amount_paid = (float) $subscription->payments()
                ->where('status', SubscriptionPayment::STATUS_CAPTURED)
                ->sum('amount');
        }
    }

    /**
     * A renewal charge failed and Razorpay is retrying. Keep access until the grace period ends.
     */
    private function handlePending(CompanySubscription $subscription): void
    {
        if ($subscription->payment_failed_at === null) {
            $subscription->payment_failed_at = now();
        }

        // Grace starts once, from the paid-through date; repeated pending events don't extend it.
        if ($subscription->grace_ends_at === null) {
            $paidThrough = $subscription->ends_at ?? now();
            $subscription->grace_ends_at = Carbon::parse($paidThrough)
                ->startOfDay()
                ->addDays($this->graceDays())
                ->toDateString();
        }

        $this->extendTo($subscription, Carbon::parse($subscription->grace_ends_at));
    }

    /**
     * Razorpay stopped retrying. Access continues only until the grace period ends.
     */
    private function handleHalted(CompanySubscription $subscription): void
    {
        $subscription->auto_renew = false;
        $this->handlePending($subscription);

        if (! $subscription->inGracePeriod()) {
            $subscription->status = 'expired';
        }
    }

    /**
     * Auto-renew stopped. The company keeps access until the paid period (ends_at) runs out.
     */
    private function handleCancelled(CompanySubscription $subscription): void
    {
        $subscription->auto_renew = false;
        $subscription->cancelled_at ??= now();
    }

    /**
     * All billing cycles were charged. Access runs until the last paid period ends.
     */
    private function handleCompleted(CompanySubscription $subscription): void
    {
        $subscription->auto_renew = false;
    }

    private function handlePaymentFailed(array $payload): string
    {
        $payment = $this->paymentEntity($payload);
        $subscription = $this->findSubscription(
            $payment['subscription_id'] ?? ($payment['notes']['razorpay_subscription_id'] ?? null)
        );

        if (! $subscription && is_numeric($payment['notes']['company_subscription_id'] ?? null)) {
            $subscription = CompanySubscription::query()
                ->whereKey((int) $payment['notes']['company_subscription_id'])
                ->where('is_recurring', true)
                ->lockForUpdate()
                ->first();
        }

        if (! $subscription || ! filled($payment['id'] ?? null)) {
            return self::RESULT_IGNORED;
        }

        $record = SubscriptionPayment::query()->firstOrNew(['razorpay_payment_id' => $payment['id']]);

        if ($record->exists && $record->status === SubscriptionPayment::STATUS_CAPTURED) {
            return self::RESULT_IGNORED;
        }

        $record->fill([
            'company_id' => $subscription->company_id,
            'company_subscription_id' => $subscription->id,
            'razorpay_invoice_id' => $payment['invoice_id'] ?? null,
            'razorpay_order_id' => $payment['order_id'] ?? null,
            'amount' => round(((int) ($payment['amount'] ?? 0)) / 100, 2),
            'currency' => $payment['currency'] ?? 'INR',
            'status' => SubscriptionPayment::STATUS_FAILED,
            'method' => $payment['method'] ?? null,
            'failure_reason' => $payment['error_description'] ?? null,
            'payload' => $payment,
        ])->save();

        return self::RESULT_PROCESSED;
    }

    /**
     * Moves ends_at forward only, so an out-of-order event never shortens access.
     */
    private function extendTo(CompanySubscription $subscription, ?Carbon $until): void
    {
        if ($until === null) {
            return;
        }

        if ($subscription->ends_at === null || $until->toDateString() > $subscription->ends_at->toDateString()) {
            $subscription->ends_at = $until->toDateString();
        }
    }

    private function findSubscription(?string $razorpaySubscriptionId): ?CompanySubscription
    {
        if (! filled($razorpaySubscriptionId)) {
            return null;
        }

        return CompanySubscription::query()
            ->where('razorpay_subscription_id', $razorpaySubscriptionId)
            ->lockForUpdate()
            ->first();
    }

    private function subscriptionEntity(array $payload): array
    {
        return (array) ($payload['payload']['subscription']['entity'] ?? []);
    }

    private function paymentEntity(array $payload): array
    {
        return (array) ($payload['payload']['payment']['entity'] ?? []);
    }

    private function timestamp(mixed $value): ?Carbon
    {
        return is_numeric($value) && (int) $value > 0
            ? Carbon::createFromTimestamp((int) $value)
            : null;
    }

    private function graceDays(): int
    {
        return max(0, (int) setting('subscription_grace_days', config('subscription.recurring.grace_days', 3)));
    }
}
