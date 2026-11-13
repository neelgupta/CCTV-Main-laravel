<?php

namespace App\Http\Controllers;

use App\Models\CompanySubscription;
use App\Models\State;
use App\Models\SubscriptionPlan;
use App\Services\CompanyAdminSeatService;
use App\Services\MediaUploadService;
use App\Services\RazorpayService;
use App\Services\SubscriptionLimitService;
use App\Support\SubscriptionPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class OnboardingController extends Controller
{
    private const ONBOARDING_SESSION_KEYS = [
        'onboarding.subscription_plan_id',
        'onboarding.billing_cycle',
        'onboarding.subscription_mode',
        'onboarding.license_count',
        'onboarding.billing_reference',
        'onboarding.order_id',
        'onboarding.order_terms',
    ];

    public function index()
    {
        $company = Auth::user()->company;

        if ($company->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        $step = $company->onboarding_step ?? 1;

        if ((int) $step === 3) {
            $step = 4;
        }

        return view('onboarding.steps', [
            'company' => $company,
            'step' => $step,
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('id')->get(),
            'trialEligibilityByPlan' => $this->trialEligibilityMap($company),
            'states' => State::query()
                ->with(['cities' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeStep1(Request $request)
    {
        $company = Auth::user()->company;

        $request->validate([
            'gst_number' => 'nullable|string|max:15',
            'pan_number' => 'nullable|string|max:10',
            'registration_number' => 'nullable|string|max:50',
            'registration_date' => 'nullable|date',
        ]);

        $company->update([
            'gst_number' => $request->gst_number,
            'pan_number' => $request->pan_number,
            'registration_number' => $request->registration_number,
            'registration_date' => $request->registration_date,
            'onboarding_step' => 2,
        ]);

        return redirect()->route('onboarding.index');
    }

    public function storeStep2(Request $request)
    {
        $company = Auth::user()->company;

        $request->validate([
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'favicon' => 'nullable|image|mimes:png,ico,svg,jpg,jpeg,webp|max:1024',
        ]);

        $updateData = [
            'address' => $request->address,
            'city' => $request->city,
            'state' => $request->state,
            'onboarding_step' => 4,
        ];

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                app(MediaUploadService::class)->delete($company->logo, 'public', $company->id);
            }

            $updateData['logo'] = app(MediaUploadService::class)->store($request->file('logo'), 'companies/logos', 'public');
        }

        if ($request->hasFile('favicon')) {
            if ($company->favicon) {
                app(MediaUploadService::class)->delete($company->favicon, 'public', $company->id);
            }

            $updateData['favicon'] = app(MediaUploadService::class)->store($request->file('favicon'), 'companies/favicons', 'public');
        }

        $company->update([
            ...$updateData,
        ]);

        return redirect()->route('onboarding.index');
    }

    public function storeStep3(Request $request)
    {
        $company = Auth::user()->company;

        $request->validate([
            'business_type' => 'required|string|in:CCTV',
            'pipeline_columns' => 'required|array',
            'pipeline_columns.*.name' => 'required|string|max:191',
            'pipeline_columns.*.color' => 'required|string|max:7',
            'project_pipeline_columns' => 'required|array',
            'project_pipeline_columns.*.name' => 'required|string|max:191',
            'project_pipeline_columns.*.color' => 'required|string|max:7',
        ]);

        $company->update([
            'business_type' => 'CCTV',
            'onboarding_step' => 4,
        ]);

        \App\Models\LeadStatus::where('company_id', $company->id)->delete();
        foreach ($request->pipeline_columns as $index => $column) {
            \App\Models\LeadStatus::create([
                'company_id' => $company->id,
                'name' => $column['name'],
                'color' => $column['color'],
                'is_default' => false,
                'order' => $index,
            ]);
        }

        \App\Models\LeadStatus::ensureNewLead($company->id);
        \App\Models\LeadStatus::ensureLoss($company->id);
        \App\Models\LeadStatus::ensureWon($company->id);
        \App\Models\LeadStatus::normalizeSystemStages($company->id);

        \App\Models\ProjectStage::where('company_id', $company->id)->delete();
        foreach ($request->project_pipeline_columns as $index => $column) {
            \App\Models\ProjectStage::create([
                'company_id' => $company->id,
                'name' => $column['name'],
                'color' => $column['color'],
                'key' => \Illuminate\Support\Str::slug($column['name']),
                'order' => $index,
            ]);
        }

        \App\Models\ProjectStage::ensureDefaults($company->id);

        return redirect()->route('onboarding.index');
    }

    public function step4()
    {
        $company = Auth::user()->company;

        if ($redirect = $this->redirectIfAlreadyOnboarded($company)) {
            return $redirect;
        }

        return view('onboarding.steps', [
            'company' => $company,
            'step' => 4,
            'plans' => SubscriptionPlan::query()->where('is_active', true)->orderBy('id')->get(),
            'trialEligibilityByPlan' => $this->trialEligibilityMap($company),
            'states' => State::query()
                ->with(['cities' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function complete(Request $request)
    {
        $company = Auth::user()->company;

        if ($redirect = $this->redirectIfAlreadyOnboarded($company)) {
            return $redirect;
        }

        $validated = $request->validate([
            'subscription_plan_id' => 'required|exists:subscription_plans,id',
            'billing_cycle' => 'required|in:monthly,yearly',
            'subscription_mode' => 'nullable|in:paid,trial',
            'use_free_trial' => 'nullable|boolean',
            'license_count' => 'required|integer|min:1',
        ]);

        $plan = SubscriptionPlan::query()->where('is_active', true)->findOrFail($validated['subscription_plan_id']);
        $billingCycle = $validated['billing_cycle'];
        $subscriptionMode = $request->boolean('use_free_trial') || ($validated['subscription_mode'] ?? null) === 'trial'
            ? 'trial'
            : 'paid';
        $licenseCount = max(1, (int) ($validated['license_count'] ?? 1));

        if ($subscriptionMode === 'trial') {
            $eligibility = app(SubscriptionLimitService::class)->trialEligibilityForPlan($company, $plan, $billingCycle);

            if (! ($eligibility['eligible'] ?? false)) {
                return back()->withErrors([
                    'subscription_plan_id' => $eligibility['reason'] ?? 'This company is not eligible for the selected free trial.',
                ])->withInput();
            }

            $licensePackage = CompanySubscription::generateLicensePackage($licenseCount);
            $billingReference = 'ONB-' . strtoupper(Str::random(8));
            $trialEndsAt = now()->addDays((int) config('subscription.trial_days', 15))->toDateString();

            DB::transaction(function () use ($company, $plan, $licensePackage, $billingReference, $trialEndsAt) {
                $subscription = CompanySubscription::create([
                    'company_id' => $company->id,
                    'subscription_plan_id' => $plan->id,
                    'billing_cycle' => 'monthly',
                    'starts_at' => now()->toDateString(),
                    'ends_at' => $trialEndsAt,
                    'trial_ends_at' => $trialEndsAt,
                    'status' => 'trialing',
                    'is_trial' => true,
                    'auto_renew' => true,
                    'amount_paid' => 0,
                    'payment_method' => 'Free Trial',
                    'billing_reference' => $billingReference,
                    'subscriber_email' => $company->email,
                    'notes' => 'Free trial started during onboarding.',
                    'license_key' => $licensePackage['license_key'],
                    'license_count' => $licensePackage['license_count'],
                    'license_keys' => $licensePackage['license_keys'],
                ]);

                app(CompanyAdminSeatService::class)->assignSeatIfNeeded($company->id, $subscription, Auth::user());

                $company->update([
                    'is_onboarded' => true,
                    'onboarding_step' => 4,
                ]);
            });

            session()->forget(self::ONBOARDING_SESSION_KEYS);

            return redirect()->route('dashboard')->with('success', 'Your ' . config('subscription.trial_days', 15) . '-day free trial has been activated successfully.');
        }

        session([
            'onboarding.subscription_plan_id' => (int) $validated['subscription_plan_id'],
            'onboarding.billing_cycle' => $billingCycle,
            'onboarding.subscription_mode' => $subscriptionMode,
            'onboarding.license_count' => $licenseCount,
        ]);

        return redirect()->route('onboarding.payment');
    }

    public function skip()
    {
        $company = Auth::user()->company;

        if ($redirect = $this->redirectIfAlreadyOnboarded($company)) {
            return $redirect;
        }

        $trialNotice = '';
        $defaultPlan = $this->resolveDefaultSkipTrialPlan();

        if ($defaultPlan) {
            $trialNotice = $this->startDefaultSkipTrial($company, $defaultPlan);
        }

        $company->update([
            'is_onboarded' => true,
            'onboarding_step' => 4,
        ]);

        session()->forget(self::ONBOARDING_SESSION_KEYS);

        $redirect = redirect()->route('dashboard');

        if (! empty($trialNotice)) {
            return $redirect->with('onboarding_trial_notice', $trialNotice);
        }

        return $redirect->with('success', 'You can finish your profile and choose a subscription plan later from the dashboard.');
    }

    public function payment(RazorpayService $razorpayService)
    {
        $company = Auth::user()->company;

        if ($redirect = $this->redirectIfAlreadyOnboarded($company)) {
            return $redirect;
        }

        $planId = (int) session('onboarding.subscription_plan_id');

        if (! $planId) {
            return redirect()->route('onboarding.step4');
        }

        $plan = SubscriptionPlan::query()
            ->where('is_active', true)
            ->findOrFail($planId);

        $billingCycle = session('onboarding.billing_cycle', 'monthly');
        $isTrial = session('onboarding.subscription_mode', 'paid') === 'trial';
        $licenseCount = max(1, (int) session('onboarding.license_count', 1));

        if ($isTrial) {
            $eligibility = app(SubscriptionLimitService::class)->trialEligibilityForPlan($company, $plan, $billingCycle);

            if (! ($eligibility['eligible'] ?? false)) {
                session()->forget(['onboarding.subscription_mode', 'onboarding.order_id', 'onboarding.order_terms', 'onboarding.billing_reference']);

                return redirect()->route('onboarding.step4')->withErrors([
                    'subscription_plan_id' => $eligibility['reason'] ?? 'This company is not eligible for the selected free trial.',
                ]);
            }
        }

        $pricing = SubscriptionPricing::forPlan($plan->priceForCycle($billingCycle), $isTrial ? 0 : $licenseCount);
        $subtotal = $pricing['subtotal'];
        $gstRate = $pricing['gst_rate'];
        $gstAmount = $pricing['gst_amount'];
        $totalPrice = $pricing['total'];
        $reference = 'ONB-' . strtoupper(Str::random(8));

        try {
            $order = $totalPrice > 0
                ? $this->buildOrderPayload($razorpayService, $totalPrice, $reference, $company->name, $plan->name)
                : [
                    'id' => ($isTrial ? 'trial_' : 'free_plan_') . Str::lower(Str::random(10)),
                    'amount' => 0,
                    'currency' => 'INR',
                    'receipt' => $reference,
                    'demo' => true,
                ];
        } catch (RuntimeException $exception) {
            Log::error('Unable to create Razorpay order during onboarding.', [
                'company_id' => $company->id,
                'subscription_plan_id' => $plan->id,
                'reference' => $reference,
                'error' => $exception->getMessage(),
            ]);

            return redirect()->route('onboarding.step4')->withErrors([
                'subscription_plan_id' => 'Online payment is not available right now. Please try again later or contact support.',
            ]);
        }

        session([
            'onboarding.billing_reference' => $reference,
            'onboarding.order_id' => $order['id'] ?? null,
            'onboarding.order_terms' => $this->orderTerms($plan->id, $billingCycle, $licenseCount, $totalPrice),
        ]);

        return view('onboarding.payment', [
            'company' => $company,
            'plan' => $plan,
            'billingCycle' => $isTrial ? 'monthly' : $billingCycle,
            'subtotal' => $subtotal,
            'gstRate' => $gstRate,
            'gstAmount' => $gstAmount,
            'price' => $totalPrice,
            'licenseCount' => $licenseCount,
            'order' => $order,
            'isTrial' => $isTrial,
        ]);
    }

    public function finalizePayment(Request $request, RazorpayService $razorpayService)
    {
        $company = Auth::user()->company;

        if ($redirect = $this->redirectIfAlreadyOnboarded($company)) {
            return $redirect;
        }

        $planId = (int) session('onboarding.subscription_plan_id');
        $subscriptionMode = session('onboarding.subscription_mode', 'paid');
        abort_if(! $planId, 422, 'No subscription plan selected.');

        $validated = $request->validate([
            'razorpay_order_id' => ['nullable', 'string', 'max:191'],
            'razorpay_payment_id' => ['nullable', 'string', 'max:191'],
            'razorpay_signature' => ['nullable', 'string', 'max:255'],
        ]);

        // Serialise completion per company so a double submit can't activate twice.
        $lock = Cache::lock('onboarding-finalize:' . $company->id, 30);
        abort_unless($lock->get(), 409, 'This payment is already being processed.');

        try {
            if ($redirect = $this->redirectIfAlreadyOnboarded($company->refresh())) {
                return $redirect;
            }

            DB::transaction(function () use ($company, $planId, $validated, $razorpayService, $subscriptionMode) {
                $plan = SubscriptionPlan::query()
                    ->where('is_active', true)
                    ->findOrFail($planId);

                $billingCycle = session('onboarding.billing_cycle', 'monthly');
                $isTrial = $subscriptionMode === 'trial';
                $licenseCount = max(1, (int) session('onboarding.license_count', 1));
                $amount = SubscriptionPricing::forPlan($plan->priceForCycle($billingCycle), $isTrial ? 0 : $licenseCount)['total'];
                $licensePackage = CompanySubscription::generateLicensePackage($licenseCount);

                if ($isTrial) {
                    $eligibility = app(SubscriptionLimitService::class)->trialEligibilityForPlan($company, $plan, $billingCycle);

                    if (! ($eligibility['eligible'] ?? false)) {
                        abort(422, $eligibility['reason'] ?? 'This company is not eligible for the selected free trial.');
                    }
                }

                // A paid plan is only activated by a genuine Razorpay payment for the order created on the
                // payment page, for the same plan, cycle, seat count and amount, and each order activates once.
                if ($amount > 0) {
                    $orderId = (string) ($validated['razorpay_order_id'] ?? '');
                    $paymentId = (string) ($validated['razorpay_payment_id'] ?? '');
                    $isValid = $orderId === (string) session('onboarding.order_id')
                        && session('onboarding.order_terms') === $this->orderTerms($plan->id, $billingCycle, $licenseCount, $amount)
                        && $razorpayService->verifySignature($orderId, $paymentId, (string) ($validated['razorpay_signature'] ?? ''));

                    if (! $isValid) {
                        Log::warning('Razorpay onboarding payment rejected: order not issued for this checkout or signature invalid.', [
                            'company_id' => $company->id,
                            'subscription_plan_id' => $plan->id,
                            'razorpay_order_id' => $orderId,
                            'razorpay_payment_id' => $paymentId,
                        ]);

                        abort(422, 'Payment verification failed. Please try again.');
                    }

                    abort_if(
                        CompanySubscription::razorpayPaymentAlreadyUsed($orderId, $paymentId),
                        422,
                        'This payment has already been used to activate a subscription.'
                    );
                }

                $effectiveBillingCycle = $isTrial ? 'monthly' : $billingCycle;
                $endsAt = $effectiveBillingCycle === 'yearly'
                    ? now()->addYear()->subDay()->toDateString()
                    : now()->addMonth()->subDay()->toDateString();

                if ($isTrial) {
                    $endsAt = now()->addDays((int) config('subscription.trial_days', 15))->toDateString();
                }

                $subscription = CompanySubscription::create([
                    'company_id' => $company->id,
                    'subscription_plan_id' => $plan->id,
                    'billing_cycle' => $effectiveBillingCycle,
                    'starts_at' => now()->toDateString(),
                    'ends_at' => $endsAt,
                    'trial_ends_at' => $isTrial ? $endsAt : null,
                    'status' => $isTrial ? 'trialing' : 'active',
                    'is_trial' => $isTrial,
                    'auto_renew' => true,
                    'amount_paid' => $amount,
                    'payment_method' => $isTrial ? 'Free Trial' : ($amount > 0 ? 'Razorpay' : 'Free Plan'),
                    'billing_reference' => session('onboarding.billing_reference'),
                    'subscriber_email' => $company->email,
                    'notes' => $isTrial ? 'Free trial started during onboarding.' : 'Created during onboarding.',
                    'razorpay_order_id' => $validated['razorpay_order_id'] ?: session('onboarding.order_id'),
                    'razorpay_payment_id' => $validated['razorpay_payment_id'] ?: null,
                    'license_key' => $licensePackage['license_key'],
                    'license_count' => $licensePackage['license_count'],
                    'license_keys' => $licensePackage['license_keys'],
                ]);

                app(CompanyAdminSeatService::class)->assignSeatIfNeeded($company->id, $subscription, Auth::user());

                $company->update([
                    'is_onboarded' => true,
                    'onboarding_step' => 4,
                ]);
            });
        } finally {
            $lock->release();
        }

        session()->forget(self::ONBOARDING_SESSION_KEYS);
        session()->forget('onboarding.license_count');

        return redirect()->route('dashboard')->with('success', $subscriptionMode === 'trial'
            ? 'Free trial activated and account setup completed.'
            : 'Payment successful and account setup completed.');
    }

    /**
     * What the onboarding Razorpay order pays for; the completed payment must match it exactly.
     */
    private function orderTerms(int $planId, string $billingCycle, int $licenseCount, float $amount): array
    {
        return [
            'plan_id' => $planId,
            'billing_cycle' => $billingCycle,
            'license_count' => $licenseCount,
            'amount_paise' => (int) round($amount * 100),
        ];
    }

    private function buildOrderPayload(RazorpayService $razorpayService, float $amount, string $reference, string $companyName, string $planName): array
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
            ]
        );
    }

    private function trialEligibilityMap($company): array
    {
        $subscriptionLimitService = app(SubscriptionLimitService::class);

        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn (SubscriptionPlan $plan) => [
                $plan->id => $subscriptionLimitService->trialEligibilityForPlan($company, $plan, 'monthly'),
            ])
            ->all();
    }

    private function redirectIfAlreadyOnboarded($company)
    {
        if (! $company?->hasCompletedOnboarding()) {
            return null;
        }

        session()->forget(self::ONBOARDING_SESSION_KEYS);

        return redirect()->route('dashboard');
    }

    private function resolveDefaultSkipTrialPlan(): ?SubscriptionPlan
    {
        return SubscriptionPlan::query()
            ->where('is_active', true)
            ->where('has_free_trial', true)
            ->where(function ($query) {
                $query->whereRaw('LOWER(name) = ?', ['starter'])
                    ->orWhereRaw('LOWER(slug) = ?', ['starter'])
                    ->orWhere('staff_limit', 1);
            })
            ->orderByRaw("CASE WHEN LOWER(name) = 'starter' OR LOWER(slug) = 'starter' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();
    }

    private function startDefaultSkipTrial($company, SubscriptionPlan $plan): string
    {
        $eligibility = app(SubscriptionLimitService::class)->trialEligibilityForPlan($company, $plan);

        if (! ($eligibility['eligible'] ?? false)) {
            return '';
        }

        $billingCycle = 'monthly';
        $amount = 0;
        $endsAt = now()->addDays((int) config('subscription.trial_days', 15))->toDateString();
        $licensePackage = CompanySubscription::generateLicensePackage(
            is_numeric($plan->staff_limit) && (int) $plan->staff_limit > 0 ? (int) $plan->staff_limit : 1
        );
        $reference = 'ONB-' . strtoupper(Str::random(8));

        $subscription = CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'billing_cycle' => $billingCycle,
            'starts_at' => now()->toDateString(),
            'ends_at' => $endsAt,
            'trial_ends_at' => $endsAt,
            'status' => 'trialing',
            'is_trial' => true,
            'auto_renew' => true,
            'amount_paid' => $amount,
            'payment_method' => 'Free Trial',
            'billing_reference' => $reference,
            'subscriber_email' => $company->email,
            'notes' => 'Free trial started by skipping onboarding plan selection.',
            'license_key' => $licensePackage['license_key'],
            'license_count' => $licensePackage['license_count'],
            'license_keys' => $licensePackage['license_keys'],
        ]);

        app(CompanyAdminSeatService::class)->assignSeatIfNeeded($company->id, $subscription, Auth::user());

        return sprintf(
            'Your %s free trial has started successfully. This plan allows %s staff member.',
            $plan->name,
            $plan->staffLimitLabel()
        );
    }
}
