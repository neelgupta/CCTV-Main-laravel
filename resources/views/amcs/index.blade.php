<x-app-layout>
    @php
    $authUser = auth()->user();
    $canCreateAmc = $authUser && $authUser->hasPermission('amc.create');
    $canEditAmc = $authUser && $authUser->hasPermission('amc.edit');
    $canDeleteAmc = $authUser && $authUser->hasPermission('amc.delete');
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">AMC List</h2>
                <p class="mt-1 text-sm text-slate-500">View and manage annual maintenance contracts.</p>
            </div>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <button type="button" id="toggle-amc-filters"
                    aria-expanded="false"
                    aria-controls="amc-filter-wrapper amc-advanced-filter-wrapper"
                    class="inline-flex w-full items-center justify-center  border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto"
                    title="Toggle filters" aria-label="Toggle AMC filters">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M2 4.75A.75.75 0 012.75 4h14.5a.75.75 0 01.53 1.28l-5.78 5.78v4.19a.75.75 0 01-1.14.64l-2.5-1.67a.75.75 0 01-.34-.64v-2.52L2.22 5.28A.75.75 0 012 4.75z" clip-rule="evenodd" />
                    </svg>
                </button>

                <div class="relative group w-full sm:w-auto">
                    <a href="{{ route('amcs.export', request()->query()) }}"
                        aria-label="Export"
                        title="Export"
                        class="inline-flex w-full items-center justify-center border border-slate-300 bg-white px-4 py-2.5 text-slate-700 shadow-sm transition hover:bg-slate-50 sm:w-auto">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true">
                            <path d="M3 14.5a1 1 0 011 1v.5a1.5 1.5 0 001.5 1.5h9A1.5 1.5 0 0016 16v-.5a1 1 0 112 0V16a3.5 3.5 0 01-3.5 3.5h-9A3.5 3.5 0 012 16v-.5a1 1 0 011-1z" />
                            <path d="M9.293 2.293a1 1 0 011.414 0l3.5 3.5a1 1 0 01-1.414 1.414L11 6.414V13a1 1 0 11-2 0V6.414L7.207 7.207a1 1 0 01-1.414-1.414l3.5-3.5z" />
                        </svg>

                        <span class="sr-only">Export</span>
                    </a>

                    <span
                        class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg group-hover:block">
                        Export
                    </span>
                </div>
                @if ($canCreateAmc)
                <div class="relative group w-full sm:w-auto">

                    <button
                        type="button"
                        id="open-amc-import-modal"
                        aria-label="Import"
                        title="Import"
                        class="inline-flex w-full items-center justify-center border border-cyan-200 bg-cyan-50 px-4 py-2.5 text-cyan-800 shadow-sm transition hover:bg-cyan-100 sm:w-auto">

                        <svg xmlns="http://www.w3.org/2000/svg"
                            class="h-4 w-4"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true">
                            <path d="M10 3.5a1 1 0 011 1V11l1.793-1.793a1 1 0 111.414 1.414l-3.5 3.5a1 1 0 01-1.414 0l-3.5-3.5a1 1 0 111.414-1.414L9 11V4.5a1 1 0 011-1z" />
                            <path d="M3 15.5a1 1 0 011 1v.5h12v-.5a1 1 0 112 0V17a2 2 0 01-2 2H4a2 2 0 01-2-2v-.5a1 1 0 011-1z" />
                        </svg>

                        <span class="sr-only">Import</span>
                    </button>

                    <span
                        class="pointer-events-none absolute bottom-full left-1/2 mb-2 hidden w-max -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-[10px] text-white shadow-lg group-hover:block">
                        Import
                    </span>

                </div>
                @endif
                @if ($canManageAmcPlans && $canEditAmc)
                <a href="{{ route('amc-plans.index') }}"
                    class="inline-flex w-full items-center justify-center border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                    Manage Plans
                </a>
                @endif
                @if ($canCreateAmc)
                <a href="{{ route('amcs.create') }}"
                    class="inline-flex w-full items-center justify-center bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 sm:w-auto">
                    Create AMC
                </a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8">
        <div class="w-full px-2 sm:px-6 lg:px-8">
            @if (session('success'))
            <div
                class="mb-4  border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
            @endif
            @php
            $visitStatusStyles = [
            'pending' => 'bg-amber-50 text-amber-700 ring-1 ring-amber-200',
            'scheduled' => 'bg-sky-50 text-sky-700 ring-1 ring-sky-200',
            'completed' => 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200',
            'cancelled' => 'bg-rose-50 text-rose-700 ring-1 ring-rose-200',
            ];
            @endphp
            

                @php
                $chipBase = 'inline-flex items-center gap-2 border px-4 py-2 text-[9px] font-semibold transition hover:shadow-sm';
                $rangeTone = [
                'today' => 'bg-amber-50 text-amber-900 border-amber-200 hover:bg-amber-100',
                'week' => 'bg-sky-50 text-sky-900 border-sky-200 hover:bg-sky-100',
                'month' => 'bg-emerald-50 text-emerald-900 border-emerald-200 hover:bg-emerald-100',
                'next_month' => 'bg-violet-50 text-violet-900 border-violet-200 hover:bg-violet-100',
                ];
                $statusTone = [
                'pending' => 'bg-amber-50 text-amber-900 border-amber-200 hover:bg-amber-100',
                'scheduled' => 'bg-sky-50 text-sky-900 border-sky-200 hover:bg-sky-100',
                'completed' => 'bg-emerald-50 text-emerald-900 border-emerald-200 hover:bg-emerald-100',
                'cancelled' => 'bg-rose-50 text-rose-900 border-rose-200 hover:bg-rose-100',
                ];
                $typeTone = [
                'comprehensive' => 'bg-violet-50 text-violet-900 border-violet-200 hover:bg-violet-100',
                'non_comprehensive' => 'bg-emerald-50 text-emerald-900 border-emerald-200 hover:bg-emerald-100',
                ];
                $filterQuery = request()->except(['page']);
                $rangeFilterUrl = fn (string $range) => route('amcs.index', array_merge($filterQuery, ['visit_range' => $range]));
                $visitStatusFilterUrl = fn (string $field, string $status) => route('amcs.index', array_merge($filterQuery, [$field => $status]));
                $amcTypeFilterUrl = fn (string $type) => route('amcs.index', array_merge($filterQuery, ['amc_type' => $type]));
                @endphp

                <form method="GET" action="{{ route('amcs.index') }}">
                    @if (request()->filled('mode'))
                    <input type="hidden" name="mode" value="{{ request('mode') }}">
                    @endif
                    @foreach (['visit_range', 'last_visit_status', 'amc_type'] as $preservedFilter)
                    @if (request()->filled($preservedFilter))
                    <input type="hidden" name="{{ $preservedFilter }}" value="{{ request($preservedFilter) }}">
                    @endif
                    @endforeach

                    <div id="amc-filter-wrapper" class="hidden mt-4 border border-slate-200 bg-white p-3 shadow-sm sm:p-6 bg-slate-50 p-4">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <h3 class="text-lg font-bold text-slate-900">AMC Records</h3>
                                <p class="mt-0.5 text-sm text-slate-500">All saved AMC contracts in table format.</p>
                            </div>
                            <div class="text-sm font-semibold text-slate-500 sm:text-right">{{ $amcs->total() }} contracts</div>
                        </div>
                        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                            @php
                            $modePills = [
                            'all' => 'All',
                            'schedule' => 'Schedule',
                            'on_call' => 'On Call',
                            'hybrid' => 'Hybrid',
                            ];
                            @endphp
                            @foreach ($modePills as $modeKey => $modeLabel)
                            @php
                            $isActiveMode = ($activeAmcMode ?? 'all') === $modeKey;
                            $modeCount = $amcModeCounters[$modeKey] ?? 0;
                            $modeQuery = request()->except('page');
                            $modeQuery['mode'] = $modeKey;
                            @endphp
                            <a href="{{ route('amcs.index', $modeQuery) }}"
                                class="border px-4 py-3 transition {{ $isActiveMode ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50' }}">
                                <div class="text-xs font-semibold uppercase tracking-wide">{{ $modeLabel }}</div>
                                <div class="mt-1 text-2xl font-bold">{{ $modeCount }}</div>
                            </a>
                            @endforeach
                        </div>


                        <div class="grid gap-4 lg:grid-cols-4">
                            <div class="lg:col-span-2">
                                <label for="amc-search-filter"
                                    class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Search</label>
                                <input id="amc-search-filter" type="search" name="search" value="{{ request('search') }}"
                                    placeholder="Search by contract, customer, project, phone, or email"
                                    class="block w-full border-slate-300 text-sm">
                            </div>
                            <div>
                                <label for="amc-technician-filter"
                                    class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-500">Technician</label>
                                <select id="amc-technician-filter" name="technician"
                                    class="block w-full border-slate-300 text-sm">
                                    <option value="">All technicians</option>
                                    <option value="unassigned" @selected(request('technician')==='unassigned' )>Unassigned</option>
                                    @foreach ($amcFilterTechnicians as $technician)
                                    <option value="{{ $technician }}" @selected((string) request('technician')===(string) $technician)>
                                        {{ $technician }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div id="amc-advanced-filter-wrapper" class="hidden mt-4">
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Advanced Filter</h3>
                            </div>
                            <a href="{{ route('amcs.index') }}"
                                class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 hover:bg-slate-50">
                                Clear All
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($visitRangeOptions as $rangeKey => $rangeMeta)
                            @php
                            $isActiveRange = $activeVisitRange === $rangeKey;
                            $cardCount = $amcVisitRangeCounts[$rangeKey] ?? 0;
                            @endphp
                            <a href="{{ $rangeFilterUrl($rangeKey) }}"
                                class="{{ $chipBase }} {{ $rangeTone[$rangeKey] ?? 'bg-white text-slate-900 border-amber-200 hover:bg-amber-100' }} {{ $isActiveRange ? 'ring-2 ring-offset-2 ring-amber-700' : '' }}"
                                @if ($isActiveRange) aria-current="page" @endif>
                                <span>{{ $rangeMeta['label'] }}</span>
                                <span class=" bg-white/80 px-2 py-0.5 text-[11px] font-bold">{{ $cardCount }}</span>
                            </a>
                            @endforeach
                            @foreach ($visitStatusOptions as $statusKey => $statusMeta)
                            @php
                            $isActiveStatus = $activeLastVisitStatus === $statusKey;
                            $cardCount = $amcVisitStatusCounts['last'][$statusKey] ?? 0;
                            @endphp
                            <a href="{{ $visitStatusFilterUrl('last_visit_status', $statusKey) }}"
                                class="{{ $chipBase }} {{ $statusTone[$statusKey] ?? 'bg-white text-slate-900 border-rose-200 hover:bg-rose-100' }} {{ $isActiveStatus ? 'ring-2 ring-offset-2 ring-rose-700' : '' }}"
                                @if ($isActiveStatus) aria-current="page" @endif>
                                <span>Last Visit Status {{ $statusMeta['label'] }}</span>
                                <span class=" bg-white/80 px-2 py-0.5 text-[11px] font-bold">{{ $cardCount }}</span>
                            </a>
                            @endforeach
                            @foreach ($amcTypeOptions as $typeKey => $typeLabel)
                            @php
                            $isActiveType = $activeAmcType === $typeKey;
                            $cardCount = $amcTypeCounts[$typeKey] ?? 0;
                            @endphp
                            <a href="{{ $amcTypeFilterUrl($typeKey) }}"
                                class="{{ $chipBase }} {{ $typeTone[$typeKey] ?? 'bg-white text-slate-900 border-violet-200 hover:bg-violet-100' }} {{ $isActiveType ? 'ring-2 ring-offset-2 ring-violet-700' : '' }}"
                                @if ($isActiveType) aria-current="page" @endif>
                                <span>{{ $typeLabel }}</span>
                                <span class=" bg-white/80 px-2 py-0.5 text-[11px] font-bold">{{ $cardCount }}</span>
                            </a>
                            @endforeach
                        </div>

                    </div>
                    </div>

                    
                </form>
            

            @if ($amcs->isEmpty())
            <p class="mt-3 text-sm italic text-slate-500">No AMC contracts created yet.</p>
            @else
            <div class="mt-3 overflow-x-auto border border-slate-200">
                <table class="min-w-[1440px] w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Contract No</th>
                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Project
                            </th>
                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Customer
                            </th>

                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                AMC Type</th>
                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Plan Mode</th>
                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Remaining / Total Visits</th>
                            {{-- <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Last Visit Date</th>
                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Last Visit Status</th> --}}
                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Total</th>
                            <th
                                class="px-3 py-3 text-left text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Technician</th>
                            <th
                                class="px-3 py-3 text-right text-xs font-semibold uppercase tracking-wider text-white sm:px-4">
                                Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach ($amcs as $amc)
                        @include('amcs.partials.row', [
                        'amc' => $amc,
                        'amcTypeOptions' => $amcTypeOptions,
                        'amcPaymentStatusOptions' => $amcPaymentStatusOptions,
                        'canEditAmc' => $canEditAmc,
                        'canDeleteAmc' => $canDeleteAmc,
                        ])
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($amcs->hasPages())
            <div class="mt-4">
                {{ $amcs->onEachSide(1)->links() }}
            </div>
            @endif
            @endif
        </div>
    </div>
    </div>

    @if ($canCreateAmc)
    <div id="amc-assign-modal"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/55 px-4 py-6"
        aria-hidden="true">
        <div class="absolute inset-0" data-close-amc-assign-modal></div>
        <div class="relative z-10 flex max-h-[85vh] w-full max-w-md flex-col overflow-hidden border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-cyan-700">Assign Technician</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="amc-assign-modal-title">Update Technician</h3>
                </div>
                <button type="button" data-close-amc-assign-modal
                    class="inline-flex h-9 w-9 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close technician modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div class="border-b border-slate-100 px-5 py-3">
                <div class="flex items-center gap-2 border border-slate-200 bg-slate-50 px-3 py-2">
                    <svg class="h-4 w-4 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 104.213 9.05l3.618 3.619a.75.75 0 101.06-1.06l-3.619-3.62A5.5 5.5 0 009 3.5zM5 9a4 4 0 118 0 4 4 0 01-8 0z" clip-rule="evenodd" />
                    </svg>
                    <input type="text" id="amc-assign-search" placeholder="Search technician..."
                        class="w-full border-0 bg-transparent text-sm text-slate-700 outline-none placeholder:text-slate-400 focus:ring-0">
                </div>
            </div>

            <div id="amc-assign-list" class="flex-1 overflow-y-auto px-5 py-3 space-y-1.5">
                @forelse ($amcTechnicians as $technician)
                <label class="js-amc-assign-option flex cursor-pointer items-center gap-3 border border-slate-200 bg-white px-3 py-2.5 transition hover:border-cyan-200 hover:bg-cyan-50/40"
                    data-technician-name="{{ \Illuminate\Support\Str::lower($technician->name) }}">
                    <input type="checkbox" value="{{ $technician->id }}"
                        class="js-amc-assign-checkbox h-4 w-4 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500">
                    <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center bg-cyan-500 text-[11px] font-bold text-white">
                        {{ strtoupper(substr($technician->name, 0, 1)) }}
                    </span>
                    <span class="text-sm font-medium text-slate-800">{{ $technician->name }}</span>
                </label>
                @empty
                <p class="px-1 py-4 text-center text-sm text-slate-400">No technicians available.</p>
                @endforelse
                <p id="amc-assign-empty" class="hidden px-1 py-4 text-center text-sm text-slate-400">No technicians match your search.</p>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-5 py-4">
                <span class="text-xs text-slate-500"><span id="amc-assign-selected-count">0</span> selected</span>
                <div class="flex items-center gap-2">
                    <button type="button" data-close-amc-assign-modal
                        class="inline-flex items-center justify-center border border-slate-300 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="button" id="amc-assign-save"
                        class="inline-flex items-center justify-center bg-slate-950 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-slate-800 disabled:opacity-60">
                        Save
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div id="amc-visit-history-modal"
        class="fixed inset-0 z-50 hidden flex items-center justify-center bg-slate-950/55 px-4 py-6"
        aria-hidden="true">
        <div class="absolute inset-0" data-close-amc-visit-history-modal></div>
        <div class="relative z-10 flex max-h-[85vh] w-full max-w-3xl flex-col overflow-hidden rounded-[1.5rem] border border-slate-200 bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-sky-700">AMC Visit Schedule</p>
                    <h3 class="mt-1 text-lg font-extrabold text-slate-950" id="amc-visit-history-modal-title">All Visits</h3>
                </div>
                <button type="button" data-close-amc-visit-history-modal
                    class="inline-flex h-9 w-9 items-center justify-center border border-slate-200 text-slate-500 transition hover:bg-slate-100 hover:text-slate-700"
                    aria-label="Close visit history modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4.5 w-4.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.22 4.22a.75.75 0 011.06 0L10 8.94l4.72-4.72a.75.75 0 111.06 1.06L11.06 10l4.72 4.72a.75.75 0 11-1.06 1.06L10 11.06l-4.72 4.72a.75.75 0 11-1.06-1.06L8.94 10 4.22 5.28a.75.75 0 010-1.06z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div class="border-b border-slate-100 px-5 py-3 text-sm text-slate-600">
                <div id="amc-visit-history-modal-subtitle" class="font-semibold text-slate-900"></div>
                <div class="mt-1 text-slate-500">All AMC visits are shown below, including status and technician where available.</div>
            </div>

            <div class="grid grid-cols-2 gap-3 border-b border-slate-100 px-5 py-4 sm:grid-cols-4">
                <div class=" border border-amber-200 bg-amber-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-amber-700">Pending</div>
                    <div id="amc-visit-history-count-pending" class="mt-1 text-2xl font-extrabold text-amber-900">0</div>
                </div>
                <div class=" border border-sky-200 bg-sky-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-sky-700">Scheduled</div>
                    <div id="amc-visit-history-count-scheduled" class="mt-1 text-2xl font-extrabold text-sky-900">0</div>
                </div>
                <div class=" border border-rose-200 bg-rose-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-rose-700">Cancelled</div>
                    <div id="amc-visit-history-count-cancelled" class="mt-1 text-2xl font-extrabold text-rose-900">0</div>
                </div>
                <div class=" border border-emerald-200 bg-emerald-50 px-4 py-3">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-emerald-700">Completed</div>
                    <div id="amc-visit-history-count-completed" class="mt-1 text-2xl font-extrabold text-emerald-900">0</div>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto px-5 py-4">
                <div id="amc-visit-history-empty" class="hidden border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-center text-sm text-slate-500">
                    No AMC visits are available for this contract.
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Visit No</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Visit Date</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Technician</th>
                            </tr>
                        </thead>
                        <tbody id="amc-visit-history-list" class="divide-y divide-slate-100 bg-white text-sm"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div id="amc-import-modal"
        class="{{ $errors->has('amc_import_file') ? '' : 'hidden ' }}fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 px-4 py-6"
        aria-hidden="{{ $errors->has('amc_import_file') ? 'false' : 'true' }}"
        data-modal-open="{{ $errors->has('amc_import_file') ? 'true' : 'false' }}">
        <div class="absolute inset-0" data-close-amc-import-modal></div>
        <div class="relative w-full max-w-xl rounded-3xl bg-white p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h4 class="text-lg font-bold text-slate-950">Import AMC</h4>
                    <p class="mt-1 text-sm text-slate-500">Upload CSV, XLS, or XLSX with the AMC fields, including an
                        optional existing plan name or ID.</p>
                </div>
                <button type="button" data-close-amc-import-modal
                    class=" p-2 text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                    aria-label="Close import modal">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            @if ($errors->has('amc_import_file'))
            <div class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                @foreach ($errors->get('amc_import_file') as $message)
                <p>{{ $message }}</p>
                @endforeach
            </div>
            @endif

            <form method="POST" action="{{ route('amcs.import') }}" enctype="multipart/form-data"
                class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="amc_import_file" class="mb-2 block text-sm font-semibold text-slate-700">Choose
                        file</label>
                    <input id="amc_import_file" type="file" name="amc_import_file" accept=".csv,.xls,.xlsx"
                        class="block w-full rounded-2xl border border-slate-300 bg-white px-3 py-3 text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:text-xs file:font-semibold file:text-slate-700">
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('amcs.import.sample') }}"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-slate-700 shadow-sm transition hover:bg-slate-50">
                        Download Sample
                    </a>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-2xl bg-cyan-600 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-cyan-700">
                        Import AMC
                    </button>
                </div>
            </form>

            <div class="mt-4 rounded-2xl border border-cyan-100 bg-cyan-50 px-4 py-3 text-sm text-cyan-900">
                AMC import required columns: <span class="font-semibold">client_name, email, phone, address,
                    project_name, amount</span>. Optional columns like <span class="font-semibold">plan</span>, gst,
                discount, amc_type, and start_date are also supported. When <span class="font-semibold">plan</span>
                matches an active AMC plan, the import links that plan, copies its duration/service setup, and amount
                can be left blank to use the plan price.
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        (function() {
            const filterToggle = document.getElementById('toggle-amc-filters');
            const filterWrapper = document.getElementById('amc-filter-wrapper');
            const filterAdvancedWrapper = document.getElementById('amc-advanced-filter-wrapper');
            const filterForm = filterWrapper?.closest('form');

            if (filterToggle && filterWrapper && filterAdvancedWrapper && filterForm) {
                const syncToggle = (open) => {
                    filterWrapper.classList.toggle('hidden', !open);
                    filterAdvancedWrapper.classList.toggle('hidden', !open);
                    filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                };
                const technicianFilter = document.getElementById('amc-technician-filter');

                syncToggle(false);

                filterToggle.addEventListener('click', function() {
                    syncToggle(filterWrapper.classList.contains('hidden'));
                });

                technicianFilter?.addEventListener('change', function() {
                    if (typeof filterForm.requestSubmit === 'function') {
                        filterForm.requestSubmit();
                        return;
                    }

                    filterForm.submit();
                });
            }

            const importModal = document.getElementById('amc-import-modal');
            const importOpenButton = document.getElementById('open-amc-import-modal');
            const visitHistoryModal = document.getElementById('amc-visit-history-modal');

            if (importModal && importOpenButton) {
                const closeButtons = importModal.querySelectorAll('[data-close-amc-import-modal]');
                const openImportModal = function() {
                    importModal.classList.remove('hidden');
                    importModal.setAttribute('aria-hidden', 'false');
                };
                const closeImportModal = function() {
                    importModal.classList.add('hidden');
                    importModal.setAttribute('aria-hidden', 'true');
                };

                importOpenButton.addEventListener('click', openImportModal);
                closeButtons.forEach(function(button) {
                    button.addEventListener('click', closeImportModal);
                });

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && !importModal.classList.contains('hidden')) {
                        closeImportModal();
                    }
                });

                if (importModal.dataset.modalOpen === 'true') {
                    openImportModal();
                }
            }

            if (visitHistoryModal) {
                const historyTitle = document.getElementById('amc-visit-history-modal-title');
                const historySubtitle = document.getElementById('amc-visit-history-modal-subtitle');
                const historyList = document.getElementById('amc-visit-history-list');
                const historyEmpty = document.getElementById('amc-visit-history-empty');
                const historyCountEls = {
                    pending: document.getElementById('amc-visit-history-count-pending'),
                    scheduled: document.getElementById('amc-visit-history-count-scheduled'),
                    cancelled: document.getElementById('amc-visit-history-count-cancelled'),
                    completed: document.getElementById('amc-visit-history-count-completed'),
                };

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                const openHistoryModal = (button) => {
                    const amcName = button.dataset.amcName || 'AMC';
                    const contractNo = button.dataset.contractNo || '';
                    let visits = [];
                    let visitStatusCounts = {};

                    try {
                        visits = JSON.parse(button.dataset.visitHistory || '[]');
                    } catch (error) {
                        visits = [];
                    }

                    try {
                        visitStatusCounts = JSON.parse(button.dataset.visitStatusCounts || '{}');
                    } catch (error) {
                        visitStatusCounts = {};
                    }

                    if (historyTitle) {
                        historyTitle.textContent = `All Visits - ${amcName}`;
                    }

                    if (historySubtitle) {
                        historySubtitle.textContent = contractNo ? `Contract ${contractNo}` : amcName;
                    }

                    if (historyList) {
                        historyList.innerHTML = visits.length ?
                            visits.map((visit) => `
                                    <tr class="align-top">
                                        <td class="px-4 py-3 font-semibold text-slate-900">${escapeHtml(visit.visit_no)}</td>
                                        <td class="px-4 py-3 whitespace-nowrap text-slate-700">${escapeHtml(visit.visit_date)}</td>
                                        <td class="px-4 py-3 text-slate-700">
                                            <span class="inline-flex bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">${escapeHtml(visit.status)}</span>
                                        </td>
                                        <td class="px-4 py-3 text-slate-700">${escapeHtml(visit.technician || '-')}</td>
                                    </tr>
                                `).join('') :
                            '';
                    }

                    if (historyEmpty) {
                        historyEmpty.classList.toggle('hidden', visits.length !== 0);
                    }

                    Object.entries(historyCountEls).forEach(([status, element]) => {
                        if (element) {
                            element.textContent = String(Number(visitStatusCounts?.[status] || 0));
                        }
                    });

                    visitHistoryModal.classList.remove('hidden');
                    visitHistoryModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');
                };

                const closeHistoryModal = () => {
                    visitHistoryModal.classList.add('hidden');
                    visitHistoryModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                };

                document.querySelectorAll('.js-amc-visit-history').forEach((button) => {
                    button.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        openHistoryModal(button);
                    });
                });

                visitHistoryModal.querySelectorAll('[data-close-amc-visit-history-modal]').forEach((button) => {
                    button.addEventListener('click', closeHistoryModal);
                });

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && !visitHistoryModal.classList.contains('hidden')) {
                        closeHistoryModal();
                    }
                });
            }

            const assignModal = document.getElementById('amc-assign-modal');

            if (assignModal) {
                const list = document.getElementById('amc-assign-list');
                const searchInput = document.getElementById('amc-assign-search');
                const emptyMsg = document.getElementById('amc-assign-empty');
                const countEl = document.getElementById('amc-assign-selected-count');
                const titleEl = document.getElementById('amc-assign-modal-title');
                const saveBtn = document.getElementById('amc-assign-save');
                const csrfToken = '{{ csrf_token() }}';

                let activeButton = null;
                let activeUrl = '';

                const checkboxes = () => Array.from(assignModal.querySelectorAll('.js-amc-assign-checkbox'));
                const options = () => Array.from(assignModal.querySelectorAll('.js-amc-assign-option'));

                const escapeHtml = (value) => String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');

                const getInitials = (name) => {
                    const parts = String(name || '').trim().split(/\s+/).filter(Boolean);
                    if (!parts.length) return '?';
                    if (parts.length === 1) return parts[0].slice(0, 1).toUpperCase();
                    return (parts[0].slice(0, 1) + parts[parts.length - 1].slice(0, 1)).toUpperCase();
                };

                const renderTechnicianBadge = (name) => {
                    if (!String(name || '').trim()) {
                        return '<span class="text-slate-400">Assign</span>';
                    }

                    return `
                            <span class="flex h-8 w-8 flex-shrink-0 items-center justify-center bg-cyan-500 text-[11px] font-bold text-white">
                                ${escapeHtml(getInitials(name))}
                            </span>
                            <span class="truncate">${escapeHtml(name)}</span>
                        `;
                };

                const updateCount = () => {
                    if (countEl) {
                        countEl.textContent = String(checkboxes().filter((c) => c.checked).length);
                    }
                };

                const filterTechnicians = (term) => {
                    const needle = String(term || '').trim().toLowerCase();
                    let visible = 0;

                    options().forEach((option) => {
                        const name = option.dataset.technicianName || '';
                        const match = needle === '' || name.includes(needle);
                        option.classList.toggle('hidden', !match);
                        if (match) {
                            visible += 1;
                        }
                    });

                    if (emptyMsg) {
                        emptyMsg.classList.toggle('hidden', visible !== 0);
                    }
                };

                const openModal = (button) => {
                    activeButton = button;
                    activeUrl = button.dataset.assignUrl || '';

                    const amcName = button.dataset.amcName || 'AMC';
                    const visitDate = button.dataset.visitDate || '';
                    if (titleEl) {
                        titleEl.textContent = visitDate ?
                            `Assign Technician - ${amcName} (${visitDate})` :
                            `Assign Technician - ${amcName}`;
                    }

                    const currentTechnicianName = String(button.dataset.currentTechnicianName || '').trim().toLowerCase();
                    checkboxes().forEach((checkbox) => {
                        const technicianName = String(checkbox.closest('.js-amc-assign-option')?.dataset.technicianName || '');
                        checkbox.checked = currentTechnicianName !== '' && technicianName === currentTechnicianName;
                    });

                    if (searchInput) {
                        searchInput.value = '';
                    }

                    filterTechnicians('');
                    updateCount();

                    assignModal.classList.remove('hidden');
                    assignModal.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('overflow-hidden');

                    if (list) {
                        list.scrollTop = 0;
                    }
                };

                const closeModal = () => {
                    assignModal.classList.add('hidden');
                    assignModal.setAttribute('aria-hidden', 'true');
                    document.body.classList.remove('overflow-hidden');
                    activeButton = null;
                    activeUrl = '';
                };

                const notify = (type, message) => {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: type,
                            title: message,
                            showConfirmButton: false,
                            timer: 2500,
                            timerProgressBar: true,
                        });
                        return;
                    }

                    alert(message);
                };

                const nextVisitIconHtml = `
                        <svg aria-hidden="true" class="h-4 w-4 shrink-0 text-current" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="5" width="18" height="16" rx="2"></rect>
                            <path d="M8 3v4M16 3v4M3 10h18"></path>
                        </svg>
                    `;

                const renderNextVisitButtonHtml = (label) => `${nextVisitIconHtml}<span class="truncate">${label || 'Set date'}</span>`;
                const nextVisitSaveTimers = new WeakMap();
                const nextVisitDateFormatter = new Intl.DateTimeFormat('en-GB', {
                    day: '2-digit',
                    month: 'short',
                    year: 'numeric',
                });

                const formatNextVisitLabel = (dateValue) => {
                    if (!dateValue) {
                        return 'Set date';
                    }

                    const parsedDate = new Date(`${dateValue}T00:00:00`);

                    if (Number.isNaN(parsedDate.getTime())) {
                        return 'Set date';
                    }

                    return nextVisitDateFormatter.format(parsedDate);
                };

                const getNextVisitBadgeClass = (dateValue) => {
                    if (!dateValue) {
                        return 'border-slate-200 bg-white text-slate-700';
                    }

                    const parsedDate = new Date(`${dateValue}T00:00:00`);
                    if (Number.isNaN(parsedDate.getTime())) {
                        return 'border-slate-200 bg-white text-slate-700';
                    }

                    const today = new Date();
                    today.setHours(0, 0, 0, 0);

                    const selectedDay = new Date(parsedDate);
                    selectedDay.setHours(0, 0, 0, 0);

                    if (selectedDay.getTime() === today.getTime()) {
                        return 'border-emerald-300 bg-emerald-50 text-emerald-800';
                    }

                    if (selectedDay < today) {
                        return 'border-rose-300 bg-rose-50 text-rose-700';
                    }

                    const diffInDays = Math.ceil((selectedDay - today) / (1000 * 60 * 60 * 24));
                    if (diffInDays <= 7) {
                        return 'border-amber-300 bg-amber-50 text-amber-800';
                    }

                    return 'border-slate-200 bg-white text-slate-700';
                };

                const updateNextVisitDisplay = (input, dateValue) => {
                    const container = input.closest('td');
                    const triggerButton = container ? container.querySelector('.js-amc-next-visit-trigger') : null;
                    const label = formatNextVisitLabel(dateValue);
                    const badgeClass = getNextVisitBadgeClass(dateValue);

                    if (triggerButton) {
                        triggerButton.className = `js-amc-next-visit-trigger inline-flex max-w-full items-center gap-2 border px-3 py-2 text-sm font-semibold shadow-sm transition hover:shadow focus:outline-none focus:ring-2 focus:ring-indigo-500/40 ${badgeClass}`;
                        triggerButton.innerHTML = renderNextVisitButtonHtml(label);
                        triggerButton.dataset.visitDate = label;
                        triggerButton.dataset.displayDate = label;
                    }

                    input.dataset.currentDate = dateValue || '';
                    input.dataset.displayDate = label;
                };

                const syncNextVisitButtons = () => {
                    document.querySelectorAll('.js-amc-next-visit-trigger').forEach((button) => {
                        const input = button.closest('td')?.querySelector('.js-amc-next-visit-input');
                        const serverDate = input?.dataset.currentDate || '';
                        const displayDate = input?.dataset.displayDate || button.dataset.displayDate || 'Set date';

                        if (input && input.value !== serverDate) {
                            input.value = serverDate;
                        }

                        button.className = `js-amc-next-visit-trigger inline-flex max-w-full items-center gap-2 border px-3 py-2 text-sm font-semibold shadow-sm transition hover:shadow focus:outline-none focus:ring-2 focus:ring-indigo-500/40 ${getNextVisitBadgeClass(serverDate)}`;
                        button.innerHTML = renderNextVisitButtonHtml(serverDate ? displayDate : 'Set date');
                    });
                };

                syncNextVisitButtons();
                window.addEventListener('pageshow', syncNextVisitButtons);

                const saveAssignment = () => {
                    if (!activeUrl || !activeButton || !saveBtn) {
                        return;
                    }

                    const selected = checkboxes().find((checkbox) => checkbox.checked);
                    const originalText = saveBtn.textContent;

                    saveBtn.disabled = true;
                    saveBtn.textContent = 'Saving...';

                    fetch(activeUrl, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                [activeButton.dataset.assignmentField || 'assigned_staff_id']: selected ? selected.value : null,
                            }),
                        })
                        .then(async (response) => {
                            const data = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                                throw new Error(firstError || data?.message || 'Failed to update technician.');
                            }

                            return data;
                        })
                        .then((data) => {
                            if (data.row_html) {
                                replaceAmcRow(data.row_html);
                            } else {
                                const assignedName = data.technician_name || '';
                                activeButton.innerHTML = renderTechnicianBadge(assignedName);
                                activeButton.dataset.currentTechnicianName = assignedName;
                                activeButton.dataset.currentTechnicianId = data.technician_id || '';
                            }
                            closeModal();
                            notify('success', data.message || 'Visit technician updated successfully.');
                        })
                        .catch((error) => {
                            notify('error', error.message || 'Failed to update technician.');
                        })
                        .finally(() => {
                            saveBtn.disabled = false;
                            saveBtn.textContent = originalText;
                        });
                };

                const replaceAmcRow = (rowHtml) => {
                    if (!rowHtml) {
                        return;
                    }

                    const parser = new DOMParser();
                    const doc = parser.parseFromString(rowHtml, 'text/html');
                    const newRow = doc.querySelector('tr[data-amc-row-id]');
                    if (!newRow) {
                        return;
                    }

                    const rowId = newRow.getAttribute('data-amc-row-id');
                    const currentRow = document.querySelector(`tr[data-amc-row-id="${rowId}"]`);
                    if (!currentRow) {
                        return;
                    }

                    currentRow.replaceWith(newRow);
                };

                const openNextVisitPicker = (button) => {
                    const container = button.closest('td');
                    const input = container ? container.querySelector('.js-amc-next-visit-input') : null;
                    if (!input) {
                        return;
                    }

                    input.focus({
                        preventScroll: true
                    });

                    if (typeof input.showPicker === 'function') {
                        try {
                            input.showPicker();
                            return;
                        } catch (error) {
                            // Fall through to click() for browsers that block showPicker on hidden inputs.
                        }
                    }

                    input.click();
                };

                const queueNextVisitDateSave = (input) => {
                    const previousTimer = nextVisitSaveTimers.get(input);
                    if (previousTimer) {
                        clearTimeout(previousTimer);
                    }

                    const timer = window.setTimeout(() => {
                        nextVisitSaveTimers.delete(input);
                        saveNextVisitDate(input);
                    }, 180);

                    nextVisitSaveTimers.set(input, timer);
                };

                const saveNextVisitDate = (input) => {
                    const updateUrl = input.dataset.updateUrl || '';
                    const rowId = input.dataset.amcRowId || '';
                    const selectedDate = input.value || '';
                    const currentDate = input.dataset.currentDate || '';

                    if (!updateUrl || !rowId || !selectedDate || selectedDate === currentDate || input.dataset.isSaving === 'true') {
                        return;
                    }

                    const triggerButton = input.closest('td')?.querySelector('.js-amc-next-visit-trigger');
                    const originalButtonDisabled = triggerButton ? triggerButton.disabled : false;
                    const originalInputDisabled = input.disabled;

                    input.dataset.isSaving = 'true';
                    updateNextVisitDisplay(input, selectedDate);

                    if (triggerButton) {
                        triggerButton.disabled = true;
                    }
                    input.disabled = true;

                    fetch(updateUrl, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                visit_date: selectedDate,
                            }),
                        })
                        .then(async (response) => {
                            const data = await response.json().catch(() => ({}));

                            if (!response.ok) {
                                const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
                                throw new Error(firstError || data?.message || 'Failed to update visit date.');
                            }

                            return data;
                        })
                        .then((data) => {
                            replaceAmcRow(data.row_html);
                            notify('success', data.message || 'Next visit date updated successfully.');
                        })
                        .catch((error) => {
                            notify('error', error.message || 'Failed to update visit date.');
                            input.value = currentDate;
                            updateNextVisitDisplay(input, currentDate);
                        })
                        .finally(() => {
                            delete input.dataset.isSaving;
                            const pendingTimer = nextVisitSaveTimers.get(input);
                            if (pendingTimer) {
                                clearTimeout(pendingTimer);
                                nextVisitSaveTimers.delete(input);
                            }

                            const currentRow = document.querySelector(`tr[data-amc-row-id="${rowId}"]`);
                            const currentInput = currentRow ? currentRow.querySelector('.js-amc-next-visit-input') : null;
                            const currentButton = currentRow ? currentRow.querySelector('.js-amc-next-visit-trigger') : null;

                            if (currentInput) {
                                currentInput.disabled = false;
                                currentInput.dataset.currentDate = currentInput.value || currentDate;
                                currentInput.dataset.displayDate = currentInput.dataset.displayDate || formatNextVisitLabel(currentInput.value || currentDate);
                            } else {
                                input.disabled = originalInputDisabled;
                            }

                            if (currentButton) {
                                currentButton.disabled = false;
                            } else if (triggerButton) {
                                triggerButton.disabled = originalButtonDisabled;
                            }
                        });
                };

                const openAmcRow = (row) => {
                    const url = row.dataset.amcUrl || '';
                    if (url) {
                        window.location.href = url;
                    }
                };

                document.querySelectorAll('tr[data-amc-url]').forEach((row) => {
                    row.setAttribute('tabindex', '0');

                    row.addEventListener('click', (event) => {
                        if (event.target.closest('a, button, form, input, select, textarea, label')) {
                            return;
                        }

                        openAmcRow(row);
                    });

                    row.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            openAmcRow(row);
                        }
                    });
                });

                document.querySelectorAll('.js-amc-assign-technician').forEach((button) => {
                    button.addEventListener('click', function(event) {
                        event.preventDefault();
                        event.stopPropagation();
                        openModal(button);
                    });
                });

                document.addEventListener('click', function(event) {
                    const triggerButton = event.target.closest('.js-amc-next-visit-trigger');
                    if (!triggerButton) {
                        return;
                    }

                    event.preventDefault();
                    openNextVisitPicker(triggerButton);
                });

                document.addEventListener('change', function(event) {
                    const dateInput = event.target.closest('.js-amc-next-visit-input');
                    if (!dateInput) {
                        return;
                    }

                    queueNextVisitDateSave(dateInput);
                });

                document.addEventListener('input', function(event) {
                    const dateInput = event.target.closest('.js-amc-next-visit-input');
                    if (!dateInput) {
                        return;
                    }

                    queueNextVisitDateSave(dateInput);
                });

                assignModal.querySelectorAll('[data-close-amc-assign-modal]').forEach((button) => {
                    button.addEventListener('click', closeModal);
                });

                if (searchInput) {
                    searchInput.addEventListener('input', function(event) {
                        filterTechnicians(event.target.value);
                    });
                }

                checkboxes().forEach((checkbox) => {
                    checkbox.addEventListener('change', function() {
                        if (this.checked) {
                            checkboxes().forEach((other) => {
                                if (other !== this) {
                                    other.checked = false;
                                }
                            });
                        }

                        updateCount();
                    });
                });

                if (saveBtn) {
                    saveBtn.addEventListener('click', saveAssignment);
                }

                document.addEventListener('keydown', function(event) {
                    if (event.key === 'Escape' && !assignModal.classList.contains('hidden')) {
                        closeModal();
                    }
                });
            }
        })();
    </script>
    @endpush
</x-app-layout>
