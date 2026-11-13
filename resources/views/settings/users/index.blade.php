<x-app-layout>
    @php
        $authUser = auth()->user();
        $canManageUsers = $authUser && $authUser->hasPermission('settings.users.manage');
        $availablePlanOptions = collect($availableSubscriptionOptions ?? []);
        $selectedPlanOption = $availablePlanOptions->firstWhere('company_subscription_id', (int) old('company_subscription_id'))
            ?? $availablePlanOptions->first();
    @endphp
    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">Company Users</h2>
                <p class="mt-1 text-sm text-slate-500">Manage users, roles, and activation status.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8">
        <div class="mx-auto w-full max-w-7xl space-y-6 px-2 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($canManageUsers)
            <div class="rounded-2xl border border-slate-200 bg-white p-3 shadow-sm sm:p-6">
                <div class="mb-5">
                    <h3 class="text-lg font-bold text-slate-900">Add User & Staff Details</h3>
                    <p class="mt-1 text-sm text-slate-500">Create a login and assign the correct role in one step.</p>
                </div>

                <form
                    id="create-user-form"
                    action="{{ route('settings.users.store') }}"
                    method="POST"
                    enctype="multipart/form-data"
                    novalidate
                    autocomplete="off"
                    onkeydown="if(event.key === 'Enter' && event.target.tagName !== 'TEXTAREA'){ event.preventDefault(); return false; }"
                    >
                    @csrf
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                        <div>
                            <x-input-label for="create_user_name" value="Full Name" />
                            <x-text-input id="create_user_name" name="name" placeholder="Employee Name" class="w-full mt-1" :value="old('name', request()->query('name'))" minlength="2" maxlength="191" required />
                            <p id="create-user-name-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('name') ? '' : 'hidden' }}">{{ $errors->first('name') }}</p>
                        </div>
                        <div>
                            <x-input-label for="create_user_email" value="Email Address" />
                            <x-text-input id="create_user_email" name="email" placeholder="Employee Email" type="text" inputmode="email" autocapitalize="none" class="w-full mt-1" :value="old('email', request()->query('email'))" maxlength="191" autocomplete="off" spellcheck="false" required />
                            <p id="create-user-email-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('email') ? '' : 'hidden' }}">{{ $errors->first('email') }}</p>
                        </div>
                        <div>
                            <x-input-label for="create_user_password" value="Password" />
                            <x-text-input id="create_user_password" name="password" placeholder="Enter Password" type="password" class="w-full mt-1" required />
                            <p id="create-user-password-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('password') ? '' : 'hidden' }}">{{ $errors->first('password') }}</p>
                        </div>
                        <div>
                            <x-input-label for="create_user_password_confirmation" value="Confirm Password" />
                            <x-text-input id="create_user_password_confirmation" name="password_confirmation" placeholder="Enter Confirm  Password" type="password" class="w-full mt-1" required />
                            <p id="create-user-password-confirmation-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('password_confirmation') ? '' : 'hidden' }}">{{ $errors->first('password_confirmation') }}</p>
                        </div>
                        <div>
                            <x-input-label for="create_user_contact_number" value="Contact Number" />
                            <x-text-input id="create_user_contact_number" name="contact_number" placeholder="Enter Contact Number" class="w-full mt-1" :value="old('contact_number', request()->query('contact_number'))" maxlength="10" required />
                            <p id="create-user-contact-number-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('contact_number') ? '' : 'hidden' }}">{{ $errors->first('contact_number') }}</p>
                        </div>
                        <div>
                            <x-input-label for="create_user_join_date" value="Joining Date" />
                            <x-text-input id="create_user_join_date" name="join_date" type="date" class="w-full mt-1" :value="old('join_date')" required />
                            <p id="create-user-join-date-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('join_date') ? '' : 'hidden' }}">{{ $errors->first('join_date') }}</p>
                        </div>
                        <div>
                            <x-input-label for="profile_photo" value="Profile Image" />

                            <label for="profile_photo" class="mt-1 flex items-center justify-between gap-3 rounded-md border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-3 py-2 cursor-pointer hover:border-indigo-300 hover:bg-indigo-50 transition h-[42px]">
                                <span class="text-sm font-medium text-indigo-700">Choose Image</span>
                                <span class="text-xs text-indigo-600">Max 2 MB</span>
                            </label>

                            <input
                                id="profile_photo"
                                type="file"
                                name="profile_photo"
                                class="sr-only js-validate-file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                onchange="updateFileName(this)"
                                data-max-size="2097152"
                            >

                            <p id="profile-photo-file-name" class="mt-1 text-xs text-gray-500 italic">No file selected.</p>

                            <p id="profile-photo-client-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('profile_photo') ? '' : 'hidden' }}">{{ $errors->first('profile_photo') }}</p>
                        </div>
                        {{-- <div>
                            <x-input-label for="profile_photo" value="Profile Image" />
                            <input type="file" name="profile_photo" class="w-full mt-1" accept="image/*">
                            <x-input-error :messages="$errors->get('profile_photo')" class="mt-2" />
                        </div> --}}
                        <div>
                            <x-input-label for="create_user_role_id" value="Role" />
                            <select id="create_user_role_id" name="role_id" class="w-full mt-1 border-gray-300 rounded-md shadow-sm" required>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}" {{ (string) old('role_id') === (string) $role->id ? 'selected' : '' }}>
                                        {{ $role->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p id="create-user-role-id-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('role_id') ? '' : 'hidden' }}">{{ $errors->first('role_id') }}</p>
                        </div>
                        <div>
                            <x-input-label for="create_user_company_subscription_id" value="Plan" />
                            <select id="create_user_company_subscription_id" name="company_subscription_id" class="w-full mt-1 border-gray-300 rounded-md shadow-sm" @if($availablePlanOptions->isNotEmpty()) required @endif>
                                <option value="">Select Plan for Staff</option>
                                @forelse($availablePlanOptions as $planOption)
                                    <option
                                        value="{{ $planOption['company_subscription_id'] }}"
                                        data-total-members="{{ $planOption['total_members'] ?? '' }}"
                                        data-used-members="{{ $planOption['used_members'] ?? '' }}"
                                        data-remaining-members="{{ $planOption['remaining_members'] ?? '' }}"
                                        data-plan-label="{{ $planOption['label'] }}"
                                        {{ (string) old('company_subscription_id') === (string) $planOption['company_subscription_id'] ? 'selected' : '' }}
                                    >
                                        {{ $planOption['label'] }}
                                    </option>
                                @empty
                                    <option value="" disabled>No active plan seats available</option>
                                @endforelse
                            </select>
                            <p id="create-user-company-subscription-id-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('company_subscription_id') ? '' : 'hidden' }}">{{ $errors->first('company_subscription_id') }}</p>
                            {{-- <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-3">
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Total Users</div>
                                    <div id="selected-plan-total" class="mt-1 text-lg font-black text-slate-900">{{ $selectedPlanOption['total_members_label'] ?? '0' }}</div>
                                </div>
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Used Users</div>
                                    <div id="selected-plan-used" class="mt-1 text-lg font-black text-slate-900">{{ isset($selectedPlanOption['used_members']) ? number_format((int) $selectedPlanOption['used_members']) : '0' }}</div>
                                </div>
                                <div class="rounded-md border border-slate-200 bg-slate-50 p-3">
                                    <div class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">Remaining Users</div>
                                    <div id="selected-plan-remaining" class="mt-1 text-lg font-black text-slate-900">
                                        {{ ($selectedPlanOption['remaining_members'] ?? null) === null ? 'Unlimited' : number_format((int) $selectedPlanOption['remaining_members']) }}
                                    </div>
                                </div>
                            </div> --}}
                            <p id="selected-plan-label" class="mt-2 text-xs text-slate-500">
                                @if($selectedPlanOption)
                                    Assigning this user to {{ $selectedPlanOption['label'] }}.
                                @else
                                    Select a plan to see seat usage.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end border-t border-slate-200 pt-5">
                        <button
                            id="create-user-submit"
                            type="submit"
                            class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-6 py-2 text-xs font-bold uppercase tracking-widest text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
                        >
                            Create User & Profile
                        </button>
                    </div>
                </form>
            </div>
            @endif

            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-slate-900">Users</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-[1120px] w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Role</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Plan</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                <th class="px-6 py-4 text-center text-xs font-semibold uppercase tracking-wider text-slate-600">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($users as $user)
                                @php
                                    $userPlan = $user->staff?->companySubscription?->plan;
                                @endphp
                                <tr class="transition-colors hover:bg-slate-50">
                                    <td class="px-6 py-4 text-sm font-medium text-slate-900">{{ $user->name }}</td>
                                    <td class="px-6 py-4 text-sm text-slate-700">{{ $user->email }}</td>

                                    <td class="px-6 py-4 text-sm italic text-slate-500">
                                        {{ Str::headline($user->roleRecord?->slug ?? $user->role) }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-slate-700">
                                        @if($userPlan)
                                            <div class="space-y-0.5">
                                                <div class="font-semibold text-slate-900">{{ $userPlan->name }}</div>
                                                <div class="text-[11px] text-slate-500">Start: {{ $user->staff?->companySubscription?->starts_at?->format('d M Y') ?? '-' }}</div>
                                                <div class="text-[11px] text-slate-500">End: {{ $user->staff?->companySubscription?->ends_at?->format('d M Y') ?? '-' }}</div>
                                            </div>
                                        @else
                                            <span class="text-slate-400">-</span>
                                        @endif
                                    </td>
                                   <td class="px-6 py-4">
                                        @if($canManageUsers)
                                        <form id="status-form-{{ $user->id }}" method="POST" action="{{ route('settings.users.toggle-status', $user) }}">
                                            @csrf
                                            @method('PATCH')

                                            <label for="toggle-{{ $user->id }}" class="flex items-center cursor-pointer">
                                                <div class="relative">
                                                    {{-- <input type="checkbox"
                                                        id="toggle-{{ $user->id }}"
                                                        class="sr-only peer"
                                                        {{ $user->is_active ? 'checked' : '' }}
                                                        onchange="document.getElementById('status-form-{{ $user->id }}').submit();"> --}}
                                                        <input type="checkbox"
                                                            id="toggle-{{ $user->id }}"
                                                            class="sr-only peer"
                                                            {{ $user->is_active ? 'checked' : '' }}
                                                            onchange="event.stopPropagation(); this.form.submit();">

                                                    <div class="w-10 h-6 bg-gray-200 rounded-full peer peer-focus:ring-4 peer-focus:ring-indigo-300 dark:peer-focus:ring-indigo-800 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-indigo-600"></div>
                                                </div>

                                                <span class="ms-3 text-sm font-medium {{ $user->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                                    {{ $user->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </label>
                                        </form>
                                        @else
                                            <span class="ms-3 text-sm font-medium {{ $user->is_active ? 'text-emerald-600' : 'text-slate-400' }}">
                                                {{ $user->is_active ? 'Active' : 'Inactive' }}
                                            </span>
                                        @endif
                                    </td>

                                    <td class="px-6 py-4">
                                        @if($canManageUsers)
                                            <form method="POST" action="{{ route('settings.users.role.update', $user) }}" class="flex flex-col items-stretch gap-2 sm:flex-row sm:items-center sm:justify-end user-role-form">
                                                @csrf
                                                @method('PATCH')

                                                <select name="role_id" class="block w-full min-w-0 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-40">
                                                    @foreach($roles as $role)
                                                        <option value="{{ $role->id }}" {{ (int) $user->role_id === (int) $role->id ? 'selected' : '' }}>
                                                            {{ $role->name }}
                                                        </option>
                                                    @endforeach
                                                </select>

                                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-xs font-bold uppercase tracking-widest text-white transition hover:bg-indigo-700 active:bg-indigo-900 sm:w-auto user-role-submit">
                                                    Save
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-sm text-slate-700">{{ Str::headline($user->roleRecord?->slug ?? $user->role) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const createUserForm = document.getElementById('create-user-form');
        const dateInput = document.getElementById('create_user_join_date');
        const contactInput = document.getElementById('create_user_contact_number');

        // Limit date picker to today or past
        if (dateInput) {
            dateInput.max = new Date().toISOString().split("T")[0];
        }

        // Prevent non-numeric characters in Contact Number via keyboard
        if (contactInput) {
            contactInput.addEventListener('keypress', function (e) {
                if (e.which < 48 || e.which > 57) e.preventDefault();
            });
        }

        if (createUserForm && window.jQuery) {
            const $form = $('#create-user-form');
            const $submitButton = $('#create-user-submit');

            // 1. Strict Email Validation (requires name@domain.tld)
            $.validator.addMethod('strictEmail', function (value, element) {
                return this.optional(element) || /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(value);
            }, 'Please enter a valid email address with a proper domain.');

            // 2. Exactly 10 Digits Validation
            $.validator.addMethod('tenDigits', function (value, element) {
                return this.optional(element) || /^\d{10}$/.test(value);
            }, 'Contact number must be exactly 10 digits.');

            // 3. File Size Validation (Max 2MB)
            $.validator.addMethod('maxFileSize', function (value, element, params) {
                if (this.optional(element) || !element.files || !element.files.length) return true;
                const size = element.files[0].size;
                const maxSize = params * 1024 * 1024;
                return size <= maxSize;
            }, $.validator.format('File size must not exceed {0} MB.'));

            // 4. Past or Present Date only
            $.validator.addMethod('notFutureDate', function (value, element) {
                if (this.optional(element)) return true;
                const selectedDate = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                return selectedDate <= today;
            }, 'Joining date cannot be a future date.');

            const $profilePhotoInput = $('#profile_photo');
            const $profilePhotoError = $('#profile-photo-client-error');

            const showProfilePhotoError = (message) => {
                $profilePhotoError.text(message).removeClass('hidden');
            };

            const clearProfilePhotoError = () => {
                $profilePhotoError.text('').addClass('hidden');
            };

            const fieldErrorMap = {
                name: '#create-user-name-error',
                email: '#create-user-email-error',
                password: '#create-user-password-error',
                password_confirmation: '#create-user-password-confirmation-error',
                contact_number: '#create-user-contact-number-error',
                join_date: '#create-user-join-date-error',
                profile_photo: '#profile-photo-client-error',
                role_id: '#create-user-role-id-error',
                company_subscription_id: '#create-user-company-subscription-id-error'
            };

            const showFieldError = (fieldName, message) => {
                const selector = fieldErrorMap[fieldName];
                if (!selector) {
                    return;
                }

                $(selector).text(message).removeClass('hidden');
            };

            const clearFieldError = (fieldName) => {
                const selector = fieldErrorMap[fieldName];
                if (!selector) {
                    return;
                }

                $(selector).text('').addClass('hidden');
            };

            const setFieldVisualState = (element, state) => {
                if (!element) return;

                const $element = $(element);
                $element.removeClass('border-red-500 border-gray-300 border-emerald-500 ring-1 ring-red-300 ring-emerald-200');

                if (state === 'error') {
                    $element.addClass('border-red-500 ring-1 ring-red-300');
                    return;
                }

                $element.addClass('border-gray-300');
            };

            $form.validate({
                ignore: [],
                errorElement: 'p',
                errorClass: 'mt-1 text-sm text-red-600',
                onkeyup: function (element) {
                    if (element.name === 'email') {
                        return;
                    }

                    this.element(element);
                },
                rules: {
                    name: {
                        required: true,
                        minlength: 2,
                        maxlength: 191,
                        normalizer: function (value) {
                            return $.trim(value);
                        }
                    },
                    email: {
                        required: true,
                        strictEmail: true,
                        maxlength: 191,
                        normalizer: function (value) {
                            return $.trim(value);
                        },
                        remote: {
                            url: "{{ route('settings.users.check-email') }}",
                            type: 'POST',
                            data: {
                                _token: "{{ csrf_token() }}",
                                email: function () {
                                    return $.trim($('#create_user_email').val());
                                }
                            },
                            dataFilter: function (response) {
                                try {
                                    const parsed = JSON.parse(response);
                                    if (parsed === true || parsed === 'true') {
                                        return 'true';
                                    }

                                    return JSON.stringify('This email address is already in use.');
                                } catch (error) {
                                    return JSON.stringify('Unable to validate this email right now.');
                                }
                            }
                        }
                    },
                    password: {
                        required: true,
                        minlength: 8,
                        maxlength: 20
                    },
                    password_confirmation: {
                        required: true,
                        equalTo: '#create_user_password'
                    },
                    contact_number: {
                        required: true,
                        tenDigits: true
                    },
                    join_date: {
                        required: true,
                        date: true,
                        notFutureDate: true
                    },
                    profile_photo: {
                        required: false,
                        extension: "jpg|jpeg|png|webp",
                        maxFileSize: 2
                    },
                    role_id: {
                        required: true
                    },
                    company_subscription_id: {
                        required: true
                    }
                },
                messages: {
                    name: {
                        required: "Full name is required.",
                        minlength: "Full name must be at least 2 characters.",
                        maxlength: "Full name may not be greater than 191 characters."
                    },
                    email: {
                        required: "Email address is required.",
                        strictEmail: "Please enter a valid email address.",
                        maxlength: "Email may not be greater than 191 characters.",
                        remote: "This email address is already in use."
                    },
                    password: {
                        required: "Password is required.",
                        minlength: "Password must be at least 8 characters.",
                        maxlength: "Password may not be greater than 20 characters."
                    },
                    password_confirmation: {
                        required: "Please confirm the password.",
                        equalTo: "Confirm password must match the password."
                    },
                    contact_number: {
                        required: "Contact number is required.",
                        tenDigits: "Contact number must be exactly 10 digits."
                    },
                    join_date: {
                        required: "Joining date is required.",
                        date: "Please enter a valid joining date.",
                        notFutureDate: "Joining date cannot be a future date."
                    },
                    profile_photo: {
                        extension: "Only JPG, JPEG, PNG, or WEBP files are allowed.",
                        maxFileSize: "Profile image must not be greater than 2 MB."
                    },
                    role_id: {
                        required: "Please select a role."
                    },
                    company_subscription_id: {
                        required: "Please select a plan."
                    }
                },
                errorPlacement: function (error, element) {
                    showFieldError(element.attr('name'), error.text());
                },
                highlight: function (element) {
                    setFieldVisualState(element, 'error');
                },
                unhighlight: function (element) {
                    setFieldVisualState(element, 'normal');
                    clearFieldError(element.name);
                },
                success: function (_label, element) {
                    setFieldVisualState(element, 'normal');
                    clearFieldError(element.name);
                },
                submitHandler: function (form) {
                    $submitButton.prop('disabled', true).text('Processing...');

                    Swal.fire({
                        title: 'Creating User...',
                        text: 'Please wait.',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    form.submit();
                }
            });

            $profilePhotoInput.on('change', function () {
                clearProfilePhotoError();
                const validator = $form.validate();
                validator.element(this);
            });

            const emailInput = document.getElementById('create_user_email');
            const subscriptionSelect = document.getElementById('create_user_company_subscription_id');
            const subscriptionTotal = document.getElementById('selected-plan-total');
            const subscriptionUsed = document.getElementById('selected-plan-used');
            const subscriptionRemaining = document.getElementById('selected-plan-remaining');
            const subscriptionLabel = document.getElementById('selected-plan-label');

            const formatCount = (value) => {
                if (value === null || value === undefined || value === '') {
                    return 'Unlimited';
                }

                const parsed = Number(value);
                return Number.isFinite(parsed) ? parsed.toLocaleString() : 'Unlimited';
            };

            const updateSelectedPlanSummary = () => {
                if (!subscriptionSelect) {
                    return;
                }

                const option = subscriptionSelect.options[subscriptionSelect.selectedIndex];
                if (!option || !option.value) {
                    if (subscriptionTotal) subscriptionTotal.textContent = '0';
                    if (subscriptionUsed) subscriptionUsed.textContent = '0';
                    if (subscriptionRemaining) subscriptionRemaining.textContent = 'Unlimited';
                    if (subscriptionLabel) subscriptionLabel.textContent = 'Select a plan to see seat usage.';
                    return;
                }

                if (subscriptionTotal) subscriptionTotal.textContent = formatCount(option.dataset.totalMembers);
                if (subscriptionUsed) subscriptionUsed.textContent = formatCount(option.dataset.usedMembers);
                if (subscriptionRemaining) subscriptionRemaining.textContent = formatCount(option.dataset.remainingMembers);
                if (subscriptionLabel) subscriptionLabel.textContent = `Assigning this user to ${option.dataset.planLabel || option.textContent}.`;
            };

            if (subscriptionSelect) {
                subscriptionSelect.addEventListener('change', updateSelectedPlanSummary);
                updateSelectedPlanSummary();
            }

            if (emailInput) {
                emailInput.addEventListener('input', function () {
                    const trimmed = String(this.value || '').trim();

                    clearFieldError('email');

                    if (!trimmed) {
                        setFieldVisualState(this, 'normal');
                        $(this).removeData('previousValue');
                        return;
                    }

                    const looksValid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(trimmed);
                    setFieldVisualState(this, looksValid ? 'normal' : 'error');

                    if (looksValid) {
                        $(this).removeData('previousValue');
                    }
                });

                emailInput.addEventListener('blur', function () {
                    const trimmed = String(this.value || '').trim();
                    if (!trimmed) {
                        return;
                    }

                    $form.validate().element(this);
                });
            }
        }
    });

    function updateFileName(input) {
        const display = document.getElementById('profile-photo-file-name');
        if (input.files && input.files.length > 0) {
            display.textContent = input.files[0].name;
            display.className = "mt-1 text-xs text-indigo-600 font-semibold italic";
        } else {
            display.textContent = 'No file selected.';
            display.className = "mt-1 text-xs text-gray-500 italic";
        }
    }
</script>
@endpush
</x-app-layout>
