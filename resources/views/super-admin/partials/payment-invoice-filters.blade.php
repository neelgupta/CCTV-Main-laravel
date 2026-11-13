<form method="GET" action="{{ $action }}" class="mb-6 rounded-[28px] border border-slate-200 bg-white px-5 py-4 shadow-sm">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
        <div class="grid flex-1 grid-cols-1 gap-4 sm:grid-cols-2">
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">From</span>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500">
            </label>
            <label class="block">
                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">To</span>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="mt-1 w-full rounded-xl border-slate-300 text-sm focus:border-rose-500 focus:ring-rose-500">
            </label>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <button type="submit" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Filter
            </button>
            @if($filters['from'] || $filters['to'])
                <a href="{{ $action }}" class="inline-flex items-center rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-500 hover:text-slate-700">
                    Reset
                </a>
            @endif
            <button type="submit" formaction="{{ route('super-admin.payments.invoices.download', $company) }}" class="inline-flex items-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                Download Invoices (PDF)
            </button>
        </div>
    </div>

    <p class="mt-3 text-xs text-slate-500">
        Filters by invoice date. Download saves this company's invoices in the selected range as one PDF, one invoice per page. Trial subscriptions have no invoice and are skipped.
    </p>

    @if($errors->has('from') || $errors->has('to'))
        <div class="mt-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700">
            {{ $errors->first('from') ?: $errors->first('to') }}
        </div>
    @endif
</form>
