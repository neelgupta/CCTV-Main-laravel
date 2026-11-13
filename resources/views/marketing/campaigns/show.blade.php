<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950 leading-tight">{{ $campaign->name }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ ucfirst($campaign->type) }} campaign &middot;
                        <span class="{{ $campaign->send_type === 'immediate' ? 'text-amber-600' : 'text-blue-600' }}">{{ $campaign->send_type === 'immediate' ? 'Immediate' : 'Scheduled' }}</span>
                        &middot; {{ ucfirst($campaign->status) }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    @if(! in_array($campaign->status, [\App\Models\Campaign::STATUS_COMPLETED, \App\Models\Campaign::STATUS_RUNNING]))
                        <form method="POST" action="{{ route('marketing.campaigns.send', $campaign->id) }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-emerald-200 bg-emerald-50 text-emerald-600 transition hover:bg-emerald-100" title="Send Now">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/></svg>
                            </button>
                        </form>
                        @if($campaign->status === \App\Models\Campaign::STATUS_DRAFT)
                            <form method="POST" action="{{ route('marketing.campaigns.status', $campaign->id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="scheduled">
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-blue-200 bg-blue-50 text-blue-600 transition hover:bg-blue-100" title="Publish">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12l5 5L20 7"/></svg>
                                </button>
                            </form>
                        @elseif($campaign->status === \App\Models\Campaign::STATUS_SCHEDULED)
                            <form method="POST" action="{{ route('marketing.campaigns.status', $campaign->id) }}" class="inline">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="draft">
                                <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-amber-200 bg-amber-50 text-amber-600 transition hover:bg-amber-100" title="Unpublish">
                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="4" height="16"/><rect x="14" y="4" width="4" height="16"/></svg>
                                </button>
                            </form>
                        @endif
                        <button type="button" onclick="document.getElementById('editCampaignModal').classList.remove('hidden')" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" title="Edit">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4 12.5-12.5z"/></svg>
                        </button>
                    @endif
                    <form method="POST" action="{{ route('marketing.campaigns.duplicate', $campaign->id) }}" class="inline" data-swal-confirm="Duplicate this campaign?" data-swal-title="Confirm Duplicate" data-swal-confirm-button="Yes, Duplicate" data-swal-cancel-button="Cancel">
                        @csrf
                        <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50" title="Duplicate">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        </button>
                    </form>
                    <form method="POST" action="{{ route('marketing.campaigns.destroy', $campaign->id) }}" class="inline" data-swal-confirm="Are you sure you want to delete this campaign? This action cannot be undone." data-swal-title="Confirm Delete" data-swal-confirm-button="Yes, Delete" data-swal-cancel-button="Cancel">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100" title="Delete">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                        </button>
                    </form>
                    <a href="{{ route('marketing.campaigns.index') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Back To Campaigns</a>
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

        <section class="grid gap-5 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <div class="space-y-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-950">Campaign Summary</h3>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Status</dt><dd class="mt-2 text-sm text-slate-900">{{ ucfirst($campaign->status) }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Send Type</dt><dd class="mt-2 text-sm"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold {{ $campaign->send_type === 'immediate' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700' }}">{{ $campaign->send_type === 'immediate' ? 'Immediate' : 'Scheduled' }}</span></dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Schedule</dt><dd class="mt-2 text-sm text-slate-900">{{ $campaign->scheduled_at?->format('d M Y h:i A') ?: 'Not scheduled' }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Recipients</dt><dd class="mt-2 text-sm text-slate-900">{{ $campaign->total_recipients }}</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Batch</dt><dd class="mt-2 text-sm text-slate-900">{{ $campaign->batch_size }} / {{ $campaign->batch_delay_seconds }}s</dd></div>
                        <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Retry Count</dt><dd class="mt-2 text-sm text-slate-900">{{ $campaign->retry_count }}</dd></div>
                    </dl>

                    @if($campaign->festivalCampaign)
                        <div class="mt-4 rounded-xl bg-cyan-50 p-4">
                            <h4 class="text-sm font-semibold text-cyan-800">Festival Details</h4>
                            <dl class="mt-2 space-y-1 text-sm text-cyan-700">
                                <dt>Festival: <span class="font-semibold">{{ $campaign->festivalCampaign->festival_name }}</span></dt>
                                <dt>Date: <span class="font-semibold">{{ $campaign->festivalCampaign->festival_date?->format('d M Y') }}</span></dt>
                                @if($campaign->festivalCampaign->description)
                                    <dt class="mt-1">{{ $campaign->festivalCampaign->description }}</dt>
                                @endif
                            </dl>
                        </div>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    @php
                        $fallbackBannerUrl = $campaign->banner_image
                            ? \Illuminate\Support\Facades\Storage::disk(env('AWS_ACCESS_KEY_ID') ? 's3' : 'public')->url(ltrim(str_replace('\\', '/', (string) $campaign->banner_image), '/'))
                            : null;
                        $bannerUrl = $campaign->generatedBanner?->display_url ?? $fallbackBannerUrl;
                    @endphp

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-slate-950">Banner Preview</h3>
                            <p class="mt-1 text-sm text-slate-500">Upload your own creative or regenerate a festival visual.</p>
                        </div>
                        @if($campaign->generatedBanner?->source)
                            <span class="inline-flex items-center rounded-full bg-cyan-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-cyan-700">
                                {{ $campaign->generatedBanner->source }}
                            </span>
                        @endif
                    </div>

                    @if($bannerUrl)
                        <div class="mt-4 overflow-hidden rounded-[28px] border border-slate-200 bg-slate-100 shadow-inner">
                            <div class="aspect-[1200/628] w-full bg-[radial-gradient(circle_at_top,_rgba(255,255,255,0.92),_rgba(226,232,240,0.88))]">
                                <img src="{{ $bannerUrl }}" alt="Campaign banner" class="h-full w-full object-cover">
                            </div>
                        </div>
                    @else
                        <div class="mt-4 rounded-[28px] border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-white text-slate-400 shadow-sm">
                                <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="M21 15l-4.5-4.5L7 19"/></svg>
                            </div>
                            <p class="mt-4 text-sm font-medium text-slate-700">No banner generated yet.</p>
                            <p class="mt-1 text-sm text-slate-500">Create an AI banner or upload a custom festival design.</p>
                        </div>
                    @endif

                    <div class="mt-5 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto]">
                        <form method="POST" action="{{ route('marketing.campaigns.banner.upload', $campaign->id) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <label class="block">
                                <span class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Upload Banner</span>
                                <input type="file" name="banner" class="mt-2 block w-full rounded-2xl border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-600 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800">
                            </label>
                            <button type="submit" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Upload / Replace Banner</button>
                        </form>

                        @if($campaign->festivalCampaign)
                            <form method="POST" action="{{ route('marketing.campaigns.banner.generate') }}" class="sm:self-end">
                                @csrf
                                <input type="hidden" name="festival_campaign_id" value="{{ $campaign->festivalCampaign->id }}">
                                <input type="hidden" name="campaign_id" value="{{ $campaign->id }}">
                                <button type="submit" class="inline-flex w-full items-center justify-center rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-700 sm:w-auto">Generate AI Banner</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>

            <div class="space-y-5">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-950">Delivery Summary</h3>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Sent</div><div class="mt-2 text-2xl font-semibold text-slate-900">{{ $campaign->sent_count }}</div></div>
                        <div class="rounded-2xl border border-emerald-100 bg-emerald-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-emerald-600">Delivered</div><div class="mt-2 text-2xl font-semibold text-emerald-700">{{ $campaign->delivered_count }}</div></div>
                        <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-blue-600">Opened</div><div class="mt-2 text-2xl font-semibold text-blue-700">{{ $campaign->opened_count }}</div></div>
                        <div class="rounded-2xl border border-violet-100 bg-violet-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-violet-600">Clicked</div><div class="mt-2 text-2xl font-semibold text-violet-700">{{ $campaign->clicked_count }}</div></div>
                        <div class="rounded-2xl border border-amber-100 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-600">Bounced</div><div class="mt-2 text-2xl font-semibold text-amber-700">{{ $campaign->bounced_count }}</div></div>
                        <div class="rounded-2xl border border-rose-100 bg-rose-50 p-4"><div class="text-xs font-semibold uppercase tracking-[0.18em] text-rose-600">Failed</div><div class="mt-2 text-2xl font-semibold text-rose-700">{{ $campaign->failed_count }}</div></div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h3 class="text-base font-semibold text-slate-950">Recipient Delivery Logs</h3>
                    <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200">
                        <table class="min-w-full divide-y divide-slate-200">
                            <thead class="bg-slate-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Recipient</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Email</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">WhatsApp</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">Overall</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 bg-white">
                                @forelse($campaign->recipients as $recipient)
                                    <tr>
                                        <td class="px-4 py-3 text-sm text-slate-900">{{ $recipient->name ?: 'Unknown' }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst($recipient->email_status) }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst($recipient->whatsapp_status) }}</td>
                                        <td class="px-4 py-3 text-sm text-slate-600">{{ ucfirst($recipient->overall_status) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No recipients added yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <div id="editCampaignModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/40">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-slate-950">Edit Campaign</h3>
                <button type="button" onclick="document.getElementById('editCampaignModal').classList.add('hidden')" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600">&times;</button>
            </div>
            <form method="POST" action="{{ route('marketing.campaigns.update', $campaign->id) }}" class="mt-5 space-y-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Campaign Name</label>
                    <input type="text" name="name" value="{{ $campaign->name }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900" required>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Subject</label>
                    <input type="text" name="subject" value="{{ $campaign->subject }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Send Type</label>
                    <div class="mt-2 grid grid-cols-2 gap-3">
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition has-[:checked]:border-emerald-500 has-[:checked]:bg-emerald-50 has-[:checked]:text-emerald-700">
                            <input type="radio" name="send_type" value="immediate" @checked($campaign->send_type === 'immediate') class="sr-only">
                            Send Now
                        </label>
                        <label class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50 has-[:checked]:text-blue-700">
                            <input type="radio" name="send_type" value="scheduled" @checked($campaign->send_type === 'scheduled') class="sr-only">
                            Schedule
                        </label>
                    </div>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Scheduled At</label>
                    <input type="datetime-local" name="scheduled_at" value="{{ $campaign->scheduled_at?->format('Y-m-d\TH:i') }}" class="mt-2 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900">
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('editCampaignModal').classList.add('hidden')" class="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
