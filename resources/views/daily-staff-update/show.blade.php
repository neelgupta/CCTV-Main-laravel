<x-app-layout>
    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-950">Daily Update Report</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        @if($report)
                            Read-only report generated for {{ $selectedDateHuman }}.
                        @else
                            No report has been generated for {{ $selectedDateHuman }}.
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('settings.company.index', ['tab' => 'daily-update-history']) }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">Back to History</a>
                    
                    @if($report)
                        <!-- Regenerate option for existing report -->
                        <form method="POST" action="{{ route('settings.daily-staff-update.generate') }}" class="inline-flex">
                            @csrf
                            <input type="hidden" name="report_date" value="{{ $selectedDate->toDateString() }}">
                            <input type="hidden" name="force_generate" value="1">
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50" title="Regenerate this date's report with current data">
                                <svg class="mr-1.5 h-4 w-4 text-slate-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                                Regenerate
                            </button>
                        </form>
                        <a href="{{ route('settings.daily-staff-update.download', $report) }}" class="inline-flex items-center justify-center rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-indigo-600/20 transition hover:bg-indigo-500">Download PDF</a>
                    @else
                        <!-- Generate button in header when report does not exist yet -->
                        <form method="POST" action="{{ route('settings.daily-staff-update.generate') }}" class="inline-flex">
                            @csrf
                            <input type="hidden" name="report_date" value="{{ $selectedDate->toDateString() }}">
                            <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-950/20 transition hover:bg-slate-800">
                                <svg class="mr-1.5 h-4 w-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                </svg>
                                Generate Report
                            </button>
                        </form>
                        <span class="inline-flex items-center justify-center rounded-2xl bg-indigo-600/40 px-5 py-2.5 text-sm font-semibold text-white cursor-not-allowed">Download PDF</span>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="rounded-[28px] border border-slate-200 bg-white p-4 shadow-sm lg:sticky lg:top-6 lg:h-[calc(100vh-3rem)]">
            <div class="flex items-start justify-between gap-3 border-b border-slate-200 pb-4">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Month</div>
                    <h3 class="mt-2 text-lg font-semibold text-slate-900">{{ $monthLabel }}</h3>
                    <p class="mt-1 text-sm text-slate-500">Browse the days in this month.</p>
                </div>
                <div class="flex items-center gap-2">
                    <a href="{{ $previousMonthUrl }}" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" aria-label="Previous month">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>
                    <a href="{{ $nextMonthUrl }}" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" aria-label="Next month">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                        </svg>
                    </a>
                </div>
            </div>

            <div class="mt-4 space-y-2 overflow-y-auto lg:max-h-[calc(100vh-12rem)] lg:pr-1">
                @foreach($monthDates as $dateItem)
                    @php
                        $dateKey = $dateItem->toDateString();
                        $isToday = $dateKey === $companyTodayDateKey;
                        $isSelected = $dateItem->isSameDay($selectedDate);
                        $hasReport = $monthReports->has($dateKey);

                        $itemClasses = $isSelected
                            ? 'border-slate-950 bg-slate-950 text-white shadow-lg shadow-slate-950/10'
                            : ($isToday
                                ? 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50');

                        $badgeClasses = $isSelected
                            ? 'border-white/20 bg-white/10 text-white'
                            : ($isToday
                                ? 'border-indigo-200 bg-white text-indigo-700'
                                : 'border-slate-200 bg-slate-50 text-slate-500');

                        $subTextClasses = $isSelected ? 'text-slate-300' : 'text-slate-500';
                    @endphp

                    <a
                        href="{{ route('settings.daily-staff-update.calendar', ['date' => $dateKey]) }}"
                        @class([
                            'group flex items-center gap-3 rounded-2xl border p-3 transition',
                            $itemClasses,
                        ])
                        @if($isSelected) aria-current="date" @endif
                    >
                        <div @class([
                            'flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-2xl border text-center',
                            $badgeClasses,
                        ])>
                            <span class="text-[10px] font-bold uppercase tracking-[0.18em]">{{ $dateItem->format('D') }}</span>
                            <span class="text-lg font-bold leading-none">{{ $dateItem->format('d') }}</span>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="truncate text-sm font-semibold">{{ $dateItem->format('j M Y') }}</span>
                                @if($isToday)
                                    <span @class([
                                        'inline-flex rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em]',
                                        $isSelected ? 'border-white/20 bg-white/10 text-white' : 'border-amber-200 bg-amber-50 text-amber-700',
                                    ])>Today</span>
                                @endif
                                @if($isSelected)
                                    <span class="inline-flex rounded-full border border-white/20 bg-white/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-[0.18em] text-white">Selected</span>
                                @endif
                            </div>
                            <p class="mt-1 text-xs {{ $subTextClasses }}">
                                {{ $hasReport ? 'Report available' : 'No report generated' }}
                            </p>
                        </div>

                        <span @class([
                            'h-2.5 w-2.5 shrink-0 rounded-full',
                            $isSelected ? 'bg-white' : ($hasReport ? 'bg-emerald-500' : 'bg-slate-300'),
                        ])></span>
                    </a>
                @endforeach
            </div>
        </aside>

        <div class="space-y-6">
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
            @endif
            @if(session('info'))
                <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">{{ session('info') }}</div>
            @endif

            @if($report)
                @include('daily-staff-update.partials.report-content', [
                    'report' => $report,
                    'reportData' => $reportData,
                ])
            @else
                <div class="flex min-h-[28rem] items-center justify-center rounded-[28px] border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <div class="max-w-md">
                        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                            <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m-9 4h12a2 2 0 002-2V7l-5-5H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <p class="mt-4 text-base font-semibold text-slate-900">Staff report has not been generated for this date.</p>
                        <p class="mt-1 text-xs text-slate-500">You can generate a report for {{ $selectedDateHuman }} now using historical data recorded on that day.</p>
                        
                        <div class="mt-6 flex justify-center">
                            <form method="POST" action="{{ route('settings.daily-staff-update.generate') }}">
                                @csrf
                                <input type="hidden" name="report_date" value="{{ $selectedDate->toDateString() }}">
                                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-950 px-6 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-950/20 transition hover:bg-slate-800">
                                    <svg class="h-4 w-4 text-slate-300" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
                                    </svg>
                                    <span>Generate Report for {{ $selectedDateHuman }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
