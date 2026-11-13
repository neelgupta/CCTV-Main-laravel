@php
    $staffRows = collect($reportData['staff'] ?? []);
    $reportDateHuman = $reportData['date_human'] ?? optional($report?->report_date)->format('d/m/Y');
    $summaryMessage = $reportData['summary_message'] ?? null;
    $totalLeadActivities = (int) ($reportData['lead_activity_count'] ?? 0);

    $reportText = $report?->report_text ?: '';
@endphp

<div class="space-y-6" x-data="{ activeTab: 'overview' }">
    <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Daily Staff Update Report</div>
                <h3 class="mt-2 text-lg font-semibold text-slate-900">Date: {{ $reportDateHuman }}</h3>
                <p class="mt-1 text-xs text-slate-500">
                    Generated {{ optional($report?->generated_at)->format('d M Y, h:i A') ?? optional($report?->created_at)->format('d M Y, h:i A') }}
                    @if(filled($reportData['generated_by']['name'] ?? null))
                        by {{ $reportData['generated_by']['name'] }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <!-- Navigation Tabs inside Header Box -->
                <div class="inline-flex rounded-2xl border border-slate-200 bg-slate-100/80 p-1.5 shadow-inner">
                    <button type="button"
                        @click="activeTab = 'overview'"
                        :class="activeTab === 'overview'
                            ? 'bg-white text-slate-900 shadow-sm'
                            : 'text-slate-500 hover:text-slate-900'"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                        </svg>
                        <span>Full Report</span>
                    </button>
                    <button type="button"
                        @click="activeTab = 'lead_activities'"
                        :class="activeTab === 'lead_activities'
                            ? 'bg-rose-600 text-white shadow-sm shadow-rose-600/30'
                            : 'text-slate-500 hover:text-slate-900'"
                        class="flex items-center gap-2 rounded-xl px-4 py-2 text-xs font-bold transition">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Lead Activities</span>
                        @if($totalLeadActivities > 0)
                            <span :class="activeTab === 'lead_activities' ? 'bg-white/25 text-white' : 'bg-rose-100 text-rose-700'" class="ml-1 rounded-full px-2 py-0.5 text-[10px] font-extrabold">
                                {{ $totalLeadActivities }}
                            </span>
                        @endif
                    </button>
                </div>

                <!-- Summary Badges -->
                <div class="flex gap-2">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Staff</div>
                        <div class="mt-0.5 text-lg font-bold text-slate-950">{{ number_format((int) ($reportData['staff_count'] ?? $staffRows->count())) }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Projects</div>
                        <div class="mt-0.5 text-lg font-bold text-slate-950">{{ number_format((int) ($reportData['project_count'] ?? 0)) }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3.5 py-2.5 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-slate-400">Tickets</div>
                        <div class="mt-0.5 text-lg font-bold text-slate-950">{{ number_format((int) ($reportData['ticket_count'] ?? 0)) }}</div>
                    </div>
                    <div class="rounded-2xl border border-rose-200 bg-rose-50/50 px-3.5 py-2.5 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-[0.16em] text-rose-500">Lead Activities</div>
                        <div class="mt-0.5 text-lg font-bold text-rose-700">{{ number_format($totalLeadActivities) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 1: FULL REPORT PREVIEW (ATTRACTIVE REPORT VIEW) -->
    <div x-show="activeTab === 'overview'" class="space-y-6" x-data="{ viewMode: 'visual' }">
        <div class="flex flex-wrap items-center justify-between gap-3 px-1">
            <div>
                <h4 class="text-base font-bold text-slate-900">Comprehensive Staff Operations Summary</h4>
                <p class="text-xs text-slate-500">Breakdown of assigned projects, tasks, support tickets, and lead interactions.</p>
            </div>
            <!-- Toggle between Visual Report and Raw Monospace Text -->
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                <button type="button"
                    @click="viewMode = 'visual'"
                    :class="viewMode === 'visual' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                    <span>Executive View</span>
                </button>
                <button type="button"
                    @click="viewMode = 'raw'"
                    :class="viewMode === 'raw' ? 'bg-slate-900 text-white shadow-sm' : 'text-slate-600 hover:text-slate-900'"
                    class="flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-bold transition">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                    <span>Raw Text View</span>
                </button>
            </div>
        </div>

        <!-- VISUAL EXECUTIVE REPORT VIEW -->
        <div x-show="viewMode === 'visual'" class="space-y-6">
            @forelse($staffRows as $staff)
                @php
                    $projects = collect($staff['projects'] ?? []);
                    $tickets = collect($staff['support_tickets'] ?? []);
                    $activities = collect($staff['lead_activities'] ?? []);

                    $totalTasks = $projects->sum(fn($p) => count($p['tasks'] ?? []));
                @endphp
                <div class="overflow-hidden rounded-[26px] border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
                    <!-- Staff Header Card -->
                    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-100 bg-gradient-to-r from-slate-50 via-white to-indigo-50/20 px-6 py-4">
                        <div class="flex items-center gap-3.5">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 font-extrabold text-white shadow-md shadow-indigo-600/20">
                                {{ strtoupper(mb_substr($staff['name'] ?? 'S', 0, 2)) }}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <h4 class="text-base font-bold text-slate-950">{{ $staff['name'] ?? 'Staff Member' }}</h4>
                                    @if(filled($staff['employee_id'] ?? null))
                                        <span class="rounded-md border border-slate-200 bg-white px-2 py-0.5 text-[10px] font-bold text-slate-600">{{ $staff['employee_id'] }}</span>
                                    @endif
                                </div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                    <span>{{ $projects->count() }} {{ Str::plural('Project', $projects->count()) }}</span>
                                    <span>•</span>
                                    <span>{{ $totalTasks }} {{ Str::plural('Task', $totalTasks) }}</span>
                                    <span>•</span>
                                    <span>{{ $tickets->count() }} {{ Str::plural('Ticket', $tickets->count()) }}</span>
                                    <span>•</span>
                                    <span>{{ $activities->count() }} Lead {{ Str::plural('Activity', $activities->count()) }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Mini Pills for Staff Metrics -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                <svg class="h-3.5 w-3.5 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" /></svg>
                                <span>{{ $projects->count() }} Projects</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                <svg class="h-3.5 w-3.5 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                                <span>{{ $tickets->count() }} Tickets</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700">
                                <svg class="h-3.5 w-3.5 text-rose-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                <span>{{ $activities->count() }} Lead Activities</span>
                            </span>
                        </div>
                    </div>

                    <!-- Inner Staff Content Sections -->
                    <div class="p-6 space-y-6">
                        <!-- Projects & Tasks Section -->
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h5 class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-slate-400">
                                    <span class="h-2 w-2 rounded-full bg-indigo-500"></span>
                                    <span>Assigned Projects & Tasks</span>
                                </h5>
                                <span class="text-xs font-semibold text-slate-500">{{ $projects->count() }} Projects</span>
                            </div>

                            @if($projects->isEmpty())
                                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 text-xs font-medium text-slate-500">
                                    No assigned projects found for this staff member.
                                </div>
                            @else
                                <div class="space-y-3">
                                    @foreach($projects as $project)
                                        <div class="rounded-2xl border border-slate-200/80 bg-slate-50/40 p-4 transition hover:bg-slate-50">
                                            <div class="flex items-center justify-between gap-3">
                                                <div class="flex items-center gap-2">
                                                    <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-indigo-100 text-indigo-700">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z" /></svg>
                                                    </div>
                                                    <span class="text-sm font-bold text-slate-900">{{ $project['name'] ?? 'Project' }}</span>
                                                </div>
                                                <span class="text-xs font-semibold text-slate-500">
                                                    {{ count($project['tasks'] ?? []) }} {{ Str::plural('task', count($project['tasks'] ?? [])) }}
                                                </span>
                                            </div>

                                            @if(empty($project['tasks']))
                                                <div class="mt-2 text-xs italic text-slate-400">No active tasks in this project.</div>
                                            @else
                                                <div class="mt-3 divide-y divide-slate-100 rounded-xl border border-slate-200/60 bg-white">
                                                    @foreach($project['tasks'] as $task)
                                                        @php
                                                            $color = $task['status_color'] ?? 'amber';
                                                            $statusBadge = match($color) {
                                                                'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                                'blue' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                                'rose' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                                default => 'bg-amber-50 text-amber-700 border-amber-200',
                                                            };
                                                        @endphp
                                                        <div class="flex flex-wrap items-center justify-between gap-2 px-3.5 py-2.5 text-xs">
                                                            <div class="flex items-center gap-2">
                                                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                                                <span class="font-medium text-slate-800">{{ $task['name'] ?? 'Task' }}</span>
                                                                @if(!empty($task['completed_date']))
                                                                    <span class="text-[11px] text-slate-400">| Completed: {{ $task['completed_date'] }}</span>
                                                                @endif
                                                            </div>
                                                            <span class="inline-flex items-center rounded-lg border px-2 py-0.5 text-[10px] font-bold {{ $statusBadge }}">
                                                                {{ $task['status_label'] ?? 'Pending' }}
                                                            </span>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Support Tickets Section -->
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h5 class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-slate-400">
                                    <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                    <span>Support Tickets (Visits / Assigned)</span>
                                </h5>
                                <span class="text-xs font-semibold text-slate-500">{{ $tickets->count() }} Tickets</span>
                            </div>

                            @if($tickets->isEmpty())
                                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 text-xs font-medium text-slate-500">
                                    No support tickets assigned for this date.
                                </div>
                            @else
                                <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200/80 bg-white">
                                    @foreach($tickets as $ticket)
                                        @php
                                            $tColor = $ticket['status_color'] ?? 'slate';
                                            $tBadge = match($tColor) {
                                                'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'blue' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'rose' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                                            };
                                        @endphp
                                        <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 transition hover:bg-slate-50/70">
                                            <div class="flex items-center gap-2.5">
                                                <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" /></svg>
                                                </div>
                                                <div>
                                                    <div class="text-xs font-bold text-slate-900">{{ $ticket['name'] ?? 'Ticket' }}</div>
                                                    @if(!empty($ticket['ticket_number']))
                                                        <div class="text-[10px] text-slate-400">#{{ $ticket['ticket_number'] }}</div>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-[11px] font-bold {{ $tBadge }}">
                                                {{ $ticket['status_label'] ?? 'Open' }}
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Lead Activities Section -->
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h5 class="flex items-center gap-2 text-xs font-black uppercase tracking-[0.2em] text-slate-400">
                                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                    <span>Lead Activities & Interactions</span>
                                </h5>
                                <span class="text-xs font-semibold text-slate-500">{{ $activities->count() }} Activities</span>
                            </div>

                            @if($activities->isEmpty())
                                <div class="rounded-2xl border border-slate-100 bg-slate-50/70 px-4 py-3 text-xs font-medium text-slate-500">
                                    No lead activities logged by this staff member on this date.
                                </div>
                            @else
                                <div class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200/80 bg-white">
                                    @foreach($activities as $activity)
                                        @php
                                            $actColor = $activity['type_color'] ?? 'slate';
                                            $actBadge = match($actColor) {
                                                'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                                'blue' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'purple' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                'rose' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                                            };
                                        @endphp
                                        <div class="flex flex-wrap items-center justify-between gap-3 p-3.5 transition hover:bg-slate-50/70">
                                            <div class="flex items-start gap-3">
                                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-600 mt-0.5">
                                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>
                                                </div>
                                                <div>
                                                    <div class="flex flex-wrap items-center gap-2">
                                                        @if(!empty($activity['lead_id']))
                                                            <a href="{{ route('leads.show', $activity['lead_key'] ?? $activity['lead_id']) }}" target="_blank" class="text-xs font-bold text-slate-900 transition hover:text-indigo-600 hover:underline">
                                                                {{ $activity['lead_name'] }}
                                                            </a>
                                                        @else
                                                            <span class="text-xs font-bold text-slate-900">{{ $activity['lead_name'] }}</span>
                                                        @endif
                                                        <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-[10px] font-bold {{ $actBadge }}">
                                                            {{ $activity['type_label'] ?? 'Activity' }}
                                                        </span>
                                                        @if(!empty($activity['time']))
                                                            <span class="text-[11px] text-slate-400">• {{ $activity['time'] }}</span>
                                                        @endif
                                                    </div>
                                                    <p class="mt-1 text-xs font-medium leading-relaxed text-slate-700">
                                                        {{ $activity['description'] }}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-[28px] border border-slate-200 bg-white p-12 text-center shadow-sm">
                    <p class="text-sm font-semibold text-slate-500">No staff records available for this date.</p>
                </div>
            @endforelse
        </div>

        <!-- RAW TEXT VIEW (Matches generated document) -->
        <div x-show="viewMode === 'raw'" x-cloak class="rounded-[28px] border border-slate-200 bg-white p-8 shadow-sm">
            <div class="mb-4 flex items-center justify-between gap-4 border-b border-slate-200 pb-4">
                <div>
                    <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Stored Text Report Preview</div>
                    <p class="mt-1 text-sm text-slate-500">Direct courier / dot-matrix formatted text representation.</p>
                </div>
                @if(filled($summaryMessage))
                    <div class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                        {{ $summaryMessage }}
                    </div>
                @endif
            </div>

            <pre class="whitespace-pre-wrap break-words font-mono text-[14px] leading-7 text-slate-900">{{ $reportText }}</pre>
        </div>
    </div>

    <!-- TAB 2: ATTRACTIVE STAFF-WISE LEAD ACTIVITIES -->
    <div x-show="activeTab === 'lead_activities'" x-cloak class="space-y-6">
        @php
            $staffWithActivities = $staffRows->filter(fn($s) => !empty($s['lead_activities']))->values();
            $staffWithoutActivities = $staffRows->filter(fn($s) => empty($s['lead_activities']))->values();
        @endphp

        @if($totalLeadActivities === 0)
            <div class="rounded-[28px] border border-slate-200 bg-white p-12 text-center shadow-sm">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 text-slate-400">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h4 class="mt-4 text-base font-bold text-slate-900">No Lead Activities Recorded</h4>
                <p class="mt-1 text-sm text-slate-500">No staff members performed any lead updates, comments, or appointment actions on {{ $reportDateHuman }}.</p>
            </div>
        @else
            <div class="grid grid-cols-1 gap-6">
                @foreach($staffWithActivities as $staff)
                    @php
                        $activities = collect($staff['lead_activities'] ?? []);
                    @endphp
                    <div class="overflow-hidden rounded-[26px] border border-slate-200/90 bg-white shadow-sm transition hover:shadow-md">
                        <!-- Staff Card Header -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-gradient-to-r from-slate-50/90 via-white to-slate-50/50 px-6 py-4">
                            <div class="flex items-center gap-3">
                                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-600 text-sm font-black text-white shadow-md shadow-indigo-500/20">
                                    {{ strtoupper(mb_substr($staff['name'] ?? 'S', 0, 2)) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="text-base font-bold text-slate-900">{{ $staff['name'] ?? 'Staff Member' }}</h4>
                                        @if(filled($staff['employee_id'] ?? null))
                                            <span class="rounded-md bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-600">{{ $staff['employee_id'] }}</span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500">Recorded {{ $activities->count() }} lead {{ Str::plural('action', $activities->count()) }} on {{ $reportDateHuman }}</p>
                                </div>
                            </div>
                            <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-200 bg-rose-50 px-3.5 py-1 text-xs font-bold text-rose-700 shadow-sm">
                                <span class="h-2 w-2 rounded-full bg-rose-500 animate-pulse"></span>
                                {{ $activities->count() }} {{ Str::plural('Activity', $activities->count()) }}
                            </span>
                        </div>

                        <!-- Activities Table / List -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-100 text-left text-sm">
                                <thead>
                                    <tr class="bg-slate-50/60 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                        <th scope="col" class="py-3 pl-6 pr-3">Time</th>
                                        <th scope="col" class="px-4 py-3">Lead / Client</th>
                                        <th scope="col" class="px-4 py-3">Type</th>
                                        <th scope="col" class="py-3 pl-4 pr-6">Activity Details</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 text-slate-700">
                                    @foreach($activities as $act)
                                        @php
                                            $typeColor = $act['type_color'] ?? 'slate';
                                            $badgeClasses = match($typeColor) {
                                                'indigo' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                                'blue' => 'bg-blue-50 text-blue-700 border-blue-200',
                                                'amber' => 'bg-amber-50 text-amber-700 border-amber-200',
                                                'emerald' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                                'purple' => 'bg-purple-50 text-purple-700 border-purple-200',
                                                'rose' => 'bg-rose-50 text-rose-700 border-rose-200',
                                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                                            };
                                        @endphp
                                        <tr class="transition hover:bg-slate-50/70">
                                            <td class="whitespace-nowrap py-3.5 pl-6 pr-3 text-xs font-semibold text-slate-500">
                                                <div class="flex items-center gap-1.5">
                                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    <span>{{ $act['time'] ?: '--:--' }}</span>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5">
                                                @if(!empty($act['lead_id']))
                                                    <a href="{{ route('leads.show', $act['lead_key'] ?? $act['lead_id']) }}" target="_blank" class="font-bold text-slate-900 transition hover:text-indigo-600 hover:underline">
                                                        {{ $act['lead_name'] }}
                                                    </a>
                                                @else
                                                    <span class="font-medium text-slate-800">{{ $act['lead_name'] }}</span>
                                                @endif
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3.5">
                                                <span class="inline-flex items-center rounded-lg border px-2.5 py-1 text-[11px] font-bold {{ $badgeClasses }}">
                                                    {{ $act['type_label'] ?? 'Activity' }}
                                                </span>
                                            </td>
                                            <td class="py-3.5 pl-4 pr-6">
                                                <p class="text-sm font-medium leading-relaxed text-slate-900">
                                                    {{ $act['description'] }}
                                                </p>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach

                @if($staffWithoutActivities->isNotEmpty())
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 p-4">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-400">Other Staff (No lead activities on this date)</div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($staffWithoutActivities as $staffNoAct)
                                <span class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                                    {{ $staffNoAct['name'] }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
