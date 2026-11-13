<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="text-center sm:text-left">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">Salary Advance</h2>
                <p class="mt-1 text-sm text-gray-500">Create, edit, and delete advance entries while keeping payroll totals in sync.</p>
            </div>
            <a href="{{ route('teams.payroll.index') }}"
                class="inline-flex w-full items-center justify-center bg-slate-100 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-200 sm:w-auto">
                Back to Salary
            </a>
        </div>
    </x-slot>

    <div class="py-4 sm:py-10">
        <div class="w-full">
            @if (session('success'))
                <div class="mb-4 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif

            @php
                $canManageAdvance = $canManageAdvance ?? (auth()->user()->isCompanyAdmin() || auth()->user()->hasPermission('teams.staff.salary.advance.manage'));
                $isEditing = filled($editingAdvance ?? null);
                $formStaffId = old('staff_id', $isEditing ? $editingAdvance->staff_id : '');
                $formAdvanceDate = old('advance_date', $isEditing ? optional($editingAdvance->advance_date)->toDateString() : now()->toDateString());
                $formAmount = old('amount', $isEditing ? $editingAdvance->amount : '');
                $formNotes = old('notes', $isEditing ? $editingAdvance->notes : '');
                $listRedirectTo = request()->input('redirect_to', request()->fullUrlWithoutQuery(['edit', 'redirect_to']));
                $currentAdvancePage = max(1, (int) request()->query('page', 1));
            @endphp

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2 lg:items-start">
                @if ($canManageAdvance)
                    <div class="border border-gray-200 bg-white p-5 shadow-sm sm:p-6 lg:self-start">
                        <h3 class="mb-4 text-center text-lg font-bold text-gray-900 sm:text-left">
                            {{ $isEditing ? 'Edit Advance' : 'Add Advance' }}
                        </h3>
                        @if ($isEditing)
                            <div class="mb-4 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                Editing advance for <strong>{{ $editingAdvance->staff?->name ?? 'selected staff' }}</strong>.
                                This form updates the existing record.
                            </div>
                        @endif
                        <form
                            id="advanceForm"
                            method="POST"
                            action="{{ $isEditing ? route('teams.payroll.advance.update', $editingAdvance) : route('teams.payroll.advance.store') }}"
                            class="space-y-4"
                        >
                            @csrf
                            @if ($isEditing)
                                @method('PATCH')
                            @endif
                            <input type="hidden" name="redirect_to" value="{{ old('redirect_to', $redirectTo ?? route('teams.payroll.advance.index')) }}">
                            <div>
                                <x-searchable-select
                                    name="staff_id"
                                    id="staff_id"
                                    label="Staff"
                                    :options="$staffs->map(fn ($staff) => ['id' => $staff->id, 'label' => $staff->name])"
                                    :selected="$formStaffId"
                                    placeholder="Search staff..."
                                    empty-label="Select staff"
                                />
                                <p class="mt-1 text-xs text-gray-500">Choose the staff member name. The recent list below shows full details.</p>
                            </div>

                            <div>
                                <x-input-label value="Date" />
                                <x-text-input type="date" name="advance_date" value="{{ $formAdvanceDate }}" class="mt-1 w-full" />
                            </div>

                            <div>
                                <x-input-label value="Advance Amount" />
                                <x-text-input type="number" step="0.01" name="amount" value="{{ $formAmount }}" class="mt-1 w-full" placeholder="0.00" />
                            </div>

                            <div>
                                <div class="flex items-center justify-between">
                                    <x-input-label for="advance_notes" value="Notes" />
                                    <x-speech-to-text target="advance_notes" />
                                </div>
                                <textarea id="advance_notes" name="notes" rows="4" class="mt-1 w-full border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Reason / note for advance">{{ $formNotes }}</textarea>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                                @if ($isEditing)
                                    <a href="{{ $listRedirectTo }}" class="inline-flex w-full items-center justify-center bg-gray-100 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-200 sm:w-auto">
                                        Cancel Edit
                                    </a>
                                @endif
                                <button type="submit" class="inline-flex w-full items-center justify-center bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 sm:w-auto">
                                    {{ $isEditing ? 'Update Advance' : 'Save Advance' }}
                                </button>
                            </div>
                        </form>
                    </div>
                @else
                    <div class="border border-gray-200 bg-white p-5 shadow-sm sm:p-6 lg:self-start">
                        <h3 class="mb-2 text-lg font-bold text-gray-900">Advance Details</h3>
                        <p class="text-sm text-gray-500">You have view-only access to salary advances.</p>
                    </div>
                @endif

                <div class="overflow-hidden border border-gray-200 bg-white shadow-sm lg:self-start">
                    <div class="border-b border-gray-100 px-4 py-4 sm:px-6">
                        <h3 class="text-lg font-bold text-gray-900">Recent Advance Entries</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-[680px] w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white text-gray-500 sm:px-6">Date</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white text-gray-500 sm:px-6">Staff</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white text-gray-500 sm:px-6">Amount</th>
                                    <th class="px-4 py-3 text-left text-xs font-bold uppercase tracking-wider text-white text-gray-500 sm:px-6">Notes</th>
                                    @if ($canManageAdvance)
                                        <th class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-white text-gray-500 sm:px-6">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                @forelse ($advances as $advance)
                                    <tr class="hover:bg-slate-50">
                                        <td class="whitespace-nowrap px-4 py-4 text-sm text-gray-700 sm:px-6">{{ $advance->advance_date->format('d M Y') }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-sm font-semibold text-gray-900 sm:px-6">{{ $advance->staff?->name ?? '-' }}</td>
                                        <td class="whitespace-nowrap px-4 py-4 text-sm font-semibold text-indigo-700 sm:px-6">&#8377;{{ number_format($advance->amount, 2) }}</td>
                                        <td class="px-4 py-4 text-sm text-gray-600 sm:px-6">{{ Str::limit($advance->notes, 30, '...') ?? '-' }}</td>
                                        @if ($canManageAdvance)
                                            <td class="whitespace-nowrap px-4 py-4 text-right text-sm sm:px-6">
                                                <div class="inline-flex items-center gap-2">
                                                    <a href="{{ route('teams.payroll.advance.edit', array_merge(['advance' => $advance, 'redirect_to' => $listRedirectTo], $currentAdvancePage > 1 ? ['page' => $currentAdvancePage] : [])) }}" class="border border-indigo-200 px-3 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">
                                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M505 122.9L517.1 135C526.5 144.4 526.5 159.6 517.1 168.9L488 198.1L441.9 152L471 122.9C480.4 113.5 495.6 113.5 504.9 122.9zM273.8 320.2L408 185.9L454.1 232L319.8 366.2C316.9 369.1 313.3 371.2 309.4 372.3L250.9 389L267.6 330.5C268.7 326.6 270.8 323 273.7 320.1zM437.1 89L239.8 286.2C231.1 294.9 224.8 305.6 221.5 317.3L192.9 417.3C190.5 425.7 192.8 434.7 199 440.9C205.2 447.1 214.2 449.4 222.6 447L322.6 418.4C334.4 415 345.1 408.7 353.7 400.1L551 202.9C579.1 174.8 579.1 129.2 551 101.1L538.9 89C510.8 60.9 465.2 60.9 437.1 89zM152 128C103.4 128 64 167.4 64 216L64 488C64 536.6 103.4 576 152 576L424 576C472.6 576 512 536.6 512 488L512 376C512 362.7 501.3 352 488 352C474.7 352 464 362.7 464 376L464 488C464 510.1 446.1 528 424 528L152 528C129.9 528 112 510.1 112 488L112 216C112 193.9 129.9 176 152 176L264 176C277.3 176 288 165.3 288 152C288 138.7 277.3 128 264 128L152 128z"/></svg>

                                                    </a>
                                                    <form method="POST" action="{{ route('teams.payroll.advance.destroy', $advance) }}" class="js-delete-advance-form" data-delete-label="{{ $advance->staff?->name ?? 'this advance' }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <input type="hidden" name="redirect_to" value="{{ $listRedirectTo }}">
                                                        <button type="button" class="js-delete-advance border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                                                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ $canManageAdvance ? 5 : 4 }}" class="px-4 py-10 text-center text-sm text-gray-500 sm:px-6">No advance records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if ($advances->hasPages())
                        <div class="border-t border-gray-100 px-4 py-4 sm:px-6">
                            {{ $advances->onEachSide(1)->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        $(document).ready(function() {
            $("#advanceForm").validate({
                rules: {
                    staff_id: {
                        required: true
                    },
                    advance_date: {
                        required: true,
                        date: true
                    },
                    amount: {
                        required: true,
                        number: true,
                        min: 1,
                        max: 1000000
                    },
                    notes: {
                        required: true,
                        maxlength: 2000
                    }
                },

                messages: {
                    staff_id: {
                        required: "Please select a staff member."
                    },
                    advance_date: {
                        required: "Please select a date."
                    },
                    amount: {
                        required: "Please enter the advance amount.",
                        min: "Amount must be at least 1."
                    },
                    notes: {
                        required: "Please provide a reason for the advance.",
                        maxlength: "Notes cannot exceed 2000 characters."
                    }
                },

                errorElement: 'p',
                errorClass: 'text-red-500 text-xs mt-1',

                highlight: function(element) {
                    $(element).addClass('border-red-500').removeClass('border-gray-300');
                },
                unhighlight: function(element) {
                    $(element).removeClass('border-red-500').addClass('border-gray-300');
                },

                errorPlacement: function(error, element) {
                    error.insertAfter(element);
                },

                submitHandler: function(form) {
                    form.submit();
                }
            });

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
</x-app-layout>
