<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950 leading-tight">WhatsApp</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Configure provider settings and broadcasting connection for marketing workflows.</p>
                </div>
                <a href="{{ route('marketing.email-template') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Back To Email Templates</a>
            </div>
        </div>
    </x-slot>

    <div x-data="whatsAppConfigWorkspace(@js($watiConfig))" x-init="init()" class="w-full py-4 sm:px-4 lg:px-6 space-y-4">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 py-4">
                <div class="flex flex-wrap items-center gap-3">
                    <button type="button" @click="setTab('send')" class="rounded-xl px-4 py-2.5 text-sm font-semibold transition" :class="activeTab === 'send' ? 'bg-white text-slate-950 shadow ring-1 ring-slate-200' : 'text-slate-500 hover:bg-slate-50'">Send &amp; Variables</button>
                    <button type="button" @click="setTab('api')" class="rounded-xl px-4 py-2.5 text-sm font-semibold transition" :class="activeTab === 'api' ? 'bg-white text-slate-950 shadow ring-1 ring-slate-200' : 'text-slate-500 hover:bg-slate-50'">API Configuration</button>
                </div>
            </div>

            <div class="space-y-5 p-4">
                <div x-show="activeTab === 'send'" class="space-y-5">
                    <div class="flex items-start justify-between gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-5">
                        <div>
                            <h4 class="text-xl font-semibold text-slate-950">WATI WhatsApp {{ $watiConfig['is_configured'] ? 'Configured' : 'Not Configured' }}</h4>
                            <p class="mt-1 text-sm text-slate-500">Configure WATI API keys to enable WhatsApp broadcasting for this conference.</p>
                        </div>
                        <button type="button" @click="showWatiConfig = true" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Configure WATI
                        </button>
                    </div>

                    <form method="POST" action="{{ route('marketing.email-template.wati-config.save') }}" class="rounded-2xl border border-sky-200 bg-sky-50/30 p-5" x-show="showWatiConfig">
                        @csrf
                        <input type="hidden" name="active_tab" :value="activeTab">
                        <input type="hidden" name="provider" value="WATI">

                        <div class="flex items-center justify-between gap-4">
                            <h4 class="text-xl font-semibold text-slate-950">WATI Configuration for Marketing Broadcast</h4>
                            <button type="button" @click="showWatiConfig = false" class="text-sm font-medium text-slate-700 hover:text-slate-950">Cancel</button>
                        </div>

                        <div class="mt-5 grid gap-5">
                            <div>
                                <label class="text-sm font-semibold text-slate-900">WATI API URL</label>
                                <input type="text" name="api_url" :value="form.api_url" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="https://live-mt-server.wati.io/xxxxx">
                                <p class="mt-2 text-xs text-slate-500">Found in WATI Dashboard → API Settings → API Endpoint URL</p>
                            </div>

                            <div>
                                <label class="text-sm font-semibold text-slate-900">WATI Access Token</label>
                                <div class="relative mt-2">
                                    <input :type="showSecret ? 'text' : 'password'" name="access_token" :value="form.access_token" class="block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 pr-12 text-sm text-slate-900" placeholder="Your WATI API access token">
                                    <button type="button" @click="showSecret = !showSecret" class="absolute inset-y-0 right-3 inline-flex items-center text-slate-400 hover:text-slate-600">
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </button>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Found in WATI Dashboard → API Settings → Access Token</p>
                            </div>

                            <div class="rounded-2xl bg-slate-50 px-5 py-4">
                                <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Quick Setup Guide</div>
                                <ol class="mt-3 space-y-2 text-sm text-slate-600">
                                    <li>1. Sign up at <span class="font-semibold text-slate-800">app.wati.io</span></li>
                                    <li>2. Go to <span class="font-semibold text-slate-800">Settings → API</span></li>
                                    <li>3. Copy the <span class="font-semibold text-slate-800">API Endpoint URL</span> and paste above</li>
                                    <li>4. Copy the <span class="font-semibold text-slate-800">Access Token</span> and paste above</li>
                                    <li>5. Click <span class="font-semibold text-slate-800">Save &amp; Connect</span></li>
                                </ol>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Save &amp; Connect</button>
                        </div>
                    </form>
                </div>

                <div x-show="activeTab === 'api'" class="space-y-5">
                    <form method="POST" action="{{ route('marketing.email-template.wati-config.save') }}" class="rounded-2xl border border-slate-200 bg-white p-5">
                        @csrf
                        <input type="hidden" name="active_tab" :value="activeTab">

                        <div>
                            <h4 class="text-xl font-semibold text-slate-950">WhatsApp Business API</h4>
                            <p class="mt-1 text-sm text-slate-500">Connect any WhatsApp Business API provider for this conference.</p>
                        </div>

                        <div class="mt-5 grid gap-5">
                            <div>
                                <label class="text-sm font-semibold text-slate-900">WhatsApp API Provider</label>
                                <select name="provider" x-model="selectedProvider" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                                    <option value="WATI">WATI</option>
                                    <option value="Twilio">Twilio</option>
                                    <option value="Gupshup">Gupshup</option>
                                    <option value="Interakt">Interakt</option>
                                    <option value="WebGlowPro">WebGlowPro</option>
                                    <option value="Other (Generic Webhook)">Other (Generic Webhook)</option>
                                </select>
                            </div>

                            <div x-show="selectedProvider === 'WATI'" class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">API URL</label>
                                    <input type="text" name="api_url" :value="form.api_url" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="https://live-mt-server.wati.io/xxxxx">
                                    <p class="mt-2 text-xs text-slate-500">Found in WATI Dashboard → API Settings → API Endpoint URL</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">Access Token</label>
                                    <textarea name="access_token" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Your WATI API access token" x-text="form.access_token"></textarea>
                                    <p class="mt-2 text-xs text-slate-500">Found in WATI Dashboard → API Settings → Access Token</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-5 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">WATI Setup Guide</div>
                                    <ol class="mt-3 space-y-2 text-sm text-slate-600">
                                        <li>Sign up at app.wati.io</li>
                                        <li>Go to Settings → API</li>
                                        <li>Copy the API Endpoint URL and paste above</li>
                                        <li>Copy the Access Token and paste above</li>
                                    </ol>
                                </div>
                            </div>

                            <div x-show="selectedProvider === 'Twilio'" class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">Account SID</label>
                                    <input type="text" name="account_sid" :value="form.account_sid" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="ACxxxxx">
                                    <p class="mt-2 text-xs text-slate-500">Found in Twilio Console → Account Info</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">Auth Token</label>
                                    <textarea name="auth_token" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Your Twilio Auth Token" x-text="form.auth_token"></textarea>
                                    <p class="mt-2 text-xs text-slate-500">Found in Twilio Console → Account Info</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">WhatsApp From Number</label>
                                    <input type="text" name="from_number" :value="form.from_number" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="whatsapp:+14155238886">
                                    <p class="mt-2 text-xs text-slate-500">Your Twilio WhatsApp-enabled number (e.g. whatsapp:+14155238886)</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-5 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Twilio Setup Guide</div>
                                    <ol class="mt-3 space-y-2 text-sm text-slate-600">
                                        <li>Sign up at twilio.com</li>
                                        <li>Go to Console → Account Info</li>
                                        <li>Copy Account SID and Auth Token</li>
                                        <li>Enable WhatsApp in Messaging → Senders</li>
                                        <li>Enter your WhatsApp-enabled number above</li>
                                    </ol>
                                </div>
                            </div>

                            <div x-show="selectedProvider === 'Gupshup'" class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">API Key</label>
                                    <textarea name="api_key" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Your Gupshup API key" x-text="form.api_key"></textarea>
                                    <p class="mt-2 text-xs text-slate-500">Found in Gupshup Dashboard → API Settings</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">App Name</label>
                                    <input type="text" name="app_name" :value="form.app_name" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Your Gupshup app name">
                                    <p class="mt-2 text-xs text-slate-500">The app name registered in Gupshup</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">Source Number</label>
                                    <input type="text" name="source_number" :value="form.source_number" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="+91xxxxxxxxxx">
                                    <p class="mt-2 text-xs text-slate-500">Your registered WhatsApp business number</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-5 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Gupshup Setup Guide</div>
                                    <ol class="mt-3 space-y-2 text-sm text-slate-600">
                                        <li>Sign up at gupshup.io</li>
                                        <li>Create a WhatsApp app in the dashboard</li>
                                        <li>Copy your API Key from Settings</li>
                                        <li>Enter the app name and source number above</li>
                                    </ol>
                                </div>
                            </div>

                            <div x-show="selectedProvider === 'Interakt'" class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">API Key</label>
                                    <textarea name="interakt_api_key" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Your Interakt API key" x-text="form.interakt_api_key"></textarea>
                                    <p class="mt-2 text-xs text-slate-500">Found in Interakt Dashboard → Developer Settings</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-5 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Interakt Setup Guide</div>
                                    <ol class="mt-3 space-y-2 text-sm text-slate-600">
                                        <li>Sign up at interakt.shop</li>
                                        <li>Go to Developer Settings → API Keys</li>
                                        <li>Copy the API key and paste above</li>
                                        <li>Save</li>
                                    </ol>
                                </div>
                            </div>

                            <div x-show="selectedProvider === 'WebGlowPro'" class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">API Base URL</label>
                                    <input type="text" name="base_url" :value="form.base_url" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="https://app.webglowpro.com">
                                    <p class="mt-2 text-xs text-slate-500">WebGlowPro base URL (default: https://app.webglowpro.com)</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">WABA ID</label>
                                    <input type="text" name="waba_id" :value="form.waba_id" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="e.g. 333831519811947">
                                    <p class="mt-2 text-xs text-slate-500">Your WhatsApp Business Account ID — visible in WebGlowPro URL: /vb/manage/whatsapp-templates/&lt;WABA_ID&gt;</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">Access Token (JWT)</label>
                                    <textarea name="jwt_token" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..." x-text="form.jwt_token"></textarea>
                                    <p class="mt-2 text-xs text-slate-500">Login to WebGlowPro, open DevTools → Application → Cookies → copy the value of 'accessToken' cookie</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">Phone Number ID</label>
                                    <input type="text" name="phone_number_id" :value="form.phone_number_id" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Provider phone number ID">
                                    <p class="mt-2 text-xs text-slate-500">Used when sending messages (separate from WABA ID)</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-5 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">WebGlowPro Setup Guide</div>
                                    <ol class="mt-3 space-y-2 text-sm text-slate-600">
                                        <li>Login to https://app.webglowpro.com</li>
                                        <li>Open the Templates page — copy the WABA ID from the URL</li>
                                        <li>Open DevTools → Application → Cookies → copy the 'accessToken' value</li>
                                        <li>Paste both above and Save — then click 'Sync Templates' in the Broadcast tab</li>
                                        <li>Save</li>
                                    </ol>
                                </div>
                            </div>

                            <div x-show="selectedProvider === 'Other (Generic Webhook)'" class="space-y-4">
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">Webhook URL</label>
                                    <input type="text" name="webhook_url" :value="form.webhook_url" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="https://your-api.com/send-message">
                                    <p class="mt-2 text-xs text-slate-500">The API endpoint to POST message payloads to</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">API Key / Auth Token</label>
                                    <textarea name="generic_auth_token" rows="3" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Your API key or auth token" x-text="form.generic_auth_token"></textarea>
                                    <p class="mt-2 text-xs text-slate-500">Sent in both Authorization: Bearer and API_KEY headers for compatibility</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">From Number (optional)</label>
                                    <input type="text" name="from_number" :value="form.from_number" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="+91xxxxxxxxxx">
                                    <p class="mt-2 text-xs text-slate-500">Source WhatsApp number if required by your provider</p>
                                </div>
                                <div>
                                    <label class="text-sm font-semibold text-slate-900">Phone Number ID (WebGlowPro/Meta)</label>
                                    <input type="text" name="phone_number_id" :value="form.phone_number_id" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Provider phone number ID">
                                    <p class="mt-2 text-xs text-slate-500">Required by WebGlowPro-style APIs. This can be different from the visible WhatsApp number.</p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-5 py-4">
                                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Other (Generic Webhook) Setup Guide</div>
                                    <ol class="mt-3 space-y-2 text-sm text-slate-600">
                                        <li>Enter your WhatsApp API webhook endpoint</li>
                                        <li>For WebGlowPro, add the Phone Number ID along with the API key</li>
                                        <li>Template messages are sent with flat JSON and bodyParams</li>
                                        <li>Auth is sent in both Authorization Bearer and API_KEY headers for compatibility</li>
                                        <li>Save</li>
                                    </ol>
                                </div>
                            </div>
                        </div>

                        <div class="mt-5 flex justify-end">
                            <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Save</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>

    @push('scripts')
        <script>
            function whatsAppConfigWorkspace(config) {
                return {
                    activeTab: config.active_tab || 'send',
                    selectedProvider: config.provider || 'WATI',
                    showWatiConfig: Boolean(config.is_configured) || (config.active_tab === 'send'),
                    showSecret: false,
                    init() {
                        const tab = new URL(window.location.href).searchParams.get('tab');
                        if (tab === 'send' || tab === 'api') {
                            this.activeTab = tab;
                        }
                    },
                    setTab(tab) {
                        this.activeTab = tab;
                        const url = new URL(window.location.href);
                        url.searchParams.set('tab', tab);
                        window.history.replaceState({}, '', url.toString());
                    },
                    form: {
                        api_url: config.api_url || '',
                        access_token: config.access_token || '',
                        account_sid: config.account_sid || '',
                        auth_token: config.auth_token || '',
                        from_number: config.from_number || '',
                        api_key: config.api_key || '',
                        app_name: config.app_name || '',
                        source_number: config.source_number || '',
                        interakt_api_key: config.interakt_api_key || '',
                        base_url: config.base_url || 'https://app.webglowpro.com',
                        waba_id: config.waba_id || '',
                        jwt_token: config.jwt_token || '',
                        phone_number_id: config.phone_number_id || '',
                        webhook_url: config.webhook_url || '',
                        generic_auth_token: config.generic_auth_token || '',
                    },
                };
            }
        </script>
    @endpush
</x-app-layout>
