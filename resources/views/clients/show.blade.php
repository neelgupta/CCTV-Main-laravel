<x-app-layout>
    @php
        $authUser = auth()->user();
        $canManageClients = $authUser && ($authUser->isCompanyAdmin() || $authUser->hasPermission('settings.users.manage'));
        $clientPhone = $client->phone ?: ($client->mobile ?? '-');
        $publicSupportLink = route('customer.support.qr.show', ['token' => $client->qr_token]);
        $qrImageUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=8&data=' . urlencode($publicSupportLink);
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-slate-900 sm:text-2xl">
                    Client Details: <span class="inline">{{ $client->name }}</span>
                </h2>
                <p class="mt-1 text-sm text-slate-500">View client information, related leads, and project activity.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                <span class="inline-flex bg-cyan-50 px-3 py-1 text-xs font-semibold text-cyan-700 ring-1 ring-inset ring-cyan-200">
                    {{ $leads->count() }} Lead{{ $leads->count() === 1 ? '' : 's' }}
                </span>
                <span class="inline-flex bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 ring-1 ring-inset ring-indigo-200">
                    {{ $projects->count() }} Project{{ $projects->count() === 1 ? '' : 's' }}
                </span>
                @if($canManageClients)
                    <a href="{{ route('settings.clients.edit', $client) }}" class="inline-flex items-center bg-indigo-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition hover:bg-indigo-500">
                        Edit Client
                    </a>
                @endif
                <a href="{{ route('settings.clients.index') }}" class="inline-flex items-center border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6 sm:py-8">
        <div class="w-full" x-data="{ tab: 'overview' }">
            <div class="overflow-hidden border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-4 pt-5 sm:px-6 sm:pt-6">
                    <div class="w-full overflow-x-auto pb-1 touch-pan-x [-webkit-overflow-scrolling:touch]">
                        <nav class="-mb-px flex w-max min-w-full flex-nowrap gap-3 border-b border-slate-200 pr-2 sm:gap-8">
                            <button
                                type="button"
                                @click="tab = 'overview'"
                                :class="tab === 'overview' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                                class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm"
                            >
                                Overview
                            </button>
                            <button
                                type="button"
                                @click="tab = 'leads'"
                                :class="tab === 'leads' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                                class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm"
                            >
                                Leads
                            </button>
                            <button
                                type="button"
                                @click="tab = 'projects'"
                                :class="tab === 'projects' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                                class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm"
                            >
                                Projects
                            </button>
                            <button
                                type="button"
                                @click="tab = 'amcs'"
                                :class="tab === 'amcs' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                                class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm"
                            >
                                AMC
                            </button>
                            <button
                                type="button"
                                @click="tab = 'supports'"
                                :class="tab === 'supports' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                                class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm"
                            >
                                Supports
                            </button>
                            <button
                                type="button"
                                @click="tab = 'ledger'"
                                :class="tab === 'ledger' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700'"
                                class="shrink-0 whitespace-nowrap border-b-2 px-2 py-3 text-[13px] font-medium transition sm:px-1 sm:py-4 sm:text-sm"
                            >
                                Ledger
                            </button>
                        </nav>
                    </div>
                </div>

                <div class="p-4 sm:p-6">
                    <div x-show="tab === 'overview'">
                        <div class="grid grid-cols-1 gap-8 lg:grid-cols-2">
                            <div>
                                <h3 class="mb-4 text-lg font-bold text-slate-900">Client Information</h3>
                                <div class="space-y-3 text-sm text-slate-700">
                                    <p><span class="font-semibold text-slate-900">Email:</span> {{ $client->email ?: '-' }}</p>
                                    <p><span class="font-semibold text-slate-900">Phone:</span> {{ $clientPhone }}</p>
                                    <p><span class="font-semibold text-slate-900">Address:</span> {{ $client->address ?: '-' }}</p>
                                    <p><span class="font-semibold text-slate-900">GST Number:</span> {{ $client->gst_number ?: '-' }}</p>
                                    <p><span class="font-semibold text-slate-900">PAN Number:</span> {{ $client->pan_number ?: '-' }}</p>
                                </div>
                            </div>

                            <div>
                                <h3 class="mb-4 text-lg font-bold text-slate-900">Account Details</h3>
                                <div class="space-y-3 text-sm text-slate-700">
                                    <p><span class="font-semibold text-slate-900">Linked User:</span>
                                        @if($client->user)
                                            {{ $client->user->name }} ({{ $client->user->email }})
                                        @else
                                            -
                                        @endif
                                    </p>
                                    <p><span class="font-semibold text-slate-900">Total Leads:</span> {{ $leads->count() }}</p>
                                    <p><span class="font-semibold text-slate-900">Total Projects:</span> {{ $projects->count() }}</p>
                                    {{-- <p><span class="font-semibold text-slate-900">Client ID:</span> {{ $client->uuid ?: $client->id }}</p> --}}
                                    <p><span class="font-semibold text-slate-900">Created:</span> {{ optional($client->created_at)->format('M d, Y') ?: '-' }}</p>
                                </div>
                            </div>
                        </div>

                        @if($canManageClients)
                        <div class="mt-8 border border-slate-200 bg-gradient-to-br from-white to-cyan-50/40 p-5 shadow-sm">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Customer QR Code</h3>
                                    <p class="mt-1 text-sm text-slate-500">Print or share this QR code to open the public complaint form.</p>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <form action="{{ route('settings.clients.qr.generate', $client) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center border border-cyan-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-cyan-700 transition hover:bg-cyan-50">
                                            Generate QR
                                        </button>
                                    </form>

                                    <form action="{{ route('settings.clients.qr.regenerate', $client) }}" method="POST" onsubmit="return confirm('Regenerate QR code? The old QR code will stop working.');">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center border border-rose-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-rose-700 transition hover:bg-rose-50">
                                            Regenerate QR
                                        </button>
                                    </form>

                                    <a href="{{ route('settings.clients.qr.print', $client) }}" target="_blank" class="inline-flex items-center border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                                        Print QR
                                    </a>

                                    <button type="button" onclick="navigator.clipboard.writeText(@js($publicSupportLink)).then(() => alert('Public link copied successfully.'))" class="inline-flex items-center border border-slate-200 bg-white px-4 py-2 text-xs font-semibold uppercase tracking-widest text-slate-700 transition hover:bg-slate-50">
                                        Copy Public Link
                                    </button>
                                </div>
                            </div>

                            <div class="mt-5 grid gap-5 lg:grid-cols-[auto,1fr] lg:items-center">
                                <div class="border border-slate-200 bg-white p-4 shadow-sm">
                                    <img src="{{ $qrImageUrl }}" alt="Customer QR Code" class="h-56 w-56 border border-slate-100 object-contain">
                                </div>

                                <div class="space-y-4">
                                    <div class="border border-slate-200 bg-white p-4">
                                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Public Link</div>
                                        <div class="mt-2 break-all text-sm font-medium text-slate-900">{{ $publicSupportLink }}</div>
                                    </div>
                                    <div class="border border-slate-200 bg-slate-50 p-4">
                                        <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Preview Status</div>
                                        <div class="mt-2 text-sm font-medium text-emerald-700">Latest QR token is active and ready to share.</div>
                                    </div>
                                    <button type="button" onclick="downloadClientQrCode()" class="inline-flex items-center bg-cyan-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-cyan-500">
                                        Download QR
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endif
                    </div>

                    <div x-show="tab === 'leads'" x-cloak>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-bold text-slate-900">Related Leads</h3>
                            <span class="text-sm text-slate-500">{{ $leads->count() }} record{{ $leads->count() === 1 ? '' : 's' }}</span>
                        </div>

                        <div class="mt-4 overflow-x-auto border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Lead</th>
                                        <th class="px-4 py-3">Email</th>
                                        <th class="px-4 py-3">Phone</th>
                                        <th class="px-4 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($leads as $lead)
                                        <tr class="transition-colors hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-900">
                                                <a href="{{ route('leads.show', $lead) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                    {{ $lead->client_name }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">{{ $lead->email ?: '-' }}</td>
                                            <td class="px-4 py-3 text-slate-600">{{ $lead->mobile ?: '-' }}</td>
                                            <td class="px-4 py-3">
                                                @if($lead->status)
                                                    <span class="inline-flex px-2.5 py-1 text-xs font-semibold" style="background-color: {{ $lead->status->color }}20; color: {{ $lead->status->color }}">
                                                        {{ $lead->status->name }}
                                                    </span>
                                                @else
                                                    <span class="text-slate-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No related leads found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="tab === 'projects'" x-cloak>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-bold text-slate-900">Related Projects</h3>
                            <span class="text-sm text-slate-500">{{ $projects->count() }} record{{ $projects->count() === 1 ? '' : 's' }}</span>
                        </div>

                        <div class="mt-4 overflow-x-auto border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Project</th>
                                        <th class="px-4 py-3">Reference</th>
                                        <th class="px-4 py-3">Stage</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($projects as $project)
                                        <tr class="transition-colors hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-900">
                                                <a href="{{ route('projects.show', $project) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                    {{ $project->name }}
                                                </a>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">{{ $project->reference_no ?: '-' }}</td>
                                            <td class="px-4 py-3">
                                                <span class="inline-flex items-center bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">
                                                    {{ str($project->current_stage)->headline() }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="3" class="px-4 py-8 text-center text-sm text-slate-500">No related projects found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="tab === 'amcs'" x-cloak>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-bold text-slate-900">Related AMC</h3>
                            <span class="text-sm text-slate-500">{{ $amcs->count() }} record{{ $amcs->count() === 1 ? '' : 's' }}</span>
                        </div>

                        <div class="mt-4 overflow-x-auto border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">AMC</th>
                                        <th class="px-4 py-3">Project</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">Support Ticket</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($amcs as $amc)
                                        <tr class="transition-colors hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-900">
                                                <a href="{{ route('amcs.show', $amc) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                    {{ $amc->contract_no }}
                                                </a>
                                                <div class="mt-1 text-xs text-slate-500">
                                                    {{ $amc->customer_name ?: '-' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ $amc->project_name ?: ($amc->project?->name ?: '-') }}
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                <div class="flex flex-col gap-1">
                                                    <span>{{ $amc->payment_status ? str($amc->payment_status)->replace('_', ' ')->title()->toString() : '-' }}</span>
                                                    <span class="text-xs text-slate-400">
                                                        {{ $amc->start_date?->format('d M Y') ?: '-' }} - {{ $amc->end_date?->format('d M Y') ?: '-' }}
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                @if($amc->supportTicket)
                                                    <a href="{{ route('tickets.show', $amc->supportTicket) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                        {{ $amc->supportTicket->ticket_number }}
                                                    </a>
                                                @else
                                                    <span class="text-slate-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No related AMC found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="tab === 'supports'" x-cloak>
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="text-lg font-bold text-slate-900">Related Supports</h3>
                            <span class="text-sm text-slate-500">{{ $supportTickets->count() }} record{{ $supportTickets->count() === 1 ? '' : 's' }}</span>
                        </div>

                        <div class="mt-4 overflow-x-auto border border-slate-200">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wider text-slate-500">
                                    <tr>
                                        <th class="px-4 py-3">Ticket</th>
                                        <th class="px-4 py-3">Subject</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3">AMC</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse($supportTickets as $ticket)
                                        <tr class="transition-colors hover:bg-slate-50">
                                            <td class="px-4 py-3 font-medium text-slate-900">
                                                <a href="{{ route('tickets.show', $ticket) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                    {{ $ticket->ticket_number }}
                                                </a>
                                                <div class="mt-1 text-xs text-slate-500">
                                                    {{ $ticket->customer_name ?: '-' }}
                                                </div>
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ $ticket->subject ?: '-' }}
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                {{ $ticket->status_label }}
                                            </td>
                                            <td class="px-4 py-3 text-slate-600">
                                                @if($ticket->generatedFromAmc)
                                                    <a href="{{ route('amcs.show', $ticket->generatedFromAmc) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                        {{ $ticket->generatedFromAmc->contract_no }}
                                                    </a>
                                                @elseif($ticket->amc)
                                                    <a href="{{ route('amcs.show', $ticket->amc) }}" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                                        {{ $ticket->amc->contract_no }}
                                                    </a>
                                                @else
                                                    <span class="text-slate-400">-</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">No related support tickets found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div x-show="tab === 'ledger'" x-cloak>
                        @php
                            $ledgerRows = $ledgerEntries
                                ->sortBy([
                                    fn (array $entry) => optional($entry['date'])->timestamp ?? 0,
                                    fn (array $entry) => strtolower((string) ($entry['reference'] ?? '')),
                                ])
                                ->values();
                            $ledgerCompany = $client->company;
                            $ledgerCompanyPhone = $ledgerCompany?->mobile_number ?: ($ledgerCompany?->phone_number ?: '-');
                            $ledgerClientPhone = $client->phone ?: ($client->mobile ?: '-');
                            $ledgerPeriodStart = optional($ledgerRows->first()['date'] ?? null);
                            $ledgerPeriodEnd = optional($ledgerRows->last()['date'] ?? null);
                            $totalDebit = (float) $ledgerRows->sum(fn (array $entry) => (float) ($entry['debit'] ?? 0));
                            $totalCredit = (float) $ledgerRows->sum(fn (array $entry) => (float) ($entry['credit'] ?? 0));
                            $runningBalance = 0.0;
                            $closingBalance = 0.0;
                            $ledgerPreparedRows = $ledgerRows->map(function (array $entry) use (&$runningBalance, &$closingBalance) {
                                $debit = (float) ($entry['debit'] ?? 0);
                                $credit = (float) ($entry['credit'] ?? 0);
                                $runningBalance += $debit - $credit;
                                $closingBalance = $runningBalance;

                                return array_merge($entry, [
                                    'running_balance' => $runningBalance,
                                ]);
                            });
                            $formatLedgerBalance = function (float $amount): string {
                                $absolute = number_format(abs($amount), 2);

                                if (abs($amount) < 0.005) {
                                    return $absolute;
                                }

                                return $absolute . ' ' . ($amount >= 0 ? 'Dr.' : 'Cr.');
                            };
                        @endphp

                        <div class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Client Ledger</h3>
                                    <p class="mt-1 text-sm text-slate-500">Ledger list inspired by statement format, with running balance and totals.</p>
                                </div>
                                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-right">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-emerald-700">Total Balance</p>
                                    <p class="mt-1 text-lg font-black text-slate-900">{{ $formatLedgerBalance($closingBalance) }}</p>
                                </div>
                            </div>

                            <div class="mt-5 overflow-hidden rounded-2xl border border-slate-300">
                                <!-- <div class="border-b border-slate-300 bg-slate-50 px-4 py-3">
                                    <p class="text-sm font-bold text-slate-900">{{ $ledgerCompany?->name ?? config('app.name') }}</p>
                                    <div class="mt-1 grid gap-1 text-xs text-slate-600 sm:grid-cols-2">
                                        <p>{{ $ledgerCompany?->address ?: '-' }}</p>
                                        <p class="sm:text-right">GSTIN: {{ $ledgerCompany?->gst_number ?: '-' }}</p>
                                        <p>Mobile: {{ $ledgerCompanyPhone }}</p>
                                        <p class="sm:text-right">Email: {{ $ledgerCompany?->email ?: '-' }}</p>
                                    </div>
                                </div> -->

                                <div class="grid border-b border-slate-300 md:grid-cols-3">
                                    <div class="border-b border-slate-200 px-4 py-4 md:border-b-0 md:border-r">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Paid Amount</p>
                                        <p class="mt-2 text-xl font-black text-emerald-600">Rs. {{ number_format((float) ($ledgerSummary['paid_amount'] ?? $ledgerSummary['received_amount'] ?? 0), 2) }}</p>
                                    </div>
                                    <div class="border-b border-slate-200 px-4 py-4 md:border-b-0 md:border-r">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Pending Amount</p>
                                        <p class="mt-2 text-xl font-black text-rose-600">Rs. {{ number_format((float) ($ledgerSummary['pending_amount'] ?? 0), 2) }}</p>
                                    </div>
                                    <div class="px-4 py-4">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-slate-500">Total Amount</p>
                                        <p class="mt-2 text-xl font-black text-slate-900">Rs. {{ number_format((float) ($ledgerSummary['total_amount'] ?? 0), 2) }}</p>
                                    </div>
                                </div>

                                <div class="overflow-x-auto">
                                    <table class="min-w-full text-xs text-slate-700">
                                        <thead class="bg-slate-100">
                                            <tr>
                                                <th class="border-b border-r border-slate-300 px-3 py-2 text-left font-semibold">Type</th>
                                                <th class="border-b border-r border-slate-300 px-3 py-2 text-left font-semibold">Date</th>
                                                <th class="border-b border-r border-slate-300 px-3 py-2 text-left font-semibold">Voucher No</th>
                                                <th class="border-b border-r border-slate-300 px-3 py-2 text-left font-semibold">Particular</th>
                                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right font-semibold">Debit</th>
                                                <th class="border-b border-r border-slate-300 px-3 py-2 text-right font-semibold">Credit</th>
                                                <th class="border-b border-slate-300 px-3 py-2 text-right font-semibold">Balance</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-200">
                                            @forelse($ledgerPreparedRows as $row)
                                                <tr class="bg-white hover:bg-slate-50/70">
                                                    <td class="border-r border-slate-200 px-3 py-2">{{ $row['type'] === 'invoice' ? 'Invoice' : 'Income' }}</td>
                                                    <td class="border-r border-slate-200 px-3 py-2">{{ optional($row['date'])->format('d-M-y') ?: '-' }}</td>
                                                    <td class="border-r border-slate-200 px-3 py-2 font-semibold text-slate-900">{{ $row['reference'] ?: '-' }}</td>
                                                    <td class="border-r border-slate-200 px-3 py-2">{{ $row['description'] ?: '-' }}</td>
                                                    <td class="border-r border-slate-200 px-3 py-2 text-right">{{ number_format((float) ($row['debit'] ?? 0), 2) }}</td>
                                                    <td class="border-r border-slate-200 px-3 py-2 text-right">{{ number_format((float) ($row['credit'] ?? 0), 2) }}</td>
                                                    <td class="px-3 py-2 text-right font-semibold text-slate-900">{{ $formatLedgerBalance((float) ($row['running_balance'] ?? 0)) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="px-3 py-8 text-center text-sm text-slate-500">No ledger entries found for this client yet.</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                        @if($ledgerPreparedRows->isNotEmpty())
                                            <tfoot>
                                                <tr class="bg-slate-100 font-semibold text-slate-900">
                                                    <td colspan="4" class="border-t border-r border-slate-300 px-3 py-2 text-right">Total</td>
                                                    <td class="border-t border-r border-slate-300 px-3 py-2 text-right">{{ number_format($totalDebit, 2) }}</td>
                                                    <td class="border-t border-r border-slate-300 px-3 py-2 text-right">{{ number_format($totalCredit, 2) }}</td>
                                                    <td class="border-t border-slate-300 px-3 py-2 text-right">{{ $formatLedgerBalance($closingBalance) }}</td>
                                                </tr>
                                                <tr class="bg-emerald-50 font-semibold text-slate-900">
                                                    <td colspan="6" class="border-t border-r border-emerald-200 px-3 py-2 text-right">Total Balance</td>
                                                    <td class="border-t border-emerald-200 px-3 py-2 text-right">{{ $formatLedgerBalance($closingBalance) }}</td>
                                                </tr>
                                            </tfoot>
                                        @endif
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadClientQrCode() {
            const qrImageUrl = @js($qrImageUrl);
            const fileName = @js('customer-qr-' . $client->id . '.png');

            fetch(qrImageUrl, { mode: 'cors' })
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Unable to download QR code.');
                    }

                    return response.blob();
                })
                .then((blob) => {
                    const objectUrl = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = objectUrl;
                    link.download = fileName;
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    window.URL.revokeObjectURL(objectUrl);
                })
                .catch(() => {
                    window.open(qrImageUrl, '_blank', 'noopener');
                });
        }
    </script>
</x-app-layout>
