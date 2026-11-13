<x-guest-layout>
    @php
        $registrationErrors = $errors->any()
            ? collect($errors->all())->unique()->take(4)->values()
            : collect();
    @endphp

    <div class="ww-auth-stage">
        <div class="ww-auth-orb -left-16 top-8 h-72 w-72 bg-blue-300/50"></div>
        <div class="ww-auth-orb bottom-0 right-0 h-96 w-96 bg-cyan-300/35"></div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center">
            <div class="ww-auth-frame">
                <x-auth.brand-panel
                    eyebrow="Guided Setup"
                    title="Launch a premium field-service workspace your team can adopt immediately."
                    description="Create your company account, invite your admin, and move into a smoother onboarding flow built around the same visual language as your public brand."

                    :stats="[
                        ['value' => '3 Steps', 'label' => 'To Go Live'],
                        ['value' => '1 Admin', 'label' => 'Created Automatically'],
                        ['value' => 'Secure', 'label' => 'Password Policy'],
                    ]"
                >
                </x-auth.brand-panel>

                <section class="ww-auth-panel">
                    <div class="mt-5">
                        <h2 class="ww-heading">Set up your workspace</h2>
                        <p class="ww-subheading">Use your business details to create the company account that will power login, onboarding, and subscription setup.</p>
                    </div>

                    {{-- @if ($registrationErrors->isNotEmpty())
                        <div class="ww-status-card ww-status-error mt-6" role="alert" aria-live="polite">
                            <p class="font-semibold">Please fix the highlighted fields before continuing.</p>
                            <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                @foreach ($registrationErrors as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif --}}

                    <form id="companyRegistrationForm" method="POST" action="{{ route('register.company.store') }}" enctype="multipart/form-data" class="ww-form-grid" novalidate>
                        @csrf

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div class="ww-field sm:col-span-2">
                                <label for="company_name" class="ww-label">Company name</label>
                                <input
                                    id="company_name"
                                    type="text"
                                    name="company_name"
                                    value="{{ old('company_name') }}"
                                    required
                                    autofocus
                                    maxlength="191"
                                    placeholder="Enter your registered business name"
                                    class="ww-input {{ $errors->has('company_name') ? 'is-invalid' : '' }}"
                                    data-validate="company_name"
                                />
                                <p class="ww-field-error {{ $errors->has('company_name') ? '' : 'hidden' }}" data-error-for="company_name" aria-live="polite">{{ $errors->first('company_name') }}</p>
                            </div>

                            <div class="ww-field sm:col-span-2">
                                <label for="email" class="ww-label">Company email</label>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    required
                                    maxlength="191"
                                    autocomplete="email"
                                    placeholder="name@company.com"
                                    class="ww-input {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                    data-validate="email"
                                />
                                <p class="ww-field-error {{ $errors->has('email') ? '' : 'hidden' }}" data-error-for="email" aria-live="polite">{{ $errors->first('email') }}</p>
                            </div>

                            <div class="ww-field sm:col-span-2">
                                <label for="phone_number" class="ww-label">Mobile number</label>
                                <input
                                    id="phone_number"
                                    type="tel"
                                    name="phone_number"
                                    value="{{ old('phone_number') }}"
                                    required
                                    maxlength="10"
                                    inputmode="numeric"
                                    autocomplete="tel"
                                    placeholder="10-digit mobile number"
                                    class="ww-input {{ $errors->has('phone_number') ? 'is-invalid' : '' }}"
                                    data-validate="phone_number"
                                />
                                <p class="ww-field-error {{ $errors->has('phone_number') ? '' : 'hidden' }}" data-error-for="phone_number" aria-live="polite">{{ $errors->first('phone_number') }}</p>
                            </div>

                            <div class="ww-field">
                                <label for="password" class="ww-label">Password</label>
                                <div class="ww-input-icon-wrap">
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Create a strong password"
                                        class="ww-input pr-20 {{ $errors->has('password') ? 'is-invalid' : '' }}"
                                        data-validate="password"
                                    />
                                    <button type="button" class="ww-input-action" data-password-toggle="#password">Show</button>
                                </div>
                                <div class="ww-password-meter" data-strength="0" id="passwordStrengthMeter">
                                    <span></span><span></span><span></span><span></span>
                                </div>
                                <p class="ww-field-error {{ $errors->has('password') ? '' : 'hidden' }}" data-error-for="password" aria-live="polite">{{ $errors->first('password') }}</p>
                            </div>

                            <div class="ww-field">
                                <label for="password_confirmation" class="ww-label">Confirm password</label>
                                <div class="ww-input-icon-wrap">
                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        name="password_confirmation"
                                        required
                                        autocomplete="new-password"
                                        placeholder="Re-enter your password"
                                        class="ww-input pr-20 {{ $errors->has('password_confirmation') ? 'is-invalid' : '' }}"
                                        data-validate="password_confirmation"
                                    />
                                    <button type="button" class="ww-input-action" data-password-toggle="#password_confirmation">Show</button>
                                </div>
                                <p class="ww-field-error {{ $errors->has('password_confirmation') ? '' : 'hidden' }}" data-error-for="password_confirmation" aria-live="polite">{{ $errors->first('password_confirmation') }}</p>
                            </div>

                            <div class="ww-field sm:col-span-2">
                                <label for="terms" class="inline-flex items-start gap-3 text-sm text-slate-600">
                                    <input
                                        id="terms"
                                        type="checkbox"
                                        name="terms"
                                        value="1"
                                        required
                                        @checked(old('terms'))
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                        data-validate="terms"
                                    />
                                    <span>
                                        I have read and agree to the
                                        <a class="ww-inline-link" href="{{ config('legal.terms_url') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a>
                                    </span>
                                </label>
                                <p class="ww-field-error {{ $errors->has('terms') ? '' : 'hidden' }}" data-error-for="terms" aria-live="polite">{{ $errors->first('terms') }}</p>
                            </div>
                        </div>

                        <div class="flex flex-col gap-4 border-t border-slate-200 pt-6 sm:flex-row sm:items-center sm:justify-between">
                            <p class="text-sm text-slate-600">
                                Already have a workspace?
                                <a class="ww-inline-link" href="{{ route('login') }}">Sign in instead</a>
                            </p>

                            <button type="submit" class="ww-primary-btn" id="createAccountBtn">
                                Create Account
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M3.25 10a.75.75 0 01.75-.75h10.19L10.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06l3.97-3.97H4A.75.75 0 013.25 10z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>
                    </form>
                </section>
            </div>
        </div>
    </div>

    <!-- Thank You Processing Modal -->
    <div id="registrationThankYouModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/70 p-4 backdrop-blur-md transition-all">
        <div class="relative w-full max-w-md rounded-[32px] border border-white/20 bg-white p-8 text-center shadow-2xl">
            <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-8 ring-emerald-50/50 mb-4">
                <svg class="h-10 w-10 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </div>

            <div class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-xs font-black uppercase tracking-wider text-emerald-800 mb-2">
                🎉 Welcome Aboard!
            </div>

            <h3 class="text-2xl font-black tracking-tight text-slate-900">Thank You for Registering!</h3>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                We are creating the company workspace for <strong id="thankYouCompanyName" class="font-bold text-slate-900">your business</strong>. Please wait a moment while we set up your account...
            </p>

            <div class="mt-6 flex items-center justify-center gap-3 rounded-2xl bg-slate-50 p-4 border border-slate-100">
                <div class="h-5 w-5 animate-spin rounded-full border-2 border-rose-600 border-t-transparent"></div>
                <span class="text-xs font-bold text-slate-700">Setting up your workspace & dashboard...</span>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>

        <script>
            (function ($) {
                // If real jQuery is not loaded, stop (we keep the behavior explicit).
                if (!$) return;

                $(function () {
                    const $registrationForm = $('#companyRegistrationForm');
                    if (!$registrationForm.length) return;

                    const $companyNameInput = $('#company_name');
                    const $emailInput = $('#email');
                    const $phoneInput = $('#phone_number');
                    const $passwordInput = $('#password');
                    const $confirmationInput = $('#password_confirmation');
                    const $termsInput = $('#terms');
                    const $passwordMeter = $('#passwordStrengthMeter');
                    const $passwordStrengthText = $('#passwordStrengthText');

                    const validators = {
                        company_name: (value) => {
                            value = (value || '').trim();
                            if (!value) return 'Please enter your company name.';
                            if (value.length < 2) return 'Company name must be at least 2 characters.';
                            if (!/^(?=.*[A-Za-z])[A-Za-z0-9&.,()'\-\s]+$/.test(value)) {
                                return 'Use letters, numbers, spaces, and basic punctuation only.';
                            }
                            return '';
                        },
                        email: (value) => {
                            value = (value || '').trim();
                            if (!value) return 'Please enter your Company email.';
                            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
                                return 'Please enter a valid email address.';
                            }
                            return '';
                        },
                        phone_number: (value) => {
                            value = (value || '').trim();
                            if (!value) return 'Please enter your mobile number.';
                            if (!/^\d{10}$/.test(value)) return 'Mobile number must be exactly 10 digits.';
                            return '';
                        },
                        password: (value) => {
                            value = value || '';
                            if (!value) return 'Please create a password.';
                            if (value.length < 8) return 'Password must be at least 8 characters.';
                            if (!/[a-z]/.test(value) || !/[A-Z]/.test(value) || !/\d/.test(value) || !/[^A-Za-z0-9]/.test(value)) {
                                return 'Include upper/lowercase letters, a number, and a symbol.';
                            }
                            return '';
                        },
                        password_confirmation: (value) => {
                            value = value || '';
                            if (!value) return 'Please confirm your password.';
                            if (value !== $passwordInput.val()) return 'Passwords do not match.';
                            return '';
                        },
                        terms: () => {
                            if (!$termsInput.is(':checked')) return 'Please accept the Terms & Conditions to create your account.';
                            return '';
                        },
                    };

                    function setFieldState(name, message) {
                        const $field = $registrationForm.find(`[name="${name}"]`);
                        const $errorNode = $registrationForm.find(`[data-error-for="${name}"]`);
                        if (!$field.length || !$errorNode.length) return;

                        const hasValue = $field.is(':checkbox') ? $field.is(':checked') : String($field.val() || '').trim().length > 0;
                        $field.removeClass('is-invalid is-valid');

                        if (message) {
                            $field.addClass('is-invalid');
                            $errorNode.text(message).removeClass('hidden');
                        } else {
                            $errorNode.text('').addClass('hidden');
                            if (hasValue) $field.addClass('is-valid');
                        }
                    }

                    function validateField(name) {
                        const $field = $registrationForm.find(`[name="${name}"]`);
                        if (!$field.length || typeof validators[name] !== 'function') return true;

                        const message = validators[name]($field.val());
                        setFieldState(name, message);
                        return message === '';
                    }

                    function updatePasswordStrength() {
                        const value = $passwordInput.val() || '';
                        let strength = 0;

                        if (value.length >= 8) strength += 1;
                        if (/[a-z]/.test(value) && /[A-Z]/.test(value)) strength += 1;
                        if (/\d/.test(value)) strength += 1;
                        if (/[^A-Za-z0-9]/.test(value)) strength += 1;

                        if ($passwordMeter.length) {
                            $passwordMeter[0].dataset.strength = String(strength);
                        }

                        if (!$passwordStrengthText.length) return;

                        if (!value.length) {
                            $passwordStrengthText.text('Use 8+ characters with upper/lowercase letters, a number, and a symbol.');
                        } else if (strength <= 1) {
                            $passwordStrengthText.text('Weak password. Add more variety and length.');
                        } else if (strength === 2) {
                            $passwordStrengthText.text('Decent start. Add a symbol or more complexity.');
                        } else if (strength === 3) {
                            $passwordStrengthText.text('Strong password. One more improvement makes it excellent.');
                        } else {
                            $passwordStrengthText.text('Excellent password strength.');
                        }
                    }

                    // Password show/hide
                    $(document).on('click', '[data-password-toggle]', function () {
                        const inputSelector = $(this).attr('data-password-toggle');
                        const $input = $(inputSelector);
                        if (!$input.length) return;

                        const nextType = $input.attr('type') === 'password' ? 'text' : 'password';
                        $input.attr('type', nextType);
                        $(this).text(nextType === 'password' ? 'Show' : 'Hide');
                    });

                    // Phone normalization
                    $phoneInput.on('input', function () {
                        const normalized = String($phoneInput.val() || '').replace(/\D/g, '').slice(0, 10);
                        $phoneInput.val(normalized);
                        validateField('phone_number');
                    });

                    // Field validation
                    $companyNameInput.on('blur input', function () { validateField('company_name'); });
                    $emailInput.on('blur input', function () { validateField('email'); });
                    $passwordInput.on('blur input', function () { validateField('password'); });
                    $confirmationInput.on('blur input', function () { validateField('password_confirmation'); });
                    $termsInput.on('change', function () { validateField('terms'); });

                    // Password strength + confirmation re-check
                    $passwordInput.on('input', function () {
                        updatePasswordStrength();
                        if (String($confirmationInput.val() || '').length) {
                            validateField('password_confirmation');
                        }
                    });

                    updatePasswordStrength();

                    $registrationForm.on('submit', function (event) {
                        const fieldNames = ['company_name', 'email', 'phone_number', 'password', 'password_confirmation', 'terms'];
                        let firstInvalid = null;

                        fieldNames.forEach((name) => {
                            const valid = validateField(name);
                            if (!valid && !firstInvalid) {
                                firstInvalid = $registrationForm.find(`[name="${name}"]`);
                            }
                        });

                        if (firstInvalid && firstInvalid.length) {
                            event.preventDefault();
                            firstInvalid.trigger('focus');
                        } else {
                            // Valid registration submit: show Thank You modal overlay
                            const companyName = String($companyNameInput.val() || 'your company').trim();
                            if (companyName) {
                                $('#thankYouCompanyName').text(companyName);
                            }
                            $('#registrationThankYouModal').removeClass('hidden').addClass('flex');
                            $registrationForm.css('pointer-events', 'none');

                            setTimeout(function () {
                                $('#createAccountBtn').prop('disabled', true).addClass('opacity-75 cursor-not-allowed').html(`
                                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white inline-block" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Creating Account...
                                `);
                            }, 50);

                            // Safety fallback: if the page is still active after 6 seconds, redirect to login
                            setTimeout(function () {
                                window.location.href = "{{ route('login') }}";
                            }, 6000);
                        }
                    });
                });
            })(window.jQuery);
        </script>
</x-guest-layout>
