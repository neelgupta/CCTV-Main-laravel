<x-app-layout>
    @php
        $passwordResetCompanyId = old('password_company_id');
        $storageResetCompanyId = old('storage_company_id');
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">{{ $inactivePage ? 'Inactive Companies' : 'Company Directory' }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $inactivePage ? 'Review and reactivate inactive organizations.' : 'Manage and monitor active organizations.' }}</p>
            </div>
            @if(! $inactivePage)
                <div>
                    <a href="{{ route('super-admin.companies.create') }}" class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                        </svg>
                        Register New Company
                    </a>
                </div>
            @endif
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <form method="GET" action="{{ route($inactivePage ? 'super-admin.companies.inactive' : 'super-admin.companies') }}" class="grid gap-4 border-b border-slate-200 px-6 py-5 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Search</label>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Name, code, email, phone...">
            </div>
            <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Subscription Plan</label>
                <select name="plan_id" class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All plans</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}" @selected((string) ($filters['plan_id'] ?? '') === (string) $plan->id)>{{ $plan->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Onboarding Status</label>
                <select name="onboarding_status" class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All statuses</option>
                    <option value="onboarded" @selected(($filters['onboarding_status'] ?? '') === 'onboarded')>Onboarded</option>
                    <option value="pending" @selected(($filters['onboarding_status'] ?? '') === 'pending')>Pending Setup</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sort By</label>
                <select name="sort" class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="latest" @selected(($filters['sort'] ?? '') === 'latest')>Newest First</option>
                    <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest First</option>
                    <option value="name_asc" @selected(($filters['sort'] ?? '') === 'name_asc')>Name (A - Z)</option>
                    <option value="name_desc" @selected(($filters['sort'] ?? '') === 'name_desc')>Name (Z - A)</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Page Size</label>
                <select name="per_page" class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    @foreach($perPageOptions ?? [10, 25, 50, 100] as $option)
                        <option value="{{ $option }}" @selected((int) request('per_page', 10) === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2 md:col-span-2 lg:col-span-1 xl:col-span-1">
                <button type="submit" class="inline-flex flex-1 items-center justify-center rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-800">
                    Apply
                </button>
                <a href="{{ route($inactivePage ? 'super-admin.companies.inactive' : 'super-admin.companies') }}" class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Reset
                </a>
            </div>
        </form>

       <div class="overflow-x-auto">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
            <tr>
                <th class="px-6 py-4 text-left">Company</th>
                <th class="px-6 py-4 text-left">Contact Info</th>
                <th class="px-6 py-4 text-left">Onboarding Date</th>
                <th class="px-6 py-4 text-left">Location</th>
                <th class="px-6 py-4 text-left">Status</th>
                <th class="px-6 py-4 text-left">Subscription Plan</th>
                <!-- <th class="px-6 py-4 text-left">Storage</th> -->
                <th class="px-6 py-4 text-left">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            @forelse($companies as $company)
                @php
                    $companyAdmin = $company->users->first(fn ($user) => $user->isCompanyAdmin());
                    $activePlanEntries = collect($companySubscriptionPlans[$company->id] ?? []);
                @endphp
                <tr>
                    <td class="px-6 py-4">
                        <div class="font-semibold text-slate-950">{{ $company->name }}</div>
                        <div class="text-slate-400">Company Code: {{ $company->company_code }}</div>
                    </td>
                    <td class="px-6 py-4 text-slate-700">
                        <div>{{ $company->email }}</div>
                        <div class="text-slate-500">{{ $company->mobile_number ?: $company->phone_number ?: '-' }}</div>
                    </td>
                    <td class="px-6 py-4 text-slate-700">
                        {{$company->created_at}}
                    </td>
                    <td class="px-6 py-4 text-slate-700">
                        <div class="max-w-[150px] truncate" title="{{ $company->city ?: '-' }}, {{ $company->state ?: '-' }}">
                            <div>{{ $company->city ?: '-' }}</div>
                            <div class="text-slate-500">{{ $company->state ?: '-' }}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700">
                            {{ $company->is_onboarded ? 'Onboarded' : 'Pending Setup' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-slate-700">
                        @if($activePlanEntries->isNotEmpty())
                            <div class="flex flex-col gap-2 items-start">
                                @foreach($activePlanEntries as $planEntry)
                                    @php
                                        $subscription = $planEntry['subscription'];
                                        $usage = $planEntry['usage'] ?? [];
                                        $usedKeysCount = (int) ($usage['used_members'] ?? 0);
                                        $remainingKeysLabel = $usage['remaining_members_label'] ?? 'Unlimited';
                                        $totalKeysLabel = $usage['total_members_label'] ?? ($subscription->licenseSeatCountLabel() ?? 'Unlimited');
                                        $usedKeyList = $usage['used_license_keys'] ?? [];
                                        $remainingKeyList = $usage['remaining_license_keys'] ?? ($usage['available_license_keys'] ?? []);

                                        $endsAtText = null;
                                        if ($subscription->ends_at) {
                                            $endsAtText = ($subscription->is_trial || $subscription->status === 'trialing' ? 'Trial ends' : 'Valid till') . ': ' . $subscription->ends_at->format('M d, Y');
                                        } elseif ($subscription->trial_ends_at) {
                                            $endsAtText = 'Trial ends: ' . $subscription->trial_ends_at->format('M d, Y');
                                        }

                                        $planModalData = [
                                            'company_name' => $company->name,
                                            'plan_name' => $subscription->plan?->name ?? 'Custom Plan',
                                            'status_label' => $subscription->statusBadgeLabel(),
                                            'status_classes' => $subscription->statusBadgeClasses(),
                                            'billing_cycle' => $subscription->billing_cycle ? ucfirst($subscription->billing_cycle) : null,
                                            'starts_at' => $subscription->starts_at?->format('M d, Y'),
                                            'ends_at_label' => $endsAtText,
                                            'total_keys' => $totalKeysLabel,
                                            'used_keys' => $usedKeysCount,
                                            'remaining_keys' => $remainingKeysLabel,
                                            'used_license_keys' => $usedKeyList,
                                            'remaining_license_keys' => $remainingKeyList,
                                        ];
                                    @endphp
                                    <button
                                        type="button"
                                        class="group inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-800 shadow-2xs hover:border-indigo-400 hover:bg-indigo-50/50 hover:text-indigo-700 transition cursor-pointer"
                                        data-plan-modal-trigger
                                        title="Click to view plan details"
                                    >
                                        <script type="application/json" class="plan-info-data">{!! json_encode($planModalData) !!}</script>
                                        <span class="font-bold text-slate-900 group-hover:text-indigo-700">{{ $subscription->plan?->name ?? 'Custom Plan' }}</span>
                                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $subscription->statusBadgeClasses() }}">
                                            {{ $subscription->statusBadgeLabel() }}
                                        </span>
                                        <svg class="h-3.5 w-3.5 text-slate-400 group-hover:text-indigo-600 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </button>
                                @endforeach
                            </div>
                        @else
                            <div class="font-medium text-slate-400">No Plan</div>
                            <div class="mt-1">
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-500 border border-slate-200">
                                    No Subscription
                                </span>
                            </div>
                        @endif
                    </td>
                    <!-- <td class="px-6 py-4 text-slate-700">
                        <div class="font-semibold text-slate-950">{{ $company->currentStorageTotalLabel() }}</div>
                        <div class="mt-1 text-xs text-slate-500">
                            Base {{ $company->currentStorageBaseLabel() }} + Add-on {{ $company->currentStorageAddonLabel() }}
                        </div>
                    </td> -->
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-2 whitespace-nowrap">
                            <form method="POST" action="{{ route('super-admin.companies.impersonate', $company) }}">
                                @csrf
                                <button
                                    type="submit"
                                    @disabled(! $companyAdmin)
                                    class="rounded-xl border px-3 py-1.5 text-xs font-semibold {{ $companyAdmin ? 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' }}"
                                    title="{{ $companyAdmin ? 'Login as company admin' : 'No company admin user found' }}"
                                >
                                    Login as Company
                                </button>
                            </form>
                            <button
                                type="button"
                                @disabled(! $companyAdmin)
                                class="rounded-xl border px-3 py-1.5 text-xs font-semibold {{ $companyAdmin ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400' }}"
                                data-company-password
                                data-company-id="{{ $company->id }}"
                                data-company-name="{{ $company->name }}"
                                data-company-email="{{ $companyAdmin?->email ?? $company->email }}"
                                data-action="{{ route('super-admin.companies.password', $company) }}"
                                title="{{ $companyAdmin ? 'Change company admin password' : 'No company admin user found' }}"
                            >
                                Change Password
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700 hover:bg-amber-100"
                                data-company-storage
                                data-company-id="{{ $company->id }}"
                                data-company-name="{{ $company->name }}"
                                data-base-mb="{{ $company->currentStorageBaseMb() }}"
                                data-current-addon="{{ $company->storageAddonMb() }}"
                                data-action="{{ route('super-admin.companies.storage', $company) }}"
                                title="Add or update storage for this company"
                            >
                                Manage Storage
                            </button>
                            <form method="POST" action="{{ route('super-admin.companies.status', $company) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-2" title="Toggle active status">
                                    <span class="relative inline-flex h-6 w-10 items-center rounded-full {{ $company->is_active ? 'bg-emerald-500' : 'bg-slate-300' }}">
                                        <span class="inline-block h-4 w-4 transform rounded-full bg-white transition {{ $company->is_active ? 'translate-x-5' : 'translate-x-1' }}"></span>
                                    </span>
                                    <span class="text-xs font-medium text-slate-700">{{ $company->is_active ? 'Active' : 'Inactive' }}</span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('super-admin.companies.appointment-module', $company) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 shadow-2xs hover:bg-slate-50 transition" title="Toggle Appointment Permission for {{ $company->name }}">
                                    <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-200 ease-in-out {{ $company->allow_appointment_module ? 'bg-indigo-600' : 'bg-slate-300' }}">
                                        <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ $company->allow_appointment_module ? 'translate-x-4' : 'translate-x-1' }}"></span>
                                    </span>
                                    <span class="text-xs font-semibold {{ $company->allow_appointment_module ? 'text-indigo-700' : 'text-slate-500' }}">
                                        Appointment: {{ $company->allow_appointment_module ? 'Allowed' : 'Disabled' }}
                                    </span>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('super-admin.companies.create-company-from-lead', $company) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-2.5 py-1.5 shadow-2xs hover:bg-slate-50 transition" title="Toggle Create Company From Lead Permission for {{ $company->name }}">
                                    <span class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors duration-200 ease-in-out {{ $company->allow_create_company_from_lead ? 'bg-indigo-600' : 'bg-slate-300' }}">
                                        <span class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm transition duration-200 ease-in-out {{ $company->allow_create_company_from_lead ? 'translate-x-4' : 'translate-x-1' }}"></span>
                                    </span>
                                    <span class="text-xs font-semibold {{ $company->allow_create_company_from_lead ? 'text-indigo-700' : 'text-slate-500' }}">
                                        Create Company From Lead: {{ $company->allow_create_company_from_lead ? 'Allowed' : 'Disabled' }}
                                    </span>
                                </button>
                            </form>
                            <form
                                method="GET"
                                action="{{ route($inactivePage ? 'super-admin.companies.inactive' : 'super-admin.companies', request()->except('delete')) }}"
                                data-swal-confirm="Delete {{ $company->name }}? This will permanently remove the company and its related records."
                                data-swal-title="Confirm Delete"
                                data-swal-confirm-button="Delete"
                                data-swal-cancel-button="Cancel"
                            >
                                <input type="hidden" name="delete" value="{{ $company->id }}">
                                <button
                                    type="submit"
                                    class="rounded-xl border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                                    title="Delete company"
                                >
                                    Delete
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-8 text-center text-sm text-slate-500">No companies available.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

    @if($companies->hasPages())
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $companies->onEachSide(1)->links() }}
            </div>
        @endif
    </section>

    <div id="company-plan-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-950/50 px-4 py-8">
        <div class="w-full max-w-xl rounded-[28px] border border-slate-200 bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h3 class="text-xl font-bold text-slate-950">Subscription Plan Details</h3>
                    <p class="mt-1 text-sm text-slate-500" id="plan-modal-company-name">Company</p>
                </div>
                <button type="button" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" data-plan-modal-close aria-label="Close modal">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <div class="space-y-5 px-6 py-6">
                <div class="flex flex-col gap-2 rounded-2xl border border-slate-200 bg-slate-50/70 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-lg font-bold text-slate-950" id="plan-modal-plan-name">-</div>
                        <div class="mt-1 text-xs text-slate-500" id="plan-modal-validity">-</div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span id="plan-modal-status-badge" class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold">-</span>
                        <span id="plan-modal-cycle" class="text-xs text-slate-500 font-medium"></span>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="rounded-2xl border border-slate-200 bg-white p-3.5 text-center shadow-2xs">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Keys</div>
                        <div id="plan-modal-total-keys" class="mt-1 text-xl font-bold text-slate-900">-</div>
                    </div>
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50/50 p-3.5 text-center shadow-2xs">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-indigo-600">Used Keys</div>
                        <div id="plan-modal-used-keys" class="mt-1 text-xl font-bold text-indigo-700">-</div>
                    </div>
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-3.5 text-center shadow-2xs">
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-600">Remaining</div>
                        <div id="plan-modal-remaining-keys" class="mt-1 text-xl font-bold text-emerald-700">-</div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" class="rounded-2xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition" data-plan-modal-close>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div id="company-storage-modal" class="fixed inset-0 z-50 hidden flex items-center justify-center overflow-y-auto bg-slate-950/50 px-4 py-8">
        <div class="w-full max-w-lg rounded-[28px] border border-slate-200 bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h3 class="text-xl font-bold text-slate-950">Manage Company Storage</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        <span id="company-storage-name">Company</span>
                        <span id="company-storage-summary" class="block"></span>
                    </p>
                </div>
                <button type="button" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" data-company-storage-close aria-label="Close storage modal">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <form id="companyStorageForm" method="POST" action="#" class="space-y-5 px-6 py-6">
                @csrf
                @method('PATCH')
                <input id="storage_company_id" type="hidden" name="storage_company_id" value="{{ $storageResetCompanyId }}">
                <x-input-error :messages="$errors->companyStorageForm->get('storage_company_id')" class="mt-2" />

                <div>
                    <label for="storage_addon_mb" class="mb-2 block text-sm font-semibold text-slate-700">Additional Storage to Add in MB</label>
                    <input id="storage_addon_mb" type="number" name="storage_addon_mb" min="0" max="999999" step="1" class="block w-full rounded-2xl border-slate-300" value="" placeholder="Enter MB to add" required>
                    <p class="mt-2 text-xs text-slate-500">Enter only the extra MB you want to add. The system will add this on top of the company’s existing add-on.</p>
                    <x-input-error :messages="$errors->companyStorageForm->get('storage_addon_mb')" class="mt-2" />
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    <div class="flex flex-col gap-y-2">
                        <div><span class="font-semibold text-slate-900">Plan base:</span> <span id="company-storage-base">-</span></div>
                        <div><span class="font-semibold text-slate-900">Current add-on:</span> <span id="company-storage-current-addon">-</span></div>
                        <div><span class="font-semibold text-slate-900">Projected total:</span> <span id="company-storage-total">-</span></div>
                    </div>
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-company-storage-close>Cancel</button>
                    <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Save Storage Add-on</button>
                </div>
            </form>
        </div>
    </div>

    <div id="company-password-modal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-950/50 px-4 py-8">
        <div class="mx-auto max-w-lg rounded-[28px] border border-slate-200 bg-white shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-200 px-6 py-5">
                <div>
                    <h3 class="text-xl font-bold text-slate-950">Change Company Password</h3>
                    <p class="mt-1 text-sm text-slate-500">
                        <span id="company-password-name">Company</span>
                        <span id="company-password-email" class="block"></span>
                    </p>
                </div>
                <button type="button" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700" data-company-password-close aria-label="Close password modal">
                    <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </div>

            <form id="companyPasswordForm" method="POST" action="#" class="space-y-5 px-6 py-6">
                @csrf
                @method('PATCH')
                <input id="password_company_id" type="hidden" name="password_company_id" value="{{ $passwordResetCompanyId }}">
                <x-input-error :messages="$errors->companyPasswordForm->get('password_company_id')" class="mt-2" />

                <div>
                    <label for="company_password" class="mb-2 block text-sm font-semibold text-slate-700">New Password</label>
                    <input id="company_password" type="password" name="password" minlength="8" maxlength="255" autocomplete="new-password" class="block w-full rounded-2xl border-slate-300" required>
                    <x-input-error :messages="$errors->companyPasswordForm->get('password')" class="mt-2" />
                </div>

                <div>
                    <label for="company_password_confirmation" class="mb-2 block text-sm font-semibold text-slate-700">Confirm Password</label>
                    <input id="company_password_confirmation" type="password" name="password_confirmation" minlength="8" maxlength="255" autocomplete="new-password" class="block w-full rounded-2xl border-slate-300" required>
                    <x-input-error :messages="$errors->companyPasswordForm->get('password_confirmation')" class="mt-2" />
                </div>

                <div class="flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <button type="button" class="rounded-2xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-company-password-close>Cancel</button>
                    <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const storageModal = document.getElementById('company-storage-modal');
            const storageForm = document.getElementById('companyStorageForm');
            const storageCompanyIdInput = document.getElementById('storage_company_id');
            const storageAddonInput = document.getElementById('storage_addon_mb');
            const storageCompanyName = document.getElementById('company-storage-name');
            const storageSummary = document.getElementById('company-storage-summary');
            const storageBaseLabel = document.getElementById('company-storage-base');
            const storageCurrentAddonLabel = document.getElementById('company-storage-current-addon');
            const storageTotalLabel = document.getElementById('company-storage-total');
            const initialStorageCompanyId = @js($storageResetCompanyId);
            const modal = document.getElementById('company-password-modal');
            const form = document.getElementById('companyPasswordForm');
            const companyIdInput = document.getElementById('password_company_id');
            const passwordInput = document.getElementById('company_password');
            const confirmationInput = document.getElementById('company_password_confirmation');
            const companyName = document.getElementById('company-password-name');
            const companyEmail = document.getElementById('company-password-email');
            const initialCompanyId = @js($passwordResetCompanyId);
            let activeStorageButton = null;

            function formatStorageLabel(mb) {
                const value = Number(mb || 0);

                if (value >= 1024) {
                    return (value / 1024).toFixed(1).replace(/\.0$/, '') + ' GB';
                }

                return value.toLocaleString() + ' MB';
            }

            function formatAddonLabel(mb) {
                const value = Number(mb || 0);

                return value > 0 ? formatStorageLabel(value) : 'No add-on';
            }

            function openStorageModal(button) {
                if (!storageModal || !storageForm || !button) {
                    return;
                }

                const baseMb = Number(button.dataset.baseMb || 0);
                const currentAddonMb = Number(button.dataset.currentAddon || 0);
                const projectedAddonMb = currentAddonMb;
                const totalMb = baseMb + projectedAddonMb;

                storageForm.action = button.dataset.action || '#';
                storageCompanyIdInput.value = button.dataset.companyId || '';
                storageCompanyName.textContent = button.dataset.companyName || 'Company';
                storageAddonInput.value = '';
                activeStorageButton = button;
                storageBaseLabel.textContent = formatStorageLabel(baseMb);
                storageCurrentAddonLabel.textContent = formatAddonLabel(currentAddonMb);
                storageTotalLabel.textContent = formatStorageLabel(totalMb);
                storageSummary.textContent = 'Base ' + formatStorageLabel(baseMb) + ' + current add-on ' + formatAddonLabel(currentAddonMb) + ' + new add-on 0 MB';

                document.body.classList.add('overflow-hidden');
                storageModal.classList.remove('hidden');
                storageAddonInput.focus();
            }

            function updateStoragePreview(button) {
                if (!button || !storageAddonInput) {
                    return;
                }

                const baseMb = Number(button.dataset.baseMb || 0);
                const currentAddonMb = Number(button.dataset.currentAddon || 0);
                const deltaMb = Number(storageAddonInput.value || 0);
                const projectedAddonMb = currentAddonMb + deltaMb;
                const totalMb = baseMb + projectedAddonMb;

                storageCurrentAddonLabel.textContent = formatAddonLabel(currentAddonMb);
                storageTotalLabel.textContent = formatStorageLabel(totalMb);
                storageSummary.textContent = 'Base ' + formatStorageLabel(baseMb) + ' + current add-on ' + formatAddonLabel(currentAddonMb) + ' + new add-on ' + formatAddonLabel(deltaMb);
            }

            function closeStorageModal() {
                if (!storageModal) {
                    return;
                }

                storageModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                activeStorageButton = null;
            }

            function openPasswordModal(button) {
                if (!modal || !form || !button) {
                    return;
                }

                form.action = button.dataset.action || '#';
                companyIdInput.value = button.dataset.companyId || '';
                companyName.textContent = button.dataset.companyName || 'Company';
                companyEmail.textContent = button.dataset.companyEmail || '';
                passwordInput.value = '';
                confirmationInput.value = '';

                document.body.classList.add('overflow-hidden');
                modal.classList.remove('hidden');
                passwordInput.focus();
            }

            function closePasswordModal() {
                if (!modal) {
                    return;
                }

                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            const planModal = document.getElementById('company-plan-modal');

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text ?? '';
                return div.innerHTML;
            }

            function openPlanModal(button) {
                if (!planModal || !button) {
                    return;
                }

                try {
                    let data = {};
                    const payloadEl = button.querySelector('.plan-info-data');
                    if (payloadEl) {
                        const raw = (payloadEl.textContent || (payloadEl.content ? payloadEl.content.textContent : '') || payloadEl.innerHTML || '').trim();
                        if (raw) {
                            data = JSON.parse(raw);
                        }
                    } else if (button.dataset.planInfo) {
                        try {
                            data = JSON.parse(button.dataset.planInfo);
                        } catch {
                            const txt = document.createElement('textarea');
                            txt.innerHTML = button.dataset.planInfo;
                            data = JSON.parse(txt.value);
                        }
                    }

                    document.getElementById('plan-modal-company-name').textContent = data.company_name || 'Company';
                    document.getElementById('plan-modal-plan-name').textContent = data.plan_name || 'Plan';

                    const statusBadge = document.getElementById('plan-modal-status-badge');
                    statusBadge.textContent = data.status_label || '-';
                    statusBadge.className = 'inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ' + (data.status_classes || 'bg-slate-100 text-slate-700');

                    document.getElementById('plan-modal-cycle').textContent = data.billing_cycle ? '(' + data.billing_cycle + ')' : '';

                    let validityText = '';
                    if (data.starts_at) {
                        validityText += 'Started: ' + data.starts_at;
                    }
                    if (data.ends_at_label) {
                        validityText += (validityText ? ' · ' : '') + data.ends_at_label;
                    }
                    document.getElementById('plan-modal-validity').textContent = validityText || 'No expiry date set';

                    document.getElementById('plan-modal-total-keys').textContent = data.total_keys || '-';
                    document.getElementById('plan-modal-used-keys').textContent = Number(data.used_keys || 0).toLocaleString();
                    document.getElementById('plan-modal-remaining-keys').textContent = data.remaining_keys || '-';

                    document.body.classList.add('overflow-hidden');
                    planModal.classList.remove('hidden');
                } catch (e) {
                    console.error('Failed to parse plan modal info:', e);
                }
            }

            function closePlanModal() {
                if (!planModal) {
                    return;
                }
                planModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            document.addEventListener('click', (event) => {
                const trigger = event.target.closest('[data-plan-modal-trigger]');
                if (trigger) {
                    event.preventDefault();
                    openPlanModal(trigger);
                    return;
                }

                const closeBtn = event.target.closest('[data-plan-modal-close]');
                if (closeBtn) {
                    event.preventDefault();
                    closePlanModal();
                    return;
                }
            });

            if (planModal) {
                planModal.addEventListener('click', (event) => {
                    if (event.target === planModal) {
                        closePlanModal();
                    }
                });
            }

            document.querySelectorAll('[data-company-storage]').forEach((button) => {
                button.addEventListener('click', () => openStorageModal(button));
            });

            document.querySelectorAll('[data-company-storage-close]').forEach((button) => {
                button.addEventListener('click', closeStorageModal);
            });

            document.querySelectorAll('[data-company-password]').forEach((button) => {
                button.addEventListener('click', () => openPasswordModal(button));
            });

            document.querySelectorAll('[data-company-password-close]').forEach((button) => {
                button.addEventListener('click', closePasswordModal);
            });

            if (modal) {
                modal.addEventListener('click', (event) => {
                    if (event.target === modal) {
                        closePasswordModal();
                    }
                });
            }

            if (storageModal) {
                storageModal.addEventListener('click', (event) => {
                    if (event.target === storageModal) {
                        closeStorageModal();
                    }
                });
            }

            storageAddonInput?.addEventListener('input', () => {
                if (activeStorageButton && storageModal && !storageModal.classList.contains('hidden')) {
                    updateStoragePreview(activeStorageButton);
                }
            });

            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') {
                    closePlanModal();
                    closePasswordModal();
                    closeStorageModal();
                }
            });

            if (initialStorageCompanyId) {
                const button = document.querySelector(`[data-company-storage][data-company-id="${initialStorageCompanyId}"]`);
                if (button) {
                    openStorageModal(button);
                }
            }

            if (initialCompanyId) {
                const button = document.querySelector(`[data-company-password][data-company-id="${initialCompanyId}"]`);
                if (button) {
                    openPasswordModal(button);
                }
            }
        });
    </script>
</x-app-layout>
