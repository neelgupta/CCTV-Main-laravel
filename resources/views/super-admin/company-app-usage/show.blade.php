<x-app-layout>
    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">{{ $company->name }} App Usage</h2>
            <p class="mt-1 text-sm text-slate-500">Detailed mobile app usage for this company.</p>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Total Staff</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-950">{{ $summary['total_staff'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff Using App</div>
                <div class="mt-2 text-3xl font-extrabold text-emerald-700">{{ $summary['staff_using_app'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff Not Using App</div>
                <div class="mt-2 text-3xl font-extrabold text-slate-700">{{ $summary['staff_not_using_app'] ?? 0 }}</div>
            </div>
            <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-slate-200">
                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Last App Activity</div>
                <div class="mt-2 text-lg font-bold text-slate-950">
                    {{ filled($summary['last_app_activity'] ?? null) ? \Illuminate\Support\Carbon::parse($summary['last_app_activity'])->format('d M Y, h:i A') : '-' }}
                </div>
            </div>
        </div>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="grid gap-6 lg:grid-cols-[1.2fr_0.8fr]">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Company Info</p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-950">{{ $company->name }}</h3>
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Company Code</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ $company->company_code }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Contact Email</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ $company->email ?: '-' }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Mobile Number</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ $company->mobile_number ?: '-' }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Subscription</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ $company->latestSubscription?->plan?->name ?? '-' }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Subscription Status</div>
                            <div class="mt-1">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ ($company->latestSubscription?->status ?? '') === 'active' ? 'bg-emerald-100 text-emerald-700' : (($company->latestSubscription?->status ?? '') === 'trialing' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700') }}">
                                    {{ str($company->latestSubscription?->status ?? 'none')->headline() }}
                                </span>
                            </div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Active Today</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ $summary['active_today'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Active 7 Days</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ $summary['active_7_days'] ?? 0 }}</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Active 30 Days</div>
                            <div class="mt-1 font-semibold text-slate-950">{{ $summary['active_30_days'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <h4 class="text-lg font-bold text-slate-950">Device Platform Summary</h4>
                        <div class="mt-4 space-y-3">
                            @forelse($platformSummary as $row)
                                <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3">
                                    <span class="font-medium text-slate-900">{{ $row['label'] }}</span>
                                    <span class="text-sm font-semibold text-slate-700">{{ $row['count'] }}</span>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">No platform data available.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-slate-50 p-5">
                        <h4 class="text-lg font-bold text-slate-950">App Version Summary</h4>
                        <div class="mt-4 space-y-3">
                            @forelse($versionSummary as $row)
                                <div class="flex items-center justify-between rounded-2xl bg-white px-4 py-3">
                                    <span class="font-medium text-slate-900">{{ $row['label'] }}</span>
                                    <span class="text-sm font-semibold text-slate-700">{{ $row['count'] }}</span>
                                </div>
                            @empty
                                <div class="rounded-2xl border border-dashed border-slate-200 px-4 py-6 text-center text-sm text-slate-500">No version data available.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-2xl font-bold text-slate-950">Staff Usage</h3>
                    <p class="mt-1 text-sm text-slate-500">Search and review each staff member's mobile app activity.</p>
                </div>
                <div class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">{{ $staff->total() }} records</div>
            </div>

            <form method="GET" action="{{ route('super-admin.company-app-usage.show', $company) }}" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Search</label>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="block w-full rounded-2xl border-slate-300" placeholder="Staff name, email, mobile, employee ID">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">App Usage Status</label>
                    <select name="app_usage_status" class="block w-full rounded-2xl border-slate-300">
                        <option value="">All staff</option>
                        <option value="using_app" @selected(($filters['app_usage_status'] ?? '') === 'using_app')>Using App</option>
                        <option value="not_using_app" @selected(($filters['app_usage_status'] ?? '') === 'not_using_app')>Not Using App</option>
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Device Platform</label>
                    <input type="text" name="platform" value="{{ $filters['platform'] ?? '' }}" class="block w-full rounded-2xl border-slate-300" placeholder="Android / iOS">
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
                        <option value="last_active_desc" @selected(($filters['sort'] ?? '') === 'last_active_desc')>Last Active - Newest</option>
                        <option value="last_active_asc" @selected(($filters['sort'] ?? '') === 'last_active_asc')>Last Active - Oldest</option>
                        <option value="name_asc" @selected(($filters['sort'] ?? '') === 'name_asc')>Name - A to Z</option>
                        <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name - Z to A</option>
                        <option value="last_login_desc" @selected(($filters['sort'] ?? '') === 'last_login_desc')>Last Login - Newest</option>
                        <option value="last_login_asc" @selected(($filters['sort'] ?? '') === 'last_login_asc')>Last Login - Oldest</option>
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
                    <a href="{{ route('super-admin.company-app-usage.show', $company) }}" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Apply Filters</button>
                </div>
            </form>

            <div class="mt-6 overflow-x-auto rounded-2xl border border-slate-200">
                <table class="min-w-[1500px] w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-4 py-3 text-left">Staff Name</th>
                            <th class="px-4 py-3 text-left">Email</th>
                            <th class="px-4 py-3 text-left">Mobile Number</th>
                            <th class="px-4 py-3 text-left">Role</th>
                            <th class="px-4 py-3 text-left">App Usage Status</th>
                            <th class="px-4 py-3 text-left">Device Platform</th>
                            <th class="px-4 py-3 text-left">App Version</th>
                            <th class="px-4 py-3 text-left">Last Active Time</th>
                            <th class="px-4 py-3 text-left">Last Login Time</th>
                            <th class="px-4 py-3 text-left">Masked Device ID</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @forelse($staff as $member)
                            @php
                                $activity = $member->latestAppActivity;
                                $maskedDeviceId = filled($activity?->device_id)
                                    ? (strlen($activity->device_id) > 8
                                        ? substr($activity->device_id, 0, 3) . str_repeat('*', max(4, strlen($activity->device_id) - 6)) . substr($activity->device_id, -3)
                                        : str_repeat('*', strlen($activity->device_id)))
                                    : '-';
                            @endphp
                            <tr>
                                <td class="px-4 py-4 align-top">
                                    <div class="font-semibold text-slate-950">{{ $member->name }}</div>
                                    <div class="text-xs text-slate-500">{{ $member->employee_id }}</div>
                                </td>
                                <td class="px-4 py-4 align-top text-slate-700">{{ $member->user?->email ?? '-' }}</td>
                                <td class="px-4 py-4 align-top text-slate-700">{{ $member->contact_number ?: '-' }}</td>
                                <td class="px-4 py-4 align-top text-slate-700">{{ $member->role?->name ?? '-' }}</td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ filled($member->last_active_at) ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' }}">
                                        {{ filled($member->last_active_at) ? 'Using App' : 'Not Using App' }}
                                    </span>
                                </td>
                                <td class="px-4 py-4 align-top text-slate-700">{{ $activity?->platform ?: '-' }}</td>
                                <td class="px-4 py-4 align-top text-slate-700">{{ $activity?->app_version ?: '-' }}</td>
                                <td class="px-4 py-4 align-top text-slate-700">{{ filled($member->last_active_at) ? \Illuminate\Support\Carbon::parse($member->last_active_at)->format('d M Y, h:i A') : '-' }}</td>
                                <td class="px-4 py-4 align-top text-slate-700">{{ filled($member->last_login_at) ? \Illuminate\Support\Carbon::parse($member->last_login_at)->format('d M Y, h:i A') : '-' }}</td>
                                <td class="px-4 py-4 align-top font-mono text-xs text-slate-700">{{ $maskedDeviceId }}</td>
                                <td class="px-4 py-4 align-top">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $member->user?->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                        {{ $member->user?->is_active ? 'Active' : 'Inactive' }}
                                    </span>
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

            @if($staff->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-200 px-4 py-4 md:flex-row md:items-center md:justify-between">
                    <div class="text-sm text-slate-500">
                        Showing {{ $staff->firstItem() }} to {{ $staff->lastItem() }} of {{ $staff->total() }} records
                    </div>
                    <div>
                        {{ $staff->onEachSide(1)->links() }}
                    </div>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
