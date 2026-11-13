<section class="space-y-6">
    <div class="max-w-full grid gap-6">
        @include('reports.partials.chart-card', [
            'title' => 'Monthly Lead Trends',
            'subtitle' => 'New Leads Over Time',
            'config' => $chartConfigs['monthly_trends'],
            'height' => 'h-52',
        ])
    </div>

    <div class="max-w-full grid gap-6 sm:grid-cols-1 xl:grid-cols-2">
        @include('reports.partials.pipeline-overview')
        @include('reports.partials.source-performance')
    </div>
</section>

<section class="space-y-6">
    <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <!-- <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Custom Field Graphs</p> -->
                <h3 class="mt-1 text-lg font-bold text-slate-950">Custom Field Graphs</h3>
            </div>
            @if (! empty($salesReport['custom_field_graphs']))
                <span class="shrink-0bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700 ring-1 ring-violet-200">
                    {{ count($salesReport['custom_field_graphs']) }} selected
                </span>
            @endif
        </div>

        @if (empty($salesReport['custom_field_graphs']))
            <div class="mt-5 border border-dashed border-slate-200 bg-slate-50 px-6 py-10 text-center">
                <p class="text-sm font-semibold text-slate-700">No custom field graphs selected</p>
                <p class="mt-1 text-xs text-slate-500">Click the field picker icon in the header to add dropdown custom fields here.</p>
            </div>
        @else
            <div class="mt-5 grid gap-6 xl:grid-cols-2">
                @foreach ($salesReport['custom_field_graphs'] as $graph)
                    @include('reports.partials.chart-card', [
                        'title' => $graph['label'],
                        'subtitle' => $graph['chart_type'] === 'doughnut' ? 'Custom Field Doughnut' : 'Custom Field Breakdown',
                        'badge' => number_format($graph['total']) . ' leads',
                        'config' => $graph['chart'],
                        'height' => $graph['chart_type'] === 'doughnut' ? 'h-72' : 'h-80',
                    ])
                @endforeach
            </div>
        @endif
    </div>
</section>

@if (! empty($exportUrl))
    @include('reports.partials.leads-export-script')
@endif
