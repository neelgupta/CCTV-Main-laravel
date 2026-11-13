@php
    $timeline = $amc->visitTimeline();
    $lastVisit = $timeline['lastVisit'] ?? null;
    $editableVisit = $timeline['editableVisit'] ?? null;
    $today = $timeline['today'] ?? now()->startOfDay();
    $canEditAmc = $canEditAmc ?? auth()->user()?->hasPermission('amc.edit');
    $canDeleteAmc = $canDeleteAmc ?? auth()->user()?->hasPermission('amc.delete');
    $canCreateTicket = $canCreateTicket ?? auth()->user()?->hasPermission('support.create');
    $amcLabel = $amc->customer_name ?: $amc->project_name ?: $amc->contract_no;
    $isOnCallAmc = $amc->isOnCall();
    $isServiceClosed = $amc->isServiceClosed();

    $paymentStatusStyles = [
        'paid' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
        'pending' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
    ];

    $statusKey = strtolower((string) $amc->payment_status);
    $statusClass = $paymentStatusStyles[$statusKey] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';

    $nextVisitTechnicianName = $isOnCallAmc
        ? ($amc->assignedStaff?->name ?: $amc->technician_name ?: null)
        : ($editableVisit?->technician ?: null);
    $nextVisitTechnicianInitial = $nextVisitTechnicianName
        ? strtoupper(substr($nextVisitTechnicianName, 0, 1))
        : '';
    $technicianUpdateUrl = $isOnCallAmc
        ? route('amcs.assigned.update', $amc)
        : ($editableVisit ? route('amcs.visits.technician.update', [$amc, $editableVisit]) : null);
    $technicianCurrentId = $isOnCallAmc ? ($amc->assigned_staff_id ?: '') : '';
    $canUpdateTechnician = $canEditAmc && $technicianUpdateUrl;
    $amcVisits = $amc->visits ?? collect();
    $amcPlanModeLabel = $amc->serviceModeLabel();
    $amcPlanModeClass = match ($amc->service_mode) {
        'on_call' => 'bg-amber-50 text-amber-800 ring-1 ring-amber-200',
        'hybrid' => 'bg-violet-50 text-violet-700 ring-1 ring-violet-200',
        default => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
    };
    $amcTotalVisits = (int) ($amc->total_visits ?? 0);
    $amcCompletedVisits = collect($amcVisits)
        ->filter(fn ($visit) => strtolower(trim((string) $visit->status)) === 'completed')
        ->count();
    $amcRemainingVisits = max(0, $amcTotalVisits - $amcCompletedVisits);
    $allVisitRows = $amcVisits
        ->sortBy(fn ($visit) => $visit->visit_date?->timestamp ?? PHP_INT_MAX)
        ->values()
        ->map(function ($visit) {
            return [
                'visit_no' => $visit->visit_no,
                'visit_date' => $visit->visit_date?->format('d M Y') ?: '-',
                'sort_date' => $visit->visit_date?->toDateString() ?: null,
                'status' => str((string) $visit->status)->headline()->toString(),
                'technician' => $visit->technician ?: '-',
            ];
        })->all();
    $visitStatusCounts = collect($allVisitRows)
        ->map(fn (array $visit) => strtolower((string) ($visit['status'] ?? '')))
        ->filter(fn ($status) => $status !== '')
        ->countBy()
        ->all();
@endphp

<tr
    class="hover:bg-slate-50 cursor-pointer"
    data-amc-row-id="{{ $amc->id }}"
    data-amc-url="{{ route('amcs.show', $amc) }}"
>
    <td class="px-3 py-3 text-sm font-semibold text-indigo-700 sm:px-4">{{ $amc->contract_no }}</td>
    <td class="px-3 py-3 text-sm text-slate-700 sm:px-4">{{ $amc->project_name }}</td>
    <td class="px-3 py-3 text-sm text-slate-700 sm:px-4">{{ $amc->customer_name }}</td>

    <td class="px-3 py-3 text-sm text-slate-700 sm:px-4">
        {{ $amcTypeOptions[$amc->amc_type] ?? str($amc->amc_type)->headline()->toString() }}
    </td>
    <td class="px-3 py-3 text-sm text-slate-700 sm:px-4">
        <span class="inline-flex items-center px-3 py-1 text-xs font-semibold {{ $amcPlanModeClass }}">
            {{ $amcPlanModeLabel }}
        </span>
    </td>
    <td class="px-3 py-3 text-sm whitespace-nowrap text-slate-700 sm:px-4">
        @if ($amc->isOnCall())
            <span class="text-slate-400">On Call</span>
        @else
            <span class="inline-flex items-center border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                {{ $amcRemainingVisits }} / {{ $amcTotalVisits }}
            </span>
        @endif
    </td>
    {{-- <td class="px-3 py-3 text-sm whitespace-nowrap text-slate-700 sm:px-4">
        @php
            $lastVisitDate = $lastVisit?->visit_date;
        @endphp
        @if ($lastVisitDate)
            <button
                type="button"
                class="js-amc-visit-history rounded-md text-left font-semibold text-indigo-700 underline decoration-dotted decoration-indigo-300 underline-offset-4 hover:text-indigo-900"
                data-amc-name="{{ $amcLabel }}"
                data-contract-no="{{ $amc->contract_no }}"
                data-visit-history='@json($allVisitRows)'
                data-visit-status-counts='@json($visitStatusCounts)'>
                {{ $lastVisitDate->format('d M Y') }}
            </button>
        @else
            <span class="text-slate-400">N/A</span>
        @endif
    </td>
    <td class="px-3 py-3 text-sm whitespace-nowrap text-slate-700 sm:px-4">
        @php
            $lastVisitStatus = strtolower((string) ($lastVisit?->status ?? ''));
            $lastVisitStatusClass =
                $visitStatusStyles[$lastVisitStatus] ?? 'bg-slate-100 text-slate-700 ring-1 ring-slate-200';
        @endphp
        @if ($lastVisitStatus !== '')
            <span class="inline-flex items-center px-3 py-1 text-xs font-semibold {{ $lastVisitStatusClass }}">
                {{ str($lastVisitStatus)->headline()->toString() }}
            </span>
        @else
            <span class="text-slate-400">N/A</span>
        @endif
    </td> --}}
    <td class="px-3 py-3 text-sm text-slate-700 sm:px-4">Rs. {{ number_format((float) $amc->total_amount, 2) }}</td>

    <td class="px-3 py-3 text-sm text-slate-700 sm:px-4">
        @if ($canUpdateTechnician)
            @if ($editableVisit || $isOnCallAmc)
                <button type="button"
                    class="js-amc-assign-technician inline-flex max-w-full items-center gap-2 border border-slate-200 bg-white px-3 py-2 text-left text-sm font-medium text-slate-700 transition hover:border-cyan-200 hover:bg-cyan-50/40"
                    data-assign-url="{{ $technicianUpdateUrl }}"
                    data-amc-name="{{ $amcLabel }}"
                    data-visit-date="{{ $editableVisit?->visit_date?->format('d M Y') ?: ($isOnCallAmc ? 'On Call' : 'N/A') }}"
                    data-assignment-field="{{ $isOnCallAmc ? 'assigned_staff_id' : 'technician_id' }}"
                    data-current-technician-id="{{ $technicianCurrentId }}"
                    data-current-technician-name="{{ $nextVisitTechnicianName }}">
                    @if ($nextVisitTechnicianName)
                        <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-cyan-500 text-[11px] font-bold text-white">
                            {{ $nextVisitTechnicianInitial }}
                        </span>
                        <span class="truncate">{{ $nextVisitTechnicianName }}</span>
                    @else
                        <span class="text-slate-400">Assign</span>
                    @endif
                </button>
            @else
                <span class="text-slate-400">N/A</span>
            @endif
        @else
            {{ $nextVisitTechnicianName ?: 'N/A' }}
        @endif
    </td>
    <td class="px-4 py-3 text-right">
        <div class="inline-flex items-center gap-2">
            <a href="{{ route('amcs.show', $amc) }}" target="_blank" rel="noopener" class="inline-flex h-8 w-8 items-center justify-center rounded bg-indigo-50 text-indigo-700 hover:bg-indigo-100" title="View">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                </svg>
            </a>
            @if ($canCreateTicket && ! $isServiceClosed && ($amc->isOnCall() || $amc->isHybrid() || $amc->generatesScheduledVisits()))
                <a href="{{ route('amcs.show', array_merge(['amc' => $amc], ['generate_ticket' => 1])) }}" class="inline-flex h-8 w-8 items-center justify-center rounded bg-emerald-50 text-emerald-700 hover:bg-emerald-100" title="Generate Ticket">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 5v14"></path>
                        <path d="M5 12h14"></path>
                    </svg>
                </a>
            @endif
            @if ($canEditAmc)
                @if ($isServiceClosed)
                    <button type="button" class="inline-flex h-8 w-8 items-center justify-center  bg-indigo-50 text-indigo-700 hover:bg-indigo-100" title="Edit" data-swal-message="{{ $amc->serviceClosureMessage() }}" data-swal-title="AMC Closed" data-swal-icon="warning">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                            <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z" />
                        </svg>
                    </button>
                @else
                    <a href="{{ route('amcs.edit', $amc) }}" class="inline-flex h-8 w-8 items-center justify-center  bg-indigo-50 text-indigo-700 hover:bg-indigo-100" title="Edit">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor">
                            <path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z" />
                        </svg>
                    </a>
                @endif
            @endif
            @if ($canDeleteAmc)
                <form method="POST" action="{{ route('amcs.destroy', $amc) }}" class="inline-flex" data-swal-confirm="Delete this AMC contract and all scheduled visits?" data-swal-title="Delete AMC" data-swal-confirm-button="Delete" data-swal-cancel-button="Cancel">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex h-8 w-8 items-center justify-center bg-rose-50 text-rose-700 hover:bg-rose-100" title="Delete">
                        <svg class="h-4 w-4" viewBox="0 0 640 640" fill="currentColor" aria-hidden="true">
                            <path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z" />
                        </svg>
                    </button>
                </form>
            @endif
        </div>
    </td>
</tr>
