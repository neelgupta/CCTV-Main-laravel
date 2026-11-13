<x-app-layout>
    <div id="reports-page">
        @php
            $toneClasses = [
                'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
                'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
                'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
                'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
                'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
            ];

            $selectedClientName = $filters['client']?->name;
            $selectedStaffName = $filters['staff']?->name;
            $canToggleReceivedAmount = auth()->user()?->isCompanyAdmin();
            $canEditAmc = auth()->user()?->hasPermission('amc.edit');
            $hasLeadGraphableCustomFields = ! empty($leadGraphableCustomFields);
        @endphp

    <x-slot name="header">
        <div class="border border-white/70 bg-white/85 p-5 shadow-xl shadow-slate-950/5 backdrop-blur">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">Business Intelligence</p>
                    <div class="mt-1 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-2xl font-extrabold text-slate-950">{{ $reportTitle }}</h2>
                            <p class="mt-1 text-sm text-slate-500">{{ $reportDescription }}</p>
                        </div>
                        <div class="flex flex-wrap gap-3 self-start">
                            @if ($activeTab === 'sales-funnel' && $hasLeadGraphableCustomFields)
                                <button
                                    type="button"
                                    data-report-filters-trigger
                                    class="inline-flex h-11 w-11 items-center justify-center border border-slate-300 bg-white text-slate-700 shadow-sm transition hover:bg-slate-50"
                                    aria-label="Toggle report filters"
                                    aria-expanded="false"
                                    title="Toggle filters"
                                >
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M4 5h16l-6 7v5l-4 2v-7L4 5z" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>

                                <button
                                    type="button"
                                    data-leads-custom-fields-trigger
                                    class="inline-flex items-center justify-center gap-2 border border-violet-200 bg-violet-50 px-4 py-2.5 text-sm font-semibold text-violet-800 shadow-sm transition hover:bg-violet-100"
                                >
                                    <svg class="h-4 w-4 flex-none" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                        <path d="M4 19h16M7 16V8M12 16V5M17 16v-4" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span>Select Fields</span>
                                </button>
                            @endif

                            @if (! empty($exportUrl))
                                <button
                                    type="button"
                                    x-data="leadsReportExport(@js($exportUrl))"
                                    @click="exportReport()"
                                    :disabled="exporting"
                                    class="inline-flex shrink-0 items-center justify-center gap-2 self-start border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                                >
                                    <svg
                                        x-show="!exporting"
                                        class="h-4 w-4"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        aria-hidden="true"
                                    >
                                        <path d="M12 4v10M8 10l4 4 4-4M5 18h14" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span x-show="exporting" class="h-4 w-4 animate-spin border-2 border-slate-300 border-t-slate-700"></span>
                                    <span x-text="exporting ? 'Exporting...' : 'Export'"></span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="grid w-full grid-cols-2 gap-4 sm:grid-cols-4 xl:w-auto">
                    @foreach ($summaryCards as $card)
                        @php
                            $isPaymentQuickFilter = $activeTab === 'payments' && isset($card['payment_status']);
                            $isActivePaymentFilter = $isPaymentQuickFilter && (string) ($filters['status'] ?? '') === (string) $card['payment_status'];
                            $cardUrl = $isPaymentQuickFilter
                                ? route('reports.payments', array_filter([
                                    'status' => $card['payment_status'],
                                    'invoice_date' => request()->query('invoice_date'),
                                    'staff_id' => request()->query('staff_id'),
                                ], fn ($value) => filled($value)))
                                : ($activeTab === 'payments' && ! empty($card['received_only'])
                                    ? route('reports.payments', array_filter([
                                        'received_only' => 1,
                                        'invoice_date' => request()->query('invoice_date'),
                                        'staff_id' => request()->query('staff_id'),
                                        'status' => request()->query('status'),
                                    ], fn ($value) => filled($value)))
                                    : null);
                            $isActiveReceivedFilter = $activeTab === 'payments'
                                && ! empty($card['received_only'])
                                && request()->boolean('received_only');
                            $isActiveCard = $isActivePaymentFilter || $isActiveReceivedFilter;
                        @endphp
                        @if ($cardUrl)
                            <a href="{{ $cardUrl }}" class="block">
                                <div class="flex min-h-[96px] flex-col justify-center rounded-2xl px-5 py-4 ring-1 transition hover:-translate-y-0.5 hover:shadow-md {{ $isActiveCard ? 'ring-slate-950 shadow-lg' : ($toneClasses[$card['tone']] ?? $toneClasses['slate']) }}">
                                    <div class="text-[10px] font-bold uppercase tracking-[0.16em]">{{ $card['label'] }}</div>
                                    <div class="mt-1 text-2xl font-extrabold text-slate-950">{{ $card['value'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ $card['meta'] }}</div>
                                </div>
                            </a>
                        @else
                            <div class="flex min-h-[96px] flex-col justify-center rounded-2xl px-5 py-4 ring-1 {{ $toneClasses[$card['tone']] ?? $toneClasses['slate'] }}">
                                <div class="text-[10px] font-bold uppercase tracking-[0.16em]">{{ $card['label'] }}</div>
                                <div class="mt-1 text-2xl font-extrabold text-slate-950">{{ $card['value'] }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ $card['meta'] }}</div>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

        </div>
    </x-slot>

    <div class="space-y-6 py-2">
        <div
            class="border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur"
            data-report-filters-panel
            hidden
            aria-hidden="true"
        >
            <form method="GET" action="{{ $formAction }}" class="grid gap-4 {{ $activeTab === 'users' ? 'lg:grid-cols-1' : 'lg:grid-cols-5' }}">
                @if ($activeTab === 'sales-funnel' && ! empty($selectedLeadCustomFieldGraphKeys))
                    @foreach ($selectedLeadCustomFieldGraphKeys as $selectedLeadCustomFieldGraphKey)
                        <input type="hidden" name="custom_graph_fields[]" value="{{ $selectedLeadCustomFieldGraphKey }}">
                    @endforeach
                @endif

                @if ($activeTab !== 'users')
                    <div>
                        <label for="report-date-from" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Date From</label>
                        <input
                            id="report-date-from"
                            name="date_from"
                            type="date"
                            value="{{ optional($filters['date_from'])->format('Y-m-d') }}"
                            class="block w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                    </div>
                    <div>
                        <label for="report-date-to" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Date To</label>
                        <input
                            id="report-date-to"
                            name="date_to"
                            type="date"
                            value="{{ optional($filters['date_to'])->format('Y-m-d') }}"
                            class="block w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                    </div>
                    <div>
                        <label for="report-project-type" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Project Type</label>
                        <select
                            id="report-project-type"
                            name="project_type"
                            class="block w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                            <option value="">All types</option>
                            @foreach ($projectTypes as $projectType)
                                <option value="{{ $projectType->id }}" @selected((string) $filters['project_type'] === (string) $projectType->id)>{{ $projectType->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div @if ($statusFilterUsesToggle) x-data="{ status: @js($filters['status'] ?? '') }" @endif>
                    <span class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status Filter</span>

                    @if ($statusFilterUsesToggle)
                        <input type="hidden" name="status" x-model="status">
                        <div
                            class="inline-flex w-full overflow-hidden border border-slate-300 bg-white shadow-sm sm:w-auto"
                            role="group"
                            aria-label="User status filter"
                        >
                            <button
                                type="button"
                                @click="status = ''"
                                :class="status === '' ? 'bg-slate-950 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'"
                                class="flex-1 px-4 py-2.5 text-sm font-semibold transition focus:outline-none sm:flex-none sm:px-5"
                                :aria-pressed="status === '' ? 'true' : 'false'"
                            >
                                All Users
                            </button>
                            @foreach ($statusFilterOptions as $statusValue => $statusLabel)
                                <button
                                    type="button"
                                    @click="status = @js($statusValue)"
                                    :class="status === @js($statusValue) ? 'bg-slate-950 text-white' : 'bg-white text-slate-700 hover:bg-slate-50'"
                                    class="flex-1 border-l border-slate-300 px-4 py-2.5 text-sm font-semibold transition focus:outline-none sm:flex-none sm:px-5"
                                    :aria-pressed="status === @js($statusValue) ? 'true' : 'false'"
                                >
                                    {{ $statusLabel }}
                                </button>
                            @endforeach
                        </div>
                    @else
                        <select
                            id="report-status"
                            name="status"
                            class="block w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                            <option value="">All statuses</option>
                            @foreach ($statusFilterOptions as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}" @selected($filters['status'] === $statusValue)>{{ $statusLabel }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>

                @if ($activeTab !== 'users')
                    <div>
                        <label for="report-client" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Client</label>
                        <select
                            id="report-client"
                            name="client_id"
                            class="block w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                            <option value="">All clients</option>
                            @foreach ($clients as $client)
                                <option value="{{ $client->id }}" @selected((string) $filters['client_id'] === (string) $client->id)>{{ $client->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($activeTab === 'payments')
                    <div>
                        <label for="payment-invoice-date" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Invoice Date</label>
                        <input
                            id="payment-invoice-date"
                            name="invoice_date"
                            type="date"
                            value="{{ request('invoice_date') }}"
                            class="block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                    </div>
                    <div>
                        <label for="report-staff" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff</label>
                        <select
                            id="report-staff"
                            name="staff_id"
                            class="block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                            <option value="">All staff</option>
                            @foreach ($paymentStaffOptions as $staff)
                                <option value="{{ $staff->id }}" @selected((string) $filters['staff_id'] === (string) $staff->id)>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="{{ $activeTab === 'users' ? '' : 'lg:col-span-5' }} flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-sm text-slate-500">
                        Filters apply to the current report.
                        @if ($selectedClientName)
                            Current client: <span class="font-semibold text-slate-700">{{ $selectedClientName }}</span>
                        @endif
                        @if ($selectedStaffName)
                            Current staff: <span class="font-semibold text-slate-700">{{ $selectedStaffName }}</span>
                        @endif
                    </p>
                    <div class="flex flex-col gap-3 sm:flex-row">
                        <a href="{{ $formAction }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                        <button type="submit" class="inline-flex items-center justify-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Apply Filters</button>
                    </div>
                </div>
            </form>
        </div>

        @if ($activeTab === 'sales-funnel')
            @include('reports.partials.leads')
        @endif

        @if ($activeTab === 'project-pipeline')
            @include('reports.partials.projects')
        @endif

        @if ($activeTab === 'amc')
            @include('reports.partials.amc')
        @endif

        @if ($activeTab === 'payments')
        <section class="space-y-6">
            <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                @include('reports.partials.chart-card', [
                    'title' => 'Invoice status breakdown',
                    'subtitle' => 'Payment Mix',
                    'badge' => $paymentReport['summary']['total_invoices'] . ' invoices',
                    'config' => $chartConfigs['payment_status'] ?? ['empty' => true, 'type' => 'doughnut', 'data' => ['labels' => [], 'datasets' => []], 'options' => []],
                    'height' => 'h-80',
                ])

                <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Who generated the invoices</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Staff vs creator comparison</h3>
                    </div>
                    <div class="mt-5 grid gap-6 md:grid-cols-2">
                        @include('reports.partials.chart-card', [
                            'title' => 'Invoices by staff',
                            'subtitle' => 'Staff activity',
                            'config' => $chartConfigs['payment_staff'] ?? ['empty' => true, 'type' => 'bar', 'data' => ['labels' => [], 'datasets' => []], 'options' => []],
                            'height' => 'h-72',
                        ])

                        @include('reports.partials.chart-card', [
                            'title' => 'Billing amount by creator',
                            'subtitle' => 'Creator revenue',
                            'config' => $chartConfigs['payment_creator'] ?? ['empty' => true, 'type' => 'bar', 'data' => ['labels' => [], 'datasets' => []], 'options' => []],
                            'height' => 'h-72',
                        ])
                    </div>
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Invoice Register</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">All support invoices</h3>
                    </div>
                </div>
                <form method="GET" action="{{ $formAction }}" class="mt-5 flex flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-end sm:justify-between">
                    <div class="w-full max-w-md">
                        <label for="payment-report-staff" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Filter by Staff</label>
                        <select
                            id="payment-report-staff"
                            name="staff_id"
                            onchange="this.form.submit()"
                            class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                            <option value="">All invoices</option>
                            @foreach ($paymentStaffOptions as $staff)
                                <option value="{{ $staff->id }}" @selected((string) $filters['staff_id'] === (string) $staff->id)>{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="w-full max-w-md">
                        <label for="payment-report-date" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Filter by Date</label>
                        <input
                            id="payment-report-date"
                            type="date"
                            name="invoice_date"
                            value="{{ request('invoice_date') }}"
                            onchange="this.form.submit()"
                            class="block w-full rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('reports.payments') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    </div>
                </form>
                <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide">Invoice</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide">Customer</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide">Staff</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide">Generated By</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide">Payment Status</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide">Amount Received By Admin</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide">Collected Date</th>
                                <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide">Amount</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide">Action</th>
                            </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse ($paymentReport['invoice_rows'] as $row)
                                @php
                                    $ticketInvoice = $row['invoice'];
                                    $ticket = $ticketInvoice->ticket;
                                    $isPaid = (int) ($ticketInvoice->payment_status ?? 0) === 1;
                                    $document = $ticketInvoice->invoice;
                                    $viewUrl = $ticket
                                        ? route('tickets.invoices.view', [$ticket, $ticketInvoice])
                                        : ($document ? route('service-call.billing-document.show', $document) : null);
                                    $printUrl = $ticket
                                        ? route('tickets.invoices.print', [$ticket, $ticketInvoice])
                                        : ($document ? route('service-call.billing-document.download', $document) : null);
                                @endphp
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900">{{ $row['number'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $row['ticket_number'] }} | {{ $row['created_at'] }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900">{{ $row['customer_name'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $row['company_name'] }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">{{ $row['staff_name'] }}</td>
                                    <td class="px-4 py-3 text-slate-700">{{ $row['creator_name'] }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $toneClasses[$row['payment_tone']] ?? $toneClasses['slate'] }}">
                                            {{ $row['payment_status'] }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($canToggleReceivedAmount && $isPaid)
                                            <form method="POST" action="{{ route('reports.payments.received.update', $ticketInvoice) }}" class="inline-flex items-center gap-3">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="received_by_admin" value="0">
                                                <label class="inline-flex items-center cursor-pointer" title="Toggle Amount Received By Admin">
                                                    <input
                                                        type="checkbox"
                                                        name="received_by_admin"
                                                        value="1"
                                                        class="sr-only peer"
                                                        @checked((bool) $ticketInvoice->received_by_admin)
                                                        onchange="this.form.submit()"
                                                    >
                                                    <span class="relative h-6 w-11 rounded-full bg-gray-200 transition peer-checked:bg-emerald-500 after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:transition after:content-[''] peer-checked:after:translate-x-5"></span>
                                                </label>
                                            </form>
                                        @elseif (! $isPaid)
                                            <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600">Invoice not paid yet</span>
                                        @else
                                            <span class="rounded-full {{ $ticketInvoice->received_by_admin ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-semibold">
                                                {{ $ticketInvoice->received_by_admin ? 'Received' : 'Pending with Staff' }}
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ $ticketInvoice->received_by_admin_at?->format('d M Y') ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-slate-900">Rs. {{ number_format($row['amount'], 0) }}</td>
                                    <td class="px-4 py-3">
                                        @if ($viewUrl || $printUrl)
                                            <div class="flex flex-wrap items-center gap-2">
                                                @if ($viewUrl)
                                                    <a
                                                        href="{{ $viewUrl }}"
                                                        class="inline-flex items-center rounded-md border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50"
                                                    >
                                                        View
                                                    </a>
                                                @endif
                                                @if ($printUrl)
                                                    <a
                                                        href="{{ $printUrl }}"
                                                        target="_blank"
                                                        rel="noopener noreferrer"
                                                        class="inline-flex items-center rounded-md border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                    >
                                                        Print
                                                    </a>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-xs text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-sm text-slate-500">No support invoices found for the selected filters.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="mt-5">
                    {{ $paymentReport['invoice_rows']->withQueryString()->links() }}
                </div>
            </div>
        </section>
        @endif


        @if ($activeTab === 'users')
        <section class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($usersReport['status_rows'] as $row)
                    <div class="border border-slate-200 bg-white px-4 py-4 shadow-sm">
                        <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $row['label'] }}</div>
                        <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $row['count'] }}</div>
                    </div>
                @endforeach
                <div class="border border-slate-200 bg-sky-50 px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">Total Users</div>
                    <div class="mt-2 text-2xl font-extrabold text-sky-950">{{ $usersReport['summary']['total_users'] }}</div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[0.95fr_1.05fr]">
                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Role Distribution</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Users By Role</h3>
                    </div>
                    <div class="mt-5 space-y-4">
                        @forelse ($usersReport['role_rows'] as $row)
                            <div>
                                <div class="mb-2 flex items-center justify-between gap-3 text-sm">
                                    <div>
                                        <div class="font-semibold text-slate-900">{{ $row['label'] }}</div>
                                        <div class="text-xs text-slate-500">{{ $row['active'] }} active • {{ $row['inactive'] }} inactive</div>
                                    </div>
                                    <span class="text-slate-500">{{ $row['count'] }}</span>
                                </div>
                                <div class="h-3 bg-slate-100">
                                    <div class="h-3 bg-gradient-to-r from-indigo-500 to-cyan-500" style="width: {{ $row['bar_percent'] }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No users found for this company.</div>
                        @endforelse
                    </div>
                </div>

                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">User Roster</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Company Users</h3>
                    </div>
                    <div class="mt-5 overflow-x-auto border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Name</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Role</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Status</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Joined</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse ($usersReport['user_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-900">{{ $row['name'] }}</div>
                                            <div class="text-xs text-slate-500">{{ $row['email'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-700">{{ $row['role'] }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold ring-1 {{ $toneClasses[$row['tone']] ?? $toneClasses['slate'] }}">
                                                {{ $row['status_label'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-700">{{ $row['joined_at'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No users found for this company.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        @endif

        @if ($activeTab === 'service-operations')
        <section class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
                <div class="border border-slate-200 bg-white px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Upcoming Visits</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $serviceReport['summary']['upcoming_visits'] }}</div>
                </div>
                <div class="border border-slate-200 bg-emerald-50 px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Completed</div>
                    <div class="mt-2 text-2xl font-extrabold text-emerald-950">{{ $serviceReport['summary']['completed_visits'] }}</div>
                </div>
                <div class="border border-slate-200 bg-amber-50 px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Pending</div>
                    <div class="mt-2 text-2xl font-extrabold text-amber-950">{{ $serviceReport['summary']['pending_visits'] }}</div>
                </div>
                <div class="border border-slate-200 bg-rose-50 px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-700">Missed / Overdue</div>
                    <div class="mt-2 text-2xl font-extrabold text-rose-950">{{ $serviceReport['summary']['overdue_tasks'] }}</div>
                </div>
                <div class="border border-slate-200 bg-cyan-50 px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-700">Open AMC Tickets</div>
                    <div class="mt-2 text-2xl font-extrabold text-cyan-950">{{ $serviceReport['summary']['open_amc_tickets'] }}</div>
                </div>
                <div class="border border-slate-200 bg-violet-50 px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-violet-700">Repeat Complaints</div>
                    <div class="mt-2 text-2xl font-extrabold text-violet-950">{{ $serviceReport['summary']['repeat_complaints'] }}</div>
                </div>
                <div class="border border-slate-200 bg-slate-100 px-4 py-4 shadow-sm">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-600">Repeat %</div>
                    <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ number_format($serviceReport['summary']['repeat_percentage'], 2) }}%</div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Technician Workload</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Workload Distribution</h3>
                    </div>
                    <div class="mt-5 space-y-4">
                        @forelse ($serviceReport['workload_rows'] as $row)
                            <div>
                                <div class="mb-2 flex items-center justify-between text-sm">
                                    <span class="font-semibold text-slate-900">{{ $row['label'] }}</span>
                                    <span class="text-slate-500">{{ $row['count'] }}</span>
                                </div>
                                <div class="h-3 bg-slate-100">
                                    <div class="h-3 bg-gradient-to-r from-cyan-500 to-indigo-500" style="width: {{ $row['percent'] }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No technician workload data is available for this filter range.</div>
                        @endforelse
                    </div>
                </div>

                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Visit Status Chart</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Service Status Breakdown</h3>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($serviceReport['status_rows'] as $row)
                            <div class="border border-slate-200 bg-slate-50 px-4 py-4">
                                <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">{{ $row['label'] }}</div>
                                <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $row['count'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff Repeat Count</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Repeat complaints by staff</h3>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse ($serviceReport['repeat_rows'] as $row)
                            <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-900">{{ $row['label'] }}</span>
                                    <span class="text-sm font-bold text-slate-950">{{ $row['count'] }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No repeat complaints recorded yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Client Repeat Count</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Repeat complaints by client</h3>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse ($serviceReport['client_repeat_rows'] as $row)
                            <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-900">{{ $row['label'] }}</span>
                                    <span class="text-sm font-bold text-slate-950">{{ $row['count'] }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No repeat complaints recorded yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Repeated Issues</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Most frequently repeated issues</h3>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse ($serviceReport['issue_repeat_rows'] as $row)
                            <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-900">{{ $row['label'] }}</span>
                                    <span class="text-sm font-bold text-slate-950">{{ $row['count'] }}</span>
                                </div>
                            </div>
                        @empty
                            <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No repeat complaint issue patterns yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Assignment Overview</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Reassignment Metrics</h3>
                    </div>
                    <div class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-1">
                        <div class="border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Total Reassignments</div>
                            <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $serviceReport['summary']['total_reassignments'] ?? 0 }}</div>
                        </div>
                        <div class="border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Avg Repeat Before Reassign</div>
                            <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ number_format((float) ($serviceReport['summary']['average_repeat_count_before_reassignment'] ?? 0), 2) }}</div>
                        </div>
                    </div>
                </div>

                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Employee Repeat Performance</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Handled by staff</h3>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse (($serviceReport['assignment_insights']['employee_repeat_rows'] ?? []) as $row)
                            <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-900">{{ $row['staff']?->name ?? 'Unassigned' }}</span>
                                    <span class="text-sm font-bold text-slate-950">{{ $row['repeat_complaints'] ?? 0 }}</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">Assigned: {{ $row['assigned_tickets'] ?? 0 }} | Reassigned: {{ $row['reassigned_tickets'] ?? 0 }}</div>
                            </div>
                        @empty
                            <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No assignment performance data yet.</div>
                        @endforelse
                    </div>
                </div>

                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Most Reassigned Tickets</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Tickets with highest movement</h3>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse (($serviceReport['assignment_insights']['most_reassigned_tickets'] ?? []) as $row)
                            <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-sm font-semibold text-slate-900">{{ $row['ticket']?->ticket_number ?? '-' }}</span>
                                    <span class="text-sm font-bold text-slate-950">{{ $row['reassignments'] ?? 0 }}</span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">
                                    {{ $row['ticket']?->subject ?? '' }}
                                    @if(filled($row['current_owner']?->name))
                                        | Current: {{ $row['current_owner']->name }}
                                    @endif
                                </div>
                                @if(! empty($row['previous_owners'] ?? []))
                                    <div class="mt-1 text-[11px] text-slate-500">
                                        Previous Owners:
                                        {{ collect($row['previous_owners'])->map(fn ($owner) => $owner?->name)->filter()->implode(', ') }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No reassignment data yet.</div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Schedule Timeline</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Upcoming AMC Visits</h3>
                    </div>
                    <div class="mt-5 space-y-3">
                        @forelse ($serviceReport['timeline_rows'] as $row)
                            <a href="{{ $row['show_url'] }}" class="block border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-slate-300 hover:bg-white">
                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-semibold text-slate-900">{{ $row['title'] }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ $row['contract_no'] }} • {{ $row['customer_name'] }}</div>
                                    </div>
                                    <div class="text-xs text-slate-500">{{ $row['date'] }}</div>
                                </div>
                                <div class="mt-3 inline-flex items-center bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-200">
                                    Technician: {{ $row['technician'] }}
                                </div>
                            </a>
                        @empty
                            <div class="border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No upcoming AMC visits in the current filter window.</div>
                        @endforelse
                    </div>
                </div>

                <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Overdue Work</p>
                        <h3 class="mt-1 text-lg font-bold text-slate-950">Missed Or Overdue Service Tasks</h3>
                    </div>
                    <div class="mt-5 overflow-x-auto border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Visit</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Technician</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Visit Date</th>
                                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Overdue</th>
                                    <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Open</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse ($serviceReport['overdue_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <div class="font-semibold text-slate-900">{{ $row['title'] }}</div>
                                            <div class="text-xs text-slate-500">{{ $row['customer_name'] }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-700">{{ $row['technician'] }}</td>
                                        <td class="px-4 py-3 text-slate-700">{{ $row['visit_date'] }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold ring-1 {{ $toneClasses['rose'] }}">
                                                {{ $row['days_overdue'] }} days
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <a href="{{ $row['show_url'] }}" class="inline-flex items-center border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No overdue service tasks at the moment.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        @endif
    </div>

    @if (! empty($chartConfigs))
        @include('reports.partials.charts-script')
    @endif

    @if ($activeTab === 'sales-funnel' && $hasLeadGraphableCustomFields)
        <div
            data-leads-custom-fields-modal
            class="fixed inset-0 z-[90] hidden items-center justify-center p-4 sm:p-6"
            aria-modal="true"
            role="dialog"
            aria-hidden="true"
        >
            <div data-leads-custom-fields-backdrop class="absolute inset-0 bg-slate-950/50 backdrop-blur-sm"></div>

            <div class="relative z-10 w-full max-w-4xl overflow-hidden border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-100 px-6 py-5">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-violet-700">Custom Field Graphs</p>
                        <h3 class="mt-1 text-xl font-extrabold text-slate-950">Choose dropdown fields to graph</h3>
                        <p class="mt-1 text-sm text-slate-500">Only dropdown custom fields with defined options are available here.</p>
                    </div>
                    <button
                        type="button"
                        data-leads-custom-fields-close
                        class="inline-flex h-10 w-10 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-50 hover:text-slate-700"
                        aria-label="Close field picker"
                    >
                        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <form method="GET" action="{{ $formAction }}" class="max-h-[75vh] overflow-y-auto px-6 py-5">
                    <input type="hidden" name="date_from" value="{{ request('date_from') }}">
                    <input type="hidden" name="date_to" value="{{ request('date_to') }}">
                    <input type="hidden" name="project_type" value="{{ request('project_type') }}">
                    <input type="hidden" name="status" value="{{ request('status') }}">
                    <input type="hidden" name="client_id" value="{{ request('client_id') }}">

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($leadGraphableCustomFields as $field)
                            <label class="flex cursor-pointer items-start gap-3 border border-slate-200 bg-slate-50 px-4 py-4 transition hover:border-violet-300 hover:bg-violet-50">
                                <input
                                    type="checkbox"
                                    name="custom_graph_fields[]"
                                    value="{{ $field['key'] }}"
                                    @checked(in_array($field['key'], $selectedLeadCustomFieldGraphKeys, true))
                                    class="mt-0.5 h-4 w-4 border-slate-300 text-violet-600 focus:ring-violet-500"
                                >
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-slate-900">{{ $field['label'] }}</div>
                                    <div class="mt-1 text-xs text-slate-500">{{ count($field['options']) }} options</div>
                                </div>
                            </label>
                        @endforeach
                    </div>

                    <div class="mt-6 flex flex-col gap-3 border-t border-slate-100 pt-5 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-slate-500">Tip: select one or more fields, then apply to render the graphs below the standard lead charts.</p>
                        <div class="flex flex-col gap-3 sm:flex-row">
                            <button
                                type="button"
                                data-leads-custom-fields-close
                                class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                            >
                                Cancel
                            </button>
                            <button
                                type="submit"
                                class="inline-flex items-center justify-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800"
                            >
                                Apply Selection
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endif
    </div>
</x-app-layout>
