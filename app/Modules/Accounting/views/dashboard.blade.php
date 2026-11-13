<x-app-layout>
    @php
        $summary = $summary ?? [];
        $charts = $charts ?? [];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-semibold leading-tight text-slate-950">Accounting Dashboard</h2>
                <p class="mt-1 text-sm text-slate-500">Track cash flow, profit, and recent accounting performance.</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('accounting.incomes.create') }}" class="inline-flex items-center bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-500">+ Income</a>
                <a href="{{ route('accounting.expenses.create') }}" class="inline-flex items-center bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-rose-500" style="background-color: #f77070;">+ Expense</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 py-2">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-accounting.summary-card label="Total Income" :value="'Rs. ' . number_format((float) ($summary['total_income'] ?? 0), 2)" meta="All recorded income" tone="emerald" />
            <x-accounting.summary-card label="Total Expense" :value="'Rs. ' . number_format((float) ($summary['total_expense'] ?? 0), 2)" meta="All recorded expense" tone="rose" />
            <x-accounting.summary-card label="Net Profit" :value="'Rs. ' . number_format((float) ($summary['net_profit'] ?? 0), 2)" meta="Income minus expense" tone="violet" />
            <x-accounting.summary-card label="Cash Income" :value="'Rs. ' . number_format((float) ($summary['cash_income'] ?? 0), 2)" meta="Cash received" tone="amber" />
            <x-accounting.summary-card label="Online Income" :value="'Rs. ' . number_format((float) ($summary['online_income'] ?? 0), 2)" meta="Online, bank, UPI, card and cheque" tone="sky" />
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">Monthly Income vs Expense</h3>
                        <p class="text-sm text-slate-500">Rolling six-month comparison</p>
                    </div>
                </div>
                <div class="mt-4 h-[320px]">
                    <canvas id="accounting-monthly-chart"></canvas>
                </div>
            </div>

            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">Monthly Profit Trend</h3>
                        <p class="text-sm text-slate-500">Positive and negative movement</p>
                    </div>
                </div>
                <div class="mt-4 h-[320px]">
                    <canvas id="accounting-profit-chart"></canvas>
                </div>
            </div>

            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <h3 class="text-base font-semibold text-slate-950">Income Category Breakdown</h3>
                <div class="mt-4 h-[320px]">
                    <canvas id="accounting-income-category-chart"></canvas>
                </div>
            </div>

            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <h3 class="text-base font-semibold text-slate-950">Expense Category Breakdown</h3>
                <div class="mt-4 h-[320px]">
                    <canvas id="accounting-expense-category-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            const chartConfig = (type, labels, datasets, options = {}) => ({
                type,
                data: { labels, datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } },
                    ...options,
                },
            });

            const create = (id, config) => {
                const canvas = document.getElementById(id);
                if (!canvas || typeof Chart === 'undefined') return;
                new Chart(canvas.getContext('2d'), config);
            };

            create('accounting-monthly-chart', chartConfig('bar', @js($charts['monthly_comparison']['labels'] ?? []), [
                { label: 'Income', data: @js($charts['monthly_comparison']['income'] ?? []), backgroundColor: '#10b981' },
                { label: 'Expense', data: @js($charts['monthly_comparison']['expense'] ?? []), backgroundColor: '#f43f5e' },
            ], { scales: { y: { beginAtZero: true } } }));

            create('accounting-profit-chart', chartConfig('line', @js($charts['profit_trend']['labels'] ?? []), [
                { label: 'Profit', data: @js($charts['profit_trend']['values'] ?? []), borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.15)', tension: 0.35, fill: true },
            ], { scales: { y: { beginAtZero: true } } }));

            create('accounting-income-category-chart', chartConfig('doughnut', @js($charts['income_breakdown']['labels'] ?? []), [
                { data: @js($charts['income_breakdown']['values'] ?? []), backgroundColor: ['#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#14b8a6', '#f97316'] },
            ]));

            create('accounting-expense-category-chart', chartConfig('doughnut', @js($charts['expense_breakdown']['labels'] ?? []), [
                { data: @js($charts['expense_breakdown']['values'] ?? []), backgroundColor: ['#ef4444', '#f97316', '#eab308', '#ec4899', '#8b5cf6', '#64748b'] },
            ]));
        })();
    </script>
</x-app-layout>
