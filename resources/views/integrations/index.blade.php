<x-app-layout>
    @php
        $authUser = auth()->user();
        $isAdmin  = $authUser && ($authUser->isCompanyAdmin() || $authUser->isSuperAdmin());

        $waConnected = $whatsappAccount
            && $whatsappAccount->status === \App\Modules\Communication\Enums\ConnectionStatusEnum::CONNECTED;
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-800">Integrations</h2>
                <p class="mt-1 text-sm text-slate-500">Connect external services and manage how you receive notifications.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-2 sm:py-8" x-data="{ tab: '{{ $isAdmin ? 'integrations' : 'notifications' }}' }">
        <div class="mx-auto w-full max-w-7xl space-y-6 px-2 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Tab switcher --}}
            <div class="inline-flex rounded-xl border border-slate-200 bg-white p-1 shadow-sm">
                @if($isAdmin)
                    <button type="button" @click="tab = 'integrations'"
                        :class="tab === 'integrations' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                        class="rounded-lg px-4 py-2 text-sm font-semibold transition">
                        Integration Settings
                    </button>
                @endif
                <button type="button" @click="tab = 'notifications'"
                    :class="tab === 'notifications' ? 'bg-indigo-600 text-white' : 'text-slate-600 hover:bg-slate-50'"
                    class="rounded-lg px-4 py-2 text-sm font-semibold transition">
                    Notification Settings
                </button>
            </div>

            {{-- ============================ INTEGRATION SETTINGS (ADMIN) ============================ --}}
            @if($isAdmin)
            <div x-show="tab === 'integrations'" x-cloak class="space-y-6">

                {{-- WhatsApp Integration --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 text-green-600">
                                <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">WhatsApp</h3>
                                <p class="text-xs text-slate-500">Meta WhatsApp Cloud API for messaging &amp; notifications.</p>
                            </div>
                        </div>
                        @if($waConnected)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> {{ $whatsappAccount ? $whatsappAccount->status->label() : 'Inactive' }}
                            </span>
                        @endif
                    </div>
                    <div class="flex items-center justify-between px-6 py-4">
                        <p class="text-sm text-slate-600">
                            @if($whatsappAccount)
                                Connected number: <strong>{{ $whatsappAccount->phone_number }}</strong>
                            @else
                                Configure Meta Cloud API credentials and webhook to enable WhatsApp messaging.
                            @endif
                        </p>
                        <a href="{{ route('integrations.whatsapp.settings') }}"
                           class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                            {{ $whatsappAccount ? 'Manage' : 'Configure' }}
                        </a>
                    </div>
                </div>

                {{-- Google Drive Integration --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
                     x-data="{ manual: {{ ($googleDrive && ! $googleDrive->isAuto()) ? 'true' : 'false' }} }">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Google Drive</h3>
                                <p class="text-xs text-slate-500">Store and back up files to your company Drive.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $googleDrive ? $googleDrive->statusBadgeClasses() : 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $googleDrive ? $googleDrive->statusDotClass() : 'bg-slate-400' }}"></span>
                            {{ $googleDrive ? $googleDrive->statusLabel() : 'Inactive' }}
                        </span>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        @if($googleDrive && $googleDrive->isAuto())
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 px-4 py-3 text-sm text-emerald-800">
                                Connected via Google{{ ($acc = $googleDrive->credentials['account_email'] ?? null) ? ' as '.$acc : '' }}.
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            @if($googleAutoAvailable)
                                <a href="{{ route('integrations.oauth.google.redirect', 'drive') }}"
                                   class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24"><path fill="currentColor" d="M12 11v2.6h3.6c-.16 1-.98 2.5-3.6 2.5a3.9 3.9 0 010-7.8c1.2 0 2 .52 2.46.96l1.68-1.62A6.3 6.3 0 0012 6a6 6 0 100 12c3.46 0 5.76-2.43 5.76-5.86 0-.4-.04-.7-.1-1.14H12z"/></svg>
                                    {{ $driveConnection ? 'Reconnect' : 'Auto Connect' }}
                                </a>
                            @endif
                            @if($driveConnection)
                                <form method="POST" action="{{ route('integrations.drive.disconnect') }}"
                                      onsubmit="return confirm('Disconnect Google Drive? Existing files stay accessible at their original location; new uploads use the next available storage.');">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center rounded-xl bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 transition">
                                        Disconnect
                                    </button>
                                </form>
                            @endif
                            <button type="button" @click="manual = !manual" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                Add Manually
                            </button>
                            @if($googleDrive)
                                @unless($googleDrive->isAuto())
                                    <button type="button" onclick="testIntegration('google-drive', this)"
                                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                        Test Connection
                                    </button>
                                @endunless
                                <form method="POST" action="{{ route('integrations.toggle', 'google_drive') }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-semibold transition
                                        {{ $googleDrive->is_enabled ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                        {{ $googleDrive->is_enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            @endif
                        </div>

                        @unless($googleAutoAvailable)
                            <p class="text-xs text-amber-600">Auto Connect is unavailable until the platform administrator configures Google OAuth.</p>
                        @endunless

                        <form method="POST" action="{{ route('integrations.google-drive.save') }}" x-show="manual" x-cloak class="grid grid-cols-1 gap-4 md:grid-cols-2 border-t border-slate-100 pt-4">
                            @csrf
                            <div>
                                <x-input-label for="gd_client_id" value="Client ID *" />
                                <x-text-input id="gd_client_id" name="client_id" class="mt-1 block w-full" :value="old('client_id', $googleDrive->credentials['client_id'] ?? '')" required />
                            </div>
                            <div>
                                <x-input-label for="gd_client_secret" value="Client Secret *" />
                                <x-text-input id="gd_client_secret" name="client_secret" type="password" class="mt-1 block w-full" :value="old('client_secret', $googleDrive->credentials['client_secret'] ?? '')" required />
                            </div>
                            <div>
                                <x-input-label for="gd_refresh_token" value="Refresh Token" />
                                <x-text-input id="gd_refresh_token" name="refresh_token" class="mt-1 block w-full" :value="old('refresh_token', $googleDrive->credentials['refresh_token'] ?? '')" />
                            </div>
                            <div>
                                <x-input-label for="gd_folder_id" value="Target Folder ID" />
                                <x-text-input id="gd_folder_id" name="folder_id" class="mt-1 block w-full" :value="old('folder_id', $googleDrive->credentials['folder_id'] ?? '')" />
                            </div>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                                    Save &amp; Activate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Email Integration (Gmail OAuth or manual SMTP) --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden"
                     x-data="{ manual: {{ ($emailSmtp && ! $emailSmtp->isAuto()) ? 'true' : 'false' }} }">
                    <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-50 text-blue-600">
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-base font-bold text-slate-900">Email</h3>
                                <p class="text-xs text-slate-500">Connect Gmail in one click, or add any SMTP provider manually.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $emailSmtp ? $emailSmtp->statusBadgeClasses() : 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $emailSmtp ? $emailSmtp->statusDotClass() : 'bg-slate-400' }}"></span>
                            {{ $emailSmtp ? $emailSmtp->statusLabel() : 'Inactive' }}
                        </span>
                    </div>
                    <div class="px-6 py-4 space-y-4">
                        @if($emailSmtp && $emailSmtp->isAuto())
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 px-4 py-3 text-sm text-emerald-800">
                                Connected via Gmail{{ ($acc = $emailSmtp->credentials['account_email'] ?? null) ? ' as '.$acc : '' }}.
                            </div>
                        @endif

                        <div class="flex flex-wrap items-center gap-2">
                            @if($googleAutoAvailable)
                                <a href="{{ route('integrations.oauth.google.redirect', 'gmail') }}"
                                   class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24"><path fill="currentColor" d="M12 11v2.6h3.6c-.16 1-.98 2.5-3.6 2.5a3.9 3.9 0 010-7.8c1.2 0 2 .52 2.46.96l1.68-1.62A6.3 6.3 0 0012 6a6 6 0 100 12c3.46 0 5.76-2.43 5.76-5.86 0-.4-.04-.7-.1-1.14H12z"/></svg>
                                    {{ ($emailSmtp && $emailSmtp->isAuto()) ? 'Reconnect Gmail' : 'Auto Connect (Gmail)' }}
                                </a>
                            @endif
                            <button type="button" @click="manual = !manual" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 transition">
                                Add Manually (SMTP)
                            </button>
                            @if($emailSmtp)
                                @unless($emailSmtp->isAuto())
                                    <button type="button" onclick="testIntegration('email-smtp', this)"
                                            class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition">
                                        Test Connection
                                    </button>
                                @endunless
                                <form method="POST" action="{{ route('integrations.toggle', 'email_smtp') }}">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center rounded-xl px-3 py-2 text-xs font-semibold transition
                                        {{ $emailSmtp->is_enabled ? 'bg-rose-50 text-rose-700 hover:bg-rose-100' : 'bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                        {{ $emailSmtp->is_enabled ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            @endif
                        </div>

                        <form method="POST" action="{{ route('integrations.email-smtp.save') }}" x-show="manual" x-cloak class="grid grid-cols-1 gap-4 md:grid-cols-2 border-t border-slate-100 pt-4">
                            @csrf
                            <div>
                                <x-input-label for="em_host" value="SMTP Host *" />
                                <x-text-input id="em_host" name="host" class="mt-1 block w-full" placeholder="smtp.gmail.com" :value="old('host', $emailSmtp->credentials['host'] ?? '')" required />
                            </div>
                            <div>
                                <x-input-label for="em_port" value="Port *" />
                                <x-text-input id="em_port" name="port" type="number" class="mt-1 block w-full" placeholder="587" :value="old('port', $emailSmtp->credentials['port'] ?? '587')" required />
                            </div>
                            <div>
                                <x-input-label for="em_username" value="Username *" />
                                <x-text-input id="em_username" name="username" class="mt-1 block w-full" :value="old('username', $emailSmtp->credentials['username'] ?? '')" required />
                            </div>
                            <div>
                                <x-input-label for="em_password" value="Password *" />
                                <x-text-input id="em_password" name="password" type="password" class="mt-1 block w-full" :value="old('password', $emailSmtp->credentials['password'] ?? '')" required />
                            </div>
                            <div>
                                <x-input-label for="em_encryption" value="Encryption *" />
                                <select id="em_encryption" name="encryption" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                    @php $enc = old('encryption', $emailSmtp->credentials['encryption'] ?? 'tls'); @endphp
                                    <option value="tls"  @selected($enc === 'tls')>TLS</option>
                                    <option value="ssl"  @selected($enc === 'ssl')>SSL</option>
                                    <option value="none" @selected($enc === 'none')>None</option>
                                </select>
                            </div>
                            <div>
                                <x-input-label for="em_from_name" value="From Name *" />
                                <x-text-input id="em_from_name" name="from_name" class="mt-1 block w-full" :value="old('from_name', $emailSmtp->credentials['from_name'] ?? '')" required />
                            </div>
                            <div class="md:col-span-2">
                                <x-input-label for="em_from_email" value="From Email *" />
                                <x-text-input id="em_from_email" name="from_email" type="email" class="mt-1 block w-full" :value="old('from_email', $emailSmtp->credentials['from_email'] ?? '')" required />
                            </div>
                            <div class="md:col-span-2 flex justify-end">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                                    Save &amp; Activate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Google Drive Connection Log --}}
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                        <h3 class="text-base font-bold text-slate-900">Google Drive Connection Log</h3>
                        <p class="text-xs text-slate-500">History of Drive account changes — useful for tracing where files were stored over time.</p>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-100 text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-2.5 text-left font-semibold">Action</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Account</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Previous</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">By</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">When</th>
                                    <th class="px-4 py-2.5 text-left font-semibold">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse($driveLogs as $log)
                                    <tr>
                                        <td class="px-4 py-2.5 font-medium capitalize text-slate-700">{{ $log->action }}</td>
                                        <td class="px-4 py-2.5 text-slate-600">{{ $log->current_account_email ?? $log->account_email ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-slate-500">{{ $log->previous_account_email ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-slate-500">{{ $log->performedBy?->name ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-slate-500">{{ $log->created_at?->format('d M Y, g:i A') }}</td>
                                        <td class="px-4 py-2.5">
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                                'bg-emerald-50 text-emerald-700' => $log->status === \App\Models\DriveConnection::STATUS_ACTIVE,
                                                'bg-slate-100 text-slate-600' => $log->status !== \App\Models\DriveConnection::STATUS_ACTIVE,
                                            ])>{{ ucfirst($log->status) }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-slate-400">No Drive connection history yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
            @endif

            {{-- ============================ NOTIFICATION SETTINGS (COMPANY-WIDE) ============================ --}}
            <div x-show="tab === 'notifications'" x-cloak>
                @php
                    $channelMeta = [
                        'whatsapp' => ['WhatsApp', 'text-green-600 bg-green-50'],
                        'email'    => ['Email', 'text-blue-600 bg-blue-50'],
                        'push'     => ['Push', 'text-amber-600 bg-amber-50'],
                    ];
                @endphp
                <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4">
                        <h3 class="text-base font-bold text-slate-900">Notification Settings</h3>
                        <p class="text-xs text-slate-500">
                            Enable each module and pick the channels it sends through. These settings are
                            <strong>company-wide</strong> and apply to all users.
                        </p>
                    </div>

                    @unless($isAdmin)
                        <div class="mx-6 mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            These settings are managed by your company admin and apply to everyone. You can view them but not change them.
                        </div>
                    @endunless

                    <form method="POST" action="{{ route('integrations.notifications.update') }}" class="px-6 py-5">
                        @csrf
                        @method('PUT')

                        <div class="overflow-x-auto rounded-xl border border-slate-200">
                            <table class="min-w-full divide-y divide-slate-100 text-sm">
                                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">Module</th>
                                        <th class="px-4 py-3 text-center font-semibold">Enabled</th>
                                        @foreach($channelMeta as $key => [$cLabel, $cClass])
                                            <th class="px-4 py-3 text-center font-semibold">
                                                {{ $cLabel }}
                                                @unless($channelAvailability[$key])
                                                    <span class="block text-[10px] font-normal normal-case text-amber-600">not active</span>
                                                @endunless
                                            </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @foreach($notificationMatrix as $module => $row)
                                        @php [$label, $desc] = \App\Models\CompanyNotificationSetting::MODULES[$module]; @endphp
                                        <tr x-data="{ on: {{ $row->is_enabled ? 'true' : 'false' }} }">
                                            <td class="px-4 py-3">
                                                <div class="font-semibold text-slate-800">{{ $label }}</div>
                                                <div class="text-xs text-slate-500">{{ $desc }}</div>
                                            </td>
                                            <td class="px-4 py-3 text-center">
                                                <input type="checkbox" x-model="on"
                                                       name="settings[{{ $module }}][is_enabled]" value="1"
                                                       @disabled(! $isAdmin)
                                                       class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                            </td>
                                            @foreach(array_keys($channelMeta) as $channel)
                                                @php $available = $channelAvailability[$channel]; @endphp
                                                <td class="px-4 py-3 text-center">
                                                    <input type="checkbox"
                                                           name="settings[{{ $module }}][{{ $channel }}]" value="1"
                                                           @checked($row->{$channel.'_enabled'})
                                                           x-bind:disabled="!on || {{ (! $isAdmin || ! $available) ? 'true' : 'false' }}"
                                                           x-bind:class="(!on || {{ (! $isAdmin || ! $available) ? 'true' : 'false' }}) ? 'opacity-40' : ''"
                                                           class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                                                </td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <p class="mt-3 text-xs text-slate-500">
                            A channel only delivers when its integration is active company-wide. Greyed channels above aren’t connected yet —
                            set them up under <strong>Integration Settings</strong>.
                        </p>

                        @if($isAdmin)
                            <div class="mt-5 flex justify-end border-t border-slate-100 pt-5">
                                <button type="submit" class="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                                    Save Notification Settings
                                </button>
                            </div>
                        @endif
                    </form>
                </div>
            </div>

        </div>
    </div>

    <script>
        function testIntegration(type, btn) {
            const original = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Testing...';

            fetch(`/integrations/${type}/test`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
            })
            .then(r => r.json().then(data => ({ ok: r.ok, data })))
            .then(({ ok, data }) => {
                if (window.Swal) {
                    Swal.fire({
                        icon: ok ? 'success' : 'error',
                        title: ok ? 'Success' : 'Failed',
                        text: data.message,
                        timer: ok ? 2500 : undefined,
                    });
                } else {
                    alert(data.message);
                }
                if (ok) setTimeout(() => window.location.reload(), 1200);
            })
            .catch(() => alert('Connection test failed.'))
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = original;
            });
        }
    </script>
</x-app-layout>
