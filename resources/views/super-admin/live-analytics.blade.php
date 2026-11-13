<x-app-layout>
    @php
        $toneClasses = [
            'sky' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'emerald' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'amber' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'rose' => 'bg-rose-50 text-rose-700 ring-rose-200',
            'violet' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'slate' => 'bg-slate-100 text-slate-700 ring-slate-200',
        ];
    @endphp

    <x-slot name="header">
        <div class="rounded-[2rem] border border-white/70 bg-white/85 p-5 shadow-xl shadow-slate-950/5 backdrop-blur">
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-cyan-700">Platform Intelligence</p>
            <h2 class="mt-1 text-2xl font-extrabold text-slate-950">Live Analytics Dashboard</h2>
            <p class="mt-1 text-sm text-slate-500">Real-time visibility across users, companies, leads, projects, and activity.</p>
        </div>
    </x-slot>

    <div
        class="space-y-6 py-2"
        x-data="liveAnalyticsDashboard({
            dataUrl: @js(route('superadmin.live-analytics.data')),
            initialData: @js($initialData),
            filters: @js($filters),
            pollInterval: 20000,
        })"
        x-init="init()"
    >
        <div class="rounded-[1.75rem] border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div class="mb-4 flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-xs text-slate-500" x-show="lastUpdated" x-text="'Last updated: ' + lastUpdated"></p>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                    <label class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">
                        <input type="checkbox" class="rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" x-model="autoRefresh" @change="toggleAutoRefresh()">
                        Auto refresh (20s)
                    </label>
                    <button
                        type="button"
                        @click="refresh()"
                        :disabled="loading"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <span x-show="loading" class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-700"></span>
                        <span x-text="loading ? 'Refreshing...' : 'Refresh now'"></span>
                    </button>
                </div>
            </div>

            <form method="GET" action="{{ route('superadmin.live-analytics') }}" class="grid gap-4 md:grid-cols-3">
                <div>
                    <label for="analytics-period" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Date Range</label>
                    <select id="analytics-period" name="period" class="block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        <option value="today" @selected($filters['period'] === 'today')>Today</option>
                        <option value="weekly" @selected($filters['period'] === 'weekly')>This Week</option>
                        <option value="monthly" @selected($filters['period'] === 'monthly')>This Month</option>
                    </select>
                </div>
                <div>
                    <label for="analytics-company" class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Company</label>
                    <select id="analytics-company" name="company_id" class="block w-full rounded-2xl border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm focus:border-slate-400 focus:outline-none focus:ring-0">
                        <option value="">All companies</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) $filters['company_id'] === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-3">
                    <a href="{{ route('superadmin.live-analytics') }}" class="inline-flex flex-1 items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Apply</button>
                </div>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <template x-for="card in kpiCards" :key="card.label">
                <div class="rounded-2xl px-5 py-4 ring-1" :class="card.toneClass">
                    <div class="text-[10px] font-bold uppercase tracking-[0.16em]" x-text="card.label"></div>
                    <div class="mt-1 text-3xl font-extrabold text-slate-950" x-text="card.value"></div>
                    <div class="mt-1 text-xs text-slate-500" x-text="card.meta"></div>
                </div>
            </template>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Activity Trend</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">Actions Over Time</h3>
                <div class="mt-5 h-72">
                    <canvas x-ref="activityLineChart"></canvas>
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">User Roles</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">Role Distribution</h3>
                <div class="mt-5 h-72">
                    <canvas x-ref="rolePieChart"></canvas>
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Lead Pipeline</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">Leads By Status</h3>
                <div class="mt-5 h-72">
                    <canvas x-ref="leadStatusPieChart"></canvas>
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Lead Sources</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">Top Lead Sources</h3>
                <div class="mt-5 h-72">
                    <canvas x-ref="leadSourceBarChart"></canvas>
                </div>
            </div>
        </div>

        <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Projects</p>
            <h3 class="mt-1 text-lg font-bold text-slate-950">Company-Wise Project Volume</h3>
            <div class="mt-5 h-80">
                <canvas x-ref="companyProjectsBarChart"></canvas>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1fr_1fr]">
            <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Online Now</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">Recently Active Users</h3>
                <div class="mt-5 space-y-3">
                    <template x-if="recentUsers.length === 0">
                        <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-10 text-center text-sm text-slate-500">No users are currently online.</div>
                    </template>
                    <template x-for="user in recentUsers" :key="user.id">
                        <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-slate-900" x-text="user.name"></div>
                                <div class="truncate text-xs text-slate-500" x-text="user.company_name + ' • ' + user.role"></div>
                            </div>
                            <span class="shrink-0 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200" x-text="user.last_active_label"></span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Module Usage</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">Platform Activity Mix</h3>
                <div class="mt-5 space-y-4">
                    <template x-for="module in moduleUsage" :key="module.module">
                        <div>
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-semibold text-slate-900" x-text="module.module"></span>
                                <span class="text-slate-500" x-text="module.count"></span>
                            </div>
                            <div class="h-3 rounded-full bg-slate-100">
                                <div class="h-3 rounded-full bg-gradient-to-r from-cyan-500 to-indigo-500" :style="'width:' + modulePercent(module.count) + '%'"></div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
            <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur h-[31rem]">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Live Feed</p>
                <h3 class="mt-1 text-lg font-bold text-slate-950">User Activity Stream</h3>
                <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200 h-[24rem]">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-500">User</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-500">Company</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-500">Module</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-500">Action</th>
                                <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-500">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <template x-if="activityFeed.length === 0">
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No activity recorded yet for the selected filters.</td>
                                </tr>
                            </template>
                            <template x-for="(item, index) in activityFeed" :key="item.occurred_at + '-' + index">
                                <tr>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-900" x-text="item.user_name"></div>
                                        <div class="text-xs text-slate-500" x-text="item.description"></div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700" x-text="item.company_name"></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 ring-1 ring-sky-200" x-text="item.module"></span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700" x-text="item.action"></td>
                                    <td class="px-4 py-3 text-slate-500" x-text="item.occurred_label"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="space-y-6">
                <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Companies</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-950">Highest Activity</h3>
                    <div class="mt-5 space-y-3">
                        <template x-for="company in topCompanies" :key="company.company_id">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="font-semibold text-slate-900" x-text="company.company_name"></div>
                                    <span class="text-sm font-bold text-cyan-700" x-text="company.activity_score"></span>
                                </div>
                                <div class="mt-1 text-xs text-slate-500" x-text="company.leads + ' leads • ' + company.projects + ' projects • ' + company.actions + ' actions'"></div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Projects</p>
                    <h3 class="mt-1 text-lg font-bold text-slate-950">Status Breakdown</h3>
                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-sky-50 px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">Ongoing</div>
                            <div class="mt-2 text-2xl font-extrabold text-sky-950" x-text="projectStatus.ongoing ?? 0"></div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-emerald-50 px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Completed</div>
                            <div class="mt-2 text-2xl font-extrabold text-emerald-950" x-text="projectStatus.completed ?? 0"></div>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-rose-50 px-4 py-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-700">Delayed</div>
                            <div class="mt-2 text-2xl font-extrabold text-rose-950" x-text="projectStatus.delayed ?? 0"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('liveAnalyticsDashboard', (config) => ({
                    dataUrl: config.dataUrl,
                    filters: config.filters,
                    pollInterval: config.pollInterval,
                    autoRefresh: true,
                    loading: false,
                    lastUpdated: '',
                    pollTimer: null,
                    payload: config.initialData,
                    charts: {},
                    kpiCards: [],
                    recentUsers: [],
                    moduleUsage: [],
                    activityFeed: [],
                    topCompanies: [],
                    projectStatus: {},
                    moduleMax: 1,

                    init() {
                        this.applyPayload(this.payload);
                        this.$nextTick(() => this.renderCharts());

                        if (this.autoRefresh) {
                            this.startPolling();
                        }
                    },

                    startPolling() {
                        this.stopPolling();
                        this.pollTimer = window.setInterval(() => this.refresh(false), this.pollInterval);
                    },

                    stopPolling() {
                        if (this.pollTimer) {
                            window.clearInterval(this.pollTimer);
                            this.pollTimer = null;
                        }
                    },

                    toggleAutoRefresh() {
                        if (this.autoRefresh) {
                            this.startPolling();
                        } else {
                            this.stopPolling();
                        }
                    },

                    async refresh(showLoader = true) {
                        if (showLoader) {
                            this.loading = true;
                        }

                        try {
                            const params = new URLSearchParams();
                            if (this.filters.period) params.set('period', this.filters.period);
                            if (this.filters.company_id) params.set('company_id', this.filters.company_id);

                            const response = await fetch(this.dataUrl + '?' + params.toString(), {
                                headers: {
                                    'Accept': 'application/json',
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                            });

                            if (!response.ok) {
                                throw new Error('Failed to refresh analytics data.');
                            }

                            this.payload = await response.json();
                            this.applyPayload(this.payload);
                            this.updateCharts();
                        } catch (error) {
                            console.error(error);
                        } finally {
                            this.loading = false;
                        }
                    },

                    applyPayload(payload) {
                        this.lastUpdated = new Date(payload.generated_at).toLocaleString();
                        this.recentUsers = payload.activity?.recent_users ?? [];
                        this.moduleUsage = payload.activity?.module_usage ?? [];
                        this.activityFeed = payload.activity?.feed ?? [];
                        this.topCompanies = payload.companies?.top_activity ?? [];
                        this.projectStatus = payload.projects?.by_status ?? {};
                        this.moduleMax = Math.max(1, ...this.moduleUsage.map((row) => row.count));

                        this.kpiCards = [
                            {
                                label: 'Total Users',
                                value: payload.users?.total ?? 0,
                                meta: (payload.users?.online ?? 0) + ' online • ' + (payload.users?.inactive ?? 0) + ' inactive',
                                toneClass: 'bg-sky-50 text-sky-700 ring-sky-200',
                            },
                            {
                                label: 'Active Companies',
                                value: payload.companies?.active ?? 0,
                                meta: (payload.companies?.total ?? 0) + ' total companies',
                                toneClass: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                            },
                            {
                                label: 'Total Leads',
                                value: payload.leads?.total ?? 0,
                                meta: (payload.leads?.by_status?.won ?? 0) + ' won • ' + (payload.leads?.by_status?.lost ?? 0) + ' lost',
                                toneClass: 'bg-violet-50 text-violet-700 ring-violet-200',
                            },
                            {
                                label: 'Total Projects',
                                value: payload.projects?.total ?? 0,
                                meta: (payload.projects?.by_status?.ongoing ?? 0) + ' ongoing • ' + (payload.projects?.by_status?.delayed ?? 0) + ' delayed',
                                toneClass: 'bg-amber-50 text-amber-700 ring-amber-200',
                            },
                        ];
                    },

                    modulePercent(count) {
                        return Math.max(8, Math.round((count / this.moduleMax) * 100));
                    },

                    renderCharts() {
                        if (typeof Chart === 'undefined') {
                            return;
                        }

                        const charts = this.payload.charts ?? {};

                        this.charts.activityLine = this.createChart(this.$refs.activityLineChart, 'line', charts.activity_line ?? { labels: [], values: [] }, {
                            tension: 0.35,
                            fill: true,
                        });
                        this.charts.rolePie = this.createChart(this.$refs.rolePieChart, 'doughnut', charts.role_pie ?? { labels: [], values: [] });
                        this.charts.leadStatusPie = this.createChart(this.$refs.leadStatusPieChart, 'doughnut', charts.lead_status_pie ?? { labels: [], values: [] });
                        this.charts.leadSourceBar = this.createChart(this.$refs.leadSourceBarChart, 'bar', charts.lead_source_bar ?? { labels: [], values: [] });
                        this.charts.companyProjectsBar = this.createChart(this.$refs.companyProjectsBarChart, 'bar', charts.company_projects_bar ?? { labels: [], values: [] }, null, true);
                    },

                    updateCharts() {
                        this.updateChart(this.charts.activityLine, this.payload.charts.activity_line);
                        this.updateChart(this.charts.rolePie, this.payload.charts.role_pie);
                        this.updateChart(this.charts.leadStatusPie, this.payload.charts.lead_status_pie);
                        this.updateChart(this.charts.leadSourceBar, this.payload.charts.lead_source_bar);
                        this.updateChart(this.charts.companyProjectsBar, this.payload.charts.company_projects_bar);
                    },

                    createChart(canvas, type, chartData, lineOptions = null, horizontal = false) {
                        if (!canvas) {
                            return null;
                        }

                        const colors = ['#06b6d4', '#6366f1', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#14b8a6', '#f97316'];

                        return new Chart(canvas.getContext('2d'), {
                            type,
                            data: {
                                labels: chartData.labels,
                                datasets: [{
                                    label: 'Count',
                                    data: chartData.values,
                                    backgroundColor: type === 'line'
                                        ? 'rgba(6, 182, 212, 0.15)'
                                        : colors,
                                    borderColor: type === 'line' ? '#06b6d4' : colors,
                                    borderWidth: type === 'line' ? 2 : 1,
                                    fill: type === 'line',
                                    tension: lineOptions?.tension ?? 0,
                                }],
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                indexAxis: horizontal ? 'y' : 'x',
                                plugins: {
                                    legend: {
                                        display: type === 'doughnut',
                                        position: 'bottom',
                                    },
                                },
                                scales: type === 'doughnut' ? {} : {
                                    x: {
                                        grid: { display: false },
                                    },
                                    y: {
                                        beginAtZero: true,
                                        ticks: { precision: 0 },
                                    },
                                },
                            },
                        });
                    },

                    updateChart(chart, chartData) {
                        if (!chart || !chartData) {
                            return;
                        }

                        chart.data.labels = chartData.labels;
                        chart.data.datasets[0].data = chartData.values;
                        chart.update('none');
                    },

                    destroy() {
                        this.stopPolling();
                        Object.values(this.charts).forEach((chart) => chart?.destroy());
                    },
                }));

            });
        </script>
    @endpush
</x-app-layout>
