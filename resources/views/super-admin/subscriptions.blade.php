<x-app-layout>
    <style>
        label.error {
            display: block;
            color: #dc2626;
            font-size: 0.75rem;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        input.error,
        select.error,
        textarea.error {
            border-color: #dc2626 !important;
        }
    </style>

    @php
        $activePlanPrices = $activePlans
            ->mapWithKeys(function ($plan) {
                return [(string) $plan->id => number_format((float) $plan->price, 2, '.', '')];
            })
            ->all();
        $planEditState = old('form_type') === 'plan-edit' && old('edit_plan_id')
            ? [
                'id' => old('edit_plan_id'),
                'name' => old('name'),
                'monthly_price' => old('monthly_price'),
                'yearly_price' => old('yearly_price'),
                'is_active' => old('is_active'),
                'is_popular' => old('is_popular'),
                'has_free_trial' => old('has_free_trial'),
                'disable_staff_tracking' => old('disable_staff_tracking'),
                'razorpay_plan_id_monthly' => old('razorpay_plan_id_monthly'),
                'razorpay_plan_id_yearly' => old('razorpay_plan_id_yearly'),
            ]
            : null;
    @endphp

    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">Subscription Management (Super Admin)</h2>
            <p class="mt-1 text-sm text-slate-500">Create plans, assign them to companies, and review current subscription records.</p>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('warning'))
        <div class="mb-6 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ session('warning') }}</div>
    @endif

    <div class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-2xl font-bold text-slate-950">Create Subscription Plan</h3>
                <form id="planForm" method="POST" action="{{ route('super-admin.plans.store') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="form_type" value="plan">
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Plan Name</label>
                        <input type="text" name="name" value="{{ old('form_type') === 'plan' ? old('name') : '' }}" minlength="2" maxlength="191" class="block w-full rounded-2xl border-slate-300" placeholder="Enter a Plan Name" required>
                        <x-input-error :messages="$errors->planForm->get('name')" class="mt-2" />
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Monthly Price</label>
                            <input type="number" name="monthly_price" step="0.01" min="0" max="999999.99" class="block w-full rounded-2xl border-slate-300" value="{{ old('form_type') === 'plan' ? old('monthly_price', 0) : 0 }}" required>
                            <x-input-error :messages="$errors->planForm->get('monthly_price')" class="mt-2" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Yearly Price</label>
                            <input type="number" name="yearly_price" step="0.01" min="0" max="999999.99" class="block w-full rounded-2xl border-slate-300" value="{{ old('form_type') === 'plan' ? old('yearly_price', 0) : 0 }}" required>
                            <x-input-error :messages="$errors->planForm->get('yearly_price')" class="mt-2" />
                        </div>
                    </div>
                    @include('super-admin.partials.razorpay-plan-fields', [
                        'bag' => 'planForm',
                        'prefix' => 'plan_create',
                        'values' => old('form_type') === 'plan'
                            ? ['monthly' => old('razorpay_plan_id_monthly'), 'yearly' => old('razorpay_plan_id_yearly')]
                            : [],
                    ])
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('form_type') === 'plan' ? old('is_active', true) : true) class="rounded border-slate-300 text-indigo-600">
                        <span>Active</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="is_popular" value="0">
                        <input type="checkbox" name="is_popular" value="1" @checked(old('form_type') === 'plan' ? old('is_popular') : false) class="rounded border-slate-300 text-indigo-600">
                        <span>Popular / Featured</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="has_free_trial" value="0">
                        <input type="checkbox" name="has_free_trial" value="1" @checked(old('form_type') === 'plan' ? old('has_free_trial') : false) class="rounded border-slate-300 text-indigo-600">
                        <span>Enable 1 Month Free Trial</span>
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="disable_staff_tracking" value="0">
                        <input type="checkbox" name="disable_staff_tracking" value="1" @checked(old('form_type') === 'plan' ? old('disable_staff_tracking') : false) class="rounded border-slate-300 text-indigo-600">
                        <span>Disable Staff Tracking</span>
                    </label>
                    <div>
                        <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Create Plan</button>
                    </div>
                </form>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-2xl font-bold text-slate-950">Assign / Update Subscription</h3>
                <form id="subscriptionForm" method="POST" action="{{ route('super-admin.subscriptions.assign') }}" class="mt-6 space-y-4">
                    @csrf
                    <input type="hidden" name="form_type" value="subscription">
                    <input type="hidden" id="selected_plan_price" value="{{ old('form_type') === 'subscription' ? old('amount_paid', 0) : 0 }}">
                    <div>
                        <x-searchable-select
                            name="company_id"
                            id="company_id"
                            label="Company"
                            :options="collect($companies)->map(fn ($c) => ['id' => $c->id, 'label' => $c->name . ($c->company_code ? ' (' . $c->company_code . ')' : '')])"
                            :selected="old('form_type') === 'subscription' ? (string) old('company_id') : (string) request('company_id')"
                            empty-label="Select company"
                            placeholder="Search company..."
                        />
                        <x-input-error :messages="$errors->subscriptionForm->get('company_id')" class="mt-2" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Plan</label>
                        <select name="subscription_plan_id" class="block w-full rounded-2xl border-slate-300" required>
                            <option value="">Select plan</option>
                            @foreach($activePlans as $plan)
                                <option
                                    value="{{ $plan->id }}"
                                    data-price="{{ number_format((float) $plan->price, 2, '.', '') }}"
                                    @selected(old('form_type') === 'subscription' && (string) old('subscription_plan_id') === (string) $plan->id)
                                >
                                    {{ $plan->name }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->subscriptionForm->get('subscription_plan_id')" class="mt-2" />
                    </div>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
                            <select name="status" class="block w-full rounded-2xl border-slate-300" required>
                                <option value="active" @selected(old('form_type') === 'subscription' ? old('status', 'active') === 'active' : true)>Active</option>
                                <option value="trialing" @selected(old('form_type') === 'subscription' && old('status') === 'trialing')>Trialing</option>
                                <option value="pending" @selected(old('form_type') === 'subscription' && old('status') === 'pending')>Pending</option>
                                <option value="expired" @selected(old('form_type') === 'subscription' && old('status') === 'expired')>Expired</option>
                                <option value="cancelled" @selected(old('form_type') === 'subscription' && old('status') === 'cancelled')>Cancelled</option>
                            </select>
                            <x-input-error :messages="$errors->subscriptionForm->get('status')" class="mt-2" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">User Count</label>
                            <input type="number" name="user_count" step="1" min="1" max="100000" class="block w-full rounded-2xl border-slate-300" value="{{ old('form_type') === 'subscription' ? old('user_count', 1) : 1 }}" required>
                            <p class="mt-2 text-xs text-slate-500">Number of users/seats for this subscription.</p>
                            <x-input-error :messages="$errors->subscriptionForm->get('user_count')" class="mt-2" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Amount Paid</label>
                        <input type="number" name="amount_paid" step="0.01" min="0" max="99999999.99" class="block w-full rounded-2xl border-slate-300 bg-slate-50" value="{{ old('form_type') === 'subscription' ? old('amount_paid', 0) : 0 }}" required readonly>
                        <p class="mt-2 text-xs text-slate-500">Amount = plan price &times; user count.</p>
                        <x-input-error :messages="$errors->subscriptionForm->get('amount_paid')" class="mt-2" />
                    </div>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Starts At</label>
                            <input type="date" name="starts_at" min="{{ now()->toDateString() }}" value="{{ old('form_type') === 'subscription' ? old('starts_at') : '' }}" class="block w-full rounded-2xl border-slate-300" required>
                            <x-input-error :messages="$errors->subscriptionForm->get('starts_at')" class="mt-2" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Trial Ends At</label>
                            <input type="date" name="trial_ends_at" min="{{ now()->toDateString() }}" value="{{ old('form_type') === 'subscription' ? old('trial_ends_at') : '' }}" class="block w-full rounded-2xl border-slate-300">
                            <x-input-error :messages="$errors->subscriptionForm->get('trial_ends_at')" class="mt-2" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Ends At</label>
                            <input type="date" name="ends_at" min="{{ now()->toDateString() }}" value="{{ old('form_type') === 'subscription' ? old('ends_at') : '' }}" class="block w-full rounded-2xl border-slate-300">
                            <x-input-error :messages="$errors->subscriptionForm->get('ends_at')" class="mt-2" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Payment Method</label>
                        <input type="text" name="payment_method" minlength="2" maxlength="50" value="{{ old('form_type') === 'subscription' ? old('payment_method') : '' }}" class="block w-full rounded-2xl border-slate-300" placeholder="Razorpay / Bank / UPI">
                        <x-input-error :messages="$errors->subscriptionForm->get('payment_method')" class="mt-2" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Billing Reference</label>
                        <input type="text" name="billing_reference" minlength="2" maxlength="191" value="{{ old('form_type') === 'subscription' ? old('billing_reference') : '' }}" class="block w-full rounded-2xl border-slate-300" placeholder="Enter Billing Reference">
                        <x-input-error :messages="$errors->subscriptionForm->get('billing_reference')" class="mt-2" />
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-slate-700">Notes</label>
                        <textarea name="notes" rows="4" minlength="2" maxlength="2000" class="block w-full rounded-2xl border-slate-300" placeholder="Enter Notes">{{ old('form_type') === 'subscription' ? old('notes') : '' }}</textarea>
                        <x-input-error :messages="$errors->subscriptionForm->get('notes')" class="mt-2" />
                    </div>
                    <div>
                        <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Save Subscription</button>
                    </div>
                </form>
            </section>
        </div>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                <h3 class="text-xl font-bold text-slate-950">Subscription Plans</h3>
                <div class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">{{ $plans->count() }} plans</div>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full table-fixed text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="w-1/4 px-6 py-4 text-left">Plan</th>
                            <th class="w-36 px-6 py-4 text-left">Monthly Price</th>
                            <th class="w-36 px-6 py-4 text-left">Yearly Price</th>
                            <th class="px-6 py-4 text-left">Description</th>
                            <th class="w-32 px-6 py-4 text-left">Status</th>
                            <th class="w-48 px-6 py-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @foreach($plans as $plan)
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="px-6 py-5 align-top">
                                    <div class="flex items-center gap-2 mb-1">
                                        <div class="font-bold text-slate-950 text-base">{{ $plan->name }}</div>
                                        @if($plan->is_popular)
                                            <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-700">Popular</span>
                                        @endif
                                        @if($plan->has_free_trial)
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-emerald-700">1 Month Trial</span>
                                        @endif
                                        @if($plan->disable_staff_tracking)
                                            <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-rose-700">Tracking Off</span>
                                        @endif
                                    </div>
                                    <div class="mt-2 text-slate-600 leading-relaxed line-clamp-3">{{ $plan->description }}</div>
                                </td>

                                <td class="px-6 py-5 align-top whitespace-nowrap">
                                    <span class="font-bold text-slate-950">Rs. {{ number_format((float) $plan->monthly_price, 2) }}</span>
                                    <div class="text-slate-500 text-xs mt-1 capitalize">{{ $plan->billing_cycle }}</div>
                                    @include('super-admin.partials.razorpay-plan-badge', ['razorpayPlan' => $plan->razorpayPlanFor($razorpayMode, 'monthly')])
                                </td>

                                <td class="px-6 py-5 align-top whitespace-nowrap">
                                    <span class="font-bold text-slate-950">Rs. {{ number_format((float) $plan->yearly_price, 2) }}</span>
                                    <div class="text-slate-500 text-xs mt-1">Annual pricing</div>
                                    @include('super-admin.partials.razorpay-plan-badge', ['razorpayPlan' => $plan->razorpayPlanFor($razorpayMode, 'yearly')])
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <div class="text-slate-600 leading-relaxed">
                                        {{ Str::limit($plan->description ?: '-', 100, '...') }}
                                    </div>
                                </td>

                                <td class="px-6 py-5 align-top">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold {{ $plan->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                        <span class="mr-1.5 h-1.5 w-1.5 rounded-full {{ $plan->is_active ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                                        {{ $plan->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>

                                <td class="px-6 py-5 align-top text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-bold text-slate-700 shadow-sm hover:bg-slate-50 transition-all"
                                            data-plan-edit
                                            data-action="{{ route('super-admin.plans.update', $plan) }}"
                                            data-id="{{ $plan->id }}"
                                            data-name="{{ e($plan->name) }}"
                                            data-monthly-price="{{ $plan->monthly_price }}"
                                            data-yearly-price="{{ $plan->yearly_price }}"
                                            data-is-active="{{ $plan->is_active ? 1 : 0 }}"
                                            data-is-popular="{{ $plan->is_popular ? 1 : 0 }}"
                                            data-has-free-trial="{{ $plan->has_free_trial ? 1 : 0 }}"
                                            data-disable-staff-tracking="{{ $plan->disable_staff_tracking ? 1 : 0 }}"
                                            @foreach (['monthly', 'yearly'] as $cycle)
                                                data-razorpay-plan-id-{{ $cycle }}="{{ data_get($plan->razorpayPlanFor($razorpayMode, $cycle), 'source') === 'manual' ? data_get($plan->razorpayPlanFor($razorpayMode, $cycle), 'id') : '' }}"
                                                data-razorpay-auto-plan-id-{{ $cycle }}="{{ data_get($plan->razorpayPlanFor($razorpayMode, $cycle), 'source') !== 'manual' ? data_get($plan->razorpayPlanFor($razorpayMode, $cycle), 'id') : '' }}"
                                                data-razorpay-plan-amount-{{ $cycle }}="{{ data_get($plan->razorpayPlanFor($razorpayMode, $cycle), 'source') === 'manual' ? data_get($plan->razorpayPlanFor($razorpayMode, $cycle), 'amount') : '' }}"
                                            @endforeach
                                        >
                                            Edit
                                        </button>

                                        <form method="POST" action="{{ route('super-admin.plans.update', $plan) }}" class="inline-block">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="name" value="{{ $plan->name }}">
                                            <input type="hidden" name="monthly_price" value="{{ $plan->monthly_price }}">
                                            <input type="hidden" name="yearly_price" value="{{ $plan->yearly_price }}">
                                            <input type="hidden" name="is_popular" value="{{ $plan->is_popular ? 1 : 0 }}">
                                            <input type="hidden" name="has_free_trial" value="{{ $plan->has_free_trial ? 1 : 0 }}">
                                            <input type="hidden" name="disable_staff_tracking" value="{{ $plan->disable_staff_tracking ? 1 : 0 }}">
                                            <input type="hidden" name="is_active" value="{{ $plan->is_active ? 0 : 1 }}">

                                            <button
                                                type="submit"
                                                class="rounded-lg px-3 py-2 text-xs font-bold transition-all {{ $plan->is_active ? 'bg-rose-50 text-rose-600 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100' }}"
                                            >
                                                {{ $plan->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>

                                        <form
                                            method="POST"
                                            action="{{ route('super-admin.plans.destroy', $plan) }}"
                                            class="inline-flex items-center gap-2"
                                            @if(($plan->company_subscriptions_count ?? 0) === 0)
                                                data-swal-confirm="Delete this subscription plan permanently? This action cannot be undone."
                                                data-swal-title="Delete Subscription Plan"
                                                data-swal-confirm-button="Delete"
                                                data-swal-cancel-button="Cancel"
                                            @endif
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                @disabled(($plan->company_subscriptions_count ?? 0) > 0)
                                                class="rounded-xl px-3 py-2 text-xs font-semibold {{ ($plan->company_subscriptions_count ?? 0) > 0 ? 'cursor-not-allowed bg-slate-100 text-slate-400' : 'bg-rose-50 text-rose-700 hover:bg-rose-100' }}"
                                                title="{{ ($plan->company_subscriptions_count ?? 0) > 0 ? 'This plan is already assigned to company subscriptions.' : 'Delete this plan' }}"
                                            >
                                                {{ ($plan->company_subscriptions_count ?? 0) > 0 ? 'In Use' : 'Delete' }}
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <div id="plan-edit-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-950/55 px-3 py-3 sm:px-4 sm:py-4 lg:py-6">
            <div class="mx-auto flex w-full max-w-4xl min-h-0 max-h-[calc(100dvh-1.5rem)] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl sm:rounded-[32px]">
                <div class="shrink-0 flex items-center justify-between border-b border-slate-200 px-5 py-4 sm:px-6 sm:py-5">
                    <div>
                        <h3 class="text-xl font-bold text-slate-950 sm:text-2xl">Edit Subscription Plan</h3>
                        <p class="mt-1 text-sm text-slate-500">Update plan name and monthly/yearly pricing.</p>
                    </div>
                    <button type="button" class="rounded-full bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-200" data-plan-edit-close>
                        Close
                    </button>
                </div>
                <form id="planEditForm" method="POST" class="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-6 sm:py-6">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="form_type" value="plan-edit">
                    <input type="hidden" name="edit_plan_id" id="plan_edit_id" value="{{ $planEditState['id'] ?? '' }}">

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="lg:col-span-2">
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Plan Name</label>
                            <input id="plan_edit_name" type="text" name="name" minlength="2" maxlength="191" class="block w-full rounded-2xl border-slate-300" placeholder="Enter a Plan Name" required>
                            <x-input-error :messages="$errors->planEditForm->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Monthly Price</label>
                            <input id="plan_edit_monthly_price" type="number" name="monthly_price" step="0.01" min="0" max="999999.99" class="block w-full rounded-2xl border-slate-300" required>
                            <x-input-error :messages="$errors->planEditForm->get('monthly_price')" class="mt-2" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Yearly Price</label>
                            <input id="plan_edit_yearly_price" type="number" name="yearly_price" step="0.01" min="0" max="999999.99" class="block w-full rounded-2xl border-slate-300" required>
                            <x-input-error :messages="$errors->planEditForm->get('yearly_price')" class="mt-2" />
                        </div>
                        <div class="lg:col-span-2">
                            @include('super-admin.partials.razorpay-plan-fields', [
                                'bag' => 'planEditForm',
                                'prefix' => 'plan_edit',
                                'values' => [],
                            ])
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-6">
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="is_active" value="0">
                            <input id="plan_edit_is_active" type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-indigo-600">
                            <span>Active</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="is_popular" value="0">
                            <input id="plan_edit_is_popular" type="checkbox" name="is_popular" value="1" class="rounded border-slate-300 text-indigo-600">
                            <span>Popular / Featured</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="has_free_trial" value="0">
                            <input id="plan_edit_has_free_trial" type="checkbox" name="has_free_trial" value="1" class="rounded border-slate-300 text-indigo-600">
                            <span>Enable 1 Month Free Trial</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                            <input type="hidden" name="disable_staff_tracking" value="0">
                            <input id="plan_edit_disable_staff_tracking" type="checkbox" name="disable_staff_tracking" value="1" class="rounded border-slate-300 text-indigo-600">
                            <span>Disable Staff Tracking</span>
                        </label>
                    </div>

                    <div class="shrink-0 mt-8 flex flex-col-reverse gap-3 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-end">
                        <button type="button" class="rounded-2xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50" data-plan-edit-close>
                            Cancel
                        </button>
                        <button type="submit" class="rounded-2xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h3 class="text-xl font-bold text-slate-950">Company Subscriptions</h3>
                    <p class="mt-1 text-sm text-slate-500">Paginated list of all company subscriptions.</p>
                </div>
                <div class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">{{ $companySubscriptions->total() }} subscriptions</div>
            </div>

            <form method="GET" action="{{ route('super-admin.subscriptions') }}" class="grid gap-4 border-b border-slate-200 px-6 py-5 md:grid-cols-2 xl:grid-cols-5">
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Search</label>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="block w-full rounded-2xl border-slate-300" placeholder="Company or plan">
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Company</label>
                    <select name="company_id" class="block w-full rounded-2xl border-slate-300">
                        <option value="">All companies</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Plan</label>
                    <select name="subscription_plan_id" class="block w-full rounded-2xl border-slate-300">
                        <option value="">All plans</option>
                        @foreach($plans as $plan)
                            <option value="{{ $plan->id }}" @selected((string) ($filters['subscription_plan_id'] ?? '') === (string) $plan->id)>{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Status</label>
                    <select name="status" class="block w-full rounded-2xl border-slate-300">
                        <option value="">All statuses</option>
                        @foreach(['active', 'trialing', 'pending', 'expired', 'cancelled'] as $status)
                            <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ str($status)->headline() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-2 block text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Sort</label>
                    <select name="sort" class="block w-full rounded-2xl border-slate-300">
                        <option value="latest" @selected(($filters['sort'] ?? '') === 'latest')>Newest First</option>
                        <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Oldest First</option>
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
                <div class="flex items-end gap-3 md:col-span-2 xl:col-span-5">
                    <a href="{{ route('super-admin.subscriptions') }}" class="inline-flex items-center rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">Reset</a>
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-950 px-4 py-3 text-sm font-semibold text-white hover:bg-slate-800">Apply Filters</button>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-6 py-4 text-left">Company</th>
                            <th class="px-6 py-4 text-left">Plan</th>
                            <th class="px-6 py-4 text-left">License Keys</th>
                            <th class="px-6 py-4 text-left">Status</th>
                            <th class="px-6 py-4 text-left">Dates</th>
                            <th class="px-6 py-4 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($companySubscriptions as $subscription)
                            <tr>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-950">{{ $subscription->company?->name ?? '-' }}</div>
                                    <div class="text-slate-500">{{ $subscription->company?->email ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-slate-700">{{ $subscription->plan?->name ?? '-' }}</td>
                                <td class="px-6 py-4 text-slate-700">
                                    @php
                                        $licenseCount = $subscription->license_count ?? count($subscription->license_keys ?? []);
                                        if ($licenseCount <= 0 && filled($subscription->license_key)) {
                                            $licenseCount = 1;
                                        }
                                    @endphp
                                    <div class="font-semibold text-slate-950">{{ $licenseCount }} key(s)</div>
                                    <div class="mt-1 font-mono text-xs break-all text-slate-500">
                                        {{ $subscription->license_key ?? '-' }}
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold
                                        {{ $subscription->status === 'active' ? 'bg-emerald-100 text-emerald-700' : '' }}
                                        {{ $subscription->status === 'trialing' ? 'bg-blue-100 text-blue-700' : '' }}
                                        {{ $subscription->status === 'pending' ? 'bg-amber-100 text-amber-700' : '' }}
                                        {{ $subscription->status === 'expired' ? 'bg-slate-200 text-slate-700' : '' }}
                                        {{ $subscription->status === 'cancelled' ? 'bg-rose-100 text-rose-700' : '' }}">
                                        {{ str($subscription->status)->headline() }}
                                    </span>
                                    @if($subscription->is_trial)
                                        <div class="mt-2 text-xs font-semibold uppercase tracking-wide text-blue-700">Free trial</div>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-700">
                                    <div>Start: {{ $subscription->starts_at?->format('M d, Y') ?? '-' }}</div>
                                    <div>Ends: {{ $subscription->ends_at?->format('M d, Y') ?? '-' }}</div>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-950">Rs. {{ number_format((float) $subscription->amount_paid, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-slate-500">No subscriptions found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($companySubscriptions->hasPages())
                <div class="flex flex-col gap-3 border-t border-slate-200 px-6 py-4 md:flex-row md:items-center md:justify-between">
                    <div class="text-sm text-slate-500">
                        Showing {{ $companySubscriptions->firstItem() }} to {{ $companySubscriptions->lastItem() }} of {{ $companySubscriptions->total() }} records
                    </div>
                    <div>{{ $companySubscriptions->onEachSide(1)->links() }}</div>
                </div>
            @endif
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const today = '{{ now()->toDateString() }}';
            const activePlanPrices = @json($activePlanPrices);
            const planEditModal = document.getElementById('plan-edit-modal');
            const planEditForm = document.getElementById('planEditForm');
            const planEditBaseUrl = @js(url('/super-admin/plans'));
            const initialPlanEditState = @json($planEditState);
            const planEditFields = {
                id: document.getElementById('plan_edit_id'),
                name: document.getElementById('plan_edit_name'),
                monthlyPrice: document.getElementById('plan_edit_monthly_price'),
                yearlyPrice: document.getElementById('plan_edit_yearly_price'),
                isActive: document.getElementById('plan_edit_is_active'),
                isPopular: document.getElementById('plan_edit_is_popular'),
                hasFreeTrial: document.getElementById('plan_edit_has_free_trial'),
                disableStaffTracking: document.getElementById('plan_edit_disable_staff_tracking'),
            };
            const subscriptionPlanSelect = document.querySelector('select[name="subscription_plan_id"]');
            const subscriptionAmountInput = document.querySelector('input[name="amount_paid"]');
            const subscriptionUserCountInput = document.querySelector('input[name="user_count"]');

            // Shows the exact per-license amount (price + GST) the Razorpay plan must have.
            function refreshRazorpayAmountHints(form) {
                if (!form) return;
                form.querySelectorAll('[data-razorpay-amount-hint]').forEach((hint) => {
                    const cycle = hint.dataset.razorpayAmountHint;
                    const priceInput = form.querySelector(`input[name="${cycle}_price"]`);
                    const price = parseFloat(priceInput?.value || '0');
                    const gstRate = parseFloat(hint.dataset.gstRate || '0');
                    if (!(price > 0)) {
                        hint.textContent = `Set a ${cycle} price above 0 to link a Razorpay plan.`;
                        return;
                    }
                    const gst = Math.round(price * gstRate) / 100;
                    const total = (Math.round((price + gst) * 100) / 100).toFixed(2);
                    let message = `Razorpay plan must be Rs. ${total} per license (Rs. ${price.toFixed(2)} + ${gstRate}% GST), billed every 1 ${cycle === 'monthly' ? 'month' : 'year'}.`;

                    const idInput = form.querySelector(`input[name="razorpay_plan_id_${cycle}"]`);
                    const linkedId = idInput?.dataset.linkedId || '';
                    const linkedAmount = Number(idInput?.dataset.linkedAmount || 0);
                    const isStale = linkedId !== ''
                        && idInput.value.trim() === linkedId
                        && linkedAmount !== Math.round(Number(total) * 100);

                    if (isStale) {
                        message += ` The linked plan charges Rs. ${(linkedAmount / 100).toFixed(2)}, so it will be unlinked when you save. Paste a new plan ID, or leave it and a matching plan is created automatically.`;
                    }

                    hint.textContent = message;
                    hint.classList.toggle('text-amber-700', isStale);
                    hint.classList.toggle('font-semibold', isStale);
                    hint.classList.toggle('text-slate-500', !isStale);
                });
            }

            [document.getElementById('planForm'), planEditForm].forEach((form) => {
                if (!form) return;
                form.querySelectorAll('input[name="monthly_price"], input[name="yearly_price"], input[name^="razorpay_plan_id_"]').forEach((input) => {
                    input.addEventListener('input', () => refreshRazorpayAmountHints(form));
                });
                refreshRazorpayAmountHints(form);
            });

            function openPlanEditModal(plan) {
                if (!planEditModal || !planEditForm) {
                    return;
                }

                const planId = plan?.id || '';
                const planAction = plan?.action || (planId ? `${planEditBaseUrl}/${planId}` : planEditForm.action);

                planEditForm.action = planAction;
                if (planEditFields.id) planEditFields.id.value = planId;
                if (planEditFields.name) planEditFields.name.value = plan?.name || '';
                if (planEditFields.monthlyPrice) planEditFields.monthlyPrice.value = plan?.monthly_price ?? '';
                if (planEditFields.yearlyPrice) planEditFields.yearlyPrice.value = plan?.yearly_price ?? '';
                if (planEditFields.isActive) planEditFields.isActive.checked = !!Number(plan?.is_active ?? 0);
                if (planEditFields.isPopular) planEditFields.isPopular.checked = !!Number(plan?.is_popular ?? 0);
                if (planEditFields.hasFreeTrial) planEditFields.hasFreeTrial.checked = !!Number(plan?.has_free_trial ?? 0);
                if (planEditFields.disableStaffTracking) planEditFields.disableStaffTracking.checked = !!Number(plan?.disable_staff_tracking ?? 0);
                ['monthly', 'yearly'].forEach((cycle) => {
                    const input = document.getElementById(`plan_edit_razorpay_plan_id_${cycle}`);
                    if (!input) return;
                    input.value = plan?.[`razorpay_plan_id_${cycle}`] || '';
                    // Remember the currently linked plan so a price change can be flagged before saving.
                    input.dataset.linkedId = plan?.[`razorpay_plan_amount_${cycle}`] ? input.value : '';
                    input.dataset.linkedAmount = plan?.[`razorpay_plan_amount_${cycle}`] || '';
                    const autoPlanId = plan?.[`razorpay_auto_plan_id_${cycle}`] || '';
                    input.placeholder = autoPlanId ? `Auto-created: ${autoPlanId}` : 'plan_XXXXXXXXXXXXXX';
                });
                refreshRazorpayAmountHints(planEditForm);

                document.body.classList.add('overflow-hidden');
                planEditModal.scrollTop = 0;
                planEditForm.scrollTop = 0;
                planEditModal.classList.remove('hidden');
                planEditFields.name?.focus();
            }

            function closePlanEditModal() {
                if (!planEditModal) {
                    return;
                }

                planEditModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }

            function syncSubscriptionAmount() {
                if (!subscriptionPlanSelect || !subscriptionAmountInput) {
                    return;
                }

                const selectedOption = subscriptionPlanSelect.options[subscriptionPlanSelect.selectedIndex];
                const selectedPlanId = subscriptionPlanSelect.value;
                const planPrice = parseFloat(selectedOption?.dataset?.price ?? activePlanPrices[selectedPlanId] ?? '0.00') || 0;
                const userCount = Math.max(1, parseInt(subscriptionUserCountInput?.value, 10) || 1);

                subscriptionAmountInput.value = selectedPlanId ? (planPrice * userCount).toFixed(2) : '0.00';
            }

            $.validator.addMethod('todayOrFuture', function (value, element) {
                if (!value) {
                    return true;
                }

                return value >= today;
            }, 'Date must be today or a future date.');

            $.validator.addMethod('afterOrEqualField', function (value, element, selector) {
                if (!value) {
                    return true;
                }

                const compareValue = $(selector).val();
                if (!compareValue) {
                    return true;
                }

                return value >= compareValue;
            }, 'Date must be the same as or after the selected start date.');

            function planValidationOptions() {
                return {
                    rules: Object.assign({
                        name: {
                            required: true,
                            minlength: 2,
                            maxlength: 191,
                            normalizer: function (value) {
                                return $.trim(value);
                            }
                        },
                        monthly_price: {
                            required: true,
                            number: true,
                            min: 0,
                            max: 999999.99
                        },
                        yearly_price: {
                            required: true,
                            number: true,
                            min: 0,
                            max: 999999.99
                        },
                    }),
                    messages: Object.assign({
                        name: {
                            required: 'Please enter a plan name.',
                            minlength: 'Plan name must be at least 2 characters.',
                            maxlength: 'Plan name may not be greater than 191 characters.'
                        },
                        monthly_price: {
                            required: 'Please enter a monthly price.',
                            number: 'Please enter a valid monthly price.',
                            min: 'Monthly price must be at least 0.',
                            max: 'Monthly price may not be greater than 999999.99.'
                        },
                        yearly_price: {
                            required: 'Please enter a yearly price.',
                            number: 'Please enter a valid yearly price.',
                            min: 'Yearly price must be at least 0.',
                            max: 'Yearly price may not be greater than 999999.99.'
                        },
                    }),
                    errorElement: 'label',
                    errorClass: 'error',
                    highlight: function (element) {
                        $(element).addClass('error');
                    },
                    unhighlight: function (element) {
                        $(element).removeClass('error');
                    },
                    submitHandler: function (form) {
                        form.submit();
                    }
                };
            }

            $('#planForm').validate(planValidationOptions());
            $('#planEditForm').validate(planValidationOptions());

            $('#subscriptionForm').validate({
                ignore: [],
                errorPlacement: function (error, element) {
                    if (element.attr('name') === 'company_id') {
                        error.appendTo(element.closest('.relative').parent());
                    } else {
                        error.insertAfter(element);
                    }
                },
                rules: {
                    company_id: {
                        required: true
                    },
                    subscription_plan_id: {
                        required: true
                    },
                    status: {
                        required: true
                    },
                    user_count: {
                        required: true,
                        digits: true,
                        min: 1,
                        max: 100000
                    },
                    amount_paid: {
                        required: true,
                        number: true,
                        min: 0,
                        max: 99999999.99
                    },
                    starts_at: {
                        required: true,
                        dateISO: true,
                        todayOrFuture: true
                    },
                    trial_ends_at: {
                        dateISO: true,
                        todayOrFuture: true,
                        afterOrEqualField: 'input[name="starts_at"]'
                    },
                    ends_at: {
                        dateISO: true,
                        todayOrFuture: true,
                        afterOrEqualField: 'input[name="starts_at"]'
                    },
                    payment_method: {
                        minlength: 2,
                        maxlength: 50,
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    },
                    billing_reference: {
                        minlength: 2,
                        maxlength: 191,
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    },
                    notes: {
                        minlength: 2,
                        maxlength: 2000,
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    }
                },
                messages: {
                    company_id: {
                        required: 'Please select a company.'
                    },
                    subscription_plan_id: {
                        required: 'Please select a plan.'
                    },
                    status: {
                        required: 'Please select a status.'
                    },
                    user_count: {
                        required: 'Please enter the number of users.',
                        digits: 'User count must be a whole number.',
                        min: 'User count must be at least 1.',
                        max: 'User count may not exceed 100000.'
                    },
                    amount_paid: {
                        required: 'Please enter the amount paid.',
                        number: 'Please enter a valid amount.',
                        min: 'Amount paid must be at least 0.',
                        max: 'Amount paid may not be greater than 99999999.99.'
                    },
                    starts_at: {
                        required: 'Please select a start date.',
                        dateISO: 'Please enter a valid date.',
                        todayOrFuture: 'Start date must be today or a future date.'
                    },
                    trial_ends_at: {
                        dateISO: 'Please enter a valid trial end date.',
                        todayOrFuture: 'Trial end date must be today or a future date.',
                        afterOrEqualField: 'Trial end date must be the same as or after the start date.'
                    },
                    ends_at: {
                        dateISO: 'Please enter a valid end date.',
                        todayOrFuture: 'End date must be today or a future date.',
                        afterOrEqualField: 'End date must be the same as or after the start date.'
                    },
                    payment_method: {
                        minlength: 'Payment method must be at least 2 characters.',
                        maxlength: 'Payment method may not be greater than 50 characters.'
                    },
                    billing_reference: {
                        minlength: 'Billing reference must be at least 2 characters.',
                        maxlength: 'Billing reference may not be greater than 191 characters.'
                    },
                    notes: {
                        minlength: 'Notes must be at least 2 characters.',
                        maxlength: 'Notes may not be greater than 2000 characters.'
                    }
                },
                errorElement: 'label',
                errorClass: 'error',
                highlight: function (element) {
                    $(element).addClass('error');
                },
                unhighlight: function (element) {
                    $(element).removeClass('error');
                },
                submitHandler: function (form) {
                    syncSubscriptionAmount();
                    form.submit();
                }
            });

            if (subscriptionPlanSelect) {
                subscriptionPlanSelect.addEventListener('change', syncSubscriptionAmount);
                syncSubscriptionAmount();
            }

            if (subscriptionUserCountInput) {
                subscriptionUserCountInput.addEventListener('input', syncSubscriptionAmount);
            }

            document.querySelectorAll('[data-plan-edit]').forEach((button) => {
                button.addEventListener('click', function () {
                    openPlanEditModal({
                        id: this.dataset.id,
                        action: this.dataset.action,
                        name: this.dataset.name,
                        monthly_price: this.dataset.monthlyPrice,
                        yearly_price: this.dataset.yearlyPrice,
                        is_active: this.dataset.isActive,
                        is_popular: this.dataset.isPopular,
                        has_free_trial: this.dataset.hasFreeTrial,
                        disable_staff_tracking: this.dataset.disableStaffTracking,
                        razorpay_plan_id_monthly: this.dataset.razorpayPlanIdMonthly,
                        razorpay_plan_id_yearly: this.dataset.razorpayPlanIdYearly,
                        razorpay_auto_plan_id_monthly: this.dataset.razorpayAutoPlanIdMonthly,
                        razorpay_auto_plan_id_yearly: this.dataset.razorpayAutoPlanIdYearly,
                        razorpay_plan_amount_monthly: this.dataset.razorpayPlanAmountMonthly,
                        razorpay_plan_amount_yearly: this.dataset.razorpayPlanAmountYearly,
                    });
                });
            });

            document.querySelectorAll('[data-plan-edit-close]').forEach((button) => {
                button.addEventListener('click', closePlanEditModal);
            });

            if (planEditModal) {
                planEditModal.addEventListener('click', function (event) {
                    if (event.target === planEditModal) {
                        closePlanEditModal();
                    }
                });
            }

            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    closePlanEditModal();
                }
            });

            if (initialPlanEditState?.id) {
                openPlanEditModal({
                    id: initialPlanEditState.id,
                    action: `${planEditBaseUrl}/${initialPlanEditState.id}`,
                    name: initialPlanEditState.name,
                    monthly_price: initialPlanEditState.monthly_price,
                    yearly_price: initialPlanEditState.yearly_price,
                    is_active: initialPlanEditState.is_active,
                    is_popular: initialPlanEditState.is_popular,
                    has_free_trial: initialPlanEditState.has_free_trial,
                    disable_staff_tracking: initialPlanEditState.disable_staff_tracking,
                    razorpay_plan_id_monthly: initialPlanEditState.razorpay_plan_id_monthly,
                    razorpay_plan_id_yearly: initialPlanEditState.razorpay_plan_id_yearly,
                });
            }
        });
    </script>
</x-app-layout>
