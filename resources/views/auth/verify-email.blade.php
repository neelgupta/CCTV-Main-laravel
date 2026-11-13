<x-guest-layout>
    <main class="ww-auth-stage relative isolate flex min-h-screen items-center justify-center overflow-hidden px-4 py-10 sm:px-6">
        <div class="ww-auth-orb -left-20 top-12 h-72 w-72 bg-rose-300/40"></div>
        <div class="ww-auth-orb bottom-8 right-0 h-80 w-80 bg-red-300/30"></div>

        <section class="relative w-full max-w-xl">
            <div class="overflow-hidden rounded-[32px] border border-white/70 bg-white/95 shadow-[0_30px_100px_rgba(15,23,42,0.18)] backdrop-blur">
                <div class="bg-[#060b1e] px-6 py-8 text-white sm:px-10 sm:py-10">
                    <div class="flex h-16 w-16 items-center justify-center rounded-2xl border border-rose-300/25 bg-rose-300/10 text-rose-300">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 7.5 12 13.125 20.25 7.5M5.25 4.5h13.5A2.25 2.25 0 0 1 21 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25H5.25A2.25 2.25 0 0 1 3 17.25V6.75A2.25 2.25 0 0 1 5.25 4.5Z" />
                        </svg>
                    </div>
                    <p class="mt-6 text-xs font-black uppercase tracking-[0.24em] text-rose-300">One last step</p>
                    <h1 class="mt-2 text-3xl font-black tracking-tight sm:text-4xl">Verify your email</h1>
                    <p class="mt-3 max-w-lg text-sm leading-6 text-slate-300">
                        We sent a confirmation link to your company email. Verify it to continue to plan selection and finish setting up your workspace.
                    </p>
                </div>

                <div class="px-6 py-7 sm:px-10 sm:py-9">
                    @if (session('registration_success') || session('success'))
                        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 flex items-start gap-3">
                            <span class="text-xl">🎉</span>
                            <div>
                                <p class="font-bold">Thank you for registering!</p>
                                <p class="text-xs text-emerald-700 mt-0.5">{{ session('success') ?? 'Your company account has been created successfully. Please verify your email below to finish setting up your workspace.' }}</p>
                            </div>
                        </div>
                    @endif

                    <div class="rounded-2xl border border-rose-100 bg-rose-50/70 p-4 sm:p-5">
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-rose-700">Verification email sent to</p>
                        <p class="mt-1 break-all text-base font-bold text-slate-900">{{ auth()->user()->email }}</p>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Open the email and click its verification button. If it is not in your inbox, check your spam folder or send a new link.</p>
                    </div>

                    @if (session('status') == 'verification-link-sent')
                        <div role="status" class="mt-5 flex gap-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.78-9.22a.75.75 0 0 0-1.06-1.06l-3.47 3.47-1.47-1.47a.75.75 0 0 0-1.06 1.06l2 2a.75.75 0 0 0 1.06 0l4-4Z" clip-rule="evenodd" /></svg>
                            <span>A fresh verification link has been sent to your email.</span>
                        </div>
                    @endif

                    @if (session('error'))
                        <div role="alert" class="mt-5 flex gap-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800">
                            <svg class="mt-0.5 h-5 w-5 shrink-0 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    <div class="mt-6 grid gap-3 sm:grid-cols-[1fr_auto]">
                        <form method="POST" action="{{ route('verification.send') }}">
                            @csrf
                            <button style="background-color: #c5233e;" type="submit" class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-red-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-red-200 transition hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M2.94 5.5A2 2 0 0 1 4.8 4.25h10.4a2 2 0 0 1 1.86 1.25L10 9.94 2.94 5.5Z"/><path d="M18 8.12 10.56 12.8a1 1 0 0 1-1.12 0L2 8.12V14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8.12Z"/></svg>
                                Resend verification email
                            </button>
                        </form>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="inline-flex min-h-12 w-full items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-600 transition hover:border-slate-300 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2 sm:w-auto">
                                Sign out
                            </button>
                        </form>
                    </div>

                    <p class="mt-6 text-center text-xs leading-5 text-slate-400">This secure link confirms that you can receive email at this address.</p>
                </div>
            </div>
            <p class="mt-5 text-center text-xs font-medium tracking-wide text-slate-500">WE4WORK <span class="mx-1 text-slate-300">·</span> Workspace setup</p>
        </section>
    </main>
</x-guest-layout>
