{{-- Razorpay plan used for recurring billing in the current mode. Expects: $razorpayPlan (array|null) --}}
@if ($razorpayPlan)
    @php($isLinked = ($razorpayPlan['source'] ?? null) === 'manual')
    <div class="mt-2 flex flex-col items-start gap-1 whitespace-normal" title="Razorpay plan {{ $isLinked ? 'linked by Super Admin' : 'created automatically' }}: {{ $razorpayPlan['id'] }}">
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider ring-1 {{ $isLinked ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-slate-200' }}">
            <span class="h-1.5 w-1.5 rounded-full {{ $isLinked ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
            {{ $isLinked ? 'Linked' : 'Auto' }}
        </span>
        <span class="break-all font-mono text-[10px] leading-tight text-slate-400">{{ $razorpayPlan['id'] }}</span>
    </div>
@endif
