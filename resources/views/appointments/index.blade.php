<x-app-layout>
    @php
        $authUser = auth()->user();
        $canManageAppointments = true;
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        1 Hour Slots · Max 10 Leads / Slot
                    </span>
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-700">
                        ☕ 02:00 PM - 03:00 PM Lunch Break
                    </span>
                </div>
                <h2 class="mt-2 text-2xl font-extrabold text-slate-950">Slot-Wise Appointments</h2>
                <p class="mt-1 text-sm text-slate-500">Manage hourly customer appointments, lead capacities, fixed agendas, and lead stages from 9:00 AM to 8:00 PM.</p>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ route('leads.index') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to Leads
                </a>

                @if($canManageAppointments)
                    <button
                        type="button"
                        id="open-book-modal-btn"
                        class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700"
                    >
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Book Appointment
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800 shadow-xs">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-xs">
            <ul class="list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Top View Switcher & Date Bar -->
    <div class="mb-6 flex flex-col gap-4 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
        <!-- View Toggle Pills -->
        <div class="flex items-center gap-2 rounded-2xl bg-slate-100 p-1.5 text-sm font-semibold">
            <button
                type="button"
                id="btn-view-slots"
                class="view-toggle-btn inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-slate-900 shadow-xs transition"
                onclick="switchView('slots')"
            >
                <svg class="h-4 w-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                </svg>
                Slot Grid View
            </button>
            <button
                type="button"
                id="btn-view-calendar"
                class="view-toggle-btn inline-flex items-center gap-2 rounded-xl px-4 py-2 text-slate-600 transition hover:text-slate-900"
                onclick="switchView('calendar')"
            >
                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Calendar View
            </button>
        </div>

        <!-- Date Controls (Relevant to Slot Grid View) -->
        <div id="slots-date-bar-container" class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('appointments.index') }}" class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Date</span>
                <input
                    type="date"
                    name="date"
                    id="date-picker-input"
                    value="{{ $selectedDate }}"
                    min="{{ now()->toDateString() }}"
                    onchange="this.form.submit()"
                    class="rounded-2xl border-slate-300 text-sm font-semibold text-slate-800 shadow-xs focus:border-indigo-500 focus:ring-indigo-500"
                >

                <div class="flex items-center gap-1.5">
                    @php
                        $todayStr = now()->toDateString();
                        $tomorrowStr = now()->addDay()->toDateString();
                    @endphp
                    <a
                        href="{{ route('appointments.index', ['date' => $todayStr]) }}"
                        class="rounded-xl px-3 py-1.5 text-xs font-semibold {{ $selectedDate === $todayStr ? 'bg-indigo-600 text-white' : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}"
                    >
                        Today
                    </a>
                    <a
                        href="{{ route('appointments.index', ['date' => $tomorrowStr]) }}"
                        class="rounded-xl px-3 py-1.5 text-xs font-semibold {{ $selectedDate === $tomorrowStr ? 'bg-indigo-600 text-white' : 'border border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100' }}"
                    >
                        Tomorrow
                    </a>
                </div>
            </form>

            <div class="rounded-xl bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-800">
                Scheduled on {{ Carbon\Carbon::parse($selectedDate)->format('M d, Y') }}: <strong class="text-indigo-600">{{ $totalAppointmentsToday }}</strong>
            </div>
        </div>
    </div>

    <!-- VIEW 1: Slots Grid View -->
    <div id="slots-grid-view" class="space-y-6">
        <!-- Legend -->
        <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl bg-white px-5 py-3 text-xs font-semibold text-slate-600 shadow-2xs">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-emerald-500"></span>
                    <span>Available Slot</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-rose-500"></span>
                    <span>Full (10/10 Leads)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-full bg-slate-300"></span>
                    <span>Past Slot (Locked)</span>
                </div>
            </div>
            <span class="text-xs text-slate-400">Fixed standard agenda per slot across all companies</span>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            @foreach($slotsData as $slotItem)
                @php
                    $slot = $slotItem['slot'];
                    $isPast = $slotItem['is_past'];
                    $isFull = $slotItem['is_full'];
                    $leadsCount = $slotItem['leads_count'];
                    $maxLeads = $slotItem['max_leads'];
                    $remaining = $slotItem['remaining_seats'];
                    $appointments = $slotItem['appointments'];

                    $cardBorderClass = 'border-slate-200 bg-white';
                    $badgeClass = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                    $badgeText = $remaining . ' seats left';

                    if ($isPast) {
                        $cardBorderClass = 'border-slate-200 bg-slate-50/70 opacity-80';
                        $badgeClass = 'bg-slate-100 text-slate-500 border-slate-200';
                        $badgeText = 'Past Slot';
                    } elseif ($isFull) {
                        $cardBorderClass = 'border-rose-200 bg-rose-50/20';
                        $badgeClass = 'bg-rose-100 text-rose-700 border-rose-200';
                        $badgeText = 'Slot Full (10/10)';
                    }
                @endphp

                <div class="flex flex-col justify-between rounded-3xl border {{ $cardBorderClass }} p-6 shadow-sm transition hover:shadow-md">
                    <div>
                        <!-- Slot Header -->
                        <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="text-lg font-bold text-slate-900">{{ $slot['label'] }}</span>
                                    <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass }}">
                                        {{ $badgeText }}
                                    </span>
                                </div>
                                <div class="mt-1 flex items-center gap-1.5 text-xs font-bold text-indigo-700">
                                    <svg class="h-3.5 w-3.5 shrink-0 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <span class="slot-agenda-display" data-slot-start="{{ $slot['start_time'] }}">Agenda: {{ $slot['agenda'] }}</span>
                                    @if($canManageAppointments)
                                        <button
                                            type="button"
                                            class="open-edit-agenda-btn inline-flex items-center text-slate-400 hover:text-indigo-600 transition ml-1"
                                            data-slot-start="{{ $slot['start_time'] }}"
                                            data-slot-label="{{ $slot['label'] }}"
                                            data-slot-agenda="{{ $slot['agenda'] }}"
                                            title="Edit Slot Agenda"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-[11px] text-slate-400">Duration: 1 Hour · Capacity: {{ $leadsCount }} / {{ $maxLeads }} Leads</p>
                            </div>

                            <div>
                                @if(!$isPast && !$isFull && $canManageAppointments)
                                    <button
                                        type="button"
                                        class="open-slot-book-btn inline-flex items-center gap-1.5 rounded-xl bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 transition hover:bg-indigo-600 hover:text-white"
                                        data-slot-start="{{ $slot['start_time'] }}"
                                        data-slot-label="{{ $slot['label'] }}"
                                        data-slot-date="{{ $selectedDate }}"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                        </svg>
                                        Add Lead
                                    </button>
                                @elseif($isPast)
                                    <span class="rounded-xl bg-slate-100 px-3 py-1.5 text-xs font-medium text-slate-400">Locked</span>
                                @elseif($isFull)
                                    <span class="rounded-xl bg-rose-100 px-3 py-1.5 text-xs font-semibold text-rose-600">Full</span>
                                @endif
                            </div>
                        </div>

                        <!-- Progress bar -->
                        <div class="mt-4">
                            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                @php
                                    $fillPercent = min(100, round(($leadsCount / $maxLeads) * 100));
                                    $progressColor = $isFull ? 'bg-rose-500' : ($fillPercent >= 70 ? 'bg-amber-500' : 'bg-emerald-500');
                                @endphp
                                <div class="h-full transition-all duration-300 {{ $progressColor }}" style="width: {{ $fillPercent }}%"></div>
                            </div>
                        </div>

                        <!-- Booked Leads List -->
                        <div class="mt-4 space-y-2.5">
                            @forelse($appointments as $index => $apt)
                                <div class="flex flex-col gap-2 rounded-2xl border border-slate-200/70 bg-white p-3 shadow-2xs hover:border-slate-300 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[10px] font-bold text-slate-600">
                                                {{ $index + 1 }}
                                            </span>
                                            @if($apt->lead)
                                                <a href="{{ route('leads.show', $apt->lead) }}" class="truncate text-sm font-bold text-slate-900 hover:text-indigo-600 hover:underline">
                                                    {{ $apt->lead->client_name ?: ('Lead #' . $apt->lead_id) }}
                                                </a>
                                            @else
                                                <span class="truncate text-sm font-bold text-slate-500" title="Lead record not found or unassigned">
                                                    {{ $apt->lead_id ? ('Lead #' . $apt->lead_id) : 'Unassigned Lead' }}
                                                </span>
                                            @endif

                                            <!-- Lead Stage Selector -->
                                            <div class="inline-flex items-center gap-1">
                                                @if($canManageAppointments && $leadStatuses->isNotEmpty())
                                                    <select
                                                        class="lead-stage-select rounded-lg border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] font-bold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500"
                                                        data-appointment-id="{{ $apt->id }}"
                                                        data-update-url="{{ route('appointments.lead-status.update', $apt) }}"
                                                        data-current-status-id="{{ $apt->lead?->lead_status_id }}"
                                                    >
                                                        @foreach($leadStatuses as $statusOption)
                                                            <option
                                                                value="{{ $statusOption->id }}"
                                                                data-color="{{ $statusOption->color }}"
                                                                @selected($apt->lead?->lead_status_id == $statusOption->id)
                                                            >
                                                                Stage: {{ $statusOption->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <span class="rounded-md border px-1.5 py-0.5 text-[10px] font-semibold text-slate-700 bg-slate-100">
                                                        {{ $apt->lead?->status?->name ?? 'New Lead' }}
                                                    </span>
                                                @endif
                                            </div>

                                            @php
                                                $statusColors = [
                                                    'scheduled' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                    'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                    'cancelled' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                    'rescheduled' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                ];
                                            @endphp
                                            <span class="rounded-md border px-1.5 py-0.5 text-[10px] font-semibold {{ $statusColors[$apt->status] ?? 'bg-slate-100 text-slate-700' }}">
                                                {{ ucfirst($apt->status) }}
                                            </span>
                                        </div>

                                        <div class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                                            @if($apt->lead?->mobile)
                                                <span class="flex items-center gap-1">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                                                    {{ $apt->lead->mobile }}
                                                </span>
                                            @endif
                                            @if($apt->assignee)
                                                <span class="flex items-center gap-1 text-slate-600">
                                                    <svg class="h-3 w-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                                    Assigned: <strong>{{ $apt->assignee->name }}</strong>
                                                </span>
                                            @endif
                                            @if($apt->notes)
                                                <span class="italic text-slate-400 truncate max-w-xs" title="{{ $apt->notes }}">"{{ $apt->notes }}"</span>
                                            @endif
                                        </div>
                                    </div>

                                    @if($canManageAppointments)
                                        <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-center">
                                            @if($apt->status === 'scheduled')
                                                <form method="POST" action="{{ route('appointments.status.update', $apt) }}" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 hover:bg-emerald-100" title="Mark Completed">
                                                        ✓ Done
                                                    </button>
                                                </form>
                                            @endif

                                            <button
                                                type="button"
                                                class="open-reschedule-btn rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 hover:bg-slate-100"
                                                data-action="{{ route('appointments.reschedule', $apt) }}"
                                                data-client-name="{{ $apt->lead?->client_name ?? 'Lead' }}"
                                                data-current-date="{{ optional($apt->appointment_date)->toDateString() ?? $selectedDate }}"
                                                data-current-slot="{{ $apt->slot_start_time }}"
                                                title="Reschedule slot"
                                            >
                                                Reschedule
                                            </button>

                                            <form
                                                method="POST"
                                                action="{{ route('appointments.destroy', $apt) }}"
                                                class="inline"
                                                data-swal-confirm="Are you sure you want to cancel this appointment for {{ $apt->lead?->client_name ?? 'this lead' }}?"
                                                data-swal-title="Cancel Appointment"
                                                data-swal-confirm-button="Yes, Cancel Appointment"
                                                data-swal-cancel-button="Keep Appointment"
                                                data-swal-confirm-color="#e11d48"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-lg p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600" title="Cancel Appointment">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400">
                                    No appointments booked in this slot yet.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <!-- VIEW 2: Calendar View -->
    <div id="calendar-view" class="hidden space-y-4">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Appointment Slots Calendar</h3>
                    <p class="text-xs text-slate-500">Hourly slots (9 AM - 8 PM) showing fixed agendas and total booked leads (Max 10 / slot). Click any slot to view all appointments and update lead stages.</p>
                </div>
                <div class="flex items-center gap-3 text-xs font-semibold text-slate-600">
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-indigo-600"></span> Available</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> 7+ Leads</span>
                    <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-rose-600"></span> Full (10/10)</span>
                </div>
            </div>

            <!-- FullCalendar Container -->
            <div id="slots-fullcalendar" class="min-h-[700px] w-full"></div>
        </div>
    </div>

    <!-- Calendar Slot Detail Modal -->
    <div id="calendar-slot-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4">
        <div class="relative w-full max-w-2xl rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span id="cal-modal-slot-label" class="text-lg font-extrabold text-slate-950"></span>
                        <span id="cal-modal-capacity-badge" class="rounded-full px-2.5 py-0.5 text-xs font-bold"></span>
                    </div>
                    <p id="cal-modal-agenda" class="mt-1 text-sm font-semibold text-indigo-700"></p>
                    <p id="cal-modal-date" class="text-xs text-slate-400"></p>
                </div>
                <button type="button" id="close-cal-slot-modal" class="rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <div class="mt-4">
                <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500">Booked Leads in this Slot</h4>
                <div id="cal-modal-leads-list" class="mt-3 max-h-96 space-y-2 overflow-y-auto pr-1">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <div class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4">
                <a id="cal-modal-day-view-link" href="#" class="text-xs font-bold text-indigo-600 hover:underline">
                    Open Full Day Slot Grid &rarr;
                </a>
                <button type="button" id="close-cal-slot-modal-footer" class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Booking Modal -->
    <div id="appointment-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4">
        <div class="relative w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-950">Schedule Lead Appointment</h3>
                    <p class="text-xs text-slate-500">Pick a lead, date, and 1-hour slot (max 10 leads, excludes 2-3 PM lunch).</p>
                </div>
                <button type="button" class="close-modal-btn rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <form id="appointment-form" method="POST" action="{{ route('appointments.store') }}" class="mt-4 space-y-4">
                @csrf
                <div>
                    <div class="mb-1 flex items-center justify-between">
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600">Select Existing Lead *</label>
                        <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-[10px] font-semibold text-indigo-700">Active Leads (Excluding Won & Lost)</span>
                    </div>

                    <!-- Hidden input to submit with the form -->
                    <input type="hidden" name="lead_id" id="lead_id" required value="">

                    <!-- Custom Searchable Dropdown with Search INSIDE the popup list -->
                    <div
                        class="relative"
                        x-data="{
                            open: false,
                            query: '',
                            selectedId: '',
                            selectedLabel: '',
                            leads: {{ \Illuminate\Support\Js::encode($availableLeads->map(fn($l) => ['id' => $l->id, 'name' => $l->client_name, 'mobile' => $l->mobile ?: 'No mobile'])) }},
                            get filteredLeads() {
                                if (!this.query.trim()) return this.leads;
                                const q = this.query.toLowerCase();
                                return this.leads.filter(l => l.name.toLowerCase().includes(q) || (l.mobile && l.mobile.includes(q)));
                            },
                            selectLead(lead) {
                                this.selectedId = lead.id;
                                this.selectedLabel = `${lead.name} (${lead.mobile})`;
                                const hiddenInput = document.getElementById('lead_id');
                                if (hiddenInput) {
                                    hiddenInput.value = lead.id;
                                    hiddenInput.dispatchEvent(new Event('change'));
                                }
                                this.open = false;
                                this.query = '';
                            }
                        }"
                        @click.outside="open = false"
                    >
                        <!-- Selector Trigger Button -->
                        <button
                            type="button"
                            @click="open = !open; if(open) { $nextTick(() => $refs.searchInput.focus()); }"
                            class="flex w-full items-center justify-between rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-left text-sm text-slate-800 shadow-2xs focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                        >
                            <span x-text="selectedLabel || '-- Choose a lead --'" :class="selectedId ? 'font-medium text-slate-900' : 'text-slate-400'"></span>
                            <svg class="h-4 w-4 text-slate-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Dropdown Popup Panel (Search is INSIDE here) -->
                        <div
                            x-show="open"
                            x-transition
                            class="absolute left-0 right-0 z-50 mt-1.5 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                            style="display: none;"
                        >
                            <!-- Search inside dropdown popup -->
                            <div class="border-b border-slate-100 bg-slate-50 p-2.5">
                                <div class="relative">
                                    <input
                                        type="text"
                                        x-ref="searchInput"
                                        x-model="query"
                                        placeholder="🔍 Type to search lead..."
                                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800 placeholder:text-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
                                        autocomplete="off"
                                    >
                                </div>
                            </div>

                            <!-- List of leads -->
                            <div class="max-h-[410px] overflow-y-auto overscroll-contain p-1 text-sm">
                                <template x-for="lead in filteredLeads" :key="lead.id">
                                    <button
                                        type="button"
                                        @click="selectLead(lead)"
                                        class="flex w-full items-center justify-between rounded-xl px-3 py-2 text-left text-xs transition hover:bg-indigo-50 hover:text-indigo-900"
                                        :class="selectedId == lead.id ? 'bg-indigo-50 font-bold text-indigo-700' : 'text-slate-700'"
                                    >
                                        <span x-text="lead.name"></span>
                                        <span class="text-[11px] text-slate-400" x-text="lead.mobile"></span>
                                    </button>
                                </template>
                                <div x-show="filteredLeads.length === 0" class="py-4 text-center text-xs text-slate-400">
                                    No leads match your search
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label for="modal_appointment_date" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">Date *</label>
                        <input
                            type="date"
                            name="appointment_date"
                            id="modal_appointment_date"
                            value="{{ $selectedDate }}"
                            min="{{ now()->toDateString() }}"
                            required
                            class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                        >
                    </div>

                    <div>
                        <label for="assigned_to" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">Assign Staff</label>
                        <select name="assigned_to" id="assigned_to" class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            <option value="">Assign to me ({{ $authUser->name }})</option>
                            @foreach($staffUsers as $staff)
                                <option value="{{ $staff->id }}">{{ $staff->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="slot_start_time" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">Select 1-Hour Slot *</label>
                    <select name="slot_start_time" id="slot_start_time" required class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Select date first...</option>
                    </select>
                </div>

                <div>
                    <label for="notes" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">Agenda / Notes</label>
                    <textarea name="notes" id="notes" rows="2" placeholder="Optional notes for this lead..." class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" class="close-modal-btn rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        Confirm Appointment
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reschedule Modal -->
    <div id="reschedule-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4">
        <div class="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-950">Reschedule Appointment</h3>
                    <p class="text-xs text-slate-500">Move <strong id="reschedule-client-name" class="text-indigo-600"></strong> to a new date/slot.</p>
                </div>
                <button type="button" class="close-reschedule-btn rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <form id="reschedule-form" method="POST" action="" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label for="reschedule_date" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">New Date *</label>
                    <input
                        type="date"
                        name="appointment_date"
                        id="reschedule_date"
                        value="{{ $selectedDate }}"
                        min="{{ now()->toDateString() }}"
                        required
                        class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                </div>

                <div>
                    <label for="reschedule_slot" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-600">New Slot *</label>
                    <select name="slot_start_time" id="reschedule_slot" required class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">Loading slots...</option>
                    </select>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" class="close-reschedule-btn rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        Save Rescheduled Slot
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Slot Agenda Modal -->
    <div id="edit-agenda-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-950/50 p-4">
        <div class="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-950">Update Slot Agenda</h3>
                    <p class="text-xs text-slate-500">Edit the fixed agenda description for this slot.</p>
                </div>
                <button type="button" class="close-agenda-modal-btn rounded-full p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <form id="edit-agenda-form" method="POST" action="{{ route('appointments.agendas.update') }}" class="mt-4 space-y-4">
                @csrf
                @method('PATCH')
                <input type="hidden" name="slot_start_time" id="edit-agenda-slot-time">

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-500">Slot Timing</label>
                    <p id="edit-agenda-slot-label" class="rounded-xl bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800 border border-slate-200"></p>
                </div>

                <div>
                    <label for="edit-agenda-text" class="mb-1 block text-xs font-bold uppercase tracking-wider text-slate-700">Agenda Title / Description *</label>
                    <input
                        type="text"
                        name="agenda"
                        id="edit-agenda-text"
                        required
                        maxlength="255"
                        placeholder="e.g. Technical Site Assessment & BOQ Review"
                        class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    <p class="mt-1 text-[11px] text-slate-400">This agenda will appear on this hourly slot across all dates.</p>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                    <button type="button" class="close-agenda-modal-btn rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" id="save-agenda-btn" class="rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700">
                        Save Agenda
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FullCalendar CSS and JS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

    <script>
        let fullCalendarInstance = null;
        const leadStatusesData = @json($leadStatuses);

        function switchView(viewName) {
            const slotsView = document.getElementById('slots-grid-view');
            const calView = document.getElementById('calendar-view');
            const dateBar = document.getElementById('slots-date-bar-container');
            const btnSlots = document.getElementById('btn-view-slots');
            const btnCal = document.getElementById('btn-view-calendar');

            if (viewName === 'calendar') {
                slotsView.classList.add('hidden');
                calView.classList.remove('hidden');
                dateBar.classList.add('hidden');

                btnCal.classList.add('bg-white', 'text-slate-900', 'shadow-xs');
                btnCal.classList.remove('text-slate-600');
                btnSlots.classList.remove('bg-white', 'text-slate-900', 'shadow-xs');
                btnSlots.classList.add('text-slate-600');

                if (!fullCalendarInstance) {
                    initCalendar();
                } else {
                    fullCalendarInstance.refetchEvents();
                    setTimeout(() => fullCalendarInstance.render(), 100);
                }
            } else {
                calView.classList.add('hidden');
                slotsView.classList.remove('hidden');
                dateBar.classList.remove('hidden');

                btnSlots.classList.add('bg-white', 'text-slate-900', 'shadow-xs');
                btnSlots.classList.remove('text-slate-600');
                btnCal.classList.remove('bg-white', 'text-slate-900', 'shadow-xs');
                btnCal.classList.add('text-slate-600');
            }
        }

        function initCalendar() {
            const calEl = document.getElementById('slots-fullcalendar');
            if (!calEl) return;

            fullCalendarInstance = new FullCalendar.Calendar(calEl, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                slotMinTime: '09:00:00',
                slotMaxTime: '20:00:00',
                slotDuration: '01:00:00',
                allDaySlot: false,
                nowIndicator: true,
                events: '{{ route('appointments.calendar-events') }}',
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    openCalendarSlotModal(info.event);
                }
            });

            fullCalendarInstance.render();
        }

        function openCalendarSlotModal(event) {
            const modal = document.getElementById('calendar-slot-modal');
            const props = event.extendedProps;

            document.getElementById('cal-modal-slot-label').textContent = props.slot_label || event.title;
            document.getElementById('cal-modal-agenda').textContent = 'Agenda: ' + (props.agenda || 'Lead Consultation');
            document.getElementById('cal-modal-date').textContent = 'Date: ' + props.date;

            const badge = document.getElementById('cal-modal-capacity-badge');
            badge.textContent = `${props.leads_count} / ${props.max_leads} Leads`;
            if (props.leads_count >= props.max_leads) {
                badge.className = 'rounded-full px-2.5 py-0.5 text-xs font-bold bg-rose-100 text-rose-700 border border-rose-200';
            } else if (props.leads_count >= 7) {
                badge.className = 'rounded-full px-2.5 py-0.5 text-xs font-bold bg-amber-100 text-amber-700 border border-amber-200';
            } else {
                badge.className = 'rounded-full px-2.5 py-0.5 text-xs font-bold bg-emerald-100 text-emerald-700 border border-emerald-200';
            }

            document.getElementById('cal-modal-day-view-link').href = `{{ route('appointments.index') }}?date=${encodeURIComponent(props.date)}`;

            const leadsContainer = document.getElementById('cal-modal-leads-list');
            leadsContainer.innerHTML = '';

            if (!props.leads || props.leads.length === 0) {
                leadsContainer.innerHTML = '<div class="rounded-xl border border-dashed border-slate-200 p-4 text-center text-xs text-slate-400">No leads booked in this slot yet.</div>';
            } else {
                props.leads.forEach((leadItem, idx) => {
                    const row = document.createElement('div');
                    row.className = 'flex items-center justify-between rounded-xl border border-slate-200 bg-slate-50/60 p-3';

                    // Options for lead statuses
                    let statusOptionsHtml = '';
                    leadStatusesData.forEach(st => {
                        const isSel = (st.name.toLowerCase() === leadItem.status_name.toLowerCase()) ? 'selected' : '';
                        statusOptionsHtml += `<option value="${st.id}" ${isSel}>Stage: ${st.name}</option>`;
                    });

                    const leadTitleHtml = (leadShowUrl && leadShowUrl !== '#')
                        ? `<a href="${leadShowUrl}" class="truncate text-xs font-bold text-slate-900 hover:text-indigo-600 hover:underline">${leadItem.client_name}</a>`
                        : `<span class="truncate text-xs font-bold text-slate-600">${leadItem.client_name}</span>`;

                    row.innerHTML = `
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-700">${idx + 1}</span>
                                ${leadTitleHtml}
                                <span class="rounded px-1.5 py-0.5 text-[10px] font-semibold text-slate-600 bg-white border border-slate-200">${leadItem.appointment_status}</span>
                            </div>
                            <div class="mt-1 text-[11px] text-slate-500">
                                ${leadItem.mobile ? `<span>📞 ${leadItem.mobile}</span> · ` : ''}
                                <span>Assignee: ${leadItem.assignee_name}</span>
                            </div>
                        </div>
                        <div class="ml-3 shrink-0">
                            <select
                                class="cal-modal-stage-select rounded-lg border-slate-200 bg-white px-2 py-1 text-xs font-semibold text-slate-800 focus:border-indigo-500 focus:ring-indigo-500"
                                data-appointment-id="${leadItem.id}"
                            >
                                ${statusOptionsHtml}
                            </select>
                        </div>
                    `;
                    leadsContainer.appendChild(row);
                });

                // Attach change listeners to status dropdowns in modal
                leadsContainer.querySelectorAll('.cal-modal-stage-select').forEach(sel => {
                    sel.addEventListener('change', async (e) => {
                        const aptId = sel.dataset.appointmentId;
                        const statusId = sel.value;
                        await updateLeadStage(aptId, statusId, sel);
                        if (fullCalendarInstance) fullCalendarInstance.refetchEvents();
                    });
                });
            }

            modal.classList.remove('hidden');
        }

        async function updateLeadStage(appointmentId, statusId, selectElement) {
            const originalBg = selectElement.style.backgroundColor;
            selectElement.style.backgroundColor = '#fef3c7'; // amber highlight

            try {
                const response = await fetch(`/appointments/${appointmentId}/lead-status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ lead_status_id: statusId })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    selectElement.style.backgroundColor = '#d1fae5'; // green flash
                    setTimeout(() => { selectElement.style.backgroundColor = originalBg; }, 1000);
                } else {
                    alert(data.message || 'Failed to update lead status');
                    selectElement.style.backgroundColor = originalBg;
                }
            } catch (err) {
                alert('Network error updating lead status.');
                selectElement.style.backgroundColor = originalBg;
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const bookModal = document.getElementById('appointment-modal');
            const rescheduleModal = document.getElementById('reschedule-modal');
            const calSlotModal = document.getElementById('calendar-slot-modal');
            const openBookBtn = document.getElementById('open-book-modal-btn');
            const modalDateInput = document.getElementById('modal_appointment_date');
            const slotSelect = document.getElementById('slot_start_time');
            const rescheduleDateInput = document.getElementById('reschedule_date');
            const rescheduleSlotSelect = document.getElementById('reschedule_slot');
            const rescheduleForm = document.getElementById('reschedule-form');
            const rescheduleClientName = document.getElementById('reschedule-client-name');

            // Hook up inline lead stage dropdowns in slot grid view
            document.querySelectorAll('.lead-stage-select').forEach(sel => {
                sel.addEventListener('change', async (e) => {
                    const aptId = sel.dataset.appointmentId;
                    const statusId = sel.value;
                    await updateLeadStage(aptId, statusId, sel);
                });
            });

            // Close calendar slot modal
            document.getElementById('close-cal-slot-modal')?.addEventListener('click', () => {
                calSlotModal.classList.add('hidden');
            });
            document.getElementById('close-cal-slot-modal-footer')?.addEventListener('click', () => {
                calSlotModal.classList.add('hidden');
            });

            // Load slot options via AJAX
            async function loadSlots(dateVal, targetSelect, preselectedSlot = '') {
                targetSelect.innerHTML = '<option value="">Checking slot capacities...</option>';
                try {
                    const res = await fetch(`{{ route('appointments.slots-availability') }}?date=${encodeURIComponent(dateVal)}`);
                    const data = await res.json();
                    targetSelect.innerHTML = '';

                    if (!data.slots || data.slots.length === 0) {
                        targetSelect.innerHTML = '<option value="">No slots available</option>';
                        return;
                    }

                    let availableCount = 0;
                    data.slots.forEach(slot => {
                        const opt = document.createElement('option');
                        opt.value = slot.start_time;

                        let label = `${slot.label} - ${slot.agenda} (${slot.leads_count}/${slot.max_leads} booked)`;
                        if (slot.is_past) {
                            label += ' - Past time (Locked)';
                            opt.disabled = true;
                        } else if (slot.is_full) {
                            label += ' - Full (Max 10 reached)';
                            opt.disabled = true;
                        } else {
                            availableCount++;
                        }

                        opt.textContent = label;
                        if (preselectedSlot && (slot.start_time === preselectedSlot || slot.start_time.startsWith(preselectedSlot))) {
                            opt.selected = true;
                        }
                        targetSelect.appendChild(opt);
                    });

                    if (availableCount === 0) {
                        const opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = '-- No available upcoming slots for this date --';
                        opt.disabled = true;
                        opt.selected = true;
                        targetSelect.insertBefore(opt, targetSelect.firstChild);
                    }
                } catch (e) {
                    targetSelect.innerHTML = '<option value="">Failed to load slots</option>';
                }
            }

            modalDateInput?.addEventListener('change', () => {
                loadSlots(modalDateInput.value, slotSelect);
            });

            rescheduleDateInput?.addEventListener('change', () => {
                loadSlots(rescheduleDateInput.value, rescheduleSlotSelect);
            });

            openBookBtn?.addEventListener('click', () => {
                bookModal.classList.remove('hidden');
                loadSlots(modalDateInput.value, slotSelect);
            });

            document.querySelectorAll('.open-slot-book-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const slotStart = btn.dataset.slotStart;
                    const slotDate = btn.dataset.slotDate;
                    if (slotDate) modalDateInput.value = slotDate;
                    bookModal.classList.remove('hidden');
                    loadSlots(modalDateInput.value, slotSelect, slotStart);
                });
            });

            document.querySelectorAll('.close-modal-btn').forEach(btn => {
                btn.addEventListener('click', () => bookModal.classList.add('hidden'));
            });

            document.querySelectorAll('.open-reschedule-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    rescheduleForm.action = btn.dataset.action;
                    rescheduleClientName.textContent = btn.dataset.clientName || 'Lead';
                    rescheduleDateInput.value = btn.dataset.currentDate;
                    rescheduleModal.classList.remove('hidden');
                    loadSlots(btn.dataset.currentDate, rescheduleSlotSelect, btn.dataset.currentSlot);
                });
            });

            document.querySelectorAll('.close-reschedule-btn').forEach(btn => {
                btn.addEventListener('click', () => rescheduleModal.classList.add('hidden'));
            });

            // Edit Agenda Modal Logic
            const agendaModal = document.getElementById('edit-agenda-modal');
            const agendaForm = document.getElementById('edit-agenda-form');
            const agendaSlotTimeInput = document.getElementById('edit-agenda-slot-time');
            const agendaSlotLabel = document.getElementById('edit-agenda-slot-label');
            const agendaTextInput = document.getElementById('edit-agenda-text');
            const saveAgendaBtn = document.getElementById('save-agenda-btn');

            document.querySelectorAll('.open-edit-agenda-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    agendaSlotTimeInput.value = btn.dataset.slotStart;
                    agendaSlotLabel.textContent = btn.dataset.slotLabel;
                    agendaTextInput.value = btn.dataset.slotAgenda || '';
                    agendaModal.classList.remove('hidden');
                    setTimeout(() => agendaTextInput.focus(), 50);
                });
            });

            document.querySelectorAll('.close-agenda-modal-btn').forEach(btn => {
                btn.addEventListener('click', () => agendaModal.classList.add('hidden'));
            });

            agendaForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                saveAgendaBtn.disabled = true;
                saveAgendaBtn.textContent = 'Saving...';

                const slotStartTime = agendaSlotTimeInput.value;
                const newAgenda = agendaTextInput.value.trim();

                try {
                    const response = await fetch('{{ route('appointments.agendas.update') }}', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            slot_start_time: slotStartTime,
                            agenda: newAgenda,
                        }),
                    });

                    const data = await response.json();
                    if (response.ok && data.success) {
                        // Update text in DOM immediately
                        document.querySelectorAll(`.slot-agenda-display[data-slot-start="${slotStartTime}"]`).forEach(el => {
                            el.textContent = `Agenda: ${newAgenda}`;
                        });
                        document.querySelectorAll(`.open-edit-agenda-btn[data-slot-start="${slotStartTime}"]`).forEach(btn => {
                            btn.dataset.slotAgenda = newAgenda;
                        });
                        agendaModal.classList.add('hidden');

                        // Refresh calendar if active
                        if (fullCalendarInstance) {
                            fullCalendarInstance.refetchEvents();
                        }
                    } else {
                        alert(data.message || 'Failed to update slot agenda.');
                    }
                } catch (err) {
                    alert('An error occurred while saving the agenda.');
                } finally {
                    saveAgendaBtn.disabled = false;
                    saveAgendaBtn.textContent = 'Save Agenda';
                }
            });
        });
    </script>
</x-app-layout>
