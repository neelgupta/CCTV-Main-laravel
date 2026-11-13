<x-app-layout>
    @php
        $authUser = auth()->user();
        $canCreateTicket = $authUser && $authUser->hasPermission('support.create');
        $canViewTickets = $authUser && ($authUser->hasPermission('support.view') || $authUser->hasPermission('support.create'));
        $canViewAmc = $authUser && $authUser->hasPermission('amc.view');
        $canManageTicket = $authUser && $authUser->hasAnyPermission('support.edit', 'tickets.manage', 'tickets.reply');
        $canDeleteTicket = $authUser && $authUser->hasAnyPermission('support.delete', 'tickets.manage');
        $canManageServiceStages = $authUser && $authUser->hasPermission('service.stages.manage');
        $priorityClasses = [
            'low' => 'bg-emerald-50 text-emerald-700 ring-emerald-100',
            'medium' => 'bg-amber-50 text-amber-700 ring-amber-100',
            'high' => 'bg-orange-50 text-orange-700 ring-orange-100',
            'urgent' => 'bg-rose-50 text-rose-700 ring-rose-100',
        ];
        $typeClasses = [
            'support' => 'bg-sky-50 text-sky-700 ring-sky-100',
            'complain' => 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-100',
        ];
    @endphp

    <x-slot name="header">
        <div class="rounded-[2rem] border border-white/70 bg-white/85 p-5 shadow-xl shadow-slate-950/5 backdrop-blur">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">Service Operations</p>
                    <h2 class="mt-1 text-2xl font-extrabold text-slate-950">Service Management</h2>
                    <p class="mt-1 text-sm text-slate-500">A single kanban board for support tickets, follow-ups, and AMC-linked service flow.</p>
                </div>

                <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <div class="flex min-h-[92px] flex-col items-center justify-center rounded-2xl bg-cyan-50 px-5 py-4 text-center ring-1 ring-cyan-100 sm:px-6 sm:py-5">
                        <h4 class="text-[10px] font-bold uppercase tracking-[0.14em] text-cyan-800">Tickets</h4>
                        <p class="mt-1 text-xl font-extrabold text-cyan-950">{{ $serviceSummary['total_tickets'] }}</p>
                    </div>
                    <div class="flex min-h-[92px] flex-col items-center justify-center rounded-2xl bg-indigo-50 px-5 py-4 text-center ring-1 ring-indigo-100 sm:px-6 sm:py-5">
                        <h4 class="text-[10px] font-bold uppercase tracking-[0.14em] text-indigo-800">Open Load</h4>
                        <p class="mt-1 text-xl font-extrabold text-indigo-950">{{ $serviceSummary['open_workload'] }}</p>
                    </div>
                    <div class="flex min-h-[92px] flex-col items-center justify-center rounded-2xl bg-rose-50 px-5 py-4 text-center ring-1 ring-rose-100 sm:px-6 sm:py-5">
                        <h4 class="text-[10px] font-bold uppercase tracking-[0.14em] text-rose-800">Urgent</h4>
                        <p class="mt-1 text-xl font-extrabold text-rose-950">{{ $serviceSummary['urgent_cases'] }}</p>
                    </div>
                    <div class="flex min-h-[92px] flex-col items-center justify-center rounded-2xl bg-emerald-50 px-5 py-4 text-center ring-1 ring-emerald-100 sm:px-6 sm:py-5">
                        <h4 class="text-[10px] font-bold uppercase tracking-[0.14em] text-emerald-800">AMC Linked</h4>
                        <p class="mt-1 text-xl font-extrabold text-emerald-950">{{ $serviceSummary['amc_linked'] }}</p>
                    </div>
                </div>

                <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                    <button type="button" id="toggle-service-filters"
                        aria-expanded="false"
                        aria-controls="service-filter-wrapper"
                        class="inline-flex w-full items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
                        title="Toggle filters" aria-label="Toggle service filters">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 01.53 1.28l-5.78 5.78v4.19a.75.75 0 01-1.14.64l-2.5-1.67a.75.75 0 01-.34-.64v-2.52L2.22 5.28A.75.75 0 012 4.75z" clip-rule="evenodd" />
                        </svg>
                    </button>

                    {{-- @if ($canManageServiceStages)
                        <a href="{{ route('settings.company.index', ['tab' => 'service-settings']) }}" class="inline-flex items-center justify-center rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-cyan-900 shadow-sm transition hover:bg-cyan-100">
                            Manage Stages
                        </a>
                    @endif

                    @if ($canCreateTicket)
                        <a href="{{ route('tickets.create') }}" class="inline-flex w-full items-center justify-center rounded-2xl bg-slate-950 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-lg shadow-slate-950/10 transition hover:bg-slate-800 sm:w-auto">
                            + New Service Ticket
                        </a>
                    @endif

                    @if ($canViewTickets)
                        <a href="{{ route('tickets.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50">
                            Support List
                        </a>
                    @endif

                    @if ($canViewAmc)
                        <a href="{{ route('amcs.index') }}" class="inline-flex items-center justify-center rounded-2xl border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-cyan-900 shadow-sm transition hover:bg-cyan-100">
                            AMC Contracts
                        </a>
                    @endif --}}
                </div>
            </div>
        </div>
    </x-slot>

    <div class="space-y-5 py-2">
        @if (session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        <div id="service-filter-wrapper" class="hidden rounded-[1.75rem] border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <form method="GET" action="{{ route('services.index') }}" class="grid gap-4 lg:grid-cols-6">
                <div class="lg:col-span-2">
                    <label for="search" class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Search All Fields</label>
                    <input id="search" name="search" type="text" value="{{ $filters['search'] }}" placeholder="Ticket, customer, AMC, assignee..."
                        class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                </div>
                <div>
                    <label for="type" class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Type</label>
                    <select id="type" name="type" class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        <option value="">All Types</option>
                        <option value="support" @selected($filters['type'] === 'support')>Support</option>
                        <option value="complain" @selected($filters['type'] === 'complain')>Complain</option>
                    </select>
                </div>
                <div>
                    <label for="priority" class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Priority</label>
                    <select id="priority" name="priority" class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        <option value="">All Priorities</option>
                        @foreach(['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $priorityValue => $priorityLabel)
                            <option value="{{ $priorityValue }}" @selected($filters['priority'] === $priorityValue)>{{ $priorityLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="assigned_to" class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Assigned</label>
                    <select id="assigned_to" name="assigned_to" class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        <option value="">All Assignees</option>
                        @foreach($assignees as $assignee)
                            <option value="{{ $assignee->id }}" @selected((string) $filters['assigned_to'] === (string) $assignee->id)>{{ $assignee->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="start_date" class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Visit From</label>
                    <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] }}"
                        class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                </div>
                <div>
                    <label for="end_date" class="mb-1 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Visit To</label>
                    <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] }}"
                        class="w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                </div>
                <div class="lg:col-span-6 flex items-end gap-3">
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply Filters</button>
                    <a href="{{ route('services.index') }}" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                </div>
            </form>
        </div>

        <div id="service-kanban-board" class="service-kanban flex items-start gap-4 overflow-x-auto pb-4">
            @foreach ($stages as $stage)
                @php
                    $stageTickets = $ticketsByStage->get($stage->key, collect());
                @endphp
                <section
                    class="flex w-full min-w-full max-w-full flex-shrink-0 snap-start flex-col rounded-[0.25rem] border p-4 shadow-sm sm:w-[330px] sm:min-w-[330px] sm:max-w-[330px]"
                    style="background-color: {{ $stage->color }}15; border-color: {{ $stage->color }}33;"
                >
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="h-3 w-3 rounded-full" style="background-color: {{ $stage->color }}"></span>
                                <h3 class="text-sm font-extrabold uppercase tracking-[0.18em] text-slate-800">{{ $stage->name }}</h3>
                                <span data-stage-count class="rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-bold text-slate-700 shadow-sm">
                                    {{ $stageTickets->count() }}
                                </span>
                            </div>
                        <p class="mt-2 text-xs leading-5 text-slate-600">{{ $stage->description }}</p>
                    </div>
                    </div>

                    <div
                        class="service-kanban-column space-y-3 pr-1 h-[68vh] max-h-[68vh] overflow-y-auto"
                        data-stage="{{ $stage->key }}"
                    >
                        @forelse ($stageTickets as $ticket)
                            @php
                                $assignedName = $ticket->assignedUser?->name ?: 'Unassigned';
                                $assignedInitials = collect(explode(' ', trim($assignedName)))
                                    ->filter()
                                    ->take(2)
                                    ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                    ->implode('');
                            @endphp
                            <article
                                class="service-ticket-card rounded-[0rem] border border-white/90 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                                data-ticket-id="{{ $ticket->getRouteKey() }}"
                                data-board-stage-url="{{ route('tickets.board-stage.update', $ticket) }}"
                            >
                                <div class="flex items-start justify-between gap-3">
                                   <div class="min-w-0">
                                        <a href="{{ route('tickets.show', $ticket) }}"
                                        class="service-ticket-open inline text-[15px] font-bold leading-6 text-slate-950 hover:text-cyan-700">
                                            {{ $ticket->subject }}
                                        </a>

                                        @if($ticket->generatedFromAmc || $ticket->amc)
                                            <span class="inline-flex ml-2 align-middle rounded-full px-2.5 py-0.5 text-[10px] font-semibold uppercase ring-1 bg-cyan-50 text-cyan-700 ring-cyan-100">
                                                AMC
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1 shrink-0">
                                        @if ($canManageTicket)
                                            <a
                                                href="{{ route('tickets.edit', $ticket) }}"
                                                class="service-ticket-action inline-flex h-8 w-8 items-center justify-center rounded-lg border border-violet-100 bg-violet-50 text-violet-600 transition hover:bg-violet-100"
                                                title="Edit ticket"
                                            >
                                               <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                            </a>
                                        @endif

                                        @if ($canDeleteTicket)
                                            <form
                                                method="POST"
                                                action="{{ route('tickets.destroy', $ticket) }}"
                                                data-swal-confirm="Delete this ticket permanently?"
                                                data-swal-title="Delete Ticket"
                                                data-swal-confirm-button="Delete"
                                                data-swal-cancel-button="Cancel"
                                                class="inline-flex"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="service-ticket-action inline-flex h-8 w-8 items-center justify-center rounded-lg border border-rose-100 bg-rose-50 text-rose-600 transition hover:bg-rose-100"
                                                    title="Delete ticket"
                                                >
                                                    <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap gap-2">
                                    <span class="inline-flex rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase ring-1 {{ $typeClasses[$ticket->type] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">
                                        {{ $ticket->type_label }}
                                    </span>


                                </div>

                                <div class="mt-4 space-y-2 text-xs text-slate-600">
                                    <div class="flex items-center justify-between gap-3">
                                        <span class="whitespace-nowrap text-[11px] text-slate-400">{{ $ticket->created_at->diffForHumans() }}</span>
                                    </div>


                                    @if ($ticket->visit_date)
                                        <div class="flex items-center justify-between gap-3">
                                            <span>Visit</span>
                                            <span class="font-semibold text-slate-700">{{ $ticket->visit_date->format('d M Y') }}</span>
                                        </div>
                                    @endif

                                </div>

                                <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                                    <div class="flex items-center -space-x-2">
                                        <div class="service-assignee-chip group relative inline-flex">
                                            <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border-2 border-white bg-indigo-500 text-xs font-bold uppercase text-white">
                                                {{ $assignedInitials ?: 'NA' }}
                                            </span>
                                            <span class="service-assignee-tooltip pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded-lg bg-slate-950 px-2.5 py-1.5 text-[11px] font-medium text-white opacity-0 shadow-lg transition duration-150 group-hover:opacity-100 group-focus-within:opacity-100">
                                                {{ $assignedName }}
                                            </span>
                                        </div>
                                        <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-full border-2 border-white bg-slate-100 px-2 text-[10px] font-bold uppercase text-slate-600">
                                            {{ $ticket->type === 'support' ? 'S' : 'C' }}
                                        </span>
                                    </div>

                                    <div class="inline-flex rounded-md bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-700">
                                        {{ ucfirst($ticket->priority) }}
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="service-lane-empty rounded-[1.3rem] border border-dashed border-white/80 bg-white/60 px-4 py-6 text-center shadow-inner">
                                <p class="text-sm font-semibold text-slate-600">No service cards here</p>
                                <p class="mt-1 text-xs leading-5 text-slate-500">Items will appear in this lane as ticket status and workload change.</p>
                            </div>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    </div>

    @push('scripts')

        <style>
            .service-kanban {
                scroll-behavior: smooth;
                scrollbar-width: thin;
            }

            .service-kanban::-webkit-scrollbar {
                height: 10px;
            }

            .service-kanban::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.45);
                border-radius: 9999px;
            }

            .service-kanban-column {
                min-height: 14rem;
            }

            .service-ticket-card {
                cursor: grab;
            }

            .service-ticket-card:active {
                cursor: grabbing;
            }

            .service-assignee-tooltip::after {
                content: '';
                position: absolute;
                left: 50%;
                top: 100%;
                width: 8px;
                height: 8px;
                background: rgb(15 23 42);
                transform: translateX(-50%) rotate(45deg);
            }

            .sortable-ghost {
                opacity: 0.45;
                transform: rotate(1deg);
            }

            .sortable-chosen {
                cursor: grabbing !important;
            }

            .sortable-drag {
                transform: rotate(1deg);
                box-shadow: 0 24px 45px -16px rgba(15, 23, 42, 0.28);
            }

            body.service-kanban-dragging,
            body.service-kanban-dragging #service-kanban-board,
            body.service-kanban-dragging #service-kanban-board * {
                cursor: grabbing !important;
                user-select: none !important;
            }
        </style>

        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const filterToggle = document.getElementById('toggle-service-filters');
                const filterWrapper = document.getElementById('service-filter-wrapper');
                const filterForm = filterWrapper?.querySelector('form');

                if (filterToggle && filterWrapper && filterForm) {
                    const hasActiveFilters = Array.from(filterForm.querySelectorAll('input, select, textarea'))
                        .some((field) => {
                            if (field.type === 'hidden') {
                                return false;
                            }

                            return String(field.value ?? '').trim() !== '';
                        });

                    const syncToggle = (open) => {
                        filterWrapper.classList.toggle('hidden', !open);
                        filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    };

                    syncToggle(hasActiveFilters);

                    filterToggle.addEventListener('click', function () {
                        syncToggle(filterWrapper.classList.contains('hidden'));
                    });
                }

                const board = document.getElementById('service-kanban-board');
                if (!board || typeof window.Sortable === 'undefined') {
                    return;
                }

                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                let isServiceKanbanDragging = false;
                let serviceKanbanAutoScrollFrame = null;
                let latestDragClientX = null;
                let suppressCardOpenUntil = 0;

                function clearActiveTextSelection() {
                    const selection = window.getSelection?.();
                    if (selection && selection.rangeCount) {
                        selection.removeAllRanges();
                    }
                }

                function getDragClientX(event) {
                    if (!event) return null;
                    if (typeof event.clientX === 'number' && event.clientX !== 0) return event.clientX;

                    const touch = event.touches?.[0] || event.changedTouches?.[0];
                    return touch && typeof touch.clientX === 'number' ? touch.clientX : null;
                }

                function trackServiceKanbanDragPointer(event) {
                    if (!isServiceKanbanDragging) return;
                    const clientX = getDragClientX(event);
                    if (clientX !== null) latestDragClientX = clientX;
                }

                function runServiceKanbanAutoScroll() {
                    if (!isServiceKanbanDragging || !board) {
                        serviceKanbanAutoScrollFrame = null;
                        return;
                    }

                    if (latestDragClientX !== null) {
                        const rect = board.getBoundingClientRect();
                        const edgeThreshold = 140;
                        const maxStep = 34;
                        let scrollDelta = 0;

                        if (latestDragClientX > rect.right - edgeThreshold) {
                            const distance = latestDragClientX - (rect.right - edgeThreshold);
                            scrollDelta = Math.min(maxStep, Math.max(8, distance * 0.28));
                        } else if (latestDragClientX < rect.left + edgeThreshold) {
                            const distance = (rect.left + edgeThreshold) - latestDragClientX;
                            scrollDelta = -Math.min(maxStep, Math.max(8, distance * 0.28));
                        }

                        if (scrollDelta !== 0) {
                            board.scrollLeft += scrollDelta;
                            clearActiveTextSelection();
                        }
                    }

                    serviceKanbanAutoScrollFrame = window.requestAnimationFrame(runServiceKanbanAutoScroll);
                }

                function startServiceKanbanDrag() {
                    isServiceKanbanDragging = true;
                    document.body.classList.add('service-kanban-dragging');
                    clearActiveTextSelection();

                    if (!serviceKanbanAutoScrollFrame) {
                        serviceKanbanAutoScrollFrame = window.requestAnimationFrame(runServiceKanbanAutoScroll);
                    }
                }

                function finishServiceKanbanDrag() {
                    isServiceKanbanDragging = false;
                    document.body.classList.remove('service-kanban-dragging');
                    suppressCardOpenUntil = Date.now() + 250;
                    latestDragClientX = null;
                    clearActiveTextSelection();

                    if (serviceKanbanAutoScrollFrame) {
                        window.cancelAnimationFrame(serviceKanbanAutoScrollFrame);
                        serviceKanbanAutoScrollFrame = null;
                    }
                }

                function refreshLaneState(lane) {
                    if (!lane) return;

                    const countBadge = lane.closest('section')?.querySelector('[data-stage-count]');
                    const cards = lane.querySelectorAll('.service-ticket-card');
                    const currentEmpty = lane.querySelector('.service-lane-empty');

                    if (countBadge) {
                        countBadge.textContent = String(cards.length);
                    }

                    if (cards.length === 0 && !currentEmpty) {
                        const emptyState = document.createElement('div');
                        emptyState.className = 'service-lane-empty rounded-[1.3rem] border border-dashed border-white/80 bg-white/60 px-4 py-6 text-center shadow-inner';
                        emptyState.innerHTML = '<p class="text-sm font-semibold text-slate-600">No service cards here</p><p class="mt-1 text-xs leading-5 text-slate-500">Items will appear in this lane as ticket status and workload change.</p>';
                        lane.appendChild(emptyState);
                    }

                    if (cards.length > 0 && currentEmpty) {
                        currentEmpty.remove();
                    }
                }

                function refreshAllLaneStates() {
                    board.querySelectorAll('.service-kanban-column').forEach(refreshLaneState);
                }

                function revertMove(moveContext) {
                    if (!moveContext?.item || !moveContext?.from) return;

                    const originChildren = Array.from(moveContext.from.children).filter((child) => child !== moveContext.item);
                    const targetIndex = typeof moveContext.oldIndex === 'number' ? moveContext.oldIndex : originChildren.length;

                    if (targetIndex >= originChildren.length) {
                        moveContext.from.appendChild(moveContext.item);
                    } else {
                        moveContext.from.insertBefore(moveContext.item, originChildren[targetIndex]);
                    }

                    refreshLaneState(moveContext.from);
                    refreshLaneState(moveContext.to);
                }

                async function updateTicketBoardStage(statusUrl, payload) {
                    const response = await fetch(statusUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(payload),
                    });

                    if (!response.ok) {
                        let message = 'Unable to move ticket right now.';

                        try {
                            const data = await response.json();
                            if (data?.message) {
                                message = data.message;
                            }
                        } catch (error) {}

                        throw new Error(message);
                    }

                    return response.json();
                }

                board.addEventListener('mousedown', function (event) {
                    if (event.target.closest('.service-ticket-card')) {
                        clearActiveTextSelection();
                    }
                });

                board.addEventListener('selectstart', function (event) {
                    if (event.target.closest('.service-ticket-card')) {
                        event.preventDefault();
                    }
                });

                board.addEventListener('click', function (event) {
                    if (Date.now() > suppressCardOpenUntil) return;

                    const openLink = event.target.closest('.service-ticket-open');
                    if (!openLink) return;

                    event.preventDefault();
                    event.stopPropagation();
                    event.stopImmediatePropagation();
                }, true);

                document.addEventListener('pointermove', trackServiceKanbanDragPointer, { passive: true });
                document.addEventListener('mousemove', trackServiceKanbanDragPointer, { passive: true });
                document.addEventListener('touchmove', trackServiceKanbanDragPointer, { passive: true });
                document.addEventListener('drag', trackServiceKanbanDragPointer, { passive: true });
                board.addEventListener('dragover', trackServiceKanbanDragPointer, { passive: true });
                document.addEventListener('selectstart', function (event) {
                    if (isServiceKanbanDragging) {
                        event.preventDefault();
                    }
                });

                refreshAllLaneStates();

                board.querySelectorAll('.service-kanban-column').forEach(function (column) {
                    new Sortable(column, {
                        group: 'service-kanban',
                        animation: 80,
                        ghostClass: 'sortable-ghost',
                        chosenClass: 'sortable-chosen',
                        dragClass: 'sortable-drag',
                        filter: '.service-ticket-action, .service-ticket-action *, form, button',
                        preventOnFilter: false,
                        forceFallback: false,
                        fallbackOnBody: false,
                        fallbackTolerance: 8,
                        emptyInsertThreshold: 20,
                        swapThreshold: 0.65,
                        scroll: true,
                        bubbleScroll: true,
                        scrollSensitivity: 120,
                        scrollSpeed: 18,

                        onStart: function (evt) {
                            startServiceKanbanDrag();

                            try {
                                const item = evt.item;
                                const dragImg = item.cloneNode(true);
                                dragImg.style.cssText = 'position:fixed;top:-9999px;left:-9999px;' +
                                    'width:' + item.offsetWidth + 'px;' +
                                    'transform:rotate(2deg) scale(1.02);' +
                                    'box-shadow:0 20px 40px -10px rgba(0,0,0,0.25),0 8px 16px -4px rgba(0,0,0,0.12);' +
                                    'border-radius:16px;background:white;opacity:1;z-index:-1;';
                                document.body.appendChild(dragImg);

                                if (evt.originalEvent && evt.originalEvent.dataTransfer) {
                                    evt.originalEvent.dataTransfer.setDragImage(
                                        dragImg,
                                        Math.floor(item.offsetWidth / 2),
                                        30
                                    );
                                }

                                setTimeout(function () {
                                    if (dragImg.parentNode) {
                                        dragImg.parentNode.removeChild(dragImg);
                                    }
                                }, 100);
                            } catch (error) {}
                        },

                        onMove: function (evt, originalEvent) {
                            trackServiceKanbanDragPointer(originalEvent);
                            return true;
                        },

                        onEnd: function (evt) {
                            finishServiceKanbanDrag();

                            if (evt.from === evt.to) {
                                refreshLaneState(evt.from);
                                return;
                            }

                            const moveContext = {
                                item: evt.item,
                                from: evt.from,
                                to: evt.to,
                                oldIndex: evt.oldIndex,
                            };

                            refreshLaneState(evt.from);
                            refreshLaneState(evt.to);

                            updateTicketBoardStage(
                                evt.item.getAttribute('data-board-stage-url'),
                                { board_stage: evt.to.getAttribute('data-stage') }
                            ).catch(function (error) {
                                revertMove(moveContext);

                                if (window.appAlert) {
                                    window.appAlert(error.message || 'Unable to move ticket right now.', 'Move Failed', 'error');
                                    return;
                                }

                                window.alert(error.message || 'Unable to move ticket right now.');
                            });
                        },
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
