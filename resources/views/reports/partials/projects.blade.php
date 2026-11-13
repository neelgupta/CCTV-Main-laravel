<section class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <div class="border border-slate-200 bg-white px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-500">Total Projects</div>
            <div class="mt-2 text-2xl font-extrabold text-slate-950">{{ $projectReport['summary']['total_projects'] }}</div>
        </div>
        <div class="border border-slate-200 bg-emerald-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">On Track</div>
            <div class="mt-2 text-2xl font-extrabold text-emerald-950">{{ $projectReport['summary']['on_track'] }}</div>
        </div>
        <div class="border border-slate-200 bg-amber-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Delayed</div>
            <div class="mt-2 text-2xl font-extrabold text-amber-950">{{ $projectReport['summary']['delayed'] }}</div>
        </div>
        <div class="border border-slate-200 bg-sky-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">Completed</div>
            <div class="mt-2 text-2xl font-extrabold text-sky-950">{{ $projectReport['summary']['completed'] }}</div>
        </div>
        <div class="border border-slate-200 bg-violet-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-violet-700">Due Today</div>
            <div class="mt-2 text-2xl font-extrabold text-violet-950">{{ $projectReport['summary']['due_today'] }}</div>
        </div>
        <div class="border border-slate-200 bg-rose-50 px-4 py-4 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-700">Inactive</div>
            <div class="mt-2 text-2xl font-extrabold text-rose-950">{{ $projectReport['summary']['inactive_projects'] }}</div>
        </div>
    </div>

    @include('reports.partials.chart-card', [
        'title' => 'Project Stage Bar Chart',
        'subtitle' => 'Projects by Stage',
        'badge' => $projectReport['summary']['total_projects'] . ' projects',
        'config' => $chartConfigs['project_stages'],
        'height' => 'h-80',
    ])

    <div class="grid gap-6 xl:grid-cols-[1fr_1.1fr]">
        <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Timeline / Gantt-style View</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-950">Project Progress Timeline</h3>
                </div>
                @if ($projectReport['timeline_bounds'])
                    <span class="text-xs text-slate-500">{{ $projectReport['timeline_bounds']['start'] }} to {{ $projectReport['timeline_bounds']['end'] }}</span>
                @endif
            </div>

            @if ($projectReport['timeline_rows']->isNotEmpty())
                <div class="mt-5 space-y-4">
                    @foreach ($projectReport['timeline_rows'] as $row)
                        <div class="border border-slate-200 bg-slate-50 px-4 py-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <a href="{{ $row['show_url'] }}" class="block truncate text-sm font-semibold text-slate-900 hover:text-indigo-700">{{ $row['name'] }}</a>
                                    <div class="mt-1 text-xs text-slate-500">{{ $row['reference_no'] }} • {{ $row['status_label'] }} • {{ $row['completion_percentage'] }}%</div>
                                </div>
                                <div class="text-xs text-slate-500">{{ $row['start_label'] }} to {{ $row['end_label'] }}</div>
                            </div>
                            <div class="relative mt-4 h-4 bg-slate-200">
                                <div class="absolute inset-y-0 opacity-25" style="left: {{ $row['offset_percent'] }}%; width: {{ $row['width_percent'] }}%; background-color: {{ $row['color'] }};"></div>
                                <div class="absolute inset-y-0" style="left: {{ $row['offset_percent'] }}%; width: {{ max(4, round($row['width_percent'] * ($row['completion_percentage'] / 100), 2)) }}%; background-color: {{ $row['color'] }};"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="mt-5 border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">Add start and end dates to projects to show the Gantt-style report.</div>
            @endif
        </div>
        <div class="border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Attention Needed</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-950">Inactive Projects</h3>
                </div>
                <span class="text-xs text-slate-500">No activity for {{ $inactiveProjectDays }} days</span>
            </div>
            <div class="mt-5 overflow-x-auto border border-slate-200">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Project</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Stage</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Last Activity</th>
                            <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Idle Days</th>
                            <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Open</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse ($projectReport['inactive_rows'] as $row)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900">{{ $row['name'] }}</div>
                                    <div class="text-xs text-slate-500">{{ $row['reference_no'] }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['stage_label'] }}</td>
                                <td class="px-4 py-3 text-slate-700">{{ $row['last_activity'] }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center px-3 py-1 text-xs font-semibold ring-1 {{ $row['days_idle'] >= 30 ? $toneClasses['rose'] : $toneClasses['amber'] }}">
                                        {{ $row['days_idle'] }} days
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ $row['show_url'] }}" class="inline-flex items-center border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No inactive projects found for the current filter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</section>
