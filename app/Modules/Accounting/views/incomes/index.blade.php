<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-slate-950">Income</h2>
                <p class="mt-1 text-sm text-slate-500">Record and review every income transaction.</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('accounting.incomes.export', array_merge(request()->query(), ['format' => 'xlsx'])) }}" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">
                    <svg class="h-4 w-4 text-emerald-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M12 5v10m0 0l4-4m-4 4l-4-4" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M5 19h14" stroke-linecap="round"></path>
                    </svg>

                </a>
                <a href="{{ route('accounting.incomes.export', array_merge(request()->query(), ['format' => 'csv'])) }}"  title="CSV" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">
                    <svg class="h-4 w-4 text-sky-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M7 7h10M7 12h10M7 17h10" stroke-linecap="round"></path>
                        <path d="M5 4h14v16H5z" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>

                </a>
                <a href="{{ route('accounting.incomes.export', array_merge(request()->query(), ['format' => 'pdf'])) }}"  title="PDF" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">
                    <svg class="h-4 w-4 text-rose-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M7 3h7l5 5v13H7z" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M14 3v5h5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>

                </a>
                <a href="{{ route('accounting.incomes.export', array_merge(request()->query(), ['format' => 'print'])) }}"  title="Print" target="_blank" class="inline-flex items-center gap-2 border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700">
                    <svg class="h-4 w-4 text-slate-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M7 17H6a2 2 0 01-2-2v-4a2 2 0 012-2h12a2 2 0 012 2v4a2 2 0 01-2 2h-1" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M7 9V4h10v5" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M7 17h10v3H7z" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>

                </a>
                <a href="{{ route('accounting.incomes.create') }}" class=" bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white">+ Add Income</a>
            </div>
        </div>
    </x-slot>

    <div class="space-y-6 py-6">
        <form method="GET" class="border border-white/70 bg-white/85 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search..." class="rounded-2xl border-slate-300">
                <select name="payment_method" class="rounded-2xl border-slate-300"><option value="">All Methods</option>@foreach($paymentMethods as $method)<option value="{{ $method }}" @selected(($filters['payment_method'] ?? '') === $method)>{{ $method }}</option>@endforeach</select>
                <select name="client_id" class="rounded-2xl border-slate-300"><option value="">All Clients</option>@foreach($clients as $client)<option value="{{ $client->id }}" @selected(($filters['client_id'] ?? '') == $client->id)>{{ $client->name }}</option>@endforeach</select>
                <input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="rounded-2xl border-slate-300">
                <input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="rounded-2xl border-slate-300">
            </div>
            <div class="mt-4 flex items-center justify-between">
                <p class="text-sm text-slate-500">Filtered income transactions.</p>
                <div class="flex gap-3">
                    <a href="{{ route('accounting.incomes.index') }}" class=" border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Reset</a>
                    <button class=" bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white">Apply Filters</button>
                </div>
            </div>
        </form>

        <div class="overflow-hidden border border-white/70 bg-white/85 shadow-lg shadow-slate-950/5 backdrop-blur">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            @foreach(['Date','Client','Title','Amount','Payment Method','Transaction No','Created By','Action'] as $heading)
                                <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-[0.18em] text-500">{{ $heading }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        @forelse($incomes as $income)
                            <tr>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ optional($income->received_date)->format('d M, Y') }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $income->client?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-slate-950">{{ $income->title }}</td>
                                <td class="px-4 py-3 text-sm font-semibold text-emerald-700">Rs. {{ number_format((float) $income->amount, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $income->payment_method }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $income->transaction_no ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm text-slate-700">{{ $income->creator?->name ?? '-' }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <div class="flex flex-wrap gap-2">
                                        <a href="{{ route('accounting.incomes.show', $income) }}" class="border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <circle cx="12" cy="12" r="3" stroke-linecap="round" stroke-linejoin="round"></circle>
                                            </svg>
                                        </a>
                                        <a href="{{ route('accounting.incomes.edit', $income) }}" class="border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">
                                            <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17v3Z" stroke-linecap="round" stroke-linejoin="round"></path>
                                                <path d="M13.5 6.5l4 4" stroke-linecap="round" stroke-linejoin="round"></path>
                                            </svg>
                                        </a>
                                        <form method="POST" action="{{ route('accounting.incomes.destroy', $income) }}" onsubmit="return confirm('Delete this income?')">
                                            @csrf @method('DELETE')
                                            <button class="border border-rose-300 px-3 py-1.5 text-xs font-semibold text-rose-700">
                                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                                                    <path d="M4 7h16" stroke-linecap="round"></path>
                                                    <path d="M9 7V4h6v3" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    <path d="M6 7l1 13h10l1-13" stroke-linecap="round" stroke-linejoin="round"></path>
                                                    <path d="M10 11v5M14 11v5" stroke-linecap="round"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">No income records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-4">{{ $incomes->links() }}</div>
        </div>
    </div>
</x-app-layout>
