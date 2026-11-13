<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950">Expense</h2>
                <p class="mt-1 text-sm text-slate-500">Track every expense transaction.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('accounting.expenses.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="inline-flex items-center gap-2  border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700" title="Excel">
                    <svg class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M7 3h7l5 5v13H7z" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M14 3v5h5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Excel</span>
                </a>
                <a href="{{ route('accounting.expenses.export', array_merge(request()->query(), ['format' => 'csv'])) }}" class="inline-flex items-center gap-2  border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700" title="CSV">
                    <svg class="h-4 w-4 text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M7 7h10M7 12h10M7 17h10" stroke-linecap="round"></path>
                        <path d="M5 4h14v16H5z" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>CSV</span>
                </a>
                <a href="{{ route('accounting.expenses.export', array_merge(request()->query(), ['format' => 'pdf'])) }}" class="inline-flex items-center gap-2  border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700" title="PDF">
                    <svg class="h-4 w-4 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M7 3h7l5 5v13H7z" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M14 3v5h5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>PDF</span>
                </a>
                <a href="{{ route('accounting.expenses.export', array_merge(request()->query(), ['format' => 'print'])) }}" target="_blank" class="inline-flex items-center gap-2  border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700" title="Print">
                    <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M7 17H6a2 2 0 01-2-2v-4a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2h-1" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M7 9V4h10v5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M7 17h10v3H7z" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                    <span>Print</span>
                </a>
                <a href="{{ route('accounting.expenses.create') }}" class=" bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white" style="background-color: #f77070;">+ Add Expense</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 py-6">
        <form method="GET" class="border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search..." class=" border-slate-300">
                <select name="category_id" class=" border-slate-300"><option value="">All Categories</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(($filters['category_id'] ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select>
                <select name="payment_method" class=" border-slate-300"><option value="">All Methods</option>@foreach($paymentMethods as $method)<option value="{{ $method }}" @selected(($filters['payment_method'] ?? '') === $method)>{{ $method }}</option>@endforeach</select>
                @unless($isStaffScoped ?? false)
                    <select name="staff_id" class=" border-slate-300"><option value="">All Staff</option>@foreach($staffMembers as $staff)<option value="{{ $staff->id }}" @selected(($filters['staff_id'] ?? '') == $staff->id)>{{ $staff->name }}</option>@endforeach</select>
                    <select name="approval_status" class=" border-slate-300"><option value="">All Status</option>@foreach($approvalStatuses as $status => $label)<option value="{{ $status }}" @selected(($filters['approval_status'] ?? '') === $status)>{{ $label }}</option>@endforeach</select>
                @endunless
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class=" border-slate-300">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class=" border-slate-300">
                <input type="number" step="0.01" min="0" name="amount_min" value="{{ $filters['amount_min'] ?? '' }}" placeholder="Min Amount" class=" border-slate-300">
                <input type="number" step="0.01" min="0" name="amount_max" value="{{ $filters['amount_max'] ?? '' }}" placeholder="Max Amount" class=" border-slate-300">
                <input type="text" name="vendor_name" value="{{ $filters['vendor_name'] ?? '' }}" placeholder="Vendor" class=" border-slate-300">
            </div>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-sm text-slate-500">Filtered expense transactions.</p>
                <div class="flex gap-3">
                    <a href="{{ route('accounting.expenses.index') }}" class=" border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Reset</a>
                    <button class=" bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply Filters</button>
                </div>
            </div>
        </form>

        <div class="overflow-hidden border border-white/70 bg-white/85 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach(['Date','Category','Vendor','Title','Amount','Payment Method','Transaction No','Status','Created By','Action'] as $heading)
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-white">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($expenses as $expense)
                            <tr>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ optional($expense->expense_date)->format('d M, Y') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $expense->category?->name }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $expense->vendor_name }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-950">{{ $expense->title }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-rose-700">Rs. {{ number_format((float) $expense->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $expense->payment_method }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $expense->transaction_no ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class=" px-2.5 py-1 text-xs font-semibold {{ $expense->approval_status === 'pending' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">{{ $expense->approval_status === 'pending' ? 'Pending' : 'Approved' }}</span>
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $expense->creator?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex flex-wrap gap-2">
                                        @if(($expense->approval_status ?? 'approved') === 'pending' && auth()->user()?->isCompanyAdmin())
                                            <form method="POST" action="{{ route('accounting.expenses.approve', $expense) }}">
                                                @csrf @method('PATCH')
                                                <button class="inline-flex items-center gap-1.5  border border-emerald-300 px-3 py-1.5 text-xs font-semibold text-emerald-700">Approve</button>
                                            </form>
                                        @endif
                                        <a href="{{ route('accounting.expenses.show', $expense) }}" class="inline-flex items-center gap-1.5 border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"></circle>
                                            </svg>
                                            <!-- <span>View</span> -->
                                        </a>
                                        <a href="{{ route('accounting.expenses.edit', $expense) }}" class="inline-flex items-center gap-1.5  border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path d="M13.5 6.5l4 4" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                            <!-- <span>Edit</span> -->
                                        </a>
                                        <form method="POST" action="{{ route('accounting.expenses.destroy', $expense) }}" onsubmit="return confirm('Delete this expense?')">
                                            @csrf @method('DELETE')
                                            <button class="inline-flex items-center gap-1.5  border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M4 7h16" stroke-linecap="round"></path>
                                                    <path d="M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    <path d="M6 7l1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    <path d="M10 11v5M14 11v5" stroke-linecap="round"></path>
                                                </svg>
                                                <!-- <span>Delete</span> -->
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-10 text-center text-sm text-slate-500">No expense records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-4">{{ $expenses->links() }}</div>
        </div>
    </div>
</x-app-layout>
