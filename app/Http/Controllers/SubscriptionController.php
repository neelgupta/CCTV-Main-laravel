<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\SubscriptionPlan;
use App\Services\CompanyAdminSeatService;
use App\Services\RazorpayService;
use App\Services\RecurringSubscriptionService;
use App\Services\SubscriptionLimitService;
use App\Support\SubscriptionInvoice;
use App\Support\SubscriptionPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SubscriptionController extends Controller
{
    private const BILLING_CYCLES = ['monthly', 'yearly'];

    private const PENDING_ORDERS_SESSION_KEY = 'subscription.pending_razorpay_orders';

    private const PAYMENT_UNAVAILABLE_MESSAGE = 'Online payment is not available right now. Please try again later or contact support.';

    public function show()
    {
        $company = Auth::user()->company;

        abort_unless($company, 404);

        $subscriptionLimitService = app(SubscriptionLimitService::class);
        $activeSubscriptions = $subscriptionLimitService->activeSubscriptions((int) $company->id);
        $expiredSubscriptions = $subscriptionLimitService->expiredSubscriptions((int) $company->id);
        $subscriptionHistory = CompanySubscription::query()
            ->with(['plan', 'renewedFrom.plan', 'renewals'])
            ->where('company_id', $company->id)
            ->where('status', '!=', 'pending')
            ->latest()
            ->get();
        $recurring = app(RecurringSubscriptionService::class);
        $activeSubscription = $activeSubscriptions->first();
        $staffUsage = $subscriptionLimitService->currentUsageSummary((int) $company->id);
        $plans = SubscriptionPlan::query()->where('is_active', true)->orderBy('id')->get();
        $subscriptionUsageById = $activeSubscriptions->mapWithKeys(function (CompanySubscription $subscription) use ($subscriptionLimitService, $company): array {
            return [
                $subscription->id => $subscriptionLimitService->subscriptionUsageSummary((int) $company->id, $subscription->id),
            ];
        });

        return view('subscription.show', [
            'company' => $company->load(['subscriptions.plan']),
            'activeSubscription' => $activeSubscription,
            'activeSubscriptions' => $activeSubscriptions,
            'expiredSubscriptions' => $expiredSubscriptions,
            'subscriptionHistory' => $subscriptionHistory,
            'subscriptionUsageById' => $subscriptionUsageById,
            'plans' => $plans,
            'trialEligibilityByPlan' => $plans->mapWithKeys(fn (SubscriptionPlan $plan) => [
                $plan->id => $subscriptionLimitService->trialEligibilityForPlan($company, $plan, 'monthly'),
            ])->all(),
            'subscriptionUsage' => [
                'staff_limit' => $staffUsage['staff_limit'] ?? null,
                'staff_limit_label' => $staffUsage['staff_limit_label'] ?? 'Unlimited',
                'used_members' => $staffUsage['staff'] ?? 0,
                'remaining_members' => $staffUsage['remaining_members'] ?? null,
            ],
            'deactivatedPlanMessage' => $subscriptionLimitService->hasDeactivatedCurrentPlan($company->id)
                ? $subscriptionLimitService->deactivatedPlanMessage($company->id)
                : null,
            'writeRestricted' => $subscriptionLimitService->shouldRestrictWritesForCompany($company),
            'expiredAccessMessage' => $subscriptionLimitService->expiredAccessMessage($company),
            'expiredPlanMessage' => $subscriptionLimitService->expiredPlanMessage((int) $company->id),
            'recurringEnabled' => $recurring->enabled(),
            'recurringMandatory' => $recurring->isMandatory(),
        ]);
    }

    public function changePlan(Request $request, RazorpayService $razorpayService)
    {
        $company = Auth::user()->company;

        abort_unless($company, 404);

        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'billing_reference' => ['nullable', 'string', 'max:191'],
            'license_count' => ['nullable', 'integer', 'min:1'],
            'is_trial' => ['nullable', 'boolean'],
            'renew_subscription_id' => ['nullable', 'integer'],
            'auto_renew' => ['nullable', 'boolean'],
        ]);

        $plan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->findOrFail($validated['subscription_plan_id']);

        $billingCycle = $this->normalizeBillingCycle($validated['billing_cycle'], $plan);
        $reference = $validated['billing_reference'] ?: ('SUB-' . strtoupper(Str::random(8)));
        $licenseCount = $this->resolveLicenseCount($validated['license_count'] ?? null, $plan);
        $isTrial = $request->boolean('is_trial');
        $pricing = SubscriptionPricing::forPlan($plan->priceForCycle($billingCycle), $isTrial ? 0 : $licenseCount);
        $subtotal = $pricing['subtotal'];
        $gstRate = $pricing['gst_rate'];
        $gstAmount = $pricing['gst_amount'];
        $amount = $pricing['total'];
        $renewalSourceId = $this->resolveRenewalSourceId($company->id, $plan->id, $request->integer('renew_subscription_id') ?: null);

        if ($isTrial) {
            $this->assertTrialEligibility($company, $plan, $billingCycle);

            $this->activatePlan($company->id, $company->email, $plan, 'monthly', [
                'payment_method' => 'Free Trial',
                'billing_reference' => $reference,
                'status' => 'trialing',
                'amount_paid' => 0,
                'license_count' => $licenseCount,
                'is_trial' => true,
                'renewed_from_subscription_id' => $renewalSourceId,
            ]);

            return redirect()->route('subscription.show')->with('success', 'Your ' . config('subscription.trial_days', 15) . '-day free trial has been activated successfully.');
        }

        if ($amount <= 0) {
            $this->activatePlan($company->id, $company->email, $plan, $billingCycle, [
                'payment_method' => 'Free Plan',
                'billing_reference' => $reference,
                'status' => 'active',
                'amount_paid' => 0,
                'license_count' => $licenseCount,
                'renewed_from_subscription_id' => $renewalSourceId,
            ]);

            return redirect()->route('subscription.show')->with('success', 'Subscription plan updated successfully.');
        }

        $recurring = app(RecurringSubscriptionService::class);

        if ($recurring->shouldUse($request->boolean('auto_renew'))) {
            try {
                $subscription = $recurring->start($company, $plan, $billingCycle, $licenseCount, $amount, $reference, $renewalSourceId);
            } catch (\Throwable $exception) {
                Log::error('Unable to start Razorpay auto-renew subscription.', [
                    'company_id' => $company->id,
                    'subscription_plan_id' => $plan->id,
                    'billing_cycle' => $billingCycle,
                    'error' => $exception->getMessage(),
                ]);

                return redirect()->route('subscription.show')
                    ->with('error', 'Auto-renew could not be set up right now: ' . $exception->getMessage());
            }

            return view('subscription.payment', [
                'company' => $company,
                'plan' => $plan,
                'billingCycle' => $billingCycle,
                'subtotal' => $subtotal,
                'gstRate' => $gstRate,
                'gstAmount' => $gstAmount,
                'price' => $amount,
                'reference' => $reference,
                'licenseCount' => $licenseCount,
                'renewSubscriptionId' => $renewalSourceId,
                'order' => [
                    'id' => $subscription->razorpay_subscription_id,
                    'amount' => (int) round($amount * 100),
                    'currency' => $razorpayService->currency(),
                    'demo' => false,
                ],
                'recurringSubscription' => $subscription,
                'successRoute' => route('subscription.recurring.complete'),
                'cancelRoute' => route('subscription.show'),
                'mode' => 'subscription-recurring',
            ]);
        }

        try {
            $order = $this->buildOrderPayload($razorpayService, $amount, $reference, $company->name, $plan->name, $billingCycle);
        } catch (RuntimeException $exception) {
            $this->logOrderFailure($exception, $company->id, $plan->id, $reference);

            return redirect()->route('subscription.show')->with('error', self::PAYMENT_UNAVAILABLE_MESSAGE);
        }

        $this->rememberPendingOrder($request, $order['id'], $this->orderTerms($company->id, $plan->id, $billingCycle, $licenseCount, $amount));

        return view('subscription.payment', [
            'company' => $company,
            'plan' => $plan,
            'billingCycle' => $billingCycle,
            'subtotal' => $subtotal,
            'gstRate' => $gstRate,
            'gstAmount' => $gstAmount,
            'price' => $amount,
            'reference' => $reference,
            'licenseCount' => $licenseCount,
            'renewSubscriptionId' => $renewalSourceId,
            'order' => $order,
            'successRoute' => route('subscription.process'),
            'cancelRoute' => route('subscription.show'),
            'mode' => 'subscription-change',
        ]);
    }

    public function processChange(Request $request, RazorpayService $razorpayService)
    {
        $company = Auth::user()->company;

        abort_unless($company, 404);

        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'billing_reference' => ['nullable', 'string', 'max:191'],
            'razorpay_order_id' => ['nullable', 'string', 'max:191'],
            'razorpay_payment_id' => ['nullable', 'string', 'max:191'],
            'razorpay_signature' => ['nullable', 'string', 'max:255'],
            'license_count' => ['nullable', 'integer', 'min:1'],
            'is_trial' => ['nullable', 'boolean'],
            'renew_subscription_id' => ['nullable', 'integer'],
        ]);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($validated['subscription_plan_id']);
        $billingCycle = $this->normalizeBillingCycle($validated['billing_cycle'], $plan);
        $licenseCount = $this->resolveLicenseCount($validated['license_count'] ?? null, $plan);
        $isTrial = $request->boolean('is_trial');
        $pricing = SubscriptionPricing::forPlan($plan->priceForCycle($billingCycle), $isTrial ? 0 : $licenseCount);
        $subtotal = $pricing['subtotal'];
        $gstRate = $pricing['gst_rate'];
        $gstAmount = $pricing['gst_amount'];
        $amount = $pricing['total'];

        if ($isTrial) {
            $this->assertTrialEligibility($company, $plan, $billingCycle);

            $this->activatePlan($company->id, $company->email, $plan, 'monthly', [
                'payment_method' => 'Free Trial',
                'billing_reference' => $validated['billing_reference'] ?: ('SUB-' . strtoupper(Str::random(8))),
                'status' => 'trialing',
                'amount_paid' => 0,
                'license_count' => $licenseCount,
                'is_trial' => true,
                'renewed_from_subscription_id' => $this->resolveRenewalSourceId($company->id, $plan->id, $validated['renew_subscription_id'] ?? null),
            ]);

            return redirect()->route('subscription.show')->with('success', 'Your ' . config('subscription.trial_days', 15) . '-day free trial has been activated successfully.');
        }

        $renewalSourceId = $this->resolveRenewalSourceId($company->id, $plan->id, $validated['renew_subscription_id'] ?? null);
        $successMessage = $renewalSourceId ? 'Subscription renewed successfully.' : 'Subscription plan updated successfully.';

        if ($amount <= 0) {
            $this->activatePlan($company->id, $company->email, $plan, $billingCycle, [
                'payment_method' => 'Free Plan',
                'billing_reference' => $validated['billing_reference'] ?: ('SUB-' . strtoupper(Str::random(8))),
                'status' => 'active',
                'amount_paid' => 0,
                'license_count' => $licenseCount,
                'renewed_from_subscription_id' => $renewalSourceId,
            ]);

            return redirect()->route('subscription.show')->with('success', $successMessage);
        }

        // A paid plan is only activated by a genuine Razorpay payment for an order this session
        // created for exactly this plan, cycle, seat count and amount, and each order activates once.
        $orderId = (string) ($validated['razorpay_order_id'] ?? '');
        $paymentId = (string) ($validated['razorpay_payment_id'] ?? '');
        $pendingOrders = (array) $request->session()->get(self::PENDING_ORDERS_SESSION_KEY, []);
        $expectedTerms = $this->orderTerms($company->id, $plan->id, $billingCycle, $licenseCount, $amount);

        if (($pendingOrders[$orderId] ?? null) !== $expectedTerms
            || ! $razorpayService->verifySignature($orderId, $paymentId, (string) ($validated['razorpay_signature'] ?? ''))) {
            Log::warning('Razorpay payment rejected: order not issued for this checkout or signature invalid.', [
                'company_id' => $company->id,
                'subscription_plan_id' => $plan->id,
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
            ]);

            return back()->with('error', 'Payment verification failed. Please try again.');
        }

        $lock = Cache::lock('razorpay-order:' . sha1($orderId), 30);

        if (! $lock->get()) {
            return redirect()->route('subscription.show')->with('error', 'This payment is already being processed.');
        }

        try {
            if (CompanySubscription::razorpayPaymentAlreadyUsed($orderId, $paymentId)) {
                return redirect()->route('subscription.show')->with('error', 'This payment has already been used to activate a subscription.');
            }

            $this->activatePlan($company->id, $company->email, $plan, $billingCycle, [
                'payment_method' => 'Razorpay',
                'billing_reference' => $validated['billing_reference'] ?: ('SUB-' . strtoupper(Str::random(8))),
                'status' => 'active',
                'amount_paid' => $amount,
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'license_count' => $licenseCount,
                'renewed_from_subscription_id' => $renewalSourceId,
            ]);
        } finally {
            $lock->release();
        }

        unset($pendingOrders[$orderId]);
        $request->session()->put(self::PENDING_ORDERS_SESSION_KEY, $pendingOrders);

        return redirect()->route('subscription.show')->with('success', $successMessage);
    }

    public function createOrder(Request $request, RazorpayService $razorpayService)
    {
        $company = Auth::user()->company;
        abort_unless($company, 404);

        $validated = $request->validate([
            'subscription_plan_id' => ['required', 'exists:subscription_plans,id'],
            'billing_cycle' => ['required', 'in:monthly,yearly'],
            'license_count' => ['nullable', 'integer', 'min:1'],
            'is_trial' => ['nullable', 'boolean'],
            'renew_subscription_id' => ['nullable', 'integer'],
        ]);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($validated['subscription_plan_id']);
        $reference = 'SUB-' . strtoupper(Str::random(8));
        $billingCycle = $this->normalizeBillingCycle($validated['billing_cycle'], $plan);
        $licenseCount = $this->resolveLicenseCount($validated['license_count'] ?? null, $plan);
        $isTrial = $request->boolean('is_trial');
        $pricing = SubscriptionPricing::forPlan($plan->priceForCycle($billingCycle), $isTrial ? 0 : $licenseCount);
        $subtotal = $pricing['subtotal'];
        $gstRate = $pricing['gst_rate'];
        $gstAmount = $pricing['gst_amount'];
        $amount = $pricing['total'];

        if ($request->boolean('is_trial')) {
            $this->assertTrialEligibility($company, $plan, $billingCycle);

            return response()->json([
                'free' => true,
                'trial' => true,
                'plan_id' => $plan->id,
                'reference' => $reference,
                'license_count' => $licenseCount,
                'billing_cycle' => 'monthly',
                'renew_subscription_id' => $this->resolveRenewalSourceId($company->id, $plan->id, $validated['renew_subscription_id'] ?? null),
            ]);
        }

        if ($amount <= 0) {
            return response()->json([
                'free' => true,
                'plan_id' => $plan->id,
                'reference' => $reference,
                'license_count' => $licenseCount,
                'billing_cycle' => $billingCycle,
                'renew_subscription_id' => $this->resolveRenewalSourceId($company->id, $plan->id, $validated['renew_subscription_id'] ?? null),
            ]);
        }

        try {
            $order = $this->buildOrderPayload($razorpayService, $amount, $reference, $company->name, $plan->name, $billingCycle);
        } catch (RuntimeException $exception) {
            $this->logOrderFailure($exception, $company->id, $plan->id, $reference);

            return response()->json(['message' => self::PAYMENT_UNAVAILABLE_MESSAGE], 503);
        }

        $this->rememberPendingOrder($request, $order['id'], $this->orderTerms($company->id, $plan->id, $billingCycle, $licenseCount, $amount));

        return response()->json([
            'order_id'  => $order['id'],
            'amount'    => $order['amount'],
            'subtotal'  => $subtotal,
            'gst_rate'  => $gstRate,
            'gst_amount'=> $gstAmount,
            'currency'  => $order['currency'] ?? 'INR',
            'reference' => $reference,
            'plan_id'   => $plan->id,
            'billing_cycle' => $billingCycle,
            'demo'      => $order['demo'] ?? false,
            'key_id'    => config('services.razorpay.key_id'),
            'company'   => $company->name,
            'plan_name' => $plan->name,
            'email'     => Auth::user()->email ?? '',
            'contact'   => $company->mobile_number ?? $company->phone_number ?? '',
            'license_count' => $licenseCount,
            'renew_subscription_id' => $this->resolveRenewalSourceId($company->id, $plan->id, $validated['renew_subscription_id'] ?? null),
        ]);
    }

    public function completeRecurring(Request $request, RecurringSubscriptionService $recurring)
    {
        $company = Auth::user()->company;
        abort_unless($company, 404);

        $validated = $request->validate([
            'razorpay_subscription_id' => ['required', 'string', 'max:191'],
            'razorpay_payment_id' => ['required', 'string', 'max:191'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $subscription = CompanySubscription::query()
            ->where('company_id', $company->id)
            ->where('is_recurring', true)
            ->where('razorpay_subscription_id', $validated['razorpay_subscription_id'])
            ->firstOrFail();

        try {
            $recurring->completeCheckout($subscription, $validated['razorpay_payment_id'], $validated['razorpay_signature']);
        } catch (\RuntimeException $exception) {
            return redirect()->route('subscription.show')->with('error', $exception->getMessage());
        }

        $subscription->refresh();

        return redirect()->route('subscription.show')->with('success', sprintf(
            'Subscription activated. It renews automatically every %s; the next payment is due on %s.',
            $subscription->billing_cycle === 'yearly' ? 'year' : 'month',
            ($subscription->next_charge_at ?? $subscription->ends_at)?->format('d M Y') ?? 'the next billing date'
        ));
    }

    public function cancelAutoRenew(CompanySubscription $subscription, RecurringSubscriptionService $recurring)
    {
        $company = Auth::user()->company;
        abort_unless($company && (int) $subscription->company_id === (int) $company->id, 404);

        try {
            $recurring->cancelAutoRenew($subscription);
        } catch (\Throwable $exception) {
            Log::warning('Unable to cancel Razorpay auto-renew.', [
                'company_subscription_id' => $subscription->id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('subscription.show')->with('error', 'Auto-renew could not be cancelled: ' . $exception->getMessage());
        }

        return redirect()->route('subscription.show')->with('success', sprintf(
            'Auto-renew cancelled. %s stays active until %s and will not be charged again.',
            $subscription->plan?->name ?? 'Your plan',
            $subscription->ends_at?->format('d M Y') ?? 'the end of the current period'
        ));
    }

    public function invoice(CompanySubscription $subscription)
    {
        $user = Auth::user();
        $company = $user?->isSuperAdmin()
            ? $subscription->company
            : $user?->company;

        abort_unless($company, 404);
        abort_unless($user?->isSuperAdmin() || (int) $subscription->company_id === (int) $company->id, 404);
        abort_if(strtolower((string) $subscription->status) === 'trialing', 403, 'Invoice generation is not available for trialing subscriptions.');

        $backUrl = $user?->isSuperAdmin()
            ? route('super-admin.payments')
            : route('subscription.show');

        $subscription->load(['company', 'plan', 'renewedFrom']);

        return view('subscription.invoice', [
            'company' => $company,
            'subscription' => $subscription,
            'backUrl' => $backUrl,
        ] + SubscriptionInvoice::data($subscription, $company));
    }

    private function activatePlan(int $companyId, ?string $subscriberEmail, SubscriptionPlan $plan, string $billingCycle, array $paymentData): void
    {
        $usageBaseline = app(SubscriptionLimitService::class)->snapshotUsageBaseline($companyId);
        $licenseCount = $this->resolveLicenseCount($paymentData['license_count'] ?? null, $plan);
        $licensePackage = CompanySubscription::generateLicensePackage($licenseCount);
        $isTrial = (bool) ($paymentData['is_trial'] ?? false);
        $effectiveBillingCycle = $isTrial ? 'monthly' : $billingCycle;

        $endsAt = $effectiveBillingCycle === 'yearly'
            ? now()->addYear()->subDay()->toDateString()
            : now()->addMonth()->subDay()->toDateString();

        if ($isTrial) {
            $endsAt = now()->addDays((int) config('subscription.trial_days', 15))->toDateString();
        }

        $subscription = CompanySubscription::create([
            'company_id' => $companyId,
            'subscription_plan_id' => $plan->id,
            'renewed_from_subscription_id' => $paymentData['renewed_from_subscription_id'] ?? null,
            'billing_cycle' => $effectiveBillingCycle,
            'starts_at' => now()->toDateString(),
            'ends_at' => $endsAt,
            'trial_ends_at' => $isTrial ? $endsAt : null,
            'status' => $paymentData['status'],
            'is_trial' => $isTrial,
            'auto_renew' => true,
            'amount_paid' => $paymentData['amount_paid'],
            'payment_method' => $paymentData['payment_method'],
            'billing_reference' => $paymentData['billing_reference'],
            'subscriber_email' => $subscriberEmail,
            'license_key' => $licensePackage['license_key'],
            'license_count' => $licensePackage['license_count'],
            'license_keys' => $licensePackage['license_keys'],
            'notes' => $isTrial
                ? 'Free trial activated by company account.'
                : (($paymentData['renewed_from_subscription_id'] ?? null) ? 'Renewed by company account.' : 'Updated by company account.'),
            'razorpay_order_id' => $paymentData['razorpay_order_id'] ?? null,
            'razorpay_payment_id' => $paymentData['razorpay_payment_id'] ?? null,
            'usage_baseline' => $usageBaseline,
        ]);

        app(CompanyAdminSeatService::class)->assignSeatIfNeeded($companyId, $subscription, Auth::user());

        $this->expireRenewalSourceIfNeeded($paymentData['renewed_from_subscription_id'] ?? null);
    }

    private function normalizeBillingCycle(?string $billingCycle, SubscriptionPlan $plan): string
    {
        $cycle = strtolower(trim((string) ($billingCycle ?: $plan->billing_cycle ?: 'monthly')));

        return in_array($cycle, self::BILLING_CYCLES, true) ? $cycle : 'monthly';
    }

    private function resolveLicenseCount(?int $requestedCount, SubscriptionPlan $plan): int
    {
        $count = is_numeric($requestedCount) ? (int) $requestedCount : null;

        if ($count !== null && $count > 0) {
            return $count;
        }

        $limit = $plan->limitFor('staff');
        $count = $requestedCount !== null ? max(1, $requestedCount) : max(1, (int) ($limit ?? 1));

        if ($limit !== null) {
            return min($count, max(1, $limit));
        }

        return $count;
    }

    private function buildOrderPayload(RazorpayService $razorpayService, float $amount, string $reference, string $companyName, string $planName, string $billingCycle): array
    {
        if (! $razorpayService->enabled()) {
            throw new RuntimeException(sprintf('Razorpay %s keys are not configured in Global Settings.', $razorpayService->mode()));
        }

        return $razorpayService->createOrder(
            (int) round($amount * 100),
            $reference,
            [
                'company_name' => $companyName,
                'plan_name' => $planName,
                'billing_cycle' => $billingCycle,
            ]
        );
    }

    /**
     * What a one-time Razorpay order pays for; the completed payment must match it exactly.
     */
    private function orderTerms(int $companyId, int $planId, string $billingCycle, int $licenseCount, float $amount): array
    {
        return [
            'company_id' => $companyId,
            'plan_id' => $planId,
            'billing_cycle' => $billingCycle,
            'license_count' => $licenseCount,
            'amount_paise' => (int) round($amount * 100),
        ];
    }

    private function rememberPendingOrder(Request $request, string $orderId, array $terms): void
    {
        $pendingOrders = (array) $request->session()->get(self::PENDING_ORDERS_SESSION_KEY, []);
        $pendingOrders[$orderId] = $terms;

        // Keep only the most recent checkouts so abandoned orders don't pile up in the session.
        $request->session()->put(self::PENDING_ORDERS_SESSION_KEY, array_slice($pendingOrders, -10, null, true));
    }

    private function logOrderFailure(RuntimeException $exception, int $companyId, int $planId, string $reference): void
    {
        Log::error('Unable to create Razorpay order.', [
            'company_id' => $companyId,
            'subscription_plan_id' => $planId,
            'reference' => $reference,
            'error' => $exception->getMessage(),
        ]);
    }

    private function assertTrialEligibility(Company $company, SubscriptionPlan $plan, ?string $billingCycle = null): void
    {
        $eligibility = app(SubscriptionLimitService::class)->trialEligibilityForPlan($company, $plan, $billingCycle);

        if ($eligibility['eligible'] ?? false) {
            return;
        }

        throw ValidationException::withMessages([
            'subscription_plan_id' => $eligibility['reason'] ?? 'This company is not eligible for the free trial on the selected plan.',
        ]);
    }

    private function resolveRenewalSourceId(int $companyId, int $planId, ?int $requestedSubscriptionId = null): ?int
    {
        $expiredSubscriptions = app(SubscriptionLimitService::class)->expiredSubscriptions($companyId)
            ->where('subscription_plan_id', $planId);

        if ($requestedSubscriptionId !== null) {
            $matchedSubscription = $expiredSubscriptions->firstWhere('id', $requestedSubscriptionId);

            return $matchedSubscription?->id;
        }

        return $expiredSubscriptions->sortByDesc('ends_at')->first()?->id;
    }

    private function expireRenewalSourceIfNeeded(?int $renewedFromSubscriptionId): void
    {
        if (! $renewedFromSubscriptionId) {
            return;
        }

        DB::table('company_subscriptions')
            ->where('id', $renewedFromSubscriptionId)
            ->whereIn('status', ['active', 'trialing'])
            ->update([
                'status' => 'expired',
                'updated_at' => now(),
            ]);
    }
}
