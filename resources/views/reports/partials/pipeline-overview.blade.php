<div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
    
        <div class="rounded-2xl p-4">
            <div class="flex items-start justify-between gap-3">
            
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Pipeline Overview</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-950">Lead Status & Conversion</h3>
                </div>
                <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                    {{ $salesReport['summary']['conversion_rate'] }}% converted
                </span>
            </div>
    
            <div class="relative mt-4 h-56">
                <div
                    class="h-full"
                    x-data="reportChart(@js($chartConfigs['leads_by_status']))"
                >
                    <div
                        x-show="loading"
                        class="absolute inset-0 flex items-center justify-center"
                    >
                        <div class="h-8 w-8 animate-spin rounded-full border-2 border-slate-200 border-t-cyan-500"></div>
                    </div>
                    <div
                        x-show="!loading && empty"
                        class="absolute inset-0 flex items-center justify-center text-center text-sm text-slate-500"
                        style="display: none;"
                    >
                        No status data available.
                    </div>
                    <canvas x-show="!loading && !empty" x-ref="canvas" class="max-h-full w-full" style="display: none;"></canvas>
                    <div x-show="!loading && !empty" class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center text-center z-0">
                        <div class="text-xl font-extrabold text-slate-950">{{ number_format($salesReport['summary']['total_leads']) }}</div>
                        <div class="mt-1 text-[11px] font-semibold uppercase tracking-wide text-slate-500">Total Leads</div>
                    </div>
                </div>
            </div>
        </div>
        
</div>
