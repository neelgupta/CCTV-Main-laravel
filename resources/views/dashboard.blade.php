<x-app-layout>
    @php
        $user = Auth::user();
        $isSuperAdmin = $user?->isSuperAdmin() ?? false;
        $companyName = $user?->company?->name ?? 'No company linked';
        $authUser = auth()->user();
        $canCreateLead = $authUser && $authUser->hasPermission('leads.create');
        $leadPipelinePayload = [
            'rows' => [],
            'empty' => true,
            'total' => 0,
            'conversion_rate' => 0,
        ];
        $leadPipelinePayload = $leadPipelinePayload ?? $leadPipelineChart ?? [
            'rows' => [],
            'empty' => true,
            'total' => 0,
            'conversion_rate' => 0,
        ];
        $dashboardActiveTab = $dashboardActiveTab ?? (in_array(request('tab'), ['overview', 'today', 'week', 'month'], true) ? request('tab') : 'overview');
        $dashboardCalendarExpanded = $dashboardCalendarExpanded ?? request('calendar') === 'expanded';
        $dashboardCalendarExpandUrl = $dashboardCalendarExpandUrl ?? route('dashboard.calendar');
        $dashboardRangePayloads = $dashboardRangePayloads ?? [];
        $dashboardRangePayload = $dashboardRangePayload ?? ($dashboardRangePayloads[$dashboardActiveTab] ?? ($dashboardRangePayloads['today'] ?? null));
        $companyPaymentSummary = $companyPaymentSummary ?? [
            'range' => 'overview',
            'range_label' => 'Overview',
            'total_invoice_amount' => 0,
            'total_paid_amount' => 0,
            'total_pending_amount' => 0,
            'total_paid_invoices' => 0,
            'total_unpaid_invoices' => 0,
            'total_invoices' => 0,
        ];
        $companyPaymentStaffSummary = collect($companyPaymentStaffSummary ?? []);
        $companyPaymentDoughnutChart = $companyPaymentDoughnutChart ?? ['empty' => true];
        $companyPaymentBarChart = $companyPaymentBarChart ?? ['empty' => true];
        $companyPaymentTabLabel = $companyPaymentTabLabel ?? 'Overview';
        $accountingSummary = $accountingSummary ?? [
            'today_income' => 0,
            'today_expense' => 0,
            'monthly_income' => 0,
            'monthly_expense' => 0,
            'net_profit' => 0,
        ];
        $dashboardSelectedStaffId = $dashboardSelectedStaffId ?? null;
        $dashboardSupportStaffLabel = $dashboardSupportStaffLabel ?? 'All Staff';
        $dashboardSupportQuery = filled($dashboardSelectedStaffId) ? ['staff' => $dashboardSelectedStaffId] : [];
        $dashboardBlockers = collect($dashboardTaskBlockers ?? []);
        if ($dashboardBlockers->isEmpty()) {
            $dashboardBlockers = collect($layoutNotifications['blocker_notifications'] ?? []);
        }
        $dashboardBlockerTickerItems = $dashboardBlockers->take(8)->values();
        $dashboardBlockerTickerBase = $dashboardBlockerTickerItems->count() === 1
            ? $dashboardBlockerTickerItems->concat($dashboardBlockerTickerItems)->concat($dashboardBlockerTickerItems)->concat($dashboardBlockerTickerItems)->values()
            : $dashboardBlockerTickerItems;
        $dashboardBlockerTickerLoop = $dashboardBlockerTickerBase->isNotEmpty()
            ? $dashboardBlockerTickerBase->concat($dashboardBlockerTickerBase)->values()
            : collect();
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
            <div>
                <h2 class="text-2xl font-semibold leading-tight text-slate-950">Dashboard</h2>
            </div>
            @if($canCreateLead)
                <a href="{{ route('leads.create') }}"
                    class="inline-flex w-full items-center justify-center bg-[#C22032] px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-[#d63384] sm:w-auto">
                    + Add New Lead
                </a>
            @endif
        </div>
    </x-slot>

    @if (session('success'))
        <div x-data="{ show: true }" x-show="show" class="mb-6 flex items-center justify-between rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800 shadow-sm">
            <div class="flex items-center gap-3">
                <span class="text-xl">🎉</span>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-800">&times;</button>
        </div>
    @endif

    @if($dashboardBlockerTickerItems->isNotEmpty())
        <section class="mb-6 overflow-hidden rounded-2xl border border-rose-200 bg-white shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-rose-100 bg-gradient-to-r from-rose-50 to-white px-5 py-3">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-rose-700">Task Blockers</p>
                    <h3 class="mt-1 text-base font-bold text-slate-950">Live blocker ticker</h3>
                </div>
                <div class="rounded-full bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                    {{ $dashboardBlockers->count() }} active blocker{{ $dashboardBlockers->count() === 1 ? '' : 's' }}
                </div>
            </div>

            <div class="dashboard-blocker-marquee dashboard-blocker-marquee--scrolling">
                <div class="dashboard-blocker-marquee__track">
                    @foreach($dashboardBlockerTickerLoop as $item)
                        @php
                            $payload = $item['payload'] ?? [];
                            $level = strtolower((string) ($item['blocker_level'] ?? ($payload['blocker_level'] ?? 'low')));
                            $badgeClass = $item['badge_class'] ?? match ($level) {
                                'high' => 'border-rose-200 bg-rose-50 text-rose-700',
                                'medium' => 'border-amber-200 bg-amber-50 text-amber-700',
                                default => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                            };
                            $dotColor = $item['dot_color'] ?? match ($level) {
                                'high' => '#dc2626',
                                'medium' => '#d97706',
                                default => '#16a34a',
                            };
                            $meta = $item['meta'] ?? $item['time'] ?? $item['date_label'] ?? '';
                        @endphp
                        <a href="{{ $item['url'] ?? '#' }}" class="dashboard-blocker-marquee__item group">
                            <span class="dashboard-blocker-marquee__dot" style="background-color: {{ $dotColor }}"></span>
                            <span class="dashboard-blocker-marquee__title">{{ $item['title'] }}</span>
                            <span class="dashboard-blocker-marquee__badge {{ $badgeClass }}">
                                {{ ucfirst($level) }} blocker
                            </span>
                            <span class="dashboard-blocker-marquee__meta">{{ $meta }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <div class="space-y-6">

        @if (($canViewCompanyDashboard ?? false) || ($canViewStaffDashboard ?? false))
        @php
            $dashboardActiveTab = $dashboardActiveTab ?? (in_array(request('tab'), ['overview', 'today', 'week', 'month'], true) ? request('tab') : 'overview');
            $dashboardCalendarExpanded = $dashboardCalendarExpanded ?? request('calendar') === 'expanded';
            $dashboardOverviewUrl = $dashboardCalendarExpanded
                ? route('dashboard', array_merge(['tab' => 'overview', 'calendar' => 'expanded'], $dashboardSupportQuery))
                : route('dashboard', array_merge(['tab' => 'overview'], $dashboardSupportQuery));
            $dashboardTodayUrl = $dashboardCalendarExpanded
                ? route('dashboard', array_merge(['tab' => 'today', 'calendar' => 'expanded'], $dashboardSupportQuery))
                : route('dashboard', array_merge(['tab' => 'today'], $dashboardSupportQuery));
            $dashboardWeekUrl = $dashboardCalendarExpanded
                ? route('dashboard', array_merge(['tab' => 'week', 'calendar' => 'expanded'], $dashboardSupportQuery))
                : route('dashboard', array_merge(['tab' => 'week'], $dashboardSupportQuery));
            $dashboardMonthUrl = $dashboardCalendarExpanded
                ? route('dashboard', array_merge(['tab' => 'month', 'calendar' => 'expanded'], $dashboardSupportQuery))
                : route('dashboard', array_merge(['tab' => 'month'], $dashboardSupportQuery));
            $dashboardCalendarExpandUrl = route('dashboard.calendar');
            $dashboardRangePayloads = $dashboardRangePayloads ?? [];
            $dashboardRangePayload = $dashboardRangePayload ?? ($dashboardRangePayloads[$dashboardActiveTab] ?? ($dashboardRangePayloads['today'] ?? null));
        @endphp

        <div class="flex flex-wrap items-center gap-2 border border-slate-200 bg-white p-2.5 shadow-sm">
            <a href="{{ $dashboardOverviewUrl }}"
                class="inline-flex items-center px-4 py-2 text-sm font-semibold transition {{ $dashboardActiveTab === 'overview' ? 'bg-[#C22032] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                Overview
            </a>
            <a href="{{ $dashboardTodayUrl }}"
                class="inline-flex items-center px-4 py-2 text-sm font-semibold transition {{ $dashboardActiveTab === 'today' ? 'bg-[#C22032] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                Today
            </a>
            <a href="{{ $dashboardWeekUrl }}"
                class="inline-flex items-center px-4 py-2 text-sm font-semibold transition {{ $dashboardActiveTab === 'week' ? 'bg-[#C22032] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                This Week
            </a>
            <a href="{{ $dashboardMonthUrl }}"
                class="inline-flex items-center px-4 py-2 text-sm font-semibold transition {{ $dashboardActiveTab === 'month' ? 'bg-[#C22032] text-white shadow-sm' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                This Month
            </a>
            <a href="{{ $dashboardCalendarExpandUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="ml-auto inline-flex items-center gap-2 border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300 hover:bg-slate-100 hover:text-slate-950">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 5v14M5 12h14" />
                    <rect x="3.5" y="3.5" width="17" height="17" rx="3.5" />
                </svg>
                Open Calendar
            </a>
        </div>



        @if ($dashboardActiveTab === 'overview')
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-7">
            @php
                $dashboardKpiCards = [
                    ['label' => 'Total Leads', 'value' => $dashboardCounts['leads']['total'] ?? 0, 'accent' => '#C22032', 'icon' => 'M12 20a8 8 0 10-8-8 8 8 0 008 8zm0-11a3 3 0 110 6 3 3 0 010-6zm-6.5 9.5A6.5 6.5 0 0118.5 18'],
                    ['label' => 'Follow-ups Today', 'value' => $dashboardCounts['leads']['follow_up_today'] ?? 0, 'accent' => '#f59e0b', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['label' => 'Done Today', 'value' => $dashboardCounts['leads']['followups_done_today'] ?? 0, 'accent' => '#16a34a', 'icon' => 'M5 13l4 4L19 7'],
                    ['label' => 'Pending Today', 'value' => $dashboardCounts['leads']['followups_pending_today'] ?? 0, 'accent' => '#f97316', 'icon' => 'M12 9v4m0 4h.01M4.93 19h14.14a2 2 0 001.75-2.97l-7.07-12.6a2 2 0 00-3.5 0L3.18 16.03A2 2 0 004.93 19z'],
                    ['label' => 'Missed Follow-ups', 'value' => $dashboardCounts['leads']['missed_followups'] ?? 0, 'accent' => '#dc2626', 'icon' => 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
                    ['label' => 'Support Tickets', 'value' => $dashboardCounts['supports']['total'] ?? 0, 'accent' => '#2563eb', 'icon' => 'M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2a2 2 0 01-.6 1.4L4 17h5M9 17a3 3 0 006 0'],
                    ['label' => 'Repeat Complaints', 'value' => $dashboardCounts['supports']['repeat_complaints'] ?? 0, 'accent' => '#d63384', 'icon' => 'M4 4h16v12H7l-3 3V4z'],
                ];
            @endphp

            @foreach ($dashboardKpiCards as $kpi)
                <div class="group overflow-hidden  border border-slate-200 bg-white p-4 shadow-sm transition-all duration-250 hover:-translate-y-0.5 hover:shadow-md" style="box-shadow: inset 0 4px 0 {{ $kpi['accent'] }};">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center transition-colors duration-250" style="background-color: {{ $kpi['accent'] }}14; color: {{ $kpi['accent'] }};">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" class="h-4 w-4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="{{ $kpi['icon'] }}" />
                        </svg>
                    </div>
                    <div class="mt-2 min-w-0 text-[11px] font-semibold uppercase leading-tight tracking-wide text-slate-500">{{ $kpi['label'] }}</div>
                    <div class="mt-0.5 text-xl font-extrabold text-slate-900 sm:text-2xl">{{ $kpi['value'] }}</div>
                </div>
            @endforeach

        </div>
        @php
            $dashboardOverviewCharts = $dashboardOverviewCharts ?? [];
        @endphp
        {{-- <div class="grid gap-6 xl:grid-cols-3">
            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-r from-slate-950 to-slate-800 px-4 py-4 text-white sm:px-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold">Today's support status changes</h3>
                            <p class="text-xs text-slate-300">Support and complaint tickets updated today.</p>
                        </div>
                        <div class="rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white/90">
                            {{ $dashboardOverviewCharts['headline']['changes'] ?? 0 }} changes
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    @if (!($dashboardOverviewCharts['status']['empty'] ?? true))
                        <div class="h-[260px]">
                            <canvas id="dashboard-support-status-chart"></canvas>
                        </div>
                    @else
                        <div class="flex h-[260px] items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                            No support ticket status changes were recorded today.
                        </div>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Today's support tickets by staff</h3>
                            <p class="text-xs text-slate-500">Tickets created today grouped by assigned staff.</p>
                        </div>
                        <div class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                            {{ $dashboardOverviewCharts['headline']['staff'] ?? 0 }} staff
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    @if (!($dashboardOverviewCharts['staff']['empty'] ?? true))
                        <div class="relative mx-auto h-[260px] max-w-[320px]">
                            <canvas id="dashboard-support-staff-chart"></canvas>
                        </div>
                    @else
                        <div class="flex h-[260px] items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                            No support tickets were created today.
                        </div>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-gradient-to-r from-amber-50 to-orange-50 px-4 py-4 sm:px-5">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Yesterday's pending backlog</h3>
                            <p class="text-xs text-slate-500">Pending support tickets touched yesterday.</p>
                        </div>
                        <div class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-800">
                            {{ $dashboardOverviewCharts['headline']['pending'] ?? 0 }} pending
                        </div>
                    </div>
                </div>
                <div class="p-4 sm:p-5">
                    @if (!($dashboardOverviewCharts['pending']['empty'] ?? true))
                        <div class="h-[260px]">
                            <canvas id="dashboard-yesterday-pending-chart"></canvas>
                        </div>
                    @else
                        <div class="flex h-[260px] items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                            No pending support tickets were found from yesterday.
                        </div>
                    @endif
                </div>
            </div>
        </div> --}}
        <div class="grid gap-6 {{ $dashboardCalendarExpanded ? '' : 'xl:grid-cols-3' }}">
            <div class="min-w-0 {{ $dashboardCalendarExpanded ? '' : 'xl:col-span-2' }} space-y-6">
                <div class=" border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    @include('dashboard._calendar-toolbar', [
                        'calendarToolbarTitle' => 'Activity',
                        'calendarToolbarOverviewUrl' => $dashboardOverviewUrl,
                        'calendarToolbarActionUrl' => $dashboardCalendarExpandUrl,
                        'calendarToolbarActionLabel' => 'Expand calendar',
                    ])

                    <div id="activity-calendar" class="min-h-[420px] w-full {{ $dashboardCalendarExpanded ? 'sm:min-h-[720px] lg:min-h-[82vh]' : 'sm:min-h-[600px]' }}"></div>
                </div>


            </div>

            <div class="min-w-0 space-y-6">
                

                <div class='flex flex-col justify-center gap-4 border  from-emerald-50 to-emerald-100 p-4 shadow-sm sm:p-5'>
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Today's Schedule</h3>
                            <p class="text-xs text-slate-500">Today's follow-ups, deadlines, tasks, and status items.
                            </p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            {{ $todaySchedule->count() }} items
                        </span>
                    </div>
                    <div class="max-h-[320px] space-y-3 overflow-y-auto pr-1 sm:max-h-[420px]">
                        @forelse($todaySchedule as $item)
                            @if (($item['type'] ?? '') === 'lead')
                                <button
                                    type="button"
                                    class="js-open-lead-followup flex w-full items-start gap-3 border border-slate-100 bg-slate-50/70 p-3 text-left transition-colors hover:bg-slate-50"
                                    data-lead-title="{{ $item['title'] }}"
                                    data-lead-follow-up-date="{{ $item['follow_up_date'] ?? '' }}"
                                    data-lead-status="{{ $item['status_name'] ?? $item['meta'] ?? '' }}"
                                    data-lead-status-id="{{ $item['status_id'] ?? '' }}"
                                    data-lead-schedule-id="{{ $item['schedule_id'] ?? '' }}"
                                    data-lead-url="{{ $item['lead_url'] ?? $item['url'] ?? '' }}"
                                    data-lead-action-url="{{ $item['action_url'] ?? '' }}"
                                    data-lead-details-url="{{ $item['details_url'] ?? '' }}"
                                    data-lead-history-url="{{ $item['history_url'] ?? '' }}">
                                    <span class="mt-1 h-3 w-3 flex-none rounded-full"
                                        style="background-color: {{ $item['color'] }}"></span>
                                    <div class="min-w-0 flex-1">
                                        @php
                                            $assignedNames = collect($item['assigned_users'] ?? [])
                                                ->filter()
                                                ->values();

                                            if ($assignedNames->isEmpty() && filled($item['employee'] ?? null)) {
                                                $assignedNames = collect([trim((string) $item['employee'])]);
                                            }

                                            $avatarPalette = ['#4f46e5', '#7c3aed', '#0891b2', '#059669', '#ea580c', '#dc2626', '#0f766e', '#db2777'];
                                        @endphp
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="min-w-0 truncate font-semibold text-slate-900">{{ $item['title'] }}</p>
                                            @if($assignedNames->isNotEmpty())
                                                <div class="flex flex-none items-center -space-x-2 overflow-visible">
                                                    @foreach($assignedNames as $index => $assignedName)
                                                        @php
                                                            $assignedName = trim((string) $assignedName);
                                                            $initials = collect(preg_split('/\s+/', $assignedName) ?: [])
                                                                ->filter()
                                                                ->take(2)
                                                                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                                                ->implode('');
                                                            $badgeColor = $avatarPalette[$index % count($avatarPalette)] ?? '#4f46e5';
                                                        @endphp
                                                        <span
                                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full ring-2 ring-white text-[10px] font-bold text-white"
                                                            style="background-color: {{ $badgeColor }};"
                                                            title="{{ $assignedName }}"
                                                            aria-label="{{ $assignedName }}"
                                                        >
                                                            {{ $initials ?: mb_strtoupper(mb_substr($assignedName, 0, 1)) }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['meta'] }}</p>
                                        @if(!empty($item['time']))
                                            <p class="mt-1 text-[11px] font-medium text-slate-700">{{ $item['time'] }}</p>
                                        @endif
                                    </div>
                                </button>
                            @else
                                <a href="{{ $item['url'] ?? '#' }}"
                                    class="flex items-start gap-3 border border-slate-100 bg-slate-50/70 p-3 transition-colors hover:bg-slate-50">
                                    <span class="mt-1 h-3 w-3 flex-none rounded-full"
                                        style="background-color: {{ $item['color'] }}"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate font-semibold text-slate-900">
                                                {{ $item['title'] }}
                                                @if(!empty($item['employee']))
                                                    <span class="ml-1 text-[11px] font-medium text-rose-500">
                                                        ({{ $item['employee'] }})
                                                    </span>
                                                @endif
                                            </p>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['meta'] }}</p>
                                        @if(!empty($item['time']))
                                            <p class="mt-1 text-[11px] font-medium text-slate-700">{{ $item['time'] }}</p>
                                        @endif
                                    </div>
                                </a>
                            @endif
                        @empty
                            <div class= "border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                No items for today.
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">AMC Reminders</h3>
                            <p class="text-xs text-slate-500">Upcoming AMC services with actual days left and ending reminders.</p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            {{ $amcReminders->count() }} items
                        </span>
                    </div>
                    <div class="max-h-[280px] space-y-3 overflow-y-auto pr-1 sm:max-h-[320px]">
                        @forelse($amcReminders as $reminder)
                            <a href="{{ $reminder['url'] ?? route('amcs.index') }}"
                                class="flex items-start gap-3 border border-slate-100 bg-slate-50/70 p-3 hover:bg-slate-50 transition-colors">
                                <span class="mt-1 h-3 w-3 rounded-full flex-none"
                                    style="background-color: {{ $reminder['color'] }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="font-semibold text-slate-900">{{ $reminder['title'] }}</p>
                                        <span class="shrink-0 rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-700">
                                            {{ $reminder['badge'] }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $reminder['meta'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Employee: {{ $reminder['employee'] }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-700">{{ $reminder['date'] }}</p>
                                </div>
                            </a>
                        @empty
                            <div class=" border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                No AMC reminders right now.
                            </div>
                        @endforelse
                    </div>
                </div>
                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    @php
                        $leadPipelinePayload = $leadPipelinePayload ?? $leadPipelineChart ?? [
                            'rows' => [],
                            'empty' => true,
                            'total' => 0,
                            'conversion_rate' => 0,
                        ];
                    @endphp
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Lead Pipeline Overview</h3>
                            <p class="text-xs text-slate-500">Lead status distribution from Pipeline Settings.</p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            {{ $leadPipelinePayload['conversion_rate'] ?? 0 }}% converted
                        </span>
                    </div>

                    <div class="relative mx-auto flex h-[260px] items-center justify-center">
                        @if (!($leadPipelinePayload['empty'] ?? true))
                            <canvas id="lead-pipeline-chart" class="max-h-full w-full"></canvas>
                            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center">
                                <div class="text-2xl font-extrabold text-slate-950">{{ number_format($leadPipelinePayload['total'] ?? 0) }}</div>
                                <div class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total Leads</div>
                            </div>
                        @else
                            <div class= border border-dashed border-slate-200 bg-slate-50 px-4 py-8 text-center text-sm text-slate-500">
                                No lead status data available.
                            </div>
                        @endif
                    </div>

                    @if (!($leadPipelinePayload['empty'] ?? true))
                        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            @foreach (($leadPipelinePayload['rows'] ?? []) as $row)
                                <div class="flex items-center gap-2 border border-slate-100 bg-slate-50 px-3 py-2">
                                    <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $row['color'] }}"></span>
                                    <div class="min-w-0">
                                        <div class="truncate text-[11px] font-semibold text-slate-700">{{ $row['label'] }}</div>
                                        <div class="text-[11px] text-slate-500">{{ $row['count'] }} leads</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>


        </div>
        {{-- <div class="grid gap-6 xl:grid-cols-3">
            <div class="xl:col-span-2 space-y-6">
                <div class= "border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-slate-950">Sales Pipeline</h3>
                            <p class="text-sm text-slate-500">Week overview</p>
                        </div>
                        <div class="rounded-md border border-slate-200 px-2 py-1 text-xs text-slate-500">Week</div>
                    </div>
                    <div class="mt-5 grid grid-cols-4 gap-3 items-end h-52">
                        <div class="flex h-full flex-col justify-end"><div class="h-[65%] rounded-t-md bg-blue-600"></div><div class="mt-2 text-center text-xs text-slate-500">Week 1</div></div>
                        <div class="flex h-full flex-col justify-end"><div class="h-[82%] rounded-t-md bg-emerald-500"></div><div class="mt-2 text-center text-xs text-slate-500">Week 2</div></div>
                        <div class="flex h-full flex-col justify-end"><div class="h-[58%] rounded-t-md bg-amber-500"></div><div class="mt-2 text-center text-xs text-slate-500">Week 3</div></div>
                        <div class="flex h-full flex-col justify-end"><div class="h-[74%] rounded-t-md bg-cyan-500"></div><div class="mt-2 text-center text-xs text-slate-500">Week 4</div></div>
                    </div>
                </div>

                <div class=" border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-slate-950">Recent Customers</h3>
                        <a href="#" class="text-sm text-sky-600">View all</a>
                    </div>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="text-left text-slate-500">
                                <tr>
                                    <th class="py-2">Customer</th>
                                    <th class="py-2">Company</th>
                                    <th class="py-2">Status</th>
                                    <th class="py-2">Deal Value</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr><td class="py-3">Sarah Johnson</td><td>Tech Solutions Inc</td><td><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">Active</span></td><td>$12,500</td></tr>
                                <tr><td class="py-3">Michael Chen</td><td>Global Markets LLC</td><td><span class="rounded-full bg-amber-50 px-2 py-1 text-xs text-amber-700">Pending</span></td><td>$7,900</td></tr>
                                <tr><td class="py-3">Emma Wilson</td><td>Creative Agency</td><td><span class="rounded-full bg-emerald-50 px-2 py-1 text-xs text-emerald-700">Active</span></td><td>$23,400</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class=" border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-slate-950">Today’s Schedule</h3>
                    <div class="mt-4 space-y-4">
                        <div class="flex items-center gap-3">
                            <div class="h-3 w-3 rounded-full bg-blue-500"></div>
                            <div>
                                <div class="text-sm font-medium text-slate-900">Team Standup</div>
                                <div class="text-xs text-slate-500">10:00 AM</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-3 w-3 rounded-full bg-emerald-500"></div>
                            <div>
                                <div class="text-sm font-medium text-slate-900">Client Demo</div>
                                <div class="text-xs text-slate-500">12:30 PM</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-3 w-3 rounded-full bg-amber-500"></div>
                            <div>
                                <div class="text-sm font-medium text-slate-900">Sales Review</div>
                                <div class="text-xs text-slate-500">3:00 PM</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class=" border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="font-semibold text-slate-950">Quick Info</h3>
                    <div class="mt-4 space-y-3 text-sm text-slate-600">
                        <div><span class="font-medium text-slate-900">Role:</span> {{ $isSuperAdmin ? 'Super Admin' : 'Company User' }}</div>
                        <div><span class="font-medium text-slate-900">Company:</span> {{ $companyName }}</div>
                    </div>
                </div>
            </div>
        </div> --}}
        @endif

        @if (in_array($dashboardActiveTab, ['today', 'week', 'month'], true))
        @php
            $dashboardRangePayload = $dashboardRangePayload ?? ($dashboardRangePayloads[$dashboardActiveTab] ?? ($dashboardRangePayloads['today'] ?? null));
            $dashboardRangeLabel = $dashboardRangePayload['range_label'] ?? ucfirst($dashboardActiveTab);
            $dashboardRangeCharts = $dashboardRangePayload['charts'] ?? [];
        @endphp
        <div class="grid gap-6 xl:grid-cols-3">
            <div class="min-w-0 xl:col-span-2 space-y-6">
                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 sm:text-xl">{{ $dashboardRangeLabel }} activity hub</h3>
                            <p class="text-sm text-slate-500">Everything scheduled, created, updated, or due in this period across the company.</p>
                        </div>
                    </div>
                    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-7">
                        @foreach (($dashboardRangePayload['summary_cards'] ?? []) as $summaryCard)
                            <div class="border border-slate-100 bg-slate-50 px-4 py-3">
                                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $summaryCard['label'] }}</div>
                                <div class="mt-1 text-2xl font-extrabold text-slate-950">{{ $summaryCard['count'] }}</div>
                                <div class="mt-1 text-[11px] leading-4 text-slate-500">{{ $summaryCard['note'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if (!empty($dashboardRangePayload['support_summary']))
                    @php
                        $supportStatusCards = [
                            ['key' => 'completed', 'label' => 'Completed', 'note' => 'Resolved and closed tickets'],
                            ['key' => 'pending', 'label' => 'Pending', 'note' => 'Open, in progress, answered, reschedule'],
                            ['key' => 'repeat', 'label' => 'Repeat', 'note' => 'Repeat complaints'],
                            ['key' => 'hold', 'label' => 'Hold', 'note' => 'Tickets on hold'],
                        ];
                    @endphp
                    <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <h4 class="text-sm font-bold text-slate-900">Support status snapshot</h4>
                                <p class="text-xs text-slate-500">Support tickets in the selected range.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-3">
                                @if(($canViewCompanyDashboard ?? false) && !empty($staffList) && $staffList->count() > 0)
                                    <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap items-center gap-2">
                                        <input type="hidden" name="tab" value="{{ $dashboardActiveTab }}">
                                        @if($dashboardCalendarExpanded)
                                            <input type="hidden" name="calendar" value="expanded">
                                        @endif
                                        <label for="dashboard-staff-filter" class="text-xs font-semibold uppercase tracking-wider text-slate-500">Staff</label>
                                        <select id="dashboard-staff-filter" name="staff" onchange="this.form.submit()" class=" border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 focus:border-slate-900 focus:ring-slate-900">
                                            <option value="">All Staff</option>
                                            @foreach($staffList as $staff)
                                                <option value="{{ $staff->id }}" @selected((string) $dashboardSelectedStaffId === (string) $staff->id)>{{ $staff->name ?: ($staff->user?->name ?: 'Staff') }}</option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    {{ ($dashboardSupportStaffLabel ?? 'All Staff') !== 'All Staff' ? 'Filtered by ' . $dashboardSupportStaffLabel : 'All Staff' }}
                                </span>
                            </div>
                        </div>
                        <div class="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
                            @foreach ($supportStatusCards as $supportStatusCard)
                                <div class="border border-slate-100 bg-slate-50 px-4 py-3">
                                    <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $supportStatusCard['label'] }}</div>
                                    <div class="mt-1 text-2xl font-extrabold text-slate-950">
                                        {{ $dashboardRangePayload['support_summary'][$supportStatusCard['key']] ?? 0 }}
                                    </div>
                                    <div class="mt-1 text-[11px] leading-4 text-slate-500">{{ $supportStatusCard['note'] }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @php
                    $dashboardStaffTaskSummary = collect($dashboardStaffTaskSummary ?? []);
                    $dashboardTodayDate = now()->toDateString();
                    $buildStaffTaskSummaryUrl = function (array $row, string $category = 'all', ?string $state = null) use ($dashboardTodayDate) {
                        $baseQuery = array_merge([
                            'view' => 'list',
                            'assigned_to' => $row['staff_user_id'] ?? null,
                            'start_date' => $dashboardTodayDate,
                            'end_date' => $dashboardTodayDate,
                        ], $category !== 'all' ? ['task_category' => $category] : [], $state ? ['task_state' => $state] : []);

                        return route('tickets.index', array_filter($baseQuery, fn ($value) => $value !== null && $value !== ''));
                    };
                @endphp

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Today's Staff Task Summary</h3>
                                <p class="text-xs text-slate-500">Assigned AMC, complaint, and support tasks for today.</p>
                            </div>
                            <div class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                {{ $dashboardStaffTaskSummary->count() }} staff
                            </div>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-500 sm:px-6">Staff Name</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-500 sm:px-6">AMC</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-500 sm:px-6">Complaint</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-500 sm:px-6">Support</th>
                                    <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-500 sm:px-6">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse ($dashboardStaffTaskSummary as $summaryRow)
                                    @php
                                        $summaryRow = (array) $summaryRow;
                                        $amcUrl = $buildStaffTaskSummaryUrl($summaryRow, 'amc');
                                        $amcRemainingUrl = $buildStaffTaskSummaryUrl($summaryRow, 'amc', 'remaining');
                                        $complaintUrl = $buildStaffTaskSummaryUrl($summaryRow, 'complain');
                                        $complaintRemainingUrl = $buildStaffTaskSummaryUrl($summaryRow, 'complain', 'remaining');
                                        $supportUrl = $buildStaffTaskSummaryUrl($summaryRow, 'support');
                                        $supportRemainingUrl = $buildStaffTaskSummaryUrl($summaryRow, 'support', 'remaining');
                                        $totalUrl = $buildStaffTaskSummaryUrl($summaryRow);
                                    @endphp
                                    <tr class="hover:bg-slate-50/70">
                                        <td class="px-4 py-3 text-sm font-semibold text-slate-900 sm:px-6">{{ $summaryRow['staff_name'] ?? 'Staff' }}</td>
                                        <td class="px-4 py-3 text-center sm:px-6">
                                            <div class="flex flex-col items-center gap-1">
                                                <a href="{{ $amcUrl }}" class="inline-flex min-w-[4rem] items-center justify-center rounded-xl bg-cyan-50 px-3 py-2 text-sm font-bold text-cyan-700 transition hover:bg-cyan-100 hover:text-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-300 cursor-pointer">
                                                    {{ (int) ($summaryRow['amc_total'] ?? 0) }} Total
                                                </a>
                                                <a href="{{ $amcRemainingUrl }}" class="inline-flex min-w-[4rem] items-center justify-center rounded-lg px-2 py-1 text-[11px] font-semibold text-cyan-700 transition hover:bg-cyan-50 hover:text-cyan-800 focus:outline-none focus:ring-2 focus:ring-cyan-200 cursor-pointer">
                                                    {{ (int) ($summaryRow['amc_remaining'] ?? 0) }} Remaining
                                                </a>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center sm:px-6">
                                            <div class="flex flex-col items-center gap-1">
                                                <a href="{{ $complaintUrl }}" class="inline-flex min-w-[4rem] items-center justify-center rounded-xl bg-fuchsia-50 px-3 py-2 text-sm font-bold text-fuchsia-700 transition hover:bg-fuchsia-100 hover:text-fuchsia-800 focus:outline-none focus:ring-2 focus:ring-fuchsia-300 cursor-pointer">
                                                    {{ (int) ($summaryRow['complaint_total'] ?? 0) }} Total
                                                </a>
                                                <a href="{{ $complaintRemainingUrl }}" class="inline-flex min-w-[4rem] items-center justify-center rounded-lg px-2 py-1 text-[11px] font-semibold text-fuchsia-700 transition hover:bg-fuchsia-50 hover:text-fuchsia-800 focus:outline-none focus:ring-2 focus:ring-fuchsia-200 cursor-pointer">
                                                    {{ (int) ($summaryRow['complaint_remaining'] ?? 0) }} Remaining
                                                </a>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center sm:px-6">
                                            <div class="flex flex-col items-center gap-1">
                                                <a href="{{ $supportUrl }}" class="inline-flex min-w-[4rem] items-center justify-center rounded-xl bg-emerald-50 px-3 py-2 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-300 cursor-pointer">
                                                    {{ (int) ($summaryRow['support_total'] ?? 0) }} Total
                                                </a>
                                                <a href="{{ $supportRemainingUrl }}" class="inline-flex min-w-[4rem] items-center justify-center rounded-lg px-2 py-1 text-[11px] font-semibold text-emerald-700 transition hover:bg-emerald-50 hover:text-emerald-800 focus:outline-none focus:ring-2 focus:ring-emerald-200 cursor-pointer">
                                                    {{ (int) ($summaryRow['support_remaining'] ?? 0) }} Remaining
                                                </a>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 text-center sm:px-6">
                                            <a href="{{ $totalUrl }}" class="inline-flex min-w-[3.25rem] items-center justify-center rounded-xl bg-slate-950 px-3 py-2 text-sm font-bold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 cursor-pointer">
                                                {{ (int) ($summaryRow['total'] ?? 0) }}
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500 sm:px-6">No staff task data available for today.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    @foreach (($dashboardRangePayload['sections'] ?? collect()) as $section)
                        <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <div class="mb-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-900">{{ $section['title'] }}</h3>
                                        <p class="text-xs text-slate-500">{{ $section['subtitle'] }}</p>
                                    </div>
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $section['items']->count() }} items</span>
                                </div>
                            </div>
                            <div class="max-h-[360px] space-y-3 overflow-y-auto pr-1">
                                @forelse($section['items'] as $item)
                                    @if (!empty($item['url']))
                                        <a href="{{ $item['url'] }}" class="flex items-start gap-3 border border-slate-100 bg-slate-50/70 p-3 transition-colors hover:bg-slate-50">
                                    @else
                                        <div class="flex items-start gap-3 border border-slate-100 bg-slate-50/70 p-3">
                                    @endif
                                        <span class="mt-1 h-3 w-3 rounded-full flex-none" style="background-color: {{ $item['color'] ?? '#64748b' }}"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <p class="font-semibold text-slate-900">{{ $item['title'] ?? 'Item' }}</p>
                                                @if (!empty($item['badge']))
                                                    <span class="shrink-0 rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-700">
                                                        {{ $item['badge'] }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if (!empty($item['meta']))
                                                <p class="mt-1 text-xs text-slate-500">{{ $item['meta'] }}</p>
                                            @endif
                                            <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] font-medium text-slate-700">
                                                @if (!empty($item['time']))
                                                    <span>{{ $item['time'] }}</span>
                                                @endif
                                                @if (!empty($item['user']))
                                                    <span class="text-slate-400">&middot;</span>
                                                    <span>{{ $item['user_label'] ?? 'By' }} {{ $item['user'] }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @if (!empty($item['url']))
                                        </a>
                                    @else
                                        </div>
                                    @endif
                                @empty
                                    <div class=" border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                        {{ $section['empty'] ?? 'No items available.' }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="min-w-0 space-y-6">
                @if (!empty($dashboardRangeCharts['support']))
                    <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-950 to-slate-800 px-4 py-4 text-white sm:px-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold">{{ $dashboardRangeLabel }} Support Status Changes</h3>
                                    <p class="text-xs text-slate-300">Support and complaint tickets updated in this period.</p>
                                </div>
                                <div class="rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white/90">
                                    {{ $dashboardRangeCharts['support']['headline']['changes'] ?? 0 }} changes
                                </div>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            @if (!($dashboardRangeCharts['support']['status']['empty'] ?? true))
                                <div class="h-[240px]">
                                    <canvas id="dashboard-range-support-status-chart"></canvas>
                                </div>
                            @else
                                <div class="flex h-[240px] items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                                    No support ticket status changes were recorded in this period.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">{{ $dashboardRangeLabel }} Support Tickets of Staff Workload</h3>
                                    <p class="text-xs text-slate-500">Support and complaint tickets created in this period.</p>
                                </div>
                                <div class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    {{ $dashboardRangeCharts['support']['headline']['staff'] ?? 0 }} staff
                                </div>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            @if (!($dashboardRangeCharts['support']['staff']['empty'] ?? true))
                                <div class="relative mx-auto h-[240px] max-w-[320px]">
                                    <canvas id="dashboard-range-support-staff-chart"></canvas>
                                </div>
                            @else
                                <div class="flex h-[240px] items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                                    No support tickets were created in this period.
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($dashboardActiveTab === 'today')
                        <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                            <div class="border-b border-slate-100 bg-gradient-to-r from-amber-50 to-orange-50 px-4 py-4 sm:px-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-bold text-slate-900">Yesterday's Pending Support Backlog</h3>
                                        <p class="text-xs text-slate-500">Pending support tickets touched on the day before this period.</p>
                                    </div>
                                    <div class="rounded-full bg-amber-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-amber-800">
                                        {{ $dashboardRangeCharts['support']['headline']['pending'] ?? 0 }} pending
                                    </div>
                                </div>
                            </div>
                            <div class="p-4 sm:p-5">
                                @if (!($dashboardRangeCharts['support']['pending']['empty'] ?? true))
                                    <div class="h-[240px]">
                                        <canvas id="dashboard-range-pending-chart"></canvas>
                                    </div>
                                @else
                                    <div class="flex h-[240px] items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                                        No pending support tickets were found just before this period.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                @endif

                @if (!empty($dashboardRangeCharts['trend']))
                    <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 bg-gradient-to-r from-slate-950 to-slate-800 px-4 py-4 text-white sm:px-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold">{{ $dashboardRangeLabel }} trend</h3>
                                    <p class="text-xs text-slate-300">New items created across the selected range.</p>
                                </div>
                                <div class="rounded-full bg-white/10 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-white/90">
                                    {{ $dashboardRangeCharts['headline']['total_activity'] ?? 0 }} items
                                </div>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            @if (!($dashboardRangeCharts['trend']['empty'] ?? true))
                                <div class="h-[260px]">
                                    <canvas id="dashboard-range-trend-chart"></canvas>
                                </div>
                            @else
                                <div class="flex h-[260px] items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                                    No activity data is available for this range yet.
                                </div>
                            @endif
                        </div>
                    </div>
                @endif

                @if (!empty($dashboardRangeCharts['mix']))
                    <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-bold text-slate-900">Workload mix</h3>
                                    <p class="text-xs text-slate-500">Relative share of leads, projects, AMC, and support items.</p>
                                </div>
                                <div class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-slate-600">
                                    {{ $dashboardRangeCharts['headline']['peak_day'] ?? 0 }} peak
                                </div>
                            </div>
                        </div>
                        <div class="p-4 sm:p-5">
                            @if (!($dashboardRangeCharts['mix']['empty'] ?? true))
                                <div class="relative mx-auto h-[220px] max-w-[280px]">
                                    <canvas id="dashboard-range-mix-chart"></canvas>
                                </div>
                            @else
                                <div class="flex h-[220px] items-center justify-center border border-dashed border-slate-200 bg-slate-50 px-4 text-center text-sm text-slate-500">
                                    No workload mix data available yet.
                                </div>
                            @endif
                        </div>
                    </div>
                @endif


            </div>
        </div>
        @endif

        @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200">
                <div class="bg-gradient-to-r from-sky-500 to-cyan-400 px-4 py-3 text-white sm:py-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="mt-1 text-xs/5 font-bold opacity-90">Assigned Leads</div>
                            <div class="text-xl font-bold sm:text-2xl">{{ $dashboardCounts['leads']['total'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200">
                <div class="bg-gradient-to-r from-emerald-500 to-teal-400 px-4 py-3 text-white sm:py-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="mt-1 text-xs/5 font-medium opacity-90">Assigned Follow-ups</div>
                            <div class="text-xl font-bold sm:text-2xl">{{ $dashboardCounts['leads']['followups_total'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200">
                <div class="bg-gradient-to-r from-amber-400 to-orange-400 px-4 py-3 text-white sm:py-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="mt-1 text-xs/5 font-medium opacity-90">Follow-ups Today</div>
                            <div class="text-xl font-bold sm:text-2xl">{{ $dashboardCounts['leads']['follow_up_today'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200">
                <div class="px-4 py-3 text-white sm:py-4" style="background: linear-gradient(135deg, #14b8a6 0%, #06b6d4 100%);">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="mt-1 text-xs/5 font-medium opacity-90">Done Today</div>
                            <div class="text-xl font-bold sm:text-2xl">{{ $dashboardCounts['leads']['followups_done_today'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="overflow-hidden bg-white shadow-sm ring-1 ring-slate-200">
                <div class="bg-gradient-to-r from-violet-500 to-fuchsia-400 px-4 py-3 text-white sm:py-4">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="mt-1 text-xs/5 font-medium opacity-90">Pending Today</div>
                            <div class="text-xl font-bold sm:text-2xl">{{ $dashboardCounts['leads']['followups_pending_today'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 {{ $dashboardCalendarExpanded ? '' : 'xl:grid-cols-3' }}">
            <div class="min-w-0 {{ $dashboardCalendarExpanded ? '' : 'xl:col-span-2' }} space-y-6">
                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    @include('dashboard._calendar-toolbar', [
                        'calendarToolbarTitle' => 'Activity',
                        'calendarToolbarOverviewUrl' => $dashboardOverviewUrl,
                        'calendarToolbarActionUrl' => $dashboardCalendarExpandUrl,
                        'calendarToolbarActionLabel' => 'Expand calendar',
                    ])

                    <div id="activity-calendar" class="min-h-[420px] w-full {{ $dashboardCalendarExpanded ? 'sm:min-h-[720px] lg:min-h-[82vh]' : 'sm:min-h-[600px]' }}"></div>
                </div>

                <div class=" border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="font-semibold text-slate-800">Today's Assignments</h3>
                        <span class="text-sm text-slate-500">{{ $todaySchedule->count() }} items</span>
                    </div>
                    <div class="mt-4 max-h-[360px] space-y-3 overflow-y-auto pr-1">
                        @forelse($todaySchedule as $item)
                            @if (($item['type'] ?? '') === 'lead')
                                <button
                                    type="button"
                                    class="js-open-lead-followup flex w-full items-start gap-3 border border-slate-100 bg-slate-50/70 p-3 text-left transition-colors hover:bg-slate-50"
                                    data-lead-title="{{ $item['title'] }}"
                                    data-lead-follow-up-date="{{ $item['follow_up_date'] ?? '' }}"
                                    data-lead-status="{{ $item['status_name'] ?? $item['meta'] ?? '' }}"
                                    data-lead-status-id="{{ $item['status_id'] ?? '' }}"
                                    data-lead-schedule-id="{{ $item['schedule_id'] ?? '' }}"
                                    data-lead-url="{{ $item['lead_url'] ?? $item['url'] ?? '' }}"
                                    data-lead-action-url="{{ $item['action_url'] ?? '' }}"
                                    data-lead-details-url="{{ $item['details_url'] ?? '' }}"
                                    data-lead-history-url="{{ $item['history_url'] ?? '' }}">
                                    <span class="mt-1 h-3 w-3 flex-none rounded-full"
                                        style="background-color: {{ $item['color'] }}"></span>
                                    <div class="min-w-0 flex-1">
                                        @php
                                            $assignedNames = collect($item['assigned_users'] ?? [])
                                                ->filter()
                                                ->values();

                                            if ($assignedNames->isEmpty() && filled($item['employee'] ?? null)) {
                                                $assignedNames = collect([trim((string) $item['employee'])]);
                                            }

                                            $avatarPalette = ['#4f46e5', '#7c3aed', '#0891b2', '#059669', '#ea580c', '#dc2626', '#0f766e', '#db2777'];
                                        @endphp
                                        <div class="flex items-start justify-between gap-3">
                                            <p class="min-w-0 truncate font-semibold text-slate-900">{{ $item['title'] }}</p>
                                            @if($assignedNames->isNotEmpty())
                                                <div class="flex flex-none items-center -space-x-2 overflow-visible">
                                                    @foreach($assignedNames as $index => $assignedName)
                                                        @php
                                                            $assignedName = trim((string) $assignedName);
                                                            $initials = collect(preg_split('/\s+/', $assignedName) ?: [])
                                                                ->filter()
                                                                ->take(2)
                                                                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                                                ->implode('');
                                                            $badgeColor = $avatarPalette[$index % count($avatarPalette)] ?? '#4f46e5';
                                                        @endphp
                                                        <span
                                                            class="inline-flex h-6 w-6 items-center justify-center rounded-full ring-2 ring-white text-[10px] font-bold text-white"
                                                            style="background-color: {{ $badgeColor }};"
                                                            title="{{ $assignedName }}"
                                                            aria-label="{{ $assignedName }}"
                                                        >
                                                            {{ $initials ?: mb_strtoupper(mb_substr($assignedName, 0, 1)) }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['meta'] }}</p>
                                        @if(!empty($item['time']))
                                            <p class="mt-1 text-[11px] font-medium text-slate-700">{{ $item['time'] }}</p>
                                        @endif
                                    </div>
                                </button>
                            @else
                                <a href="{{ $item['url'] ?? '#' }}"
                                    class="flex items-start gap-3 border border-slate-100 bg-slate-50/70 p-3 transition-colors hover:bg-slate-50">
                                    <span class="mt-1 h-3 w-3 flex-none rounded-full"
                                        style="background-color: {{ $item['color'] }}"></span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center justify-between gap-3">
                                            <p class="truncate font-semibold text-slate-900">{{ $item['title'] }}</p>
                                        </div>
                                        <p class="mt-1 text-xs text-slate-500">{{ $item['meta'] }}</p>
                                        @if(!empty($item['time']))
                                            <p class="mt-1 text-[11px] font-medium text-slate-700">{{ $item['time'] }}</p>
                                        @endif
                                    </div>
                                </a>
                            @endif
                        @empty
                            <div class=" border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                No assignments for today.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="min-w-0 space-y-6">
                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Service Reminders</h3>
                            <p class="text-xs text-slate-500">Your assigned AMC reminders and service deadlines.</p>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                            {{ $amcReminders->count() }} items
                        </span>
                    </div>
                    <div class="max-h-[280px] space-y-3 overflow-y-auto pr-1 sm:max-h-[320px]">
                        @forelse($amcReminders as $reminder)
                            <a href="{{ $reminder['url'] ?? route('amcs.index') }}"
                                class="flex items-start gap-3 border border-slate-100 bg-slate-50/70 p-3 hover:bg-slate-50 transition-colors">
                                <span class="mt-1 h-3 w-3 rounded-full flex-none"
                                    style="background-color: {{ $reminder['color'] }}"></span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start justify-between gap-3">
                                        <p class="font-semibold text-slate-900">{{ $reminder['title'] }}</p>
                                        <span class="shrink-0 rounded-full bg-slate-200 px-2 py-1 text-[10px] font-bold uppercase tracking-wide text-slate-700">
                                            {{ $reminder['badge'] }}
                                        </span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">{{ $reminder['meta'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500">Employee: {{ $reminder['employee'] }}</p>
                                    <p class="mt-1 text-xs font-medium text-slate-700">{{ $reminder['date'] }}</p>
                                </div>
                            </a>
                        @empty
                            <div class=" border border-dashed border-slate-200 bg-slate-50 p-4 text-sm text-slate-500">
                                No service reminders right now.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
        @endif

    </div>

    <div id="calendar-task-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/60 px-4">
        <div class="w-full max-w-xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Add calendar task</h3>
                    <p class="text-xs text-slate-500">Create a task or appointment with a location.</p>
                </div>
                <button type="button" id="close-calendar-task-modal" class="text-slate-500 hover:text-[#C22032] text-2xl leading-none">&times;</button>
            </div>
            <form method="POST" action="{{ route('dashboard.calendar-events.store') }}" id="calendar-task-form" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="_method" id="calendar-task-form-method" value="POST">
                <input type="hidden" name="event_date" id="calendar-task-date" required>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Title</label>
                    <input name="title" id="calendar-task-title" type="text" required class="w-full border-slate-300 focus:border-[#d63384] focus:ring-[#d63384]" placeholder="Task title">
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Date</label>
                        <input id="calendar-task-date-visible" type="date" required class="w-full border-slate-300 focus:border-[#d63384] focus:ring-[#d63384]">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Type</label>
                        <select name="event_type" id="calendar-task-type" class="w-full border-slate-300 focus:border-[#d63384] focus:ring-[#d63384]">
                            <option value="task">Task</option>
                            <option value="appointment">Appointment</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Location</label>
                    <input name="location" id="calendar-task-location" type="text" class="w-full border-slate-300 focus:border-[#d63384] focus:ring-[#d63384]" placeholder="Office, site, client location">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Status</label>
                    <select name="status" id="calendar-task-status" class="w-full border-slate-300 focus:border-[#d63384] focus:ring-[#d63384]">
                        <option value="pending">Pending</option>
                        <option value="done">Done</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="calendar-task-description" rows="4" class="w-full border-slate-300 focus:border-[#d63384] focus:ring-[#d63384]" placeholder="Notes, reminder, or details"></textarea>
                </div>
                <div class="flex items-center justify-between gap-3 pt-2">
                    <button type="submit" form="calendar-task-delete-form" id="delete-calendar-task-btn" class="hidden border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">Delete</button>
                    <div class="ml-auto flex items-center gap-3">
                        <button type="button" id="cancel-calendar-task-modal" class=" border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-rose-50 hover:text-[#C22032]">Cancel</button>
                        <button type="submit" id="save-calendar-task-btn" class=" bg-[#C22032] px-4 py-2 text-sm font-semibold text-white hover:bg-[#d63384]">Save Task</button>
                    </div>
                </div>
            </form>
            <form method="POST" action="" id="calendar-task-delete-form" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    <div id="calendar-task-detail-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/60 px-4">
        <div class="w-full max-w-lg rounded-3xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <div>
                    <h3 class="text-lg font-bold text-slate-800">Task details</h3>
                    <p class="text-xs text-slate-500">View or edit the saved task information.</p>
                </div>
                <button type="button" id="close-calendar-task-detail-modal" class="text-slate-500 hover:text-[#C22032] text-2xl leading-none">&times;</button>
            </div>
            <div class="p-6 space-y-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Title</p>
                    <p id="calendar-task-detail-title" class="mt-1 text-lg font-bold text-slate-800"></p>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Date</p>
                        <p id="calendar-task-detail-date" class="mt-1 text-sm text-slate-700"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Location</p>
                        <p id="calendar-task-detail-location" class="mt-1 text-sm text-slate-700"></p>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Type</p>
                        <p id="calendar-task-detail-type" class="mt-1 text-sm text-slate-700"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Status</p>
                        <p id="calendar-task-detail-status" class="mt-1 text-sm text-slate-700"></p>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Description</p>
                    <p id="calendar-task-detail-description" class="mt-1 text-sm leading-6 text-slate-700 whitespace-pre-wrap"></p>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="edit-calendar-task-btn" class= "bg-[#C22032] px-4 py-2 text-sm font-semibold text-white hover:bg-[#d63384]">Edit Task</button>
                </div>
            </div>
        </div>
    </div>
        @if ($canViewCompanyDashboard)
            @php
                $paymentRangeKey = $dashboardActiveTab === 'today' ? 'today' : ($dashboardActiveTab === 'week' ? 'week' : ($dashboardActiveTab === 'month' ? 'month' : 'overview'));
                $paymentCardPrefix = match ($paymentRangeKey) {
                    'today' => "Today's",
                    'week' => 'This Week',
                    'month' => 'This Month',
                    default => 'Total',
                };
                $paymentTotalInvoices = (int) ($companyPaymentSummary['total_invoices'] ?? 0);
            @endphp

            <div class="mt-6 space-y-6">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <div class=" border border-emerald-100 bg-white p-4 shadow-sm">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Today's Income</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($accountingSummary['today_income'] ?? 0), 2) }}</div>
                    </div>
                    <div class=" border border-rose-100 bg-white p-4 shadow-sm">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-rose-600">Today's Expense</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($accountingSummary['today_expense'] ?? 0), 2) }}</div>
                    </div>
                    <div class=" border border-emerald-100 bg-white p-4 shadow-sm">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600">Monthly Income</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($accountingSummary['monthly_income'] ?? 0), 2) }}</div>
                    </div>
                    <div class=" border border-rose-100 bg-white p-4 shadow-sm">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-rose-600">Monthly Expense</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($accountingSummary['monthly_expense'] ?? 0), 2) }}</div>
                    </div>
                    <div class=" border border-violet-100 bg-white p-4 shadow-sm">
                        <div class="text-[11px] font-bold uppercase tracking-wider text-violet-600">Net Profit</div>
                        <div class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($accountingSummary['net_profit'] ?? 0), 2) }}</div>
                    </div>
                </div>

                <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h3 class="text-lg font-bold text-slate-900 sm:text-xl">Company Payment Summary</h3>
                            <p class="text-sm text-slate-500">Payment statistics for {{ $companyPaymentTabLabel }} invoices of your company.</p>
                        </div>
                        <div class="text-sm font-medium text-slate-500">
                            Filtered by <span class="font-semibold text-slate-900">{{ $companyPaymentTabLabel }}</span>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class=" border border-slate-100 bg-slate-50 px-4 py-4">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ $paymentCardPrefix }} Invoice Amount</div>
                            <div class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format((float) ($companyPaymentSummary['total_invoice_amount'] ?? 0), 2) }}</div>
                        </div>
                        <div class="border border-emerald-100 bg-gradient-to-br  px-4 py-4 text-black">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-50/90">Collected Amount</div>
                            <div class="mt-2 text-2xl font-black">Rs. {{ number_format((float) ($companyPaymentSummary['total_paid_amount'] ?? 0), 2) }}</div>
                        </div>
                        <div class=" border border-orange-100 bg-gradient-to-br from-orange-500 to-rose-500 px-4 py-4 text-black">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-orange-50/90">Pending Collection</div>
                            <div class="mt-2 text-2xl font-black">Rs. {{ number_format((float) ($companyPaymentSummary['total_pending_amount'] ?? 0), 2) }}</div>
                        </div>
                        <div class="border border-slate-100 bg-slate-50 px-4 py-4">
                            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Invoices</div>
                            <div class="mt-2 text-2xl font-black text-slate-950">{{ number_format($paymentTotalInvoices) }}</div>
                        </div>
                    </div>

                    <div class="mt-6 w-full overflow-hidden border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 px-4 py-4 sm:px-5">
                            <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                                <div>
                                    <h4 class="text-base font-semibold text-slate-950">Staff Wise Summary</h4>
                                    <p class="text-sm text-slate-500">Employee-wise invoice collection for {{ $companyPaymentTabLabel }}.</p>
                                </div>
                                <div class="text-sm text-slate-500">Grand total row included at bottom</div>
                            </div>
                        </div>

                        <div class="w-full overflow-x-auto">
                            <table class="w-full min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white">Employee Name</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-white">Paid Amount</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-white">Pending Amount</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-white">Total Invoice</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($companyPaymentStaffSummary as $row)
                                        <tr class="{{ ($row['is_grand_total'] ?? false) ? 'bg-slate-50 font-bold' : '' }}">
                                            <td class="px-4 py-3 text-sm text-slate-900">{{ $row['staff_name'] }}</td>
                                            <td class="px-4 py-3 text-right text-sm text-slate-700">Rs. {{ number_format((float) $row['paid_amount'], 2) }}</td>
                                            <td class="px-4 py-3 text-right text-sm text-slate-700">Rs. {{ number_format((float) $row['pending_amount'], 2) }}</td>
                                            <td class="px-4 py-3 text-right text-sm text-slate-700">Rs. {{ number_format((float) $row['total_invoice_amount'], 2) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-10 text-center text-sm text-slate-500">No payment records found for this company.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @include('partials.lead-followup-modal')

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <style>
        .dashboard-blocker-marquee {
            position: relative;
            overflow: hidden;
            background: linear-gradient(90deg, rgba(255, 255, 255, 0.95), rgba(255, 241, 242, 0.97), rgba(255, 255, 255, 0.95));
        }

        .dashboard-blocker-marquee::before,
        .dashboard-blocker-marquee::after {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 72px;
            z-index: 2;
            pointer-events: none;
        }

        .dashboard-blocker-marquee::before {
            left: 0;
            background: linear-gradient(90deg, rgba(255, 255, 255, 1), rgba(255, 255, 255, 0));
        }

        .dashboard-blocker-marquee::after {
            right: 0;
            background: linear-gradient(270deg, rgba(255, 255, 255, 1), rgba(255, 255, 255, 0));
        }

        .dashboard-blocker-marquee__track {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: max-content;
            padding: 0.85rem 1rem;
            animation: dashboard-blocker-scroll 26s linear infinite;
        }

        .dashboard-blocker-marquee--single .dashboard-blocker-marquee__track {
            width: 100%;
            animation: none;
        }

        .dashboard-blocker-marquee:hover .dashboard-blocker-marquee__track {
            animation-play-state: paused;
        }

        .dashboard-blocker-marquee__item {
            display: inline-flex;
            align-items: center;
            gap: 0.65rem;
            border: 1px solid rgba(244, 63, 94, 0.16);
            border-radius: 9999px;
            background: rgba(255, 255, 255, 0.96);
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            padding: 0.65rem 0.9rem;
            white-space: nowrap;
            color: inherit;
            text-decoration: none;
            transition: transform 160ms ease, border-color 160ms ease, background-color 160ms ease;
        }

        .dashboard-blocker-marquee__item--full {
            width: 100%;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-radius: 1.25rem;
            white-space: normal;
        }

        .dashboard-blocker-marquee__item--full .dashboard-blocker-marquee__dot {
            margin-top: 0.35rem;
        }

        .dashboard-blocker-marquee__content {
            display: flex;
            flex: 1 1 auto;
            min-width: 0;
            flex-direction: column;
            gap: 0.35rem;
        }

        .dashboard-blocker-marquee__footer {
            display: flex;
            flex: none;
            align-items: center;
            gap: 0.5rem;
            margin-left: auto;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .dashboard-blocker-marquee__item:hover {
            transform: translateY(-1px);
            border-color: rgba(244, 63, 94, 0.36);
            background: rgba(255, 241, 242, 0.96);
        }

        .dashboard-blocker-marquee__dot {
            display: inline-flex;
            width: 0.6rem;
            height: 0.6rem;
            border-radius: 9999px;
            box-shadow: 0 0 0 4px rgba(255, 255, 255, 0.86);
            flex: none;
        }

        .dashboard-blocker-marquee__title {
            max-width: 20rem;
            overflow: hidden;
            text-overflow: ellipsis;
            font-size: 0.875rem;
            font-weight: 700;
            color: #0f172a;
        }

        .dashboard-blocker-marquee__item--full .dashboard-blocker-marquee__title {
            max-width: none;
            white-space: normal;
        }

        .dashboard-blocker-marquee__description {
            font-size: 0.875rem;
            line-height: 1.45;
            color: #334155;
        }

        .dashboard-blocker-marquee__badge {
            border-radius: 9999px;
            border: 1px solid;
            padding: 0.25rem 0.6rem;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.15em;
        }

        .dashboard-blocker-marquee__meta {
            font-size: 0.78rem;
            color: #64748b;
        }

        @keyframes dashboard-blocker-scroll {
            0% {
                transform: translateX(0);
            }

            100% {
                transform: translateX(-50%);
            }
        }

        @media (max-width: 640px) {
            .dashboard-blocker-marquee__track {
                animation-duration: 20s;
            }

            .dashboard-blocker-marquee__item--full {
                flex-direction: column;
                align-items: flex-start;
            }

            .dashboard-blocker-marquee__footer {
                margin-left: 0;
                justify-content: flex-start;
            }

            .dashboard-blocker-marquee__title {
                max-width: 12rem;
            }

            .dashboard-blocker-marquee::before,
            .dashboard-blocker-marquee::after {
                width: 36px;
            }
        }

        /* Compact, card-style calendar */
        .fc {
            --fc-border-color: #e5e7eb;
            --fc-today-bg-color: #fff1f4;
        }

        .fc .fc-toolbar.fc-header-toolbar {
            margin-bottom: 8px;
        }

        .fc .fc-toolbar-title {
            font-size: 1rem;
            font-weight: 800;
            color: #111827;
        }

        .fc .fc-button-primary {
            background: #f8fafc;
            color: #111827;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 6px 12px;
            box-shadow: none;
        }

        .fc .fc-button-primary:not(:disabled):hover {
            background: #fff1f4;
            border-color: #f9a8d4;
        }

        .fc .fc-daygrid-day-frame {
            padding: 6px;
        }

        .fc .fc-daygrid-day-number {
            color: #0f172a;
            font-weight: 700;
            font-size: 12px;
            padding: 4px 6px;
        }

        .fc .fc-daygrid-day.fc-day-other .fc-daygrid-day-number {
            color: #cbd5e1;
            font-weight: 600;
        }

        .fc .fc-col-header {
            background: #f8fafc;
        }

        .fc .fc-col-header-cell {
            padding: 8px 0;
        }

        .fc .fc-col-header-cell-cushion {
            color: #ffff;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            font-size: 11px;
        }

        .fc .fc-daygrid-event {
            border: none;
            background: transparent;
            padding: 0;
            overflow: visible;
            margin: 2px 0 0 0;
            width: 100%;
        }

        .fc .fc-daygrid-day-events {
            display: flex;
            flex-direction: column;
            gap: 2px;
            align-items: stretch;
            min-height: 0;
        }

        .fc .fc-daygrid-event-harness {
            width: 100%;
        }

        .fc .fc-daygrid-event .fc-event-pill {
            display: flex;
            align-items: center;
            gap: 6px;
            width: 100%;
            min-width: 0;
            border-radius: 6px;
            padding: 3px 8px;
            font-size: 11px;
            line-height: 1.4;
            font-weight: 600;
            color: #334155;
            background: #f1f5f9;
            box-shadow: none;
            cursor: pointer;
        }

        .fc .fc-daygrid-event .fc-event-pill .event-text {
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .fc .fc-daygrid-event .fc-event-pill .event-chip {
            flex: none;
            width: 6px;
            height: 6px;
            border-radius: 9999px;
            background: currentColor;
        }

        .event-custom-task .fc-event-pill {
            background: #fce4ec;
            color: #b0206e;
        }

        .event-project .fc-event-pill {
            background: #d9f5e6;
            color: #0e8a5f;
        }

        .event-amc .fc-event-pill {
            background: #fef3c7;
            color: #b45309;
        }

        .event-ticket .fc-event-pill {
            background: #dbeefe;
            color: #0369a1;
        }

        .event-lead .fc-event-pill {
            background: #fce4ec;
            color: #C22032;
        }

        .event-overdue .fc-event-pill {
            background: #fee2e2;
            color: #b91c1c;
        }

        .event-absent .fc-event-pill {
            background: #fee2e2;
            color: #dc2626;
        }

        .fc .fc-daygrid-more-link {
            display: inline-flex;
            margin-top: 2px;
            padding: 2px 6px;
            border-radius: 9999px;
            background: #f8fafc;
            color: #111827;
            font-size: 11px;
            font-weight: 700;
            text-decoration: none;
        }

        .fc-theme-standard .fc-popover {
            border-radius: 14px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
        }

        .fc-theme-standard .fc-popover-title {
            font-weight: 800;
            color: #111827;
        }

        /* Materials pagination styling */
        .materials-pagination .pagination li a,
        .materials-pagination .pagination li span {
            background-color: #f8fafc;
            color: #111827;
            border: 1px solid #e5e7eb;
        }

        .materials-pagination .pagination li.active span {
            background-color: #2563eb;
            color: #fff;
            border-color: #2563eb;
        }

        .materials-pagination .pagination li:first-child a,
        .materials-pagination .pagination li:first-child span {
            border-radius: 8px 0 0 8px;
        }

        .materials-pagination .pagination li:last-child a,
        .materials-pagination .pagination li:last-child span {
            border-radius: 0 8px 8px 0;
        }

        .fc .fc-toolbar.fc-header-toolbar {
            margin-top: 10px;
            margin-bottom: 15px;
        }

        /* Keep the grid lines clean as requested previously */
        .fc .fc-daygrid-day {
            border: 1px solid #cbd5e1 !important;
        }

        .fc .fc-daygrid-day-frame {
            background-color: #f1f5f9;
            /* The slightly dark background */
            transition: background-color 0.2s;
        }

        @media (max-width: 640px) {
            .fc .fc-toolbar.fc-header-toolbar {
                flex-wrap: wrap;
                gap: 0.5rem;
                margin-bottom: 10px;
            }

            .fc .fc-toolbar-title {
                font-size: 0.95rem;
            }

            .fc .fc-button-primary {
                padding: 3px 8px;
                border-radius: 8px;
                font-size: 0.75rem;
            }

            .fc .fc-daygrid-day-frame {
                padding: 2px;
            }

            .fc .fc-daygrid-day-number {
                font-size: 10px;
            }

            .fc .fc-col-header-cell-cushion {
                font-size: 10px;
            }

            .fc .fc-daygrid-event .fc-event-pill {
                padding: 2px 6px;
                font-size: 10px;
            }

            .fc .fc-daygrid-more-link {
                font-size: 10px;
            }
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const leadPipelineData = @js($leadPipelinePayload ?? [
                'rows' => [],
                'empty' => true,
                'total' => 0,
                'conversion_rate' => 0,
            ]);
            const dashboardOverviewCharts = @js($dashboardOverviewCharts ?? []);
            const dashboardRangeCharts = @js($dashboardRangeCharts ?? []);
            const leadPipelineCanvas = document.getElementById('lead-pipeline-chart');
            const renderDashboardChart = (canvasId, config) => {
                const canvas = document.getElementById(canvasId);
                if (!canvas || !config || config.empty || typeof Chart === 'undefined') {
                    return null;
                }

                const context = canvas.getContext('2d');
                if (!context) {
                    return null;
                }

                return new Chart(context, {
                    type: config.type,
                    data: config.data,
                    options: config.options ?? {},
                });
            };

            if (leadPipelineCanvas && leadPipelineData && !leadPipelineData.empty && typeof Chart !== 'undefined') {
                const leadLabels = (leadPipelineData.rows || []).map((row) => row.label);
                const leadCounts = (leadPipelineData.rows || []).map((row) => Number(row.count || 0));
                const leadColors = (leadPipelineData.rows || []).map((row) => row.color || '#94a3b8');

                new Chart(leadPipelineCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: leadLabels,
                        datasets: [{
                            data: leadCounts,
                            backgroundColor: leadColors,
                            borderWidth: 0,
                            hoverOffset: 4,
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '72%',
                        plugins: {
                            legend: {
                                display: false,
                            },
                            tooltip: {
                                callbacks: {
                                    label(context) {
                                        const label = context.label || '';
                                        const value = Number(context.raw || 0);
                                        return `${label}: ${value}`;
                                    },
                                },
                            },
                        },
                    },
                });
            }

            renderDashboardChart('dashboard-range-trend-chart', dashboardRangeCharts.trend);
            renderDashboardChart('dashboard-range-mix-chart', dashboardRangeCharts.mix);
            renderDashboardChart('dashboard-support-status-chart', dashboardOverviewCharts.status);
            renderDashboardChart('dashboard-support-staff-chart', dashboardOverviewCharts.staff);
            renderDashboardChart('dashboard-yesterday-pending-chart', dashboardOverviewCharts.pending);
            renderDashboardChart('company-payment-doughnut-chart', @js($companyPaymentDoughnutChart ?? []));
            renderDashboardChart('company-payment-bar-chart', @js($companyPaymentBarChart ?? []));
            const dashboardRangeSupportCharts = dashboardRangeCharts.support || {};
            const isTodayRangeTab = @js($dashboardActiveTab === 'today');
            renderDashboardChart('dashboard-range-support-status-chart', dashboardRangeSupportCharts.status);
            renderDashboardChart('dashboard-range-support-staff-chart', dashboardRangeSupportCharts.staff);
            if (isTodayRangeTab && dashboardRangeSupportCharts.pending) {
                renderDashboardChart('dashboard-range-pending-chart', dashboardRangeSupportCharts.pending);
            }

            const calendarEvents = @json($calendarEvents ?? []);
            const dashboardToday = @json(now()->toDateString());
            const leadStatuses = @json(($leadStatuses ?? collect())->map(fn ($status) => [
                'id' => (int) $status->id,
                'name' => (string) $status->name,
            ])->values());
            const taskModal = document.getElementById('calendar-task-modal');
            const taskDetailModal = document.getElementById('calendar-task-detail-modal');
            const leadFollowUpModal = document.getElementById('lead-follow-up-modal');
            const taskForm = document.getElementById('calendar-task-form');
            const taskFormMethodInput = document.getElementById('calendar-task-form-method');
            const taskDateInput = document.getElementById('calendar-task-date');
            const taskDateVisibleInput = document.getElementById('calendar-task-date-visible');
            const taskTitleInput = document.getElementById('calendar-task-title');
            const taskTypeInput = document.getElementById('calendar-task-type');
            const taskLocationInput = document.getElementById('calendar-task-location');
            const taskStatusInput = document.getElementById('calendar-task-status');
            const taskDescriptionInput = document.getElementById('calendar-task-description');
            const openTaskModalBtn = document.getElementById('open-calendar-task-modal');
            const closeTaskModalBtn = document.getElementById('close-calendar-task-modal');
            const cancelTaskModalBtn = document.getElementById('cancel-calendar-task-modal');
            const closeTaskDetailModalBtn = document.getElementById('close-calendar-task-detail-modal');
            const editTaskBtn = document.getElementById('edit-calendar-task-btn');
            const deleteTaskBtn = document.getElementById('delete-calendar-task-btn');
            const deleteTaskForm = document.getElementById('calendar-task-delete-form');
            const saveTaskBtn = document.getElementById('save-calendar-task-btn');
            const taskDetailTitle = document.getElementById('calendar-task-detail-title');
            const taskDetailDate = document.getElementById('calendar-task-detail-date');
            const taskDetailLocation = document.getElementById('calendar-task-detail-location');
            const taskDetailType = document.getElementById('calendar-task-detail-type');
            const taskDetailStatus = document.getElementById('calendar-task-detail-status');
            const taskDetailDescription = document.getElementById('calendar-task-detail-description');
            let activeTaskEvent = null;
            let activeLeadEvent = null;
            let activityCalendar = null;

            const syncTaskDateInputs = (value = '') => {
                if (taskDateInput) taskDateInput.value = value;
                if (taskDateVisibleInput) taskDateVisibleInput.value = value;
            };

            const resetTaskForm = () => {
                taskForm?.reset();
                if (taskForm) {
                    taskForm.action = @json(route('dashboard.calendar-events.store'));
                }
                if (taskFormMethodInput) {
                    taskFormMethodInput.value = 'POST';
                }
                syncTaskDateInputs(new Date().toISOString().slice(0, 10));
                if (taskTypeInput) taskTypeInput.value = 'task';
                if (taskStatusInput) taskStatusInput.value = 'pending';
                if (saveTaskBtn) saveTaskBtn.textContent = 'Save Task';
                deleteTaskBtn?.classList.add('hidden');
                if (deleteTaskForm) deleteTaskForm.action = '';
                activeTaskEvent = null;
            };

            const openTaskModal = (dateString = '') => {
                if (!taskModal || !taskDateInput) return;
                resetTaskForm();
                taskModal.classList.remove('hidden');
                taskModal.classList.add('flex');
                syncTaskDateInputs(dateString || new Date().toISOString().slice(0, 10));
            };

            const closeTaskModal = () => {
                if (!taskModal) return;
                taskModal.classList.add('hidden');
                taskModal.classList.remove('flex');
            };

            const openTaskDetailModal = (event) => {
                if (!taskDetailModal) return;
                activeTaskEvent = event;
                taskDetailTitle.textContent = event.title || 'Task';
                taskDetailDate.textContent = event.extendedProps.eventDate || event.startStr || '';
                taskDetailLocation.textContent = event.extendedProps.location || 'N/A';
                taskDetailType.textContent = event.extendedProps.eventType || 'task';
                taskDetailStatus.textContent = event.extendedProps.status || 'pending';
                taskDetailDescription.textContent = event.extendedProps.description || 'N/A';
                taskDetailModal.classList.remove('hidden');
                taskDetailModal.classList.add('flex');
            };

            const closeTaskDetailModal = () => {
                if (!taskDetailModal) return;
                taskDetailModal.classList.add('hidden');
                taskDetailModal.classList.remove('flex');
            };

            const leadFollowUpForm = document.getElementById('lead-follow-up-form');
            const leadActionTypeInput = document.getElementById('lead-action-type');
            const leadActionSelector = document.getElementById('lead-action-selector');
            const leadRescheduleFields = document.getElementById('lead-follow-up-reschedule-fields');
            const leadStatusFields = document.getElementById('lead-status-fields');
            const leadFollowUpDetailLink = document.getElementById('lead-follow-up-detail-link');
            const closeLeadFollowUpModalBtn = document.getElementById('close-lead-follow-up-modal');
            const cancelLeadFollowUpModalBtn = document.getElementById('cancel-lead-follow-up-modal');
            const saveLeadFollowUpBtn = document.getElementById('save-lead-follow-up-btn');
            const leadFollowUpDateInput = document.getElementById('lead-follow-up-date');
            const leadFollowUpDateError = document.getElementById('lead-follow-up-date-error');
            const leadFollowUpRemarkInput = document.getElementById('lead-follow-up-remark');
            const leadFollowUpRemarkError = document.getElementById('lead-follow-up-remark-error');
            const leadStatusSelect = document.getElementById('lead-status-select');
            const leadFollowUpLeadName = document.getElementById('lead-follow-up-lead-name');
            const leadFollowUpCurrentDate = document.getElementById('lead-follow-up-current-date');

            const leadFollowUpValidator = $('#lead-follow-up-form').validate({
                ignore: [],
                errorClass: 'text-xs text-red-600',
                errorElement: 'span',
                highlight: function (element) {
                    element.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                },
                unhighlight: function (element) {
                    element.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                },
                rules: {
                    summary: { required: true, maxlength: 5000 },
                    next_followup_date: { required: true, min: dashboardToday }
                },
                messages: {
                    summary: { required: 'Please enter today\'s follow-up summary.' },
                    next_followup_date: {
                        required: 'Please choose next follow-up date.',
                        min: 'Please choose today or a future date.'
                    }
                },
                errorPlacement: function (error, element) {
                    if (element.attr('name') === 'summary') {
                        const slot = document.getElementById('lead-follow-up-remark-error');
                        if (slot) {
                            slot.textContent = error.text();
                            slot.classList.remove('hidden');
                            return;
                        }
                    }

                    if (element.attr('name') === 'next_followup_date') {
                        const slot = document.getElementById('lead-follow-up-date-error');
                        if (slot) {
                            slot.textContent = error.text();
                            slot.classList.remove('hidden');
                            return;
                        }
                    }

                    error.insertAfter(element);
                }
            });

            const getLeadEventDisplay = (title, followUpDate, status = '') => {
                const dateValue = String(followUpDate || '').slice(0, 10);
                const overdue = dateValue ? dateValue < dashboardToday : false;
                const normalizedTitle = String(title || 'Lead');
                const displayTitle = overdue ? `${normalizedTitle} (Overdue)` : normalizedTitle;
                const tooltipParts = [normalizedTitle];

                if (dateValue) {
                    tooltipParts.push(`Next follow-up: ${dateValue}`);
                }

                if (overdue) {
                    tooltipParts.push('Overdue');
                }

                if (status) {
                    tooltipParts.push(status);
                }

                return {
                    displayTitle,
                    tooltip: tooltipParts.join(' | '),
                    isOverdue: overdue,
                };
            };

            const escapeHtml = (value = '') => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const formatStatusLabel = (status = '') => String(status || 'Pending')
                .replaceAll('_', ' ')
                .replaceAll('-', ' ')
                .replace(/\b\w/g, (letter) => letter.toUpperCase());

            const setFollowUpLocked = (locked) => {
                document.getElementById('lead-follow-up-done-notice')?.classList.toggle('hidden', !locked);
                if (leadFollowUpRemarkInput) leadFollowUpRemarkInput.disabled = locked;
                if (leadFollowUpDateInput) leadFollowUpDateInput.disabled = locked;
                if (leadStatusSelect) leadStatusSelect.disabled = locked;
                if (saveLeadFollowUpBtn) saveLeadFollowUpBtn.disabled = locked;
            };

            const renderLeadDetails = (payload = {}) => {
                const lead = payload.lead || {};
                const histories = Array.isArray(payload.latest_followup_summaries) ? payload.latest_followup_summaries : [];
                const customFields = Array.isArray(lead.custom_fields) ? lead.custom_fields : [];
                const loading = document.getElementById('lead-follow-up-loading');
                const info = document.getElementById('lead-follow-up-info');
                const setText = (id, value, fallback = 'N/A') => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = value || fallback;
                };

                loading?.classList.add('hidden');
                info?.classList.remove('hidden');
                if (leadFollowUpLeadName) leadFollowUpLeadName.textContent = lead.name || 'Lead';
                setText('lead-info-company', lead.company_name);
                setText('lead-info-status', lead.status);
                setText('lead-info-followup-status', lead.last_follow_up_status ? formatStatusLabel(lead.last_follow_up_status) : null);
                setText('lead-info-mobile', lead.mobile);
                setText('lead-info-email', lead.email);
                setText('lead-info-address', lead.address);
                setText('lead-info-last-summary', lead.last_follow_up_summary, 'No follow-up history yet.');
                setFollowUpLocked(lead.can_add_followup === false);

                const customWrap = document.getElementById('lead-info-custom-fields');
                if (customWrap) {
                    customWrap.innerHTML = customFields.length
                        ? customFields.map((field) => `
                            <div class= bg-white p-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">${escapeHtml(field.label || 'Custom Field')}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800">${escapeHtml(field.value || 'N/A')}</p>
                            </div>
                        `).join('')
                        : '<div class= bg-white p-3 text-sm text-slate-500">No custom fields.</div>';
                }

                const historyWrap = document.getElementById('lead-followup-history-list');
                if (historyWrap) {
                    historyWrap.innerHTML = histories.length
                        ? histories.map((item) => `
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold text-slate-500">${escapeHtml(formatStatusLabel(item.status))}</span>
                                    <span class="text-[11px] font-semibold text-slate-400">${escapeHtml(item.followup_date || '')}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-wrap text-sm leading-5 text-slate-700">${escapeHtml(item.summary || '')}</p>
                            </div>
                        `).join('')
                        : '<div class="text-slate-500">No follow-up summaries yet.</div>';
                }
            };

            const setLeadModalLoading = () => {
                document.getElementById('lead-follow-up-loading')?.classList.remove('hidden');
                document.getElementById('lead-follow-up-info')?.classList.add('hidden');
                const historyWrap = document.getElementById('lead-followup-history-list');
                if (historyWrap) historyWrap.innerHTML = '<div class="text-slate-500">Loading follow-up summaries...</div>';
            };

            const upsertCalendarEvent = (payload = {}) => {
                if (!activityCalendar || !payload?.id) return;
                const existing = activityCalendar.getEventById(payload.id);
                if (existing) {
                    existing.setProp('title', payload.title || existing.title);
                    existing.setStart(payload.start || existing.startStr);
                    existing.setProp('backgroundColor', payload.color || existing.backgroundColor);
                    existing.setProp('borderColor', payload.color || existing.borderColor);
                    Object.entries(payload).forEach(([key, value]) => {
                        if (!['id', 'title', 'start', 'allDay', 'url', 'color'].includes(key)) {
                            existing.setExtendedProp(key, value);
                        }
                    });
                    return;
                }
                activityCalendar.addEvent(payload);
            };

            const createLeadEventStub = (payload = {}) => ({
                title: payload.title || 'Lead',
                startStr: payload.followUpDate || dashboardToday,
                url: payload.leadUrl || '',
                extendedProps: {
                    followUpDate: payload.followUpDate || '',
                    status: payload.status || '',
                    statusId: payload.statusId || '',
                    scheduleId: payload.scheduleId || '',
                    actionUrl: payload.actionUrl || '',
                    detailsUrl: payload.detailsUrl || '',
                    historyUrl: payload.historyUrl || '',
                    url: payload.leadUrl || '',
                    latestComments: payload.latestComments || [],
                    displayTitle: payload.displayTitle || payload.title || 'Lead',
                    tooltip: payload.tooltip || '',
                },
                setStart() {},
                setExtendedProp(key, value) {
                    this.extendedProps[key] = value;
                },
                setProp() {},
            });

            const setLeadActionMode = (mode = 'reschedule') => {
                if (leadActionTypeInput) {
                    leadActionTypeInput.value = 'followup';
                }

                if (leadFollowUpDateInput) {
                    leadFollowUpDateInput.disabled = false;
                    leadFollowUpDateInput.min = dashboardToday;
                }

                if (leadFollowUpRemarkInput) {
                    leadFollowUpRemarkInput.disabled = false;
                }

                if (leadStatusSelect) {
                    leadStatusSelect.disabled = false;
                    leadStatusSelect.required = false;
                }

                if (saveLeadFollowUpBtn) {
                    saveLeadFollowUpBtn.textContent = 'Save Follow-up';
                }
            };

            const clearLeadFollowUpValidation = () => {
                if (leadFollowUpDateError) {
                    leadFollowUpDateError.textContent = '';
                    leadFollowUpDateError.classList.add('hidden');
                }

                if (leadFollowUpRemarkError) {
                    leadFollowUpRemarkError.textContent = '';
                    leadFollowUpRemarkError.classList.add('hidden');
                }

                leadFollowUpValidator?.resetForm();
                leadFollowUpDateInput?.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                leadFollowUpRemarkInput?.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
            };

            const setLeadFollowUpError = (element, errorElement, message) => {
                if (!element || !errorElement) return false;

                errorElement.textContent = message;
                errorElement.classList.toggle('hidden', !message);
                element.classList.toggle('border-red-500', !!message);
                element.classList.toggle('focus:border-red-500', !!message);
                element.classList.toggle('focus:ring-red-500', !!message);
                element.setAttribute('aria-invalid', message ? 'true' : 'false');

                return !message;
            };

            const validateLeadFollowUpDate = () => {
                const actionType = String(leadActionTypeInput?.value || 'reschedule');
                const followUpDate = String(leadFollowUpDateInput?.value || '').trim();

                if (actionType === 'status_change') {
                    return setLeadFollowUpError(leadFollowUpDateInput, leadFollowUpDateError, '');
                }

                if (!followUpDate) {
                    return setLeadFollowUpError(leadFollowUpDateInput, leadFollowUpDateError, 'Please choose a next follow-up date.');
                }

                if (followUpDate < dashboardToday) {
                    return setLeadFollowUpError(leadFollowUpDateInput, leadFollowUpDateError, 'Past dates are not allowed. Please select today or a future date.');
                }

                return setLeadFollowUpError(leadFollowUpDateInput, leadFollowUpDateError, '');
            };

            const validateLeadFollowUpRemark = () => {
                const remark = String(leadFollowUpRemarkInput?.value || '').trim();

                if (!remark) {
                    return setLeadFollowUpError(leadFollowUpRemarkInput, leadFollowUpRemarkError, 'Remark is required.');
                }

                return setLeadFollowUpError(leadFollowUpRemarkInput, leadFollowUpRemarkError, '');
            };

            const syncLeadEventState = (event, updates = {}) => {
                if (!event) return;

                const followUpDate = updates.followUpDate ?? event.extendedProps.followUpDate ?? event.startStr ?? '';
                const statusName = updates.statusName ?? event.extendedProps.status ?? '';
                const statusId = updates.statusId ?? event.extendedProps.statusId ?? '';
                const title = event.title || event.extendedProps.displayTitle || 'Lead';
                const display = getLeadEventDisplay(title, followUpDate, statusName);

                if (followUpDate) {
                    event.setStart(followUpDate);
                }
                event.setExtendedProp('followUpDate', followUpDate);
                event.setExtendedProp('status', statusName);
                event.setExtendedProp('statusId', statusId);
                event.setExtendedProp('displayTitle', display.displayTitle);
                event.setExtendedProp('tooltip', display.tooltip);
                event.setExtendedProp('isOverdue', display.isOverdue);
                event.setProp('backgroundColor', display.isOverdue ? '#dc2626' : '#2563eb');
                event.setProp('borderColor', display.isOverdue ? '#dc2626' : '#2563eb');
            };

            const openLeadActionModal = (event) => {
                if (!leadFollowUpModal || !leadFollowUpForm) return;

                activeLeadEvent = event;
                const leadTitle = event.title || 'Lead';
                const followUpDate = event.extendedProps.followUpDate || event.startStr || '';
                const currentStatus = event.extendedProps.status || '';
                const leadUrl = event.url || event.extendedProps.url || '';

                leadFollowUpLeadName.textContent = leadTitle;
                leadFollowUpCurrentDate.textContent = followUpDate
                    ? `Current schedule: ${followUpDate}${currentStatus ? ` | ${formatStatusLabel(currentStatus)}` : ''}`
                    : 'No follow-up date set for this schedule.';
                const scheduleInput = document.getElementById('lead-follow-up-schedule-id');
                if (scheduleInput) scheduleInput.value = event.extendedProps.scheduleId || '';
                leadFollowUpDateInput.value = '';
                leadFollowUpRemarkInput.value = '';
                if (leadStatusSelect) leadStatusSelect.value = 'Pending';
                clearLeadFollowUpValidation();
                if (leadFollowUpDateInput) {
                    leadFollowUpDateInput.min = dashboardToday;
                }
                if (leadFollowUpDetailLink) {
                    leadFollowUpDetailLink.href = leadUrl || '#';
                    leadFollowUpDetailLink.classList.toggle('pointer-events-none', !leadUrl);
                    leadFollowUpDetailLink.classList.toggle('opacity-40', !leadUrl);
                    leadFollowUpDetailLink.classList.toggle('cursor-not-allowed', !leadUrl);
                    leadFollowUpDetailLink.setAttribute('aria-disabled', leadUrl ? 'false' : 'true');
                    if (leadUrl) {
                        leadFollowUpDetailLink.removeAttribute('tabindex');
                    } else {
                        leadFollowUpDetailLink.setAttribute('tabindex', '-1');
                    }
                }
                leadFollowUpForm.action = event.extendedProps.actionUrl || '';
                setLeadActionMode('reschedule');
                setFollowUpLocked(false);
                setLeadModalLoading();
                leadFollowUpModal.classList.remove('hidden');
                leadFollowUpModal.classList.add('flex');
                if (event.extendedProps.detailsUrl) {
                    fetch(event.extendedProps.detailsUrl, {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then((response) => response.ok ? response.json() : Promise.reject(new Error('Unable to load lead details.')))
                        .then(renderLeadDetails)
                        .catch(() => {
                            const loading = document.getElementById('lead-follow-up-loading');
                            if (loading) loading.textContent = 'Unable to load lead details. You can still save the follow-up.';
                        });
                }
                setTimeout(() => leadFollowUpRemarkInput?.focus(), 0);
            };

            document.querySelectorAll('.js-open-lead-followup').forEach((button) => {
                button.addEventListener('click', () => {
                    openLeadActionModal(createLeadEventStub({
                        title: button.dataset.leadTitle || 'Lead',
                        followUpDate: button.dataset.leadFollowUpDate || '',
                        status: button.dataset.leadStatus || '',
                        statusId: button.dataset.leadStatusId || '',
                        scheduleId: button.dataset.leadScheduleId || '',
                        leadUrl: button.dataset.leadUrl || '',
                        actionUrl: button.dataset.leadActionUrl || '',
                        detailsUrl: button.dataset.leadDetailsUrl || '',
                        historyUrl: button.dataset.leadHistoryUrl || '',
                    }));
                });
            });

            const closeLeadFollowUpModal = () => {
                if (!leadFollowUpModal) return;
                leadFollowUpModal.classList.add('hidden');
                leadFollowUpModal.classList.remove('flex');
                activeLeadEvent = null;
            };

            const openTaskEditModal = (event) => {
                if (!taskModal || !taskForm || !taskFormMethodInput) return;

                closeTaskDetailModal();
                resetTaskForm();
                activeTaskEvent = event;
                taskForm.action = event.extendedProps.updateUrl || taskForm.action;
                taskFormMethodInput.value = 'PATCH';
                taskTitleInput.value = event.title || '';
                taskTypeInput.value = event.extendedProps.eventType || 'task';
                taskLocationInput.value = event.extendedProps.location || '';
                taskStatusInput.value = event.extendedProps.status || 'pending';
                taskDescriptionInput.value = event.extendedProps.description || '';
                syncTaskDateInputs(event.extendedProps.eventDate || event.startStr || '');
                if (saveTaskBtn) saveTaskBtn.textContent = 'Update Task';
                if (deleteTaskForm && event.extendedProps.deleteUrl) {
                    deleteTaskForm.action = event.extendedProps.deleteUrl;
                    deleteTaskBtn?.classList.remove('hidden');
                }
                taskModal.classList.remove('hidden');
                taskModal.classList.add('flex');
            };

            openTaskModalBtn?.addEventListener('click', () => openTaskModal());
            closeTaskModalBtn?.addEventListener('click', closeTaskModal);
            cancelTaskModalBtn?.addEventListener('click', closeTaskModal);
            closeTaskDetailModalBtn?.addEventListener('click', closeTaskDetailModal);
            editTaskBtn?.addEventListener('click', () => {
                if (activeTaskEvent) {
                    openTaskEditModal(activeTaskEvent);
                }
            });
            taskDateVisibleInput?.addEventListener('input', (e) => {
                syncTaskDateInputs(e.target.value);
            });
            taskModal?.addEventListener('click', (e) => {
                if (e.target === taskModal) closeTaskModal();
            });
            taskDetailModal?.addEventListener('click', (e) => {
                if (e.target === taskDetailModal) closeTaskDetailModal();
            });
            closeLeadFollowUpModalBtn?.addEventListener('click', closeLeadFollowUpModal);
            cancelLeadFollowUpModalBtn?.addEventListener('click', closeLeadFollowUpModal);
            leadFollowUpModal?.addEventListener('click', (e) => {
                if (e.target === leadFollowUpModal) closeLeadFollowUpModal();
            });
            leadActionSelector?.addEventListener('change', (e) => {
                setLeadActionMode(e.target.value || 'reschedule');
                clearLeadFollowUpValidation();
            });
            leadFollowUpDateInput?.addEventListener('input', validateLeadFollowUpDate);
            leadFollowUpDateInput?.addEventListener('change', validateLeadFollowUpDate);
            leadFollowUpRemarkInput?.addEventListener('input', validateLeadFollowUpRemark);
            leadFollowUpRemarkInput?.addEventListener('blur', validateLeadFollowUpRemark);

            leadFollowUpForm?.addEventListener('submit', async (e) => {
                e.preventDefault();

                if (!activeLeadEvent) return;
                if (saveLeadFollowUpBtn?.disabled) return;

                if (!leadFollowUpValidator.form()) {
                    if (!String(leadFollowUpRemarkInput?.value || '').trim()) {
                        leadFollowUpRemarkInput?.focus();
                    } else {
                        leadFollowUpDateInput?.focus();
                    }
                    return;
                }

                const followUpDate = String(leadFollowUpDateInput?.value || '').trim();
                if (!followUpDate) return;

                const submitUrl = leadFollowUpForm.action || '';
                if (!submitUrl) return;

                saveLeadFollowUpBtn?.setAttribute('disabled', 'disabled');
                saveLeadFollowUpBtn && (saveLeadFollowUpBtn.textContent = 'Saving...');

                try {
                    const response = await fetch(submitUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                        },
                        body: new FormData(leadFollowUpForm),
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok || !data?.success) {
                        throw new Error(data?.message || 'Unable to update the lead action.');
                    }

                    upsertCalendarEvent(data.current_event);
                    upsertCalendarEvent(data.next_event);
                    activityCalendar?.render();
                    closeLeadFollowUpModal();

                    if (window.Swal) {
                        await Swal.fire({
                            icon: 'success',
                            title: 'Follow-up saved',
                            text: data?.message || 'Next follow-up scheduled successfully.',
                            confirmButtonColor: '#0f172a',
                        });
                    }

                    window.location.reload();
                } catch (error) {
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Save failed',
                            text: error?.message || 'Unable to update lead follow-up.',
                            confirmButtonColor: '#0f172a',
                        });
                    }
                } finally {
                    saveLeadFollowUpBtn?.removeAttribute('disabled');
                    setLeadActionMode();
                }
            });

            const commonOptions = {
                initialView: 'dayGridMonth',
                height: 'auto',
                headerToolbar: {
                    left: 'prev,next',
                    center: 'title',
                    right: ''
                },
                dayMaxEventRows: 4,
                dayMaxEvents: 4,
                moreLinkClick: 'popover',
                displayEventTime: false,
                eventClassNames(arg) {
                    const classes = [`event-${arg.event.extendedProps.type || 'default'}`];
                    if (
                        arg.event.extendedProps.type === 'lead' &&
                        String(arg.event.extendedProps.followUpDate || arg.event.startStr || '').slice(0, 10) < dashboardToday
                    ) {
                        classes.push('event-overdue');
                    }
                    return classes;
                },
                eventContent(arg) {
                    const wrap = document.createElement('div');
                    wrap.className = 'fc-event-pill';
                    wrap.title = arg.event.extendedProps.tooltip || arg.event.title || '';
                    const eventColor = arg.event.backgroundColor || arg.event.extendedProps.color || '';
                    if (eventColor) {
                        wrap.style.background = `color-mix(in srgb, ${eventColor} 16%, white)`;
                        wrap.style.color = eventColor;
                    }

                    const chip = document.createElement('span');
                    chip.className = 'event-chip';

                    const text = document.createElement('span');
                    text.className = 'event-text';
                    text.textContent = arg.event.extendedProps.displayTitle || arg.event.title || '';

                    wrap.append(chip, text);
                    return {
                        domNodes: [wrap]
                    };
                },
                eventOrder(a, b) {
                    const rankA = Number(a.extendedProps.statusRank ?? 50);
                    const rankB = Number(b.extendedProps.statusRank ?? 50);
                    if (rankA !== rankB) return rankA - rankB;
                    return String(a.title || '').localeCompare(String(b.title || ''));
                },
                eventDidMount(info) {
                    info.el.style.border = '0';
                    info.el.style.background = 'transparent';
                    info.el.style.boxShadow = 'none';
                    info.el.style.padding = '0';
                    info.el.style.margin = '0';
                    info.el.style.lineHeight = '1';
                    info.el.style.cursor = 'pointer';
                },
                dayCellDidMount(info) {
                    const frame = info.el.querySelector('.fc-daygrid-day-frame');
                    if (!frame || frame.dataset.taskClickBound === '1') return;
                    frame.dataset.taskClickBound = '1';
                    frame.style.cursor = 'pointer';
                    frame.addEventListener('click', (event) => {
                        if (
                            event.target.closest('.fc-daygrid-event') ||
                            event.target.closest('.fc-event') ||
                            event.target.closest('.fc-event-main') ||
                            event.target.closest('.fc-more-link')
                        ) {
                            return;
                        }
                        openTaskModal(info.date.toISOString().slice(0, 10));
                    }, true);
                },
                dateClick(info) {
                    openTaskModal(info.dateStr);
                },
                eventClick(info) {
                    info.jsEvent.preventDefault();
                    info.jsEvent.stopImmediatePropagation();
                    info.jsEvent.stopPropagation();
                    if (info.event.extendedProps.type === 'custom-task') {
                        openTaskDetailModal(info.event);
                        return;
                    }

                    if (info.event.extendedProps.type === 'lead') {
                        openLeadActionModal(info.event);
                        return;
                    }

                    if (info.event.url) {
                        window.location.href = info.event.url;
                    }
                }
            };

            const activityCalendarEl = document.getElementById('activity-calendar');
            if (activityCalendarEl) {
                activityCalendar = new FullCalendar.Calendar(activityCalendarEl, {
                    ...commonOptions,
                    events: calendarEvents,
                    eventDisplay: 'block',
                    displayEventEnd: true
                });
                activityCalendar.render();
            }
        });
    </script>

    @if (session('onboarding_trial_notice'))
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const message = @js(session('onboarding_trial_notice'));

                if (window.Swal) {
                    Swal.fire({
                        title: 'Free Trial Started',
                        text: message,
                        icon: 'success',
                        confirmButtonColor: '#0f172a',
                    });
                    return;
                }

                window.alert(message);
            });
        </script>
    @endif
</x-app-layout>
