<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950">Income Details</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $income->title }}</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('accounting.incomes.edit', $income) }}" class="inline-flex items-center gap-2 bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M13.5 6.5l4 4" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <!-- <span>Edit</span> -->
                </a>
                <form method="POST" action="{{ route('accounting.incomes.destroy', $income) }}" onsubmit="return confirm('Delete this income?')">
                    @csrf @method('DELETE')
                    <button class="inline-flex items-center gap-2  bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 7h16" stroke-linecap="round"></path>
                            <path d="M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M6 7l1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10 11v5M14 11v5" stroke-linecap="round"></path>
                        </svg>
                        <!-- <span>Delete</span> -->
                    </button>
                </form>
            </div>
        </div>
    </x-slot>
    <div class="py-6">
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur lg:col-span-2">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Category</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $income->category?->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Amount</dt><dd class="mt-1 text-sm font-semibold text-slate-950">Rs. {{ number_format((float) $income->amount, 2) }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Client</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $income->client?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Invoice</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $income->invoice?->invoice_number ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Payment Method</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $income->payment_method }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Received Date</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ optional($income->received_date)->format('d M, Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Transaction No</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $income->transaction_no ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Created By</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $income->creator?->name ?? '-' }}</dd></div>
                </dl>
                @if(filled($income->description))
                    <div class="mt-5 rounded-2xl bg-slate-50 p-4 text-sm text-slate-700 whitespace-pre-line">{{ $income->description }}</div>
                @endif
            </div>
            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <h3 class="text-base font-semibold text-slate-950">Attachment</h3>
                @if($income->attachment_url)
                    <a href="{{ $income->attachment_url }}" target="_blank" class="mt-4 inline-flex rounded-2xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Open File</a>
                @else
                    <p class="mt-4 text-sm text-slate-500">No attachment uploaded.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
