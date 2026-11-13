<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-center sm:text-left">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Salary Sheet - {{ $monthDate->format('F Y') }}
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Review total salary, advance, and the net payable for each staff member.
                </p>
            </div>
            <a href="{{ route('teams.payroll.index', ['fy' => $financialYear['key']]) }}"
                class="inline-flex w-full items-center justify-center rounded-md bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 sm:w-auto">
                Back to Months
            </a>
        </div>
    </x-slot>

    <div
        class="py-4 sm:py-10"
        x-data="{
            advanceModalOpen: false,
            advanceStaffName: '',
            advanceHistory: [],
            openAdvanceModal(name, history) {
                this.advanceStaffName = name;
                this.advanceHistory = history || [];
                this.advanceModalOpen = true;
            },
            closeAdvanceModal() {
                this.advanceModalOpen = false;
            }
        }"
    >
        <div class="w-full">
            @if (session('success'))
                <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="mb-4 rounded-lg border border-gray-200 bg-white px-3 py-3 shadow-sm sm:px-4">
                <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Financial Year</p>
                <p class="text-sm font-semibold text-gray-900">{{ $financialYear['label'] }}</p>
            </div>

            <div class="mb-4 grid grid-cols-1 gap-3 sm:mb-6 sm:grid-cols-2 sm:gap-4 lg:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Staff Count</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">{{ $summary['staff_count'] }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Salary</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">&#8377;{{ number_format($summary['total_salary'], 2) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Advance</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">&#8377;{{ number_format($summary['total_advance'], 2) }}</p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Working Days</p>
                    <p class="mt-2 text-2xl font-bold text-gray-900">{{ $summary['working_days'] }}</p>
                </div>
            </div>

            @php
                $canManagePayroll = auth()->user()->isCompanyAdmin() || auth()->user()->hasPermission('teams.staff.salary.manage');
                $canManageAdvance = auth()->user()->isCompanyAdmin() || auth()->user()->hasPermission('teams.staff.salary.advance.manage');
            @endphp

            <form method="POST" action="{{ route('teams.payroll.store', ['month' => $monthKey, 'fy' => $financialYear['key']]) }}">
                @csrf
                <input type="hidden" name="fy" value="{{ $financialYear['key'] }}">

                <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="w-full overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500 text-white sm:px-6">Staff Name</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500 text-white sm:px-6">Total Salary</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500 text-white sm:px-6">Advance</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500 text-white sm:px-6">Net Payable Salary</th>
                                    <th class="px-3 py-3 text-left text-xs font-bold uppercase tracking-wider text-gray-500 text-white sm:px-6">Paid</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @foreach ($rows as $row)
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-3 py-4 sm:px-6">
                                            <div class="font-semibold text-gray-900">{{ $row['staff']->name }}</div>
                                            <div class="text-xs text-gray-500">{{ $row['staff']->user?->email ?? '-' }}</div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm font-semibold text-gray-900 sm:px-6">
                                            <div>&#8377;{{ number_format($row['salary'], 2) }}</div>
                                            <div class="text-xs text-gray-500">&#8377;{{ number_format($row['per_day_rate'], 2) }}/day</div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 sm:px-6">
                                            <button
                                                type="button"
                                                @click="openAdvanceModal(@js($row['staff']->name), @js($row['advance_history']))"
                                                class="text-left"
                                            >
                                                <div class="text-sm font-semibold text-indigo-700 hover:text-indigo-900">
                                                    &#8377;{{ number_format($row['advance'], 2) }}
                                                </div>
                                                <div class="text-xs text-gray-500">
                                                    {{ $row['advance_count'] }} entry(s)
                                                </div>
                                            </button>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm font-semibold text-gray-900 sm:px-6">
                                            <div>&#8377;{{ number_format($row['net_payable'], 2) }}</div>
                                            <div class="text-xs text-gray-500">
                                                {{ number_format($row['present_equivalent'], 1) }}/{{ $row['working_days_count'] }} days
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 sm:px-6">
                                            <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                                <input type="hidden" name="payroll[{{ $row['staff']->id }}][is_paid]" value="0">
                                                <input
                                                    type="checkbox"
                                                    name="payroll[{{ $row['staff']->id }}][is_paid]"
                                                    value="1"
                                                    @checked($row['is_paid'])
                                                    @disabled(!$canManagePayroll)
                                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                >
                                                <span>{{ $row['is_paid'] ? 'Paid' : 'Unpaid' }}</span>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="flex flex-col gap-3 border-t border-gray-100 bg-gray-50 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6">
                        <p class="text-center text-sm text-gray-500 sm:text-left">
                            Advance is managed from the separate Advance menu. This sheet only saves payroll status.
                            @unless($canManagePayroll)
                                <span class="font-medium text-rose-600">You have view-only access.</span>
                            @endunless
                        </p>

                        @if ($canManagePayroll)
                            <button
                                type="submit"
                                class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 sm:w-auto"
                            >
                                Save Payroll
                            </button>
                        @else
                            <button
                                type="button"
                                disabled
                                title="You don't have permission to save payroll"
                                class="inline-flex w-full cursor-not-allowed items-center justify-center rounded-md bg-gray-300 px-4 py-2 text-sm font-semibold text-gray-600 sm:w-auto"
                            >
                                Save Payroll
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <template x-teleport="body">
            <div x-show="advanceModalOpen" x-cloak class="fixed inset-0 z-[200]">
                <div class="absolute inset-0 bg-black/50" @click="closeAdvanceModal()"></div>
                <div class="absolute left-1/2 top-1/2 w-[min(94vw,760px)] -translate-x-1/2 -translate-y-1/2 rounded-2xl bg-white shadow-2xl">
                    <div class="flex items-start justify-between gap-4 border-b border-gray-100 px-4 py-4 sm:px-6">
                        <div>
                            <h3 class="text-lg font-bold text-gray-900" x-text="advanceStaffName"></h3>
                            <p class="text-sm text-gray-500">Advance history</p>
                        </div>
                        <button type="button" class="text-gray-500 hover:text-gray-800" @click="closeAdvanceModal()">
                            &times;
                        </button>
                    </div>
                    <div class="max-h-[60vh] overflow-y-auto p-4 sm:p-6">
                        <template x-if="advanceHistory.length === 0">
                            <p class="text-sm text-gray-500">No advance records found.</p>
                        </template>
                        <template x-if="advanceHistory.length > 0">
                            <div class="space-y-3">
                                <template x-for="item in advanceHistory" :key="item.id || (item.date + item.amount)">
                                    <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3">
                                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900" x-text="item.date"></p>
                                                <p class="text-xs text-gray-500" x-text="item.notes || 'No notes'"></p>
                                            </div>
                                            <div class="flex items-center gap-3 sm:justify-end">
                                                <p class="text-sm font-bold text-indigo-700" x-text="'\u20B9' + Number(item.amount).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></p>
                                                @if ($canManageAdvance)
                                                    <div class="flex items-center gap-2">
                                                        <a :href="item.edit_url" class="rounded-md border border-indigo-200 px-3 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>
                                                        </a>
                                                        <form :action="item.delete_url" method="POST" class="js-delete-advance-form" :data-delete-label="item.notes || item.date">
                                                            @csrf
                                                            @method('DELETE')
                                                            <input type="hidden" name="redirect_to" value="{{ route('teams.payroll.show', ['month' => $monthKey, 'fy' => $financialYear['key']]) }}">
                                                            <button type="button" class="js-delete-advance rounded-md border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                            </button>
                                                        </form>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</x-app-layout>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.addEventListener('click', function (event) {
            const button = event.target.closest('.js-delete-advance');
            if (!button) {
                return;
            }

            event.preventDefault();

            const form = button.closest('.js-delete-advance-form');
            const label = form?.dataset?.deleteLabel || 'this advance';

            Swal.fire({
                title: 'Delete advance?',
                text: `This will permanently remove ${label}.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete',
                cancelButtonText: 'Cancel',
                confirmButtonColor: '#e11d48',
                cancelButtonColor: '#6b7280',
            }).then((result) => {
                if (result.isConfirmed && form) {
                    form.submit();
                }
            });
        });
    });
</script>
