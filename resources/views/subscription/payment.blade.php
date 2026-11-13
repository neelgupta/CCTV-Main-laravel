<x-app-layout>
    @php($isRecurring = ($mode ?? null) === 'subscription-recurring')
    @php($payAmount = (float) ($price ?? $plan->price))
    @php($payAmountLabel = number_format($payAmount, fmod($payAmount, 1.0) == 0.0 ? 0 : 2))
    @php($cycleWord = ($billingCycle ?? $plan->billing_cycle) === 'yearly' ? 'year' : 'month')
    <div id="subscription-payment-page">
    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">Payment Details</h2>
            <p class="mt-1 text-sm text-slate-500">Complete payment to activate the selected subscription plan.</p>
        </div>
    </x-slot>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    <div class="mx-auto max-w-4xl">
        <div class="rounded-[32px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-[28px] bg-[linear-gradient(160deg,#5b4cf0_0%,#4338ca_100%)] p-5 text-white">
                    <div class="flex items-center gap-3">
                        <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/20 bg-white/10 text-xl font-black">
                            {{ strtoupper(substr($company->name, 0, 1)) }}
                        </div>
                        <div class="text-2xl font-black">{{ $company->name }}</div>
                    </div>

                    <div class="mt-5 rounded-2xl bg-white/95 p-4 text-slate-950">
                        <div class="text-sm font-medium text-slate-500">Price Summary</div>
                        <div class="mt-1 text-4xl font-black">Rs.{{ $payAmountLabel }}</div>
                    </div>

                    <div class="mt-3 rounded-2xl border border-white/20 bg-white/10 px-4 py-3 text-sm">
                        {{ $company->mobile_number ?: ($company->phone_number ?: $company->email) }}
                    </div>

                    <div class="mt-6 space-y-2">
                        <div class="text-xs font-semibold uppercase tracking-widest text-white/60">Plan: {{ $plan->name }}</div>
                        <div class="text-xs text-white/60">Billing: {{ ucfirst($billingCycle ?? $plan->billing_cycle) }}</div>
                    </div>

                    <div class="mt-10 flex items-center gap-2 text-xs text-white/60">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                        Secured by Razorpay
                    </div>
                </div>

                <div class="flex flex-col justify-between rounded-[28px] border border-slate-200 bg-slate-50 p-6">
                    <div>
                        <div class="text-lg font-bold text-slate-950">Order Summary</div>
                        <div class="mt-4 divide-y divide-slate-200">
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm text-slate-500">Plan</span>
                                <span class="text-sm font-semibold text-slate-950">{{ $plan->name }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm text-slate-500">Billing Cycle</span>
                                <span class="text-sm font-semibold text-slate-950">{{ ucfirst($billingCycle ?? $plan->billing_cycle) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm text-slate-500">Plan Amount</span>
                                <span class="text-sm font-semibold text-slate-950">Rs.{{ number_format((float) ($subtotal ?? $price), 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm text-slate-500">GST ({{ $gstRate ?? 18 }}%)</span>
                                <span class="text-sm font-semibold text-slate-950">Rs.{{ number_format((float) ($gstAmount ?? 0), 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3 bg-slate-100/70 px-2 rounded-xl">
                                <span class="text-sm font-bold text-slate-950">Total Amount Due</span>
                                <span class="text-base font-black text-slate-950">Rs.{{ number_format((float) ($price ?? $plan->price), 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm text-slate-500">Users</span>
                                <span class="text-sm font-semibold text-slate-950">{{ $licenseCount ?? 1 }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm text-slate-500">Reference</span>
                                <span class="text-xs font-mono text-slate-400">{{ $reference }}</span>
                            </div>
                            <div class="flex items-center justify-between py-3">
                                <span class="text-sm text-slate-500">{{ $isRecurring ? 'Subscription ID' : 'Order ID' }}</span>
                                <span class="text-xs font-mono text-slate-400">{{ $order['id'] ?? '' }}</span>
                            </div>
                        </div>

                        @if($isRecurring)
                            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
                                <div class="font-semibold">Auto-renew is on</div>
                                <p class="mt-1 text-xs leading-relaxed text-emerald-800">
                                    You pay Rs.{{ number_format((float) ($price ?? $plan->price), 2) }} now, then the same amount is charged automatically every {{ $cycleWord }} through Razorpay until you cancel.
                                    You can cancel auto-renew any time from the Subscription page; your plan stays active until the end of the paid period.
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="mt-6">
                        <form id="razorpay-payment-form" method="POST" action="{{ $successRoute }}">
                            @csrf
                            <input type="hidden" name="subscription_plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="billing_cycle" value="{{ $billingCycle ?? $plan->billing_cycle }}">
                            <input type="hidden" name="billing_reference" value="{{ $reference }}">
                            <input type="hidden" name="license_count" value="{{ $licenseCount ?? '' }}">
                            <input type="hidden" name="renew_subscription_id" value="{{ $renewSubscriptionId ?? '' }}">
                            @if($isRecurring)
                                <input type="hidden" id="rzp_subscription_id" name="razorpay_subscription_id" value="{{ $order['id'] ?? '' }}">
                            @else
                                <input type="hidden" id="rzp_order_id" name="razorpay_order_id" value="{{ $order['id'] ?? '' }}">
                            @endif
                            <input type="hidden" id="rzp_payment_id" name="razorpay_payment_id" value="">
                            <input type="hidden" id="rzp_signature" name="razorpay_signature" value="">
                        </form>
                        <div class="flex items-center justify-between gap-3">
                            <a href="{{ $cancelRoute }}" class="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-100">Cancel</a>
                            <button id="rzp-pay-btn" type="button" class="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                                {{ $isRecurring ? 'Pay & enable auto-renew' : 'Pay' }} Rs.{{ $payAmountLabel }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.getElementById('rzp-pay-btn').addEventListener('click', function () {
        var isRecurring = @json($isRecurring);
        var options = {
            key: "{{ config('services.razorpay.key_id') }}",
            name: "{{ addslashes($company->name) }}",
            description: "{{ addslashes($plan->name) }} Plan" + (isRecurring ? " (auto-renew every {{ $cycleWord }})" : ""),
            handler: function (response) {
                document.getElementById('rzp_payment_id').value = response.razorpay_payment_id;
                document.getElementById('rzp_signature').value = response.razorpay_signature;
                if (isRecurring) {
                    document.getElementById('rzp_subscription_id').value = response.razorpay_subscription_id;
                } else {
                    document.getElementById('rzp_order_id').value = response.razorpay_order_id;
                }
                document.getElementById('razorpay-payment-form').submit();
            },
            prefill: {
                name: "{{ addslashes($company->name) }}",
                email: "{{ addslashes(auth()->user()->email ?? '') }}",
                contact: "{{ addslashes($company->mobile_number ?? $company->phone_number ?? '') }}"
            },
            notes: {
                company: "{{ addslashes($company->name) }}",
                plan: "{{ addslashes($plan->name) }}",
                reference: "{{ $reference }}",
                billing_cycle: "{{ $billingCycle ?? $plan->billing_cycle }}"
            },
            theme: { color: '#4f46e5' }
        };

        if (isRecurring) {
            // Razorpay takes the amount from the subscription's plan.
            options.subscription_id = @json($order['id'] ?? '');
        } else {
            options.amount = {{ (int) ($order['amount'] ?? 0) }};
            options.currency = @json($order['currency'] ?? 'INR');
            options.order_id = @json($order['id'] ?? '');
        }

        var rzp = new Razorpay(options);
        rzp.on('payment.failed', function (response) {
            alert('Payment failed: ' + response.error.description);
        });
        rzp.open();
    });
    </script>
    </div>
</x-app-layout>
