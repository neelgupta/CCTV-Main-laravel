<x-app-layout>
    <div id="amc-show-page">
    @php
        $authUser = auth()->user();
        $canCreateTicket = $authUser && $authUser->hasPermission('support.create');
        $canEditAmc = $authUser && $authUser->hasPermission('amc.edit');
        $canDeleteAmc = $authUser && $authUser->hasPermission('amc.delete');
        $isServiceClosed = $amc->isServiceClosed();
    @endphp
    <x-slot name="header">
        <div class="flex flex-col items-center gap-3 text-center sm:flex-row sm:items-start sm:justify-between sm:text-left">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">AMC Details</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $amc->secure_reference }} • {{ $amc->customer_name }} • {{ $amc->project_name }}</p>
            </div>
            <div class="flex w-full max-w-xs flex-col gap-2 sm:w-auto sm:max-w-none sm:flex-row">
                <a href="{{ route('amcs.receipt', $amc) }}"
                    target="_blank"
                    title="Generate Receipt"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-emerald-600 hover:bg-emerald-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                            viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M9 14h6M9 10h6M7 3h10a2 2 0 012 2v16l-2-1-2 1-2-1-2 1-2-1-2 1V5a2 2 0 012-2z"/>
                        </svg>
                    </a>
               <a href="{{ route('amcs.schedule', $amc) }}"
                target="_blank"
                title="Check Schedule"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-700 hover:bg-slate-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M8 7V3m8 4V3M5 11h14M5 5h14a2 2 0 012 2v12a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/>
                    </svg>
                </a>
                @if($canEditAmc)
                    @if($isServiceClosed)
                        <button type="button" title="Edit AMC" data-swal-message="{{ $amc->serviceClosureMessage() }}" data-swal-title="AMC Closed" data-swal-icon="warning" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-indigo-600 hover:bg-indigo-50">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                        </button>
                    @else
                        <a href="{{ route('amcs.edit', $amc) }}" title="Edit AMC" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-indigo-600 hover:bg-indigo-50">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                        </a>
                    @endif
                @endif
                @if($canDeleteAmc)
                    <form method="POST" action="{{ route('amcs.destroy', $amc) }}" class="inline-flex w-full sm:w-auto" data-swal-confirm="Delete this AMC contract and all scheduled visits?" data-swal-title="Delete AMC" data-swal-confirm-button="Delete" data-swal-cancel-button="Cancel">
                        @csrf
                        @method('DELETE')
                        <button type="submit" title="Delete AMC" class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-rose-600 hover:bg-rose-50">
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                        </button>
                    </form>
                @endif
               <a href="{{ route('amcs.index') }}"
                title="Back to AMC"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 hover:bg-slate-100">
                    <svg xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                        class="h-5 w-5">
                        <path stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18" />
                    </svg>
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-4 sm:py-8">
        <div class="w-full space-y-4 px-2 sm:space-y-6 sm:px-6 lg:px-8">
    @if($isServiceClosed)
        <div class=" border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800">
            {{ $amc->serviceClosureMessage() }}
        </div>
    @endif
    @php
        $paymentStatusStyles = [
            'paid' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
            'pending' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
        ];
        $isOnCallAmc = $amc->isOnCall();
        $hasOnCallSupport = $amc->hasOnCallSupport();
        $openTicketModal = request()->boolean('generate_ticket') || $errors->has('visit_date') || $errors->has('description');
        $ticketModalDescription = old('description', '');

        $amcTasks = collect($amc->tasks ?? [])
                    ->map(function ($task) {
                        if (is_string($task) || is_numeric($task)) {
                            return trim((string) $task);
                        }

                        if (is_array($task)) {
                            foreach (['task', 'name', 'title', 'label', 'value', 'text'] as $key) {
                                if (filled($task[$key] ?? null)) {
                                    return trim((string) $task[$key]);
                                }
                            }

                            return trim((string) ($task['description'] ?? ''));
                        }

                        if (is_object($task) && method_exists($task, '__toString')) {
                            return trim((string) $task);
                        }

                        return '';
                    })
                    ->filter(fn ($task) => $task !== '')
                    ->values()
                    ->all();

        $visitInvoiceAmount = collect($amc->visits ?? [])
            ->sum(fn ($visit) => (float) ($visit->ticketInvoice?->total_amount ?? $visit->ticketInvoice?->invoice_total ?? 0));
        $visitInvoiceCount = collect($amc->visits ?? [])
            ->filter(fn ($visit) => $visit->ticketInvoice !== null)
            ->count();
        $remainingVisitInvoiceAmount = max(0, (float) $amc->total_amount - $visitInvoiceAmount);
            @endphp
            <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-2">
                <div class=" border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <h3 class="text-lg font-bold text-slate-900">Customer & Contract</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Customer</dt>
                            <dd class="text-slate-900">{{ $amc->customer_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Project</dt>
                            <dd class="text-slate-900">{{ $amc->project_name }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Mobile</dt>
                            <dd class="text-slate-900">{{ $amc->mobile_number ?: '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Email</dt>
                            <dd class="text-slate-900">{{ $amc->email ?: '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">GST Number</dt>
                            <dd class="text-slate-900">{{ $amc->gst_number ?: '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">AMC Type</dt>
                            <dd class="text-slate-900">{{ $amcTypeOptions[$amc->amc_type] ?? str($amc->amc_type)->headline()->toString() }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">AMC Plan</dt>
                            <dd class="text-right text-slate-900">
                                <div>{{ $amc->plan_name ?: 'Legacy AMC' }}</div>
                                <div class="text-xs text-slate-500">{{ \App\Models\Amc::formatDurationMonths($amc->plan_duration_months) }}</div>
                                <div class="mt-1 text-xs font-semibold text-sky-700">{{ $amc->serviceModeLabel() }}</div>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Start Date</dt>
                            <dd class="text-slate-900">{{ $amc->start_date?->format('d M Y') }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">End Date</dt>
                            <dd class="text-slate-900">{{ $amc->end_date?->format('d M Y') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <h3 class="text-lg font-bold text-slate-900">System & Payment</h3>
                    <dl class="mt-4 space-y-3 text-sm">
                        {{-- <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Total Cameras</dt>
                            <dd class="text-slate-900">{{ $amc->total_cameras }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Camera Types</dt>
                            <dd class="text-right text-slate-900">
                                {{ collect($amc->camera_types ?? [])->map(fn ($type) => $amcCameraTypeOptions[$type] ?? str($type)->headline()->toString())->join(', ') ?: '-' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">DVR/NVR Model</dt>
                            <dd class="text-slate-900">{{ $amc->dvr_nvr_model ?: '-' }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Hard Disk Size</dt>
                            <dd class="text-slate-900">{{ $amc->hard_disk_size ?: '-' }}</dd>
                        </div> --}}
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">AMC Amount</dt>
                            <dd class="text-slate-900">Rs. {{ number_format((float) $amc->amc_amount, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Discount</dt>
                            <dd class="text-slate-900">Rs. {{ number_format((float) $amc->discount, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">GST %</dt>
                            <dd class="text-slate-900">{{ number_format((float) $amc->tax_percent, 2) }}%</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Total Amount</dt>
                            <dd class="text-slate-900">Rs. {{ number_format((float) $amc->total_amount, 2) }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Payment Mode</dt>
                            <dd class="text-slate-900">{{ $amcPaymentModeOptions[$amc->payment_mode] ?? str($amc->payment_mode)->headline()->toString() }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="font-semibold text-slate-600">Payment Status</dt>
                            @php
                                $statusKey = strtolower((string) $amc->payment_status);
                                $statusClass = $paymentStatusStyles[$statusKey] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
                            @endphp
                            <dd>
                                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold {{ $statusClass }}">
                                    {{ $amcPaymentStatusOptions[$amc->payment_status] ?? str($amc->payment_status)->headline()->toString() }}
                                </span>
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 border border-slate-200 bg-slate-50 p-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contract Total</div>
                                <div class="mt-1 text-lg font-bold text-slate-900">Rs. {{ number_format((float) $amc->total_amount, 2) }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Visited Invoice Amount</div>
                                <div class="mt-1 text-lg font-bold text-emerald-700">Rs. {{ number_format((float) $visitInvoiceAmount, 2) }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Remaining Balance</div>
                                <div class="mt-1 text-lg font-bold text-amber-700">Rs. {{ number_format((float) $remainingVisitInvoiceAmount, 2) }}</div>
                            </div>
                        </div>
                        <div class="mt-3 text-xs font-medium text-slate-500">
                            {{ $visitInvoiceCount }} visit invoice{{ $visitInvoiceCount === 1 ? '' : 's' }} linked to this AMC.
                        </div>
                    </div>

                </div>
            </div>

            <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Visit Schedule</h3>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ $isOnCallAmc ? 'This AMC is handled when the customer raises a service call, so no preventive visit dates are generated automatically.' : ($amc->isHybrid() ? 'Visits are generated automatically from the selected AMC plan, and this contract also includes on-call support.' : 'Visits are generated automatically from the selected AMC plan and spread across the contract period.') }}
                        </p>
                    </div>
                    <div class="text-sm text-slate-600">
                        <div>Service Mode: {{ $amc->serviceModeLabel() }}</div>
                        <div>{{ $amc->generatesScheduledVisits() ? 'Total Visits: ' . $amc->total_visits : 'Scheduled Visits: None' }}</div>
                        <div>Remaining Visits: {{ (int) ($amc->remaining_visits ?? $amc->total_visits) }}</div>
                        <div>Technician: {{ $amc->technician_name ?: 'Not assigned' }}</div>
                    </div>
                </div>

                @php
                    $visitStatusOptions = ['pending', 'scheduled', 'completed', 'cancelled'];
                @endphp

                @if($amc->visits->isNotEmpty())
                    <div class="mt-4 overflow-x-auto border border-slate-200 bg-white">
                        <table class="min-w-[920px] w-full table-fixed divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="w-16 px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600 sm:px-4 sm:py-3 sm:text-xs text-white">Visit No</th>
                                    <th class="w-40 px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600 sm:px-4 sm:py-3 sm:text-xs text-white">Date</th>
                                    <th class="w-36 px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600 sm:px-4 sm:py-3 sm:text-xs text-white">Status</th>
                                    <th class="w-40 px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600 sm:px-4 sm:py-3 sm:text-xs text-white">Technician</th>
                                    <th class="w-48 px-2 py-2 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-600 sm:px-4 sm:py-3 sm:text-xs text-white">Invoice</th>
                                    <th class="w-24 px-2 py-2 text-right text-[11px] font-semibold uppercase tracking-wide text-slate-600 sm:px-4 sm:py-3 sm:text-xs text-white">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white text-sm">
                                @foreach($amc->visits as $visit)
                                    <tr>
                                        <td class="px-2 py-2 text-slate-900 sm:px-4 sm:py-3">{{ $visit->visit_no }}</td>
                                        <td class="px-2 py-2 text-slate-700 sm:px-4 sm:py-3">
                                            @if($isServiceClosed)
                                                <span class="block w-full rounded-md bg-slate-50 px-3 py-2 text-slate-700">{{ $visit->visit_date?->format('d M Y') ?? '-' }}</span>
                                            @else
                                                <input type="date" name="visit_date" value="{{ $visit->visit_date?->toDateString() }}"
                                                    form="visit-status-form-{{ $visit->id }}"
                                                    min="{{ $amc->start_date?->toDateString() ?? now()->toDateString() }}"
                                                    class="block w-full rounded-md border-slate-300 text-sm">
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-slate-700 sm:px-4 sm:py-3">
                                            @if($isServiceClosed)
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ str($visit->status)->headline() }}</span>
                                            @else
                                                <select name="status" form="visit-status-form-{{ $visit->id }}" class="block w-full min-w-[120px] rounded-md border-slate-300 text-sm">
                                                    @foreach($visitStatusOptions as $status)
                                                        <option value="{{ $status }}" @selected($visit->status === $status)>{{ str($status)->headline() }}</option>
                                                    @endforeach
                                                </select>
                                                {{-- <input type="hidden" name="visit_type" value="{{ $visit->visit_type ?: $amc->service_mode }}" form="visit-status-form-{{ $visit->id }}">
                                                <input type="text" name="reason" placeholder="Reason for change" form="visit-status-form-{{ $visit->id }}" class="mt-2 block w-full rounded-md border-slate-300 text-sm"> --}}
                                            @endif
                                        </td>
                                        <td class="px-2 py-2 text-slate-700 sm:px-4 sm:py-3">
                                            {{ $visit->displayTechnicianName() ?: '-' }}
                                        </td>
                                        <td class="px-2 py-2 text-slate-700 sm:px-4 sm:py-3">
                                            @if($visit->ticketInvoice && $visit->ticketInvoice->invoice)
                                                <div class="space-y-2">
                                                    <div>
                                                        <div class="font-semibold text-slate-900">Rs. {{ number_format((float) ($visit->ticketInvoice->total_amount ?? $visit->ticketInvoice->invoice_total ?? 0), 2) }}</div>
                                                        <div class="text-xs text-slate-500">{{ $visit->ticketInvoice->invoice->document_number }}</div>
                                                    </div>
                                                    <div class="flex flex-wrap gap-2">
                                                        <a href="{{ route('amcs.visits.invoice.download', [$amc, $visit]) }}" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                                                            Download
                                                        </a>
                                                    </div>
                                                </div>
                                            @else
                                                <span class="text-slate-400">No invoice</span>
                                            @endif
                                        </td>
                                        <td class="whitespace-nowrap px-2 py-2 text-right sm:px-4 sm:py-3">
                                            @if($isServiceClosed)
                                                <span class="text-slate-400">Closed</span>
                                            @else
                                                <form id="visit-status-form-{{ $visit->id }}" method="POST" action="{{ route('amcs.visits.update', [$amc, $visit]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 whitespace-nowrap hover:bg-slate-50 sm:px-3 sm:py-2 sm:text-xs">
                                                        Update
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="mt-4 border border-dashed border-sky-200 bg-sky-50 px-4 py-4 text-sm text-sky-800">
                        {{ $isOnCallAmc ? 'No visits are pre-scheduled for this on-call AMC. Create support activity whenever the client contacts your team.' : ($amc->isHybrid() ? 'No visits have been generated for this AMC yet. This contract still includes on-call support.' : 'No visits have been generated for this AMC yet.') }}
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 sm:gap-6 lg:grid-cols-2">
                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <h3 class="text-lg font-bold text-slate-900">Service Scope</h3>
                    <div class="mt-4 flex flex-wrap gap-3">
                        @forelse($amc->service_scope ?? [] as $scope)
                            <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-medium text-emerald-700">
                                {{ $amcServiceScopeOptions[$scope] ?? str($scope)->headline()->toString() }}
                            </span>
                        @empty
                            <p class="text-sm text-slate-500">No service scope selected.</p>
                        @endforelse
                    </div>
                </div>

                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <h3 class="text-lg font-bold text-slate-900">Support & Terms</h3>
                    <div class="mt-4 space-y-4 text-sm">
                        @if($isOnCallAmc)
                            <div class="border border-sky-200 bg-sky-50 p-4">
                                <div class="flex flex-col gap-4">
                                    <div>
                                        <div class="font-semibold text-sky-800">On-call support assignment</div>
                                        <div class="mt-1 text-sky-700">Set the service date and technician for this on-call AMC. The linked support ticket will update automatically.</div>
                                    </div>
                                    @if($isServiceClosed)
                                        <div class="border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                            This AMC service is closed. You can't change anything in this AMC.
                                        </div>
                                    @else
                                        <form method="POST" action="{{ route('amcs.on-call-support.update', $amc) }}" class="grid gap-3 sm:grid-cols-2">
                                            @csrf
                                            @method('PATCH')
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Support Date</label>
                                                <input
                                                    type="date"
                                                    name="visit_date"
                                                    value="{{ old('visit_date', $amc->supportTicket?->visit_date?->toDateString() ?: now()->toDateString()) }}"
                                                    min="{{ now()->toDateString() }}"
                                                    class="block w-full border-slate-300 text-sm"
                                                >
                                            </div>
                                            <div>
                                                <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Technician</label>
                                                <select name="assigned_staff_id" class="block w-full border-slate-300 text-sm">
                                                    <option value="">Select technician</option>
                                                    @foreach($amcTechnicians as $technician)
                                                        <option value="{{ $technician->id }}" @selected((string) old('assigned_staff_id', $amc->assigned_staff_id) === (string) $technician->id)>
                                                            {{ $technician->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <button type="submit" class="inline-flex items-center justify-center bg-sky-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-700">
                                                    Save On-call Assignment
                                                </button>
                                            </div>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endif
                        {{-- @if($canCreateTicket && ! $isServiceClosed && ($isOnCallAmc || $amc->isHybrid() || $amc->generatesScheduledVisits()))
                            <div class="border border-emerald-200 bg-emerald-50 p-4">
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <div class="font-semibold text-emerald-800">Raise AMC Support Ticket</div>
                                        <div class="mt-1 text-emerald-700">Create a new support ticket for this {{ strtolower($amc->serviceModeLabel()) }} AMC and choose the visit date before generating it.</div>
                                    </div>
                                    <button
                                        type="button"
                                        id="open-amc-ticket-modal"
                                        class="inline-flex items-center justify-center bg-emerald-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700"
                                        data-amc-name="{{ $amc->project_name ?: $amc->customer_name ?: $amc->contract_no }}"
                                        data-ticket-url="{{ route('tickets.amc.store', $amc) }}"
                                        data-default-visit-date="{{ now()->toDateString() }}"
                                    >
                                        Generate Support Ticket
                                    </button>
                                </div>
                            </div>
                        @endif --}}
                        <div>
                            <div class="font-semibold text-slate-700">Service Timing</div>
                            <div class="mt-1 text-slate-600">{{ $amc->service_timing ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-700">Task List</div>
                            @if($amcTasks !== [])
                                <ul class="mt-1 space-y-1 text-slate-600">
                                    @foreach($amcTasks as $task)
                                        <li>{{ $loop->iteration }}. {{ $task }}</li>
                                    @endforeach
                                </ul>
                            @else
                                <div class="mt-1 text-slate-600">-</div>
                            @endif
                        </div>
                        <div>
                            <div class="font-semibold text-slate-700">Parts Replacement Policy</div>
                            <div class="mt-1 text-slate-600">{{ $amc->parts_replacement_policy ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-700">Emergency Support Terms</div>
                            <div class="mt-1 text-slate-600">{{ $amc->emergency_support_terms ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="font-semibold text-slate-700">Support Ticket</div>
                            <div class="mt-1 text-slate-600">
                                @if($amc->supportTicket)
                                    <a href="{{ route('tickets.show', $amc->supportTicket) }}" class="text-indigo-600 hover:text-indigo-800 hover:underline">
                                        {{ $amc->supportTicket->ticket_number }}
                                    </a>
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($canCreateTicket && ! $isServiceClosed && ($isOnCallAmc || $amc->isHybrid() || $amc->generatesScheduledVisits()))
        <div id="amc-ticket-modal" class="{{ $openTicketModal ? '' : 'hidden ' }}fixed inset-0 z-50 flex items-center justify-center bg-slate-950/55 px-4 py-6" aria-hidden="{{ $openTicketModal ? 'false' : 'true' }}" data-modal-open="{{ $openTicketModal ? 'true' : 'false' }}">
            <div class="absolute inset-0" data-close-amc-ticket-modal></div>
            <div class="relative z-10 w-full max-w-lg overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-2xl">
                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-slate-500">Generate Support Ticket</p>
                        <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="amc-ticket-modal-title">Select Visit Date & Description</h3>
                        <p class="mt-1 text-sm text-slate-500">Add the visit date and a short description before the ticket is created.</p>
                    </div>
                    <button type="button" data-close-amc-ticket-modal class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700" aria-label="Close ticket modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('tickets.amc.store', $amc) }}" class="space-y-5 px-5 py-5">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="amc-ticket-visit-date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Visit Date</label>
                            <input
                                id="amc-ticket-visit-date"
                                type="date"
                                name="visit_date"
                                value="{{ old('visit_date', now()->toDateString()) }}"
                                min="{{ now()->toDateString() }}"
                                class="block w-full border-slate-300 text-sm focus:border-slate-500 focus:ring-slate-500"
                                required
                            >
                            @error('visit_date')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <div class="mb-1 flex items-center justify-between">
                                <label for="amc-ticket-description" class="block text-xs font-semibold uppercase tracking-wide text-slate-500">Description</label>
                                <x-speech-to-text target="amc-ticket-description" />
                            </div>
                            <textarea
                                id="amc-ticket-description"
                                name="description"
                                rows="4"
                                class="block w-full border-slate-300 text-sm placeholder-slate-400 focus:border-slate-500 focus:ring-slate-500"
                                placeholder="Describe the issue or support request"
                                required
                            >{{ $ticketModalDescription }}</textarea>
                            @error('description')
                                <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4">
                        <button type="button" data-close-amc-ticket-modal class="inline-flex items-center justify-center border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex items-center justify-center bg-slate-950 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                            Generate Ticket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @push('scripts')
        <script>
            (function () {
                const openButton = document.getElementById('open-amc-ticket-modal');
                const modal = document.getElementById('amc-ticket-modal');

                const generateTicketUrl = new URL(window.location.href);
                const visitDateInput = document.getElementById('amc-ticket-visit-date');
                const descriptionInput = document.getElementById('amc-ticket-description');
                const titleEl = document.getElementById('amc-ticket-modal-title');
                const closeButtons = modal ? modal.querySelectorAll('[data-close-amc-ticket-modal]') : [];
                const shouldOpenOnLoad = modal?.dataset.modalOpen === 'true';

                const clearGenerateTicketQuery = () => {
                    if (!generateTicketUrl.searchParams.has('generate_ticket')) {
                        return;
                    }

                    generateTicketUrl.searchParams.delete('generate_ticket');
                    window.history.replaceState({}, '', generateTicketUrl.toString());
                };

                const openModal = () => {
                    const amcName = openButton?.dataset.amcName || 'AMC';

                    if (titleEl) {
                        titleEl.textContent = `Select Visit Date & Description - ${amcName}`;
                    }

                    if (visitDateInput && !visitDateInput.value) {
                        visitDateInput.value = openButton?.dataset.defaultVisitDate || '';
                    }

                    modal.classList.remove('hidden');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');

                    window.setTimeout(() => {
                        (visitDateInput?.value ? descriptionInput : visitDateInput)?.focus();
                    }, 50);
                };

                const closeModal = () => {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                    clearGenerateTicketQuery();
                };

                openButton?.addEventListener('click', openModal);
                closeButtons.forEach((button) => button.addEventListener('click', closeModal));

                if (shouldOpenOnLoad) {
                    openModal();
                }

                document.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && modal && !modal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
            })();
        </script>
    @endpush
    </div>
</x-app-layout>
