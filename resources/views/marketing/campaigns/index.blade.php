<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950 leading-tight">Campaign Management</h2>
                    <p class="mt-1 text-sm text-slate-500">Normal and festival campaigns with queue scheduling, tracking, and analytics.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('marketing.campaigns.create.normal') }}" class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-emerald-700">Create Normal Campaign</a>
                    <a href="{{ route('marketing.campaigns.create.festival') }}" class="inline-flex items-center rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-700">Create Festival Campaign</a>
                    <a href="{{ route('settings.campaign.batch.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Batch Settings</a>
                    <a href="{{ route('marketing.email-template') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Email Templates</a>
                    {{-- <a href="{{ route('marketing.whatsapp') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">WhatsApp</a> --}}
                </div>
            </div>
        </div>
    </x-slot>

    <div class="w-full space-y-5 py-4 sm:px-4 lg:px-6">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        @if(session('error'))
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-2.5 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Total Campaigns</div><div class="mt-3 text-3xl font-semibold text-slate-950">{{ $analytics['cards']['total_campaigns'] }}</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Active / Running</div><div class="mt-3 text-3xl font-semibold text-slate-950">{{ $analytics['cards']['active_campaigns'] }}</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Scheduled</div><div class="mt-3 text-3xl font-semibold text-slate-950">{{ $analytics['cards']['scheduled_campaigns'] }}</div></div>
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Completed</div><div class="mt-3 text-3xl font-semibold text-slate-950">{{ $analytics['cards']['completed_campaigns'] }}</div></div>
        </section>

        <section class="grid gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-base font-semibold text-slate-950">Analytics Dashboard</h3>

                <div class="mt-5 grid gap-4 md:grid-cols-3">
                    <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Open Rate</div><div class="mt-2 text-2xl font-semibold text-slate-950">{{ $analytics['email']['open_rate'] }}%</div></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">CTR</div><div class="mt-2 text-2xl font-semibold text-slate-950">{{ $analytics['email']['ctr'] }}%</div></div>
                    <div class="rounded-2xl bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Bounce Rate</div><div class="mt-2 text-2xl font-semibold text-slate-950">{{ $analytics['email']['bounce_rate'] }}%</div></div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <h4 class="text-sm font-semibold text-slate-700">Email Statistics</h4>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Sent</dt><dd class="font-semibold text-slate-900">{{ $analytics['email']['total_sent'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Delivered</dt><dd class="font-semibold text-emerald-600">{{ $analytics['email']['delivered'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Opened</dt><dd class="font-semibold text-blue-600">{{ $analytics['email']['opened'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Clicked</dt><dd class="font-semibold text-purple-600">{{ $analytics['email']['clicked'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Bounced</dt><dd class="font-semibold text-red-600">{{ $analytics['email']['bounced'] }}</dd></div>
                        </dl>
                    </div>
                    <div class="rounded-2xl border border-slate-200 p-4">
                        <h4 class="text-sm font-semibold text-slate-700">WhatsApp Statistics</h4>
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-slate-500">Sent</dt><dd class="font-semibold text-slate-900">{{ $analytics['whatsapp']['sent'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Delivered</dt><dd class="font-semibold text-emerald-600">{{ $analytics['whatsapp']['delivered'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Read</dt><dd class="font-semibold text-blue-600">{{ $analytics['whatsapp']['read'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-slate-500">Failed</dt><dd class="font-semibold text-red-600">{{ $analytics['whatsapp']['failed'] }}</dd></div>
                        </dl>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-2xl border border-slate-200">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Campaign</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Type</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Send</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Recipients</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Schedule</th>
                                <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-slate-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($campaigns as $campaign)
                                <tr class="hover:bg-slate-50/80">
                                    <td class="px-4 py-3"><div class="font-semibold text-slate-900">{{ $campaign->name }}</div><div class="mt-1 text-xs text-slate-500">{{ $campaign->subject ?: 'No subject' }}</div></td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst($campaign->type) }}</td>
                                    <td class="px-4 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $campaign->send_type === 'immediate' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">{{ $campaign->send_type === 'immediate' ? 'Immediate' : 'Scheduled' }}</span></td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst($campaign->status) }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-600">{{ $campaign->total_recipients }}</td>
                                    <td class="px-4 py-3 text-sm text-slate-500">{{ $campaign->scheduled_at?->format('d M Y h:i A') ?: 'N/A' }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-1">
                                            <a href="{{ route('marketing.campaigns.show', $campaign->id) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" title="View">
                                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                                            </a>
                                            @if(! in_array($campaign->status, [\App\Models\Campaign::STATUS_COMPLETED, \App\Models\Campaign::STATUS_RUNNING]))
                                                <form method="POST" action="{{ route('marketing.campaigns.send', $campaign->id) }}" class="inline">
                                                    @csrf
                                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-emerald-600 transition hover:bg-emerald-50" title="Send Now">
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('marketing.campaigns.duplicate', $campaign->id) }}" class="inline">
                                                @csrf
                                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" title="Duplicate">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('marketing.campaigns.destroy', $campaign->id) }}" class="inline" data-swal-confirm="Delete this campaign?" data-swal-title="Confirm Delete" data-swal-confirm-button="Yes, Delete" data-swal-cancel-button="Cancel">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100" title="Delete">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-4 py-8 text-center text-sm text-slate-500">No campaigns created yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($campaigns->hasPages())<div class="mt-4">{{ $campaigns->links() }}</div>@endif
            </div>
        </section>
    </div>
</x-app-layout>
