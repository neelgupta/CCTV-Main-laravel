<x-app-layout>
    <x-slot name="header">
        <div class="rounded-none border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">
                {{ __('Add New Staff Member') }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">Compact staff form with controlled spacing.</p>
        </div>
    </x-slot>

    @php
        $availablePlanOptions = collect($availableSubscriptionOptions ?? []);
        $selectedPlanOption = $availablePlanOptions->firstWhere('company_subscription_id', (int) old('company_subscription_id'))
            ?? $availablePlanOptions->first();
    @endphp

    <div class="py-4">
        <div class="mx-auto max-w-4xl sm:px-6 lg:px-8">
            <div class="rounded-none border border-slate-200 bg-white p-5 shadow-sm">
                <form method="POST" action="{{ route('teams.staff.store') }}" enctype="multipart/form-data" class="space-y-6">
                        @csrf

                        <div class="space-y-6">
                            <div>
                                <h3 class="text-lg font-medium text-slate-950 border-b border-slate-100 pb-2">Basic Information</h3>
                                <p class="mt-1 text-sm text-slate-500">Enter the personal and professional details of the new employee.</p>
                            </div>

                            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                                <div class="md:col-span-2">
                                    <x-input-label for="user_id" :value="__('Linked User')" />
                                    <select id="user_id" name="user_id" class="mt-1 block w-full rounded-none border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                        <option value="">Select User</option>
                                        @foreach($usersWithoutStaff as $companyUser)
                                            <option value="{{ $companyUser->id }}" {{ old('user_id') == $companyUser->id ? 'selected' : '' }}>
                                                {{ $companyUser->name }} ({{ $companyUser->email }})
                                        </option>
                                    @endforeach
                                    </select>
                                    @if($usersWithoutStaff->isEmpty())
                                        <p class="mt-2 text-xs text-amber-700">No company users are available to link. Create a user first from the Users module.</p>
                                    @endif
                                    <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                                </div>

                                <div class="md:col-span-2">
                                    <x-input-label for="company_subscription_id" :value="__('Plan')" />
                                    <select id="company_subscription_id" name="company_subscription_id" class="mt-1 block w-full rounded-none border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" @if($availablePlanOptions->isNotEmpty()) required @endif>
                                        <option value="">Select Plan for Staff</option>
                                        @forelse($availablePlanOptions as $planOption)
                                            <option
                                                value="{{ $planOption['company_subscription_id'] }}"
                                                data-total-members="{{ $planOption['total_members'] ?? '' }}"
                                                data-used-members="{{ $planOption['used_members'] ?? '' }}"
                                                data-remaining-members="{{ $planOption['remaining_members'] ?? '' }}"
                                                data-plan-label="{{ $planOption['label'] }}"
                                                @selected(old('company_subscription_id') === (string) $planOption['company_subscription_id'])
                                            >
                                                {{ $planOption['label'] }}
                                            </option>
                                        @empty
                                            <option value="" disabled>No active plan seats available</option>
                                        @endforelse
                                    </select>
                                    <p class="mt-2 text-xs text-slate-500">Choose an active plan seat. The system will assign the next available license key automatically.</p>
                                    <x-input-error :messages="$errors->get('company_subscription_id')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="name" :value="__('Full Name')" />
                                    <x-text-input id="name" class="mt-1 block w-full rounded-none" type="text" name="name" :value="old('name')" required minlength="2" maxlength="191" autofocus placeholder="John Doe" />
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="employee_id_display" :value="__('Employee ID')" />
                                    <x-text-input id="employee_id_display" class="mt-1 block w-full rounded-none bg-slate-50" type="text" value="Auto-generated on save" disabled />
                                    <p class="mt-1 text-xs text-slate-500">The system will assign a unique employee ID automatically for every company.</p>
                                </div>

                                <div>
                                    <x-input-label for="contact_number" :value="__('Contact Number')" />
                                    <x-text-input id="contact_number" class="mt-1 block w-full rounded-none" type="text" name="contact_number" :value="old('contact_number')" required minlength="10" maxlength="10" inputmode="numeric" placeholder="9876543210" oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);" />
                                    <x-input-error :messages="$errors->get('contact_number')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="department" :value="__('Department')" />
                                    <select id="department" name="department" class="mt-1 block w-full rounded-none border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                        <option value="">Select Department</option>
                                        <option value="HR">Human Resources</option>
                                        <option value="IT">Information Technology</option>
                                        <option value="Sales">Sales</option>
                                        <option value="Marketing">Marketing</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('department')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="join_date" :value="__('Joining Date')" />
                                    <x-text-input id="join_date" class="mt-1 block w-full rounded-none" type="date" name="join_date" :value="old('join_date')" required />
                                    <x-input-error :messages="$errors->get('join_date')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="profile_photo" :value="__('Profile Photo')" />
                                    <input id="profile_photo" name="profile_photo" type="file" class="mt-1 block w-full rounded-none border border-slate-200 bg-white px-3 py-2 text-sm text-slate-500" />
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                <div class="rounded-none border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Total Members</div>
                                    <div id="selected-plan-total" class="mt-2 text-2xl font-black text-slate-950">{{ $selectedPlanOption['total_members_label'] ?? ($subscriptionUsage['staff_limit_label'] ?? 'Unlimited') }}</div>
                                </div>
                                <div class="rounded-none border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Used Members</div>
                                    <div id="selected-plan-used" class="mt-2 text-2xl font-black text-slate-950">{{ isset($selectedPlanOption['used_members']) ? number_format((int) $selectedPlanOption['used_members']) : number_format((int) ($subscriptionUsage['used_members'] ?? 0)) }}</div>
                                </div>
                                <div class="rounded-none border border-slate-200 bg-slate-50 p-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Remaining Members</div>
                                    <div id="selected-plan-remaining" class="mt-2 text-2xl font-black text-slate-950">
                                        {{ ($selectedPlanOption['remaining_members'] ?? $subscriptionUsage['remaining_members'] ?? null) === null ? 'Unlimited' : number_format((int) ($selectedPlanOption['remaining_members'] ?? $subscriptionUsage['remaining_members'])) }}
                                    </div>
                                </div>
                            </div>
                            <p id="selected-plan-label" class="mt-2 text-xs text-slate-500">
                                @if($selectedPlanOption)
                                    Assigning this staff member to {{ $selectedPlanOption['label'] }}.
                                @else
                                    Select a plan to see seat usage.
                                @endif
                            </p>

                            <div class="flex items-center justify-end border-t border-slate-100 pt-6">
                                <a href="{{ route('teams.staff.index') }}" class="mr-4 text-sm text-slate-600 hover:text-slate-900">Cancel</a>
                                <x-primary-button class="rounded-none px-6 py-2">
                                    {{ __('Save Staff Member') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var subscriptionSelect = document.getElementById('company_subscription_id');
            var total = document.getElementById('selected-plan-total');
            var used = document.getElementById('selected-plan-used');
            var remaining = document.getElementById('selected-plan-remaining');
            var label = document.getElementById('selected-plan-label');

            function formatCount(value) {
                if (value === null || value === undefined || value === '') {
                    return 'Unlimited';
                }

                var parsed = Number(value);
                return Number.isFinite(parsed) ? parsed.toLocaleString() : 'Unlimited';
            }

            function updateSummary() {
                if (!subscriptionSelect) return;

                var option = subscriptionSelect.options[subscriptionSelect.selectedIndex];
                if (!option || !option.value) {
                    if (total) total.textContent = '0';
                    if (used) used.textContent = '0';
                    if (remaining) remaining.textContent = 'Unlimited';
                    if (label) label.textContent = 'Select a plan to see seat usage.';
                    return;
                }

                if (total) total.textContent = formatCount(option.dataset.totalMembers);
                if (used) used.textContent = formatCount(option.dataset.usedMembers);
                if (remaining) remaining.textContent = formatCount(option.dataset.remainingMembers);
                if (label) label.textContent = 'Assigning this staff member to ' + (option.dataset.planLabel || option.textContent) + '.';
            }

            if (subscriptionSelect) {
                subscriptionSelect.addEventListener('change', updateSummary);
                updateSummary();
            }
        });
    </script>
</x-app-layout>
