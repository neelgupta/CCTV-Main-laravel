<x-app-layout>
    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">Company App Usage</h2>
            <p class="mt-1 text-sm text-slate-500">Track which companies are actively using the mobile app.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total Companies</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $stats['total_companies'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Companies Using the App</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $stats['companies_using_app'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Companies Not Using the App</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $stats['companies_not_using_app'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total Staff Using the App</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $stats['total_staff_using_app'] ?? 0 }}</div>
            </div>
        </div>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-slate-950">Company App Usage Report</h3>
                    <p class="mt-1 text-sm text-slate-500">Search, filter, and paginate company usage records.</p>
                </div>
                <div class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">{{ $companies->total() }} records</div>
            </div>

            <form method="GET" action="{{ route('super-admin.company-app-usage.index') }}" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Search</label>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="block w-full rounded-2xl border-slate-300" placeholder="Company name, code, or email">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Subscription Plan</label>
                    <select name="subscription_plan_id" class="block w-full rounded-2xl border-slate-300">
                        <option value="">All plans</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((string) ($filters['subscription_plan_id'] ?? '') === (string) $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Subscription Status</label>
                    <select name="subscription_status" class="block w-full rounded-2xl border-slate-300">
                        <option value="">All statuses</option>
                        <option value="active" @selected(($filters['subscription_status'] ?? '') === 'active')>Active</option>
                        <option value="trialing" @selected(($filters['subscription_status'] ?? '') === 'trialing')>Trialing</option>
                        <option value="pending" @selected(($filters['subscription_status'] ?? '') === 'pending')>Pending</option>
                        <option value="expired" @selected(($filters['subscription_status'] ?? '') === 'expired')>Expired</option>
                        <option value="cancelled" @selected(($filters['subscription_status'] ?? '') === 'cancelled')>Cancelled</option>
                        <option value="none" @selected(($filters['subscription_status'] ?? '') === 'none')>No active subscription</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">App Usage Status</label>
                    <select name="app_usage_status" class="block w-full rounded-2xl border-slate-300">
                        <option value="">All companies</option>
                        <option value="using_app" @selected(($filters['app_usage_status'] ?? '') === 'using_app')>Using App</option>
                        <option value="not_using_app" @selected(($filters['app_usage_status'] ?? '') === 'not_using_app')>Not Using App</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Activity Period</label>
                    <select name="activity_period" class="block w-full rounded-2xl border-slate-300">
                        <option value="">All time</option>
                        <option value="today" @selected(($filters['activity_period'] ?? '') === 'today')>Today</option>
                        <option value="last_7_days" @selected(($filters['activity_period'] ?? '') === 'last_7_days')>Last 7 Days</option>
                        <option value="last_30_days" @selected(($filters['activity_period'] ?? '') === 'last_30_days')>Last 30 Days</option>
                        <option value="custom" @selected(($filters['activity_period'] ?? '') === 'custom')>Custom Date Range</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">From</label>
                    <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="block w-full rounded-2xl border-slate-300">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">To</label>
                    <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="block w-full rounded-2xl border-slate-300">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sort</label>
                    <select name="sort" class="block w-full rounded-2xl border-slate-300">
                        <option value="last_active_desc" @selected(($filters['sort'] ?? '') === 'last_active_desc')>Last Activity - Newest</option>
                        <option value="last_active_asc" @selected(($filters['sort'] ?? '') === 'last_active_asc')>Last Activity - Oldest</option>
                        <option value="name_asc" @selected(($filters['sort'] ?? '') === 'name_asc')>Company Name - A to Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Company Name - Z to A</option>
                        <option value="using_app_desc" @selected(($filters['sort'] ?? '') === 'using_app_desc')>Most App Users</option>
                        <option value="using_app_asc" @selected(($filters['sort'] ?? '') === 'using_app_asc')>Fewest App Users</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Page Size</label>
                    <select name="per_page" class="block w-full rounded-2xl border-slate-300">
                        @foreach($perPageOptions as $option)
                            <option value="{{ $option }}" @selected((int) request('per_page', 10) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-3 md:col-span-2 xl:col-span-4">
                    <a href="{{ route('super-admin.company-app-usage.index') }}" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Apply Filters</button>
                </div>
            </form>

            <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200">
                <table class="min-w-[1300px] w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Company Name</th>
                            <th class="px-4 py-3 text-left">Subscription Plan</th>
                            <th class="px-4 py-3 text-left">Subscription Status</th>
                            <th class="px-4 py-3 text-left">Total Staff</th>
                            <th class="px-4 py-3 text-left">Staff Using App</th>
                            <th class="px-4 py-3 text-left">Staff Not Using App</th>
                            <th class="px-4 py-3 text-left">Active Today</th>
                            <th class="px-4 py-3 text-left">Active in Last 7 Days</th>
                            <th class="px-4 py-3 text-left">Active in Last 30 Days</th>
                            <th class="px-4 py-3 text-left">Last App Activity</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($companies as $company)
                            <tr>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-semibold text-slate-950">{{ $company->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $company->company_code }}</div>
                                </td>
                                <td class="px-4 py-4 align-top text-slate-700">{{ $company->latestSubscription?->plan?->name ?? '-' }}</td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ ($company->latestSubscription?->status ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : (($company->latestSubscription?->status ?? '') === 'trialing' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700') }}">
                                        {{ str($company->latestSubscription?->status ?? 'none')->headline() }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top font-semibold text-slate-950">{{ (int) ($company->total_staff ?? 0) }}</td>
                                <td class="px-4 py-4 align-top font-semibold text-emerald-700">{{ (int) ($company->staff_using_app ?? 0) }}</td>
                                <td class="px-4 py-4 align-top font-semibold text-slate-700">{{ (int) ($company->staff_not_using_app ?? 0) }}</td>
                                <td class="px-4 py-4 align-top font-semibold text-cyan-700">{{ (int) ($company->active_today ?? 0) }}</td>
                                <td class="px-4 py-4 align-top font-semibold text-violet-700">{{ (int) ($company->active_7_days ?? 0) }}</td>
                                <td class="px-4 py-4 align-top font-semibold text-amber-700">{{ (int) ($company->active_30_days ?? 0) }}</td>
                                <td class="px-4 py-4 align-top text-slate-700">
                                    {{ filled($company->last_app_activity) ? \Illuminate\Support\Carbon::parse($company->last_app_activity)->format('d M Y, h:i A') : '-' }}
                                </td>
                                <td class="px-4 py-4 align-top text-right">
                                    <a href="{{ route('super-admin.company-app-usage.show', $company) }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">View Details</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-6 py-10 text-center text-sm text-slate-500">No company app usage data found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($companies->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-4 md:flex-row md:items-center md:justify-between">
                    <div class="text-sm text-slate-500">
                        Showing {{ $companies->firstItem() }} to {{ $companies->lastItem() }} of {{ $companies->total() }} records
                    </div>
                    <div>
                        {{ $companies->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
