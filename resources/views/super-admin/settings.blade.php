<x-app-layout>
    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">Global Settings</h2>
            <p class="mt-1 text-sm text-slate-500">Manage platform-wide configuration for Super Admin only.</p>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
    @endif

    <div class="space-y-6">
        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-2 border-b border-slate-200 pb-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-cyan-700">Secure Configuration</p>
                    <h3 class="mt-1 text-2xl font-bold text-slate-950">Settings</h3>
                    <p class="mt-1 text-sm text-slate-500">Secret values are stored encrypted and are never displayed in full.</p>
                </div>
            </div>

            <form method="POST" action="{{ route('super-admin.settings.update') }}" class="mt-6 space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="rounded-3xl border border-slate-200 bg-slate-50/60 p-5">
                        <h4 class="text-lg font-bold text-slate-950">Business</h4>
                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Google Maps API Key</label>
                                <input type="text" name="google_maps_api_key" value="{{ old('google_maps_api_key', $settings['google_maps_api_key'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="Loaded from settings table">
                                <x-input-error :messages="$errors->settingsForm->get('google_maps_api_key')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Subscription GST Rate (%)</label>
                                <input type="number" step="0.01" min="0" max="100" name="subscription_gst_percentage" value="{{ old('subscription_gst_percentage', $settings['subscription_gst_percentage'] ?? '18') }}" class="block w-full rounded-2xl border-slate-300" placeholder="18">
                                <p class="mt-2 text-xs text-slate-500">Percentage of GST added to subscription payments (default 18%).</p>
                                <x-input-error :messages="$errors->settingsForm->get('subscription_gst_percentage')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">GST Number</label>
                                <input type="text" name="gst_number" value="{{ old('gst_number', $settings['gst_number'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="Business GST number">
                                <x-input-error :messages="$errors->settingsForm->get('gst_number')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Business Name</label>
                                <input type="text" name="business_name" value="{{ old('business_name', $settings['business_name'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="Public business name">
                                <x-input-error :messages="$errors->settingsForm->get('business_name')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Support Email</label>
                                <input type="email" name="support_email" value="{{ old('support_email', $settings['support_email'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="support@example.com">
                                <x-input-error :messages="$errors->settingsForm->get('support_email')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Mobile No</label>
                                <input type="text" name="mobile_number" value="{{ old('mobile_number', $settings['mobile_number'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="9999999999">
                                <x-input-error :messages="$errors->settingsForm->get('mobile_number')" class="mt-2" />
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Support Call Number</label>
                                    <input type="tel" name="support_call_number" value="{{ old('support_call_number', $settings['support_call_number'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="+91 98765 43210">
                                    <x-input-error :messages="$errors->settingsForm->get('support_call_number')" class="mt-2" />
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Support WhatsApp Number</label>
                                    <input type="tel" name="support_whatsapp_number" value="{{ old('support_whatsapp_number', $settings['support_whatsapp_number'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="+91 98765 43210">
                                    <x-input-error :messages="$errors->settingsForm->get('support_whatsapp_number')" class="mt-2" />
                                </div>
                            </div>
                            <p class="-mt-2 text-xs text-slate-500">Shown in the Support menu in the header for all users. Include the country code; a 10-digit number is treated as +91. Leave both blank to hide the menu.</p>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Address</label>
                                <textarea name="address" rows="3" class="block w-full rounded-2xl border-slate-300" placeholder="Business address">{{ old('address', $settings['address'] ?? '') }}</textarea>
                                <x-input-error :messages="$errors->settingsForm->get('address')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-slate-200 bg-slate-50/60 p-5">
                        <h4 class="text-lg font-bold text-slate-950">Email & SMTP</h4>
                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Email From Name</label>
                                <input type="text" name="email_from_name" value="{{ old('email_from_name', $settings['email_from_name'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="Sender display name">
                                <x-input-error :messages="$errors->settingsForm->get('email_from_name')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Email From Address</label>
                                <input type="email" name="email_from_address" value="{{ old('email_from_address', $settings['email_from_address'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="noreply@example.com">
                                <x-input-error :messages="$errors->settingsForm->get('email_from_address')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">SMTP Host</label>
                                <input type="text" name="smtp_host" value="{{ old('smtp_host', $settings['smtp_host'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="Loaded from settings table">
                                <x-input-error :messages="$errors->settingsForm->get('smtp_host')" class="mt-2" />
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">SMTP Port</label>
                                    <input type="number" name="smtp_port" min="1" max="65535" value="{{ old('smtp_port', $settings['smtp_port'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="Loaded from settings table">
                                    <x-input-error :messages="$errors->settingsForm->get('smtp_port')" class="mt-2" />
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">SMTP Encryption</label>
                                    <select name="smtp_encryption" class="block w-full rounded-2xl border-slate-300">
                                        @php($smtpEncryption = old('smtp_encryption', $settings['smtp_encryption'] ?? ''))
                                        <option value="">None</option>
                                        <option value="tls" @selected($smtpEncryption === 'tls')>TLS</option>
                                        <option value="ssl" @selected($smtpEncryption === 'ssl')>SSL</option>
                                        <option value="null" @selected($smtpEncryption === 'null')>None (null)</option>
                                    </select>
                                    <x-input-error :messages="$errors->settingsForm->get('smtp_encryption')" class="mt-2" />
                                </div>
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">SMTP Username</label>
                                <input type="text" name="smtp_username" value="{{ old('smtp_username', $settings['smtp_username'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="Loaded from settings table">
                                <x-input-error :messages="$errors->settingsForm->get('smtp_username')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">SMTP Password</label>
                                <input type="password" name="smtp_password" class="block w-full rounded-2xl border-slate-300" placeholder="Leave blank to keep the stored secret">
                                <p class="mt-2 text-xs text-slate-500">Stored securely. A saved value is never shown here.</p>
                                <x-input-error :messages="$errors->settingsForm->get('smtp_password')" class="mt-2" />
                            </div>
                        </div>
                    </section>

                    @php($gatewayMode = old('payment_gateway_mode', $settings['payment_gateway_mode'] ?? 'live') === 'test' ? 'test' : 'live')
                    <section class="rounded-3xl border border-slate-200 bg-slate-50/60 p-5">
                        <h4 class="text-lg font-bold text-slate-950">Payment Gateway (Razorpay)</h4>
                        <p class="mt-1 text-sm text-slate-500">Only the key set for the selected mode is used for payments.</p>
                        <div class="mt-5 space-y-4">
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Mode</label>
                                <select name="payment_gateway_mode" class="block w-full rounded-2xl border-slate-300">
                                    <option value="live" @selected($gatewayMode === 'live')>Live</option>
                                    <option value="test" @selected($gatewayMode === 'test')>Test</option>
                                </select>
                                <p class="mt-2 text-xs text-slate-500">Switching mode while recurring subscriptions are active will stop their renewals, because mandates belong to one mode.</p>
                                <x-input-error :messages="$errors->settingsForm->get('payment_gateway_mode')" class="mt-2" />
                            </div>
                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Webhook URL</label>
                                <div class="flex gap-2">
                                    <input type="text" id="razorpay-webhook-url" value="{{ route('api.razorpay.webhook') }}" class="block w-full min-w-0 rounded-2xl border-slate-300 bg-slate-100 text-slate-600" readonly>
                                    <button type="button" class="shrink-0 rounded-2xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-100"
                                        onclick="navigator.clipboard.writeText(document.getElementById('razorpay-webhook-url').value).then(() => { this.textContent = 'Copied'; setTimeout(() => this.textContent = 'Copy', 1500); })">Copy</button>
                                </div>
                                <p class="mt-2 text-xs text-slate-500">Add this URL in Razorpay Dashboard → Account &amp; Settings → Webhooks, separately for Live and Test mode, with the matching webhook secret below.</p>
                            </div>

                            @foreach ([
                                'live' => ['label' => 'Live Keys', 'prefix' => 'payment_gateway_', 'placeholder' => 'rzp_live_...'],
                                'test' => ['label' => 'Test Keys', 'prefix' => 'payment_gateway_test_', 'placeholder' => 'rzp_test_...'],
                            ] as $keyMode => $keySet)
                                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                                    <div class="flex items-center justify-between">
                                        <p class="text-sm font-bold text-slate-900">{{ $keySet['label'] }}</p>
                                        @if ($gatewayMode === $keyMode)
                                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">In use</span>
                                        @endif
                                    </div>
                                    <div class="mt-4 space-y-4">
                                        <div>
                                            <label class="mb-2 block text-sm font-semibold text-slate-700">Key ID</label>
                                            <input type="text" name="{{ $keySet['prefix'] }}public_key" value="{{ old($keySet['prefix'] . 'public_key', $settings[$keySet['prefix'] . 'public_key'] ?? '') }}" class="block w-full rounded-2xl border-slate-300" placeholder="{{ $keySet['placeholder'] }}">
                                            <x-input-error :messages="$errors->settingsForm->get($keySet['prefix'] . 'public_key')" class="mt-2" />
                                        </div>
                                        @foreach (['secret_key' => 'Key Secret', 'webhook_key' => 'Webhook Secret'] as $secretSuffix => $secretLabel)
                                            @php($secretField = $keySet['prefix'] . $secretSuffix)
                                            <div>
                                                <label class="mb-2 block text-sm font-semibold text-slate-700">{{ $secretLabel }}</label>
                                                <input type="password" name="{{ $secretField }}" class="block w-full rounded-2xl border-slate-300" placeholder="Leave blank to keep the stored secret" autocomplete="new-password">
                                                <p class="mt-2 text-xs text-slate-500">
                                                    {{ filled($settings[$secretField] ?? null) ? 'A value is saved.' : 'Not set.' }}
                                                    Stored securely. A saved value is never shown here.
                                                </p>
                                                <x-input-error :messages="$errors->settingsForm->get($secretField)" class="mt-2" />
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>

                    @php($recurringEnabled = (bool) old('subscription_recurring_enabled', $settings['subscription_recurring_enabled'] ?? $recurringDefaults['enabled'] ?? false))
                    @php($recurringMode = old('subscription_recurring_mode', $settings['subscription_recurring_mode'] ?? $recurringDefaults['mode'] ?? 'optional'))
                    @php($graceAccess = old('subscription_grace_access', $settings['subscription_grace_access'] ?? $recurringDefaults['grace_access'] ?? 'full'))
                    @php($trialRequiresMandate = (bool) old('subscription_trial_requires_mandate', $settings['subscription_trial_requires_mandate'] ?? $recurringDefaults['trial_requires_mandate'] ?? false))
                    <section class="rounded-3xl border border-slate-200 bg-slate-50/60 p-5">
                        <h4 class="text-lg font-bold text-slate-950">Recurring Billing</h4>
                        <p class="mt-1 text-sm text-slate-500">Auto-renewing subscription plans charged through Razorpay Subscriptions.</p>
                        <div class="mt-5 space-y-4">
                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                                <input type="hidden" name="subscription_recurring_enabled" value="0">
                                <input type="checkbox" name="subscription_recurring_enabled" value="1" class="mt-0.5 rounded border-slate-300" @checked($recurringEnabled)>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-700">Enable recurring billing</span>
                                    <span class="mt-1 block text-xs text-slate-500">Companies can buy plans that renew and charge automatically.</span>
                                </span>
                            </label>
                            <x-input-error :messages="$errors->settingsForm->get('subscription_recurring_enabled')" class="mt-2" />

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Payment Option</label>
                                <select name="subscription_recurring_mode" class="block w-full rounded-2xl border-slate-300">
                                    <option value="optional" @selected($recurringMode !== 'mandatory')>Optional (company chooses auto-renew or one-time payment)</option>
                                    <option value="mandatory" @selected($recurringMode === 'mandatory')>Mandatory (all paid plans auto-renew)</option>
                                </select>
                                <x-input-error :messages="$errors->settingsForm->get('subscription_recurring_mode')" class="mt-2" />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Grace Period (days)</label>
                                    <input type="number" name="subscription_grace_days" min="0" max="30" value="{{ old('subscription_grace_days', $settings['subscription_grace_days'] ?? $recurringDefaults['grace_days'] ?? 3) }}" class="block w-full rounded-2xl border-slate-300">
                                    <p class="mt-2 text-xs text-slate-500">Access kept after a failed renewal charge.</p>
                                    <x-input-error :messages="$errors->settingsForm->get('subscription_grace_days')" class="mt-2" />
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Access During Grace Period</label>
                                    <select name="subscription_grace_access" class="block w-full rounded-2xl border-slate-300">
                                        <option value="full" @selected($graceAccess !== 'read_only')>Full access</option>
                                        <option value="read_only" @selected($graceAccess === 'read_only')>Read only</option>
                                    </select>
                                    <x-input-error :messages="$errors->settingsForm->get('subscription_grace_access')" class="mt-2" />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Billing Cycles (Monthly)</label>
                                    <input type="number" name="subscription_total_count_monthly" min="1" max="1200" value="{{ old('subscription_total_count_monthly', $settings['subscription_total_count_monthly'] ?? $recurringDefaults['total_count_monthly'] ?? 120) }}" class="block w-full rounded-2xl border-slate-300">
                                    <x-input-error :messages="$errors->settingsForm->get('subscription_total_count_monthly')" class="mt-2" />
                                </div>
                                <div>
                                    <label class="mb-2 block text-sm font-semibold text-slate-700">Billing Cycles (Yearly)</label>
                                    <input type="number" name="subscription_total_count_yearly" min="1" max="100" value="{{ old('subscription_total_count_yearly', $settings['subscription_total_count_yearly'] ?? $recurringDefaults['total_count_yearly'] ?? 10) }}" class="block w-full rounded-2xl border-slate-300">
                                    <x-input-error :messages="$errors->settingsForm->get('subscription_total_count_yearly')" class="mt-2" />
                                </div>
                            </div>
                            <p class="-mt-2 text-xs text-slate-500">Razorpay requires a fixed number of charges per subscription. It ends after this many cycles unless renewed.</p>

                            <div>
                                <label class="mb-2 block text-sm font-semibold text-slate-700">Renewal Reminder (days before charge)</label>
                                <input type="number" name="subscription_renewal_reminder_days" min="0" max="30" value="{{ old('subscription_renewal_reminder_days', $settings['subscription_renewal_reminder_days'] ?? $recurringDefaults['renewal_reminder_days'] ?? 3) }}" class="block w-full rounded-2xl border-slate-300">
                                <p class="mt-2 text-xs text-slate-500">Set 0 to disable reminder emails.</p>
                                <x-input-error :messages="$errors->settingsForm->get('subscription_renewal_reminder_days')" class="mt-2" />
                            </div>

                            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4">
                                <input type="hidden" name="subscription_trial_requires_mandate" value="0">
                                <input type="checkbox" name="subscription_trial_requires_mandate" value="1" class="mt-0.5 rounded border-slate-300" @checked($trialRequiresMandate)>
                                <span>
                                    <span class="block text-sm font-semibold text-slate-700">Require auto-pay setup for free trials</span>
                                    <span class="mt-1 block text-xs text-slate-500">The company authorises auto-pay when the trial starts and is charged automatically when it ends.</span>
                                </span>
                            </label>
                            <x-input-error :messages="$errors->settingsForm->get('subscription_trial_requires_mandate')" class="mt-2" />
                        </div>
                    </section>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-800">Save Settings</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
