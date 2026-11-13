<x-app-layout>
    @php
        $user = Auth::user();
    @endphp

    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Dashboard</h2>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h3 class="text-2xl font-semibold text-slate-950">Welcome back, {{ $user->name }}!</h3>
                    <p class="text-sm text-slate-500">Here's what's happening with your customers today.</p>
                </div>
            </div>
        </div>

        <section class="rounded-2xl bg-[#141b2d] px-5 py-5 text-white shadow-lg shadow-slate-950/10">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <div class="text-lg font-bold">Super Admin Portal</div>
                    <div class="text-sm text-slate-300">Quick access to global admin tools.</div>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route('super-admin.companies.create') }}"
                        class="inline-flex items-center rounded-2xl bg-indigo-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-indigo-400 shadow-sm">
                        + Register Company
                    </a>
                    <a href="{{ route('super-admin.locations') }}"
                        class="inline-flex items-center rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300">
                        Manage States & Cities
                    </a>
                </div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Dashboard Insight</p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-950">Company App Usage</h3>
                    <p class="mt-1 text-sm text-slate-500">Monitor which companies are active on the mobile app and how their staff are using it.</p>
                </div>
                <a href="{{ route('super-admin.company-app-usage.index') }}" class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                    View Full Report
                </a>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl bg-sky-50 p-4 ring-1 ring-sky-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-sky-700">Total Companies</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['total_companies'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-emerald-700">Companies Using the App</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['companies_using_app'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-rose-50 p-4 ring-1 ring-rose-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-rose-700">Companies Not Using the App</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['companies_not_using_app'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-amber-50 p-4 ring-1 ring-amber-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Total Staff</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['total_staff'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-cyan-50 p-4 ring-1 ring-cyan-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-cyan-700">Total Staff Using the App</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['total_staff_using_app'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-violet-50 p-4 ring-1 ring-violet-100">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-violet-700">Active App Users Today</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['active_app_users_today'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-slate-100 p-4 ring-1 ring-slate-200">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-700">Active in Last 7 Days</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['active_app_users_7_days'] ?? 0 }}</div>
                </div>
                <div class="rounded-2xl bg-slate-100 p-4 ring-1 ring-slate-200">
                    <div class="text-[11px] font-semibold uppercase tracking-[0.16em] text-slate-700">Active in Last 30 Days</div>
                    <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $companyAppUsageStats['active_app_users_30_days'] ?? 0 }}</div>
                </div>
            </div>

            <div class="mt-8 overflow-x-auto rounded-2xl border border-slate-200">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Company</th>
                            <th class="px-4 py-3 text-left">Plan</th>
                            <th class="px-4 py-3 text-left">Status</th>
                            <th class="px-4 py-3 text-left">Active Today</th>
                            <th class="px-4 py-3 text-left">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($companyAppUsageHighlights ?? [] as $company)
                            <tr>
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-950">{{ $company->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $company->company_code }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700">{{ $company->latestSubscription?->plan?->name ?? '-' }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ ($company->latestSubscription?->status ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : (($company->latestSubscription?->status ?? '') === 'trialing' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700') }}">
                                        {{ str($company->latestSubscription?->status ?? 'none')->headline() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-semibold text-slate-950">{{ $company->active_today ?? 0 }}</td>
                                <td class="px-4 py-3 text-slate-700">
                                    {{ filled($company->last_app_activity) ? \Illuminate\Support\Carbon::parse($company->last_app_activity)->format('d M Y, h:i A') : '-' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No company app usage data found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-app-layout>
