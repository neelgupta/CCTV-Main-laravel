<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Company-facing recurring (auto-renew) purchases through Razorpay Subscriptions.
 *
 * Flow: start() creates a local "pending" row and the Razorpay subscription, the company
 * authorises it in Razorpay checkout, then completeCheckout() verifies and activates it.
 * Renewals, failures and cancellations afterwards arrive through the webhook.
 */
class RecurringSubscriptionService
{
    public function __construct(
        private readonly RazorpayService $razorpay,
        private readonly RazorpayPlanSyncService $planSync,
        private readonly RazorpayWebhookService $webhooks,
    ) {
    }

    public function enabled(): bool
    {
        return $this->razorpay->enabled()
            && (bool) setting('subscription_recurring_enabled', config('subscription.recurring.enabled'));
    }

    public function isMandatory(): bool
    {
        return setting('subscription_recurring_mode', config('subscription.recurring.mode')) === 'mandatory';
    }

    public function shouldUse(bool $requested): bool
    {
        return $this->enabled() && ($requested || $this->isMandatory());
    }

    public function totalCount(string $billingCycle): int
    {
        $key = 'subscription_total_count_' . ($billingCycle === 'yearly' ? 'yearly' : 'monthly');

        return max(1, (int) setting($key, config('subscription.recurring.' . str_replace('subscription_', '', $key))));
    }

    /**
     * Creates the pending local row and the Razorpay subscription the company will authorise.
     */
    public function start(
        Company $company,
        SubscriptionPlan $plan,
        string $billingCycle,
        int $licenseCount,
        float $amount,
        string $reference,
        ?int $renewedFromSubscriptionId = null,
    ): CompanySubscription {
        $razorpayPlanId = $this->planSync->planIdFor($plan, $billingCycle);
        $licensePackage = CompanySubscription::generateLicensePackage($licenseCount);
        $totalCount = $this->totalCount($billingCycle);

        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'renewed_from_subscription_id' => $renewedFromSubscriptionId,
            'billing_cycle' => $billingCycle,
            'starts_at' => now()->toDateString(),
            'ends_at' => null,
            'status' => 'pending',
            'is_trial' => false,
            'auto_renew' => true,
            'is_recurring' => true,
            'gateway_mode' => $this->razorpay->mode(),
            'amount_paid' => 0,
            'payment_method' => 'Razorpay',
            'billing_reference' => $reference,
            'subscriber_email' => $company->email,
            'notes' => 'Auto-renew subscription started by company account.',
            'license_key' => $licensePackage['license_key'],
            'license_count' => $licensePackage['license_count'],
            'license_keys' => $licensePackage['license_keys'],
            'razorpay_plan_id' => $razorpayPlanId,
            'total_count' => $totalCount,
            'usage_baseline' => app(SubscriptionLimitService::class)->snapshotUsageBaseline((int) $company->id),
        ]);

        try {
            $remote = $this->razorpay->createSubscription($razorpayPlanId, $totalCount, $licenseCount, null, [
                'company_id' => (string) $company->id,
                'company_subscription_id' => (string) $subscription->id,
                'subscription_plan_id' => (string) $plan->id,
                'billing_cycle' => $billingCycle,
                'license_count' => (string) $licenseCount,
                'reference' => $reference,
                'amount' => number_format($amount, 2, '.', ''),
            ]);
        } catch (Throwable $exception) {
            $subscription->delete();

            throw $exception;
        }

        $subscription->forceFill([
            'razorpay_subscription_id' => (string) ($remote['id'] ?? ''),
            'razorpay_status' => $remote['status'] ?? 'created',
        ])->save();

        if (! filled($subscription->razorpay_subscription_id)) {
            $subscription->delete();

            throw new RuntimeException('Razorpay did not return a subscription id.');
        }

        return $subscription;
    }

    /**
     * Verifies the checkout response and activates the subscription.
     *
     * @throws RuntimeException when the signature does not match
     */
    public function completeCheckout(CompanySubscription $subscription, string $paymentId, string $signature): void
    {
        if (! $this->razorpay->verifySubscriptionSignature($paymentId, (string) $subscription->razorpay_subscription_id, $signature)) {
            throw new RuntimeException('Payment signature verification failed. Please try again.');
        }

        $entity = ['id' => $subscription->razorpay_subscription_id];
        $payment = ['id' => $paymentId];

        // Best effort: fill in the period dates and payment details now; the webhook confirms them later.
        try {
            $entity = $this->razorpay->fetchSubscription((string) $subscription->razorpay_subscription_id);
        } catch (Throwable $exception) {
            Log::warning('Could not fetch Razorpay subscription after checkout.', [
                'company_subscription_id' => $subscription->id,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $payment = $this->razorpay->fetchPayment($paymentId);
        } catch (Throwable $exception) {
            Log::warning('Could not fetch Razorpay payment after checkout.', [
                'company_subscription_id' => $subscription->id,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->webhooks->applyCheckout($subscription, $entity, $payment);
    }

    /**
     * Stops future charges. The company keeps access until the current paid period ends.
     */
    public function cancelAutoRenew(CompanySubscription $subscription): void
    {
        if (! $subscription->isRecurring() || ! $subscription->auto_renew) {
            throw new RuntimeException('Auto-renew is not active for this subscription.');
        }

        $remote = $this->razorpay->cancelSubscription((string) $subscription->razorpay_subscription_id, true);

        $subscription->forceFill([
            'auto_renew' => false,
            'cancel_at_cycle_end' => true,
            'cancelled_at' => now(),
            'razorpay_status' => $remote['status'] ?? $subscription->razorpay_status,
        ])->save();
    }

    /**
     * Removes checkouts that were started but never authorised (older than a day).
     *
     * @return int number of rows removed
     */
    public function prunePending(): int
    {
        $removed = 0;

        CompanySubscription::query()
            ->where('status', 'pending')
            ->where('is_recurring', true)
            ->where('paid_count', 0)
            ->where('created_at', '<', now()->subDay())
            ->whereDoesntHave('payments')
            ->orderBy('id')
            ->each(function (CompanySubscription $subscription) use (&$removed): void {
                if (filled($subscription->razorpay_subscription_id)) {
                    try {
                        $remote = $this->razorpay->fetchSubscription($subscription->razorpay_subscription_id);
                    } catch (Throwable $exception) {
                        Log::warning('Skipping pending subscription cleanup; Razorpay lookup failed.', [
                            'company_subscription_id' => $subscription->id,
                            'error' => $exception->getMessage(),
                        ]);

                        return;
                    }

                    // Authorised or charged on Razorpay's side: leave it for the webhook to activate.
                    if (! in_array($remote['status'] ?? '', ['created', 'expired', 'cancelled'], true)) {
                        return;
                    }

                    if (($remote['status'] ?? '') === 'created') {
                        try {
                            $this->razorpay->cancelSubscription($subscription->razorpay_subscription_id, false);
                        } catch (Throwable) {
                            // An unauthorised subscription simply expires on Razorpay's side.
                        }
                    }
                }

                $subscription->delete();
                $removed++;
            });

        return $removed;
    }
}
