<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Create Staff Advance</h2>
            <a href="{{ route('teams.payroll.advance.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800 font-semibold">← Back to Advances</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" data-flash-success>
                    {{ session('success') }}
                </div>
            @endif
            @if($errors->any())
                <div class="mb-4 rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="bg-white shadow-sm rounded-lg border border-gray-200 p-8">
                <form method="POST" action="{{ route('teams.payroll.advance.store') }}" class="space-y-6">
                    @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-searchable-select
                                name="staff_id"
                                id="staff_id"
                                label="Staff Member"
                                :options="$staffs->map(fn ($staff) => ['id' => $staff->id, 'label' => $staff->name . ' (' . $staff->employee_id . ')'])"
                                :selected="old('staff_id')"
                                placeholder="Search staff..."
                                empty-label="Select staff"
                            />
                        </div>
                        <div>
                            <x-input-label value="Amount" />
                            <x-text-input type="number" step="0.01" min="0" name="amount" class="mt-1 w-full text-sm" placeholder="0.00" required />
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between">
                            <x-input-label for="advance_create_notes" value="Notes (optional)" />
                            <x-speech-to-text target="advance_create_notes" />
                        </div>
                        <textarea id="advance_create_notes" name="notes" rows="3" class="mt-1 w-full border-gray-300 rounded-md shadow-sm text-sm" placeholder="Reason or repayment terms"></textarea>
                    </div>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('teams.payroll.advance.index') }}" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-md text-xs font-semibold hover:bg-gray-200">Cancel</a>
                        <x-primary-button class="bg-indigo-600">Save Advance</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const flash = document.querySelector('[data-flash-success]');
            if (flash) {
                setTimeout(() => {
                    flash.classList.add('transition-opacity', 'duration-500', 'opacity-0');
                    flash.addEventListener('transitionend', () => flash.remove());
                }, 3200);
            }
        });

        @if(session('success'))
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    title: 'Success!',
                    text: "{{ session('success') }}",
                    icon: 'success',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2200,
                    timerProgressBar: true,
                });
            });
        @endif
    </script>
</x-app-layout>
