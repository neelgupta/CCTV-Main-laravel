<x-app-layout>
    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">Platform Integrations</h2>
            <p class="mt-1 text-sm text-slate-500">Configure the OAuth apps that power one-click "Auto Connect" for all companies.</p>
        </div>
    </x-slot>

    <div class="mx-auto w-full max-w-4xl space-y-6 py-6 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
        @endif

        {{-- Google OAuth App --}}
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white ring-1 ring-slate-200">
                        <svg class="h-6 w-6" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 01-2.2 3.32v2.77h3.57c2.08-1.92 3.27-4.74 3.27-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84A11 11 0 0012 23z"/><path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 010-4.2V7.06H2.18a11 11 0 000 9.88l3.66-2.84z"/><path fill="#EA4335" d="M12 4.75c1.62 0 3.06.56 4.21 1.64l3.15-3.15A11 11 0 0012 1 11 11 0 002.18 7.06l3.66 2.84C6.71 7.3 9.14 4.75 12 4.75z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Google OAuth App</h3>
                        <p class="text-xs text-slate-500">Powers Auto Connect for <strong>Google Drive</strong> and <strong>Gmail (Email)</strong>.</p>
                    </div>
                </div>
                @if($googleSetting && $googleSetting->isConfigured())
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Not Configured
                    </span>
                @endif
            </div>

            <div class="p-6 space-y-6">
                {{-- Redirect URI helper --}}
                <div class="rounded-2xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-2">
                    <h4 class="font-bold text-slate-900 text-sm">Authorized redirect URI</h4>
                    <p class="text-xs text-slate-500">Add this exact URI to your Google Cloud Console → Credentials → OAuth 2.0 Client → Authorized redirect URIs.</p>
                    <div class="flex gap-2 items-center">
                        <input id="redirect_uri" type="text" readonly value="{{ $redirectUri }}"
                               class="block w-full rounded-xl border-slate-200 bg-white font-mono text-xs p-2.5 text-slate-600 select-all">
                        <button type="button" onclick="copyRedirect()" id="copyBtn"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition whitespace-nowrap">
                            Copy
                        </button>
                    </div>
                    <p class="text-xs text-slate-500">
                        Enable the <strong>Google Drive API</strong> and <strong>Gmail API</strong> in your Google Cloud project, and add scopes
                        <code class="bg-white px-1 rounded border border-slate-200">drive.file</code>,
                        <code class="bg-white px-1 rounded border border-slate-200">gmail.send</code>.
                    </p>
                </div>

                <form method="POST" action="{{ route('super-admin.integrations.google.update') }}" class="space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="client_id" value="Client ID *" />
                        <x-text-input id="client_id" name="client_id" class="mt-1 block w-full rounded-xl"
                                      :value="old('client_id', $googleSetting?->client_id)"
                                      placeholder="xxxxxxxx.apps.googleusercontent.com" required />
                        <x-input-error :messages="$errors->get('client_id')" class="mt-1" />
                    </div>

                    <div>
                        <x-input-label for="client_secret" value="Client Secret {{ $googleSetting?->client_secret ? '(leave blank to keep current)' : '*' }}" />
                        <x-text-input id="client_secret" name="client_secret" type="password" class="mt-1 block w-full rounded-xl"
                                      placeholder="{{ $googleSetting?->client_secret ? '••••••••••••••••' : 'Paste OAuth client secret' }}" />
                        <x-input-error :messages="$errors->get('client_secret')" class="mt-1" />
                    </div>

                    <label class="flex items-center gap-3">
                        <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $googleSetting?->is_enabled))
                               class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        <span class="text-sm font-medium text-slate-700">Enable Auto Connect for all companies</span>
                    </label>

                    <div class="flex justify-end border-t border-slate-100 pt-5">
                        <button type="submit" class="inline-flex items-center rounded-xl bg-indigo-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                            Save Configuration
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Super Admin Drive (fallback storage) --}}
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
            <div class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-5">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-bold text-slate-900">Super Admin Google Drive</h3>
                        <p class="text-xs text-slate-500">Fallback storage used when a company has no Drive connected. Files land in <code class="bg-slate-100 px-1 rounded">we4work-data</code>.</p>
                    </div>
                </div>
                @if($driveConnection)
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Active
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span> Not Connected
                    </span>
                @endif
            </div>

            <div class="p-6 space-y-5">
                @if($driveConnection)
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 px-4 py-3 text-sm text-emerald-800">
                        Connected as <strong>{{ $driveConnection->account_email ?? 'Google account' }}</strong>
                        @if($driveConnection->connected_at) • since {{ $driveConnection->connected_at->format('d M Y, g:i A') }} @endif
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-2">
                    @if($googleSetting && $googleSetting->isConfigured())
                        <a href="{{ route('integrations.oauth.google.redirect', 'drive') }}"
                           class="inline-flex items-center gap-2 rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 transition">
                            {{ $driveConnection ? 'Reconnect Drive' : 'Connect Google Drive' }}
                        </a>
                        @if($driveConnection)
                            <form method="POST" action="{{ route('integrations.drive.disconnect') }}"
                                  onsubmit="return confirm('Disconnect this Drive? Existing files stay accessible at their original location.');">
                                @csrf
                                <button type="submit" class="inline-flex items-center rounded-xl bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 transition">
                                    Disconnect
                                </button>
                            </form>
                        @endif
                    @else
                        <p class="text-xs text-amber-600">Configure and enable the Google OAuth app above before connecting a Drive.</p>
                    @endif
                </div>

                {{-- Audit log --}}
                <div>
                    <h4 class="text-sm font-bold text-slate-800 mb-2">Drive Connection Log</h4>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
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
        </div>
    </div>

    <script>
        function copyRedirect() {
            const el = document.getElementById('redirect_uri');
            el.select(); el.setSelectionRange(0, 99999);
            navigator.clipboard.writeText(el.value).then(() => {
                const btn = document.getElementById('copyBtn');
                const t = btn.textContent; btn.textContent = 'Copied!';
                setTimeout(() => btn.textContent = t, 2000);
            });
        }
    </script>
</x-app-layout>
