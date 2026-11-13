<x-app-layout>
    @php
        $authUser = auth()->user();
        $canExport = $authUser && $authUser->hasAnyPermission('leads.quotation.manage', 'support.view', 'tickets.view', 'tickets.reply', 'tickets.manage');
        $quotationStatusOptions = collect($statusOptions)->map(fn ($label, $value) => [
            'id' => (string) $value,
            'name' => $label,
        ])->values();
        $quotationStaffOptions = collect($staffMembers ?? [])->map(fn ($staff) => [
            'id' => (string) $staff->id,
            'name' => $staff->name,
            'meta' => collect([$staff->email ?? null, $staff->role ?? null])->filter()->implode(' • '),
        ])->values();
    @endphp

    <x-slot name="header">
        <div id="service-call-quotations-page" x-data="{ filtersOpen: {{ collect($filters ?? [])->filter()->isNotEmpty() ? 'true' : 'false' }}, exportOpen: false }" class="border border-slate-200 bg-white px-4 py-4 shadow-sm sm:px-5">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-slate-400">Service Call</p>
                    <h2 class="mt-1 text-2xl font-semibold leading-tight text-slate-950">Quotations</h2>
                    <p class="mt-1 text-sm text-slate-500">Filter quotations by date, staff, and client search. Export keeps the active filters.</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <a href="{{ route('service-call.quotations.create') }}" class="bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Create Quotation</a>
                    @if($canExport)
                        <a href="{{ route('service-call.quotations.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}"
                         title="Excel" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h10l6 6v10a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 4v6h6"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M8 14l4 4m0-4l-4 4"/>
                            </svg>
                        </a>
                        <a href="{{ route('service-call.quotations.export', array_merge(request()->query(), ['format' => 'csv'])) }}"
                         title="CSV" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 4h16v16H4z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 10h16M10 4v16"/>
                            </svg>
                        </a>

                        <a href="{{ route('service-call.quotations.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"
                         title="PDF" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M7 3h7l5 5v13H7a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 3v5h5"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 11h6M9 15h6"/>
                            </svg>
                        </a>
                    @endif
                </div>
            </div>

            <div x-show="filtersOpen" x-transition x-cloak class="mt-4 border-t border-slate-100 pt-4">
                <form method="GET" action="{{ route('service-call.quotations.index') }}" class="grid gap-4 lg:grid-cols-6">
                    <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
                    <div>
                        <label for="start_date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Start Date</label>
                        <input id="start_date" name="start_date" type="date" value="{{ $filters['start_date'] ?? '' }}"
                            class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm transition-all duration-250 focus:border-[#c01917] focus:outline-none focus:ring-4 focus:ring-[#c01917]/10">
                    </div>
                    <div>
                        <label for="end_date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">End Date</label>
                        <input id="end_date" name="end_date" type="date" value="{{ $filters['end_date'] ?? '' }}"
                            class="w-full border border-slate-300 px-4 py-2.5 text-sm text-slate-700 shadow-sm transition-all duration-250 focus:border-[#c01917] focus:outline-none focus:ring-4 focus:ring-[#c01917]/10">
                    </div>
                    <div>
                        <x-searchable-select
                            name="status"
                            id="status"
                            label="Status"
                            :options="$quotationStatusOptions"
                            :selected="(string) ($filters['status'] ?? '')"
                            empty-label="All Status"
                            placeholder="Search status..."
                        />
                    </div>
                    <div>
                        <x-searchable-select
                            name="staff_id"
                            id="staff_id"
                            label="Staff"
                            :options="$quotationStaffOptions"
                            :selected="(string) ($filters['staff_id'] ?? '')"
                            empty-label="All Staff"
                            placeholder="Search staff..."
                        />
                    </div>
                    <div class="lg:col-span-2 flex items-end gap-3">
                        <button type="submit" class="inline-flex h-[42px] items-center bg-[#c01917] px-5 text-sm font-semibold text-white shadow-sm transition-all duration-250 hover:-translate-y-0.5 hover:bg-[#a61413] hover:shadow-md">Apply Filter</button>
                        <a href="{{ route('service-call.quotations.index') }}" class="inline-flex h-[42px] items-center border border-slate-300 bg-white px-5 text-sm font-semibold text-slate-700 transition-all duration-250 hover:bg-slate-50">Reset</a>
                    </div>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="w-full px-2 py-6 sm:px-6 lg:px-8">
        <div id="service-call-page-shell">
            <div class="ww-table-toolbar">
                <p class="ww-table-count">
                    @if ($quotations->total() > 0)
                        Showing <span class="font-semibold text-slate-700">{{ $quotations->firstItem() }}</span>
                        to <span class="font-semibold text-slate-700">{{ $quotations->lastItem() }}</span>
                        of <span class="font-semibold text-slate-700">{{ $quotations->total() }}</span> entries
                    @else
                        No entries to show
                    @endif
                </p>
                <div class="ww-pagination-wrap">
                    {{ $quotations->onEachSide(1)->links() }}
                </div>
            </div>

            <div class="ww-table-shell w-full overflow-hidden rounded-lg border border-slate-200">
                <!-- Added overflow-x-auto and w-full to enable horizontal scrolling on small screens -->
                <div class="ww-table-scroll w-full overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50/90">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Quotation #</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Client</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Company</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Mobile</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Email</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Staff</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Amount</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-[0.18em] text-500">Date</th>
                                <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-[0.18em] text-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            @forelse($quotations as $quotation)
                                @php
                                    $statusLabel = (string) ($quotation['status_label'] ?? 'Draft');
                                    $badgeClass = match ($statusLabel) {
                                        'Accepted' => 'border-emerald-300 text-emerald-700 bg-white',
                                        'Sent' => 'border-sky-300 text-sky-700 bg-white',
                                        'Declined' => 'border-rose-300 text-rose-700 bg-white',
                                        'Expired' => 'border-orange-300 text-orange-700 bg-white',
                                        'Quotation' => 'border-[#c01917]/30 text-[#c01917] bg-white',
                                        'Draft' => 'border-slate-300 text-slate-600 bg-white',
                                        default => 'border-slate-300 text-slate-600 bg-white',
                                    };
                                @endphp
                                <tr class="transition-colors duration-200 hover:bg-slate-50/80">
                                    <td class="whitespace-nowrap px-5 py-4 align-top">
                                        <div class="text-sm font-semibold text-[#c01917]">{{ $quotation['number'] ?? '-' }}</div>
                                        @if(! empty($quotation['view_url']))
                                            <a href="{{ $quotation['view_url'] }}" class="mt-1 inline-flex text-xs font-medium text-slate-500 transition hover:text-[#c01917]">View</a>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $quotation['client_name'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $quotation['company_name'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $quotation['mobile'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $quotation['email'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-700">{{ $quotation['staff_name'] ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4">
                                        <span class="{{ $badgeClass }} inline-flex items-center border px-3 py-1 text-xs font-semibold uppercase tracking-wide">
                                            {{ $statusLabel }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-900">Rs. {{ number_format((float) ($quotation['amount'] ?? 0), 2) }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-500">{{ optional($quotation['date'] ?? null)->format('d M Y') ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-5 py-4 text-right text-sm">
                                        @if(! empty($quotation['action_url']))
                                            <div class="flex justify-end gap-2">
                                                @if(! empty($quotation['edit_url']))
                                                    <a href="{{ $quotation['edit_url'] }}" class="inline-flex items-center border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Edit</a>
                                                @endif
                                                <a href="{{ $quotation['action_url'] }}" class="inline-flex items-center border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                            </div>
                                        @else
                                            <span class="inline-flex items-center border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-400">{{ $quotation['action_label'] ?? 'View' }} Unavailable</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="ww-table-empty">No quotations found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
