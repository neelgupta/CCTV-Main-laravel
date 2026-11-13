<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <div>
                <h2 class="text-2xl font-bold text-slate-950">Inquiries & Demo Requests</h2>
                <p class="mt-1 text-sm text-slate-500">Track and manage leads submitted from the CCTVPro landing page demo form.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    onclick="openApiGuideModal()"
                    class="inline-flex items-center gap-2 rounded-2xl border border-indigo-200 bg-indigo-50 px-4 py-2.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 transition-colors shadow-sm"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                    </svg>
                    API Integration Guide
                </button>
                <form method="POST" action="{{ route('super-admin.inquiries.sync-all') }}" onsubmit="return confirm('Sync all inquiries into target company leads?');">
                    @csrf
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-4 py-2.5 text-xs font-semibold text-white hover:bg-rose-700 transition-colors shadow-sm"
                        title="Sync all inquiries to Sourcecube CCTV Pro One leads"
                    >
                        <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Sync to Leads
                    </button>
                </form>

                <a
                    href="{{ route('super-admin.inquiries.export', request()->query()) }}"
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-sm"
                >
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export CSV
                </a>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('success') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">&times;</button>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <svg class="h-5 w-5 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>{{ session('error') }}</span>
            </div>
            <button type="button" onclick="this.parentElement.remove()" class="text-rose-500 hover:text-rose-700">&times;</button>
        </div>
    @endif

    <!-- Metric Cards -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-6">
        <div class="rounded-[24px] border border-slate-200 bg-white p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Total Inquiries</div>
            <div class="mt-2 text-2xl font-bold text-slate-900">{{ number_format($counts['total']) }}</div>
        </div>
        <div class="rounded-[24px] border border-rose-100 bg-rose-50/50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-rose-500">New / Pending</div>
            <div class="mt-2 text-2xl font-bold text-rose-600">{{ number_format($counts['new']) }}</div>
        </div>
        <div class="rounded-[24px] border border-blue-100 bg-blue-50/50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-blue-500">Contacted</div>
            <div class="mt-2 text-2xl font-bold text-blue-600">{{ number_format($counts['contacted']) }}</div>
        </div>
        <div class="rounded-[24px] border border-purple-100 bg-purple-50/50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-purple-500">Demo Scheduled</div>
            <div class="mt-2 text-2xl font-bold text-purple-600">{{ number_format($counts['demo_scheduled']) }}</div>
        </div>
        <div class="rounded-[24px] border border-emerald-100 bg-emerald-50/50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-emerald-500">Converted</div>
            <div class="mt-2 text-2xl font-bold text-emerald-600">{{ number_format($counts['converted']) }}</div>
        </div>
        <div class="rounded-[24px] border border-slate-200 bg-slate-50 p-4 shadow-sm">
            <div class="text-xs font-semibold uppercase tracking-wider text-slate-500">Closed</div>
            <div class="mt-2 text-2xl font-bold text-slate-600">{{ number_format($counts['closed']) }}</div>
        </div>
    </div>

    <!-- Filters & Search -->
    <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm mb-6">
        <form method="GET" action="{{ route('super-admin.inquiries.index') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-12 items-end">
            <div class="lg:col-span-4">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Search</label>
                <div class="relative">
                    <input
                        type="text"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search name, phone, email, company..."
                        class="block w-full rounded-2xl border-slate-300 pl-10 pr-4 py-2.5 text-sm focus:border-rose-500 focus:ring-rose-500"
                    >
                    <svg class="absolute left-3.5 top-3 h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Status</label>
                <select name="status" class="block w-full rounded-2xl border-slate-300 py-2.5 text-sm focus:border-rose-500 focus:ring-rose-500">
                    <option value="all" @selected($statusFilter === 'all' || empty($statusFilter))>All Statuses</option>
                    @foreach($statusOptions as $val => $label)
                        <option value="{{ $val }}" @selected($statusFilter === $val)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">State</label>
                <select name="state" class="block w-full rounded-2xl border-slate-300 py-2.5 text-sm focus:border-rose-500 focus:ring-rose-500">
                    <option value="">All States</option>
                    @foreach($states as $st)
                        <option value="{{ $st }}" @selected($stateFilter === $st)>{{ $st }}</option>
                    @endforeach
                </select>
            </div>

            <div class="lg:col-span-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Date Range</label>
                <input
                    type="date"
                    name="date_from"
                    value="{{ $dateFrom }}"
                    class="block w-full rounded-2xl border-slate-300 py-2.5 text-sm focus:border-rose-500 focus:ring-rose-500"
                    placeholder="From"
                >
            </div>

            <div class="lg:col-span-2 flex items-center gap-2">
                <button
                    type="submit"
                    class="w-full inline-flex justify-center items-center rounded-2xl bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 transition-colors"
                >
                    Filter
                </button>
                @if($search || $statusFilter || $stateFilter || $dateFrom || $dateTo)
                    <a
                        href="{{ route('super-admin.inquiries.index') }}"
                        class="inline-flex items-center justify-center rounded-2xl border border-slate-300 bg-white px-3 py-2.5 text-sm font-medium text-slate-600 hover:bg-slate-50"
                        title="Reset Filters"
                    >
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </section>

    <!-- Inquiries List Table -->
    <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">
                    <tr>
                        <th class="px-6 py-4 text-left">Contact Info</th>
                        <th class="px-6 py-4 text-left">Company & Location</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Admin Notes</th>
                        <th class="px-6 py-4 text-left">Received Date</th>
                        <th class="px-6 py-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    @forelse($inquiries as $inquiry)
                        @php
                            $cleanPhone = preg_replace('/[^0-9]/', '', (string) $inquiry->phone);
                            $waNumber = str_starts_with($cleanPhone, '91') && strlen($cleanPhone) > 10 ? $cleanPhone : (strlen($cleanPhone) === 10 ? '91'.$cleanPhone : $cleanPhone);
                        @endphp
                        <tr class="hover:bg-slate-50/75 transition-colors" id="inquiry-row-{{ $inquiry->id }}">
                            <td class="px-6 py-4">
                                <div class="font-bold text-slate-900 flex items-center gap-2">
                                    {{ $inquiry->name }}
                                    @if($inquiry->created_at && $inquiry->created_at->isToday())
                                        <span class="inline-flex items-center rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 ring-1 ring-inset ring-rose-600/20">NEW</span>
                                    @endif
                                </div>
                                <div class="mt-1 flex flex-col gap-0.5 text-xs text-slate-600">
                                    <div class="flex items-center gap-1.5">
                                        <a href="tel:{{ $inquiry->phone }}" class="font-medium text-slate-800 hover:text-rose-600 hover:underline flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                            {{ $inquiry->phone }}
                                        </a>
                                        @if(!empty($waNumber))
                                            <a
                                                href="https://wa.me/{{ $waNumber }}?text={{ urlencode('Hello ' . $inquiry->name . ', thank you for your demo request for CCTVPro!') }}"
                                                target="_blank"
                                                class="text-emerald-600 hover:text-emerald-700"
                                                title="Chat on WhatsApp"
                                            >
                                                <span class="inline-flex rounded bg-emerald-50 px-1 py-0.5 text-[10px] font-semibold text-emerald-700 hover:bg-emerald-100">WA</span>
                                            </a>
                                        @endif
                                    </div>
                                    @if($inquiry->email)
                                        <a href="mailto:{{ $inquiry->email }}" class="text-slate-500 hover:text-rose-600 hover:underline flex items-center gap-1">
                                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                            {{ $inquiry->email }}
                                        </a>
                                    @endif
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <div class="font-medium text-slate-900">{{ $inquiry->company_name ?: '—' }}</div>
                                <div class="mt-0.5 text-xs text-slate-500 flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    {{ $inquiry->state ?: ($inquiry->city ?: 'State Not Specified') }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <form method="POST" action="{{ route('super-admin.inquiries.status', $inquiry) }}" class="inline-block">
                                    @csrf
                                    @method('PATCH')
                                    <select
                                        name="status"
                                        onchange="this.form.submit()"
                                        class="rounded-full px-3 py-1 text-xs font-semibold cursor-pointer border-0 shadow-sm focus:ring-2 focus:ring-rose-500 {{ $inquiry->status_badge_classes }}"
                                    >
                                        @foreach($statusOptions as $val => $lbl)
                                            <option value="{{ $val }}" @selected($inquiry->status === $val)>{{ $lbl }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>

                            <td class="px-6 py-4">
                                <div class="max-w-[220px]">
                                    @if($inquiry->notes)
                                        <div class="text-xs text-slate-700 line-clamp-2" title="{{ $inquiry->notes }}">
                                            {{ $inquiry->notes }}
                                        </div>
                                    @else
                                        <span class="text-xs text-slate-400 italic">No notes</span>
                                    @endif
                                    <button
                                        type="button"
                                        onclick="openNotesModal({{ $inquiry->id }}, '{{ addslashes($inquiry->name) }}', `{{ addslashes($inquiry->notes ?? '') }}`)"
                                        class="mt-1 text-[11px] font-semibold text-indigo-600 hover:text-indigo-800 hover:underline flex items-center gap-0.5"
                                    >
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                        {{ $inquiry->notes ? 'Edit Notes' : '+ Add Note' }}
                                    </button>
                                </div>
                            </td>

                            <td class="px-6 py-4 text-xs text-slate-600 whitespace-nowrap">
                                <div class="font-medium text-slate-900">{{ $inquiry->created_at ? $inquiry->created_at->format('d M Y') : '—' }}</div>
                                <div class="text-slate-400">{{ $inquiry->created_at ? $inquiry->created_at->format('h:i A') : '' }}</div>
                                <div class="text-[10px] text-slate-400">{{ $inquiry->created_at ? $inquiry->created_at->diffForHumans() : '' }}</div>
                            </td>

                            <td class="px-6 py-4 text-right whitespace-nowrap">
                                <div class="flex items-center justify-end gap-1.5">
                                    <form method="POST" action="{{ route('super-admin.inquiries.sync-lead', $inquiry) }}" class="inline-block" title="Sync this inquiry to company lead">
                                        @csrf
                                        <button
                                            type="submit"
                                            class="rounded-xl border border-indigo-100 bg-indigo-50 p-2 text-indigo-600 hover:bg-indigo-100 hover:text-indigo-700 transition-colors"
                                            title="Sync to Company Lead"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                            </svg>
                                        </button>
                                    </form>

                                    <button
                                        type="button"
                                        onclick="openDetailModal(@js($inquiry))"
                                        class="rounded-xl border border-slate-200 bg-slate-50 p-2 text-slate-600 hover:bg-slate-100 hover:text-slate-900 transition-colors"
                                        title="View Full Details"
                                    >
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </button>

                                    <form method="POST" action="{{ route('super-admin.inquiries.destroy', $inquiry) }}" onsubmit="return confirm('Are you sure you want to delete this inquiry record?');" class="inline-block">
                                        @csrf
                                        @method('DELETE')
                                        <button
                                            type="submit"
                                            class="rounded-xl border border-rose-100 bg-rose-50 p-2 text-rose-600 hover:bg-rose-100 hover:text-rose-700 transition-colors"
                                            title="Delete Inquiry"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-3">
                                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                        </svg>
                                    </div>
                                    <div class="text-base font-semibold text-slate-800">No inquiries found</div>
                                    <p class="text-xs text-slate-400 mt-1">When users submit the demo form from your landing page, their details will appear here in real-time.</p>
                                    <button
                                        type="button"
                                        onclick="openApiGuideModal()"
                                        class="mt-4 inline-flex items-center gap-2 rounded-2xl bg-rose-600 px-4 py-2 text-xs font-semibold text-white hover:bg-rose-700 shadow-sm"
                                    >
                                        View API Connection Setup
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($inquiries->hasPages())
            <div class="border-t border-slate-200 px-6 py-4">
                {{ $inquiries->links() }}
            </div>
        @endif
    </section>

    <!-- Details Modal -->
    <div id="detailModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm flex items-center justify-center">
        <div class="relative w-full max-w-2xl rounded-[28px] bg-white p-6 shadow-2xl transition-all">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900" id="detailName">Inquiry Details</h3>
                    <p class="text-xs text-slate-500" id="detailDate">Submitted info</p>
                </div>
                <button type="button" onclick="closeDetailModal()" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="rounded-2xl bg-slate-50 p-4 border border-slate-100">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Contact Details</div>
                    <div class="mt-2 space-y-2">
                        <div>
                            <span class="text-xs text-slate-500 block">Name</span>
                            <span class="font-semibold text-slate-900" id="detailFullName">—</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500 block">Mobile Number</span>
                            <span class="font-semibold text-slate-900" id="detailPhone">—</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500 block">Email Address</span>
                            <span class="font-semibold text-slate-900" id="detailEmail">—</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl bg-slate-50 p-4 border border-slate-100">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Business & Location</div>
                    <div class="mt-2 space-y-2">
                        <div>
                            <span class="text-xs text-slate-500 block">Company Name</span>
                            <span class="font-semibold text-slate-900" id="detailCompany">—</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500 block">State</span>
                            <span class="font-semibold text-slate-900" id="detailState">—</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-500 block">City</span>
                            <span class="font-semibold text-slate-900" id="detailCity">—</span>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2 rounded-2xl bg-slate-50 p-4 border border-slate-100">
                    <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Inquiry Message / Notes</div>
                    <div class="mt-1 text-slate-700 text-sm whitespace-pre-wrap" id="detailMessage">—</div>
                </div>

                <div class="md:col-span-2 rounded-2xl bg-slate-50 p-4 border border-slate-100 text-xs text-slate-500 space-y-1">
                    <div class="flex justify-between">
                        <span>Source:</span>
                        <span class="font-medium text-slate-800" id="detailSource">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span>IP Address:</span>
                        <span class="font-mono text-slate-800" id="detailIp">—</span>
                    </div>
                    <div class="flex justify-between">
                        <span>User Agent:</span>
                        <span class="font-mono text-slate-800 truncate max-w-sm" id="detailUserAgent">—</span>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button" onclick="closeDetailModal()" class="rounded-2xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Edit Notes Modal -->
    <div id="notesModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm flex items-center justify-center">
        <div class="relative w-full max-w-lg rounded-[28px] bg-white p-6 shadow-2xl transition-all">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Admin Notes</h3>
                    <p class="text-xs text-slate-500" id="notesModalLeadName">Add follow up remarks</p>
                </div>
                <button type="button" onclick="closeNotesModal()" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form id="notesForm" method="POST" action="" class="mt-4">
                @csrf
                @method('PATCH')
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1">Follow-up Notes / Remarks</label>
                    <textarea
                        name="notes"
                        id="notesModalTextarea"
                        rows="4"
                        placeholder="e.g., Called client on Monday, interested in 10-camera setup demo..."
                        class="block w-full rounded-2xl border-slate-300 px-4 py-3 text-sm focus:border-rose-500 focus:ring-rose-500"
                    ></textarea>
                </div>
                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" onclick="closeNotesModal()" class="rounded-2xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-2xl bg-slate-900 px-6 py-2.5 text-xs font-semibold text-white hover:bg-slate-800">
                        Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- API Guide Modal -->
    <div id="apiGuideModal" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 p-4 backdrop-blur-sm flex items-center justify-center">
        <div class="relative w-full max-w-3xl rounded-[28px] bg-white p-6 shadow-2xl transition-all max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between border-b border-slate-100 pb-4">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Landing Page Form API Integration</h3>
                    <p class="text-xs text-slate-500">How to connect the landing page modal form to this Super Admin inquiry system.</p>
                </div>
                <button type="button" onclick="closeApiGuideModal()" class="rounded-full p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="mt-4 space-y-6 text-sm text-slate-700">
                <!-- Endpoint -->
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-1">API Endpoint URL</div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex rounded-lg bg-emerald-100 px-2.5 py-1 font-mono text-xs font-bold text-emerald-800">POST</span>
                        <input
                            type="text"
                            readonly
                            value="{{ url('/api/inquiries') }}"
                            class="font-mono text-xs w-full rounded-xl border-slate-200 bg-slate-50 px-3 py-2 text-slate-800"
                            id="apiUrlInput"
                        >
                        <button
                            type="button"
                            onclick="navigator.clipboard.writeText(document.getElementById('apiUrlInput').value); alert('Copied API URL to clipboard!');"
                            class="shrink-0 rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Copy
                        </button>
                    </div>
                </div>

                <!-- Form Fields Mapping -->
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">Form Fields Supported</div>
                    <div class="overflow-x-auto rounded-2xl border border-slate-200">
                        <table class="min-w-full text-xs divide-y divide-slate-200">
                            <thead class="bg-slate-50 text-slate-500 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-2.5 text-left">Landing Page Field</th>
                                    <th class="px-4 py-2.5 text-left">Key Name</th>
                                    <th class="px-4 py-2.5 text-left">Required</th>
                                    <th class="px-4 py-2.5 text-left">Example</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white text-slate-700">
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-900">Your name</td>
                                    <td class="px-4 py-2 font-mono text-indigo-600">name</td>
                                    <td class="px-4 py-2 font-semibold text-rose-600">Yes</td>
                                    <td class="px-4 py-2 text-slate-500">"Rahul Sharma"</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-900">Mobile number</td>
                                    <td class="px-4 py-2 font-mono text-indigo-600">phone / mobile</td>
                                    <td class="px-4 py-2 font-semibold text-rose-600">Yes</td>
                                    <td class="px-4 py-2 text-slate-500">"9876543210"</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-900">Email id</td>
                                    <td class="px-4 py-2 font-mono text-indigo-600">email</td>
                                    <td class="px-4 py-2 text-slate-400">Optional</td>
                                    <td class="px-4 py-2 text-slate-500">"rahul@example.com"</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-900">Company name</td>
                                    <td class="px-4 py-2 font-mono text-indigo-600">company_name</td>
                                    <td class="px-4 py-2 text-slate-400">Optional</td>
                                    <td class="px-4 py-2 text-slate-500">"SecureVision Pvt Ltd"</td>
                                </tr>
                                <tr>
                                    <td class="px-4 py-2 font-medium text-slate-900">Select State</td>
                                    <td class="px-4 py-2 font-mono text-indigo-600">state</td>
                                    <td class="px-4 py-2 text-slate-400">Optional</td>
                                    <td class="px-4 py-2 text-slate-500">"Maharashtra"</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Code Example -->
                <div>
                    <div class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2">JavaScript Form Submission Code (Landing Page Project)</div>
                    <pre class="rounded-2xl bg-slate-900 p-4 text-xs font-mono text-emerald-400 overflow-x-auto"><code>// Attach this to your "Request My Demo" form submit event
async function submitInquiry(event) {
    event.preventDefault();

    const payload = {
        name: document.querySelector('#name').value,
        phone: document.querySelector('#phone').value,
        email: document.querySelector('#email').value,
        company_name: document.querySelector('#company_name').value,
        state: document.querySelector('#state').value
    };

    try {
        const response = await fetch('{{ url('/api/inquiries') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const result = await response.json();
        if (response.ok && result.success) {
            alert(result.message || 'Demo requested successfully!');
            // close modal / reset form
        } else {
            alert(result.message || 'Failed to submit demo request.');
        }
    } catch (err) {
        console.error('Error submitting demo request:', err);
    }
}</code></pre>
                </div>
            </div>

            <div class="mt-6 flex justify-end border-t border-slate-100 pt-4">
                <button type="button" onclick="closeApiGuideModal()" class="rounded-2xl bg-slate-900 px-6 py-2.5 text-xs font-semibold text-white hover:bg-slate-800">
                    Got It
                </button>
            </div>
        </div>
    </div>

    <script>
        function openDetailModal(inquiry) {
            document.getElementById('detailName').innerText = inquiry.name || 'Inquiry Details';
            document.getElementById('detailDate').innerText = inquiry.created_at ? new Date(inquiry.created_at).toLocaleString() : '';
            document.getElementById('detailFullName').innerText = inquiry.name || '—';
            document.getElementById('detailPhone').innerText = inquiry.phone || '—';
            document.getElementById('detailEmail').innerText = inquiry.email || '—';
            document.getElementById('detailCompany').innerText = inquiry.company_name || '—';
            document.getElementById('detailState').innerText = inquiry.state || '—';
            document.getElementById('detailCity').innerText = inquiry.city || '—';
            document.getElementById('detailMessage').innerText = inquiry.message || 'No additional message submitted.';
            document.getElementById('detailSource').innerText = inquiry.source || 'landing_page_demo';
            document.getElementById('detailIp').innerText = inquiry.ip_address || '—';
            document.getElementById('detailUserAgent').innerText = inquiry.user_agent || '—';

            document.getElementById('detailModal').classList.remove('hidden');
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }

        function openNotesModal(id, name, currentNotes) {
            document.getElementById('notesModalLeadName').innerText = 'Notes for: ' + name;
            document.getElementById('notesModalTextarea').value = currentNotes || '';
            document.getElementById('notesForm').action = '{{ url('/admin/inquiries') }}/' + id + '/notes';
            document.getElementById('notesModal').classList.remove('hidden');
        }

        function closeNotesModal() {
            document.getElementById('notesModal').classList.add('hidden');
        }

        function openApiGuideModal() {
            document.getElementById('apiGuideModal').classList.remove('hidden');
        }

        function closeApiGuideModal() {
            document.getElementById('apiGuideModal').classList.add('hidden');
        }

        // Close on escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeDetailModal();
                closeNotesModal();
                closeApiGuideModal();
            }
        });
    </script>
</x-app-layout>

