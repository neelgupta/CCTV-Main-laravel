<x-guest-layout>
    @unless($order['demo'] ?? false)
        <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    @endunless

    @php
        $displayPrice = !empty($isTrial) ? 'Free' : 'Rs.' . number_format((float) $price, ((float) $price - floor((float) $price)) > 0 ? 2 : 0);
        $billingLabel = ucfirst($billingCycle);
    @endphp

    <div id="subscription-payment-page" class="ww-auth-stage">
        <div class="ww-auth-orb -left-16 top-8 h-72 w-72 bg-rose-300/40"></div>
        <div class="ww-auth-orb bottom-0 right-0 h-96 w-96 bg-pink-300/30"></div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-4 py-6 sm:px-6 lg:px-8">
            <div class="ww-card-frame">
                <section class="ww-auth-panel w-full mx-auto text-left">
                    <div class="subscription-payment-header">
                        <div>
                            <span class="subscription-payment-kicker">Secure checkout</span>
                            <h2 class="ww-heading">{{ !empty($isTrial) ? 'Activate free trial' : 'Complete payment' }}</h2>
                            <p class="ww-subheading">{{ !empty($isTrial) ? 'Review your selected plan and start the ' . config('subscription.trial_days', 15) . '-day trial on this workspace.' : 'Review your plan details below and finish setup with a secure payment confirmation.' }}</p>
                        </div>
                    </div>

                    <div class="mt-8 grid gap-5 xl:grid-cols-[0.95fr_1.05fr]">
                        <div class="ww-card subscription-payment-hero text-slate-950">
                            <div class="subscription-payment-hero-top p-6 text-white">
                                <div class="flex items-center gap-4">
                                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-xl font-black shadow-[0_14px_28px_rgba(255,255,255,0.08)]">
                                        {{ strtoupper(substr($company->name, 0, 1)) }}
                                    </div>
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.22em] text-rose-100/80">Workspace</p>
                                        <p class="mt-1 text-2xl font-black">
                                            {{ $company->name }}
                                            <span class="text-base font-semibold text-rose-100/80">/ {{ $licenseCount ?? 1 }} staff</span>
                                        </p>
                                    </div>
                                </div>

                                <div class="subscription-payment-amount mt-6 rounded-[24px] px-5 py-4 text-slate-950">
                                    <p class="text-sm font-semibold text-slate-500">Amount due</p>
                                    <div class="mt-2 text-4xl font-black">{{ $displayPrice }}</div>
                                    <p class="mt-2 text-sm text-slate-500">{{ !empty($isTrial) ? config('subscription.trial_days', 15) . '-day access on the selected plan' : 'For the selected ' . strtolower($billingLabel) . ' plan cycle' }}</p>
                                </div>

                                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                                    <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-rose-100/70">Plan</p>
                                        <p class="mt-1 text-sm font-semibold text-white">{{ $plan->name }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-white/10 bg-white/10 px-4 py-3">
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-rose-100/70">Billing</p>
                                        <p class="mt-1 text-sm font-semibold text-white">{{ $billingLabel }}</p>
                                    </div>
                                </div>

                                <div class="mt-5 flex items-center gap-2 text-sm text-rose-100/80">
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                                    </svg>
                                    <span>{{ !empty($isTrial) ? 'Activation starts instantly after confirmation.' : 'Secured by Razorpay for trusted payment processing.' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="ww-card subscription-payment-summary">
                            <div class="flex items-center justify-between gap-4">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-500">Order summary</p>
                                    <h3 class="mt-2 text-2xl font-black tracking-tight text-slate-950">Review before you continue</h3>
                                </div>
                                <span class="ww-pill {{ !empty($isTrial) ? 'ww-pill-emerald' : 'ww-pill-blue' }}">
                                    {{ !empty($isTrial) ? 'Trial' : 'Secure Payment' }}
                                </span>
                            </div>

                            <div class="mt-6 divide-y divide-slate-100 rounded-3xl border border-slate-200 bg-slate-50/70 px-5">
                                <div class="flex items-center justify-between py-4">
                                    <span class="text-sm text-slate-500">Plan</span>
                                    <span class="text-sm font-semibold text-slate-950">{{ $plan->name }}</span>
                                </div>
                                <div class="flex items-center justify-between py-4">
                                    <span class="text-sm text-slate-500">Billing cycle</span>
                                    <span class="text-sm font-semibold text-slate-950">{{ $billingLabel }}</span>
                                </div>
                                <div class="flex items-center justify-between py-4">
                                    <span class="text-sm text-slate-500">Staff</span>
                                    <span class="text-sm font-semibold text-slate-950">{{ $licenseCount ?? 1 }}</span>
                                </div>
                                @if (!empty($isTrial))
                                    <div class="flex items-center justify-between py-4">
                                        <span class="text-sm text-slate-500">Access type</span>
                                        <span class="text-sm font-semibold text-emerald-700">{{ config('subscription.trial_days', 15) }}-Day Free Trial</span>
                                    </div>
                                    <div class="flex items-center justify-between py-4">
                                        <span class="text-sm text-slate-500">Total Amount</span>
                                        <span class="text-sm font-semibold text-slate-950">Rs.0</span>
                                    </div>
                                @else
                                    <div class="flex items-center justify-between py-4">
                                        <span class="text-sm text-slate-500">Plan Amount</span>
                                        <span class="text-sm font-semibold text-slate-950">Rs.{{ number_format((float) ($subtotal ?? $price), 2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-4">
                                        <span class="text-sm text-slate-500">GST ({{ $gstRate ?? 18 }}%)</span>
                                        <span class="text-sm font-semibold text-slate-950">Rs.{{ number_format((float) ($gstAmount ?? 0), 2) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between py-4 font-bold text-slate-950">
                                        <span class="text-sm font-bold">Total Amount Due</span>
                                        <span class="text-base font-black">Rs.{{ number_format((float) $price, 2) }}</span>
                                    </div>
                                @endif
                                @if (!($order['demo'] ?? false))
                                    <div class="flex items-center justify-between py-4">
                                        <span class="text-sm text-slate-500">Order ID</span>
                                        <span class="text-xs font-mono text-slate-400">{{ $order['id'] ?? '' }}</span>
                                    </div>
                                @endif
                            </div>

                            <div class="mt-6 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="ww-check-list">
                                    <div class="ww-check-item">
                                        <span class="ww-check-icon">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.2 7.2a1 1 0 01-1.415 0l-3-3a1 1 0 011.414-1.42l2.293 2.294 6.493-6.494a1 1 0 011.415 0z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                        <span>{{ !empty($isTrial) ? 'No payment required to activate the trial.' : 'Payment is only taken for the plan and cycle shown above.' }}</span>
                                    </div>
                                    <div class="ww-check-item">
                                        <span class="ww-check-icon">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.2 7.2a1 1 0 01-1.415 0l-3-3a1 1 0 011.414-1.42l2.293 2.294 6.493-6.494a1 1 0 011.415 0z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                        <span>Your company admin account is activated immediately after successful confirmation.</span>
                                    </div>
                                    <div class="ww-check-item">
                                        <span class="ww-check-icon">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M16.704 5.29a1 1 0 010 1.42l-7.2 7.2a1 1 0 01-1.415 0l-3-3a1 1 0 011.414-1.42l2.293 2.294 6.493-6.494a1 1 0 011.415 0z" clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                        <span>The redesigned flow preserves the same branding, spacing, and hierarchy from sign-up through activation.</span>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8">
                                @if ($order['demo'] ?? false)
                                    <form method="POST" action="{{ route('onboarding.payment.finalize') }}" class="space-y-4">
                                        @csrf
                                        <input type="hidden" name="razorpay_order_id" value="{{ $order['id'] ?? '' }}">
                                        <input type="hidden" name="razorpay_payment_id" value="demo_payment_{{ \Illuminate\Support\Str::lower(\Illuminate\Support\Str::random(10)) }}">
                                        <input type="hidden" name="razorpay_signature" value="demo-signature">

                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <a href="{{ route('onboarding.step4') }}" class="ww-secondary-btn">Back</a>
                                            <button type="submit" class="ww-primary-btn">
                                                {{ !empty($isTrial) ? 'Start free trial' : ((float) $price <= 0 ? 'Activate free plan' : 'Complete activation') }}
                                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M3.25 10a.75.75 0 01.75-.75h10.19L10.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06l3.97-3.97H4A.75.75 0 013.25 10z" clip-rule="evenodd" />
                                                </svg>
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <form id="razorpay-payment-form" method="POST" action="{{ route('onboarding.payment.finalize') }}">
                                        @csrf
                                        <input type="hidden" id="rzp_order_id" name="razorpay_order_id" value="{{ $order['id'] ?? '' }}">
                                        <input type="hidden" id="rzp_payment_id" name="razorpay_payment_id" value="">
                                        <input type="hidden" id="rzp_signature" name="razorpay_signature" value="">
                                    </form>

                                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                        <a href="{{ route('onboarding.step4') }}" class="ww-secondary-btn">Back</a>
                                        <button id="rzp-pay-btn" type="button" class="ww-primary-btn">
                                            Pay {{ 'Rs.' . number_format((float) $price, ((float) $price - floor((float) $price)) > 0 ? 2 : 0) }}
                                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd" d="M3.25 10a.75.75 0 01.75-.75h10.19L10.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06l3.97-3.97H4A.75.75 0 013.25 10z" clip-rule="evenodd" />
                                            </svg>
                                        </button>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @unless($order['demo'] ?? false)
        @push('scripts')
            <script>
                document.getElementById('rzp-pay-btn')?.addEventListener('click', function () {
                    const options = {
                        key: @json(config('services.razorpay.key_id')),
                        amount: {{ (int) ($order['amount'] ?? 0) }},
                        currency: @json($order['currency'] ?? 'INR'),
                        name: @json($company->name),
                        description: @json($plan->name . ' Plan'),
                        order_id: @json($order['id'] ?? ''),
                        handler(response) {
                            document.getElementById('rzp_payment_id').value = response.razorpay_payment_id;
                            document.getElementById('rzp_order_id').value = response.razorpay_order_id;
                            document.getElementById('rzp_signature').value = response.razorpay_signature;
                            document.getElementById('razorpay-payment-form').submit();
                        },
                        prefill: {
                            name: @json($company->name),
                            email: @json(auth()->user()->email ?? ''),
                            contact: @json($company->mobile_number ?? $company->phone_number ?? ''),
                        },
                        notes: {
                            company: @json($company->name),
                            plan: @json($plan->name),
                        },
                        theme: { color: '#d63384' },
                    };

                    const razorpay = new Razorpay(options);
                    razorpay.on('payment.failed', function (response) {
                        window.alert('Payment failed: ' + response.error.description);
                    });
                    razorpay.open();
                });
            </script>
        @endpush
    @endunless
</x-guest-layout>
