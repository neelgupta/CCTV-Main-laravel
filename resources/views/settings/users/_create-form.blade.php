@php
    $formIdPrefix = $formIdPrefix ?? 'create_user';
    $availablePlanOptions = collect($availableSubscriptionOptions ?? []);
    $selectedPlanOption = $selectedPlanOption
        ?? $availablePlanOptions->firstWhere('company_subscription_id', (int) old('company_subscription_id'))
        ?? $availablePlanOptions->first();
    $formAction = $formAction ?? route('settings.users.store');
    $submitLabel = $submitLabel ?? 'Create User & Profile';
    $formTitle = $formTitle ?? 'Add User & Staff Details';
    $formDescription = $formDescription ?? 'Create a login and assign the correct role in one step.';
@endphp

<div data-company-user-form-root data-form-prefix="{{ $formIdPrefix }}">
    <form
        id="{{ $formIdPrefix }}-form"
        action="{{ $formAction }}"
        method="POST"
        enctype="multipart/form-data"
        novalidate
        autocomplete="off"
        onkeydown="if(event.key === 'Enter' && event.target.tagName !== 'TEXTAREA'){ event.preventDefault(); return false; }"
    >
        @csrf

        @if(filled($formTitle) || filled($formDescription))
            <div class="mb-5">
                @if(filled($formTitle))
                    <h3 class="text-lg font-bold text-slate-900">{{ $formTitle }}</h3>
                @endif

                @if(filled($formDescription))
                    <p class="mt-1 text-sm text-slate-500">{{ $formDescription }}</p>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            <div>
                <x-input-label for="{{ $formIdPrefix }}_name" value="Full Name" />
                <x-text-input
                    id="{{ $formIdPrefix }}_name"
                    name="name"
                    placeholder="Employee Name"
                    class="w-full mt-1"
                    :value="old('name', request()->query('name'))"
                    minlength="2"
                    maxlength="191"
                    required
                />
                <p id="{{ $formIdPrefix }}-name-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('name') ? '' : 'hidden' }}">{{ $errors->first('name') }}</p>
            </div>

            <div>
                <x-input-label for="{{ $formIdPrefix }}_email" value="Email Address" />
                <x-text-input
                    id="{{ $formIdPrefix }}_email"
                    name="email"
                    placeholder="Employee Email"
                    type="text"
                    inputmode="email"
                    autocapitalize="none"
                    class="w-full mt-1"
                    :value="old('email', request()->query('email'))"
                    maxlength="191"
                    autocomplete="off"
                    spellcheck="false"
                    required
                />
                <p id="{{ $formIdPrefix }}-email-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('email') ? '' : 'hidden' }}">{{ $errors->first('email') }}</p>
            </div>

            <div>
                <x-input-label for="{{ $formIdPrefix }}_password" value="Password" />
                <x-text-input id="{{ $formIdPrefix }}_password" name="password" placeholder="Enter Password" type="password" class="w-full mt-1" required />
                <p id="{{ $formIdPrefix }}-password-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('password') ? '' : 'hidden' }}">{{ $errors->first('password') }}</p>
            </div>

            <div>
                <x-input-label for="{{ $formIdPrefix }}_password_confirmation" value="Confirm Password" />
                <x-text-input id="{{ $formIdPrefix }}_password_confirmation" name="password_confirmation" placeholder="Enter Confirm  Password" type="password" class="w-full mt-1" required />
                <p id="{{ $formIdPrefix }}-password-confirmation-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('password_confirmation') ? '' : 'hidden' }}">{{ $errors->first('password_confirmation') }}</p>
            </div>

            <div>
                <x-input-label for="{{ $formIdPrefix }}_contact_number" value="Contact Number" />
                <x-text-input
                    id="{{ $formIdPrefix }}_contact_number"
                    name="contact_number"
                    placeholder="Enter Contact Number"
                    class="w-full mt-1"
                    :value="old('contact_number', request()->query('contact_number'))"
                    maxlength="10"
                    required
                />
                <p id="{{ $formIdPrefix }}-contact-number-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('contact_number') ? '' : 'hidden' }}">{{ $errors->first('contact_number') }}</p>
            </div>

            <div>
                <x-input-label for="{{ $formIdPrefix }}_join_date" value="Joining Date" />
                <x-text-input id="{{ $formIdPrefix }}_join_date" name="join_date" type="date" class="w-full mt-1" :value="old('join_date')" required />
                <p id="{{ $formIdPrefix }}-join-date-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('join_date') ? '' : 'hidden' }}">{{ $errors->first('join_date') }}</p>
            </div>

            <div>
                <x-input-label for="{{ $formIdPrefix }}_profile_photo" value="Profile Image" />

                <label for="{{ $formIdPrefix }}_profile_photo" class="mt-1 flex h-[42px] cursor-pointer items-center justify-between gap-3 rounded-md border-2 border-dashed border-indigo-200 bg-indigo-50/50 px-3 py-2 transition hover:border-indigo-300 hover:bg-indigo-50">
                    <span class="text-sm font-medium text-indigo-700">Choose Image</span>
                    <span class="text-xs text-indigo-600">Max 2 MB</span>
                </label>

                <input
                    id="{{ $formIdPrefix }}_profile_photo"
                    type="file"
                    name="profile_photo"
                    class="js-validate-file sr-only"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    onchange="updateFileName(this, '{{ $formIdPrefix }}')"
                    data-max-size="2097152"
                >

                <p id="{{ $formIdPrefix }}-profile-photo-file-name" class="mt-1 text-xs italic text-gray-500">No file selected.</p>
                <p id="{{ $formIdPrefix }}-profile-photo-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('profile_photo') ? '' : 'hidden' }}">{{ $errors->first('profile_photo') }}</p>
            </div>

            <div>
                <x-searchable-select
                    name="role_id"
                    id="{{ $formIdPrefix }}_role_id"
                    label="Role"
                    :options="$roles->map(fn ($role) => ['id' => $role->id, 'label' => $role->name])"
                    :selected="old('role_id')"
                    placeholder="Search role..."
                    empty-label="Select Role"
                />
                <p id="{{ $formIdPrefix }}-role-id-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('role_id') ? '' : 'hidden' }}">{{ $errors->first('role_id') }}</p>
            </div>

            <div>
                <x-searchable-select
                    name="company_subscription_id"
                    id="{{ $formIdPrefix }}_company_subscription_id"
                    label="Plan"
                    :options="$availablePlanOptions->map(fn ($planOption) => [
                        'id' => $planOption['company_subscription_id'],
                        'label' => $planOption['label'],
                        'meta' => trim(implode(' ', array_filter([
                            $planOption['total_members_label'] ?? null,
                            $planOption['remaining_members'] ?? null,
                        ]))),
                    ])"
                    :selected="old('company_subscription_id')"
                    placeholder="Search plan..."
                    empty-label="Select Plan for Staff"
                    :disabled="$availablePlanOptions->isEmpty()"
                />
                <p id="{{ $formIdPrefix }}-company-subscription-id-error" class="create-user-error mt-2 text-sm text-red-600 {{ $errors->has('company_subscription_id') ? '' : 'hidden' }}">{{ $errors->first('company_subscription_id') }}</p>
                <p id="{{ $formIdPrefix }}-selected-plan-label" class="mt-2 text-xs text-slate-500">
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
                id="{{ $formIdPrefix }}_submit"
                type="submit"
                class="inline-flex min-h-11 items-center justify-center rounded-md border border-slate-900 bg-slate-900 px-6 py-2 text-xs font-bold uppercase tracking-widest text-white shadow-sm transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2"
            >
                {{ $submitLabel }}
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.jQuery || !jQuery.fn || typeof jQuery.fn.validate !== 'function') {
            return;
        }

        const $ = window.jQuery;
        const roots = document.querySelectorAll('[data-company-user-form-root]');

        const formatCount = (value) => {
            if (value === null || value === undefined || value === '') {
                return 'Unlimited';
            }

            const parsed = Number(value);
            return Number.isFinite(parsed) ? parsed.toLocaleString() : 'Unlimited';
        };

        const setFieldVisualState = (element, state) => {
            if (!element) {
                return;
            }

            const $element = $(element);
            $element.removeClass('border-red-500 border-gray-300 border-emerald-500 ring-1 ring-red-300 ring-emerald-200');

            if (state === 'error') {
                $element.addClass('border-red-500 ring-1 ring-red-300');
                return;
            }

            $element.addClass('border-gray-300');
        };

        const initForm = (root) => {
            if (!root || root.dataset.initialized === 'true') {
                return;
            }

            root.dataset.initialized = 'true';

            const prefix = root.dataset.formPrefix || 'create_user';
            const form = root.querySelector('form');
            if (!form) {
                return;
            }

            const $form = $(form);
            const $submitButton = $(`#${prefix}_submit`);
            const $profilePhotoInput = $(`#${prefix}_profile_photo`);
            const $profilePhotoError = $(`#${prefix}-profile-photo-error`);
            const emailInput = document.getElementById(`${prefix}_email`);
            const contactInput = document.getElementById(`${prefix}_contact_number`);
            const dateInput = document.getElementById(`${prefix}_join_date`);
            const subscriptionSelect = document.getElementById(`${prefix}_company_subscription_id`);
            const subscriptionLabel = document.getElementById(`${prefix}-selected-plan-label`);

            const fieldErrorMap = {
                name: `#${prefix}-name-error`,
                email: `#${prefix}-email-error`,
                password: `#${prefix}-password-error`,
                password_confirmation: `#${prefix}-password-confirmation-error`,
                contact_number: `#${prefix}-contact-number-error`,
                join_date: `#${prefix}-join-date-error`,
                profile_photo: `#${prefix}-profile-photo-error`,
                role_id: `#${prefix}-role-id-error`,
                company_subscription_id: `#${prefix}-company-subscription-id-error`
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

            const clearProfilePhotoError = () => {
                $profilePhotoError.text('').addClass('hidden');
            };

            if (dateInput) {
                dateInput.max = new Date().toISOString().split('T')[0];
            }

            if (contactInput) {
                contactInput.addEventListener('keypress', function (e) {
                    if (e.which < 48 || e.which > 57) {
                        e.preventDefault();
                    }
                });
            }

            $.validator.addMethod('strictEmail', function (value, element) {
                return this.optional(element) || /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/.test(value);
            }, 'Please enter a valid email address with a proper domain.');

            $.validator.addMethod('tenDigits', function (value, element) {
                return this.optional(element) || /^\d{10}$/.test(value);
            }, 'Contact number must be exactly 10 digits.');

            $.validator.addMethod('maxFileSize', function (value, element, params) {
                if (this.optional(element) || !element.files || !element.files.length) {
                    return true;
                }

                const size = element.files[0].size;
                const maxSize = params * 1024 * 1024;
                return size <= maxSize;
            }, $.validator.format('File size must not exceed {0} MB.'));

            $.validator.addMethod('notFutureDate', function (value, element) {
                if (this.optional(element)) {
                    return true;
                }

                const selectedDate = new Date(value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                return selectedDate <= today;
            }, 'Joining date cannot be a future date.');

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
                                    return $.trim($(`#${prefix}_email`).val());
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
                        equalTo: `#${prefix}_password`
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
                        extension: 'jpg|jpeg|png|webp',
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
                        required: 'Full name is required.',
                        minlength: 'Full name must be at least 2 characters.',
                        maxlength: 'Full name may not be greater than 191 characters.'
                    },
                    email: {
                        required: 'Email address is required.',
                        strictEmail: 'Please enter a valid email address.',
                        maxlength: 'Email may not be greater than 191 characters.',
                        remote: 'This email address is already in use.'
                    },
                    password: {
                        required: 'Password is required.',
                        minlength: 'Password must be at least 8 characters.',
                        maxlength: 'Password may not be greater than 20 characters.'
                    },
                    password_confirmation: {
                        required: 'Please confirm the password.',
                        equalTo: 'Confirm password must match the password.'
                    },
                    contact_number: {
                        required: 'Contact number is required.',
                        tenDigits: 'Contact number must be exactly 10 digits.'
                    },
                    join_date: {
                        required: 'Joining date is required.',
                        date: 'Please enter a valid joining date.',
                        notFutureDate: 'Joining date cannot be a future date.'
                    },
                    profile_photo: {
                        extension: 'Only JPG, JPEG, PNG, or WEBP files are allowed.',
                        maxFileSize: 'Profile image must not be greater than 2 MB.'
                    },
                    role_id: {
                        required: 'Please select a role.'
                    },
                    company_subscription_id: {
                        required: 'Please select a plan.'
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
                    if ($submitButton.length) {
                        $submitButton.prop('disabled', true).text('Processing...');
                    }

                    if (window.Swal) {
                        Swal.fire({
                            title: 'Creating User...',
                            text: 'Please wait.',
                            allowOutsideClick: false,
                            didOpen: () => { Swal.showLoading(); }
                        });
                    }

                    form.submit();
                }
            });

            $profilePhotoInput.on('change', function () {
                clearProfilePhotoError();
                const validator = $form.validate();
                validator.element(this);
                updateFileName(this, prefix);
            });

            if (subscriptionSelect) {
                const updateSelectedPlanSummary = () => {
                    const option = subscriptionSelect.options[subscriptionSelect.selectedIndex];

                    if (!option || !option.value) {
                        if (subscriptionLabel) {
                            subscriptionLabel.textContent = 'Select a plan to see seat usage.';
                        }
                        return;
                    }

                    if (subscriptionLabel) {
                        subscriptionLabel.textContent = `Assigning this user to ${option.dataset.planLabel || option.textContent}.`;
                    }
                };

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
        };

        roots.forEach(initForm);
    });

    function updateFileName(input, prefix) {
        const display = document.getElementById(`${prefix}-profile-photo-file-name`);
        if (!display) {
            return;
        }

        if (input.files && input.files.length > 0) {
            display.textContent = input.files[0].name;
            display.className = 'mt-1 text-xs font-semibold italic text-indigo-600';
        } else {
            display.textContent = 'No file selected.';
            display.className = 'mt-1 text-xs italic text-gray-500';
        }
    }
</script>
@endpush
