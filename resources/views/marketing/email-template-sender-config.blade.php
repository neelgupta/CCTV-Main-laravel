<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-950 leading-tight">Sender Config</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Manage default sender details and sender groups on a full page.</p>
                </div>
                <a href="{{ route('marketing.email-template') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">Back To Templates</a>
            </div>
        </div>
    </x-slot>

    <div class="w-full py-4 sm:px-4 lg:px-6 space-y-4">
        @if(session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">{{ session('success') }}</div>
        @endif

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-slate-950">Default Sender</h3>
                    <p class="mt-1 text-sm text-slate-500">Configure the default sender used by email templates.</p>
                </div>
                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold {{ $senderConfig->is_connected ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">
                    {{ $senderConfig->is_connected ? 'Connected' : 'Not Connected' }}
                </span>
            </div>

            <form method="POST" action="{{ route('marketing.email-template.sender-config.save') }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Sender Email</label>
                    <input type="email" name="sender_email" value="{{ old('sender_email', $senderConfig->sender_email) }}" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Display Name</label>
                    <input type="text" name="display_name" value="{{ old('display_name', $senderConfig->display_name) }}" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Reply To</label>
                    <input type="email" name="reply_to_email" value="{{ old('reply_to_email', $senderConfig->reply_to_email) }}" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Default CC</label>
                    <input type="text" name="cc_email" value="{{ old('cc_email', $senderConfig->cc_email) }}" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Default BCC</label>
                    <input type="text" name="bcc_email" value="{{ old('bcc_email', $senderConfig->bcc_email) }}" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700">Save Sender Config</button>
                </div>
            </form>
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div>
                <h3 class="text-base font-semibold text-slate-950">Sender Groups</h3>
                <p class="mt-1 text-sm text-slate-500">Create group-specific sender mailboxes with their own CC/BCC values.</p>
            </div>

            <form method="POST" action="{{ route('marketing.email-template.sender-groups.store') }}" class="mt-5 grid gap-4 md:grid-cols-2">
                @csrf
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Group Name</label>
                    <input type="text" name="name" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Sender Name</label>
                    <input type="text" name="sender_name" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Sender Email</label>
                    <input type="email" name="sender_email" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">CC</label>
                    <input type="text" name="cc_email" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div class="md:col-span-2">
                    <label class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">BCC</label>
                    <input type="text" name="bcc_email" class="mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900">
                </div>
                <div class="md:col-span-2 flex justify-end">
                    <button type="submit" class="inline-flex items-center rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-cyan-700">Add Group</button>
                </div>
            </form>

            <div class="mt-6 space-y-4">
                @forelse($senderGroups as $group)
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 shadow-sm">
                        <div class="mb-4 flex flex-col gap-3 border-b border-slate-200 pb-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-sm font-semibold text-slate-950">{{ $group->name }}</h4>
                                <p class="mt-1 text-xs text-slate-500">{{ $group->sender_email ?: 'No sender email configured yet.' }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex rounded-full bg-white px-3 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200">
                                    Sender Group
                                </span>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('marketing.email-template.sender-groups.update', $group) }}" class="grid gap-4 md:grid-cols-2">
                            @csrf
                            @method('PATCH')
                            <input type="text" name="name" value="{{ $group->name }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Group Name">
                            <input type="text" name="sender_name" value="{{ $group->sender_name }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Sender Name">
                            <input type="email" name="sender_email" value="{{ $group->sender_email }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="Sender Email">
                            <input type="text" name="cc_email" value="{{ $group->cc_email }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900" placeholder="CC">
                            <input type="text" name="bcc_email" value="{{ $group->bcc_email }}" class="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 md:col-span-2" placeholder="BCC">
                            <div class="md:col-span-2 flex flex-col gap-3 border-t border-slate-200 pt-4 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-slate-500">Update sender details or remove this group if it is no longer needed.</p>
                                <div class="flex items-center justify-end gap-1">
                                    <button type="submit" form="delete-sender-group-{{ $group->id }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-500 transition hover:bg-rose-100" title="Delete">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 14H6L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                    </button>
                                    <button type="submit" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 transition hover:bg-slate-50" title="Update Group">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 13l4 4L19 7"/></svg>
                                    </button>
                                </div>
                            </div>
                        </form>
                        <form id="delete-sender-group-{{ $group->id }}" method="POST" action="{{ route('marketing.email-template.sender-groups.destroy', $group) }}" data-swal-confirm="Delete this sender group? This action cannot be undone." data-swal-title="Confirm Delete" data-swal-confirm-button="Yes, Delete" data-swal-cancel-button="Cancel" data-swal-confirm-color="#DC2626" class="hidden">
                            @csrf
                            @method('DELETE')
                        </form>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-4 py-8 text-center text-sm text-slate-500">No sender groups yet.</div>
                @endforelse
            </div>

            @if($senderGroups->hasPages())
                <div class="mt-4">
                    {{ $senderGroups->links() }}
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
