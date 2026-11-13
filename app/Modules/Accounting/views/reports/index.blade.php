<x-app-layout>
    @php
        $toneClasses = [
            'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
        ];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950">Accounting Reports</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $periodLabel }}: {{ $dateFrom->format('d M, Y') }} to {{ $dateTo->format('d M, Y') }}</p>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 py-6">
        <form method="GET" class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <select name="period" class=" border-slate-300">
                    @foreach(['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month', 'custom' => 'Custom Date', 'year' => 'Current Year'] as $key => $label)
                        <option value="{{ $key }}" @selected(request('period', 'month') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class=" border-slate-300">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class=" border-slate-300">
                <select name="category_id" class=" border-slate-300"><option value="">All Income Categories</option>@foreach($incomeCategories as $category)<option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>
                <select name="payment_method" class=" border-slate-300"><option value="">All Methods</option>@foreach($paymentMethods as $method)<option value="{{ $method }}" @selected(request('payment_method') === $method)>{{ $method }}</option>@endforeach</select>
                <select name="staff_id" class=" border-slate-300"><option value="">All Staff</option>@foreach($staffMembers as $staff)<option value="{{ $staff->id }}" @selected((string) request('staff_id') === (string) $staff->id)>{{ $staff->name }}</option>@endforeach</select>
                <select name="client_id" class=" border-slate-300"><option value="">All Clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected((string) request('client_id') === (string) $client->id)>{{ $client->name }}</option>@endforeach</select>
                <select name="expense_category_id" class=" border-slate-300"><option value="">All Expense Categories</option>@foreach($expenseCategories as $category)<option value="{{ $category->id }}" @selected((string) request('expense_category_id') === (string) $category->id)>{{ $category->name }}</option>@endforeach</select>
            </div>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-sm text-slate-500">Use the filters to narrow the report window.</p>
                <div class="flex gap-3">
                    <a href="{{ route('accounting.reports.index') }}" class=" border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Reset</a>
                    <button class=" bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply Filters</button>
                </div>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-accounting.summary-card label="Income Count" :value="$incomeSummary['count'] ?? 0" tone="emerald" />
            <x-accounting.summary-card label="Income Amount" :value="'Rs. ' . number_format((float) ($incomeSummary['amount'] ?? 0), 2)" tone="emerald" />
            <x-accounting.summary-card label="Expense Count" :value="$expenseSummary['count'] ?? 0" tone="rose" />
            <x-accounting.summary-card label="Expense Amount" :value="'Rs. ' . number_format((float) ($expenseSummary['amount'] ?? 0), 2)" tone="rose" />
            <x-accounting.summary-card label="Net Profit" :value="'Rs. ' . number_format((float) (($incomeSummary['amount'] ?? 0) - ($expenseSummary['amount'] ?? 0)), 2)" tone="violet" />
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <h3 class="text-base font-semibold text-slate-950">Monthly Income vs Expense</h3>
                <div class="mt-4 h-[300px]"><canvas id="reports-monthly-chart"></canvas></div>
            </div>
            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <h3 class="text-base font-semibold text-slate-950">Monthly Profit Trend</h3>
                <div class="mt-4 h-[300px]"><canvas id="reports-profit-chart"></canvas></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        (() => {
            const create = (id, config) => {
                const canvas = document.getElementById(id);
                if (!canvas || typeof Chart === 'undefined') return;
                new Chart(canvas.getContext('2d'), config);
            };

            create('reports-monthly-chart', {
                type: 'bar',
                data: {
                    labels: @js($charts['monthly_comparison']['labels'] ?? []),
                    datasets: [
                        { label: 'Income', data: @js($charts['monthly_comparison']['income'] ?? []), backgroundColor: '#10b981' },
                        { label: 'Expense', data: @js($charts['monthly_comparison']['expense'] ?? []), backgroundColor: '#f43f5e' },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
            });

            create('reports-profit-chart', {
                type: 'line',
                data: {
                    labels: @js($charts['profit_trend']['labels'] ?? []),
                    datasets: [
                        { label: 'Profit', data: @js($charts['profit_trend']['values'] ?? []), borderColor: '#8b5cf6', backgroundColor: 'rgba(139,92,246,0.15)', tension: 0.35, fill: true },
                    ],
                },
                options: { responsive: true, maintainAspectRatio: false, scales: { y: { beginAtZero: true } } },
            });
        })();
    </script>
</x-app-layout>
