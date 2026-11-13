<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-indigo-600 mb-1">
                    <a href="{{ route('super-admin.companies') }}" class="hover:underline flex items-center gap-1">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                        </svg>
                        Companies
                    </a>
                    <span>/</span>
                    <span>New Registration</span>
                </div>
                <h2 class="text-2xl font-bold text-slate-950">Register New Company</h2>
                <p class="mt-1 text-sm text-slate-500">Create an organization workspace, set up admin credentials, and configure subscription with payment gateway.</p>
            </div>
            <div>
                <a href="{{ route('super-admin.companies') }}" class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                    Cancel
                </a>
            </div>
        </div>
    </x-slot>

    <!-- Razorpay Checkout SDK -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>

    @if($errors->any())
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <div class="flex items-center gap-2 font-semibold">
                <svg class="h-5 w-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Please correct the errors below:
            </div>
            <ul class="mt-2 list-disc list-inside space-y-1 text-xs text-rose-700">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="registerCompanyForm" method="POST" action="{{ route('super-admin.companies.store') }}" class="space-y-6">
        @csrf

        <!-- Hidden Razorpay response inputs -->
        <input type="hidden" name="razorpay_order_id" id="razorpay_order_id" value="{{ old('razorpay_order_id') }}">
        <input type="hidden" name="razorpay_payment_id" id="razorpay_payment_id" value="{{ old('razorpay_payment_id') }}">
        <input type="hidden" name="razorpay_signature" id="razorpay_signature" value="{{ old('razorpay_signature') }}">

        <div class="grid gap-6 lg:grid-cols-12">
            <!-- Left Column: Company & Admin Information (7 cols) -->
            <div class="space-y-6 lg:col-span-7">
                <!-- Company Details Card -->
                <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-950">Company Profile</h3>
                            <p class="text-xs text-slate-500">Basic organization information and location.</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-5">
                        <div>
                            <label for="company_name" class="mb-2 block text-sm font-semibold text-slate-700">Company Name <span class="text-rose-500">*</span></label>
                            <input
                                id="company_name"
                                type="text"
                                name="company_name"
                                value="{{ old('company_name') }}"
                                class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g. SecureTech Systems Pvt Ltd"
                                required
                            >
                            <x-input-error :messages="$errors->get('company_name')" class="mt-2" />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="state" class="mb-2 block text-sm font-semibold text-slate-700">State</label>
                                <select
                                    id="state"
                                    name="state"
                                    class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Select State</option>
                                    @foreach($states as $state)
                                        <option value="{{ $state->name }}" data-state-id="{{ $state->id }}" @selected(old('state') === $state->name)>{{ $state->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('state')" class="mt-2" />
                            </div>

                            <div>
                                <label for="city_select" class="mb-2 block text-sm font-semibold text-slate-700">City</label>
                                <select
                                    id="city_select"
                                    name="city"
                                    class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="">Select City</option>
                                    @if(old('city'))
                                        <option value="{{ old('city') }}" selected>{{ old('city') }}</option>
                                    @endif
                                </select>
                                <x-input-error :messages="$errors->get('city')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <label for="address" class="mb-2 block text-sm font-semibold text-slate-700">Office / Billing Address</label>
                            <textarea
                                id="address"
                                name="address"
                                rows="2"
                                class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Street, Building, Area, Pincode"
                            >{{ old('address') }}</textarea>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>

                        <div>
                            <label for="business_type" class="mb-2 block text-sm font-semibold text-slate-700">Business Type</label>
                            <select
                                id="business_type"
                                name="business_type"
                                class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="CCTV" @selected(old('business_type', 'CCTV') === 'CCTV')>CCTV & Surveillance</option>
                                <option value="Security Systems" @selected(old('business_type') === 'Security Systems')>Security & Access Control</option>
                                <option value="IT & Networking" @selected(old('business_type') === 'IT & Networking')>IT & Networking</option>
                                <option value="Electrical & Automation" @selected(old('business_type') === 'Electrical & Automation')>Electrical & Automation</option>
                                <option value="General Services" @selected(old('business_type') === 'General Services')>General Contractor / Services</option>
                            </select>
                            <x-input-error :messages="$errors->get('business_type')" class="mt-2" />
                        </div>
                    </div>
                </section>

                <!-- Admin User Credentials Card -->
                <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center gap-3 border-b border-slate-100 pb-4">
                        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-sky-50 text-sky-600">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-slate-950">Company Admin Account</h3>
                            <p class="text-xs text-slate-500">Sign-in credentials for the primary administrator.</p>
                        </div>
                    </div>

                    <div class="mt-6 space-y-5">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <label for="admin_name" class="mb-2 block text-sm font-semibold text-slate-700">Admin Full Name</label>
                                <input
                                    id="admin_name"
                                    type="text"
                                    name="admin_name"
                                    value="{{ old('admin_name') }}"
                                    class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="Leave blank for '{Company} Admin'"
                                >
                                <x-input-error :messages="$errors->get('admin_name')" class="mt-2" />
                            </div>

                            <div>
                                <label for="phone_number" class="mb-2 block text-sm font-semibold text-slate-700">Mobile Number (10 digits) <span class="text-rose-500">*</span></label>
                                <input
                                    id="phone_number"
                                    type="tel"
                                    name="phone_number"
                                    value="{{ old('phone_number') }}"
                                    maxlength="10"
                                    class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="e.g. 9876543210"
                                    required
                                >
                                <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <label for="email" class="mb-2 block text-sm font-semibold text-slate-700">Admin Email Address (Username) <span class="text-rose-500">*</span></label>
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="admin@company.com"
                                required
                            >
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                            <div class="flex items-center justify-between mb-3">
                                <label class="text-sm font-semibold text-slate-800">Admin Password <span class="text-rose-500">*</span></label>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        id="btn_generate_password"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition"
                                    >
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        Generate Password
                                    </button>
                                    <button
                                        type="button"
                                        id="btn_toggle_visibility"
                                        class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition"
                                    >
                                        <span id="toggle_visibility_text">Show</span>
                                    </button>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        minlength="8"
                                        class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Min. 8 characters"
                                        required
                                    >
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                </div>
                                <div>
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        minlength="8"
                                        class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Confirm Password"
                                        required
                                    >
                                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                                </div>
                            </div>
                            <p id="password_copied_hint" class="mt-2 text-xs font-medium text-emerald-600 hidden">Password generated and copied to clipboard!</p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Right Column: Subscription & Payment Option (5 cols) -->
            <div class="space-y-6 lg:col-span-5">
                <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-start justify-between border-b border-slate-100 pb-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-slate-950">Subscription & Payment</h3>
                                <p class="text-xs text-slate-500">Plan selection and payment processing.</p>
                            </div>
                        </div>

                        <!-- Subscription toggle -->
                        <label class="relative inline-flex cursor-pointer items-center">
                            <input
                                type="checkbox"
                                name="enable_subscription"
                                id="enable_subscription"
                                value="1"
                                class="peer sr-only"
                                @checked(old('enable_subscription', '1') === '1')
                            >
                            <div class="peer h-6 w-11 rounded-full bg-slate-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-slate-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-emerald-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-focus:outline-none"></div>
                        </label>
                    </div>

                    <!-- Subscription Details Body -->
                    <div id="subscription_fields_container" class="mt-6 space-y-5">
                        <!-- Plan Selector -->
                        <div>
                            <label for="subscription_plan_id" class="mb-2 block text-sm font-semibold text-slate-700">Select Plan <span class="text-rose-500">*</span></label>
                            <select
                                id="subscription_plan_id"
                                name="subscription_plan_id"
                                class="block w-full rounded-2xl border-slate-300 text-sm font-medium focus:border-indigo-500 focus:ring-indigo-500"
                            >
                                <option value="">Choose a plan...</option>
                                @foreach($plans as $plan)
                                    <option
                                        value="{{ $plan->id }}"
                                        data-monthly="{{ number_format((float) $plan->monthly_price, 2, '.', '') }}"
                                        data-yearly="{{ number_format((float) $plan->yearly_price, 2, '.', '') }}"
                                        data-trial="{{ $plan->has_free_trial ? '1' : '0' }}"
                                        data-name="{{ $plan->name }}"
                                        @selected(old('subscription_plan_id') == $plan->id || ($loop->first && !old('subscription_plan_id')))>
                                        {{ $plan->name }} (Rs. {{ number_format((float) $plan->monthly_price, 0) }}/mo @if($gstRate > 0)+ {{ $gstRate }}% GST @endif)
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('subscription_plan_id')" class="mt-2" />
                        </div>

                        <!-- Billing Cycle -->
                        <div>
                            <label class="mb-2 block text-sm font-semibold text-slate-700">Billing Cycle</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="cycle-pill flex cursor-pointer items-center justify-center rounded-2xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold transition hover:bg-slate-50">
                                    <input
                                        type="radio"
                                        name="billing_cycle"
                                        value="monthly"
                                        class="sr-only"
                                        @checked(old('billing_cycle', 'monthly') === 'monthly')
                                    >
                                    <span>Monthly</span>
                                </label>
                                <label class="cycle-pill flex cursor-pointer items-center justify-center rounded-2xl border border-slate-200 px-4 py-2.5 text-center text-sm font-semibold transition hover:bg-slate-50">
                                    <input
                                        type="radio"
                                        name="billing_cycle"
                                        value="yearly"
                                        class="sr-only"
                                        @checked(old('billing_cycle') === 'yearly')
                                    >
                                    <span>Yearly</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('billing_cycle')" class="mt-2" />
                        </div>

                        <!-- User Seats & Status -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="user_count" class="mb-2 block text-sm font-semibold text-slate-700">Staff / Seat Count</label>
                                <input
                                    id="user_count"
                                    type="number"
                                    name="user_count"
                                    min="1"
                                    max="100000"
                                    step="1"
                                    value="{{ old('user_count', 1) }}"
                                    class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                <x-input-error :messages="$errors->get('user_count')" class="mt-2" />
                            </div>

                            <div>
                                <label for="subscription_status" class="mb-2 block text-sm font-semibold text-slate-700">Status</label>
                                <select
                                    id="subscription_status"
                                    name="subscription_status"
                                    class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="pending" @selected(old('subscription_status', 'pending') === 'pending')>Pending Payment</option>
                                    <option value="active" @selected(old('subscription_status') === 'active')>Active (Paid)</option>
                                    <option value="trialing" @selected(old('subscription_status') === 'trialing')>Trialing (Free Trial)</option>
                                </select>
                                <x-input-error :messages="$errors->get('subscription_status')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Amount Paid & Payment Method -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="flex items-center justify-between mb-2">
                                    <label for="amount_paid" class="block text-sm font-semibold text-slate-700">Amount to Pay (Rs.) <span class="text-rose-500">*</span></label>
                                    @if($gstRate > 0)
                                        <span class="inline-flex items-center rounded-full bg-indigo-50 border border-indigo-100 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                                            + {{ $gstRate }}% GST Included
                                        </span>
                                    @endif
                                </div>
                                <input
                                    id="amount_paid"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="99999999.99"
                                    name="amount_paid"
                                    value="{{ old('amount_paid', '0.00') }}"
                                    class="block w-full rounded-2xl border-slate-300 text-sm font-semibold text-slate-900 focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                <div id="gst_breakdown_bar" class="mt-2 rounded-xl bg-slate-50 border border-slate-200/80 px-3 py-2 text-[11px] text-slate-600 flex items-center justify-between">
                                    <span>Base: <strong id="breakdown_subtotal" class="text-slate-800">Rs. 0.00</strong></span>
                                    <span class="text-slate-400">+</span>
                                    <span>GST ({{ $gstRate }}%): <strong id="breakdown_gst" class="text-indigo-600">Rs. 0.00</strong></span>
                                    <span class="text-slate-400">=</span>
                                    <span>Total: <strong id="breakdown_total" class="text-slate-900">Rs. 0.00</strong></span>
                                </div>
                                <p class="mt-1 text-[11px] text-slate-400">Total charge includes {{ $gstRate }}% GST (configured in Admin Settings).</p>
                                <x-input-error :messages="$errors->get('amount_paid')" class="mt-2" />
                            </div>

                            <div>
                                <label for="payment_method" class="mb-2 block text-sm font-semibold text-slate-700">Payment Option / Mode</label>
                                <select
                                    id="payment_method"
                                    name="payment_method"
                                    class="block w-full rounded-2xl border-slate-300 text-sm font-semibold focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                    <option value="Razorpay" @selected(old('payment_method', 'Razorpay') === 'Razorpay')>⚡ Online Payment Gateway (Razorpay - UPI / Cards)</option>
                                    <option value="Cash" @selected(old('payment_method') === 'Cash')>Cash (Offline)</option>
                                    <option value="Bank Transfer" @selected(old('payment_method') === 'Bank Transfer')>Bank Transfer / NEFT / IMPS (Offline)</option>
                                    <option value="UPI" @selected(old('payment_method') === 'UPI')>UPI Manual (Offline)</option>
                                    <option value="Cheque" @selected(old('payment_method') === 'Cheque')>Cheque (Offline)</option>
                                    <option value="Free Trial" @selected(old('payment_method') === 'Free Trial')>Free Trial</option>
                                    <option value="Waived" @selected(old('payment_method') === 'Waived')>Waived / Complimentary</option>
                                    <option value="Other" @selected(old('payment_method') === 'Other')>Other</option>
                                </select>
                                <input
                                    id="payment_method_custom"
                                    type="text"
                                    name="payment_method_custom"
                                    value="{{ old('payment_method_custom') }}"
                                    placeholder="Enter custom payment method"
                                    class="mt-2 hidden block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <x-input-error :messages="$errors->get('payment_method')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Gateway Helper Box -->
                        <div id="gateway_notice_box" class="rounded-2xl border border-indigo-100 bg-indigo-50/70 p-3.5 text-xs text-indigo-900 flex items-start gap-2.5">
                            <svg class="h-5 w-5 text-indigo-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            <div>
                                <span class="font-bold">Payment Gateway Active:</span> Clicking "Complete Registration & Activate" will immediately open the secure Razorpay checkout popup (supporting UPI, Cards, Netbanking & Wallets).
                            </div>
                        </div>

                        <!-- Billing Reference / Cheque No / Notes -->
                        <div>
                            <label for="billing_reference" class="mb-2 block text-sm font-semibold text-slate-700">Billing Reference / Note</label>
                            <input
                                id="billing_reference"
                                type="text"
                                name="billing_reference"
                                value="{{ old('billing_reference') }}"
                                class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Auto-generated if left empty (e.g. TXN-10029384)"
                            >
                            <x-input-error :messages="$errors->get('billing_reference')" class="mt-2" />
                        </div>

                        <!-- Dates: Starts At and Ends At -->
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label for="starts_at" class="mb-2 block text-sm font-semibold text-slate-700">Starts At <span class="text-rose-500">*</span></label>
                                <input
                                    id="starts_at"
                                    type="date"
                                    name="starts_at"
                                    value="{{ old('starts_at', now()->toDateString()) }}"
                                    class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    required
                                >
                                <x-input-error :messages="$errors->get('starts_at')" class="mt-2" />
                            </div>

                            <div>
                                <label for="ends_at" class="mb-2 block text-sm font-semibold text-slate-700">Ends At</label>
                                <input
                                    id="ends_at"
                                    type="date"
                                    name="ends_at"
                                    value="{{ old('ends_at', now()->addMonth()->toDateString()) }}"
                                    class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                >
                                <x-input-error :messages="$errors->get('ends_at')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="mb-2 block text-sm font-semibold text-slate-700">Notes / Remarks</label>
                            <textarea
                                id="notes"
                                name="notes"
                                rows="2"
                                class="block w-full rounded-2xl border-slate-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Internal note regarding this company registration or payment terms"
                            >{{ old('notes') }}</textarea>
                            <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                        </div>
                    </div>

                    <!-- Disabled State Notice -->
                    <div id="subscription_disabled_notice" class="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500 hidden">
                        <svg class="mx-auto h-8 w-8 text-slate-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        <p class="font-medium text-slate-700">No subscription will be assigned now.</p>
                        <p class="mt-1 text-xs text-slate-400">The company will be created without an active plan, and you can assign one later from the Subscriptions management tab.</p>
                    </div>
                </section>

                <!-- Summary Card -->
                <section class="rounded-[28px] border border-slate-200 bg-slate-900 text-white p-6 shadow-sm">
                    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Order & Registration Summary</h4>
                        <span id="gateway_badge" class="inline-flex items-center gap-1 rounded-full bg-indigo-500/20 px-2.5 py-0.5 text-[11px] font-semibold text-indigo-300">
                            <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Razorpay Gateway
                        </span>
                    </div>

                    <div class="mt-4 space-y-3 text-sm">
                        <div class="flex justify-between border-b border-slate-800 pb-2">
                            <span class="text-slate-400">Company Name</span>
                            <span id="summary_company_name" class="font-medium text-white">-</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-800 pb-2">
                            <span class="text-slate-400">Plan</span>
                            <span id="summary_plan_name" class="font-medium text-white">-</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-800 pb-2">
                            <span class="text-slate-400">Cycle & Seats</span>
                            <span id="summary_cycle_seats" class="font-medium text-white">Monthly &bull; 1 Seat</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-800 pb-2">
                            <span class="text-slate-400">Base Subtotal</span>
                            <span id="summary_subtotal" class="font-medium text-slate-300">Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-800 pb-2">
                            <span class="text-slate-400">GST ({{ $gstRate }}%)</span>
                            <span id="summary_gst" class="font-medium text-indigo-300">Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between border-b border-slate-800 pb-2">
                            <span class="text-slate-400">Payment Option</span>
                            <span id="summary_payment_mode" class="font-medium text-indigo-300">Online Gateway</span>
                        </div>
                        <div class="flex justify-between pt-1 text-base">
                            <span class="font-bold text-slate-300">Total Amount</span>
                            <span id="summary_total_amount" class="font-extrabold text-emerald-400">Rs. 0.00</span>
                        </div>
                    </div>

                    <div class="mt-6">
                        <button
                            type="button"
                            id="submit_button"
                            class="w-full inline-flex justify-center items-center gap-2 rounded-2xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg transition hover:bg-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 cursor-pointer"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span id="submit_button_text">Complete Registration & Activate</span>
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </form>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Data maps
            const statesData = @json($states);
            const citiesData = @json($cities);
            const razorpayKey = "{{ $razorpayKey ?? config('services.razorpay.key_id') }}";
            const orderCreateUrl = "{{ route('super-admin.companies.payment-order') }}";
            const csrfToken = "{{ csrf_token() }}";

            // Elements
            const form = document.getElementById('registerCompanyForm');
            const stateSelect = document.getElementById('state');
            const citySelect = document.getElementById('city_select');

            const companyNameInput = document.getElementById('company_name');
            const adminNameInput = document.getElementById('admin_name');
            const adminEmailInput = document.getElementById('email');
            const adminPhoneInput = document.getElementById('phone_number');
            const adminPasswordInput = document.getElementById('password');
            const confirmPasswordInput = document.getElementById('password_confirmation');
            const btnGeneratePassword = document.getElementById('btn_generate_password');
            const btnToggleVisibility = document.getElementById('btn_toggle_visibility');
            const toggleVisibilityText = document.getElementById('toggle_visibility_text');
            const passwordCopiedHint = document.getElementById('password_copied_hint');

            const enableSubscriptionToggle = document.getElementById('enable_subscription');
            const subscriptionFieldsContainer = document.getElementById('subscription_fields_container');
            const subscriptionDisabledNotice = document.getElementById('subscription_disabled_notice');

            const planSelect = document.getElementById('subscription_plan_id');
            const cycleRadios = document.querySelectorAll('input[name="billing_cycle"]');
            const userCountInput = document.getElementById('user_count');
            const statusSelect = document.getElementById('subscription_status');
            const amountPaidInput = document.getElementById('amount_paid');
            const paymentMethodSelect = document.getElementById('payment_method');
            const paymentMethodCustomInput = document.getElementById('payment_method_custom');
            const billingReferenceInput = document.getElementById('billing_reference');
            const startsAtInput = document.getElementById('starts_at');
            const endsAtInput = document.getElementById('ends_at');

            // Hidden Razorpay Inputs
            const rzpOrderIdInput = document.getElementById('razorpay_order_id');
            const rzpPaymentIdInput = document.getElementById('razorpay_payment_id');
            const rzpSignatureInput = document.getElementById('razorpay_signature');

            // UI Elements
            const gatewayNoticeBox = document.getElementById('gateway_notice_box');
            const gatewayBadge = document.getElementById('gateway_badge');
            const submitBtn = document.getElementById('submit_button');
            const submitBtnText = document.getElementById('submit_button_text');

            // Summary Elements
            const summaryCompanyName = document.getElementById('summary_company_name');
            const summaryPlanName = document.getElementById('summary_plan_name');
            const summaryCycleSeats = document.getElementById('summary_cycle_seats');
            const summarySubtotal = document.getElementById('summary_subtotal');
            const summaryGst = document.getElementById('summary_gst');
            const summaryPaymentMode = document.getElementById('summary_payment_mode');
            const summaryTotalAmount = document.getElementById('summary_total_amount');

            // Breakdown Badge Elements
            const breakdownSubtotal = document.getElementById('breakdown_subtotal');
            const breakdownGst = document.getElementById('breakdown_gst');
            const breakdownTotal = document.getElementById('breakdown_total');

            const gstRate = {{ (float) $gstRate }};

            // 1. State / City Dropdowns
            function populateCities(selectedStateName) {
                const currentSelectedCity = citySelect.value || "{{ old('city') }}";
                citySelect.innerHTML = '<option value="">Select City</option>';

                if (!selectedStateName) {
                    citiesData.forEach(city => {
                        const opt = document.createElement('option');
                        opt.value = city.name;
                        opt.textContent = city.name;
                        citySelect.appendChild(opt);
                    });
                    return;
                }

                const stateObj = statesData.find(s => s.name.toLowerCase() === selectedStateName.toLowerCase());
                if (stateObj) {
                    const filteredCities = citiesData.filter(c => c.state_id === stateObj.id);
                    filteredCities.forEach(city => {
                        const opt = document.createElement('option');
                        opt.value = city.name;
                        opt.textContent = city.name;
                        if (city.name.toLowerCase() === currentSelectedCity.toLowerCase()) {
                            opt.selected = true;
                        }
                        citySelect.appendChild(opt);
                    });
                }
            }

            stateSelect.addEventListener('change', function () {
                populateCities(this.value);
            });

            if (stateSelect.value) {
                populateCities(stateSelect.value);
            } else {
                populateCities('');
            }

            // 2. Password Generator
            function generateStrongPassword() {
                const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%&*?";
                let password = "";
                password += "ABCDEFGHIJKLMNOPQRSTUVWXYZ"[Math.floor(Math.random() * 26)];
                password += "abcdefghijklmnopqrstuvwxyz"[Math.floor(Math.random() * 26)];
                password += "0123456789"[Math.floor(Math.random() * 10)];
                password += "!@#$%&*?"[Math.floor(Math.random() * 8)];
                for (let i = 4; i < 12; i++) {
                    password += chars[Math.floor(Math.random() * chars.length)];
                }
                password = password.split('').sort(() => 0.5 - Math.random()).join('');

                adminPasswordInput.value = password;
                confirmPasswordInput.value = password;
                adminPasswordInput.type = "text";
                confirmPasswordInput.type = "text";
                toggleVisibilityText.textContent = "Hide";

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(password).then(() => {
                        passwordCopiedHint.classList.remove('hidden');
                        setTimeout(() => passwordCopiedHint.classList.add('hidden'), 4000);
                    });
                }
            }

            btnGeneratePassword.addEventListener('click', generateStrongPassword);

            btnToggleVisibility.addEventListener('click', function () {
                const isPassword = adminPasswordInput.type === "password";
                adminPasswordInput.type = isPassword ? "text" : "password";
                confirmPasswordInput.type = isPassword ? "text" : "password";
                toggleVisibilityText.textContent = isPassword ? "Hide" : "Show";
            });

            // 3. Subscription Toggle
            function toggleSubscriptionSection() {
                const isEnabled = enableSubscriptionToggle.checked;
                if (isEnabled) {
                    subscriptionFieldsContainer.classList.remove('hidden');
                    subscriptionDisabledNotice.classList.add('hidden');
                    planSelect.setAttribute('required', 'required');
                } else {
                    subscriptionFieldsContainer.classList.add('hidden');
                    subscriptionDisabledNotice.classList.remove('hidden');
                    planSelect.removeAttribute('required');
                }
                updateSummary();
            }

            enableSubscriptionToggle.addEventListener('change', toggleSubscriptionSection);
            toggleSubscriptionSection();

            // 4. Payment Method Custom input toggle & gateway notice
            function updatePaymentMethodState() {
                const method = paymentMethodSelect.value;
                const isOnlineGateway = method === 'Razorpay';

                if (method === 'Other') {
                    paymentMethodCustomInput.classList.remove('hidden');
                    paymentMethodCustomInput.setAttribute('required', 'required');
                } else {
                    paymentMethodCustomInput.classList.add('hidden');
                    paymentMethodCustomInput.removeAttribute('required');
                }

                if (isOnlineGateway) {
                    gatewayNoticeBox?.classList.remove('hidden');
                    gatewayBadge?.classList.remove('hidden');
                    submitBtnText.textContent = 'Pay & Complete Activation';
                } else {
                    gatewayNoticeBox?.classList.add('hidden');
                    gatewayBadge?.classList.add('hidden');
                    submitBtnText.textContent = 'Complete Registration & Activate';
                }

                if (method === 'Free Trial') {
                    statusSelect.value = 'trialing';
                    calculatePricingAndDates();
                } else if (method === 'Waived') {
                    amountPaidInput.value = '0.00';
                    updateSummary();
                }

                updateSummary();
            }

            paymentMethodSelect.addEventListener('change', updatePaymentMethodState);

            // 5. Billing Cycle Pills Visual Update
            function updateCyclePills() {
                cycleRadios.forEach(radio => {
                    const pill = radio.closest('.cycle-pill');
                    if (radio.checked) {
                        pill.classList.add('border-indigo-600', 'bg-indigo-50', 'text-indigo-700');
                        pill.classList.remove('border-slate-200', 'text-slate-700');
                    } else {
                        pill.classList.remove('border-indigo-600', 'bg-indigo-50', 'text-indigo-700');
                        pill.classList.add('border-slate-200', 'text-slate-700');
                    }
                });
            }

            cycleRadios.forEach(radio => {
                radio.addEventListener('change', function () {
                    updateCyclePills();
                    calculatePricingAndDates();
                });
            });
            updateCyclePills();

            // Helper: Update breakdown display
            function updateBreakdown(subtotal, gst, total) {
                const subStr = 'Rs. ' + Number(subtotal).toFixed(2);
                const gstStr = 'Rs. ' + Number(gst).toFixed(2);
                const totStr = 'Rs. ' + Number(total).toFixed(2);

                if (breakdownSubtotal) breakdownSubtotal.textContent = subStr;
                if (breakdownGst) breakdownGst.textContent = gstStr;
                if (breakdownTotal) breakdownTotal.textContent = totStr;
                if (summarySubtotal) summarySubtotal.textContent = subStr;
                if (summaryGst) summaryGst.textContent = gstStr;
                if (summaryTotalAmount) summaryTotalAmount.textContent = totStr;
            }

            // 6. Pricing & Dates Calculation
            function calculatePricingAndDates() {
                const selectedOption = planSelect.options[planSelect.selectedIndex];
                const cycle = document.querySelector('input[name="billing_cycle"]:checked')?.value || 'monthly';
                const seats = Math.max(1, parseInt(userCountInput.value || 1, 10));
                const status = statusSelect.value;
                const method = paymentMethodSelect.value;

                if (!selectedOption || !selectedOption.value) {
                    amountPaidInput.value = '0.00';
                    updateBreakdown(0, 0, 0);
                    updateSummary();
                    return;
                }

                const monthlyPrice = parseFloat(selectedOption.dataset.monthly || 0);
                const yearlyPrice = parseFloat(selectedOption.dataset.yearly || 0);
                const unitPrice = cycle === 'yearly' ? yearlyPrice : monthlyPrice;

                let subtotal = 0;
                let gstAmount = 0;
                let total = 0;

                if (status === 'trialing' || method === 'Free Trial' || method === 'Waived') {
                    subtotal = 0;
                    gstAmount = 0;
                    total = 0;
                    amountPaidInput.value = '0.00';
                } else {
                    subtotal = Math.round(unitPrice * seats * 100) / 100;
                    gstAmount = gstRate > 0 ? Math.round((subtotal * (gstRate / 100)) * 100) / 100 : 0;
                    total = Math.round((subtotal + gstAmount) * 100) / 100;
                    amountPaidInput.value = total.toFixed(2);
                }

                updateBreakdown(subtotal, gstAmount, total);

                const startsAtVal = startsAtInput.value ? new Date(startsAtInput.value) : new Date();
                if (!isNaN(startsAtVal.getTime())) {
                    const endsAtDate = new Date(startsAtVal);
                    if (status === 'trialing') {
                        endsAtDate.setDate(endsAtDate.getDate() + 15);
                    } else if (cycle === 'yearly') {
                        endsAtDate.setFullYear(endsAtDate.getFullYear() + 1);
                    } else {
                        endsAtDate.setMonth(endsAtDate.getMonth() + 1);
                    }
                    endsAtInput.value = endsAtDate.toISOString().split('T')[0];
                }

                updateSummary();
            }

            planSelect.addEventListener('change', calculatePricingAndDates);
            userCountInput.addEventListener('input', calculatePricingAndDates);
            statusSelect.addEventListener('change', calculatePricingAndDates);
            startsAtInput.addEventListener('change', calculatePricingAndDates);

            // 7. Live Summary Update
            function updateSummary() {
                const companyName = companyNameInput.value.trim() || '-';
                summaryCompanyName.textContent = companyName;

                if (!enableSubscriptionToggle.checked) {
                    summaryPlanName.textContent = 'None (Assign Later)';
                    summaryCycleSeats.textContent = '-';
                    summaryPaymentMode.textContent = 'No Subscription';
                    summaryTotalAmount.textContent = 'Rs. 0.00';
                    if (summarySubtotal) summarySubtotal.textContent = 'Rs. 0.00';
                    if (summaryGst) summaryGst.textContent = 'Rs. 0.00';
                    return;
                }

                const selectedOption = planSelect.options[planSelect.selectedIndex];
                const planName = (selectedOption && selectedOption.value) ? selectedOption.dataset.name : 'No Plan Selected';
                const cycle = document.querySelector('input[name="billing_cycle"]:checked')?.value || 'monthly';
                const seats = Math.max(1, parseInt(userCountInput.value || 1, 10));
                const total = parseFloat(amountPaidInput.value || 0);
                const method = paymentMethodSelect.value;

                summaryPlanName.textContent = planName;
                summaryCycleSeats.textContent = (cycle.charAt(0).toUpperCase() + cycle.slice(1)) + ' • ' + seats + (seats > 1 ? ' Seats' : ' Seat');
                summaryPaymentMode.textContent = method === 'Razorpay' ? 'Online Gateway (Razorpay)' : method;
                summaryTotalAmount.textContent = 'Rs. ' + total.toFixed(2);

                let subtotal = total;
                let gstAmount = 0;
                if (total > 0 && gstRate > 0) {
                    subtotal = Math.round((total / (1 + (gstRate / 100))) * 100) / 100;
                    gstAmount = Math.round((total - subtotal) * 100) / 100;
                }
                if (summarySubtotal) summarySubtotal.textContent = 'Rs. ' + subtotal.toFixed(2);
                if (summaryGst) summaryGst.textContent = 'Rs. ' + gstAmount.toFixed(2);
            }

            companyNameInput.addEventListener('input', updateSummary);
            amountPaidInput.addEventListener('input', function () {
                const enteredTotal = parseFloat(amountPaidInput.value || 0);
                let subtotal = enteredTotal;
                let gstAmount = 0;
                if (enteredTotal > 0 && gstRate > 0) {
                    subtotal = Math.round((enteredTotal / (1 + (gstRate / 100))) * 100) / 100;
                    gstAmount = Math.round((enteredTotal - subtotal) * 100) / 100;
                }
                updateBreakdown(subtotal, gstAmount, enteredTotal);
                updateSummary();
            });

            // 8. Payment Gateway Button Click Handler
            function resetSubmitButton() {
                submitBtn.disabled = false;
                const isGateway = paymentMethodSelect.value === 'Razorpay' && enableSubscriptionToggle.checked && parseFloat(amountPaidInput.value || 0) > 0;
                submitBtnText.textContent = isGateway ? 'Pay & Complete Activation' : 'Complete Registration & Activate';
            }

            submitBtn.addEventListener('click', async function (e) {
                // Step A: Client-side Form Validation
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return;
                }

                // Password Confirmation Match check
                if (adminPasswordInput.value !== confirmPasswordInput.value) {
                    alert('Passwords do not match. Please verify admin password and confirmation.');
                    confirmPasswordInput.focus();
                    return;
                }

                // Phone number digits check
                const phoneCleaned = adminPhoneInput.value.replace(/\D/g, '');
                if (phoneCleaned.length !== 10) {
                    alert('Please enter a valid 10-digit mobile number.');
                    adminPhoneInput.focus();
                    return;
                }

                const isSubscriptionEnabled = enableSubscriptionToggle.checked;
                const paymentMethod = paymentMethodSelect.value;
                const amount = parseFloat(amountPaidInput.value || 0);

                // If already paid via Razorpay (callback set rzp_payment_id) or offline payment, submit form
                if (rzpPaymentIdInput.value || !isSubscriptionEnabled || amount <= 0 || paymentMethod !== 'Razorpay') {
                    submitBtn.disabled = true;
                    submitBtnText.textContent = 'Processing Registration...';
                    form.submit();
                    return;
                }

                // Step B: Trigger Razorpay Payment Gateway
                submitBtn.disabled = true;
                submitBtnText.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Opening Payment Gateway...</span>';

                try {
                    const cycle = document.querySelector('input[name="billing_cycle"]:checked')?.value || 'monthly';
                    const orderPayload = {
                        company_name: companyNameInput.value.trim(),
                        email: adminEmailInput.value.trim(),
                        phone_number: adminPhoneInput.value.trim(),
                        subscription_plan_id: planSelect.value,
                        billing_cycle: cycle,
                        amount_paid: amount
                    };

                    const response = await fetch(orderCreateUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify(orderPayload)
                    });

                    if (!response.ok) {
                        const errData = await response.json().catch(() => ({}));
                        throw new Error(errData.message || 'Unable to create payment order. Please check inputs.');
                    }

                    const orderData = await response.json();

                    // Open Razorpay Checkout modal
                    const rzpOptions = {
                        key: orderData.key || razorpayKey,
                        amount: orderData.amount,
                        currency: orderData.currency || 'INR',
                        name: companyNameInput.value.trim(),
                        description: (orderData.plan_name || 'Subscription') + ' Plan Activation' + (gstRate > 0 ? ' (incl. ' + gstRate + '% GST)' : ''),
                        order_id: (orderData.demo ? undefined : orderData.order_id),
                        prefill: {
                            name: adminNameInput.value.trim() || companyNameInput.value.trim(),
                            email: adminEmailInput.value.trim(),
                            contact: adminPhoneInput.value.trim()
                        },
                        notes: {
                            company_name: companyNameInput.value.trim(),
                            plan_name: orderData.plan_name,
                            billing_cycle: cycle,
                            gst_rate: gstRate + '%'
                        },
                        theme: {
                            color: '#4f46e5'
                        },
                        handler: function (rzpResponse) {
                            // Payment successful! Fill hidden inputs and submit form
                            rzpPaymentIdInput.value = rzpResponse.razorpay_payment_id;
                            rzpOrderIdInput.value = rzpResponse.razorpay_order_id || orderData.order_id;
                            rzpSignatureInput.value = rzpResponse.razorpay_signature || '';
                            if (!billingReferenceInput.value.trim()) {
                                billingReferenceInput.value = rzpResponse.razorpay_payment_id;
                            }

                            submitBtn.disabled = true;
                            submitBtnText.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="animate-spin h-4 w-4" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> Payment Received! Activating Company...</span>';

                            form.submit();
                        },
                        modal: {
                            ondismiss: function () {
                                resetSubmitButton();
                            }
                        }
                    };

                    const rzp = new Razorpay(rzpOptions);
                    rzp.on('payment.failed', function (resp) {
                        const failMsg = (resp && resp.error) ? resp.error.description : 'Payment transaction failed or was cancelled.';
                        alert('Payment Notice: ' + failMsg);
                        resetSubmitButton();
                    });
                    rzp.open();

                } catch (err) {
                    alert('Could not initialize Payment Gateway: ' + err.message);
                    resetSubmitButton();
                }
            });

            // Initial calculation run
            calculatePricingAndDates();
            updatePaymentMethodState();
            updateSummary();
        });
    </script>
</x-app-layout>
