<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">
                {{ __('WhatsApp Settings') }}
            </h2>
            <p class="mt-1 text-sm text-slate-500">Configure your Meta WhatsApp Cloud API credentials to receive and process messages.</p>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto py-6 sm:px-6 lg:px-8 space-y-6">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ session('error') }}
            </div>
        @endif

        <!-- Main Card Wrapper -->
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <!-- Card Header -->
            <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-5 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">WhatsApp / Communication Settings</h3>
                        <p class="text-xs text-slate-500">Manage credentials and webhooks in one place.</p>
                    </div>
                </div>

                @if($account)
                    <div class="flex gap-2">
                        <form method="POST" action="{{ route('integrations.whatsapp.verify') }}">
                            @csrf
                            <input type="hidden" name="uuid" value="{{ $account->uuid }}">
                            <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-indigo-50 border border-indigo-200 text-indigo-700 px-4 py-2 text-xs font-semibold hover:bg-indigo-100 transition shadow-sm">
                                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                Verify Connection
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Card Content Body -->
            <div class="p-6 space-y-6">
                @if($account)
                    <!-- Connection Status Banner -->
                    <div @class([
                        'rounded-2xl border px-4 py-4 flex items-start gap-3 text-sm shadow-sm',
                        'border-emerald-200 bg-emerald-50 text-emerald-800' => $account->status === \App\Modules\Communication\Enums\ConnectionStatusEnum::CONNECTED,
                        'border-amber-200 bg-amber-50 text-amber-800' => $account->status === \App\Modules\Communication\Enums\ConnectionStatusEnum::DISCONNECTED,
                        'border-rose-200 bg-rose-50 text-rose-800' => $account->status === \App\Modules\Communication\Enums\ConnectionStatusEnum::FAILED,
                    ])>
                        <div class="mt-0.5">
                            @if($account->status === \App\Modules\Communication\Enums\ConnectionStatusEnum::CONNECTED)
                                <svg class="h-5 w-5 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            @else
                                <svg class="h-5 w-5 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            @endif
                        </div>
                        <div>
                            <span class="font-semibold block">
                                WhatsApp connection status: {{ $account->status->label() }}
                            </span>
                            <span class="text-xs mt-0.5 opacity-90 block">
                                Registered phone number: <strong>{{ $account->phone_number }}</strong> 
                                @if($account->last_verified_at)
                                    • Last verified {{ $account->last_verified_at->diffForHumans() }}
                                @endif
                            </span>
                        </div>
                    </div>

                    <!-- Webhook Callback URL Card -->
                    <div class="rounded-2xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-3">
                        <div>
                            <h4 class="font-bold text-slate-900 text-sm">Webhook callback URL (paste in Meta)</h4>
                            <p class="text-xs text-slate-500 mt-0.5">Meta → WhatsApp → Configuration → Webhook → Callback URL. Subscribe to messages for incoming chats.</p>
                        </div>
                        
                        <div class="flex gap-2 items-center">
                            <input id="callback_url" type="text" class="block w-full rounded-xl border-slate-200 bg-white font-mono text-xs p-2.5 text-slate-600 select-all" 
                                   value="{{ route('integrations.whatsapp.webhook.verify', ['uuid' => $account->uuid]) }}" readonly>
                            <button id="copyBtn" onclick="copyCallbackUrl()" type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-sm whitespace-nowrap">
                                <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path>
                                </svg>
                                Copy
                            </button>
                        </div>

                        <div class="text-xs text-slate-600">
                            <strong>Verify Token:</strong> <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200 font-mono">{{ $account->verify_token }}</code>
                        </div>
                    </div>
                @endif

                <!-- Credentials Setup Guide -->
                <div class="rounded-2xl border border-slate-200 bg-slate-50/50 p-4 space-y-2">
                    <h4 class="font-bold text-slate-900 text-sm">How to get your credentials:</h4>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        Navigate to your <a href="https://developers.facebook.com/" target="_blank" class="text-indigo-600 hover:underline font-semibold">Meta Business Settings</a> → WhatsApp → API Setup to find your WhatsApp Access Token, WhatsApp Business Account ID (WABA ID), and Phone Number ID. For incoming messages, register the Webhook callback URL displayed above under Meta → WhatsApp → Configuration → Webhook (using the same Webhook Verify Token you set below).
                    </p>
                </div>

                <!-- Settings Form -->
                <form method="POST" action="{{ route('integrations.whatsapp.connect') }}" class="space-y-5">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="display_name" value="Display Name *" />
                            <x-text-input id="display_name" name="display_name" class="mt-1 block w-full rounded-xl" 
                                          value="{{ old('display_name', $account?->display_name) }}" 
                                          placeholder="e.g. Support Desk" required />
                        </div>

                        <div>
                            <x-input-label for="phone_number" value="WhatsApp Phone Number *" />
                            <x-text-input id="phone_number" name="phone_number" class="mt-1 block w-full rounded-xl" 
                                          value="{{ old('phone_number', $account?->phone_number) }}" 
                                          placeholder="e.g. +919281463330" required />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                        <div>
                            <x-input-label for="phone_number_id" value="Phone Number ID *" />
                            <x-text-input id="phone_number_id" name="phone_number_id" class="mt-1 block w-full rounded-xl" 
                                          value="{{ old('phone_number_id', $account?->phone_number_id) }}" 
                                          placeholder="Found in Meta dashboard" required />
                        </div>

                        <div>
                            <x-input-label for="business_account_id" value="Business Account ID *" />
                            <x-text-input id="business_account_id" name="business_account_id" class="mt-1 block w-full rounded-xl" 
                                          value="{{ old('business_account_id', $account?->business_account_id) }}" 
                                          placeholder="Business Account ID" required />
                        </div>

                        <div>
                            <x-input-label for="app_id" value="Meta App ID" />
                            <x-text-input id="app_id" name="app_id" class="mt-1 block w-full rounded-xl" 
                                          value="{{ old('app_id', $account?->app_id) }}" 
                                          placeholder="e.g. 12456789" />
                        </div>
                    </div>

                    <div class="border-t border-slate-100 pt-5">
                        <x-input-label for="access_token" value="WhatsApp Access Token *" />
                        <x-text-input id="access_token" name="access_token" type="text" class="mt-1 block w-full rounded-xl font-mono text-sm" 
                                      value="{{ old('access_token', $account ? '••••••••••••••••••••••••••••••••••••••••••••••••••••' : '') }}" 
                                      placeholder="Paste permanent Meta access token" required />
                        <p class="text-[11px] text-slate-400 mt-1">Generate a permanent system user access token in Meta Business Manager Settings.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        <div>
                            <x-input-label for="verify_token" value="Webhook Verify Token *" />
                            <x-text-input id="verify_token" name="verify_token" class="mt-1 block w-full rounded-xl font-mono" 
                                          value="{{ old('verify_token', $account?->verify_token) }}" 
                                          placeholder="Handshake verification token string" required />
                        </div>

                        <div>
                            <x-input-label for="webhook_secret" value="App Secret (Optional)" />
                            <x-text-input id="webhook_secret" name="webhook_secret" type="password" class="mt-1 block w-full rounded-xl" 
                                          value="{{ old('webhook_secret', $account?->webhook_secret) }}" 
                                          placeholder="App Secret for request signature validations" />
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-100 pt-5">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                            {{ __('Save & Connect') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JavaScript helper to copy callback URL -->
    <script>
        function copyCallbackUrl() {
            var copyText = document.getElementById("callback_url");
            copyText.select();
            copyText.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(copyText.value).then(function() {
                var btn = document.getElementById("copyBtn");
                var originalHTML = btn.innerHTML;
                btn.innerHTML = `
                    <svg class="h-4 w-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                    Copied!
                `;
                setTimeout(function() {
                    btn.innerHTML = originalHTML;
                }, 2000);
            });
        }
    </script>
</x-app-layout>
