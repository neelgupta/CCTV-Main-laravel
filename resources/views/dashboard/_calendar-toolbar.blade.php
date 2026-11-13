@php
    $calendarToolbarTitle = $calendarToolbarTitle ?? 'Activity';
    $calendarToolbarOverviewUrl = $calendarToolbarOverviewUrl ?? null;
    $calendarToolbarActionUrl = $calendarToolbarActionUrl ?? null;
    $calendarToolbarActionLabel = $calendarToolbarActionLabel ?? 'Expand calendar';
    $calendarToolbarShowLegend = $calendarToolbarShowLegend ?? true;
@endphp

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <h3 class="text-base font-bold text-slate-900 sm:text-lg">
        {{ $calendarToolbarTitle }}
    </h3>

    <div class="flex flex-wrap items-center gap-2">
        @if(filled($calendarToolbarOverviewUrl))
            <a
                href="{{ $calendarToolbarOverviewUrl }}"
                class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:bg-slate-50"
            >
                Overview
            </a>
        @endif

        <button
            type="button"
            id="open-calendar-task-modal"
            class="inline-flex items-center justify-center rounded-lg bg-[#C22032] px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#d63384]"
        >
            + Add Task
        </button>

        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button
                type="button"
                @click="open = !open"
                class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50"
                title="More options"
            >
                <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true">
                    <circle cx="12" cy="5" r="1.6" />
                    <circle cx="12" cy="12" r="1.6" />
                    <circle cx="12" cy="19" r="1.6" />
                </svg>
            </button>
            <div
                x-show="open"
                x-cloak
                x-transition
                class="absolute right-0 z-20 mt-2 w-52 rounded-lg border border-slate-200 bg-white py-1 shadow-lg"
            >
                @if(filled($calendarToolbarActionUrl))
                    <a
                        href="{{ $calendarToolbarActionUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        {{ $calendarToolbarActionLabel }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>

@if($calendarToolbarShowLegend)
    <div class="mb-3 flex flex-wrap items-center gap-2 text-[11px] font-semibold text-slate-600">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1">
            <span class="h-2 w-2 rounded-full bg-blue-600"></span> Lead
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1">
            <span class="h-2 w-2 rounded-full bg-emerald-600"></span> Project
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1">
            <span class="h-2 w-2 rounded-full bg-amber-500"></span> AMC
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1">
            <span class="h-2 w-2 rounded-full bg-teal-500"></span> Tickets
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1">
            <span class="h-2 w-2 rounded-full bg-rose-500"></span> Absent
        </span>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-50 px-2.5 py-1">
            <span class="h-2 w-2 rounded-full bg-violet-600"></span> Task
        </span>
    </div>
@endif
