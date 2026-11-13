<section class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Projects With AMC</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $amcCoverageReport['summary']['projects_with_amc'] }}</div>
        </div>
        <div class="border border-slate-200 bg-slate-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Without AMC</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $amcCoverageReport['summary']['projects_without_amc'] }}</div>
        </div>
        <div class="border border-slate-200 bg-emerald-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Paid AMC</div>
            <div class="mt-2 text-2xl font-extrabold text-emerald-950">{{ $amcCoverageReport['summary']['paid_amcs'] }}</div>
        </div>
        <div class="border border-slate-200 bg-amber-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Pending AMC</div>
            <div class="mt-2 text-2xl font-extrabold text-amber-950">{{ $amcCoverageReport['summary']['pending_amcs'] }}</div>
        </div>
        <div class="border border-slate-200 bg-cyan-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-700">Billed Revenue</div>
            <div class="mt-2 text-2xl font-extrabold text-cyan-950">Rs. {{ number_format($amcCoverageReport['summary']['billed_revenue'], 0) }}</div>
        </div>
        <div class="border border-slate-200 bg-violet-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-violet-700">Pending Revenue</div>
            <div class="mt-2 text-2xl font-extrabold text-violet-950">Rs. {{ number_format($amcCoverageReport['summary']['pending_revenue'], 0) }}</div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="border border-slate-200 bg-rose-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-700">Expiring In 7 Days</div>
            <div class="mt-2 text-2xl font-extrabold text-rose-950">{{ $renewalReport['summary']['expiring_in_7_days'] }}</div>
        </div>
        <div class="border border-slate-200 bg-amber-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Expiring In 15 Days</div>
            <div class="mt-2 text-2xl font-extrabold text-amber-950">{{ $renewalReport['summary']['expiring_in_15_days'] }}</div>
        </div>
        <div class="border border-slate-200 bg-sky-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">Expiring In 30 Days</div>
            <div class="mt-2 text-2xl font-extrabold text-sky-950">{{ $renewalReport['summary']['expiring_in_30_days'] }}</div>
        </div>
        <div class="border border-slate-200 bg-slate-100 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-600">Already Expired</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $renewalReport['summary']['expired_amcs'] }}</div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-2">
        @include('reports.partials.chart-card', [
            'title' => 'AMC Status Chart',
            'subtitle' => 'Contract Status Breakdown',
            'badge' => $amcCoverageReport['summary']['total_amcs'] . ' contracts',
            'config' => $chartConfigs['amc_status'],
        ])

        @include('reports.partials.renewal-table')
    </div>

    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.15fr]">
        @include('reports.partials.chart-card', [
            'title' => 'Active / No AMC / Expired',
            'subtitle' => 'Project Coverage',
            'config' => $chartConfigs['coverage'],
        ])

        <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Stacked Bar</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">AMC By Plan Type</h3>
            </div>
            <div class="mt-5 space-y-4">
                @forelse ($amcCoverageReport['plan_rows'] as $row)
                    <div class="border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <div class="text-sm font-semibold text-slate-900">{{ $row['label'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['count'] }} contracts • Rs. {{ number_format($row['amount'], 0) }}</div>
                            </div>
                            <div class="text-xs text-slate-500">{{ $row['paid'] }} paid / {{ $row['pending'] }} pending</div>
                        </div>
                        <div class="mt-4 flex h-3 overflow-hidden bg-slate-200">
                            <div class="bg-emerald-500" style="width: {{ $row['paid_percent'] }}%;"></div>
                            <div class="bg-amber-400" style="width: {{ $row['pending_percent'] }}%;"></div>
                        </div>
                    </div>
                @empty
                    <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No AMC plan records match the current filter.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
        <!-- <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Payment Status</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">AMC Payment Overview</h3>
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2">
                @foreach ($amcCoverageReport['payment_rows'] as $row)
                    <div class="border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $row['label'] }}</div>
                        <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $row['count'] }}</div>
                    </div>
                @endforeach
            </div>
        </div> -->

        <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Revenue Overview</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">AMC Revenue Snapshot</h3>
            </div>
            @php
                $billed = max(1, $amcCoverageReport['summary']['billed_revenue']);
                $paidPercent = round(($amcCoverageReport['summary']['paid_revenue'] / $billed) * 100, 1);
                $pendingPercent = round(($amcCoverageReport['summary']['pending_revenue'] / $billed) * 100, 1);
            @endphp
            <div class="mt-5 space-y-5">
                <div>
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-700">Paid Revenue</span>
                        <span class="text-slate-500">Rs. {{ number_format($amcCoverageReport['summary']['paid_revenue'], 0) }}</span>
                    </div>
                    <div class="h-3 bg-slate-100">
                        <div class="h-3 bg-gradient-to-r from-emerald-500 to-teal-500" style="width: {{ $paidPercent }}%;"></div>
                    </div>
                </div>
                <div>
                    <div class="mb-2 flex items-center justify-between text-sm">
                        <span class="font-semibold text-slate-700">Pending Revenue</span>
                        <span class="text-slate-500">Rs. {{ number_format($amcCoverageReport['summary']['pending_revenue'], 0) }}</span>
                    </div>
                    <div class="h-3 bg-slate-100">
                        <div class="h-3 bg-gradient-to-r from-amber-400 to-orange-400" style="width: {{ $pendingPercent }}%;"></div>
                    </div>
                </div>
                <div class="border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Total Billed</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">Rs. {{ number_format($amcCoverageReport['summary']['billed_revenue'], 0) }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Priority Customers</p>
            <h3 class="mt-1 text-lg font-bold text-slate-950">High-value Renewal Follow-up</h3>
        </div>
        <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse ($renewalReport['priority_rows'] as $row)
                <div class="border border-slate-200 bg-slate-50 px-4 py-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-900">{{ $row['customer_name'] }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ $row['project_name'] }} • {{ $row['contract_no'] }}</div>
                        </div>
                        <span class="inline-flex items-center px-3 py-1 text-xs font-semibold ring-1 {{ $toneClasses[$row['tone']] ?? $toneClasses['slate'] }}">
                            {{ $row['status_label'] }}
                        </span>
                    </div>
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="text-slate-500">Expiry: {{ $row['expiry_date'] }}</span>
                        <span class="font-semibold text-slate-900">Rs. {{ number_format($row['amount'], 0) }}</span>
                    </div>
                </div>
            @empty
                <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500 md:col-span-2 xl:col-span-3">No high-priority renewals right now.</div>
            @endforelse
        </div>
    </div>
</section>
