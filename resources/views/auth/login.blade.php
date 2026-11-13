<x-guest-layout>
    @php
        $loginError = $errors->first('email') ?: ($errors->first('password') ?: ($errors->any() ? 'These credentials do not match our records.' : null));
    @endphp

    <div class="ww-auth-stage">
        <div class="ww-auth-orb -left-20 top-12 h-72 w-72 bg-blue-300/45"></div>
        <div class="ww-auth-orb bottom-12 right-0 h-80 w-80 bg-cyan-300/35"></div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center">
            <div class="ww-auth-frame">
                <x-auth.brand-panel
                    title="Manage your workforce, projects, and operations from one polished command center."
                    description="Built for CCTV installation and security service businesses that need better visibility, faster team coordination, and seamless project execution."
                    :highlights="[
                        'Track leads, projects, tickets, and technicians from one branded workspace.',
                        'Give every field and office user clear next steps with live operational context.',
                    ]"
                    
                >
                    
                </x-auth.brand-panel>

                <section class="ww-auth-panel">
                    <div class="mt-5">
                        <h2 class="ww-heading">Welcome back</h2>
                        <p class="ww-subheading">Sign in to continue managing customers, projects, and field teams inside your CCTV Pro One workspace.</p>
                    </div>

                    @if (session('registration_success') || session('status'))
                        <div class="mt-6 rounded-2xl border border-emerald-200 bg-gradient-to-r from-emerald-50 via-teal-50 to-emerald-50 p-4 text-emerald-900 shadow-sm flex items-start gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white font-bold text-lg shadow-sm">
                                ✓
                            </div>
                            <div class="flex-1">
                                <h4 class="font-extrabold text-sm text-slate-900">Registration Successful! 🎉</h4>
                                <p class="mt-0.5 text-xs text-slate-700 leading-relaxed">
                                    {{ session('status') ?? 'Thank you for registering! Your company account has been created successfully. Please sign in to continue.' }}
                                </p>
                            </div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('login') }}" class="ww-form-grid" novalidate>
                        @csrf

                        <div class="ww-field">
                            <label for="email" class="ww-label">Email Address</label>
                            <div class="ww-input-icon-wrap">
                                <span class="ww-input-icon">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path d="M2.94 5.5A2 2 0 014.8 4.25h10.4a2 2 0 011.86 1.25L10 9.938 2.94 5.5z" />
                                        <path d="M18 8.118l-7.445 4.68a1 1 0 01-1.11 0L2 8.118V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
                                    </svg>
                                </span>
                                <input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value="{{ old('email', session('registered_email')) }}"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="name@company.com"
                                    class="ww-input ww-input-with-icon {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                />
                            </div>
                            <x-input-error :messages="$errors->get('email')" class="ww-field-error" />
                        </div>

                        <div class="ww-field">
                            <div class="flex items-center justify-between gap-3">
                                <label for="password" class="ww-label">Password</label>
                                @if (Route::has('password.request'))
                                    <a class="ww-inline-link text-sm" href="{{ route('password.request') }}">Forgot password?</a>
                                @endif
                            </div>

                            <div class="ww-input-icon-wrap">
                                <span class="ww-input-icon">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M5 8V6a5 5 0 1110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm2-2a3 3 0 116 0v2H7V6z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Enter your password"
                                    class="ww-input ww-input-with-icon {{ $errors->has('password') || $errors->has('email') ? 'is-invalid' : '' }}"
                                />
                                <button type="button" class="ww-input-action" data-password-toggle="#password">Show</button>
                            </div>
                            <x-input-error :messages="$errors->get('password')" class="ww-field-error" />
                        </div>

                        <div class="flex flex-col gap-4 pt-1 sm:flex-row sm:items-center sm:justify-between">
                            <label for="remember_me" class="inline-flex items-center gap-3 text-sm text-slate-600">
                                <input id="remember_me" type="checkbox" name="remember" class="h-4 w-4 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span>Keep me signed in on this device</span>
                            </label>

                            <div class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                                Encrypted access
                            </div>
                        </div>

                        <button type="submit" class="ww-primary-btn w-full">
                            Sign In
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3.25 10a.75.75 0 01.75-.75h10.19L10.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06l3.97-3.97H4A.75.75 0 013.25 10z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </form>

                    <div class="mt-8 border border-slate-200 bg-slate-50 px-5 py-4">
                        <p class="text-sm text-slate-600">
                            New to CCTV?
                            <a href="{{ route('register.company') }}" class="ww-inline-link">Create your company account</a>
                            and launch your onboarding flow in a few guided steps.
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.querySelectorAll('[data-password-toggle]').forEach((button) => {
                button.addEventListener('click', () => {
                    const input = document.querySelector(button.dataset.passwordToggle);

                    if (!input) {
                        return;
                    }

                    const nextType = input.type === 'password' ? 'text' : 'password';
                    input.type = nextType;
                    button.textContent = nextType === 'password' ? 'Show' : 'Hide';
                });
            });
        </script>
    @endpush
</x-guest-layout>
