<div class="fixed inset-x-0 bottom-4 z-[9998] px-4 sm:px-6 lg:px-8 pointer-events-none">
    <div
        data-pwa-shell
        class="mx-auto hidden w-full max-w-md pointer-events-auto rounded-3xl border border-slate-700/70 bg-slate-950/95 p-4 text-white shadow-2xl shadow-slate-950/30 backdrop-blur"
        role="status"
        aria-live="polite"
    >
        <div class="flex items-start gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-cyan-400/15 ring-1 ring-cyan-300/20">
                <svg class="h-6 w-6 text-cyan-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M4 7a3 3 0 013-3h10a3 3 0 013 3v10a3 3 0 01-3 3H7a3 3 0 01-3-3V7z" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M8 12h8M12 8v8" stroke-linecap="round" />
                </svg>
            </div>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold">Install {{ config('app.name', 'SaaS Workshop') }}</p>
                <p class="mt-1 text-xs leading-5 text-slate-300">
                    Add this app to your home screen for faster access, offline support, and a more app-like experience.
                </p>
            </div>

            <button
                type="button"
                data-pwa-dismiss
                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-white/10 hover:text-white"
                aria-label="Dismiss install prompt"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M6 6l12 12M18 6L6 18" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="mt-4 flex gap-2" data-pwa-standard-actions>
            <button
                type="button"
                data-pwa-install
                class="flex-1 rounded-2xl bg-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300 active:scale-[0.99]"
            >
                Add to Home Screen
            </button>
            <button
                type="button"
                data-pwa-later
                class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-white transition hover:bg-white/10"
            >
                Later
            </button>
        </div>

        <div data-pwa-ios-instructions class="mt-3 hidden rounded-2xl border border-cyan-400/20 bg-cyan-950/40 p-3 text-xs leading-relaxed text-slate-200">
            <div class="flex items-center gap-2 font-medium text-cyan-300 mb-1">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0-12l-4 4m4-4l4 4M4 16v4a2 2 0 002 2h12a2 2 0 002-2v-4" />
                </svg>
                <span>How to Install on iPhone / iPad:</span>
            </div>
            <ol class="list-decimal pl-4 space-y-1 text-slate-300">
                <li>Tap the <strong>Share</strong> button <svg class="inline-block h-3.5 w-3.5 text-cyan-300 mx-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" /></svg> in Safari.</li>
                <li>Scroll down and tap <strong>Add to Home Screen</strong>.</li>
            </ol>
            <div class="mt-2 text-right">
                <button type="button" data-pwa-later class="text-xs text-slate-400 hover:text-white underline">Got it / Close</button>
            </div>
        </div>

        <div
            data-pwa-offline
            class="mt-3 hidden rounded-2xl border border-amber-400/20 bg-amber-400/10 px-3 py-2 text-xs leading-5 text-amber-100"
        >
            You are offline. Cached content is still available.
        </div>
    </div>
</div>
