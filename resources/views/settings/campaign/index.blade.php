<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-xl font-semibold text-slate-950 leading-tight">Campaign Batch Settings</h2>
            <p class="mt-1 text-sm text-slate-500">Configure email batch sending behaviour, cron intervals, and retry policies.</p>
        </div>
    </x-slot>

    <div class="w-full space-y-5 py-4 sm:px-4 lg:px-6">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <form method="POST" action="{{ route('settings.campaign.batch.update') }}" class="space-y-6">
                @csrf

                <div class="grid gap-6 md:grid-cols-2">
                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Emails Per Batch</label>
                        <input type="number" name="email_batch_size" value="{{ old('email_batch_size', $settings['email_batch_size']) }}" min="1" max="10000" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                        <p class="mt-1 text-xs text-slate-500">Number of emails processed in each batch execution (default: 200).</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Cron Interval</label>
                        <select name="cron_interval" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                            <option value="everyMinute" {{ $settings['cron_interval'] === 'everyMinute' ? 'selected' : '' }}>Every Minute</option>
                            <option value="everyFiveMinutes" {{ $settings['cron_interval'] === 'everyFiveMinutes' ? 'selected' : '' }}>Every 5 Minutes</option>
                            <option value="everyTenMinutes" {{ $settings['cron_interval'] === 'everyTenMinutes' ? 'selected' : '' }}>Every 10 Minutes</option>
                            <option value="everyFifteenMinutes" {{ $settings['cron_interval'] === 'everyFifteenMinutes' ? 'selected' : '' }}>Every 15 Minutes</option>
                            <option value="everyThirtyMinutes" {{ $settings['cron_interval'] === 'everyThirtyMinutes' ? 'selected' : '' }}>Every 30 Minutes</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">How often the cron job runs to process pending batches.</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Retry Attempts</label>
                        <input type="number" name="retry_count" value="{{ old('retry_count', $settings['retry_count']) }}" min="0" max="20" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                        <p class="mt-1 text-xs text-slate-500">Maximum retry attempts for failed email sends (default: 3).</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Delay Between Batches</label>
                        <input type="number" name="batch_delay_seconds" value="{{ old('batch_delay_seconds', $settings['batch_delay_seconds']) }}" min="1" max="3600" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                        <p class="mt-1 text-xs text-slate-500">Delay in seconds between batch dispatches (default: 30).</p>
                    </div>

                    <div>
                        <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Active Email Provider</label>
                        <select name="email_provider" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                            <option value="smtp" {{ $settings['email_provider'] === 'smtp' ? 'selected' : '' }}>SMTP</option>
                            <option value="sendgrid" {{ $settings['email_provider'] === 'sendgrid' ? 'selected' : '' }}>SendGrid</option>
                            <option value="ses" {{ $settings['email_provider'] === 'ses' ? 'selected' : '' }}>Amazon SES</option>
                            <option value="mailgun" {{ $settings['email_provider'] === 'mailgun' ? 'selected' : '' }}>Mailgun</option>
                            <option value="postmark" {{ $settings['email_provider'] === 'postmark' ? 'selected' : '' }}>Postmark</option>
                        </select>
                        <p class="mt-1 text-xs text-slate-500">Primary email provider for sending campaign emails.</p>
                    </div>
                </div>

                <div class="flex items-center gap-3 pt-4">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Save Settings</button>
                    <a href="{{ route('marketing.campaigns.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Cancel</a>
                </div>
            </form>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-base font-semibold text-slate-950">Current Configuration Summary</h3>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Emails Per Batch</dt>
                    <dd class="mt-2 text-lg font-semibold text-slate-900">{{ $settings['email_batch_size'] }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Cron Interval</dt>
                    <dd class="mt-2 text-lg font-semibold text-slate-900">{{ $settings['cron_interval'] }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Retry Attempts</dt>
                    <dd class="mt-2 text-lg font-semibold text-slate-900">{{ $settings['retry_count'] }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Batch Delay</dt>
                    <dd class="mt-2 text-lg font-semibold text-slate-900">{{ $settings['batch_delay_seconds'] }}s</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4">
                    <dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Email Provider</dt>
                    <dd class="mt-2 text-lg font-semibold text-slate-900">{{ ucfirst($settings['email_provider']) }}</dd>
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>
