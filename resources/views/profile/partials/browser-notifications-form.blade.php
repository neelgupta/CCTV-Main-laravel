@php
    $pushPreference = $notificationPreference ?? auth()->user()?->notificationPreference;
    $pushEnabled = (bool) ($pushPreference?->push_enabled ?? false);
    $firebaseReady = (bool) (config('services.firebase.project_id') && config('services.firebase.api_key') && config('services.firebase.messaging_sender_id') && config('services.firebase.app_id') && config('services.firebase.vapid_key'));
    $hasCompany = (bool) (auth()->user()?->company_id ?? false);
    $missingConfigKeys = $firebasePushConfig['missingConfigKeys'] ?? [];
@endphp

<section class="bg-white p-6 rounded-lg border border-slate-100">
    <header class="border-b border-gray-100 pb-4 mb-6">
        <h2 class="text-lg font-bold text-gray-900">
            {{ __('Windows Notifications') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Allow Firebase push notifications so new app notifications can appear as Windows system toasts in Chrome or Edge.') }}
        </p>
    </header>

    @if($hasCompany)
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Current state</div>
            <div class="mt-2 text-base font-semibold text-slate-900" data-push-status-text>
                {{ $pushEnabled ? 'Enabled on this account' : 'Disabled on this account' }}
            </div>
            <p class="mt-2 text-sm text-slate-500">
                {{ $pushEnabled ? 'This browser can receive system notifications when the tab is closed or minimized.' : 'Turn this on to register the current device with Firebase Cloud Messaging.' }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Browser permission</div>
            <div class="mt-2 text-base font-semibold text-slate-900" data-push-permission-text>
                Not requested yet
            </div>
            <p class="mt-2 text-sm text-slate-500">
                {{ __('Chrome and Edge must allow notifications for Windows to show the popup.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Firebase setup</div>
            <div class="mt-2 text-base font-semibold {{ $firebaseReady ? 'text-emerald-700' : 'text-rose-700' }}">
                {{ $firebaseReady ? 'Configured' : 'Missing config' }}
            </div>
            <p class="mt-2 text-sm text-slate-500">
                {{ $firebaseReady ? 'The app can register tokens and deliver FCM messages.' : 'Set the Firebase env variables before enabling push.' }}
            </p>
            @if(! $firebaseReady && ! empty($missingConfigKeys))
                <div class="mt-3 rounded-xl border border-rose-200 bg-white px-3 py-2 text-xs text-rose-700">
                    Missing:
                    <span class="font-semibold">{{ implode(', ', $missingConfigKeys) }}</span>
                </div>
            @endif
        </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button
                type="button"
                data-push-enable
                class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                @disabled(! $firebaseReady)
            >
                Enable on this device
            </button>
            <button
                type="button"
                data-push-disable
                class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
            >
                Disable on this device
            </button>
        </div>

        <p class="mt-4 text-xs text-slate-500">
            If notification permission is blocked in the browser, open Chrome or Edge site settings and allow notifications for this domain.
        </p>
    @else
        <div class="rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Company notifications are only available after a company account is linked to your profile.
        </div>
    @endif
</section>
