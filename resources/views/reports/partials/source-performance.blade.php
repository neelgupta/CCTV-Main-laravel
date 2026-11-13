@php
    $sourceChart = $salesReport['source_chart'];
    $bestSource = $salesReport['summary']['best_source'];
@endphp

<div
    class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur"
    x-data="reportChart(@js($sourceChart['chart']))"
>
    <div class="flex items-start justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Lead Source Conversion</p>
            <h3 class="mt-1 text-lg font-bold text-slate-950">Source Performance</h3>
        </div>
        @if ($bestSource)
            <span class="shrink-0 text-xs text-slate-500">
                Best: <span class="font-semibold text-slate-700">{{ $bestSource['label'] }}</span>
            </span>
        @endif
    </div>

    @if ($sourceChart['empty'])
        <div class="mt-5 flex flex-col items-center justify-center rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-6 py-14 text-center">
            <p class="text-sm font-semibold text-slate-600">No source data available</p>
            <p class="mt-1 text-xs text-slate-500">Try adjusting your filters or date range.</p>
        </div>
    @else
        <div class="mt-5 grid gap-5 sm:grid-cols-[170px_1fr] sm:items-center">
            <div class="flex justify-center">
                <div class="relative h-[9.5rem] w-[9.5rem]">
                    <div
                        x-show="loading"
                        class="absolute inset-0 flex items-center justify-center"
                    >
                        <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500"></div>
                    </div>
                    <canvas
                        x-show="!loading"
                        x-ref="canvas"
                        class="h-full w-full"
                        style="display: none;"
                    ></canvas>
                    <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center z-0">
                        <div class="text-xl font-extrabold text-slate-950">{{ number_format($sourceChart['total']) }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Source Units</div>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                @foreach ($sourceChart['rows'] as $row)
                    <div class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="h-3 w-3 shrink-0 rounded-full" style="background-color: {{ $row['color'] }};"></span>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-slate-900">{{ $row['label'] }}</div>
                                <div class="text-xs text-slate-500">{{ $row['conversion_rate'] }}% conversion</div>
                            </div>
                        </div>
                        <div class="shrink-0 text-sm font-extrabold text-slate-950">{{ $row['count'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
