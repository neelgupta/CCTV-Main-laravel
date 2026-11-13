<x-app-layout>
    <div id="service-call-invoices-page">
    @php
        $authUser = auth()->user();
        $canExport =
            $authUser && $authUser->hasAnyPermission('support.view', 'tickets.view', 'tickets.reply', 'tickets.manage');
        $canViewAllInvoices = $authUser?->isCompanyAdmin();
        $invoiceStatusOptions = collect($statusOptions)->map(fn ($label, $value) => [
            'id' => (string) $value,
            'name' => $label,
        ])->values();
        $invoiceStaffOptions = collect($staffMembers ?? [])->map(fn ($staff) => [
            'id' => (string) $staff->id,
            'name' => $staff->name,
            'meta' => collect([$staff->email ?? null, $staff->role ?? null])->filter()->implode(' • '),
        ])->values();
    @endphp

    <x-slot name="header">
        <div class="border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold leading-tight text-slate-950">Invoice</h2>
                    <p class="mt-1 text-sm text-slate-500">Review service invoices with server-side filters and filtered
                        exports.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('service-call.invoices.create') }}" class="bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Generate Invoice</a>
                    @if($canExport)
                        <a href="{{ route('service-call.invoices.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"
                            title="Excel" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M4 4h10l6 6v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14 4v6h6"/>
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8 14l4 4m0-4l-4 4"/>
                            </svg>
                        </a>
                        <a href="{{ route('service-call.invoices.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                           title="CSV" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 4h16v16H4z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 10h16M10 4v16"/>
                                </svg>
                        </a>
                        <a href="{{ route('service-call.invoices.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"
                            title="PDF"   class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M7 3h7l5 5v13H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M14 3v5h5"/>
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 15h6M9 11h6"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="w-full px-2 py-6 sm:px-6 lg:px-8">
        <div id="service-call-page-shell">
            <div class="mb-5 border border-slate-200 bg-white p-5 shadow-sm" id="service-call-filter-wrapper">
                <form method="GET" action="{{ route('service-call.invoices.index') }}"
                    class="grid gap-4 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <label for="search"
                            class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Client
                            Search</label>
                        <input id="search" name="search" type="text" value="{{ $filters['search'] ?? '' }}"
                            placeholder="Client, company, invoice no, mobile, email"
                            class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label for="start_date"
                            class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Start
                            Date</label>
                        <input id="start_date" name="start_date" type="date"
                            value="{{ $filters['start_date'] ?? '' }}"
                            class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label for="end_date"
                            class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">End
                            Date</label>
                        <input id="end_date" name="end_date" type="date"
                            value="{{ $filters['end_date'] ?? '' }}"
                            class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <x-searchable-select
                            name="status"
                            id="status"
                            label="Invoice Status"
                            :options="$invoiceStatusOptions"
                            :selected="(string) ($filters['status'] ?? '')"
                            empty-label="All Status"
                            placeholder="Search status..."
                        />
                    </div>
                    <div>
                        @if($canViewAllInvoices)
                            <x-searchable-select
                                name="staff_id"
                                id="staff_id"
                                label="Staff"
                                :options="$invoiceStaffOptions"
                                :selected="(string) ($filters['staff_id'] ?? '')"
                                empty-label="All Staff"
                                placeholder="Search staff..."
                            />
                        @else
                            <input type="text" value="{{ $authUser?->staff?->name ?? 'Assigned invoices only' }}"
                                disabled
                                class="w-full border border-slate-300 bg-slate-50 px-4 py-2.5 text-sm text-slate-500 shadow-sm">
                        @endif
                    </div>
                    <div class="lg:col-span-6 flex items-end gap-3">
                        <button type="submit"
                            class="inline-flex items-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply
                            Filter</button>
                        <a href="{{ route('service-call.invoices.index') }}"
                            class="inline-flex items-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset
                            Filter</a>
                    </div>
                </form>
            </div>

            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Invoice #</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Ticket</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Client</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Company</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Staff</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Status</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Amount</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Date</th>
                                <th
                                    class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    View</th>
                                <th
                                    class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-white text-slate-500">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($invoices as $invoice)
                                @php
                                    $paymentStatus = (int) ($invoice->payment_status ?? -1);
                                    $ageInDays = optional($invoice->created_at)->diffInDays(now()) ?? 0;
                                    $statusLabel = match (true) {
                                        $paymentStatus === 1 => 'Paid',
                                        $paymentStatus === 2 => 'Partially Paid',
                                        $paymentStatus === 3 => 'Cancelled',
                                        $paymentStatus === 0 && $ageInDays > 30 => 'Overdue',
                                        $paymentStatus === 0 => 'Unpaid',
                                        default => 'Draft',
                                    };
                                    $badgeClass = match ($statusLabel) {
                                        'Paid' => 'bg-emerald-50 text-emerald-700',
                                        'Partially Paid' => 'bg-amber-50 text-amber-700',
                                        'Overdue' => 'bg-rose-50 text-rose-700',
                                        'Cancelled' => 'bg-slate-100 text-slate-700',
                                        'Draft' => 'bg-sky-50 text-sky-700',
                                        default => 'bg-zinc-50 text-zinc-700',
                                    };
                                @endphp
                                <tr class="hover:bg-slate-50/70">
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-slate-950">{{ $invoice->invoice_number ?: ('INV-' . $invoice->id) }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $invoice->ticket?->ticket_number ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-700">{{ $invoice->ticket?->customer_name ?? $invoice->client?->name ?? '-' }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-700">{{ $invoice->ticket?->project?->client_name ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">{{ $invoice->creator?->name ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        <span
                                            class="{{ $badgeClass }} inline-flex items-center  px-2.5 py-1 text-xs font-semibold">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-emerald-700">Rs.
                                        {{ number_format((float) ($invoice->total_amount ?? ($invoice->invoice_total ?? 0)), 2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-700">
                                        {{ optional($invoice->created_at)->format('d M Y') ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @if($invoice->ticket)
                                            <a href="{{ route('tickets.invoices.view', [$invoice->ticket, $invoice]) }}" class="inline-flex items-center border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">View</a>
                                        @elseif($invoice->invoice_id)
                                            <a href="{{ route('service-call.billing-document.show', $invoice->invoice_id) }}" class="inline-flex items-center border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">View</a>
                                        @else
                                            <span class="text-xs text-slate-400">-</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right text-sm">
                                        @if ($invoice->ticket)
                                            <div class="flex justify-end gap-2">
                                                <a href="{{ route('tickets.show', [$invoice->ticket, 'tab' => 'billing', 'document_type' => 'invoice']) }}"
                                                    class="inline-flex items-center border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Edit</a>
                                                <a href="{{ route('tickets.show', $invoice->ticket) }}"
                                                    class="inline-flex items-center border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View
                                                    Ticket</a>
                                            </div>
                                        @elseif($invoice->invoice_id)
                                            <a href="{{ route('service-call.billing-document.edit', $invoice->invoice_id) }}" class="inline-flex items-center border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Edit</a>
                                        @else
                                            <span class="text-xs text-slate-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-6 py-10 text-center text-sm text-slate-500">No
                                        invoices found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-4 py-4">
                    {{ $invoices->links() }}
                </div>
            </div>
        </div>
    </div>
    </div>
</x-app-layout>
