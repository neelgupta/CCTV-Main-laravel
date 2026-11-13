<x-app-layout>
    @php
        $activeStateOptions = $stateOptions->map(function ($state) {
            return [
                'id' => $state->id,
                'name' => $state->name,
            ];
        })->values()->all();

        $activeCityOptions = $activeCityOptions->map(function ($city) {
            return [
                'id' => $city->id,
                'name' => $city->name,
                'state_id' => $city->state_id,
            ];
        })->values()->all();
    @endphp

    <style>
        label.error {
            display: block;
            color: #dc2626;
            font-size: 0.75rem;
            margin-top: 0.5rem;
            font-weight: 500;
        }

        input.error,
        select.error {
            border-color: #dc2626 !important;
        }
    </style>

    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <h2 class="text-2xl font-bold text-slate-950">Location Master</h2>
            <p class="mt-1 text-sm text-slate-500">Add and manage the geographic database.</p>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
    @endif

    <div class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Add State</h3>
                <form id="stateForm" method="POST" action="{{ route('super-admin.states.store') }}" class="mt-5">
                    @csrf
                    <input type="hidden" name="form_type" value="state">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                        <div class="w-full">
                            <input
                                type="text"
                                name="name"
                                value="{{ old('form_type') === 'state' ? old('name') : '' }}"
                                class="block w-full rounded-2xl border-slate-300 px-4 py-3"
                                placeholder="State Name"
                                required
                                minlength="2"
                                maxlength="100"
                            >
                            <x-input-error :messages="$errors->stateForm->get('name')" class="mt-2" />
                        </div>
                        <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">Add</button>
                    </div>
                </form>
            </section>

            <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Add City</h3>
                <form id="cityForm" method="POST" action="{{ route('super-admin.cities.store') }}" class="mt-5 grid gap-4 md:grid-cols-[1.1fr_1fr_auto] md:items-start">
                    @csrf
                    <input type="hidden" name="form_type" value="city">
                    <div>
                        <select name="state_id" class="block w-full rounded-2xl border-slate-300 px-4 py-3" required>
                            <option value="">Select State</option>
                            @foreach($stateOptions as $state)
                                <option value="{{ $state->id }}" @selected(old('form_type') === 'city' && (string) old('state_id') === (string) $state->id)>{{ $state->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->cityForm->get('state_id')" class="mt-2" />
                    </div>
                    <div>
                        <input
                            type="text"
                            name="name"
                            value="{{ old('form_type') === 'city' ? old('name') : '' }}"
                            class="block w-full rounded-2xl border-slate-300 px-4 py-3"
                            placeholder="City Name"
                            required
                            minlength="2"
                            maxlength="100"
                        >
                        <x-input-error :messages="$errors->cityForm->get('name')" class="mt-2" />
                    </div>
                    <button type="submit" class="inline-flex items-center justify-center rounded-2xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white hover:bg-slate-800">Add City</button>
                </form>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2 xl:items-start">
            <section class="min-w-0 overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
                    <div class="text-xl font-bold text-slate-950">Manage Cities</div>
                    <div class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">{{ $cities->total() }} Total Cities</div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                            <tr>
                                <th class="px-6 py-4 text-left">City Name</th>
                                <th class="px-6 py-4 text-left">State</th>
                                <th class="px-6 py-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            @forelse($cities as $city)
                                <tr>
                                    <td class="px-6 py-4 font-medium text-slate-950">{{ $city->name }}</td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $city->state?->name ?? '-' }}</span>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <form method="POST" action="{{ route('super-admin.cities.destroy', $city) }}" class="inline-flex" data-swal-confirm="Delete this city from the active list?" data-swal-title="Delete City">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-100">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-8 text-center text-sm text-slate-500">No cities added yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-slate-200 px-6 py-4">
                    <div class="flex w-full max-w-full justify-center overflow-x-auto">
                        <div class="min-w-max">
                            {{ $cities->links() }}
                        </div>
                    </div>
                </div>
            </section>

            <div class="min-w-0 space-y-6">
                <section class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-xl font-bold text-slate-950">Quick State Edit</h3>
                        <div class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">{{ $states->total() }} Total States</div>
                    </div>
                    <div class="mt-5 space-y-4">
                        @forelse($states as $state)
                            <form method="POST" action="{{ route('super-admin.states.update', $state) }}" class="state-edit-form rounded-2xl border border-slate-200 px-4 py-3">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="editing_state_id" value="{{ $state->id }}">
                                <div class="flex flex-col gap-3">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="w-full">
                                            <input
                                                type="text"
                                                name="name"
                                                value="{{ (string) old('editing_state_id') === (string) $state->id ? old('name') : $state->name }}"
                                                class="block w-full rounded-2xl border-slate-300 px-4 py-3 font-semibold text-slate-900"
                                                placeholder="State Name"
                                                required
                                                minlength="2"
                                                maxlength="100"
                                            >
                                            @if((string) old('editing_state_id') === (string) $state->id)
                                                <x-input-error :messages="$errors->stateUpdateForm->get('name')" class="mt-2" />
                                            @endif
                                        </div>
                                        <button type="submit" class="shrink-0 rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                            Update
                                        </button>
                                    </div>
                                    <div class="text-sm text-slate-500">{{ $state->cities_count }} cities</div>
                                </div>
                            </form>
                        @empty
                            <div class="text-sm text-slate-500">No states available.</div>
                        @endforelse
                    </div>
                    <div class="mt-5 border-t border-slate-200 pt-4">
                        <div class="flex w-full max-w-full justify-center overflow-x-auto">
                            <div class="min-w-max">
                                {{ $states->links() }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[28px] border border-dashed border-slate-300 bg-white/60 p-6 shadow-sm">
                    <h3 class="text-sm font-black uppercase tracking-[0.2em] text-slate-400">Recently Deleted</h3>
                    <div class="mt-5 space-y-4">
                        @forelse($deletedCities as $deletedCity)
                            <div class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                <div class="font-semibold text-slate-900">{{ $deletedCity->name }}</div>
                                <div class="text-sm text-slate-500">{{ $deletedCity->state?->name ?? '-' }}</div>
                            </div>
                        @empty
                            <div class="italic text-slate-400">Trash is empty</div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const activeStates = @json($activeStateOptions);
            const activeCities = @json($activeCityOptions);

            const normalizeValue = (value) => String(value || '').trim().toLowerCase();

            $.validator.addMethod('uniqueStateName', function (value, element) {
                const normalized = normalizeValue(value);
                if (!normalized) {
                    return true;
                }

                return !activeStates.some((state) => normalizeValue(state.name) === normalized);
            }, 'State already exists.');

            $.validator.addMethod('uniqueCityNameForState', function (value, element) {
                const normalized = normalizeValue(value);
                const stateId = normalizeValue($('#cityForm select[name="state_id"]').val());

                if (!normalized || !stateId) {
                    return true;
                }

                return !activeCities.some((city) =>
                    normalizeValue(city.name) === normalized &&
                    normalizeValue(city.state_id) === stateId
                );
            }, 'City already exists for the selected state.');

            $.validator.addMethod('uniqueStateNameOnEdit', function (value, element) {
                const normalized = normalizeValue(value);
                const currentStateId = normalizeValue($(element).closest('form').find('input[name="editing_state_id"]').val());

                if (!normalized) {
                    return true;
                }

                return !activeStates.some((state) =>
                    normalizeValue(state.id) !== currentStateId &&
                    normalizeValue(state.name) === normalized
                );
            }, 'State already exists.');

            $('#stateForm').validate({
                rules: {
                    name: {
                        required: true,
                        minlength: 2,
                        maxlength: 100,
                        uniqueStateName: true
                    }
                },
                messages: {
                    name: {
                        required: 'Please enter a state name.',
                        minlength: 'State name must be at least 2 characters.',
                        maxlength: 'State name may not be greater than 100 characters.',
                        uniqueStateName: 'State already exists.'
                    }
                },
                errorElement: 'label',
                errorClass: 'error',
                highlight: function (element) {
                    $(element).addClass('error');
                },
                unhighlight: function (element) {
                    $(element).removeClass('error');
                }
            });

            $('#cityForm').validate({
                rules: {
                    state_id: {
                        required: true
                    },
                    name: {
                        required: true,
                        minlength: 2,
                        maxlength: 100,
                        uniqueCityNameForState: true
                    }
                },
                messages: {
                    state_id: {
                        required: 'Please select a state.'
                    },
                    name: {
                        required: 'Please enter a city name.',
                        minlength: 'City name must be at least 2 characters.',
                        maxlength: 'City name may not be greater than 100 characters.',
                        uniqueCityNameForState: 'City already exists for the selected state.'
                    }
                },
                errorElement: 'label',
                errorClass: 'error',
                highlight: function (element) {
                    $(element).addClass('error');
                },
                unhighlight: function (element) {
                    $(element).removeClass('error');
                }
            });

            $('.state-edit-form').each(function () {
                $(this).validate({
                    rules: {
                        name: {
                            required: true,
                            minlength: 2,
                            maxlength: 100,
                            uniqueStateNameOnEdit: true
                        }
                    },
                    messages: {
                        name: {
                            required: 'Please enter a state name.',
                            minlength: 'State name must be at least 2 characters.',
                            maxlength: 'State name may not be greater than 100 characters.',
                            uniqueStateNameOnEdit: 'State already exists.'
                        }
                    },
                    errorElement: 'label',
                    errorClass: 'error',
                    highlight: function (element) {
                        $(element).addClass('error');
                    },
                    unhighlight: function (element) {
                        $(element).removeClass('error');
                    }
                });
            });
        });
    </script>
</x-app-layout>
