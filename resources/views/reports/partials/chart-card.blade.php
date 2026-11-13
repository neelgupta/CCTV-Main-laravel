@props([
    'title',
    'subtitle' => null,
    'badge' => null,
    'config',
    'height' => 'h-72',
])

<div
    class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur"
    x-data="reportChart(@js($config))"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            @if ($subtitle)
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">{{ $subtitle }}</p>
            @endif
            <h3 class="{{ $subtitle ? 'mt-1' : '' }} text-lg font-bold text-slate-950">{{ $title }}</h3>
        </div>
        @if ($badge)
            <span class="shrink-0 rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-200">{{ $badge }}</span>
        @endif
    </div>

    <div class="relative mt-5 {{ $height }}">
        <div
            x-show="loading"
            x-transition.opacity
            class="absolute inset-0 flex items-center justify-center rounded-2xl bg-slate-50/80"
        >
            <div class="flex flex-col items-center gap-3">
                <div class="h-9 w-9 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500"></div>
                <p class="text-xs font-medium text-slate-500">Loading chart...</p>
            </div>
        </div>

        <div
            x-show="!loading && empty"
            x-transition.opacity
            class="absolute inset-0 flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 text-center"
            style="display: none;"
        >
            <svg class="mb-3 h-10 w-10 text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <path d="M4 19h16M6 16l3-4 3 3 4-6 2 3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <p class="text-sm font-semibold text-slate-600">No data available</p>
            <p class="mt-1 text-xs text-slate-500">Try adjusting your filters or date range.</p>
        </div>

        <canvas
            x-show="!loading && !empty"
            x-ref="canvas"
            class="max-h-full w-full"
            style="display: none;"
        ></canvas>
    </div>
</div>
