<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Salary Months</h2>
                <p class="mt-1 text-sm text-gray-500">Open a month to review staff salary, advance, and payout status.</p>
            </div>
            <a href="{{ route('teams.staff.index') }}"
                class="inline-flex w-full items-center justify-center bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 sm:w-auto">
                Back to Staff
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-10">
        <div class="w-full px-3 sm:px-6 lg:px-8">
            @if (session('success'))
                <div class="mb-4 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-4 border border-gray-200 bg-white px-4 py-3 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Financial Year</p>
                        <p class="text-sm font-semibold text-gray-900">{{ $financialYear['label'] }}</p>
                    </div>

                    <form method="GET" action="{{ route('teams.payroll.index') }}" class="w-full sm:w-auto">
                        <label class="sr-only" for="fy">Select Financial Year</label>
                        <select id="fy" name="fy" onchange="this.form.submit()"
                            class="w-full border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:w-auto">
                            @foreach ($financialYears as $fy)
                                <option value="{{ $fy['key'] }}" @selected($financialYear['key'] === $fy['key'])>
                                    {{ $fy['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            </div>

            <div class="overflow-hidden border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Monthly Payroll</h3>
                        <p class="text-sm text-gray-500">Month list, processing status, and quick access to every staff payroll sheet.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-[720px] w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Month</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Status</th>
                                <th class="px-4 py-3 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Records</th>
                                <th class="px-4 py-3 text-right text-[11px] font-bold uppercase tracking-wider text-gray-500 sm:px-6 text-white sm:text-xs">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @forelse ($months as $month)
                                <tr class="hover:bg-slate-50">
                                    <td class="whitespace-nowrap px-4 py-3 sm:px-6 sm:py-4">
                                        <div class="font-semibold text-gray-900">{{ $month['month_label'] }}</div>
                                        <div class="text-xs text-gray-500">{{ $month['month_key'] }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 sm:px-6 sm:py-4">
                                        @php
                                            $statusClass = match ($month['status']) {
                                                'Paid' => 'bg-emerald-100 text-emerald-700 border-emerald-200',
                                                'Partial' => 'bg-amber-100 text-amber-700 border-amber-200',
                                                'Draft' => 'bg-sky-100 text-sky-700 border-sky-200',
                                                default => 'bg-gray-100 text-gray-700 border-gray-200',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center border px-2.5 py-1 text-xs font-semibold {{ $statusClass }}">
                                            {{ $month['status'] }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-600 sm:px-6 sm:py-4">
                                        {{ $month['paid_count'] }}/{{ $month['staff_count'] }} paid
                                        <span class="text-gray-400">&middot;</span>
                                        Advance: Rs. {{ number_format($month['total_advance'], 2) }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right sm:px-6 sm:py-4">
                                        <a href="{{ $month['route'] }}"
                                            class="inline-flex h-9 w-9 items-center justify-center bg-indigo-50 text-indigo-700 transition hover:bg-indigo-100"
                                            title="Open month">
                                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                                                <circle cx="12" cy="12" r="3" />
                                            </svg>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500 sm:px-6">
                                        No salary months found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
