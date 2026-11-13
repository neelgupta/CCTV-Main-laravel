<x-app-layout>
    <div id="subscription-show-page">
    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">Subscription</h2>
            <p class="mt-1 text-sm text-slate-500">Manage your current purchase and add more active plans when your business grows.</p>
        </div>
    </x-slot>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        @php
            $flashError = (string) session('error');
            $flashErrorLower = strtolower($flashError);
            $isSubscriptionLimit = str_contains($flashErrorLower, 'upgrade your subscription')
                || str_contains($flashErrorLower, 'upgrade your plan');
        @endphp
        <div class="mb-6 rounded-2xl px-4 py-3 text-sm {{ $isSubscriptionLimit ? 'border border-amber-200 bg-amber-50 text-amber-900' : 'border border-red-200 bg-red-50 text-red-800' }}">
            {{ $flashError }}
        </div>
    @endif
    @if(!empty($deactivatedPlanMessage))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $deactivatedPlanMessage }}
        </div>
    @endif
    @if(!empty($writeRestricted))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $expiredAccessMessage }}
        </div>
    @endif
    @php
        $defaultLicenseCount = 1;
        $defaultBillingCycle = 'monthly';
        $planFeatures = [
            'Lead Management',
            'Project Management',
            'AMC Management',
            'Client Management',
            'Staff Management',
        ];
    @endphp

    @php
        $historyByPlan = $subscriptionHistory->groupBy('subscription_plan_id')->map(function ($subs) {
            return $subs->map(function ($s) {
                return [
                    'plan_name'         => $s->plan?->name ?? 'Subscription',
                    'billing_cycle'     => ucfirst($s->billing_cycle ?? 'monthly'),
                    'status'            => $s->status,
                    'amount_paid'       => (float) ($s->amount_paid ?? 0),
                    'starts_at'         => $s->starts_at?->format('d M Y'),
                    'starts_year'       => $s->starts_at ? (int) $s->starts_at->format('Y') : null,
                    'ends_at'           => $s->ends_at?->format('d M Y'),
                    'billing_reference' => $s->billing_reference ?: 'N/A',
                    'is_trial'          => (bool) $s->is_trial,
                    'payment_method'    => $s->payment_method ?: 'Razorpay',
                ];
            })->values()->all();
        })->all();
    @endphp

    <form id="rzp-process-form" method="POST" action="{{ route('subscription.change') }}" class="hidden">
        @csrf
        <input type="hidden" id="rzp_plan_id" name="subscription_plan_id" value="">
        <input type="hidden" id="rzp_billing_cycle" name="billing_cycle" value="{{ $defaultBillingCycle }}">
        <input type="hidden" id="rzp_billing_ref" name="billing_reference" value="">
        <input type="hidden" id="rzp_license_count" name="license_count" value="{{ $defaultLicenseCount }}">
        <input type="hidden" id="rzp_is_trial" name="is_trial" value="0">
        <input type="hidden" id="rzp_auto_renew" name="auto_renew" value="0">
        <input type="hidden" id="rzp_renew_subscription_id" name="renew_subscription_id" value="">
        <input type="hidden" id="rzp_order_id" name="razorpay_order_id" value="">
        <input type="hidden" id="rzp_payment_id" name="razorpay_payment_id" value="">
        <input type="hidden" id="rzp_signature" name="razorpay_signature" value="">
    </form>

    <div class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-[0.82fr_1.18fr]">
            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="rounded-[24px] bg-[#060b1e] p-5 text-white">
                    <div class="text-xs font-black uppercase tracking-[0.28em] text-cyan-300">Primary Active Subscription</div>
                    <div class="mt-3 text-4xl font-black">{{ $activeSubscription?->plan?->name ?? 'No Active Plan' }}</div>
                    <!-- <p class="mt-2 text-sm text-slate-300">{{ $activeSubscription?->plan?->description ?? 'No active purchase is assigned to this company yet.' }}</p> -->
                    <div class="mt-6 text-5xl font-black">
                        {{ $activeSubscription ? number_format((float) ( $activeSubscription->plan?->price ), 0) : '0' }}
                    </div>
                    <div class="text-sm uppercase tracking-[0.26em] text-slate-400">{{ ucfirst($activeSubscription?->billing_cycle ?? $activeSubscription?->plan?->billing_cycle ?? 'monthly') }}</div>
                    </div>

                <dl class="mt-5 divide-y divide-slate-200">
                    <div class="flex items-center justify-between py-4">
                        <dt class="text-slate-500">Status</dt>
                        <dd class="font-semibold text-slate-950">{{ $activeSubscription ? str($activeSubscription->status)->headline()->toString() : 'Inactive' }}</dd>
                    </div>
                    {{-- <div class="flex items-center justify-between py-4">
                        <dt class="text-slate-500">Total Staff Seats</dt>
                        <dd class="font-semibold text-slate-950">{{ $subscriptionUsage['staff_limit_label'] ?? 'Unlimited' }}</dd>
                    </div>
                    <div class="flex items-center justify-between py-4">
                        <dt class="text-slate-500">Used Members</dt>
                        <dd class="font-semibold text-slate-950">{{ number_format((int) ($subscriptionUsage['used_members'] ?? 0)) }}</dd>
                    </div>
                    <div class="flex items-center justify-between py-4">
                        <dt class="text-slate-500">Remaining Members</dt>
                        <dd class="font-semibold text-slate-950">
                            {{ $subscriptionUsage['remaining_members'] === null ? 'Unlimited' : number_format((int) $subscriptionUsage['remaining_members']) }}
                        </dd>
                    </div> --}}
                    {{-- <div class="flex items-center justify-between py-4">
                        <dt class="text-slate-500">Starts At</dt>
                        <dd class="font-semibold text-slate-950">{{ $activeSubscription?->starts_at?->format('d M Y') ?? '-' }}</dd>
                    </div>
                    <div class="flex items-center justify-between py-4">
                        <dt class="text-slate-500">Ends At</dt>
                        <dd class="font-semibold text-slate-950">{{ $activeSubscription?->ends_at?->format('d M Y') ?? '-' }}</dd>
                    </div>
                    <div class="flex items-center justify-between py-4">
                        <dt class="text-slate-500">Payment Ref</dt>
                        <dd class="font-semibold text-slate-950">{{ $activeSubscription?->billing_reference ?: 'N/A' }}</dd>
                    </div> --}}
                </dl>

                @if($activeSubscription)
                    {{-- <div class="mt-4 rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-900">
                        You can add another active purchase from the panel on the right. Existing subscriptions stay active when a new one is purchased.
                    </div> --}}
                @else
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                        No active subscription is assigned to this company yet.
                    </div>
                @endif
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="mx-auto max-w-5xl rounded-[32px] border border-cyan-200 bg-white/95 px-4 py-6 shadow-[0_20px_70px_rgba(37,99,235,0.10)] sm:px-6">
                    <div class="text-center">
                        <h3 class="text-2xl font-black text-slate-950 sm:text-3xl">Choose your subscription plan</h3>
                        <p class="mt-2 text-sm text-slate-500">Select the plan that best fits your business needs.</p>
                    </div>

                    <div class="mt-6 flex justify-center">
                        <div class="inline-flex rounded-full border border-cyan-200 bg-cyan-50 p-1 shadow-sm">
                            <label style="background-color: #c5233e;" class="billing-cycle-option inline-flex cursor-pointer items-center rounded-full px-5 py-2.5 text-sm font-semibold transition" data-cycle-option="monthly">
                                <input type="radio" name="billing_cycle_ui" value="monthly" class="sr-only" checked>
                                Monthly
                            </label>
                            <label class="billing-cycle-option inline-flex cursor-pointer items-center rounded-full px-5 py-2.5 text-sm font-semibold transition" data-cycle-option="yearly">
                                <input type="radio" name="billing_cycle_ui" value="yearly" class="sr-only">
                                Yearly
                            </label>
                        </div>
                    </div>

                    <div class="mt-8">
                        <select id="plan-select" class="sr-only">
                            <option value="" selected>Select a plan</option>
                            @foreach($plans as $plan)
                                @php
                                    $planSeatCount = $defaultLicenseCount;
                                @endphp
                                <option
                                    value="{{ $plan->id }}"
                                    data-monthly-price="{{ number_format((float) $plan->priceForCycle('monthly'), 0, '.', '') }}"
                                    data-yearly-price="{{ number_format((float) $plan->priceForCycle('yearly'), 0, '.', '') }}"
                                    data-yearly-discount-label="{{ $plan->yearlyDiscountLabel() ?? '' }}"
                                    data-name="{{ $plan->name }}"
                                    data-staff-limit="{{ $plan->staff_limit ?? '' }}"
                                    data-has-free-trial="{{ $plan->has_free_trial ? 1 : 0 }}"
                                    data-trial-eligible="{{ ($trialEligibilityByPlan[$plan->id]['eligible'] ?? false) ? 1 : 0 }}"
                                    data-trial-reason="{{ $trialEligibilityByPlan[$plan->id]['reason'] ?? '' }}"
                                >{{ $plan->name }} - Rs.{{ number_format((float) $plan->priceForCycle('monthly'), 0) }} / monthly</option>
                            @endforeach
                        </select>

                        <div class="grid gap-4 lg:grid-cols-2">
                            @foreach($plans as $plan)
                                @php
                                    $trialEligible = $trialEligibilityByPlan[$plan->id]['eligible'] ?? false;
                                    $yearlyDiscountLabel = $plan->yearlyDiscountLabel();
                                    $cardSeatCount = $defaultLicenseCount;
                                @endphp
                                <div
                                    role="button"
                                    tabindex="0"
                                    class="subscription-plan-card relative flex h-full flex-col rounded-[28px] border border-cyan-200 bg-white text-left transition"
                                    data-plan-card-id="{{ $plan->id }}"
                                    data-seat-count="{{ $cardSeatCount }}"
                                    data-monthly-price="{{ number_format((float) $plan->priceForCycle('monthly'), 0, '.', '') }}"
                                    data-yearly-price="{{ number_format((float) $plan->priceForCycle('yearly'), 0, '.', '') }}"
                                    data-plan-name="{{ $plan->name }}"
                                    data-staff-limit="{{ $plan->staff_limit ?? '' }}"
                                >
                                    @if($plan->is_popular)
                                        <span style="background-color: #c5233e;" class="absolute right-4 top-0 -translate-y-1/2 rounded-full bg-blue-600 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-white shadow">
                                            Most Popular
                                        </span>
                                    @endif

                                    <div class="border-b border-cyan-100 px-5 pb-6 pt-8 text-center">
                                        <div class="text-2xl font-black text-slate-950 sm:text-[1.75rem]">{{ $plan->name }}</div>
                                        <div class="mt-6 text-3xl font-black text-slate-950 sm:text-4xl" data-plan-card-price="{{ $plan->id }}">
                                            Rs.{{ number_format((float) $plan->priceForCycle('monthly'), 0) }}
                                        </div>
                                        <div class="mt-1 text-xs text-slate-500">
                                            <span data-plan-card-unit-price="{{ $plan->id }}">Rs.{{ number_format((float) $plan->priceForCycle('monthly'), 0) }} / staff / month</span>
                                            <span class="block text-[10px] text-slate-400 mt-0.5">+ {{ setting('subscription_gst_percentage', 18) }}% GST</span>
                                        </div>

                                        @php
                                            $planNameLower = strtolower($plan->name ?? '');
                                            $futurePrice = null;
                                            if (str_contains($planNameLower, 'growth')) {
                                                $futurePrice = 299;
                                            } elseif (str_contains($planNameLower, 'enterprise')) {
                                                $futurePrice = 599;
                                            }
                                        @endphp

                                        @if(now()->format('Y-m-d') <= '2026-10-10' && $futurePrice)
                                            <div class="mt-3 rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-center text-xs font-semibold text-amber-900 shadow-sm">
                                                Current price is available till 10th Oct 2026. After 10th Oct 2026, {{ $plan->name }} plan price will be Rs. {{ $futurePrice }}.
                                            </div>
                                        @endif
                                    </div>

                                    <div class="flex-1 px-5 py-5">
                                        <div class="space-y-2">
                                            @foreach($planFeatures as $feature)
                                                <div class="flex items-center gap-2 rounded-full border border-cyan-200 bg-cyan-50/40 px-3 py-2 text-sm font-semibold text-slate-700">
                                                    <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                                                    <span>{{ $feature }}</span>
                                                </div>
                                            @endforeach
                                        </div>

                                        @if(! $plan->disable_staff_tracking)
                                            <div style="background-color: #c5233e;" class="mt-3 rounded-2xl bg-[#284fda] px-4 py-3 text-white">
                                                <div class="flex items-center justify-between gap-3">
                                                    <div class="flex items-center gap-2 text-sm font-semibold">
                                                        <span class="h-2.5 w-2.5 rounded-full bg-white"></span>
                                                        <span>Staff Tracking</span>
                                                    </div>
                                                    <span class="rounded-full bg-white/15 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.18em] text-white">
                                                        Enterprise Only
                                                    </span>
                                                </div>
                                            </div>
                                        @endif

                                        <div class="mt-4 border-t border-dashed border-cyan-100 pt-4 text-center">
                                            <div class="text-xs font-bold uppercase tracking-[0.28em] text-slate-500">Select Staff</div>
                                            <div class="mt-3 inline-flex items-center overflow-hidden rounded-full border border-cyan-200 bg-white shadow-sm">
                                                <button type="button" class="seat-stepper-button inline-flex h-10 w-10 items-center justify-center border-r border-cyan-200 text-lg font-bold text-blue-600 transition hover:bg-blue-50" data-seat-decrement data-seat-plan-id="{{ $plan->id }}">-</button>
                                                <span class="min-w-[3.5rem] px-4 py-2 text-center text-base font-black text-slate-950" data-seat-value data-seat-plan-id="{{ $plan->id }}">{{ $cardSeatCount }}</span>
                                                <button type="button" class="seat-stepper-button inline-flex h-10 w-10 items-center justify-center border-l border-cyan-200 text-lg font-bold text-blue-600 transition hover:bg-blue-50" data-seat-increment data-seat-plan-id="{{ $plan->id }}">+</button>
                                            </div>
                                        </div>

                                        @if($plan->has_free_trial)
                                            <div
                                                class="mt-4 inline-flex rounded-full border border-emerald-200 bg-emerald-50 px-4 py-2 text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700"
                                                data-plan-card-trial-status="{{ $plan->id }}"
                                            >
                                                {{ $trialEligible ? '15-Day Free Trial Available' : 'Trial unavailable' }}
                                            </div>
                                        @endif
                                    </div>

                                    <div class="border-t border-cyan-100 px-5 py-5">
                                            <button style="background-color: #c5233e;"
                                                type="button"
                                                class="inline-flex w-full items-center justify-center rounded-full px-5 py-3 text-sm font-bold text-white shadow-lg transition hover:opacity-95"
                                                style="background: linear-gradient(90deg, #1d4ed8 0%, #0891b2 100%); box-shadow: 0 14px 30px rgba(37, 99, 235, 0.18);"
                                                data-plan-card-action="{{ $plan->id }}"
                                            >
                                                Select {{ $plan->name }} &rarr;
                                            </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <p id="plan-price-hint" class="mt-4 text-center text-xs text-slate-500"></p>
                    </div>

                    <div id="trial-panel" class="mt-5 hidden rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                        <label class="flex items-start gap-3 text-sm text-emerald-900">
                            <input id="trial-checkbox" type="checkbox" disabled class="mt-1 rounded border-emerald-300 text-emerald-600 focus:ring-emerald-500 disabled:cursor-not-allowed disabled:opacity-50">
                            <span>
                                <span class="block font-semibold">Use 15-day free trial on this plan</span>
                                <span id="trial-hint" class="block text-xs text-emerald-700"></span>
                            </span>
                        </label>
                    </div>

                    @if($recurringEnabled)
                        <div id="auto-renew-panel" class="mt-5 rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-3">
                            @if($recurringMandatory)
                                <div class="flex items-start gap-3 text-sm text-cyan-900">
                                    <svg class="mt-0.5 h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span>
                                        <span class="block font-semibold">Renews automatically</span>
                                        <span class="block text-xs text-cyan-800">Paid plans are charged automatically every billing cycle through Razorpay. You can cancel auto-renew any time.</span>
                                    </span>
                                </div>
                            @else
                                <label class="flex items-start gap-3 text-sm text-cyan-900">
                                    <input id="auto-renew-checkbox" type="checkbox" checked class="mt-1 rounded border-cyan-300 text-cyan-600 focus:ring-cyan-500">
                                    <span>
                                        <span class="block font-semibold">Renew automatically</span>
                                        <span class="block text-xs text-cyan-800">Charge this plan automatically every billing cycle through Razorpay. You can cancel auto-renew any time.</span>
                                    </span>
                                </label>
                            @endif
                        </div>
                    @endif

                    <div class="mt-6 flex justify-end">
                        <button style="background-color: #c5233e;"
                            id="rzp-change-btn"
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full bg-blue-700 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-200 transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-60"
                            style="background: linear-gradient(90deg, #1d4ed8 0%, #0f766e 100%); box-shadow: 0 14px 30px rgba(37, 99, 235, 0.22);"
                        >
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                            Continue to payment
                        </button>
                    </div>
                </div>
            </section>
        </div>

    <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <h3 class="text-2xl font-bold text-slate-950">Active Purchased Plans</h3>
            <p class="mt-1 text-sm text-slate-500">Every active subscription bought by this company appears here.</p>
        </div>

        <div class="mt-4 flex flex-col gap-3">
            @forelse($activeSubscriptions as $subscription)
                <div class="rounded-none border {{ $activeSubscription?->id === $subscription->id ? 'border-indigo-400 shadow-[0_0_0_2px_rgba(99,102,241,0.12)]' : 'border-slate-200' }} bg-white py-4 px-5 transition-all" data-sub-id="{{ $subscription->id }}" data-plan-id="{{ $subscription->subscription_plan_id }}">
                    <div class="grid items-center gap-4 grid-cols-1 md:grid-cols-2 lg:grid-cols-12">
                        <div class="lg:col-span-3 flex flex-col gap-1">
                            <div class="flex items-center gap-2">
                                <h4 class="text-base font-bold text-slate-950 leading-tight">
                                    {{ $subscription->plan?->name ?? 'Subscription' }}
                                </h4>
                                <span class="shrink-0 rounded-none bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                     {{ $subscription->status === 'trialing' ? 'Trial' : str($subscription->status)->headline()->toString() }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-500 line-clamp-1">
                                {{ $subscription->plan?->description ?? 'Purchased subscription plan.' }}
                            </p>

                            @if($subscription->isRecurring() && $subscription->inGracePeriod())
                                <div class="mt-1 self-start rounded-none border border-rose-200 bg-rose-50 px-2.5 py-1 text-[11px] font-semibold text-rose-700">
                                    Renewal payment failed. Razorpay is retrying; access continues until {{ $subscription->grace_ends_at->format('d M Y') }}.
                                </div>
                            @endif

                            @if($activeSubscription?->id === $subscription->id)
                                <div class="mt-0.5 self-start rounded-none bg-indigo-50 px-2.5 py-0.5 text-[10px] font-semibold text-indigo-700">
                                    Primary Active Plan
                                </div>
                            @endif
                        </div>

                        <div class="lg:col-span-2 lg:border-l lg:border-slate-100 lg:pl-4 py-0.5">
                            <div class="text-xl font-extrabold text-slate-950 tracking-tight">
                                Rs.{{ number_format((float) ($subscription->plan->monthly_price ?? 0), 0) }}
                            </div>
                            <div class="text-[9px] font-bold uppercase tracking-[0.15em] text-slate-400">
                                {{ $subscription->billing_cycle ?? $subscription->plan?->billing_cycle ?? 'monthly' }}
                            </div>
                        </div>

                        <div class="lg:col-span-4 lg:border-l lg:border-slate-100 lg:pl-4 grid grid-cols-3 gap-2">
                            <div class="flex flex-col rounded-none bg-slate-50/50 p-1.5 border border-slate-100/70 text-center lg:text-left">
                                <span class="text-slate-400 uppercase tracking-wider text-[9px] font-semibold">Total</span>
                                <span class="text-sm font-bold text-slate-900 mt-0.5">
                                    {{ $subscriptionUsageById[$subscription->id]['total_members_label'] ?? ($subscription->licenseSeatCountLabel() ?? 'Unlimited') }}
                                </span>
                            </div>
                            <div class="flex flex-col rounded-none bg-slate-50/50 p-1.5 border border-slate-100/70 text-center lg:text-left">
                                <span class="text-slate-400 uppercase tracking-wider text-[9px] font-semibold">Used</span>
                                <span class="text-sm font-bold text-slate-900 mt-0.5">
                                    {{ number_format((int) ($subscriptionUsageById[$subscription->id]['used_members'] ?? 0)) }}
                                </span>
                            </div>
                            <div class="flex flex-col rounded-none bg-slate-50/50 p-1.5 border border-slate-100/70 text-center lg:text-left">
                                <span class="text-slate-400 uppercase tracking-wider text-[9px] font-semibold">Remaining</span>
                                <span class="text-sm font-bold text-slate-900 mt-0.5">
                                    {{ ($subscriptionUsageById[$subscription->id]['remaining_members'] ?? null) === null ? 'Unlimited' : number_format((int) $subscriptionUsageById[$subscription->id]['remaining_members']) }}
                                </span>
                            </div>
                        </div>

                        <div class="lg:col-span-3 lg:border-l lg:border-slate-100 lg:pl-4 space-y-1 text-[11px] text-slate-600">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-slate-400">Starts</span>
                                <span class="font-medium text-slate-900">{{ $subscription->starts_at?->format('d M Y') ?? '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-slate-400">Ends</span>
                                <span class="font-medium text-slate-900">{{ $subscription->ends_at?->format('d M Y') ?? '-' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-slate-400">Reference</span>
                                <span class="font-medium text-slate-700 font-mono truncate max-w-[110px]" title="{{ $subscription->billing_reference }}">
                                    {{ $subscription->billing_reference ?: 'N/A' }}
                                </span>
                            </div>
                            @if($subscription->isRecurring())
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-slate-400">Auto-renew</span>
                                    <span class="font-semibold {{ $subscription->auto_renew ? 'text-emerald-700' : 'text-slate-500' }}">{{ $subscription->auto_renew ? 'On' : 'Off' }}</span>
                                </div>
                                @if($subscription->auto_renew && $subscription->next_charge_at)
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-slate-400">Next charge</span>
                                        <span class="font-medium text-slate-900">{{ $subscription->next_charge_at->format('d M Y') }}</span>
                                    </div>
                                @endif
                            @endif
                            <div class="pt-2 flex items-center gap-2 flex-wrap">
                                @if(strtolower((string) $subscription->status) !== 'trialing')
                                    <a href="{{ route('subscription.invoice', $subscription) }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-none border border-slate-300 bg-white px-3 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                        Invoice
                                    </a>
                                @endif
                                <button type="button"
                                        class="history-toggle inline-flex items-center gap-1 rounded-none border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-[11px] font-semibold text-indigo-600 hover:bg-indigo-100 transition-colors"
                                        data-history-id="{{ $subscription->id }}"
                                        data-plan-id="{{ $subscription->subscription_plan_id }}">
                                    <svg class="history-chevron h-3 w-3 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                    History
                                </button>
                                @if($subscription->isRecurring() && $subscription->auto_renew)
                                    <form method="POST" action="{{ route('subscription.cancel-auto-renew', $subscription) }}"
                                          data-swal-confirm="Stop automatic payments for {{ $subscription->plan?->name ?? 'this plan' }}? It stays active until {{ $subscription->ends_at?->format('d M Y') ?? 'the end of the paid period' }}."
                                          data-swal-title="Cancel auto-renew"
                                          data-swal-confirm-button="Cancel auto-renew"
                                          data-swal-cancel-button="Keep auto-renew"
                                          data-swal-confirm-color="#e11d48">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center rounded-none border border-rose-200 bg-rose-50 px-3 py-1.5 text-[11px] font-semibold text-rose-700 hover:bg-rose-100">
                                            Cancel auto-renew
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                    </div>

                    <div id="hist-{{ $subscription->id }}" class="hidden mt-3 pt-3 border-t border-slate-100">
                        <div class="flex items-center justify-between mb-3">
                            <h5 class="text-xs font-semibold uppercase tracking-wider text-slate-500">Purchase History</h5>
                            <div class="relative">
                                    <select class="hist-year-sel appearance-none text-xs rounded-none border border-slate-200 bg-white py-1.5 pl-3 pr-7 font-semibold text-slate-700 outline-none cursor-pointer focus:ring-1 focus:ring-indigo-400"></select>
                                <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                </div>
                            </div>
                        </div>
                        <div class="hist-content"></div>
                    </div>

                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">
                    No active purchased plans are available for this company yet.
                </div>
            @endforelse
        </div>
    </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h3 class="text-2xl font-bold text-slate-950">Expired Purchased Plans</h3>
                <p class="mt-1 text-sm text-slate-500">Plans that have already expired are listed here so you can renew them with a new purchase.</p>
            </div>

            <div class="mt-4 flex flex-col gap-3">
                @forelse($expiredSubscriptions as $subscription)
                    <div class="rounded-none border border-rose-200 bg-rose-50/60 px-5 py-4" data-sub-id="{{ $subscription->id }}" data-plan-id="{{ $subscription->subscription_plan_id }}">
                        <div class="grid gap-4 md:grid-cols-[1.2fr_0.8fr_1fr] md:items-center">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-base font-bold text-slate-950">{{ $subscription->plan?->name ?? 'Subscription' }}</h4>
                                    <span class="rounded-none bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700">
                                        Expired
                                    </span>
                                </div>
                                <p class="mt-1 text-xs text-slate-600">
                                    {{ $subscription->plan?->description ?? 'Expired subscription plan.' }}
                                </p>
                            </div>
                            <div>
                                <div class="text-xl font-extrabold text-slate-950">
                                    Rs.{{ number_format((float) ($subscription->amount_paid ?? 0), 0) }}
                                </div>
                                <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">
                                    {{ ucfirst((string) ($subscription->billing_cycle ?? $subscription->plan?->billing_cycle ?? 'monthly')) }}
                                </div>
                            </div>
                            <div class="space-y-1 text-[12px] text-slate-700">
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-slate-500">Started</span>
                                    <span class="font-medium">{{ $subscription->starts_at?->format('d M Y') ?? '-' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-slate-500">Expired On</span>
                                    <span class="font-semibold text-rose-700">{{ $subscription->ends_at?->format('d M Y') ?? '-' }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-slate-500">Reference</span>
                                    <span class="font-mono text-[11px]">{{ $subscription->billing_reference ?: 'N/A' }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            <button
                                type="button"
                                class="inline-flex items-center rounded-none bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:bg-slate-800"
                                data-renew-plan-id="{{ $subscription->subscription_plan_id }}"
                                data-renew-subscription-id="{{ $subscription->id }}"
                                data-renew-billing-cycle="{{ $subscription->billing_cycle ?? 'monthly' }}"
                            >
                                Renew Plan
                            </button>
                            @if(strtolower((string) $subscription->status) !== 'trialing')
                                <a href="{{ route('subscription.invoice', $subscription) }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-none border border-rose-300 bg-white px-4 py-2 text-sm font-semibold text-rose-700 transition hover:bg-rose-50">
                                    Invoice
                                </a>
                            @endif
                            <button type="button"
                                    class="history-toggle inline-flex items-center gap-1.5 rounded-none border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                                    data-history-id="{{ $subscription->id }}"
                                    data-plan-id="{{ $subscription->subscription_plan_id }}">
                                <svg class="history-chevron h-4 w-4 transition-transform duration-200" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                History
                            </button>
                        </div>

                        <div id="hist-{{ $subscription->id }}" class="hidden mt-3 pt-3 border-t border-rose-200">
                            <div class="flex items-center justify-between mb-3">
                                <h5 class="text-xs font-semibold uppercase tracking-wider text-rose-600">Purchase History</h5>
                                <div class="relative">
                                    <select class="hist-year-sel appearance-none text-xs rounded-lg border border-rose-200 bg-white py-1.5 pl-3 pr-7 font-semibold text-slate-700 outline-none cursor-pointer focus:ring-1 focus:ring-rose-400"></select>
                                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
                                    </div>
                                </div>
                            </div>
                            <div class="hist-content"></div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-5 text-sm text-slate-600">
                        No expired plans found for this company.
                    </div>
                @endforelse
            </div>
        </section>

        {{-- <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div>
                <h3 class="text-2xl font-bold text-slate-950">Plan Purchase History</h3>
                <p class="mt-1 text-sm text-slate-500">Complete plan history for the logged in company, including renewals and previous purchases.</p>
            </div>

            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[980px] text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Plan</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Cycle</th>
                            <th class="px-4 py-3">Amount</th>
                            <th class="px-4 py-3">Period</th>
                            <th class="px-4 py-3">Reference</th>
                            <th class="px-4 py-3">History</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($subscriptionHistory as $subscription)
                            <tr>
                                <td class="px-4 py-4">
                                    <div class="font-semibold text-slate-950">{{ $subscription->plan?->name ?? 'Subscription' }}</div>
                                    <div class="text-xs text-slate-500">{{ $subscription->payment_method ?: 'Manual' }}</div>
                                </td>
                                <td class="px-4 py-4">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold
                                        {{ $subscription->status === 'active' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                        {{ $subscription->status === 'trialing' ? 'bg-sky-100 text-sky-700' : '' }}
                                        {{ $subscription->status === 'expired' ? 'bg-rose-100 text-rose-700' : '' }}
                                        {{ !in_array($subscription->status, ['active', 'trialing', 'expired']) ? 'bg-slate-100 text-slate-700' : '' }}">
                                        {{ str($subscription->status)->headline()->toString() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 text-slate-700">{{ ucfirst((string) ($subscription->billing_cycle ?? 'monthly')) }}</td>
                                <td class="px-4 py-4 font-semibold text-slate-950">Rs.{{ number_format((float) ($subscription->amount_paid ?? 0), 0) }}</td>
                                <td class="px-4 py-4 text-slate-700">
                                    {{ $subscription->starts_at?->format('d M Y') ?? '-' }} to {{ $subscription->ends_at?->format('d M Y') ?? '-' }}
                                </td>
                                <td class="px-4 py-4 font-mono text-xs text-slate-700">{{ $subscription->billing_reference ?: 'N/A' }}</td>
                                <td class="px-4 py-4 text-xs text-slate-600">
                                    @if($subscription->renewedFrom)
                                        Renewed from {{ $subscription->renewedFrom->billing_reference ?: ('#' . $subscription->renewedFrom->id) }}
                                    @elseif($subscription->renewals->isNotEmpty())
                                        Renewed later
                                    @else
                                        Original purchase
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right">
                                    <div class="inline-flex items-center gap-2">
                                        @if(strtolower((string) $subscription->status) !== 'trialing')
                                            <a href="{{ route('subscription.invoice', $subscription) }}" target="_blank" rel="noopener" class="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                Invoice
                                            </a>
                                        @endif
                                        @if($subscription->status === 'expired')
                                            <button
                                                type="button"
                                                class="rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-800"
                                                data-renew-plan-id="{{ $subscription->subscription_plan_id }}"
                                                data-renew-subscription-id="{{ $subscription->id }}"
                                                data-renew-billing-cycle="{{ $subscription->billing_cycle ?? 'monthly' }}"
                                            >
                                                Renew
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-4 py-6 text-center text-sm text-slate-500">No plan history found yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section> --}}
    </div>

    <script>
    (function () {
        var changePlanUrl = "{{ route('subscription.change') }}";
        var csrfToken = "{{ csrf_token() }}";
        var planSelect = document.getElementById('plan-select');
        var billingCycleInputs = document.querySelectorAll('input[name="billing_cycle_ui"]');
        var billingCycleValue = '{{ $defaultBillingCycle }}';
        var defaultLicenseCount = {{ (int) $defaultLicenseCount }};
        var payButtonHtml = '<svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg> Continue to payment';
        var renewButtonHtml = 'Renew plan';
        var trialPanel = document.getElementById('trial-panel');
        var trialCheckbox = document.getElementById('trial-checkbox');
        var trialHint = document.getElementById('trial-hint');
        var autoRenewPanel = document.getElementById('auto-renew-panel');
        var autoRenewCheckbox = document.getElementById('auto-renew-checkbox');
        var recurringMandatory = @json((bool) $recurringMandatory);
        var renewButtons = document.querySelectorAll('[data-renew-plan-id]');
        var planCards = document.querySelectorAll('[data-plan-card-id]');
        var planCardPrices = document.querySelectorAll('[data-plan-card-price]');
        var planCardUnitPrices = document.querySelectorAll('[data-plan-card-unit-price]');
        var planCardDiscounts = document.querySelectorAll('[data-plan-card-discount]');
        var planCardTrialStatuses = document.querySelectorAll('[data-plan-card-trial-status]');
        var seatStepButtons = document.querySelectorAll('[data-seat-decrement], [data-seat-increment]');
        var seatValueNodes = document.querySelectorAll('[data-seat-value]');
        var planCardActions = document.querySelectorAll('[data-plan-card-action]');
        var planSeatInputs = document.querySelectorAll('[data-plan-seat-input]');
        var planSeatStepButtons = document.querySelectorAll('[data-seat-step]');
        var planCounts = {};

        planCards.forEach(function (card) {
            var planId = card.dataset.planCardId;
            var count = parseInt(card.dataset.seatCount || '1', 10);

            if (planId) {
                planCounts[planId] = Number.isFinite(count) && count > 0 ? count : 1;
            }
        });

        function setHiddenPlanId(value) {
            var input = document.getElementById('rzp_plan_id');
            if (input) {
                input.value = value || '';
            }
        }

        function setHiddenLicenseCount(value) {
            var input = document.getElementById('rzp_license_count');
            if (input) {
                input.value = value || '';
            }
        }

        function planOptionById(planId) {
            if (!planSelect || !planId) {
                return null;
            }

            return planSelect.querySelector('option[value="' + planId + '"]');
        }

        function planSeatInputForPlan(planId) {
            if (!planId) {
                return null;
            }

            return document.querySelector('[data-plan-seat-input="' + planId + '"]');
        }

        function selectedPlanSeatInput() {
            return planSeatInputForPlan(selectedPlanId());
        }

        function clampSeatCount(value, planId) {
            var count = parseInt(value, 10);

            if (!Number.isFinite(count) || count < 1) {
                count = 1;
            }

            var option = planOptionById(planId) || document.querySelector('[data-plan-card-id="' + planId + '"]');
            var staffLimit = parseInt(option ? (option.dataset.staffLimit || '') : '', 10);

            if (Number.isFinite(staffLimit) && staffLimit > 0) {
                return Math.min(count, staffLimit);
            }

            return Math.min(count, 999);
        }

        function selectedPlanId() {
            return planSelect ? (planSelect.value || '') : '';
        }

        function planCount(planId) {
            var count = parseInt(planCounts[planId] || 1, 10);
            return Number.isFinite(count) && count > 0 ? Math.min(count, 999) : 1;
        }

        function syncSeatCountUI() {
            setHiddenPlanId(selectedPlanId());
            setHiddenLicenseCount(planCount(selectedPlanId()));

            seatValueNodes.forEach(function (node) {
                node.textContent = planCount(node.dataset.seatPlanId);
            });
        }

        function updateSeatCount(planId, nextCount) {
            if (!planId) {
                return;
            }

            planCounts[planId] = clampSeatCount(nextCount, planId);

            var card = document.querySelector('[data-plan-card-id="' + planId + '"]');
            if (card) {
                card.dataset.seatCount = String(planCounts[planId]);
            }

            syncSeatCountUI();
            refreshPlanSummary();
        }

        function setHiddenBillingCycle(value) {
            var input = document.getElementById('rzp_billing_cycle');
            if (input) {
                input.value = value || 'monthly';
            }
        }

        function setHiddenRenewSubscriptionId(value) {
            var input = document.getElementById('rzp_renew_subscription_id');
            if (input) {
                input.value = value || '';
            }
        }

        function setHiddenLicenseCount(value) {
            var input = document.getElementById('rzp_license_count');
            if (input) {
                input.value = value || '';
            }
        }

        function syncPlanSeatInput(planId, value) {
            var normalized = clampSeatCount(value, planId);
            var input = planSeatInputForPlan(planId);

            if (input) {
                input.value = String(normalized);
            }

            return normalized;
        }

        function selectedPlanOption() {
            if (!planSelect || !planSelect.value) {
                return null;
            }

            return planOptionById(planSelect.value) || null;
        }

        function selectedPlanSeatCount(planId) {
            var targetPlanId = planId || selectedPlanId();

            return planCount(targetPlanId || '') || defaultLicenseCount;
        }

        function selectedPlanAmount(planId) {
            var option = planOptionById(planId || selectedPlanId());

            if (!option) {
                return 0;
            }

            var cycleKey = billingCycleLabel(billingCycleValue);
            var price = cycleKey === 'yearly' ? option.dataset.yearlyPrice : option.dataset.monthlyPrice;

            return Number(price || 0) * selectedPlanSeatCount(option.value);
        }

        function billingCycleLabel(cycle) {
            return cycle === 'yearly' ? 'yearly' : 'monthly';
        }

        function currencyLabel(amount) {
            return 'Rs.' + Number(amount || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
        }

        function refreshBillingCycleOptions() {
            billingCycleInputs.forEach(function (input) {
                var label = input.closest('.billing-cycle-option');
                var isActive = input.value === billingCycleValue;

                if (label) {
                    label.classList.toggle('border-blue-600', isActive);
                    label.classList.toggle('bg-blue-600', isActive);
                    label.classList.toggle('text-white', isActive);
                    label.classList.toggle('shadow-sm', isActive);
                    label.classList.toggle('border-cyan-200', !isActive);
                    label.classList.toggle('bg-white', !isActive);
                    label.classList.toggle('text-slate-700', !isActive);
                }
            });

            setHiddenBillingCycle(billingCycleValue);
        }

        function refreshLicenseCountBounds() {
            planCards.forEach(function (card) {
                var planId = card.dataset.planCardId;
                var normalized = clampSeatCount(planCount(planId), planId);

                planCounts[planId] = normalized;
                card.dataset.seatCount = String(normalized);
            });
        }

        function refreshPlanCardPrices() {
            var cycleKey = billingCycleLabel(billingCycleValue);

            Array.prototype.forEach.call(planSelect ? planSelect.options : [], function (opt) {
                if (!opt.value) {
                    return;
                }

                var planId = opt.value;
                var planCountForCard = planCount(planId);
                var planPrice = cycleKey === 'yearly' ? opt.dataset.yearlyPrice : opt.dataset.monthlyPrice;
                opt.textContent = opt.dataset.name + ' - ' + currencyLabel(Number(planPrice || 0) * planCountForCard) + ' / ' + cycleKey + ' / ' + planCountForCard + ' staff';
            });

            planCardPrices.forEach(function (node) {
                var matchingOption = planOptionById(node.dataset.planCardPrice);

                if (!matchingOption) {
                    return;
                }

                var unitAmount = cycleKey === 'yearly' ? matchingOption.dataset.yearlyPrice : matchingOption.dataset.monthlyPrice;
                node.textContent = currencyLabel(Number(unitAmount || 0) * planCount(node.dataset.planCardPrice));
            });

            planCardDiscounts.forEach(function (node) {
                var matchingOption = planOptionById(node.dataset.planCardDiscount);
                var discountLabel = cycleKey === 'yearly' ? (matchingOption ? matchingOption.dataset.yearlyDiscountLabel : '') : '';

                node.textContent = discountLabel || '';
                node.classList.toggle('hidden', !discountLabel);
            });

            planCardTrialStatuses.forEach(function (node) {
                node.classList.toggle('hidden', cycleKey === 'yearly');
            });

            planCardUnitPrices.forEach(function (node) {
                var matchingOption = planOptionById(node.dataset.planCardUnitPrice);

                if (!matchingOption) {
                    return;
                }

                var unitAmount = cycleKey === 'yearly' ? matchingOption.dataset.yearlyPrice : matchingOption.dataset.monthlyPrice;
                node.textContent = currencyLabel(unitAmount) + ' / staff / ' + cycleKey;
            });
        }

        function refreshPlanSummary() {
            var cycleKey = billingCycleLabel(billingCycleValue);
            var option = selectedPlanOption();
            var hint = document.getElementById('plan-price-hint');
            var changeButton = document.getElementById('rzp-change-btn');
            var matchingExpiredSubscription = option
                ? document.querySelector('[data-renew-plan-id="' + option.value + '"]')
                : null;

            refreshLicenseCountBounds();
            refreshPlanCardPrices();

            var selectedCount = selectedPlanSeatCount();
            var selectedAmount = selectedPlanAmount();
            setHiddenLicenseCount(selectedCount);

            Array.prototype.forEach.call(planSelect.options, function (opt) {
                if (!opt.value) {
                    return;
                }

                var planId = opt.value;
                var planCountForCard = planCount(planId);
                var planPrice = cycleKey === 'yearly' ? opt.dataset.yearlyPrice : opt.dataset.monthlyPrice;
                opt.textContent = opt.dataset.name + ' - ' + currencyLabel(Number(planPrice || 0) * planCountForCard) + ' / ' + cycleKey + ' / ' + planCountForCard + ' staff';
            });

            planCardPrices.forEach(function (node) {
                var matchingOption = planSelect ? planSelect.querySelector('option[value="' + node.dataset.planCardPrice + '"]') : null;

                if (!matchingOption) {
                    return;
                }

                var unitAmount = cycleKey === 'yearly' ? matchingOption.dataset.yearlyPrice : matchingOption.dataset.monthlyPrice;
                node.textContent = currencyLabel(Number(unitAmount || 0) * planCount(node.dataset.planCardPrice));
            });

            planCardDiscounts.forEach(function (node) {
                var matchingOption = planSelect ? planSelect.querySelector('option[value="' + node.dataset.planCardDiscount + '"]') : null;
                var discountLabel = cycleKey === 'yearly' ? (matchingOption ? matchingOption.dataset.yearlyDiscountLabel : '') : '';

                node.textContent = discountLabel || '';
                node.classList.toggle('hidden', !discountLabel);
            });

            planCardTrialStatuses.forEach(function (node) {
                node.classList.toggle('hidden', cycleKey === 'yearly');
            });

            planCardUnitPrices.forEach(function (node) {
                var matchingOption = planSelect ? planSelect.querySelector('option[value="' + node.dataset.planCardUnitPrice + '"]') : null;

                if (!matchingOption) {
                    return;
                }

                var unitAmount = cycleKey === 'yearly' ? matchingOption.dataset.yearlyPrice : matchingOption.dataset.monthlyPrice;
                node.textContent = currencyLabel(unitAmount) + ' / staff / ' + cycleKey;
            });

            if (hint) {
                if (!option) {
                    hint.textContent = 'Choose any plan card to continue to payment.';
                } else {
                    var price = cycleKey === 'yearly' ? option.dataset.yearlyPrice : option.dataset.monthlyPrice;
                    var selectedCount = selectedPlanSeatCount(option.value);
                    hint.textContent = option.dataset.name + ' will be billed at ' + currencyLabel(Number(price || 0) * selectedCount) + ' on a ' + cycleKey + ' cycle for ' + selectedCount + ' staff.'
                        + (matchingExpiredSubscription ? ' This will renew an expired purchase of the same plan.' : '');
                }
            }

            if (changeButton) {
                changeButton.innerHTML = matchingExpiredSubscription ? renewButtonHtml : payButtonHtml;
                changeButton.disabled = !option;
            }

            syncSeatCountUI();
            refreshTrialPanel();
            if (autoRenewPanel) {
                var trialSelected = trialCheckbox && trialCheckbox.checked && !trialCheckbox.disabled;
                autoRenewPanel.classList.toggle('hidden', !option || trialSelected);
            }
            if (changeButton && trialCheckbox && trialCheckbox.checked && !trialCheckbox.disabled) {
                changeButton.textContent = 'Start 15-day free trial';
            }
            refreshSelectedPlanCard();
        }

        function refreshTrialPanel() {
            var option = selectedPlanOption();
            if (!trialPanel || !trialCheckbox || !trialHint) return;

            if (!option) {
                trialPanel.classList.add('hidden');
                trialCheckbox.checked = false;
                trialCheckbox.disabled = true;
                trialHint.textContent = '';
                return;
            }

            if (billingCycleValue !== 'monthly') {
                trialPanel.classList.add('hidden');
                trialCheckbox.checked = false;
                return;
            }

            if (option.dataset.hasFreeTrial !== '1') {
                trialPanel.classList.add('hidden');
                trialCheckbox.checked = false;
                return;
            }

            trialPanel.classList.remove('hidden');
            trialCheckbox.disabled = option.dataset.trialEligible !== '1';
            if (trialCheckbox.disabled) {
                trialCheckbox.checked = false;
            }

            trialHint.textContent = option.dataset.trialEligible === '1'
                ? 'This company can use a free trial only once.'
                : (option.dataset.trialReason || 'Free trial is not available on the selected subscription plan.');
        }

        if (trialCheckbox) {
            trialCheckbox.addEventListener('change', refreshPlanSummary);
        }

        function refreshSelectedPlanCard() {
            var selectedValue = planSelect ? planSelect.value : '';

            planCards.forEach(function (card) {
                var isSelected = card.dataset.planCardId === selectedValue;
                card.classList.toggle('border-blue-600', isSelected);
                card.classList.toggle('ring-1', isSelected);
                card.classList.toggle('ring-blue-200', isSelected);
                card.classList.toggle('shadow-[0_18px_50px_rgba(244,63,94,0.12)]', isSelected);
                card.classList.toggle('border-cyan-200', !isSelected);
            });
        }

        function setSelectedPlan(planId) {
            if (!planSelect) {
                return;
            }

            planSelect.value = planId || '';

            if (planId) {
                syncPlanSeatInput(planId, planCount(planId));
            } else {
                setHiddenPlanId('');
                setHiddenLicenseCount(defaultLicenseCount);
            }

            refreshPlanSummary();
        }

        seatStepButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                var step = button.hasAttribute('data-seat-increment') ? 1 : -1;
                var card = button.closest('[data-plan-card-id]');
                var planId = card ? card.dataset.planCardId : '';

                updateSeatCount(planId, planCount(planId) + step);
            });
        });

        seatValueNodes.forEach(function (node) {
            node.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        });

        billingCycleInputs.forEach(function (input) {
            input.addEventListener('change', function () {
                billingCycleValue = input.value === 'yearly' ? 'yearly' : 'monthly';
                setHiddenBillingCycle(billingCycleValue);
                refreshBillingCycleOptions();
                refreshPlanSummary();
            });
        });

        if (planSelect) {
            planSelect.addEventListener('change', refreshPlanSummary);
        }

        planCards.forEach(function (card) {
            card.addEventListener('click', function () {
                setSelectedPlan(card.dataset.planCardId || '');
            });

            card.addEventListener('keydown', function (event) {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    setSelectedPlan(card.dataset.planCardId || '');
                }
            });
        });

        planCardActions.forEach(function (button) {
            button.addEventListener('click', function () {
                setSelectedPlan(button.getAttribute('data-plan-card-action') || '');
            });
        });

        planSeatInputs.forEach(function (input) {
            input.addEventListener('input', function () {
                var planId = input.getAttribute('data-plan-seat-input');
                var planOption = planOptionById(planId);
                var count = normalizeSeatCount(input.value, planOption);

                input.value = String(count);
                if (planId && planSelect && planSelect.value !== planId) {
                    setSelectedPlan(planId);
                } else {
                    refreshPlanSummary();
                }
            });

            input.addEventListener('change', function () {
                input.dispatchEvent(new Event('input'));
            });
        });

        planSeatStepButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var step = parseInt(button.getAttribute('data-seat-step') || '0', 10) || 0;
                var planId = button.getAttribute('data-plan-id') || '';
                var seatInput = planSeatInputForPlan(planId) || selectedPlanSeatInput();
                var current = normalizeSeatCount(seatInput ? seatInput.value : defaultLicenseCount, planOptionById(planId || (planSelect ? planSelect.value : '')));
                var nextCount = current + step;
                var option = planOptionById(planId || (planSelect ? planSelect.value : ''));

                if (option) {
                    var maxUsers = parseInt(option.dataset.staffLimit || '', 10);
                    if (Number.isFinite(maxUsers) && maxUsers > 0) {
                        nextCount = Math.min(nextCount, maxUsers);
                    }
                }

                nextCount = Math.max(1, nextCount);

                if (planId) {
                    syncPlanSeatInput(planId, nextCount);
                    setSelectedPlan(planId);
                } else {
                    syncPlanSeatInput(planSelect ? planSelect.value : '', nextCount);
                    refreshPlanSummary();
                }
            });
        });

        refreshBillingCycleOptions();
        syncSeatCountUI();
        refreshPlanSummary();

        function openRazorpay(planId, renewSubscriptionId, preferredCycle) {
            var form = document.getElementById('rzp-process-form');
            var resolvedPlanId = planId || selectedPlanId();
            var option = planOptionById(resolvedPlanId);
            var effectiveBillingCycle = preferredCycle || billingCycleValue;

            if (!form || !resolvedPlanId || !option) {
                alert('Please select a plan.');
                return;
            }

            setSelectedPlan(resolvedPlanId);
            setHiddenPlanId(resolvedPlanId);
            setHiddenBillingCycle(effectiveBillingCycle);
            setHiddenLicenseCount(selectedPlanSeatCount(resolvedPlanId));
            setHiddenRenewSubscriptionId(renewSubscriptionId || '');

            if (trialCheckbox) {
                var trialAllowed = option.dataset.hasFreeTrial === '1'
                    && option.dataset.trialEligible === '1'
                    && billingCycleLabel(effectiveBillingCycle) === 'monthly';

                document.getElementById('rzp_is_trial').value = trialCheckbox.checked && trialAllowed ? '1' : '0';
            }

            document.getElementById('rzp_auto_renew').value = autoRenewPanel
                && (recurringMandatory || (autoRenewCheckbox && autoRenewCheckbox.checked)) ? '1' : '0';

            document.getElementById('rzp_billing_ref').value = '';
            document.getElementById('rzp_order_id').value = '';
            document.getElementById('rzp_payment_id').value = '';
            document.getElementById('rzp_signature').value = '';
            form.setAttribute('action', changePlanUrl);
            form.submit();
        }

        var changeButton = document.getElementById('rzp-change-btn');
        if (changeButton) {
            changeButton.addEventListener('click', function () {
                var planId = planSelect ? planSelect.value : '';
                if (!planId) { alert('Please select a plan.'); return; }
                var matchingExpiredSubscription = document.querySelector('[data-renew-plan-id="' + planId + '"]');
                openRazorpay(planId, matchingExpiredSubscription ? matchingExpiredSubscription.getAttribute('data-renew-subscription-id') : '', billingCycleValue);
            });
        }

        renewButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                var planId = button.getAttribute('data-renew-plan-id');
                var renewSubscriptionId = button.getAttribute('data-renew-subscription-id');
                var renewBillingCycle = button.getAttribute('data-renew-billing-cycle') || 'monthly';
                openRazorpay(planId, renewSubscriptionId, renewBillingCycle);
            });
        });
    })();

    // ──── Purchase History Toggle ────
    (function () {
        var historyByPlan = {!! json_encode($historyByPlan) !!};
        var currentYear = {{ now()->year }};

        function getYearsForPlan(planId) {
            var records = historyByPlan[planId] || [];
            var seen = {};
            records.forEach(function (r) { if (r.starts_year) seen[r.starts_year] = true; });
            return Object.keys(seen).map(Number).sort(function (a, b) { return b - a; });
        }

        function statusBadge(status) {
            var cls = status === 'active' ? 'bg-emerald-100 text-emerald-700'
                    : status === 'trialing' ? 'bg-sky-100 text-sky-700'
                    : status === 'expired'  ? 'bg-rose-100 text-rose-700'
                    : 'bg-slate-100 text-slate-700';
            return '<span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold ' + cls + '">'
                + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
        }

        function renderHistory(container, planId, year) {
            var records = (historyByPlan[planId] || []).filter(function (r) { return r.starts_year == year; });
            if (!records.length) {
                container.innerHTML = '<p class="py-4 text-center text-xs text-slate-400">No history available for ' + year + '.</p>';
                return;
            }
            var rows = records.map(function (r) {
                var trialBadge = r.is_trial ? ' <span class="ml-1 rounded-full bg-emerald-50 px-1.5 py-0.5 text-[9px] font-semibold text-emerald-600">Trial</span>' : '';
                return '<tr class="border-t border-slate-100">'
                    + '<td class="px-3 py-2 font-medium text-slate-800">' + r.plan_name + trialBadge + '</td>'
                    + '<td class="px-3 py-2">' + statusBadge(r.status) + '</td>'
                    + '<td class="px-3 py-2 text-[10px] text-slate-500">' + r.billing_cycle + '</td>'
                    + '<td class="px-3 py-2 font-semibold text-slate-900">Rs.' + Number(r.amount_paid).toLocaleString('en-IN', { maximumFractionDigits: 0 }) + '</td>'
                    + '<td class="px-3 py-2 text-slate-600">' + (r.starts_at || '—') + ' → ' + (r.ends_at || '—') + '</td>'
                    + '<td class="px-3 py-2 font-mono text-[10px] text-slate-500">' + r.billing_reference + '</td>'
                    + '</tr>';
            }).join('');
            container.innerHTML = '<div class="overflow-x-auto rounded-xl border border-slate-100">'
                + '<table class="w-full min-w-[560px] text-xs">'
                + '<thead class="bg-slate-50 text-left"><tr>'
                + '<th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Plan</th>'
                + '<th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Status</th>'
                + '<th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Cycle</th>'
                + '<th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Amount</th>'
                + '<th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Period</th>'
                + '<th class="px-3 py-2 text-[10px] font-semibold uppercase tracking-wider text-slate-400">Reference</th>'
                + '</tr></thead>'
                + '<tbody>' + rows + '</tbody>'
                + '</table></div>';
        }

        document.querySelectorAll('.history-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var histId  = btn.getAttribute('data-history-id');
                var planId  = btn.getAttribute('data-plan-id');
                var panel   = document.getElementById('hist-' + histId);
                if (!panel) return;

                var opening = panel.classList.contains('hidden');
                panel.classList.toggle('hidden', !opening);

                var chevron = btn.querySelector('.history-chevron');
                if (chevron) chevron.style.transform = opening ? 'rotate(180deg)' : '';

                if (!opening) return;

                var yearSel = panel.querySelector('.hist-year-sel');
                var content = panel.querySelector('.hist-content');
                if (!yearSel || !content || yearSel.options.length > 0) return;

                var years = getYearsForPlan(planId);
                if (!years.length) {
                    yearSel.style.display = 'none';
                    content.innerHTML = '<p class="py-4 text-center text-xs text-slate-400">No history available.</p>';
                    return;
                }

                years.forEach(function (y) {
                    var opt = document.createElement('option');
                    opt.value = y;
                    opt.textContent = y;
                    yearSel.appendChild(opt);
                });

                var defaultYear = years.indexOf(currentYear) !== -1 ? currentYear : years[0];
                yearSel.value = defaultYear;
                renderHistory(content, planId, defaultYear);

                yearSel.addEventListener('change', function () {
                    renderHistory(content, planId, parseInt(yearSel.value, 10));
                });
            });
        });
    })();
    </script>
    </div>
</x-app-layout>
