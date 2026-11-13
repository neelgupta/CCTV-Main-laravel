{{-- Razorpay plan link fields for the plan create / edit forms. Expects: $bag, $prefix, $values, $razorpayMode, $gstRate --}}
<div class="rounded-2xl border border-slate-200 bg-slate-50/60 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm font-bold text-slate-900">Razorpay Plans (recurring billing)</p>
        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $razorpayMode === 'test' ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700' }}">{{ ucfirst($razorpayMode) }} mode</span>
    </div>
    <p class="mt-1 text-xs text-slate-500">Optional. Paste a plan ID created in the Razorpay dashboard ({{ $razorpayMode }} mode), or leave blank to have one created automatically.</p>
    <div class="mt-4 grid gap-4 md:grid-cols-2">
        @foreach (['monthly' => 'Monthly', 'yearly' => 'Yearly'] as $cycle => $cycleLabel)
            <div>
                <label for="{{ $prefix }}_razorpay_plan_id_{{ $cycle }}" class="mb-2 block text-sm font-semibold text-slate-700">{{ $cycleLabel }} Plan ID</label>
                <input
                    type="text"
                    id="{{ $prefix }}_razorpay_plan_id_{{ $cycle }}"
                    name="razorpay_plan_id_{{ $cycle }}"
                    value="{{ $values[$cycle] ?? '' }}"
                    maxlength="100"
                    autocomplete="off"
                    class="block w-full rounded-2xl border-slate-300 font-mono text-sm"
                    placeholder="plan_XXXXXXXXXXXXXX"
                >
                <p class="mt-2 text-xs text-slate-500" data-razorpay-amount-hint="{{ $cycle }}" data-gst-rate="{{ $gstRate }}">
                    Razorpay amount must be the {{ $cycle }} price + {{ rtrim(rtrim(number_format($gstRate, 2), '0'), '.') }}% GST, per license, billed every 1 {{ $cycle === 'monthly' ? 'month' : 'year' }}.
                </p>
                <x-input-error :messages="$errors->{$bag}->get('razorpay_plan_id_' . $cycle)" class="mt-2" />
            </div>
        @endforeach
    </div>
</div>
