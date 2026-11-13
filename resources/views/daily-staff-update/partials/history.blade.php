@php
    $embedded = $embedded ?? false;
    $historyIndexUrl = $historyIndexUrl ?? route('settings.daily-staff-update.history.index');
    $crmSettingsUrl = $crmSettingsUrl ?? route('settings.company.index', ['tab' => 'crm']);
    $searchFormAction = $embedded
        ? route('settings.company.index')
        : route('settings.daily-staff-update.history.index');
@endphp

<div class="{{ $embedded ? 'space-y-6' : '' }}">
    <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">Daily Update History</h2>
                <p class="mt-1 text-sm text-slate-500">View, download, search, and manage generated daily staff update reports.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="{{ $crmSettingsUrl }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    CRM Settings
                </a>
                <form method="POST" action="{{ route('settings.daily-staff-update.generate') }}" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <div class="flex items-center gap-2">
                        <label for="history-report-date" class="text-xs font-bold text-slate-500 uppercase tracking-wider">Date:</label>
                        <input type="date" id="history-report-date" name="report_date" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" class="rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-0">
                    </div>
                    <label class="inline-flex items-center gap-2 text-xs font-medium text-slate-600">
                        <input type="checkbox" name="force_generate" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Force generate
                    </label>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-950/10 transition hover:bg-slate-800">
                        Generate Report
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="rounded-[28px] border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-6 lg:h-[calc(100vh-3rem)]">
            <div class="border-b border-slate-200 pb-4">
                <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Current Month</div>
                <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ $currentMonthLabel }}</h3>
                <p class="mt-1 text-sm text-slate-500">Select a date to open that day's report.</p>
            </div>

            <div class="mt-4 space-y-2 overflow-y-auto lg:max-h-[calc(100vh-12rem)] lg:pr-1">
                @foreach($currentMonthDates as $dateItem)
                    @php
                        $dateKey = $dateItem->toDateString();
                        $isToday = $dateKey === $currentMonthTodayDateKey;
                        $hasReport = $currentMonthReports->has($dateKey);
                    @endphp

                    <a
                        href="{{ route('settings.daily-staff-update.calendar', ['date' => $dateKey]) }}"
                        class="group flex items-center gap-3 rounded-2xl border bg-white p-3 text-slate-700 transition hover:border-slate-300 hover:bg-slate-50"
                    >
                        <div class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-2xl border border-slate-200 bg-slate-50 text-center text-slate-600">
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em]">{{ $dateItem->format('D') }}</span>
                            <span class="text-lg font-bold leading-none">{{ $dateItem->format('d') }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate text-sm font-semibold">{{ $dateItem->format('j M Y') }}</span>
                                @if($isToday)
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em] text-amber-700">Today</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ $hasReport ? 'Report available' : 'No report generated' }}
                            </p>
                        </div>

                        <span class="h-2.5 w-2.5 shrink-0 rounded-full {{ $hasReport ? 'bg-emerald-500' : 'bg-slate-300' }}"></span>
                    </a>
                @endforeach
            </div>
        </aside>

        <div class="space-y-6">
            @if($embedded)
                @if(session('info'))
                    <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('info') }}</div>
                @endif
                @if(session('error'))
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
                @endif
            @else
                @if(session('success'))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
                @endif
                @if(session('info'))
                    <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('info') }}</div>
                @endif
                @if(session('error'))
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
                @endif
            @endif

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <form method="GET" action="{{ $searchFormAction }}" class="grid gap-4 lg:grid-cols-[1fr_auto_auto]">
                    @if($embedded)
                        <input type="hidden" name="tab" value="daily-update-history">
                    @endif
                    <div>
                        <label for="daily_update_search" class="text-sm font-semibold text-slate-700">Search</label>
                        <input id="daily_update_search" name="search" value="{{ $search }}" placeholder="Date, status, generated by..." class="mt-1 block w-full rounded-2xl border-slate-300">
                    </div>
                    <div>
                        <label for="daily_update_sort" class="text-sm font-semibold text-slate-700">Sort by</label>
                        <select id="daily_update_sort" name="sort" class="mt-1 block w-full rounded-2xl border-slate-300">
                            <option value="report_date" @selected($sort === 'report_date')>Report Date</option>
                            <option value="generated_at" @selected($sort === 'generated_at')>Generated Time</option>
                            <option value="generation_type" @selected($sort === 'generation_type')>Generation Type</option>
                            <option value="status" @selected($sort === 'status')>Status</option>
                        </select>
                    </div>
                    <div>
                        <label for="daily_update_direction" class="text-sm font-semibold text-slate-700">Direction</label>
                        <select id="daily_update_direction" name="direction" class="mt-1 block w-full rounded-2xl border-slate-300">
                            <option value="desc" @selected($direction === 'desc')>Descending</option>
                            <option value="asc" @selected($direction === 'asc')>Ascending</option>
                        </select>
                    </div>
                    <div class="lg:col-span-3 flex justify-end gap-3">
                        <a href="{{ $historyIndexUrl }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Reset</a>
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-950/10 transition hover:bg-slate-800">Apply</button>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[1100px] text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-6 py-4 text-left">Report Date</th>
                                <th class="px-6 py-4 text-left">Generated By</th>
                                <th class="px-6 py-4 text-left">Generated Time</th>
                                <th class="px-6 py-4 text-left">Generation Type</th>
                                <th class="px-6 py-4 text-left">Status</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($reports as $report)
                                <tr>
                                    <td class="px-6 py-4 font-semibold text-slate-900">{{ optional($report->report_date)->format('d/m/Y') }}</td>
                                    <td class="px-6 py-4 text-slate-700">{{ $report->generatedBy?->name ?? 'System' }}</td>
                                    <td class="px-6 py-4 text-slate-700">{{ optional($report->generated_at)->format('d M Y, h:i A') ?? '-' }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-slate-700">
                                            {{ ucfirst($report->generation_type) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        @php
                                            $statusClasses = $report->status === 'success'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-rose-200 bg-rose-50 text-rose-700';
                                        @endphp
                                        <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold {{ $statusClasses }}">
                                            {{ ucfirst($report->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('settings.daily-staff-update.calendar', ['date' => optional($report->report_date)->toDateString()]) }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                            <a href="{{ route('settings.daily-staff-update.download', $report) }}" class="rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs font-semibold text-indigo-700 hover:bg-indigo-100">Download</a>
                                            <form method="POST" action="{{ route('settings.daily-staff-update.destroy', $report) }}" data-swal-confirm="Delete this report permanently?" data-swal-title="Delete Report" class="inline-flex">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">
                                        No reports found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($reports->hasPages())
                    <div class="border-t border-slate-200 px-6 py-4">
                        {{ $reports->links() }}
                    </div>
                @endif
            </section>
        </div>
    </div>
</div>
