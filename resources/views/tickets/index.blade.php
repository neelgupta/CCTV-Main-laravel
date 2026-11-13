<x-app-layout>
    @php
        $authUser = auth()->user();
        $canCreateTicket = $authUser && $authUser->hasPermission('support.create');
        $canExportTicket = $authUser && $authUser->hasPermission('support.view');
        $canManageTicket = $authUser && $authUser->hasAnyPermission('support.edit', 'tickets.manage', 'tickets.reply');
        $canDeleteTicket = $authUser && $authUser->hasAnyPermission('support.delete', 'tickets.manage');
        $canQuickAssignTicket = $authUser && ($authUser->isCompanyAdmin() || $authUser->hasAnyPermission('support.edit', 'tickets.manage'));
        $viewMode = $viewMode ?? 'list';
        $listViewUrl = route('tickets.index', array_merge(request()->except(['view', 'page']), ['view' => 'list']));
        $kanbanViewUrl = route('tickets.index', array_merge(request()->except(['view', 'page']), ['view' => 'kanban']));
        $typeClasses = [
            'support' => 'bg-sky-50 text-sky-700 ring-sky-100',
            'complain' => 'bg-fuchsia-50 text-fuchsia-700 ring-fuchsia-100',
        ];

        $quickFilters = [
            'all' => ['label' => 'All'],
            'today' => ['label' => 'Today'],
            'week' => ['label' => 'This Week'],
            'month' => ['label' => 'This Month'],
            'next_month' => ['label' => 'Next Month'],
            'repeat_yes' => ['label' => 'Repeat YES'],
            'repeat_no' => ['label' => 'Repeat NO'],
            'open' => ['label' => 'Open'],
            'closed' => ['label' => 'Closed'],
            'in_progress' => ['label' => 'In Progress'],
            'pending' => ['label' => 'Pending'],
            'resolved' => ['label' => 'Resolved'],
            'overdue' => ['label' => 'Overdue'],
            'unassigned' => ['label' => 'Unassigned'],
            'amc_generated' => ['label' => 'AMC Generated'],
            'manual_ticket' => ['label' => 'Manual Ticket'],
        ];

        $quickFilterKey = $filters['quick_filter'] ?? 'all';
        $quickFilterCount = fn (string $key) => (int) ($ticketCounts[$key] ?? 0);

        $chipBase = 'inline-flex items-center gap-2 border px-4 py-2 text-sm font-semibold transition whitespace-nowrap';
        $chipStyles = [
            'all' => [
                'inactive' => 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-950',
                'active' => 'border-slate-950 bg-slate-950 text-white shadow-sm',
                'count' => 'bg-slate-100 text-slate-600',
            ],
            'today' => [
                'inactive' => 'border-sky-200 bg-sky-50 text-sky-700 hover:bg-sky-100 hover:text-sky-950',
                'active' => 'border-sky-600 bg-sky-600 text-white shadow-sm',
                'count' => 'bg-sky-100 text-sky-700',
            ],
            'week' => [
                'inactive' => 'border-cyan-200 bg-cyan-50 text-cyan-700 hover:bg-cyan-100 hover:text-cyan-950',
                'active' => 'border-cyan-600 bg-cyan-600 text-white shadow-sm',
                'count' => 'bg-cyan-100 text-cyan-700',
            ],
            'month' => [
                'inactive' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-950',
                'active' => 'border-emerald-600 bg-emerald-600 text-white shadow-sm',
                'count' => 'bg-emerald-100 text-emerald-700',
            ],
            'next_month' => [
                'inactive' => 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100 hover:text-indigo-950',
                'active' => 'border-indigo-600 bg-indigo-600 text-white shadow-sm',
                'count' => 'bg-indigo-100 text-indigo-700',
            ],
            'repeat_yes' => [
                'inactive' => 'border-lime-200 bg-lime-50 text-lime-700 hover:bg-lime-100 hover:text-lime-950',
                'active' => 'border-lime-600 bg-lime-600 text-white shadow-sm',
                'count' => 'bg-lime-100 text-lime-700',
            ],
            'repeat_no' => [
                'inactive' => 'border-slate-200 bg-slate-50 text-slate-700 hover:bg-slate-100 hover:text-slate-950',
                'active' => 'border-slate-700 bg-slate-700 text-white shadow-sm',
                'count' => 'bg-slate-100 text-slate-600',
            ],
            'open' => [
                'inactive' => 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 hover:text-emerald-950',
                'active' => 'border-emerald-600 bg-emerald-600 text-white shadow-sm',
                'count' => 'bg-emerald-100 text-emerald-700',
            ],
            'closed' => [
                'inactive' => 'border-zinc-200 bg-zinc-50 text-zinc-700 hover:bg-zinc-100 hover:text-zinc-950',
                'active' => 'border-zinc-700 bg-zinc-700 text-white shadow-sm',
                'count' => 'bg-zinc-100 text-zinc-600',
            ],
            'in_progress' => [
                'inactive' => 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100 hover:text-violet-950',
                'active' => 'border-violet-600 bg-violet-600 text-white shadow-sm',
                'count' => 'bg-violet-100 text-violet-700',
            ],
            'pending' => [
                'inactive' => 'border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-100 hover:text-amber-950',
                'active' => 'border-amber-600 bg-amber-600 text-white shadow-sm',
                'count' => 'bg-amber-100 text-amber-700',
            ],
            'resolved' => [
                'inactive' => 'border-teal-200 bg-teal-50 text-teal-700 hover:bg-teal-100 hover:text-teal-950',
                'active' => 'border-teal-600 bg-teal-600 text-white shadow-sm',
                'count' => 'bg-teal-100 text-teal-700',
            ],
            'overdue' => [
                'inactive' => 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 hover:text-rose-950',
                'active' => 'border-rose-600 bg-rose-600 text-white shadow-sm',
                'count' => 'bg-rose-100 text-rose-700',
            ],
            'unassigned' => [
                'inactive' => 'border-stone-200 bg-stone-50 text-stone-700 hover:bg-stone-100 hover:text-stone-950',
                'active' => 'border-stone-700 bg-stone-700 text-white shadow-sm',
                'count' => 'bg-stone-100 text-stone-600',
            ],
            'amc_generated' => [
                'inactive' => 'border-cyan-200 bg-cyan-50 text-cyan-700 hover:bg-cyan-100 hover:text-cyan-950',
                'active' => 'border-cyan-600 bg-cyan-600 text-white shadow-sm',
                'count' => 'bg-cyan-100 text-cyan-700',
            ],
            'manual_ticket' => [
                'inactive' => 'border-orange-200 bg-orange-50 text-orange-700 hover:bg-orange-100 hover:text-orange-950',
                'active' => 'border-orange-600 bg-orange-600 text-white shadow-sm',
                'count' => 'bg-orange-100 text-orange-700',
            ],
        ];
    @endphp

    <x-slot name="header">
        <div class="border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold leading-tight text-slate-950">Support Tickets</h2>
                    <p class="mt-1 text-sm text-slate-500">Track issues, requests, and support conversations.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" id="toggle-ticket-filter" class="inline-flex items-center justify-center border border-slate-300 bg-white px-3 py-2.5 text-slate-700 transition hover:bg-slate-50" aria-controls="ticket-filter-wrapper" aria-expanded="false" title="Show or hide filters">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M3 5h18l-7 8v5l-4 1v-6L3 5z"></path>
                        </svg>
                        <span class="sr-only">Toggle ticket filters</span>
                    </button>
                    @if($canExportTicket)
                        <a href="{{ route('tickets.export', request()->query()) }}" class="border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Export</a>
                    @endif
                    @if($canCreateTicket)
                        <a href="{{ route('tickets.create') }}" class="bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Create Ticket</a>
                    @endif
                    <div class="inline-flex overflow-hidden border border-slate-200 bg-white shadow-sm" role="group" aria-label="Support view toggle">
                        <a href="{{ $listViewUrl }}"
                            class="inline-flex items-center justify-center px-3 py-2.5 {{ $viewMode === 'list' ? 'bg-slate-950 text-white' : 'text-slate-700 hover:bg-slate-50' }}"
                            title="List View" aria-label="List View">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M4 4h12v2H4V4zm0 5h12v2H4V9zm0 5h12v2H4v-2z" />
                            </svg>
                        </a>
                        <a href="{{ $kanbanViewUrl }}"
                            class="inline-flex items-center justify-center border-l border-slate-200 px-3 py-2.5 {{ $viewMode === 'kanban' ? 'bg-slate-950 text-white' : 'text-slate-700 hover:bg-slate-50' }}"
                            title="Kanban View" aria-label="Kanban View">
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path d="M3 3h5v5H3V3zm0 9h5v5H3v-5zm9-9h5v5h-5V3zm0 9h5v5h-5v-5z" />
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="w-full px-2 py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-6 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div id="ticket-page-shell">
            <div id="ticket-filter-wrapper" class="mb-5 hidden border border-slate-200 bg-white p-5 shadow-sm">
                <form
                    id="ticket-filter-form"
                    method="GET"
                    action="{{ route('tickets.index') }}"
                    class="grid gap-4 lg:grid-cols-6"
                    data-allow-multi-submit="1"
                    data-no-global-loader="1"
                >
                    <input type="hidden" name="view" value="{{ $viewMode }}">
                    <input type="hidden" name="quick_filter" id="quick_filter" value="{{ $quickFilterKey !== 'all' ? $quickFilterKey : '' }}">
                    @if(!empty($filters['type']))
                        <input type="hidden" name="type" value="{{ $filters['type'] }}">
                    @endif

                    <div class="lg:col-span-2">
                        <label for="search" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search All Fields</label>
                        <input
                            id="search"
                            name="search"
                            type="text"
                            value="{{ $filters['search'] }}"
                            placeholder="Ticket, customer, assignee, status, AMC..."
                            class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0"
                        >
                    </div>

                    <div>
                        <label for="priority" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Priority</label>
                        <select id="priority" name="priority" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                            <option value="">All Priorities</option>
                            @foreach($priorityOptions as $priorityValue => $priorityLabel)
                                <option value="{{ $priorityValue }}" @selected($filters['priority'] === $priorityValue)>{{ $priorityLabel }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="assigned_to" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Assigned Employee</label>
                        <select id="assigned_to" name="assigned_to" class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                            <option value="">All Employees</option>
                            @foreach($assignees as $assignee)
                                <option value="{{ $assignee->id }}" @selected((string) $filters['assigned_to'] === (string) $assignee->id)>{{ $assignee->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-end gap-3 lg:col-span-6">
                        <button type="submit" class="inline-flex items-center bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply Filters</button>
                        <a href="{{ route('tickets.index') }}" class="inline-flex items-center border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    </div>

                    <div class="lg:col-span-6">
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-[0.22em] text-slate-500">Quick Filters</h3>

                            </div>
                            <div class="text-sm text-slate-500">
                                Total Tickets: <span class="font-semibold text-slate-900">{{ $quickFilterCount('all') }}</span>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-3" id="ticket-quick-filters">
                            @foreach($quickFilters as $key => $chip)
                                @php
                                    $isActive = $quickFilterKey === $key || ($quickFilterKey === 'all' && $key === 'all');
                                    $chipStyle = $chipStyles[$key] ?? $chipStyles['all'];
                                @endphp
                                <button
                                    type="button"
                                    class="{{ $chipBase }} {{ $isActive ? $chipStyle['active'] : $chipStyle['inactive'] }}"
                                    data-quick-filter="{{ $key }}"
                                    aria-pressed="{{ $isActive ? 'true' : 'false' }}"
                                >
                                    <span>{{ $chip['label'] }}</span>
                                    <span class="px-2.5 py-0.5 text-xs font-bold {{ $isActive ? 'bg-white/20 text-white' : $chipStyle['count'] }}">{{ $quickFilterCount($key) }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>

            @if($viewMode === 'list')
            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm" id="ticket-table-card">
                <div class="overflow-x-auto" id="ticket-list-wrapper">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Ticket</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Customer</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Priority</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Assigned To</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Status</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Repeat</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white  text-slate-500">Count</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Visit Date</th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Created</th>
                                <th scope="col" class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white" id="ticket-table-body">
                            @forelse($tickets as $ticket)
                                <tr class="hover:bg-slate-50/70 transition-colors">
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="font-semibold text-slate-950">{{ $ticket->subject }}</div>
                                        <div class="flex items-center gap-2 text-[11px] font-mono uppercase text-slate-400">
                                            <span>{{ $ticket->ticket_number }}</span>
                                            @if($ticket->generatedFromAmc || $ticket->amc)
                                                <span class="inline-flex items-center border border-cyan-200 bg-cyan-50 px-2 py-0.5 text-[10px] font-bold tracking-wide text-cyan-700">AMC</span>
                                            @endif
                                            @if($ticket->repeat_count)
                                                <span class="inline-flex items-center border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold tracking-wide text-amber-700">Repeat x{{ $ticket->repeat_count }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center gap-1.5 text-sm text-slate-700">
                                            {{ $ticket->customer_name }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <div class="flex items-center gap-1.5 text-sm text-slate-700">
                                            <span class="h-1.5 w-1.5 {{ $ticket->priority === 'urgent' ? 'bg-rose-500' : ($ticket->priority === 'high' ? 'bg-orange-500' : 'bg-slate-300') }}"></span>
                                            {{ ucfirst($ticket->priority) }}
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
                                        @php
                                            $hasMultiAssignments = \Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments');
                                            $assignedUsersList = $hasMultiAssignments && $ticket->relationLoaded('assignedUsers') && $ticket->assignedUsers->isNotEmpty()
                                                ? $ticket->assignedUsers
                                                : ($ticket->assignedUser ? collect([$ticket->assignedUser]) : collect());
                                            $primaryAssigned = $assignedUsersList->first();
                                            $assignedName = $primaryAssigned?->name ?: ($ticket->assignedUser?->name ?: 'Unassigned');
                                            $assignedInitials = collect(preg_split('/\s+/', trim((string) $assignedName)) ?: [])
                                                ->filter()
                                                ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
                                                ->take(2)
                                                ->join('');
                                            $assignedUsersData = $assignedUsersList->map(function ($u) {
                                                $uName = (string) $u->name;
                                                $uInitials = collect(preg_split('/\s+/', trim($uName)) ?: [])
                                                    ->filter()
                                                    ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
                                                    ->take(2)
                                                    ->join('');
                                                return [
                                                    'id' => (int) $u->id,
                                                    'name' => $uName,
                                                    'avatar_url' => $u->profile_photo_url,
                                                    'initials' => $uInitials ?: 'NA',
                                                ];
                                            })->values();
                                            $extraAssignedCount = max(0, $assignedUsersList->count() - 1);
                                        @endphp
                                        @if($canQuickAssignTicket)
                                            <button
                                                type="button"
                                                class="js-ticket-assign-trigger inline-flex items-center gap-2 border border-slate-200 bg-white px-3 py-1.5 text-left text-sm font-semibold text-slate-700 shadow-sm transition hover:border-cyan-200 hover:bg-cyan-50 hover:text-cyan-950"
                                                data-ticket-id="{{ $ticket->id }}"
                                                data-ticket-url="{{ route('tickets.assigned-technician.update', $ticket) }}"
                                                data-ticket-subject="{{ $ticket->subject }}"
                                                data-ticket-number="{{ $ticket->ticket_number }}"
                                                data-ticket-assigned-users="{{ json_encode($assignedUsersData) }}"
                                                data-ticket-assigned-id="{{ $primaryAssigned?->id ?? '' }}"
                                                data-ticket-assigned-name="{{ $assignedName }}"
                                                data-ticket-assigned-avatar="{{ $primaryAssigned?->profile_photo_url ?? '' }}"
                                                data-ticket-assigned-initials="{{ $assignedInitials ?: 'NA' }}"
                                                aria-haspopup="dialog"
                                                aria-label="Assign technician for {{ $ticket->ticket_number }}"
                                            >
                                                <span class="flex h-8 w-8 items-center justify-center overflow-hidden {{ $primaryAssigned ? 'bg-cyan-500 text-white' : 'bg-slate-100 text-slate-500' }}">
                                                    @if($primaryAssigned?->profile_photo_url)
                                                        <img src="{{ $primaryAssigned->profile_photo_url }}" alt="{{ $primaryAssigned->name }}" class="h-full w-full object-cover">
                                                    @else
                                                        {{ $assignedInitials ?: 'NA' }}
                                                    @endif
                                                </span>
                                                <span class="min-w-0 truncate">{{ $assignedName }}</span>
                                                @if($extraAssignedCount > 0)
                                                    <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 shrink-0">+{{ $extraAssignedCount }}</span>
                                                @endif
                                                <svg class="h-4 w-4 text-slate-400 shrink-0 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"></path>
                                                </svg>
                                            </button>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600">
                                                <span class="truncate">{{ $assignedName }}</span>
                                                @if($extraAssignedCount > 0)
                                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 shrink-0">+{{ $extraAssignedCount }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center border px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider
                                            {{ match($ticket->status) {
                                                'open'        => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                                'in_progress' => 'bg-blue-100 text-blue-700 border-blue-200',
                                                'answered'    => 'bg-purple-100 text-purple-700 border-purple-200',
                                                'resolved'    => 'bg-indigo-100 text-indigo-700 border-indigo-200',
                                                'hold'        => 'bg-amber-100 text-amber-700 border-amber-200',
                                                'reschedule'  => 'bg-cyan-100 text-cyan-700 border-cyan-200',
                                                'closed'      => 'bg-slate-100 text-slate-600 border-slate-200',
                                                default       => 'bg-gray-100 text-gray-600 border-gray-200',
                                            } }}">
                                            <span class="mr-1.5 h-1 w-1 bg-current"></span>
                                            {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider {{ $ticket->repeat_status ? 'border border-emerald-200 bg-emerald-100 text-emerald-700' : 'border border-slate-200 bg-slate-100 text-slate-600' }}">
                                            {{ $ticket->repeat_status ? 'Yes' : 'No' }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-slate-700">{{ $ticket->repeat_count ?: 0 }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $ticket->visit_date?->format('d M Y') ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-slate-500">{{ $ticket->created_at->format('d M Y') }}</td>
                                    <td class="whitespace-nowrap px-6 py-4 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a href="{{ route('tickets.show', $ticket) }}" class="inline-flex h-10 w-10 items-center justify-center border border-slate-200 bg-white text-slate-700 shadow-sm transition-all hover:bg-slate-50 hover:text-indigo-600" title="View Ticket" aria-label="View Ticket">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"></circle>
                                                </svg>
                                            </a>
                                            @if($canManageTicket)
                                                <a href="{{ route('tickets.edit', $ticket) }}" class="inline-flex h-10 w-10 items-center justify-center border border-slate-200 bg-white text-slate-700 shadow-sm transition-all hover:bg-slate-50 hover:text-emerald-600" title="Edit Ticket" aria-label="Edit Ticket">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                        <path d="M12 20h9" stroke-linecap="round"></path>
                                                        <path d="M16.5 3.5a2.1 2.1 0 113 3L7 19l-4 1 1-4 12.5-12.5z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    </svg>
                                                </a>
                                            @endif
                                            @if($canDeleteTicket)
                                                <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" data-swal-confirm="Delete this ticket permanently?" data-swal-title="Delete Ticket" data-swal-confirm-button="Delete" data-swal-cancel-button="Cancel">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="inline-flex h-10 w-10 items-center justify-center border border-rose-200 bg-rose-50 text-rose-600 shadow-sm transition-all hover:bg-rose-100" title="Delete Ticket" aria-label="Delete Ticket">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                            <path d="M3 6h18" stroke-linecap="round"></path>
                                                            <path d="M8 6V4h8v2" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M6 6l1 14h10l1-14" stroke-linecap="round" stroke-linejoin="round"></path>
                                                            <path d="M10 11v5M14 11v5" stroke-linecap="round"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-12 text-center">
                                        <div class="text-sm text-slate-500">No tickets created yet.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($tickets->hasPages())
                <div class="mt-6" id="ticket-pagination">
                    {{ $tickets->links() }}
                </div>
            @endif
            @else
                <div id="ticket-kanban-board" class="ticket-kanban flex items-start gap-4 overflow-x-auto pb-4">
                    @foreach($stages as $stage)
                        @php
                            $stageTickets = $ticketsByStage->get($stage->key, collect());
                        @endphp
                        <section
                            class="flex w-full min-w-full max-w-full flex-shrink-0 snap-start flex-col border p-4 shadow-sm sm:w-[330px] sm:min-w-[330px] sm:max-w-[330px]"
                            style="background-color: {{ $stage->color }}15; border-color: {{ $stage->color }}33;"
                        >
                            <div class="mb-4 flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="h-3 w-3" style="background-color: {{ $stage->color }}"></span>
                                        <h3 class="text-sm font-extrabold uppercase tracking-[0.18em] text-slate-800">{{ $stage->name }}</h3>
                                        <span data-stage-count class="bg-white/90 px-2 py-0.5 text-[11px] font-bold text-slate-700 shadow-sm">{{ $stageTickets->count() }}</span>
                                    </div>
                                    <p class="mt-2 text-xs leading-5 text-slate-600">{{ $stage->description }}</p>
                                </div>
                            </div>

                            <div class="ticket-kanban-column h-[68vh] max-h-[68vh] space-y-3 overflow-y-auto pr-1" data-stage="{{ $stage->key }}">
                                @forelse($stageTickets as $ticket)
                                    @php
                                        $hasMultiAssignments = \Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments');
                                        $assignedUsersList = $hasMultiAssignments && $ticket->relationLoaded('assignedUsers') && $ticket->assignedUsers->isNotEmpty()
                                            ? $ticket->assignedUsers
                                            : ($ticket->assignedUser ? collect([$ticket->assignedUser]) : collect());
                                        $primaryAssigned = $assignedUsersList->first();
                                        $assignedName = $primaryAssigned?->name ?: ($ticket->assignedUser?->name ?: 'Unassigned');
                                        $allAssignedNames = $assignedUsersList->isNotEmpty() ? $assignedUsersList->pluck('name')->implode(', ') : $assignedName;
                                        $assignedInitials = collect(explode(' ', trim($assignedName)))->filter()->take(2)->map(fn ($part) => strtoupper(substr($part, 0, 1)))->implode('');
                                        $extraAssignedCount = max(0, $assignedUsersList->count() - 1);
                                    @endphp
                                    <article
                                        class="ticket-kanban-card {{ $canManageTicket ? 'ticket-kanban-card-draggable' : '' }} border border-white/90 bg-white p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-lg"
                                        data-board-stage-url="{{ route('tickets.board-stage.update', $ticket) }}"
                                    >
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <a href="{{ route('tickets.show', $ticket) }}" class="ticket-card-open inline text-[15px] font-bold leading-6 text-slate-950 hover:text-cyan-700">{{ $ticket->subject }}</a>
                                                <div class="mt-1 font-mono text-[11px] uppercase text-slate-400">{{ $ticket->ticket_number }}</div>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-1">
                                                @if($canManageTicket)
                                                    <a href="{{ route('tickets.edit', $ticket) }}" class="ticket-card-action inline-flex h-8 w-8 items-center justify-center border border-violet-100 bg-violet-50 text-violet-600 transition hover:bg-violet-100" title="Edit ticket">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke-linecap="round" stroke-linejoin="round"></path><path d="M13.5 6.5l4 4" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                                    </a>
                                                @endif
                                                @if($canDeleteTicket)
                                                    <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" data-swal-confirm="Delete this ticket permanently?" data-swal-title="Delete Ticket" data-swal-confirm-button="Delete" data-swal-cancel-button="Cancel" class="inline-flex">
                                                        @csrf @method('DELETE')
                                                        <button class="ticket-card-action inline-flex h-8 w-8 items-center justify-center border border-rose-100 bg-rose-50 text-rose-600 transition hover:bg-rose-100" title="Delete ticket">
                                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 7h16" stroke-linecap="round"></path><path d="M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round"></path><path d="M6 7l1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <span class="inline-flex px-2.5 py-1 text-[10px] font-semibold uppercase ring-1 {{ $typeClasses[$ticket->type] ?? 'bg-slate-100 text-slate-700 ring-slate-200' }}">{{ $ticket->type_label }}</span>
                                            <span class="inline-flex px-2.5 py-1 text-[10px] font-semibold uppercase ring-1 {{ $ticket->priority === 'urgent' ? 'bg-rose-50 text-rose-700 ring-rose-100' : ($ticket->priority === 'high' ? 'bg-orange-50 text-orange-700 ring-orange-100' : 'bg-slate-100 text-slate-700 ring-slate-200') }}">{{ ucfirst($ticket->priority) }}</span>
                                            @if($ticket->generatedFromAmc || $ticket->amc)
                                                <span class="inline-flex bg-cyan-50 px-2.5 py-1 text-[10px] font-semibold uppercase text-cyan-700 ring-1 ring-cyan-100">AMC</span>
                                            @endif
                                        </div>

                                        <div class="mt-4 space-y-2 text-xs text-slate-600">
                                            <div class="flex items-center justify-between gap-3"><span>Customer</span><span class="max-w-[12rem] truncate font-semibold text-slate-700">{{ $ticket->customer_name }}</span></div>
                                            <div class="flex items-center justify-between gap-3"><span>Visit</span><span class="font-semibold text-slate-700">{{ $ticket->visit_date?->format('d M Y') ?? '-' }}</span></div>
                                        </div>

                                        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-100 pt-3">
                                            <div class="group relative inline-flex items-center">
                                                <span class="inline-flex h-8 w-8 items-center justify-center border-2 border-white bg-indigo-500 text-xs font-bold uppercase text-white">{{ $assignedInitials ?: 'NA' }}</span>
                                                @if($extraAssignedCount > 0)
                                                    <span class="-ml-2 inline-flex h-6 items-center justify-center border-2 border-white bg-indigo-100 px-1.5 text-[10px] font-black text-indigo-700" title="+{{ $extraAssignedCount }} more">+{{ $extraAssignedCount }}</span>
                                                @endif
                                                <span class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap bg-slate-950 px-2.5 py-1.5 text-[11px] font-medium text-white opacity-0 shadow-lg transition group-hover:opacity-100">{{ $allAssignedNames }}</span>
                                            </div>
                                            <span class="text-[11px] font-semibold text-slate-400">{{ $ticket->created_at->diffForHumans() }}</span>
                                        </div>
                                    </article>
                                @empty
                                    <div class="ticket-lane-empty border border-dashed border-white/80 bg-white/60 px-4 py-6 text-center shadow-inner">
                                        <p class="text-sm font-semibold text-slate-600">No tickets here</p>
                                        <p class="mt-1 text-xs leading-5 text-slate-500">Cards will appear here as support work moves forward.</p>
                                    </div>
                                @endforelse
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if($canQuickAssignTicket)
        <div id="ticket-assign-modal"
            class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/55 px-4 py-6"
            aria-hidden="true">
            <div class="absolute inset-0" data-close-ticket-assign-modal></div>
            <div class="relative z-10 flex max-h-[88vh] w-full max-w-2xl flex-col overflow-hidden border border-slate-200 bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Quick Assign</p>
                        <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="ticket-assign-modal-title">Assign Technicians</h3>
                        <p class="mt-1 text-sm text-slate-500" id="ticket-assign-modal-subtitle">Select technicians for this ticket.</p>
                    </div>
                    <button type="button" data-close-ticket-assign-modal
                        class="inline-flex h-9 w-9 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                        aria-label="Close technician modal">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="border-b border-slate-100 bg-slate-50/70 px-5 py-3.5" id="ticket-assign-selected-container">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Current Assigned Staff</p>
                        <span id="ticket-assign-count" class="text-xs font-semibold text-slate-500">0 selected</span>
                    </div>
                    <div id="ticket-assign-selected-badges" class="mt-2 flex flex-wrap gap-2 min-h-[28px] items-center">
                        <p class="text-xs text-slate-400 italic">Unassigned</p>
                    </div>
                </div>

                <div class="border-b border-slate-100 px-5 py-3">
                    <div class="flex items-center gap-2 border border-slate-200 bg-slate-50 px-3 py-2">
                        <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 104.213 9.05l3.618 3.619a.75.75 0 101.06-1.06l-3.619-3.62A5.5 5.5 0 009 3.5zM5 9a4 4 0 118 0 4 4 0 01-8 0z" clip-rule="evenodd" /></svg>
                        <input type="text" id="ticket-assign-search" placeholder="Search technician..."
                            class="w-full border-0 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:ring-0">
                    </div>
                </div>

                <div id="ticket-assign-list" class="flex-1 overflow-y-auto px-5 py-3 space-y-2 max-h-[46vh]">
                    @forelse ($quickAssignTechnicians as $technician)
                        <button
                            type="button"
                            class="js-ticket-technician-option flex w-full items-start gap-3 border border-slate-200 bg-white px-4 py-3 text-left transition hover:border-indigo-200 hover:bg-indigo-50/40"
                            data-technician-id="{{ $technician['id'] }}"
                            data-technician-name="{{ \Illuminate\Support\Str::lower($technician['name']) }}"
                            data-technician-display-name="{{ $technician['name'] }}"
                            data-technician-avatar="{{ $technician['avatar_url'] ?? '' }}"
                            data-technician-initials="{{ $technician['initials'] }}"
                            data-technician-search="{{ \Illuminate\Support\Str::lower($technician['name'] . ' ' . ($technician['total_active_tickets'] ?? 0) . ' ' . ($technician['open_tickets'] ?? 0) . ' ' . ($technician['in_progress_tickets'] ?? 0) . ' ' . ($technician['repeat_tickets'] ?? 0)) }}"
                        >
                            <span class="flex h-11 w-11 flex-shrink-0 items-center justify-center overflow-hidden bg-cyan-500 text-sm font-bold text-white">
                                @if(!empty($technician['avatar_url']))
                                    <img src="{{ $technician['avatar_url'] }}" alt="{{ $technician['name'] }}" class="h-full w-full object-cover">
                                @else
                                    {{ $technician['initials'] }}
                                @endif
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-3">
                                    <span class="block truncate text-sm font-semibold text-slate-900">{{ $technician['name'] }}</span>
                                    <span class="bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                        Active {{ $technician['total_active_tickets'] }}
                                    </span>
                                </span>
                            </span>
                            <span class="mt-1 inline-flex h-5 w-5 items-center justify-center border border-slate-200 text-slate-400 js-ticket-technician-check transition">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                    <path d="M20 6L9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"></path>
                                </svg>
                            </span>
                        </button>
                    @empty
                        <p class="px-1 py-4 text-center text-sm text-slate-400">No technicians available.</p>
                    @endforelse
                    <p id="ticket-assign-empty" class="hidden px-1 py-4 text-center text-sm text-slate-400">No technicians match your search.</p>
                </div>

                <div class="flex flex-col gap-3 border-t border-slate-100 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-xs text-slate-500">
                        <span class="font-semibold text-slate-700" id="ticket-assign-selected-name">No technician selected</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" data-close-ticket-assign-modal
                            class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="button" id="ticket-assign-save"
                            class="inline-flex items-center justify-center bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-60"
                            disabled>
                            Save
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @push('scripts')
        @if($viewMode === 'kanban')
            <style>
                .ticket-kanban {
                    scroll-behavior: smooth;
                    scrollbar-width: thin;
                }

                .ticket-kanban::-webkit-scrollbar {
                    height: 10px;
                }

                .ticket-kanban::-webkit-scrollbar-thumb {
                    background: rgba(148, 163, 184, 0.45);
                    border-radius: 9999px;
                }

                .ticket-kanban-column {
                    min-height: 14rem;
                }

                .ticket-kanban-card-draggable {
                    cursor: grab;
                }

                .ticket-kanban-card-draggable:active,
                body.ticket-kanban-dragging,
                body.ticket-kanban-dragging #ticket-kanban-board,
                body.ticket-kanban-dragging #ticket-kanban-board * {
                    cursor: grabbing !important;
                    user-select: none !important;
                }

                .sortable-ghost {
                    opacity: 0.45;
                    transform: rotate(1deg);
                }

                .sortable-drag {
                    transform: rotate(1deg);
                    box-shadow: 0 24px 45px -16px rgba(15, 23, 42, 0.28);
                }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
            <script>
                (function () {
                    let initializedBoard = null;

                    window.initializeTicketKanban = function () {
                    const board = document.getElementById('ticket-kanban-board');
                    if (!board || typeof window.Sortable === 'undefined' || !@json($canManageTicket)) {
                        return;
                    }
                    if (initializedBoard === board) {
                        return;
                    }
                    initializedBoard = board;

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    let suppressCardOpenUntil = 0;

                    function refreshLaneState(lane) {
                        if (!lane) return;

                        const countBadge = lane.closest('section')?.querySelector('[data-stage-count]');
                        const cards = lane.querySelectorAll('.ticket-kanban-card');
                        const emptyState = lane.querySelector('.ticket-lane-empty');

                        if (countBadge) {
                            countBadge.textContent = String(cards.length);
                        }

                        if (cards.length === 0 && !emptyState) {
                            const empty = document.createElement('div');
                            empty.className = 'ticket-lane-empty border border-dashed border-white/80 bg-white/60 px-4 py-6 text-center shadow-inner';
                            empty.innerHTML = '<p class="text-sm font-semibold text-slate-600">No tickets here</p><p class="mt-1 text-xs leading-5 text-slate-500">Cards will appear here as support work moves forward.</p>';
                            lane.appendChild(empty);
                        }

                        if (cards.length > 0 && emptyState) {
                            emptyState.remove();
                        }
                    }

                    function revertMove(context) {
                        if (!context?.item || !context?.from) return;

                        const siblings = Array.from(context.from.children).filter((child) => child !== context.item);
                        const index = typeof context.oldIndex === 'number' ? context.oldIndex : siblings.length;

                        if (index >= siblings.length) {
                            context.from.appendChild(context.item);
                        } else {
                            context.from.insertBefore(context.item, siblings[index]);
                        }

                        refreshLaneState(context.from);
                        refreshLaneState(context.to);
                    }

                    async function updateTicketStage(url, boardStage) {
                        const response = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ board_stage: boardStage }),
                        });

                        if (!response.ok) {
                            const payload = await response.json().catch(function () {
                                return {};
                            });
                            throw new Error(payload.message || 'Unable to move ticket right now.');
                        }
                    }

                    board.addEventListener('click', function (event) {
                        if (Date.now() > suppressCardOpenUntil) return;

                        const openLink = event.target.closest('.ticket-card-open');
                        if (!openLink) return;

                        event.preventDefault();
                        event.stopPropagation();
                        event.stopImmediatePropagation();
                    }, true);

                    board.querySelectorAll('.ticket-kanban-column').forEach(function (column) {
                        new Sortable(column, {
                            group: 'ticket-kanban',
                            animation: 100,
                            ghostClass: 'sortable-ghost',
                            dragClass: 'sortable-drag',
                            filter: '.ticket-card-action, .ticket-card-action *, form, button',
                            preventOnFilter: false,
                            emptyInsertThreshold: 20,
                            scroll: true,
                            bubbleScroll: true,
                            scrollSensitivity: 120,
                            scrollSpeed: 18,
                            onStart: function () {
                                document.body.classList.add('ticket-kanban-dragging');
                            },
                            onEnd: function (event) {
                                document.body.classList.remove('ticket-kanban-dragging');
                                suppressCardOpenUntil = Date.now() + 250;

                                if (event.from === event.to) {
                                    refreshLaneState(event.from);
                                    return;
                                }

                                const context = {
                                    item: event.item,
                                    from: event.from,
                                    to: event.to,
                                    oldIndex: event.oldIndex,
                                };

                                refreshLaneState(event.from);
                                refreshLaneState(event.to);

                                updateTicketStage(
                                    event.item.getAttribute('data-board-stage-url'),
                                    event.to.getAttribute('data-stage')
                                ).catch(function (error) {
                                    revertMove(context);

                                    if (window.appAlert) {
                                        window.appAlert(error.message || 'Unable to move ticket right now.', 'Move Failed', 'error');
                                        return;
                                    }

                                    window.alert(error.message || 'Unable to move ticket right now.');
                                });
                            },
                        });
                    });
                    };

                    document.addEventListener('DOMContentLoaded', window.initializeTicketKanban);
                })();
            </script>
        @endif
        <script>
            (function () {
                const shellId = 'ticket-page-shell';
                const filterWrapperId = 'ticket-filter-wrapper';
                const filterFormId = 'ticket-filter-form';
                const quickFilterInputId = 'quick_filter';

                let shell = document.getElementById(shellId);
                const toggleButton = document.getElementById('toggle-ticket-filter');
                const filterWrapper = document.getElementById(filterWrapperId);
                const assignModal = document.getElementById('ticket-assign-modal');
                const assignSearch = document.getElementById('ticket-assign-search');
                const assignList = document.getElementById('ticket-assign-list');
                const assignSave = document.getElementById('ticket-assign-save');
                const assignSelectedName = document.getElementById('ticket-assign-selected-name');
                const assignSelectedBadges = document.getElementById('ticket-assign-selected-badges');
                const assignCount = document.getElementById('ticket-assign-count');
                const assignEmpty = document.getElementById('ticket-assign-empty');
                const assignModalTitle = document.getElementById('ticket-assign-modal-title');
                const assignModalSubtitle = document.getElementById('ticket-assign-modal-subtitle');
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                let activeAssignButton = null;
                const selectedTechniciansMap = new Map();

                function setExpanded(hidden) {
                    if (!toggleButton || !filterWrapper) return;
                    toggleButton.setAttribute('aria-expanded', hidden ? 'false' : 'true');
                }

                if (filterWrapper && Array.from(filterWrapper.querySelectorAll('input, select, textarea')).some(function (field) {
                    return field.type !== 'hidden' && field.name && String(field.value || '').trim() !== '';
                })) {
                    filterWrapper.classList.remove('hidden');
                    setExpanded(false);
                }

                async function loadTickets(url) {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'text/html',
                        },
                    });

                    if (!response.ok) {
                        throw new Error('Unable to load tickets.');
                    }

                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const nextShell = doc.getElementById(shellId);
                    if (!nextShell || !shell) {
                        window.location.href = url;
                        return;
                    }

                    shell.outerHTML = nextShell.outerHTML;
                    shell = document.getElementById(shellId);
                    window.history.pushState({}, '', url);
                    window.initializeTicketKanban?.();
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }

                document.addEventListener('submit', function (event) {
                    const form = event.target;
                    if (!(form instanceof HTMLFormElement)) return;
                    if (form.id !== filterFormId) return;

                    event.preventDefault();
                    const url = new URL(form.action, window.location.origin);
                    const data = new FormData(form);

                    data.forEach(function (value, key) {
                        if (typeof value === 'string' && value.trim() !== '') {
                            url.searchParams.set(key, value.trim());
                        }
                    });

                    if (!url.searchParams.get('quick_filter')) {
                        url.searchParams.delete('quick_filter');
                    }

                    loadTickets(url.toString()).catch(function () {
                        window.location.href = url.toString();
                    });
                });

                document.addEventListener('click', function (event) {
                    if (event.target.closest('#toggle-ticket-filter')) {
                        const currentFilterWrapper = document.getElementById(filterWrapperId);
                        const currentToggleButton = document.getElementById('toggle-ticket-filter');
                        if (!currentFilterWrapper || !currentToggleButton) return;

                        const isHidden = currentFilterWrapper.classList.toggle('hidden');
                        currentToggleButton.setAttribute('aria-expanded', String(!isHidden));
                        return;
                    }

                    const quickFilterButton = event.target.closest('[data-quick-filter]');
                    if (quickFilterButton) {
                        event.preventDefault();
                        const form = document.getElementById(filterFormId);
                        const input = document.getElementById(quickFilterInputId);
                        if (!form || !input) {
                            return;
                        }

                        const filterValue = quickFilterButton.getAttribute('data-quick-filter') || '';
                        input.value = filterValue === 'all' ? '' : filterValue;
                        form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
                        return;
                    }

                    const paginationLink = event.target.closest('#ticket-pagination a');
                    if (paginationLink) {
                        event.preventDefault();
                        loadTickets(paginationLink.href).catch(function () {
                            window.location.href = paginationLink.href;
                        });
                    }
                });

                window.addEventListener('popstate', function () {
                    window.location.reload();
                });

                function escapeHtml(value) {
                    return String(value)
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                }

                function getTechnicianInfo(techId, fallbackName) {
                    const id = Number(techId);
                    const idStr = String(techId);
                    if (assignList) {
                        const option = assignList.querySelector(`.js-ticket-technician-option[data-technician-id="${idStr}"]`);
                        if (option) {
                            const name = option.dataset.technicianDisplayName || option.querySelector('.block.truncate')?.textContent?.trim() || fallbackName || `Technician #${id}`;
                            const avatar = option.dataset.technicianAvatar || '';
                            const initials = option.dataset.technicianInitials || (name ? name.substring(0, 2).toUpperCase() : 'NA');
                            return { id: id, name: name, avatar_url: avatar, initials: initials };
                        }
                    }
                    return {
                        id: id,
                        name: fallbackName || `Technician #${id}`,
                        avatar_url: '',
                        initials: (fallbackName ? fallbackName.substring(0, 2).toUpperCase() : 'NA'),
                    };
                }

                function renderSelectedBadges() {
                    if (!assignSelectedBadges) return;

                    const list = Array.from(selectedTechniciansMap.values());
                    if (list.length === 0) {
                        assignSelectedBadges.innerHTML = '<p class="text-xs text-slate-400 italic py-0.5">Unassigned</p>';
                    } else {
                        assignSelectedBadges.innerHTML = list.map(function (item) {
                            return `
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-200 px-3 py-1 text-xs font-semibold text-indigo-700 transition">
                                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                                    <span class="truncate max-w-[150px]">${escapeHtml(item.name)}</span>
                                    <button type="button" class="ml-1 -mr-1 inline-flex h-4 w-4 items-center justify-center rounded-full text-indigo-400 hover:bg-indigo-200 hover:text-indigo-800 transition focus:outline-none" data-remove-technician-id="${item.id}" title="Remove ${escapeHtml(item.name)}" aria-label="Remove ${escapeHtml(item.name)}">
                                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                        </svg>
                                    </button>
                                </span>
                            `;
                        }).join('');
                    }

                    if (assignCount) {
                        assignCount.textContent = list.length === 1 ? '1 selected' : `${list.length} selected`;
                    }

                    if (assignSelectedName) {
                        if (list.length === 0) {
                            assignSelectedName.textContent = 'No technician selected';
                        } else if (list.length === 1) {
                            assignSelectedName.textContent = list[0].name;
                        } else {
                            assignSelectedName.textContent = `${list.length} technicians selected: ${list.map(t => t.name).join(', ')}`;
                        }
                    }
                }

                function updateTechniciansListSelection() {
                    if (!assignList) return;

                    assignList.querySelectorAll('.js-ticket-technician-option').forEach(function (option) {
                        const id = Number(option.dataset.technicianId || 0);
                        const isSelected = selectedTechniciansMap.has(id);

                        option.classList.toggle('border-indigo-300', isSelected);
                        option.classList.toggle('bg-indigo-50/50', isSelected);
                        option.classList.toggle('ring-2', isSelected);
                        option.classList.toggle('ring-indigo-100', isSelected);
                        option.classList.toggle('border-slate-200', !isSelected);
                        option.classList.toggle('bg-white', !isSelected);

                        const check = option.querySelector('.js-ticket-technician-check');
                        if (check) {
                            check.classList.toggle('border-indigo-500', isSelected);
                            check.classList.toggle('bg-indigo-600', isSelected);
                            check.classList.toggle('text-white', isSelected);
                            check.classList.toggle('border-slate-200', !isSelected);
                            check.classList.toggle('text-slate-400', !isSelected);
                        }
                    });
                }

                function toggleTechnician(techId, techName, techAvatar, techInitials) {
                    const id = Number(techId);
                    if (!id) return;

                    if (selectedTechniciansMap.has(id)) {
                        selectedTechniciansMap.delete(id);
                    } else {
                        const info = getTechnicianInfo(id, techName);
                        selectedTechniciansMap.set(id, {
                            id: id,
                            name: info.name || techName || `Technician #${id}`,
                            avatar_url: info.avatar_url || techAvatar || '',
                            initials: info.initials || techInitials || 'NA',
                        });
                    }

                    renderSelectedBadges();
                    updateTechniciansListSelection();
                }

                function filterTechnicians(query) {
                    if (!assignList || !assignEmpty) return;

                    const normalized = String(query || '').trim().toLowerCase();
                    let visibleCount = 0;

                    assignList.querySelectorAll('.js-ticket-technician-option').forEach(function (option) {
                        const haystack = String(option.dataset.technicianSearch || option.dataset.technicianName || '').toLowerCase();
                        const show = !normalized || haystack.includes(normalized);
                        option.classList.toggle('hidden', !show);
                        if (show) {
                            visibleCount++;
                        }
                    });

                    assignEmpty.classList.toggle('hidden', visibleCount !== 0);
                }

                function renderAssignedButton(ticket) {
                    const assignedUsers = Array.isArray(ticket.assigned_users) ? ticket.assigned_users : [];
                    const firstUser = assignedUsers[0] || null;
                    const hasAssignee = Boolean(firstUser || ticket.assigned_to);
                    const name = firstUser ? firstUser.name : (ticket.assigned_to_name || 'Unassigned');
                    const initials = firstUser ? firstUser.initials : (ticket.assigned_to_initials || 'NA');
                    const avatarUrl = firstUser ? firstUser.avatar_url : (ticket.assigned_to_avatar_url || '');
                    const count = assignedUsers.length > 0 ? assignedUsers.length : (ticket.assigned_to ? 1 : 0);
                    const extraCount = count > 1 ? count - 1 : 0;

                    return `
                        <span class="flex h-8 w-8 items-center justify-center overflow-hidden ${hasAssignee ? 'bg-cyan-500 text-white' : 'bg-slate-100 text-slate-500'}">
                            ${avatarUrl ? `<img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(name)}" class="h-full w-full object-cover">` : escapeHtml(initials)}
                        </span>
                        <span class="min-w-0 truncate">${escapeHtml(name)}</span>
                        ${extraCount > 0 ? `<span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-700 shrink-0">+${extraCount}</span>` : ''}
                        <svg class="h-4 w-4 text-slate-400 shrink-0 ml-auto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"></path>
                        </svg>
                    `;
                }

                function openAssignModal(trigger) {
                    if (!assignModal || !assignSearch || !assignSave) return;

                    activeAssignButton = trigger;
                    selectedTechniciansMap.clear();

                    let existingList = [];
                    try {
                        existingList = JSON.parse(trigger.dataset.ticketAssignedUsers || '[]');
                    } catch (e) {
                        existingList = [];
                    }

                    if (!Array.isArray(existingList) || existingList.length === 0) {
                        const legacyId = trigger.dataset.ticketAssignedId;
                        if (legacyId && String(legacyId).trim() !== '') {
                            existingList = [{
                                id: Number(legacyId),
                                name: trigger.dataset.ticketAssignedName || 'Assigned Technician',
                                avatar_url: trigger.dataset.ticketAssignedAvatar || '',
                                initials: trigger.dataset.ticketAssignedInitials || 'NA',
                            }];
                        }
                    }

                    existingList.forEach(function (tech) {
                        const id = Number(tech.id);
                        if (id) {
                            const info = getTechnicianInfo(id, tech.name);
                            selectedTechniciansMap.set(id, {
                                id: id,
                                name: info.name || tech.name || `Technician #${id}`,
                                avatar_url: info.avatar_url || tech.avatar_url || '',
                                initials: info.initials || tech.initials || 'NA',
                            });
                        }
                    });

                    if (assignModalTitle) {
                        assignModalTitle.textContent = `Assign Technicians - ${trigger.dataset.ticketNumber || ''}`;
                    }

                    if (assignModalSubtitle) {
                        assignModalSubtitle.textContent = trigger.dataset.ticketSubject || 'Select technicians for this ticket.';
                    }

                    assignSearch.value = '';
                    filterTechnicians('');
                    renderSelectedBadges();
                    updateTechniciansListSelection();
                    assignSave.disabled = false;

                    assignModal.classList.remove('hidden');
                    assignModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');

                    window.setTimeout(function () {
                        assignSearch.focus();
                    }, 50);
                }

                function closeAssignModal() {
                    if (!assignModal) return;

                    assignModal.classList.add('hidden');
                    assignModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                    activeAssignButton = null;
                    selectedTechniciansMap.clear();
                }

                function updateAssignedRow(ticket) {
                    if (!activeAssignButton) return;

                    const row = activeAssignButton.closest('tr');
                    const assignedUsers = Array.isArray(ticket.assigned_users) ? ticket.assigned_users : [];
                    const firstUser = assignedUsers[0] || null;
                    const assignedToId = firstUser ? firstUser.id : (ticket.assigned_to || '');
                    const assignedToName = firstUser ? firstUser.name : (ticket.assigned_to_name || 'Unassigned');
                    const assignedToAvatar = firstUser ? firstUser.avatar_url : (ticket.assigned_to_avatar_url || '');
                    const assignedToInitials = firstUser ? firstUser.initials : (ticket.assigned_to_initials || 'NA');

                    activeAssignButton.dataset.ticketAssignedUsers = JSON.stringify(assignedUsers);
                    activeAssignButton.dataset.ticketAssignedId = String(assignedToId || '');
                    activeAssignButton.dataset.ticketAssignedName = assignedToName;
                    activeAssignButton.dataset.ticketAssignedAvatar = assignedToAvatar || '';
                    activeAssignButton.dataset.ticketAssignedInitials = assignedToInitials;
                    activeAssignButton.innerHTML = renderAssignedButton(ticket);

                    if (row) {
                        row.dataset.ticketAssignedId = String(assignedToId || '');
                        row.dataset.ticketAssignedName = assignedToName;
                        row.dataset.ticketAssignedUsers = JSON.stringify(assignedUsers);
                    }
                }

                document.addEventListener('click', function (event) {
                    const trigger = event.target.closest('.js-ticket-assign-trigger');
                    if (trigger) {
                        event.preventDefault();
                        openAssignModal(trigger);
                        return;
                    }

                    const closeButton = event.target.closest('[data-close-ticket-assign-modal]');
                    if (closeButton) {
                        event.preventDefault();
                        closeAssignModal();
                        return;
                    }

                    const removeBtn = event.target.closest('[data-remove-technician-id]');
                    if (removeBtn) {
                        event.preventDefault();
                        const id = Number(removeBtn.dataset.removeTechnicianId);
                        if (id && selectedTechniciansMap.has(id)) {
                            selectedTechniciansMap.delete(id);
                            renderSelectedBadges();
                            updateTechniciansListSelection();
                        }
                        return;
                    }

                    const option = event.target.closest('.js-ticket-technician-option');
                    if (option && assignModal && !assignModal.classList.contains('hidden')) {
                        event.preventDefault();
                        const id = Number(option.dataset.technicianId || 0);
                        const name = option.dataset.technicianDisplayName || option.querySelector('.block.truncate')?.textContent?.trim() || '';
                        const avatar = option.dataset.technicianAvatar || '';
                        const initials = option.dataset.technicianInitials || '';
                        toggleTechnician(id, name, avatar, initials);
                        return;
                    }
                });

                if (assignSearch) {
                    assignSearch.addEventListener('input', function () {
                        filterTechnicians(this.value);
                    });
                }

                if (assignSave) {
                    assignSave.addEventListener('click', async function () {
                        if (!activeAssignButton) {
                            return;
                        }

                        const url = activeAssignButton.dataset.ticketUrl;
                        if (!url) {
                            return;
                        }

                        const previousLabel = assignSave.textContent;
                        assignSave.disabled = true;
                        assignSave.textContent = 'Saving...';

                        const selectedIds = Array.from(selectedTechniciansMap.keys());

                        try {
                            const response = await fetch(url, {
                                method: 'PATCH',
                                headers: {
                                    'Accept': 'application/json',
                                    'Content-Type': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': csrfToken,
                                },
                                body: JSON.stringify({
                                    assigned_to: selectedIds,
                                }),
                            });

                            const payload = await response.json().catch(function () {
                                return {};
                            });

                            if (!response.ok || !payload.success) {
                                throw new Error(payload.message || 'Unable to assign technician.');
                            }

                            updateAssignedRow(payload.ticket || {});
                            closeAssignModal();

                            if (window.appAlert) {
                                window.appAlert(payload.message || 'Ticket assigned successfully.', 'Success', 'success');
                            }
                        } catch (error) {
                            if (window.appAlert) {
                                window.appAlert(error.message || 'Unable to assign technician.', 'Error', 'error');
                            }
                        } finally {
                            assignSave.textContent = previousLabel;
                            assignSave.disabled = false;
                        }
                    });
                }

                window.addEventListener('keydown', function (event) {
                    if (event.key === 'Escape' && assignModal && !assignModal.classList.contains('hidden')) {
                        closeAssignModal();
                    }
                });
            })();
        </script>
    @endpush
</x-app-layout>
