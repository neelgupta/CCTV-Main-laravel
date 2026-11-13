<x-guest-layout>
    <div class="ww-auth-stage">
        <div class="ww-auth-orb -left-20 top-12 h-72 w-72 bg-blue-300/45"></div>
        <div class="ww-auth-orb bottom-12 right-0 h-80 w-80 bg-cyan-300/35"></div>

        <div class="relative mx-auto flex min-h-screen max-w-7xl items-center px-4 py-6 sm:px-6 lg:px-8">
            <div class="ww-auth-frame">
                <x-auth.brand-panel
                    eyebrow="Account Recovery"
                    title="Get back into your workspace without breaking the premium onboarding experience."
                    description="Enter your Email Address and we’ll send a secure password reset link so you can recover access and continue managing field operations."
                    :highlights="[
                        'Uses the same branded shell and spacing system as login and registration.',
                        'Keeps recovery focused on one clear action with stronger hierarchy and messaging.',
                    ]"
                >
                  
                </x-auth.brand-panel>

                <section class="ww-auth-panel">
                    <div class="mt-5">
                        <h2 class="ww-heading">Forgot your password?</h2>
                        <p class="ww-subheading">No problem. Share your Email Address and we’ll send a secure reset link to help you regain access.</p>
                    </div>

                    <x-auth-session-status class="ww-status-card ww-status-success mt-6" :status="session('status')" />

                    <form method="POST" action="{{ route('password.email') }}" class="ww-form-grid" novalidate>
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
                                    value="{{ old('email') }}"
                                    required
                                    autofocus
                                    autocomplete="email"
                                    placeholder="name@company.com"
                                    class="ww-input ww-input-with-icon {{ $errors->has('email') ? 'is-invalid' : '' }}"
                                />
                            </div>
                            <p class="ww-field-note">We’ll email a time-sensitive reset link to the address associated with your account.</p>
                            <x-input-error :messages="$errors->get('email')" class="ww-field-error" />
                        </div>

                        <button type="submit" class="ww-primary-btn w-full">
                            Send Reset Link
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M3.25 10a.75.75 0 01.75-.75h10.19L10.22 5.28a.75.75 0 111.06-1.06l5.25 5.25a.75.75 0 010 1.06l-5.25 5.25a.75.75 0 01-1.06-1.06l3.97-3.97H4A.75.75 0 013.25 10z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </form>

                    <div class="mt-8 rounded-3xl border border-slate-200 bg-slate-50 px-5 py-4">
                        <p class="text-sm text-slate-600">
                            Remembered your password?
                            <a href="{{ route('login') }}" class="ww-inline-link">Return to sign in</a>
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</x-guest-layout>
