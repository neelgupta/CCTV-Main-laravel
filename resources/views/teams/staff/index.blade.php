<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Staff Management</h2>
                <p class="mt-1 text-sm text-slate-500">Manage staff records and import or export team data.</p>
            </div>
            @php
                $headerUser = auth()->user();
                $canCreateStaff = $headerUser->isCompanyAdmin() || $headerUser->hasAnyPermission('teams.staff.create', 'settings.users.manage');
                $canExportStaff = $headerUser->isCompanyAdmin() || $headerUser->hasPermission('teams.staff.view');
                $canImportStaff = $headerUser->isCompanyAdmin();
            @endphp
            <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                @if ($canExportStaff)
                    <a href="{{ route('teams.staff.export') }}"
                        class="inline-flex w-full items-center justify-center  border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50 sm:w-auto">
                        Export
                    </a>
                @endif
                @if ($canImportStaff)
                    <button type="button" id="open-staff-import-modal"
                        class="inline-flex w-full items-center justify-center  border border-cyan-200 bg-cyan-50 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-cyan-800 hover:bg-cyan-100 sm:w-auto">
                        Import
                    </button>
                @endif
                @if ($canCreateStaff)
                    <button type="button" id="open-add-staff-modal" onclick="window.openAddStaffModal && window.openAddStaffModal()"
                        class="inline-flex w-full items-center justify-center  border border-transparent bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white hover:bg-indigo-700 sm:w-auto">
                        Add New Staff
                    </button>
                @endif
            </div>
        </div>
    </x-slot>

    @php
        $user = auth()->user();
        $canDeleteStaff = $user->isCompanyAdmin() || $user->hasPermission('teams.staff.delete');
        $canViewSalary = $user->isCompanyAdmin() || $user->hasPermission('teams.staff.salary.view');
        $canViewAdvance = $user->isCompanyAdmin() || $user->hasPermission('teams.staff.salary.advance.view');
        $canManageAttendance = $user->isCompanyAdmin() || $user->hasPermission('teams.staff.attendance.bulk.manage');
        $canReactivateExpiredStaff = $user->isCompanyAdmin() || $user->hasPermission('teams.staff.edit');
        $canTrackStaffRoutes = $canTrackStaffRoutes ?? false;
        $trackingStaffOptions = collect($staffs ?? [])
            ->filter(fn ($staff) => ! ($staff->companySubscription?->plan?->shouldDisableStaffTracking() ?? true))
            ->map(fn ($staff) => [
                'id' => (int) $staff->id,
                'route_key' => $staff->getRouteKey(),
                'name' => $staff->name,
                'employee_id' => $staff->employee_id,
                'tracking_enabled' => true,
                'label' => $staff->name . ($staff->employee_id ? ' - ' . $staff->employee_id : '') . ' (Tracking)',
            ])
            ->values()
            ->all();
        $allowedStaffTabs = ['staff-list', 'bulk-attendance', 'advance', 'salary'];

        if ($canTrackStaffRoutes) {
            $allowedStaffTabs[] = 'route-tracking';
        }
    @endphp

    <div
        class="py-6 sm:py-12"
        x-data="{
            activeTab: @js(in_array(request('tab'), $allowedStaffTabs, true) ? request('tab') : 'staff-list'),
            todayDate: '{{ now()->toDateString() }}',
            attendanceDate: '{{ $attendanceDate ?? now()->toDateString() }}',
            todayMonthKey: '{{ now()->format('Y-m') }}',
            selectedMonthKey: '{{ request('month', now()->format('Y-m')) }}',
            selectedAttendanceStatus: 'Present',
            bulkExportBase: '{{ route('attendance.bulk.export') }}',
            salaryMonth: '{{ $salaryMonthKey ?? now()->format('Y-m') }}',
            ready: false,
        }"
        x-init="
            const syncTabToUrl = (value) => {
                const url = new URL(window.location);
                if (value === 'staff-list') {
                    url.searchParams.delete('tab');
                } else {
                    url.searchParams.set('tab', value);
                }
                history.replaceState({}, '', url);
            };
            syncTabToUrl(activeTab);
            $watch('activeTab', (value) => {
                syncTabToUrl(value);

                if (value === 'route-tracking') {
                    window.setTimeout(() => window.dispatchEvent(new Event('resize')), 120);
                }
            });
            const routeTrackingStaffSelect = document.getElementById('route_tracking_staff_id');
            if (routeTrackingStaffSelect) {
                routeTrackingStaffSelect.addEventListener('change', () => {
                    selectedStaffId = routeTrackingStaffSelect.value || 'all';
                    reload();
                });
            }
            $nextTick(() => ready = true);
        "
    >
        <div class="w-full">
            <div x-show="!ready" x-cloak class="mb-4 inline-flex items-center gap-2  border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 shadow-sm">
                <svg class="h-4 w-4 animate-spin text-indigo-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                </svg>
                Loading...
            </div>
            @if(session('success'))
                <div class="mb-4  border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" data-flash-success>
                    {{ session('success') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4  border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif

           @if ($canViewSalary || $canViewAdvance || $canManageAttendance || $canTrackStaffRoutes)
    <div class="mb-6 flex gap-4 overflow-x-auto border-b border-gray-200 px-2 pb-1 sm:gap-8 sm:px-0">

        <button type="button" @click="activeTab = 'staff-list'"
            :class="activeTab === 'staff-list'
                ? 'border-indigo-600 text-indigo-600'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="shrink-0 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors duration-200 sm:py-4">
            Staff List
        </button>

        @if ($canViewSalary)
            <a href="{{ route('teams.payroll.index') }}"
                class="shrink-0 whitespace-nowrap border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 transition-colors duration-200 hover:border-gray-300 hover:text-gray-700 sm:py-4">
                Salary
            </a>
        @endif

        @if ($canViewAdvance)
            <a href="{{ route('teams.payroll.advance.index') }}"
                class="shrink-0 whitespace-nowrap border-b-2 border-transparent px-1 py-3 text-sm font-medium text-gray-500 transition-colors duration-200 hover:border-gray-300 hover:text-gray-700 sm:py-4">
                Advance
            </a>
        @endif

        @if ($canManageAttendance)
            <button type="button" @click="activeTab = 'bulk-attendance'"
                :class="activeTab === 'bulk-attendance'
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="shrink-0 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors duration-200 sm:py-4">
                Bulk Attendance
            </button>
        @endif

        @if ($canTrackStaffRoutes)
            <button type="button" @click="activeTab = 'route-tracking'"
                :class="activeTab === 'route-tracking'
                    ? 'border-indigo-600 text-indigo-600'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="shrink-0 whitespace-nowrap border-b-2 px-1 py-3 text-sm font-medium transition-colors duration-200 sm:py-4">
                Staff Tracking
            </button>
        @endif
    </div>
@endif

<div x-show="ready && activeTab === 'staff-list'" x-cloak>
    <div class="overflow-hidden border border-gray-200 bg-white shadow-sm sm:">
        <div class="overflow-x-auto">
        <table class="min-w-[920px] w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Staff Name</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Email</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Role</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Plan</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Tracking</th>
                    <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Status</th>
                    <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @foreach ($staffs as $staff)
                    @php
                        $canEditStaff = $user->isCompanyAdmin()
                            || $user->hasPermission('teams.staff.edit')
                            || ($user->hasPermission('teams.staff.self.manage') && (int) $staff->user_id === (int) $user->id);
                        $staffTrackingEnabled = $subscriptionLimitService->staffTrackingEnabledForStaff($staff);
                    @endphp
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 sm:px-6 sm:py-4">
                            <a href="{{ route('teams.staff.show', $staff) }}" class="hover:text-indigo-600 transition-colors">
                                {{ $staff->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 sm:px-6 sm:py-4">{{ $staff->user?->email ?? '-' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600 sm:px-6 sm:py-4">
                            @php
                                $staffRole = $staff->user?->roleRecord?->name
                                    ?? ($staff->user?->role ? \Illuminate\Support\Str::of($staff->user->role)->replace('_', ' ')->headline()->toString() : null);
                            @endphp
                            @if ($staffRole)
                                <span class="inline-flex items-center bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                    {{ $staffRole }}
                                </span>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600 sm:px-6 sm:py-4">
                            @php
                                $staffPlan = $staff->companySubscription?->plan;
                                $staffSubscription = $staff->companySubscription;
                                $trackingEnabled = $staffPlan?->shouldDisableStaffTracking() ? false : true;
                                $trackingLabel = $trackingEnabled ? 'Tracking' : 'Non Tracking';
                                $subscriptionStatus = null;

                                if ($staffSubscription) {
                                    $isActiveSubscription = $subscriptionLimitService->subscriptionIsActive($staffSubscription);

                                    $subscriptionStatus = $isActiveSubscription ? 'Active' : 'Expired';
                                }
                            @endphp
                            @if ($staffPlan)
                                <div class="space-y-0.5">
                                    <div class="font-semibold text-gray-900">{{ $staffPlan->name }}</div>
                                    <div class="text-[11px] text-gray-500">Start: {{ $staffSubscription?->starts_at?->format('d M Y') ?? '-' }}</div>
                                    <div class="text-[11px] text-gray-500">End: {{ $staffSubscription?->ends_at?->format('d M Y') ?? '-' }}</div>
                                </div>
                            @else
                                <span class="text-gray-400">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm sm:px-6 sm:py-4">
                            <span class="inline-flex items-center bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">
                                {{ $trackingLabel }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-sm sm:px-6 sm:py-4">
                            @if ($subscriptionStatus === 'Active')
                                <span class="inline-flex items-center bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                                    Active
                                </span>
                            @elseif ($subscriptionStatus === 'Expired')
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex items-center bg-rose-100 px-3 py-1 text-xs font-semibold text-rose-700">
                                        Expired
                                    </span>
                                    @if ($canReactivateExpiredStaff)
                                        <label class="inline-flex cursor-pointer items-center gap-2">
                                            <input
                                                type="checkbox"
                                                class="reactivate-staff-checkbox sr-only peer"
                                                data-staff-id="{{ $staff->getRouteKey() }}"
                                                data-staff-name="{{ $staff->name }}"
                                                data-current-plan="{{ $staffPlan?->name ?? 'Expired Plan' }}"
                                            >
                                            <span class="relative h-6 w-11 bg-slate-200 transition peer-checked:bg-emerald-600 after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:after:border after:border-slate-300 after:bg-white after:transition-all peer-checked:after:translate-x-full peer-checked:after:border-white"></span>
                                            <span class="text-xs font-semibold text-emerald-700">Activate</span>
                                        </label>
                                    @endif
                                </div>
                            @else
                                @if ($canReactivateExpiredStaff)
                                    <label class="inline-flex cursor-pointer items-center gap-2">
                                        <input
                                            type="checkbox"
                                            class="reactivate-staff-checkbox sr-only peer"
                                            data-staff-id="{{ $staff->getRouteKey() }}"
                                            data-staff-name="{{ $staff->name }}"
                                            data-current-plan=""
                                            data-mode="assign"
                                        >
                                        <span class="relative h-6 w-11 bg-slate-200 transition peer-checked:bg-indigo-600 after:absolute after:start-[2px] after:top-[2px] after:h-5 after:w-5 after:after:border after:border-slate-300 after:bg-white after:transition-all peer-checked:after:translate-x-full peer-checked:after:border-white"></span>
                                        <span class="text-xs font-semibold text-indigo-700">Assign Plan</span>
                                    </label>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-right text-sm font-medium sm:px-6 sm:py-4">
                            <div class="flex items-center justify-end gap-3 min-w-[132px]">
                                @if ($canEditStaff)
                                    <a href="{{ route('teams.staff.edit', $staff) }}" class="inline-flex h-9 w-9 items-center justify-center  bg-amber-50 text-amber-600 transition hover:bg-amber-100" title="Edit staff" aria-label="Edit staff">
                                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 3.487a2.25 2.25 0 1 1 3.182 3.182L7.5 19.213 3 21l1.787-4.5L16.862 3.487Z" />
                                        </svg>
                                    </a>
                                @endif
                                @if ($canDeleteStaff)
                                    <form action="{{ route('teams.staff.destroy', $staff) }}" method="POST" class="inline js-confirm-delete" data-confirm-title="Delete Staff?" data-confirm-text="This will permanently delete the staff profile and linked user account. This action cannot be undone." data-no-global-loader="1">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex h-9 w-9 items-center justify-center  bg-red-50 text-red-600 transition hover:bg-red-100" aria-label="Delete staff">
                                            <svg class="w-4 h-4" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208z"/></svg>
                                        </button>
                                    </form>
                                @endif
                                <a href="{{ route('teams.staff.show', $staff) }}" class="inline-flex h-9 w-9 items-center justify-center  bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100" title="View details" aria-label="View staff details">
                                    <svg class="w-5 h-5" viewBox="0 0 1024 1024" fill="currentColor"><path d="M360.08448 352.17408h276.48v40.96h-276.48zM360.08448 458.61376h276.48v40.96h-276.48zM360.08448 567.00416h122.88v40.96h-122.88z" /><path d="M472.90368 747.52H293.52448V276.48h409.6v223.09376a20.48 20.48 0 0 0 40.96 0V276.48c0-22.58432-18.37568-40.96-40.96-40.96h-409.6c-22.58432 0-40.96 18.37568-40.96 40.96v471.04c0 22.58432 18.37568 40.96 40.96 40.96h179.3792a20.48 20.48 0 0 0 0-40.96z" /><path d="M765.57312 745.984l-54.7328-55.76192a106.73664 106.73664 0 0 0 16.78336-57.43616c0-59.28448-48.2304-107.52-107.52-107.52s-107.52 48.23552-107.52 107.52 48.2304 107.52 107.52 107.52c23.36256 0 44.94336-7.57248 62.59712-20.2752l53.632 54.64576a20.4288 20.4288 0 0 0 14.6176 6.13376 20.48 20.48 0 0 0 14.62272-34.82624z m-145.46944-46.63808c-36.70016 0-66.56-29.85984-66.56-66.56s29.85984-66.56 66.56-66.56 66.56 29.85984 66.56 66.56-29.85472 66.56-66.56 66.56z" /></svg>
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        </div>
    </div>
</div>

@if ($canTrackStaffRoutes)
    <div
        x-show="ready && activeTab === 'route-tracking'"
        x-cloak
        x-data="staffTrackerPanel({
            dataUrl: @js(route('teams.staff.route-tracking.data')),
            staffOptions: @js($trackingStaffOptions),
            defaultDate: @js(now()->toDateString()),
            googleMapsScriptUrl: @js(filled(config('services.google_maps.key')) ? 'https://maps.googleapis.com/maps/api/js?key=' . config('services.google_maps.key') . '&v=weekly' : ''),
        })"
        x-init="init()"
        class="space-y-4"
    >
        <div class="overflow-hidden border border-sky-100 bg-gradient-to-r from-white via-white to-sky-50 shadow-sm">
            <div class="border-b border-slate-200 px-4 py-4 sm:px-6">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <div class="text-sm text-slate-500">
                        Last updated:
                        <span class="font-medium text-slate-700" x-text="lastUpdatedLabel"></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <label class="inline-flex cursor-pointer items-center gap-2 border border-slate-200 bg-white px-4 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            <input
                                type="checkbox"
                                class="h-4 w-4 border-sky-300 text-sky-600 focus:ring-sky-500"
                                x-model="autoRefreshEnabled"
                                @change="toggleAutoRefresh()"
                            >
                            <span x-text="`Auto refresh (${pollIntervalLabel})`"></span>
                        </label>
                        <button
                            type="button"
                            @click="reload()"
                            class="inline-flex items-center justify-center border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            Refresh now
                        </button>
                    </div>
                </div>
            </div>

            <div class="border-b border-slate-200 px-4 py-4 sm:px-6">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]">
                    <label class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                        Date
                        <input type="date" x-model="selectedDate" @change="reload()" class="mt-1 block w-full border-slate-300 text-sm shadow-sm focus:border-sky-500 focus:ring-sky-500">
                    </label>
                    <div>
                        <x-searchable-select
                            name="staff_filter"
                            id="route_tracking_staff_id"
                            label="Staff"
                            :options="collect($trackingStaffOptions)->map(fn ($staff) => ['id' => $staff['id'], 'label' => $staff['label']])"
                            selected=""
                            placeholder="Search staff..."
                            empty-label="All staff"
                        />
                    </div>
                    <div class="flex items-end">
                        <button type="button" @click="reload()" class="inline-flex h-[42px] w-full items-center justify-center bg-sky-600 px-4 text-sm font-semibold text-white transition hover:bg-sky-700">
                            Refresh route
                        </button>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 p-4 xl:grid-cols-[minmax(0,1.6fr)_minmax(340px,0.8fr)]">
                <div class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Routes loaded</div>
                            <div class="mt-2 text-2xl font-bold text-slate-950" x-text="summary.staff_count"></div>
                        </div>
                        <div class="border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Route points</div>
                            <div class="mt-2 text-2xl font-bold text-slate-950" x-text="summary.point_count"></div>
                        </div>
                        <div class="border border-slate-200 bg-white p-4 shadow-sm">
                            <div class="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Distance</div>
                            <div class="mt-2 text-2xl font-bold text-slate-950">
                                <span x-text="Number(summary.distance_km || 0).toFixed(2)"></span> km
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-slate-500">Roadmap view</h4>
                                <p class="mt-1 text-sm text-slate-500">Google Maps style roads, labels, landmarks, and a highlighted staff trail.</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.14em] text-slate-400">
                                <span class="rounded-full bg-slate-100 px-3 py-1" x-text="selectedDate"></span>
                                <span class="rounded-full bg-emerald-50 px-3 py-1 text-emerald-700">Roadmap default</span>
                            </div>
                        </div>
                        <div class="relative bg-slate-100">
                            <div class="absolute left-4 top-4 z-10">
                                <button
                                    type="button"
                                    @click="recenterMap()"
                                    class="inline-flex items-center gap-2 rounded-2xl border border-white/80 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-[0.14em] text-slate-700 shadow-lg shadow-slate-200/70 transition hover:bg-slate-50"
                                >
                                    <svg class="h-4 w-4 text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2v4M12 18v4M4 12H2M22 12h-2M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0-8 0" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                    Recenter
                                </button>
                            </div>
                            <div class="absolute inset-0 z-10 flex items-center justify-center bg-white/75 px-6 text-center backdrop-blur-sm" x-show="loading || mapLoading" x-cloak>
                                <div class="max-w-sm rounded-3xl border border-slate-200 bg-white px-5 py-4 shadow-xl">
                                    <svg class="mx-auto h-5 w-5 animate-spin text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    <p class="mt-3 text-sm font-semibold text-slate-900">Loading map and tracking data...</p>
                                    <p class="mt-1 text-xs text-slate-500">Preparing roads, route points, and place labels.</p>
                                </div>
                            </div>
                            <div class="absolute inset-0 z-10 flex items-center justify-center bg-white/80 px-6 text-center backdrop-blur-sm" x-show="!loading && !mapLoading && !error && routes.length === 0" x-cloak>
                                <div class="max-w-md rounded-3xl border border-dashed border-slate-300 bg-white px-6 py-8 shadow-sm">
                                    <svg class="mx-auto h-8 w-8 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                                        <path d="M3 7l6-3 6 3 6-3v13l-6 3-6-3-6 3V7z" stroke-linecap="round" stroke-linejoin="round"></path>
                                        <path d="M9 4v13M15 7v13" stroke-linecap="round" stroke-linejoin="round"></path>
                                    </svg>
                                    <h5 class="mt-3 text-sm font-bold text-slate-900">No location data available</h5>
                                    <p class="mt-2 text-sm text-slate-500">No route points were recorded for the selected staff and date yet.</p>
                                </div>
                            </div>
                            <div x-ref="mapCanvas" class="h-[520px] w-full min-h-[420px] sm:h-[620px] xl:h-[720px]"></div>
                        </div>
                    </div>
                    <div class="border border-sky-100 bg-sky-50 px-4 py-3 text-xs text-sky-800">
                        Full-day tracking is grouped from 12:00 AM to 11:59 PM for the selected date so admins can replay the staff trail like a delivery route.
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-slate-500">Staff trails</h4>
                            <p class="mt-1 text-sm text-slate-500">Tap a staff member to highlight their route on the map.</p>
                        </div>

                        <div class="max-h-[320px] space-y-3 overflow-y-auto p-4">
                            <template x-if="loading">
                                <div class="flex items-center gap-3 border border-sky-100 bg-sky-50 px-4 py-4 text-sm text-sky-700">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    Loading route data...
                                </div>
                            </template>

                            <template x-if="!loading && routes.length === 0">
                                <div class="border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                                    No route points were recorded for the selected date yet.
                                </div>
                            </template>

                            <template x-for="route in routes" :key="route.staff_id">
                                <button
                                    type="button"
                                    @click="focusRoute(route.staff_id)"
                                    class="w-full border px-4 py-4 text-left transition"
                                    :class="focusedRouteId === route.staff_id ? 'border-sky-300 bg-sky-50 shadow-sm' : 'border-slate-200 bg-white hover:border-sky-200 hover:bg-slate-50'"
                                >
                                    <div class="flex items-start gap-3">
                                        <span class="mt-1 h-3 w-3 shrink-0" :style="`background:${route.color}`"></span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center justify-between gap-2">
                                                <div class="truncate text-sm font-semibold text-slate-950" x-text="route.staff_name"></div>
                                                <span class="px-2.5 py-1 text-[11px] font-bold uppercase tracking-[0.14em]" :style="`background:${route.color}15;color:${route.color}`">
                                                    <span x-text="route.summary.point_count"></span> points
                                                </span>
                                            </div>
                                            <div class="mt-1 text-xs text-slate-500">
                                                ID: <span x-text="route.employee_id"></span>
                                            </div>
                                            <div class="mt-2 flex flex-wrap gap-2 text-[11px] text-slate-500">
                                                <span class="bg-slate-100 px-2 py-1">Distance: <span x-text="Number(route.summary.distance_km || 0).toFixed(2)"></span> km</span>
                                                <span class="bg-slate-100 px-2 py-1">First: <span x-text="formatTime(route.summary.first_seen)"></span></span>
                                                <span class="bg-slate-100 px-2 py-1">Last: <span x-text="formatTime(route.summary.last_seen)"></span></span>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-200 px-5 py-4">
                            <h4 class="text-sm font-bold uppercase tracking-[0.18em] text-slate-500">Selected route points</h4>
                            <p class="mt-1 text-sm text-slate-500" x-text="selectedRoute ? `${selectedRoute.staff_name} trail details` : 'Choose a staff member to inspect their trail.'"></p>
                        </div>

                        <div class="max-h-[300px] overflow-y-auto">
                            <template x-if="selectedRoute">
                                <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                                    <thead class="bg-slate-50 text-xs uppercase tracking-[0.14em] text-slate-500">
                                        <tr>
                                            <th class="px-4 py-3">#</th>
                                            <th class="px-4 py-3">Time</th>
                                            <th class="px-4 py-3">Coordinates</th>
                                            <th class="px-4 py-3">Source</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white">
                                        <template x-for="(point, index) in selectedRoute.points" :key="`${point.recorded_at}-${index}`">
                                            <tr>
                                                <td class="px-4 py-3 font-semibold text-slate-400" x-text="point.sequence || index + 1"></td>
                                                <td class="px-4 py-3 font-semibold text-slate-900" x-text="formatTime(point.recorded_at)"></td>
                                                <td class="px-4 py-3 text-slate-600">
                                                    <div x-text="`${point.latitude.toFixed(5)}, ${point.longitude.toFixed(5)}`"></div>
                                                    <div class="text-xs text-slate-400" x-text="point.location_name || 'GPS location'"></div>
                                                </td>
                                                <td class="px-4 py-3 text-slate-500" x-text="point.source || '-'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </template>

                            <template x-if="!selectedRoute">
                                <div class="px-4 py-8 text-sm text-slate-500">
                                    No route selected.
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>

            <template x-if="error">
                <div class="px-6 pb-5">
                    <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700" x-text="error"></div>
                </div>
            </template>
        </div>
    </div>
@endif

    <div id="staff-import-modal"
        class="{{ $errors->has('staff_import_file') ? '' : 'hidden ' }}fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6"
        aria-hidden="{{ $errors->has('staff_import_file') ? 'false' : 'true' }}"
        data-modal-open="{{ $errors->has('staff_import_file') ? 'true' : 'false' }}">
        <div class="absolute inset-0" data-close-staff-import-modal></div>
        <div class="relative w-full max-w-xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h4 class="text-lg font-bold text-slate-950">Import Staff</h4>
                    <p class="mt-1 text-sm text-slate-500">Upload CSV, XLS, or XLSX to create staff and linked users in bulk.</p>
                </div>
                <button type="button" data-close-staff-import-modal class="p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600" aria-label="Close import modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            @if ($errors->has('staff_import_file'))
                <div class="mt-4 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    @foreach ($errors->get('staff_import_file') as $message)
                        <p>{{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('teams.staff.import') }}" enctype="multipart/form-data" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="staff_import_file" class="mb-2 block text-sm font-semibold text-slate-700">Choose file</label>
                    <input id="staff_import_file" type="file" name="staff_import_file" accept=".csv,.xls,.xlsx" class="block w-full border border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 file:mr-3 file:file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700">
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('teams.staff.import.sample') }}" class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50">
                        Download Sample
                    </a>
                    <button type="submit" class="inline-flex items-center justify-center bg-cyan-600 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-cyan-700">
                        Import Staff
                    </button>
                </div>
            </form>

            <div class="mt-4 border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
                Staff import required columns: <span class="font-semibold">name, email, password, phone</span>. Optional columns like role, department, and join_date are also supported.
            </div>
        </div>
    </div>

    @if ($canCreateStaff)
        @php
            $openCreateStaffModal = $errors->hasAny([
                'name',
                'email',
                'password',
                'password_confirmation',
                'contact_number',
                'join_date',
                'profile_photo',
                'role_id',
                'company_subscription_id',
            ]);
        @endphp
        <div
            id="add-staff-modal"
            class="{{ $openCreateStaffModal ? '' : 'hidden ' }}fixed inset-0 z-[260] flex items-center justify-center bg-slate-950/55 px-4 py-6"
            aria-hidden="{{ $openCreateStaffModal ? 'false' : 'true' }}"
            data-modal-open="{{ $openCreateStaffModal ? 'true' : 'false' }}"
        >
            <div class="absolute inset-0" data-close-add-staff-modal></div>
            <div class="relative w-full max-w-6xl overflow-hidden bg-white shadow-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Add User & Staff Details</h3>
                        <p class="mt-1 text-sm text-slate-500">Create a login and assign the correct role in one step.</p>
                    </div>
                    <button
                        type="button"
                        class="p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                        data-close-add-staff-modal
                        aria-label="Close add staff modal"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </div>

                <div class="max-h-[calc(100vh-9rem)] overflow-y-auto px-5 py-5 sm:px-6">
                    @if ($openCreateStaffModal)
                        <div class="mb-4 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                            Please fix the highlighted fields and try again.
                        </div>
                    @endif

                    @include('settings.users._create-form', [
                        'formIdPrefix' => 'add_staff_modal',
                        'formAction' => route('settings.users.store'),
                        'submitLabel' => 'Create User & Profile',
                        'formTitle' => '',
                        'formDescription' => '',
                        'roles' => $roles,
                        'availableSubscriptionOptions' => $availableSubscriptionOptions,
                        'selectedPlanOption' => $selectedPlanOption,
                    ])
                </div>
            </div>
        </div>
    @endif

    @php
        $openReactivateModal = $errors->has('reactivate_company_subscription_id')
            || (filled(old('reactivate_staff_id')) && $errors->has('company_subscription_id'));
    @endphp
    <div
        id="reactivate-staff-modal"
        class="{{ $openReactivateModal ? '' : 'hidden ' }}fixed inset-0 z-[270] flex items-center justify-center bg-slate-950/55 px-4 py-6"
        aria-hidden="{{ $openReactivateModal ? 'false' : 'true' }}"
        data-modal-open="{{ $openReactivateModal ? 'true' : 'false' }}"
    >
        <div class="absolute inset-0" data-close-reactivate-staff-modal></div>
        <div class="relative w-full max-w-2xl overflow-hidden bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 sm:px-6">
                <div>
                    <h3 id="reactivate-modal-title" class="text-lg font-bold text-slate-900">Assign Plan & Activate Staff</h3>
                    <p class="mt-1 text-sm text-slate-500">Select an active subscription plan with an available seat for this staff member.</p>
                </div>
                <button
                    type="button"
                    class="p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    data-close-reactivate-staff-modal
                    aria-label="Close activate staff modal"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
            </div>

            <form
                id="reactivate-staff-form"
                method="POST"
                action=""
                data-action-template="{{ url('/teams/staff/__STAFF__/reactivate-subscription') }}"
                class="space-y-5 px-5 py-5 sm:px-6"
            >
                @csrf
                @method('PATCH')

                <div class="border border-slate-200 bg-slate-50 px-4 py-3">
                    <p class="text-sm text-slate-600">Staff member</p>
                    <p id="reactivate-staff-name" class="mt-1 text-base font-semibold text-slate-900">{{ old('reactivate_staff_name', 'Select staff') }}</p>
                    <p id="reactivate-current-plan" class="mt-1 text-xs text-slate-500">{{ old('reactivate_current_plan', '') }}</p>
                </div>

                @if ($errors->has('reactivate_company_subscription_id') || (filled(old('reactivate_staff_id')) && $errors->has('company_subscription_id')))
                    <div class="border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {{ $errors->first('reactivate_company_subscription_id') ?: $errors->first('company_subscription_id') }}
                    </div>
                @endif

                <div>
                    <x-input-label for="reactivate_company_subscription_id" :value="__('Active Plan')" />
                    <select id="reactivate_company_subscription_id" name="company_subscription_id" class="mt-1 block w-full border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">Select active plan</option>
                        @forelse($availableSubscriptionOptions as $planOption)
                            <option value="{{ $planOption['company_subscription_id'] }}" @selected((string) old('company_subscription_id') === (string) $planOption['company_subscription_id'])>
                                {{ $planOption['label'] }}
                            </option>
                        @empty
                            <option value="" disabled>No active plan seats available</option>
                        @endforelse
                    </select>
                    <p class="mt-2 text-xs text-slate-500">
                        @if (collect($availableSubscriptionOptions)->isEmpty())
                            No active subscription has free members right now. Staff cannot be activated until a seat is available.
                        @else
                            Select any active subscription plan that still has available members.
                        @endif
                    </p>
                </div>

                <input type="hidden" id="reactivate_staff_id_input" name="reactivate_staff_id" value="{{ old('reactivate_staff_id') }}">
                <input type="hidden" id="reactivate_staff_name_input" name="reactivate_staff_name" value="{{ old('reactivate_staff_name') }}">
                <input type="hidden" id="reactivate_current_plan_input" name="reactivate_current_plan" value="{{ old('reactivate_current_plan') }}">

                <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-5">
                    <button type="button" class="text-sm text-slate-600 hover:text-slate-900" data-close-reactivate-staff-modal>Cancel</button>
                    <x-primary-button
                        class="px-6 py-2"
                        :disabled="collect($availableSubscriptionOptions)->isEmpty()"
                    >
                        Activate Staff
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>

           @if(auth()->user()->isCompanyAdmin() && auth()->user()->hasPermission('teams.staff.attendance.bulk.manage'))
                <div x-show="ready && activeTab === 'bulk-attendance'" x-cloak>
                    <div x-data="{
                        selectedAttendanceStatus: 'Present',
                        selectedMonthKey: '{{ request('month', now()->format('Y-m')) }}',
                        todayMonthKey: '{{ now()->format('Y-m') }}',
                        bulkExportBase: '{{ route('attendance.bulk.export') }}',
                        isExporting: false,
                        async exportBulk() {
                            if (this.isExporting) return;

                            try {
                                this.isExporting = true;
                                const response = await fetch(`${this.bulkExportBase}?month=${encodeURIComponent(this.selectedMonthKey)}`, {
                                    credentials: 'same-origin',
                                });

                                if (!response.ok) {
                                    throw new Error('Export failed');
                                }

                                const blob = await response.blob();
                                const url = window.URL.createObjectURL(blob);
                                const anchor = document.createElement('a');
                                anchor.href = url;
                                anchor.download = `bulk-attendance-${this.selectedMonthKey}.csv`;
                                document.body.appendChild(anchor);
                                anchor.click();
                                anchor.remove();
                                window.URL.revokeObjectURL(url);
                            } catch (error) {
                                if (window.appAlert) {
                                    window.appAlert('Unable to export attendance right now.', 'Export', 'error');
                                } else {
                                    alert('Unable to export attendance right now.');
                                }
                            } finally {
                                this.isExporting = false;
                            }
                        }
                    }"
                        class="space-y-4">

                        {{-- Filter Form --}}
                        {{-- <form method="GET" action="{{ route('teams.staff.index') }}" class="flex flex-wrap items-end gap-4 bg-white p-4  border border-gray-200 shadow-sm">
                        <input type="hidden" name="tab" value="bulk-attendance">
                        <div class="w-48">
                            <x-input-label value="Select Month" class="text-xs font-bold uppercase text-gray-500" />
                            <x-text-input type="month" name="month" x-model="selectedMonthKey" class="mt-1 w-full text-sm" />
                        </div>
                        <div class="w-56">
                            <x-input-label value="Attendance Type" class="text-xs font-bold uppercase text-gray-500" />
                            <select x-model="selectedAttendanceStatus" class="mt-1 w-full border-gray-300  shadow-sm text-sm">
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Half Day">Half Day</option>
                                <option value="Leave">Leave</option>
                            </select>
                        </div>
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-slate-800 text-white  text-xs font-semibold uppercase hover:bg-slate-900 transition">
                            Load Sheet
                        </button>
                    </form> --}}

                        <form method="GET" action="{{ route('teams.staff.index') }}"
                            class="flex w-full flex-col gap-3  border border-gray-200 bg-white p-3 shadow-sm sm:flex-row sm:items-end sm:gap-4 sm:p-4">

                            <input type="hidden" name="tab" value="bulk-attendance">

                            <div class="w-full sm:min-w-[180px]">
                                <x-input-label value="Select Month" class="text-xs font-bold uppercase text-gray-500" />
                                <x-text-input type="month" name="month" x-model="selectedMonthKey"
                                    class="mt-1 w-full text-sm" />
                            </div>

                            <button type="submit"
                                class="inline-flex w-full shrink-0 items-center justify-center  bg-slate-800 px-4 py-2 text-xs font-semibold uppercase text-white transition hover:bg-slate-900 sm:w-auto">
                                Load Sheet
                            </button>

                            <div class="flex w-full flex-wrap gap-3 pt-1 text-[11px] font-semibold sm:ml-auto sm:w-auto sm:gap-4 sm:p-3">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 bg-emerald-100 border border-emerald-200"></span>
                                    <span class="text-gray-700">Present</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 bg-amber-100 border border-amber-200"></span>
                                    <span class="text-gray-700">Half Day</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 bg-sky-100 border border-sky-200"></span>
                                    <span class="text-gray-700">Leave</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 bg-rose-100 border border-rose-200"></span>
                                    <span class="text-gray-700">Absent</span>
                                </div>
                            </div>
                        </form>


                        {{-- Main Attendance Form --}}
                        <form method="POST" action="{{ route('attendance.bulk') }}">
                            @csrf
                            <input type="hidden" name="tab" value="bulk-attendance">

                            @php
                                $selectedMonth = request('month', now()->format('Y-m'));
                                try {
                                    $selectedMonthDate = \Carbon\Carbon::createFromFormat(
                                        'Y-m',
                                        $selectedMonth,
                                    )->startOfMonth();
                                } catch (\Throwable $e) {
                                    $selectedMonthDate = now()->startOfMonth();
                                }
                                $daysInMonth = $selectedMonthDate->daysInMonth;
                                $currentMonth = $selectedMonthDate->format('M');
                                $workingDays = collect(
                                    $workingDays ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
                                )->all();
                                $attendanceDaysCount = (int) ($attendanceDaysCount ?? 0);
                            @endphp

                            <div class="bg-white  border border-gray-200 shadow-sm overflow-hidden"
                                x-data="bulkTable()" @recompute-header.window="setHeader($event.detail.date)">

                                <div class="overflow-x-auto max-h-[600px] overflow-y-auto">

                                    <table class="w-full border-collapse">
                                        <thead>
                                            <tr class="bg-emerald-700 text-white">
                                                <th
                                                    class="sticky left-0 z-40 bg-emerald-700 px-2 py-3 text-left text-[10px] font-bold uppercase border-r border-emerald-500 min-w-[64px] sm:px-4 sm:text-[11px] sm:min-w-[80px]">
                                                    Emp ID</th>
                                                <th
                                                    class="sticky left-[64px] z-40 bg-emerald-700 px-2 py-3 text-left text-[10px] font-bold uppercase border-r border-emerald-500 min-w-[140px] sm:left-[80px] sm:px-4 sm:text-[11px] sm:min-w-[180px]">
                                                    Emp Name</th>
                                                <th
                                                    class="hidden md:table-cell px-4 py-3 text-left text-[11px] font-bold uppercase border-r border-emerald-500 min-w-[120px]">
                                                    Supervisor</th>

                                                @for ($i = 1; $i <= $daysInMonth; $i++)
                                                    @php
                                                        $cellDate = $selectedMonthDate->copy()->day($i)->toDateString();
                                                        $isToday = $cellDate === now()->toDateString();
                                                        $dayKey = strtolower(
                                                            $selectedMonthDate->copy()->day($i)->format('D'),
                                                        );
                                                        $isHoliday = isset($holidayDates[$cellDate]);
                                                        $isWorkingDay = in_array($dayKey, $workingDays, true);
                                                    @endphp
                                                    <th
                                                        class="px-0.5 py-2 text-center text-[9px] font-bold border-r border-emerald-500 min-w-[36px] sm:px-1 sm:py-3 sm:text-[10px] sm:min-w-[45px] {{ $isHoliday ? 'bg-rose-500 text-white' : ($isWorkingDay ? ($isToday ? 'bg-yellow-400 text-black' : 'bg-sky-500 text-white') : 'bg-slate-500 text-white') }}">
                                                        {{ $i }}-{{ $currentMonth }}
                                                        @if ($isHoliday)
                                                            <div class="mt-1 text-[9px] font-semibold">Holiday</div>
                                                        @elseif(!$isWorkingDay)
                                                            <div class="mt-1 text-[9px] font-semibold">Off</div>
                                                        @endif
                                                        @if ($isToday)
                                                            <div class="mt-1 flex justify-center">
                                                                <input type="checkbox" title="Toggle today for all"
                                                                    class="w-3 h-3 border-gray-300 text-emerald-600"
                                                                    data-date="{{ $cellDate }}"
                                                                    data-header-date="{{ $cellDate }}"
                                                                    :checked="allChecked('{{ $cellDate }}')"
                                                                    @change="$dispatch('column-set-status', { date: '{{ $cellDate }}', status: $event.target.checked ? 'Present' : 'Absent' })">
                                                            </div>
                                                        @endif
                                                    </th>
                                                @endfor

                                                <th
                                                    class="bg-orange-600 px-2 py-3 text-center text-[10px] font-bold uppercase border-l border-orange-500 min-w-[56px] sm:px-4 sm:text-[11px] sm:min-w-[70px]">
                                                    Present</th>
                                                <th
                                                    class="bg-orange-600 px-2 py-3 text-center text-[10px] font-bold uppercase border-l border-orange-500 min-w-[56px] sm:px-4 sm:text-[11px] sm:min-w-[70px]">
                                                    Total</th>
                                                <th
                                                    class="bg-orange-600 px-2 py-3 text-center text-[10px] font-bold uppercase border-l border-orange-500 min-w-[88px] sm:px-4 sm:text-[11px] sm:min-w-[120px]">
                                                    Attnd %</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @foreach ($staffs as $staff)
                                                @php
                                                    $staffAttendanceByDate = $attendanceMap[$staff->id] ?? [];
                                                @endphp
                                                <tr class="hover:bg-blue-50 transition-colors group"
                                                    x-data="{
                                                        rowData: @js($staffAttendanceByDate),
                                                        todayDate: '{{ now()->toDateString() }}',
                                                        activeDate: '',
                                                        openStatusModal: false,
                                                        status: 'Absent',
                                                        leaveReason: '',
                                                        overtimeHours: 0,
                                                        correctionReason: '',

                                                        getDay(dateValue) {
                                                            if (!this.rowData[dateValue]) {
                                                                this.rowData[dateValue] = { status: 'Absent', leave_reason: '', overtime_hours: 0, correction_reason: '', is_corrected: false, touched: 0 };
                                                            }
                                                            return this.rowData[dateValue];
                                                        },
                                                        getStatus(dateValue) { return this.getDay(dateValue).status || 'Absent'; },
                                                        isMarked(dateValue) { return this.getStatus(dateValue) !== 'Absent'; },
                                                        isToday(dateValue) { return dateValue === this.todayDate; },
                                                        syncHeader(dateValue) {
                                                            const headerCheckbox = document.querySelector(`input[data-header-date='${dateValue}']`);
                                                            const rowCheckboxes = Array.from(document.querySelectorAll(`input[type='checkbox'][data-date='${dateValue}']:not([data-header-date])`));
                                                            if (headerCheckbox) {
                                                                headerCheckbox.checked = rowCheckboxes.length > 0 && rowCheckboxes.every((box) => box.checked);
                                                            }
                                                        },
                                                        presentEquivalent() {
                                                            let total = 0;
                                                            Object.values(this.rowData).forEach((d) => {
                                                                if (d.status === 'Present') total += 1;
                                                                if (d.status === 'Half Day') total += 0.5;
                                                            });
                                                            return total;
                                                        },
                                                        attendancePercent(days) {
                                                            return days > 0 ? Math.round((this.presentEquivalent() / days) * 100) : 0;
                                                        },
                                                        isPastDate(dateValue) { return dateValue < this.todayDate; },
                                                        isFutureDate(dateValue) { return dateValue > this.todayDate; },
                                                        displayDate(dateValue) {
                                                            if (!dateValue) return '';
                                                            const [year, month, day] = dateValue.split('-');
                                                            return `${day}-${month}-${year}`;
                                                        },
                                                        applyBulkStatus(dateValue, maybeStatus = null, opts = {}) {
                                                            if (this.isFutureDate(dateValue)) return;
                                                            if (typeof maybeStatus === 'string') {
                                                                opts = { ...opts, forceStatus: maybeStatus };
                                                            } else if (typeof maybeStatus === 'object' && maybeStatus !== null) {
                                                                opts = maybeStatus;
                                                            }

                                                            const current = this.getStatus(dateValue);
                                                            const target = opts.forceStatus ?? 'Present';
                                                            const nextStatus = opts.force ? target : (current === target ? 'Absent' : target);
                                                            if (this.isPastDate(dateValue) && !opts.force) {
                                                                this.openModal(dateValue, nextStatus);
                                                                return;
                                                            }
                                                            const correctionNote = this.isPastDate(dateValue) ? (opts.correctionReason || 'Bulk past attendance update') : '';
                                                            this.commitDayUpdate(dateValue, nextStatus, '', 0, correctionNote);
                                                            this.$nextTick(() => this.$dispatch('recompute-header', { date: dateValue }));
                                                        },
                                                        commitDayUpdate(dateValue, nextStatus, nextLeaveReason = '', nextOvertime = 0, nextCorrectionReason = '') {
                                                            const day = this.getDay(dateValue);
                                                            const isPast = this.isPastDate(dateValue);
                                                            if (nextStatus === 'Leave' && !nextLeaveReason.trim()) { alert('Please enter leave reason.'); return false; }
                                                            if (isPast && !nextCorrectionReason.trim()) { alert('Please enter correction reason for past date update.'); return false; }

                                                            day.status = nextStatus;
                                                            day.leave_reason = nextStatus === 'Leave' ? nextLeaveReason : '';
                                                            day.overtime_hours = (nextStatus === 'Present' || nextStatus === 'Half Day') ? Number(nextOvertime) : 0;
                                                            day.correction_reason = isPast ? nextCorrectionReason : '';
                                                            day.is_corrected = isPast;
                                                            day.touched = 1;
                                                            this.rowData = { ...this.rowData };
                                                            return true;
                                                        },
                                                        openModal(dateValue, forcedStatus = null) {
                                                            if (this.isFutureDate(dateValue)) return;
                                                            this.activeDate = dateValue;
                                                            const day = this.getDay(dateValue);
                                                            this.status = forcedStatus || day.status || 'Absent';
                                                            this.leaveReason = day.leave_reason || '';
                                                            this.overtimeHours = day.overtime_hours || 0;
                                                            this.correctionReason = day.correction_reason || (this.isPastDate(dateValue) ? 'Manual past attendance update' : '');
                                                            this.openStatusModal = true;
                                                        },
                                                        saveModal() {
                                                            if (this.commitDayUpdate(this.activeDate, this.status, this.leaveReason, this.overtimeHours, this.correctionReason)) {
                                                                this.openStatusModal = false;
                                                                this.$nextTick(() => this.$dispatch('recompute-header', { date: this.activeDate }));
                                                            }
                                                        }
                                                    }"
                                                    @column-set-status.window="if($event.detail?.date){ applyBulkStatus($event.detail.date, { forceStatus: $event.detail.status, force: true, correctionReason: 'Column bulk set' }); }">
                                                    <td
                                                        class="sticky left-0 z-10 bg-white group-hover:bg-blue-50 px-2 py-3 text-[11px] font-medium text-gray-600 border-r border-gray-100 sm:px-4 sm:text-xs">
                                                        {{ $staff->employee_id }}
                                                    </td>
                                                    <td
                                                        class="sticky left-[64px] z-10 bg-white group-hover:bg-blue-50 px-2 py-3 text-[11px] font-bold text-gray-900 border-r border-gray-100 sm:left-[80px] sm:px-4 sm:text-xs">
                                                        {{ $staff->name }}

                                                        {{-- Modal Teleport --}}
                                                        <template x-teleport="body">
                                                            <div x-show="openStatusModal" x-cloak
                                                                class="fixed inset-0 z-[200]">
                                                                <div class="absolute inset-0 bg-black/45 backdrop-blur-[1px]"
                                                                    @click="openStatusModal = false"></div>
                                                                <div
                                                                    class="absolute left-1/2 top-1/2 w-[360px] -translate-x-1/2 -translate-y-1/2 bg-white p-5 shadow-2xl">
                                                                    <h4 class="text-sm font-bold text-gray-900 mb-4">
                                                                        Mark Past Attendance ({{ $staff->name }})</h4>
                                                                    <p class="mb-3 text-xs text-gray-500" x-text="displayDate(activeDate)"></p>
                                                                    <select x-model="status"
                                                                        class="w-full border-gray-300  text-sm">
                                                                        <option value="Present">Present</option>
                                                                        <option value="Half Day">Half Day</option>
                                                                        <option value="Absent">Absent</option>
                                                                        <option value="Leave">Leave</option>
                                                                        <option value="Holiday">Holiday</option>
                                                                    </select>
                                                                    <div class="mt-3"
                                                                        x-show="status === 'Present' || status === 'Half Day'">
                                                                        <x-input-label value="Overtime Hours" />
                                                                        <x-text-input type="number" step="0.25"
                                                                            x-model="overtimeHours"
                                                                            class="w-full mt-1" />
                                                                    </div>
                                                                    <div class="mt-3" x-show="status === 'Leave'">
                                                                        <x-input-label value="Leave Reason" />
                                                                        <x-text-input type="text"
                                                                            x-model="leaveReason"
                                                                            class="w-full mt-1" />
                                                                    </div>
                                                                    <div class="mt-3"
                                                                        x-show="isPastDate(activeDate)">
                                                                        <x-input-label value="Correction Reason" />
                                                                        <x-text-input type="text"
                                                                            x-model="correctionReason"
                                                                            class="w-full mt-1" />
                                                                    </div>
                                                                    <div class="mt-5 flex justify-end gap-2">
                                                                        <button type="button"
                                                                            @click="openStatusModal = false"
                                                                            class="px-3.5 py-2 text-xs bg-gray-100 ">Cancel</button>
                                                                        <button type="button"
                                                                            @click="saveModal()"
                                                                            class="px-3.5 py-2 text-xs bg-indigo-600 text-white ">Save</button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </template>

                                                        {{-- Hidden Inputs for Form Submission --}}
                                                        <template x-for="(entry, dateKey) in rowData"
                                                            :key="dateKey">
                                                            <span>
                                                                <input type="hidden"
                                                                    :name="`attendance[{{ $staff->id }}][${dateKey}][status]`"
                                                                    :value="entry.status">
                                                                <input type="hidden"
                                                                    :name="`attendance[{{ $staff->id }}][${dateKey}][leave_reason]`"
                                                                    :value="entry.leave_reason">
                                                                <input type="hidden"
                                                                    :name="`attendance[{{ $staff->id }}][${dateKey}][overtime_hours]`"
                                                                    :value="entry.overtime_hours">
                                                                <input type="hidden"
                                                                    :name="`attendance[{{ $staff->id }}][${dateKey}][correction_reason]`"
                                                                    :value="entry.correction_reason">
                                                                <input type="hidden"
                                                                    :name="`attendance[{{ $staff->id }}][${dateKey}][touched]`"
                                                                    :value="entry.touched">
                                                            </span>
                                                        </template>
                                                    </td>
                                                    <td
                                                        class="hidden md:table-cell px-4 py-3 text-xs text-gray-500 border-r border-gray-100">
                                                        Admin</td>

                                                    @for ($i = 1; $i <= $daysInMonth; $i++)
                                                        @php
                                                            $cellDate = $selectedMonthDate
                                                                ->copy()
                                                                ->day($i)
                                                                ->toDateString();
                                                            $isEditableCell = $cellDate <= now()->toDateString();
                                                            $dayKey = strtolower(
                                                                $selectedMonthDate->copy()->day($i)->format('D'),
                                                            );
                                                            $isHoliday = isset($holidayDates[$cellDate]);
                                                            $isWorkingDay = in_array($dayKey, $workingDays, true);
                                                        @endphp
                                                        <td class="p-1 border-r border-gray-100 {{ $isHoliday ? 'bg-indigo-100' : ($isWorkingDay ? ($isEditableCell ? 'cursor-pointer' : 'bg-gray-50/40') : 'bg-slate-100') }}"
                                                            @if ($isEditableCell) :class="{
                                                                'bg-emerald-100': getStatus('{{ $cellDate }}') === 'Present',
                                                                'bg-amber-100': getStatus('{{ $cellDate }}') === 'Half Day',
                                                                'bg-sky-100': getStatus('{{ $cellDate }}') === 'Leave',
                                                                'bg-rose-100': getStatus('{{ $cellDate }}') === 'Absent',
                                                                'bg-violet-100': getStatus('{{ $cellDate }}') === 'Holiday'
                                                            }" @endif>
                                                            <div class="flex items-center justify-center">
                                                                <input type="checkbox"
                                                                    :checked="isMarked('{{ $cellDate }}')"
                                                                    class="w-4 h-4"
                                                                    data-date="{{ $cellDate }}"
                                                                    @change="if (isPastDate('{{ $cellDate }}')) { $event.target.checked = isMarked('{{ $cellDate }}'); openModal('{{ $cellDate }}'); } else { applyBulkStatus('{{ $cellDate }}'); }"
                                                                    {{ !$isEditableCell ? 'disabled' : '' }}>
                                                            </div>
                                                        </td>
                                                    @endfor

                                                    <td
                                                        class="bg-orange-50 px-4 py-3 text-center text-xs font-bold text-gray-700 italic border-l border-orange-100">
                                                        <span x-text="presentEquivalent()"></span>
                                                    </td>
                                                    <td
                                                        class="bg-orange-50 px-4 py-3 text-center text-xs font-bold text-gray-700 italic border-l border-orange-100">
                                                        {{ $attendanceDaysCount }}
                                                    </td>
                                                    <td
                                                        class="bg-orange-50 px-4 py-3 text-center border-l border-orange-100">
                                                        <div class="flex items-center gap-2">
                                                            <div class="w-full bg-gray-200 h-1.5">
                                                                <div class="bg-emerald-500 h-1.5"
                                                                    :style="`width: ${attendancePercent({{ $attendanceDaysCount > 0 ? $attendanceDaysCount : $daysInMonth }})}%`">
                                                                </div>
                                                            </div>
                                                            <span class="text-[10px] font-bold"
                                                                x-text="`${attendancePercent({{ $attendanceDaysCount > 0 ? $attendanceDaysCount : $daysInMonth }})}%`"></span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                {{-- Footer --}}
                                <div class="flex flex-col gap-3 border-t border-gray-200 bg-gray-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-center text-xs italic text-gray-500 sm:text-left">Tip: Use horizontal scroll. Name/ID are
                                        frozen.</p>
                                    <div class="flex flex-col gap-2 sm:flex-row">
                                        <button type="button"
                                            @click="exportBulk()"
                                            :disabled="isExporting"
                                            class="w-full  border bg-white px-4 py-2 text-center text-xs font-semibold transition disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto">
                                            <span x-show="!isExporting">Export</span>
                                            <span x-show="isExporting" x-cloak>Exporting...</span>
                                        </button>
                                        <x-primary-button
                                            class="w-full justify-center bg-emerald-600 sm:w-auto"
                                            x-bind:disabled="selectedMonthKey > todayMonthKey">Save Attendance
                                            Sheet</x-primary-button>
                                    </div>
                                </div>
                            </div>
                        </form>
                </div>

                @if(auth()->user()->hasPermission('teams.staff.salary.manage'))
                    <div x-show="ready && activeTab === 'advance'" x-cloak class="bg-white  border border-gray-200 shadow-sm">
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wide">Staff Advances</h3>
                            <a href="{{ route('teams.payroll.advance.index') }}" class="inline-flex items-center px-3 py-2 bg-indigo-600 text-white text-xs font-semibold  hover:bg-indigo-700">
                                + New Advance
                            </a>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Staff Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Emp ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Notes</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($advances as $advance)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $advance->staff?->name ?? '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $advance->staff?->employee_id ?? '-' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ \Carbon\Carbon::parse($advance->advance_date)->format('d M Y') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">₹{{ number_format($advance->amount, 2) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $advance->notes ?: '—' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-6 text-center text-sm text-gray-500">No advances recorded yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="ready && activeTab === 'salary'" x-cloak x-data="salaryPanel(@js($salarySummaries), '{{ route('teams.staff.salary.status') }}', '{{ csrf_token() }}')" @open-salary.window="openSalary($event.detail)" @open-advance.window="openAdvance($event.detail)" class="bg-white  border border-gray-200 shadow-sm">
                        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 border-b border-gray-100">
                            <h3 class="text-sm font-bold text-gray-800 uppercase tracking-wide">Salary</h3>

                            @php
                                $currentSalaryKey = now()->format('Y-m');
                                $previousSalaryKey = now()->subMonth()->format('Y-m');
                            @endphp
                            <form method="GET" action="{{ route('teams.staff.index') }}" class="flex items-center gap-3">
                                <input type="hidden" name="tab" value="salary">
                                <label class="text-xs font-semibold text-gray-600">
                                    Salary Month
                                    <input type="month" name="salary_month" x-model="salaryMonth" max="{{ now()->format('Y-m') }}" class="ml-2  border-gray-300 text-sm">
                                </label>
                                <button type="submit" class="inline-flex items-center px-3 py-2 bg-slate-800 text-white text-xs font-semibold  hover:bg-slate-900">Load</button>
                            </form>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Staff</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Month</th>
                                        <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <template x-if="rows.length === 0">
                                        <tr>
                                            <td colspan="4" class="px-6 py-6 text-center text-sm text-gray-500">No salary data for selected month.</td>
                                        </tr>
                                    </template>

                                    <template x-for="row in rows" :key="`${row.staff_id}-${row.month_key}`">
                                        <tr class="hover:bg-slate-50">
                                            <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                                <div x-text="row.name"></div>
                                                <div class="text-[11px] text-gray-500">ID: <span x-text="row.employee_id"></span></div>
                                            </td>
                                            <td class="px-6 py-4 text-sm text-gray-900" x-text="row.month_label"></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span :class="row.status === 'Done' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-800'" class="inline-flex items-center px-3 py-1 text-xs font-bold">
                                                    <span class="h-2 w-2 mr-2" :class="row.status === 'Done' ? 'bg-emerald-500' : 'bg-amber-500'"></span>
                                                    <span x-text="row.status"></span>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                                <button type="button" class="inline-flex items-center justify-center w-10 h-9 bg-indigo-600 text-white  hover:bg-indigo-700" @click="openSalary(row)">
                                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        {{-- Salary detail modal --}}
                        <div x-show="salaryModal" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center">
                            <div class="absolute inset-0 bg-black/45" @click="closeSalary()"></div>
                            <div class="relative w-[380px] max-w-full bg-white p-6 shadow-2xl border border-gray-200">
                                <h4 class="text-sm font-bold text-gray-900 mb-4">Salary Detail</h4>
                                <template x-if="salaryModal">
                                    <div class="space-y-3 text-sm text-gray-800">
                                        <table class="w-full text-sm text-left border border-gray-200  overflow-hidden">
                                            <tbody>
                                                <tr class="border-b border-gray-200">
                                                    <th class="bg-gray-50 px-3 py-2 font-semibold text-gray-700 w-40">User Name</th>
                                                    <td class="px-3 py-2" x-text="salaryModal.name"></td>
                                                </tr>
                                                <tr class="border-b border-gray-200">
                                                    <th class="bg-gray-50 px-3 py-2 font-semibold text-gray-700">Total Payable</th>
                                                    <td class="px-3 py-2">Rs. <span x-text="Number(salaryModal.payable).toFixed(2)"></span></td>
                                                </tr>
                                                <tr class="border-b border-gray-200">
                                                    <th class="bg-gray-50 px-3 py-2 font-semibold text-gray-700">Advance</th>
                                                    <td class="px-3 py-2">
                                                        <button type="button" class="text-indigo-600 hover:underline" @click="openAdvance(salaryModal)">
                                                            Rs. <span x-text="Number(salaryModal.advance_total).toFixed(2)"></span>
                                                        </button>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <th class="bg-gray-50 px-3 py-2 font-semibold text-gray-700">Net Payable</th>
                                                    <td class="px-3 py-2 font-semibold text-indigo-700">Rs. <span x-text="Number(salaryModal.net_payable).toFixed(2)"></span></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <label class="flex items-center gap-2 text-xs mt-1">
                                            <input type="checkbox" class="border-gray-300 text-indigo-600" :checked="salaryModal.status === 'Done'" @change="toggleStatus($event.target.checked)">
                                            <span>Mark as done</span>
                                        </label>
                                    </div>
                                </template>
                                <div class="mt-5 flex justify-end gap-2">
                                    <button type="button" class="px-3 py-2 text-sm bg-gray-100 " @click="closeSalary()">Close</button>
                                </div>
                            </div>
                        </div>

                        {{-- Advance detail modal --}}
                        <div x-show="advanceModal" x-cloak class="fixed inset-0 z-[300] flex items-center justify-center">
                            <div class="absolute inset-0 bg-black/45" @click="closeAdvance()"></div>
                            <div class="relative w-[400px] max-w-full bg-white p-5 shadow-2xl border border-gray-200">
                                <h4 class="text-sm font-bold text-gray-900 mb-4">Advance Details</h4>
                                <template x-if="advanceModal">
                                    <div class="space-y-3 text-sm text-gray-800">
                                        <div class="flex justify-between"><span class="font-semibold">Staff</span><span x-text="advanceModal.name"></span></div>
                                        <div class="flex justify-between"><span class="font-semibold">Month</span><span x-text="advanceModal.month_label"></span></div>

                                        <div class="border border-gray-200  overflow-hidden">
                                            <table class="w-full text-sm">
                                                <thead class="bg-gray-50 text-gray-700 font-semibold">
                                                    <tr>
                                                        <th class="px-3 py-2 text-left">Date</th>
                                                        <th class="px-3 py-2 text-right">Amount</th>
                                                        <th class="px-3 py-2 text-right">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <template x-if="(advanceModal.advances || []).length === 0">
                                                        <tr>
                                                            <td colspan="3" class="px-3 py-3 text-center text-xs text-gray-500">No advances in this month.</td>
                                                        </tr>
                                                    </template>
                                                    <template x-for="(adv, idx) in advanceModal.advances" :key="adv.id || idx">
                                                        <tr class="border-t border-gray-100">
                                                            <td class="px-3 py-2" x-text="adv.date"></td>
                                                            <td class="px-3 py-2 text-right">Rs. <span x-text="Number(adv.amount).toFixed(2)"></span></td>
                                                            <td class="px-3 py-2 text-right">
                                                                <div class="inline-flex items-center gap-2">
                                                                    <a :href="adv.edit_url" class=" border border-indigo-200 px-2 py-1 text-[11px] font-semibold text-indigo-700 hover:bg-indigo-50">
                                                                        Edit
                                                                    </a>
                                                                    <form :action="adv.delete_url" method="POST" class="js-confirm-delete" data-confirm-title="Delete advance?" data-confirm-text="This will permanently remove this advance entry.">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                                                        <button type="submit" class=" border border-rose-200 px-2 py-1 text-[11px] font-semibold text-rose-700 hover:bg-rose-50">
                                                                            Delete
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    </template>
                                                </tbody>
                                                <tfoot class="bg-gray-50 font-semibold text-gray-800 border-t border-gray-200">
                                                    <tr>
                                                        <td class="px-3 py-2">Total</td>
                                                        <td class="px-3 py-2 text-right">Rs. <span x-text="advanceModal.advance_total.toFixed(2)"></span></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </template>
                                <div class="mt-4 flex justify-end">
                                    <button type="button" class="px-3 py-2 text-sm bg-gray-100 " @click="closeAdvance()">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            @endif
        </div>
    </div>
</x-app-layout>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const flash = document.querySelector('[data-flash-success]');
        if (flash) {
            setTimeout(() => {
                flash.classList.add('transition-opacity', 'duration-500', 'opacity-0');
                flash.addEventListener('transitionend', () => flash.remove());
            }, 3200);
        }

        @if(session('success'))
            Swal.fire({
                title: 'Success!',
                text: "{{ session('success') }}",
                icon: 'success',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 2200,
                timerProgressBar: true,
            });
        @endif

        document.querySelectorAll('form.js-confirm-delete').forEach(form => {
            form.addEventListener('submit', function(e) {
                if (form.dataset.confirmedDelete === '1') return;
                e.preventDefault();
                const title = form.dataset.confirmTitle || 'Delete?';
                const text = form.dataset.confirmText || 'This action cannot be undone.';

                Swal.fire({
                    title,
                    text,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e11d48',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: 'Delete',
                    cancelButtonText: 'Cancel',
                }).then((result) => {
                    if (!result.isConfirmed) return;
                    form.dataset.confirmedDelete = '1';
                    form.submit();
                });
            });
        });
    });
</script>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('bulkTable', () => ({
            init() {
                // after rows initialise, sync header checkboxes with row state
                this.$nextTick(() => {
                    document.querySelectorAll("input[data-header-date]").forEach((el) => {
                        this.setHeader(el.dataset.headerDate);
                    });
                });
            },
            allChecked(date) {
                // ignore header toggle itself to avoid self-referencing loop
                const boxes = Array.from(document.querySelectorAll(`input[type='checkbox'][data-date='${date}']:not([data-header-date])`));
                if (boxes.length === 0) return false;
                return boxes.every(b => b.checked);
            },
            setHeader(date) {
                const header = document.querySelector(`input[data-header-date='${date}']`);
                if (header) header.checked = this.allChecked(date);
            },
        }));

        Alpine.data('salaryPanel', (rows, statusUrl, csrfToken) => ({
            rows,
            statusUrl,
            csrfToken,
            salaryModal: null,
            advanceModal: null,
            openSalary(row) { this.salaryModal = row; },
            closeSalary() { this.salaryModal = null; },
            openAdvance(row) {
                const advArray = Array.isArray(row.advances)
                    ? row.advances
                    : row.advances
                        ? Object.values(row.advances)
                        : [];
                advArray.sort((a, b) => (a.date || '').localeCompare(b.date || ''));
                this.advanceModal = {
                    ...row,
                    advances: advArray,
                };
            },
            closeAdvance() { this.advanceModal = null; },
            async toggleStatus(done) {
                if (!this.salaryModal) return;
                const status = done ? 'Done' : 'Pending';
                this.salaryModal.status = status;
                const idx = this.rows.findIndex(r => r.staff_id === this.salaryModal.staff_id && r.month_key === this.salaryModal.month_key);
                if (idx !== -1) this.rows[idx].status = status;

                try {
                    await fetch(this.statusUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            staff_id: this.salaryModal.staff_id,
                            month_key: this.salaryModal.month_key,
                            status,
                        }),
                    });
                } catch (e) {
                    console.error('Failed to save status', e);
                }
            }
        }));

        Alpine.data('staffTrackerPanel', (config) => ({
            dataUrl: config.dataUrl,
            googleMapsScriptUrl: config.googleMapsScriptUrl || '',
            staffOptions: Array.isArray(config.staffOptions) ? config.staffOptions : [],
            selectedDate: config.defaultDate || new Date().toISOString().slice(0, 10),
            selectedStaffId: 'all',
            focusedRouteId: null,
            loading: false,
            mapLoading: false,
            error: '',
            autoRefreshEnabled: true,
            pollIntervalMs: 30000,
            pollIntervalLabel: '30s',
            refreshTimer: null,
            visibilityHandler: null,
            resizeHandler: null,
            googleMapsScriptPromise: null,
            lastUpdatedLabel: 'Never',
            map: null,
            geocoder: null,
            infoWindow: null,
            routeBounds: null,
            mapArtifacts: [],
            defaultCenter: { lat: 20.5937, lng: 78.9629 },
            activePopupKey: null,
            mapTypeId: 'roadmap',
            addressCache: new Map(),
            routes: [],
            summary: {
                staff_count: 0,
                point_count: 0,
                distance_km: 0,
            },
            escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            },
            async init() {
                this.resizeHandler = () => {
                    if (this.map && window.google?.maps?.event) {
                        window.google.maps.event.trigger(this.map, 'resize');
                    }
                };

                window.addEventListener('resize', this.resizeHandler);
                await this.ensureMap();
                this.syncAutoRefresh();
                this.reload();
            },
            destroy() {
                this.stopAutoRefresh();
                if (this.resizeHandler) {
                    window.removeEventListener('resize', this.resizeHandler);
                    this.resizeHandler = null;
                }
                this.clearMapArtifacts();
            },
            get selectedRoute() {
                return this.routes.find((route) => this.sameStaffId(route.staff_id, this.focusedRouteId)) || this.routes[0] || null;
            },
            toggleAutoRefresh() {
                this.stopAutoRefresh();

                if (!this.autoRefreshEnabled) {
                    return;
                }

                this.visibilityHandler = () => {
                    if (document.visibilityState === 'visible') {
                        this.reload();
                    }
                };

                document.addEventListener('visibilitychange', this.visibilityHandler);

                this.refreshTimer = window.setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        this.reload();
                    }
                }, this.pollIntervalMs);
            },
            syncAutoRefresh() {
                if (this.autoRefreshEnabled) {
                    this.toggleAutoRefresh();
                }
            },
            stopAutoRefresh() {
                if (this.refreshTimer) {
                    window.clearInterval(this.refreshTimer);
                    this.refreshTimer = null;
                }

                if (this.visibilityHandler) {
                    document.removeEventListener('visibilitychange', this.visibilityHandler);
                    this.visibilityHandler = null;
                }
            },
            async reload() {
                if (!this.dataUrl) {
                    return;
                }

                if (this.loading) {
                    return;
                }

                this.loading = true;
                this.error = '';

                try {
                    await this.ensureMap();

                    const url = new URL(this.dataUrl, window.location.origin);
                    url.searchParams.set('date', this.selectedDate);
                    url.searchParams.set('staff_id', this.selectedStaffId || 'all');

                    const response = await fetch(url.toString(), {
                        headers: {
                            'Accept': 'application/json',
                        },
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        throw new Error(data.message || 'Unable to load staff routes.');
                    }

                    this.routes = Array.isArray(data.routes) ? data.routes : [];
                    this.summary = data.summary || this.summary;
                    this.focusedRouteId = this.resolveFocusedRouteId();
                    this.lastUpdatedLabel = this.formatUpdatedLabel(new Date());

                    await this.renderMap();
                } catch (error) {
                    this.routes = [];
                    this.summary = {
                        staff_count: 0,
                        point_count: 0,
                        distance_km: 0,
                    };
                    this.focusedRouteId = null;
                    this.error = error.message || 'Unable to load staff routes.';
                    await this.renderMap(true);
                } finally {
                    this.loading = false;
                }
            },
            formatUpdatedLabel(date) {
                return date.toLocaleString('en-IN', {
                    day: 'numeric',
                    month: 'numeric',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                });
            },
            resolveFocusedRouteId() {
                if (this.selectedStaffId !== 'all') {
                    const matched = this.routes.find((route) => this.sameStaffId(route.staff_id, this.selectedStaffId));
                    return matched ? matched.staff_id : (this.routes[0]?.staff_id ?? null);
                }

                if (this.focusedRouteId && this.routes.some((route) => this.sameStaffId(route.staff_id, this.focusedRouteId))) {
                    return this.focusedRouteId;
                }

                return this.routes[0]?.staff_id ?? null;
            },
            focusRoute(routeId) {
                this.focusedRouteId = routeId;
                this.renderMap();
            },
            sameStaffId(left, right) {
                return String(left ?? '') === String(right ?? '');
            },
            async ensureMap() {
                if (this.map || !this.$refs.mapCanvas) {
                    return;
                }

                this.mapLoading = true;

                try {
                    await this.loadGoogleMapsScript();

                    if (!window.google?.maps) {
                        throw new Error('Google Maps failed to load. Please refresh the page.');
                    }

                    this.geocoder = new google.maps.Geocoder();
                    this.infoWindow = new google.maps.InfoWindow({
                        maxWidth: 340,
                    });

                    this.map = new google.maps.Map(this.$refs.mapCanvas, {
                        center: this.defaultCenter,
                        zoom: 5,
                        mapTypeId: google.maps.MapTypeId.ROADMAP,
                        mapTypeControl: true,
                        mapTypeControlOptions: {
                            mapTypeIds: ['roadmap', 'satellite', 'hybrid', 'terrain'],
                            position: google.maps.ControlPosition.TOP_RIGHT,
                        },
                        zoomControl: true,
                        fullscreenControl: true,
                        streetViewControl: true,
                        clickableIcons: false,
                        gestureHandling: 'greedy',
                        controlSize: 32,
                    });

                    this.map.addListener('click', () => this.closeInfoWindow());
                } catch (error) {
                    this.error = error.message || 'Unable to load the map.';
                } finally {
                    this.mapLoading = false;
                    window.setTimeout(() => {
                        if (this.map && window.google?.maps?.event) {
                            window.google.maps.event.trigger(this.map, 'resize');
                        }
                    }, 150);
                }
            },
            loadGoogleMapsScript() {
                if (window.google?.maps) {
                    return Promise.resolve();
                }

                if (this.googleMapsScriptPromise) {
                    return this.googleMapsScriptPromise;
                }

                if (!this.googleMapsScriptUrl) {
                    return Promise.reject(new Error('Google Maps API key is not configured.'));
                }

                this.googleMapsScriptPromise = new Promise((resolve, reject) => {
                    const existingScript = document.querySelector('script[data-google-maps-staff-tracking="true"]');

                    if (existingScript) {
                        existingScript.addEventListener('load', () => resolve(), { once: true });
                        existingScript.addEventListener('error', () => reject(new Error('Google Maps failed to load.')), { once: true });
                        return;
                    }

                    const script = document.createElement('script');
                    script.src = this.googleMapsScriptUrl;
                    script.async = true;
                    script.defer = true;
                    script.dataset.googleMapsStaffTracking = 'true';
                    script.onload = () => resolve();
                    script.onerror = () => reject(new Error('Google Maps failed to load.'));
                    document.head.appendChild(script);
                });

                return this.googleMapsScriptPromise;
            },
            clearMapArtifacts() {
                this.closeInfoWindow();

                this.mapArtifacts.forEach((artifact) => {
                    if (artifact?.instance && typeof artifact.instance.setMap === 'function') {
                        artifact.instance.setMap(null);
                    }
                });

                this.mapArtifacts = [];
                this.routeBounds = null;
            },
            closeInfoWindow() {
                if (this.infoWindow) {
                    this.infoWindow.close();
                }

                this.activePopupKey = null;
            },
            async renderMap(forceReset = false) {
                if (!this.map) {
                    if (!forceReset) {
                        await this.ensureMap();
                    }

                    if (!this.map) {
                        return;
                    }
                }

                this.clearMapArtifacts();

                if (typeof google === 'undefined' || !google.maps) {
                    return;
                }

                const activeRouteId = this.resolveFocusedRouteId();
                this.focusedRouteId = activeRouteId;
                const bounds = new google.maps.LatLngBounds();
                let hasVisiblePoint = false;
                let visiblePointCount = 0;

                if (this.routes.length === 0) {
                    this.map.setCenter(this.defaultCenter);
                    this.map.setZoom(5);
                    return;
                }

                this.routes.forEach((route) => {
                    const points = Array.isArray(route.points) ? route.points : [];
                    const path = points
                        .map((point) => ({
                            lat: Number(point.latitude),
                            lng: Number(point.longitude),
                        }))
                        .filter((pair) => Number.isFinite(pair.lat) && Number.isFinite(pair.lng));

                    if (path.length === 0) {
                        return;
                    }

                    hasVisiblePoint = true;
                    visiblePointCount += path.length;
                    path.forEach((position) => bounds.extend(position));

                    const isActive = this.sameStaffId(route.staff_id, activeRouteId);
                    const polyline = new google.maps.Polyline({
                        path,
                        strokeColor: route.color || '#2563eb',
                        strokeOpacity: isActive ? 0.92 : 0.55,
                        strokeWeight: isActive ? 6 : 4,
                        zIndex: isActive ? 20 : 10,
                        clickable: true,
                    });

                    polyline.setMap(this.map);
                    polyline.addListener('click', () => this.focusRoute(route.staff_id));
                    this.mapArtifacts.push({ type: 'polyline', instance: polyline });

                    const currentPoint = points[points.length - 1];
                    const marker = new google.maps.Marker({
                        position: {
                            lat: Number(currentPoint.latitude),
                            lng: Number(currentPoint.longitude),
                        },
                        map: this.map,
                        title: route.staff_name,
                        icon: this.buildMarkerIcon(route.color || '#2563eb', isActive),
                        zIndex: isActive ? 1000 : 100,
                    });

                    marker.addListener('click', () => {
                        this.openRoutePopup(route, currentPoint, marker);
                    });

                    this.mapArtifacts.push({ type: 'marker', instance: marker });
                    this.prefetchPointAddress(route, currentPoint);
                });

                this.routeBounds = hasVisiblePoint ? bounds : null;

                if (hasVisiblePoint) {
                    if (visiblePointCount === 1) {
                        this.map.setCenter(bounds.getCenter());
                        this.map.setZoom(16);
                    } else {
                        this.map.fitBounds(bounds, 56);
                    }
                } else {
                    this.map.setCenter(this.defaultCenter);
                    this.map.setZoom(5);
                }

                window.setTimeout(() => {
                    if (this.map && window.google?.maps?.event) {
                        window.google.maps.event.trigger(this.map, 'resize');
                    }
                }, 120);
            },
            buildMarkerIcon(color, active = false) {
                const size = active ? 42 : 36;
                const radius = size / 2;
                const innerRadius = active ? 10 : 8;
                const svg = `
                    <svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
                        <circle cx="${radius}" cy="${radius}" r="${radius - 3}" fill="${color}" stroke="#ffffff" stroke-width="4"></circle>
                        <circle cx="${radius}" cy="${radius}" r="${innerRadius}" fill="#ffffff" opacity="0.95"></circle>
                        <circle cx="${radius}" cy="${radius}" r="${innerRadius - 3}" fill="${color}" opacity="0.95"></circle>
                    </svg>
                `;

                return {
                    url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
                    scaledSize: new google.maps.Size(size, size),
                    anchor: new google.maps.Point(radius, radius),
                };
            },
            pointKey(route, point) {
                return [
                    route.staff_id,
                    point.recorded_at || point.sequence || 'point',
                    point.latitude,
                    point.longitude,
                ].join('|');
            },
            async prefetchPointAddress(route, point) {
                if (!point) {
                    return 'Unknown location';
                }

                const cacheKey = `${Number(point.latitude).toFixed(6)},${Number(point.longitude).toFixed(6)}`;

                if (point.resolvedAddress) {
                    this.addressCache.set(cacheKey, point.resolvedAddress);
                    return point.resolvedAddress;
                }

                if (this.addressCache.has(cacheKey)) {
                    point.resolvedAddress = this.addressCache.get(cacheKey);
                    return point.resolvedAddress;
                }

                if (!this.geocoder || typeof this.geocoder.geocode !== 'function') {
                    const fallback = point.location_name || 'Unknown location';
                    point.resolvedAddress = fallback;
                    this.addressCache.set(cacheKey, fallback);
                    return fallback;
                }

                const resolved = await new Promise((resolve) => {
                    this.geocoder.geocode(
                        {
                            location: {
                                lat: Number(point.latitude),
                                lng: Number(point.longitude),
                            },
                        },
                        (results, status) => {
                            if (status === 'OK' && results && results[0]) {
                                resolve(results[0].formatted_address || results[0].name || point.location_name || 'Unknown location');
                                return;
                            }

                            resolve(point.location_name || 'Unknown location');
                        }
                    );
                });

                point.resolvedAddress = resolved;
                this.addressCache.set(cacheKey, resolved);

                return resolved;
            },
            async openRoutePopup(route, point, marker) {
                if (!this.infoWindow) {
                    return;
                }

                const key = this.pointKey(route, point);
                this.activePopupKey = key;
                this.infoWindow.setContent(
                    this.buildPopupContent(route, point, point.resolvedAddress || point.location_name || 'Resolving address...')
                );
                this.infoWindow.open({
                    map: this.map,
                    anchor: marker,
                });

                const address = await this.prefetchPointAddress(route, point);

                if (this.activePopupKey === key && this.infoWindow) {
                    this.infoWindow.setContent(this.buildPopupContent(route, point, address));
                }
            },
            buildPopupContent(route, point, address) {
                const latitude = Number(point.latitude).toFixed(6);
                const longitude = Number(point.longitude).toFixed(6);
                const lastUpdated = this.formatPopupTime(point.recorded_at);
                const status = route.summary?.point_count ? 'Tracking active' : 'Tracking unavailable';

                return `
                    <div class="min-w-[220px] space-y-3 text-sm text-slate-700">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Staff</div>
                            <div class="mt-1 text-base font-bold text-slate-950">${this.escapeHtml(route.staff_name)}</div>
                            <div class="text-xs text-slate-500">${this.escapeHtml(route.employee_id || 'No employee ID')}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-3 py-3">
                            <div class="text-[11px] font-bold uppercase tracking-[0.2em] text-slate-400">Current place</div>
                            <div class="mt-1 font-semibold text-slate-900">${this.escapeHtml(address || point.location_name || 'Unknown location')}</div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <div class="font-bold uppercase tracking-[0.16em] text-slate-400">Latitude</div>
                                <div class="mt-1 font-semibold text-slate-900">${latitude}</div>
                            </div>
                            <div>
                                <div class="font-bold uppercase tracking-[0.16em] text-slate-400">Longitude</div>
                                <div class="mt-1 font-semibold text-slate-900">${longitude}</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs">
                            <div>
                                <div class="font-bold uppercase tracking-[0.16em] text-slate-400">Last updated</div>
                                <div class="mt-1 font-semibold text-slate-900">${this.escapeHtml(lastUpdated)}</div>
                            </div>
                            <div>
                                <div class="font-bold uppercase tracking-[0.16em] text-slate-400">Status</div>
                                <div class="mt-1 font-semibold text-emerald-700">${this.escapeHtml(status)}</div>
                            </div>
                        </div>
                    </div>
                `;
            },
            recenterMap() {
                if (!this.map) {
                    return;
                }

                if (this.routeBounds && typeof this.routeBounds.isEmpty === 'function' && !this.routeBounds.isEmpty()) {
                    this.map.fitBounds(this.routeBounds, 56);
                    return;
                }

                if (this.routes.length > 0) {
                    const route = this.selectedRoute || this.routes[0];
                    const points = Array.isArray(route?.points) ? route.points : [];

                    if (points.length > 0) {
                        const latestPoint = points[points.length - 1];
                        this.map.setCenter({
                            lat: Number(latestPoint.latitude),
                            lng: Number(latestPoint.longitude),
                        });
                        this.map.setZoom(16);
                        return;
                    }
                }

                this.map.setCenter(this.defaultCenter);
                this.map.setZoom(5);
            },
            formatPopupTime(value) {
                if (!value) {
                    return 'Unknown time';
                }

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString('en-IN', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            },
            formatTime(value) {
                if (!value) {
                    return '-';
                }

                const date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return value;
                }

                return date.toLocaleString('en-IN', {
                    day: '2-digit',
                    month: 'short',
                    hour: '2-digit',
                    minute: '2-digit',
                });
            },
        }));
    });
</script>

<script>
    (function () {
        const initStaffPageModals = function () {
            const bindModal = function (modalId, openButtonId, closeSelector, globalOpenName) {
                const modal = document.getElementById(modalId);
                const openButton = document.getElementById(openButtonId);

                if (!modal) {
                    return;
                }

                const closeButtons = modal.querySelectorAll(closeSelector);
                const openModal = function () {
                    modal.classList.remove('hidden');
                    modal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                };
                const closeModal = function () {
                    modal.classList.add('hidden');
                    modal.setAttribute('aria-hidden', 'true');

                    if (!document.querySelector('#staff-import-modal:not(.hidden), #add-staff-modal:not(.hidden), #reactivate-staff-modal:not(.hidden)')) {
                        document.body.classList.remove('overflow-hidden');
                    }
                };

                if (globalOpenName) {
                    window[globalOpenName] = openModal;
                }

                if (openButton && openButton.dataset.modalBound !== 'true') {
                    openButton.dataset.modalBound = 'true';
                    openButton.addEventListener('click', openModal);
                }

                closeButtons.forEach(function (button) {
                    if (button.dataset.modalBound === 'true') {
                        return;
                    }

                    button.dataset.modalBound = 'true';
                    button.addEventListener('click', closeModal);
                });

                if (modal.dataset.escapeBound !== 'true') {
                    modal.dataset.escapeBound = 'true';
                    document.addEventListener('keydown', function (event) {
                        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                            closeModal();
                        }
                    });
                }

                if (modal.dataset.modalOpen === 'true') {
                    openModal();
                }

                return {
                    openModal,
                    closeModal,
                };
            };

            const importModalApi = bindModal('staff-import-modal', 'open-staff-import-modal', '[data-close-staff-import-modal]', 'openStaffImportModal');
            const addStaffModalApi = bindModal('add-staff-modal', 'open-add-staff-modal', '[data-close-add-staff-modal]', 'openAddStaffModal');
            const reactivateModalApi = bindModal('reactivate-staff-modal', null, '[data-close-reactivate-staff-modal]', 'openReactivateStaffModal');

            const reactivateForm = document.getElementById('reactivate-staff-form');
            const reactivateName = document.getElementById('reactivate-staff-name');
            const reactivateCurrentPlan = document.getElementById('reactivate-current-plan');
            const reactivateStaffIdInput = document.getElementById('reactivate_staff_id_input');
            const reactivateStaffNameInput = document.getElementById('reactivate_staff_name_input');
            const reactivateCurrentPlanInput = document.getElementById('reactivate_current_plan_input');

            const reactivateModalTitle = document.getElementById('reactivate-modal-title');

            const setReactivateFormState = function (staffId, staffName, currentPlan, mode) {
                if (!reactivateForm) {
                    return;
                }

                const actionTemplate = reactivateForm.dataset.actionTemplate || '';
                reactivateForm.action = staffId ? actionTemplate.replace('__STAFF__', staffId) : '';

                if (reactivateName) {
                    reactivateName.textContent = staffName || 'Select staff';
                }

                if (reactivateCurrentPlan) {
                    if (mode === 'assign') {
                        reactivateCurrentPlan.textContent = 'No plan currently assigned.';
                    } else {
                        reactivateCurrentPlan.textContent = currentPlan ? `Current expired plan: ${currentPlan}` : '';
                    }
                }

                if (reactivateModalTitle) {
                    reactivateModalTitle.textContent = mode === 'assign' ? 'Assign Plan & Activate Staff' : 'Activate Expired Staff';
                }

                if (reactivateStaffIdInput) {
                    reactivateStaffIdInput.value = staffId || '';
                }

                if (reactivateStaffNameInput) {
                    reactivateStaffNameInput.value = staffName || '';
                }

                if (reactivateCurrentPlanInput) {
                    reactivateCurrentPlanInput.value = currentPlan || '';
                }
            };

            if (reactivateForm && reactivateStaffIdInput && reactivateStaffIdInput.value) {
                setReactivateFormState(
                    reactivateStaffIdInput.value,
                    reactivateStaffNameInput ? reactivateStaffNameInput.value : '',
                    reactivateCurrentPlanInput ? reactivateCurrentPlanInput.value : ''
                );
            }

            document.querySelectorAll('.reactivate-staff-checkbox').forEach(function (checkbox) {
                if (checkbox.dataset.modalBound === 'true') {
                    return;
                }

                checkbox.dataset.modalBound = 'true';
                checkbox.addEventListener('change', function (event) {
                    event.preventDefault();
                    checkbox.checked = false;

                    setReactivateFormState(
                        checkbox.dataset.staffId || '',
                        checkbox.dataset.staffName || '',
                        checkbox.dataset.currentPlan || '',
                        checkbox.dataset.mode || ''
                    );

                    if (reactivateModalApi && typeof reactivateModalApi.openModal === 'function') {
                        reactivateModalApi.openModal();
                    }
                });
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initStaffPageModals);
            return;
        }

        initStaffPageModals();
    })();
</script>
