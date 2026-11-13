<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950">Expense Details</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $expense->title }}</p>
            </div>
            <div class="flex gap-3">
                @if(($expense->approval_status ?? 'approved') === 'pending' && auth()->user()?->isCompanyAdmin())
                    <form method="POST" action="{{ route('accounting.expenses.approve', $expense) }}">
                        @csrf @method('PATCH')
                        <button class="inline-flex items-center gap-2  bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white">Approve</button>
                    </form>
                @endif
                <a href="{{ route('accounting.expenses.edit', $expense) }}" class="inline-flex items-center gap-2  bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M13.5 6.5l4 4" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Edit</span>
                </a>
                <form method="POST" action="{{ route('accounting.expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense?')">
                    @csrf @method('DELETE')
                    <button class="inline-flex items-center gap-2  bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M4 7h16" stroke-linecap="round"></path>
                            <path d="M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M6 7l1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"></path>
                            <path d="M10 11v5M14 11v5" stroke-linecap="round"></path>
                        </svg>
                        <span>Delete</span>
                    </button>
                </form>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="grid gap-4 lg:grid-cols-3">
            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur lg:col-span-2">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Category</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $expense->category?->name }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Amount</dt><dd class="mt-1 text-sm font-semibold text-slate-950">Rs. {{ number_format((float) $expense->amount, 2) }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Vendor</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $expense->vendor_name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Staff</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $expense->staff?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Payment Method</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $expense->payment_method }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Expense Date</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ optional($expense->expense_date)->format('d M, Y') }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Approval</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ ($expense->approval_status ?? 'approved') === 'pending' ? 'Pending Approval' : 'Approved' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Approved By</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $expense->approver?->name ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Transaction No</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $expense->transaction_no ?? '-' }}</dd></div>
                    <div><dt class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Created By</dt><dd class="mt-1 text-sm font-semibold text-slate-950">{{ $expense->creator?->name ?? '-' }}</dd></div>
                </dl>

                @if(filled($expense->description))
                    <div class="mt-5  bg-slate-50 p-4 text-sm text-slate-700 whitespace-pre-line">{{ $expense->description }}</div>
                @endif
            </div>

            <div class=" border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
                <h3 class="text-base font-semibold text-slate-950">Attachment</h3>
                @if($expense->attachment_url)
                    <a href="{{ $expense->attachment_url }}" target="_blank" rel="noopener" class="mt-4 inline-flex  bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">
                        Open File
                    </a>
                @else
                    <p class="mt-4 text-sm text-slate-500">No attachment uploaded.</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
