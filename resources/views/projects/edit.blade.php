<x-app-layout>
    @php
        $cityOptionsByState = $states->mapWithKeys(function ($state) {
            return [
                $state->id => $state->cities
                    ->map(
                        fn($city) => [
                            'id' => $city->id,
                            'name' => $city->name,
                        ],
                    )
                    ->values(),
            ];
        });
        $selectedStateId = old('state_id', $project->state_id);
        $selectedCityId = old('city_id', $project->city_id);
        $initialCityOptions = collect($cityOptionsByState[$selectedStateId] ?? [])
            ->map(
                fn($city) => [
                    'id' => $city['id'],
                    'name' => $city['name'],
                ],
            )
            ->values();
    @endphp
    <x-slot name="header">
        <div class="border border-white/70 bg-white/85 p-5 shadow-xl backdrop-blur">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-extrabold text-slate-950">Edit Project</h2>
                    <p class="text-sm text-slate-500">{{ $project->reference_no }} — {{ $project->name }}</p>
                </div>
                <a href="{{ route('projects.show', $project) }}"
                    class="inline-flex items-center gap-2 border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition">
                    Back to Project
                </a>
            </div>
        </div>
    </x-slot>

    <div id="project-edit-page" class="py-8">
        <div class="w-full">

            @if ($errors->any())
                <div class="mb-6 border border-red-200 bg-red-50 p-4">
                    <ul class="list-disc list-inside text-sm text-red-600">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form id="project-edit-form" method="POST" action="{{ route('projects.update', $project) }}" novalidate
                class="space-y-6">
                @csrf
                @method('PATCH')

                {{-- Main Consolidated Card --}}
                <div class="border border-slate-200 bg-white p-6 shadow-sm">
                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div class="md:col-span-3">
                            <x-client-picker :clients="$clients ?? []" :selected="old('existing_client_id', $selectedExistingClientId ?? '')" label="Select Existing Client"
                                helper-text="Search and select exactly one client, or keep New client and edit the fields below manually." />
                        </div>

                        {{-- Project Name --}}
                        <div>
                            <x-input-label for="name" value="Project Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full"
                                :value="old('name', $project->name)" required />
                        </div>

                        {{-- Reference & Lead --}}
                        <div>
                            <x-input-label for="reference_no" value="Reference Number" />
                            <x-text-input id="reference_no" name="reference_no" class="mt-1 block w-full"
                                :value="old('reference_no', $project->reference_no)" />
                        </div>

                        <div>
                            <x-input-label for="lead_id" value="Linked Lead" />
                            <select id="lead_id" name="lead_id"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">-- No linked lead --</option>
                                @foreach ($leads as $lead)
                                    <option value="{{ $lead->id }}" @selected((string) old('lead_id', $project->lead_id) === (string) $lead->id)>
                                        {{ $lead->client_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="contractor_id" value="Contractor (Optional)" />
                            <select id="contractor_id" name="contractor_id"
                                class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                <option value="">-- No contractor --</option>
                                @foreach ($contractors ?? [] as $contractor)
                                    <option value="{{ $contractor->id }}" @selected((string) old('contractor_id', $project->contractor_id) === (string) $contractor->id)>
                                        {{ $contractor->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Status & Stage --}}
                        <div>
                            <x-input-label for="current_stage" value="Current Stage" />
                            <select id="current_stage" name="current_stage"
                                class="mt-1 block w-full border-gray-300 text-sm">
                                @foreach ($projectStages as $stageKey => $stageData)
                                    <option value="{{ $stageKey }}" @selected(old('current_stage', $project->current_stage) === $stageKey)>
                                        {{ is_array($stageData) ? $stageData['name'] : $stageData }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="status" value="Overall Status" />
                            <select id="status" name="status" class="mt-1 block w-full border-gray-300 text-sm">
                                @foreach($statusOptions as $statusKey => $statusLabel)
                                    <option value="{{ $statusKey }}" @selected(old('status', $project->status) === $statusKey)>
                                        {{ $statusLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Dates --}}
                        <div>
                            <x-input-label for="start_date" value="Start Date" />
                            <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date', $project->start_date?->format('Y-m-d'))" />
                        </div>

                        <div>
                            <x-input-label for="end_date" value="Deadline" />
                            <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date', $project->end_date?->format('Y-m-d'))" />
                        </div>

                        {{-- Client Info --}}
                        <div>
                            <x-input-label for="client_name" value="Client Name" />
                            <x-text-input id="client_name" name="client_name" class="mt-1 block w-full" :value="old('client_name', $project->client_name)" required />
                        </div>

                        <div class="md:col-span-3">
                            <x-input-label for="client_address" value="Client Address" />
                            <div class="relative mt-1">
                                <textarea
                                    id="client_address"
                                    name="client_address"
                                    rows="3"
                                    class="block w-full border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    autocomplete="off"
                                    data-location-autocomplete="project-client-address"
                                    data-autocomplete-url="{{ config('services.location_autocomplete.url') }}"
                                >{{ old('client_address', $project->address) }}</textarea>
                                <div
                                    id="client-address-suggestions"
                                    class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden border border-slate-200 bg-white shadow-xl"
                                    role="listbox"
                                    aria-label="Client address suggestions"
                                ></div>
                            </div>
                        </div>
                        <input type="hidden" name="latitude" id="latitude"
                            value="{{ old('latitude', $project->latitude) }}">
                        <input type="hidden" name="longitude" id="longitude"
                            value="{{ old('longitude', $project->longitude) }}">

                        <div>
                            <x-input-label for="state_id" value="State" />
                            <select id="state_id" name="state_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">Select state...</option>
                                @foreach($states as $state)
                                    <option value="{{ $state->id }}" @selected((string) old('state_id', $project->state_id) === (string) $state->id)>{{ $state->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <x-input-label for="city_id" value="City" />
                            <select id="city_id" name="city_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 text-sm" required>
                                <option value="">Select city...</option>
                            </select>
                        </div>
                        @foreach($projectCustomFields ?? [] as $field)
                            @php
                                $fieldValue = data_get(
                                    old('custom_fields', $project->custom_fields ?? []),
                                    $field['key'],
                                );
                                if ($fieldValue === null) {
                                    $fieldValue = $field['type'] === 'checkbox' ? false : '';
                                }
                                $isTextarea = $field['type'] === 'textarea';
                                $isChoiceField = in_array($field['type'], ['dropdown', 'radiobutton'], true);
                                $inputType = in_array($field['type'], ['email', 'number', 'date'], true)
                                    ? $field['type']
                                    : 'text';
                            @endphp

                            <div @class(['md:col-span-3' => $isTextarea || $field['type'] === 'checkbox'])>
                                @if($field['type'] === 'checkbox')
                                    <label class="flex items-start gap-3 border border-slate-200 bg-slate-50 px-4 py-3">
                                        <input type="hidden" name="custom_fields[{{ $field['key'] }}]" value="0">
                                        <input id="custom_field_{{ $field['key'] }}" name="custom_fields[{{ $field['key'] }}]" type="checkbox" value="1" class="mt-1 er-slate-300 text-indigo-600 focus:ring-indigo-500" @checked((bool) $fieldValue)>
                                        <span>
                                            <span
                                                class="block text-sm font-semibold text-slate-900">{{ $field['label'] }}
                                                @if ($field['required'])
                                                    <span class="text-rose-500">*</span>
                                                @endif
                                            </span>
                                            @if (!empty($field['help_text']))
                                                <span
                                                    class="mt-1 block text-xs text-slate-500">{{ $field['help_text'] }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @elseif($isTextarea)
                                    <div class="flex items-center justify-between">
                                        <x-input-label for="custom_field_{{ $field['key'] }}" :value="$field['label']" />
                                        @if(str_contains(strtolower($field['key'] . ' ' . $field['label']), 'desc'))
                                            <x-speech-to-text target="custom_field_{{ $field['key'] }}" />
                                        @endif
                                    </div>
                                    <textarea id="custom_field_{{ $field['key'] }}" name="custom_fields[{{ $field['key'] }}]" rows="3" class="mt-1 block w-full border-gray-300 text-sm" placeholder="{{ $field['placeholder'] ?? '' }}" {{ $field['required'] ? 'required' : '' }}>{{ old('custom_fields.' . $field['key'], $fieldValue) }}</textarea>
                                    @if(!empty($field['help_text']))
                                        <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                                    @endif
                                    <span class="error-message text-xs text-red-500 mt-1">{{ $errors->first('custom_fields.' . $field['key']) }}</span>
                                @elseif($isChoiceField)
                                    <x-input-label :value="$field['label']" />
                                    @if($field['type'] === 'radiobutton')
                                        <div class="mt-2 space-y-2 border border-gray-200 bg-white p-3">
                                            @foreach(($field['options'] ?? []) as $option)
                                                <label class="flex items-center gap-3 text-sm text-slate-700">
                                                    <input type="radio" name="custom_fields[{{ $field['key'] }}]"
                                                        value="{{ $option }}"
                                                        class="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                        @checked(old('custom_fields.' . $field['key'], $fieldValue) === $option)
                                                        {{ $field['required'] ? 'required' : '' }}>
                                                    <span>{{ $option }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @else
                                        <select id="custom_field_{{ $field['key'] }}" name="custom_fields[{{ $field['key'] }}]" class="mt-1 block w-full border-gray-300 text-sm" {{ $field['required'] ? 'required' : '' }}>
                                            <option value="">Select an option...</option>
                                            @foreach ($field['options'] ?? [] as $option)
                                                <option value="{{ $option }}" @selected(old('custom_fields.' . $field['key'], $fieldValue) === $option)>
                                                    {{ $option }}</option>
                                            @endforeach
                                        </select>
                                    @endif
                                    @if (!empty($field['help_text']))
                                        <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                                    @endif
                                    <span class="error-message text-xs text-red-500 mt-1">{{ $errors->first('custom_fields.' . $field['key']) }}</span>
                                @else
                                    <x-input-label for="custom_field_{{ $field['key'] }}" :value="$field['label']" />
                                    <div class="mt-1 flex items-center border border-gray-300 bg-white px-3 py-2 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                                        <input id="custom_field_{{ $field['key'] }}" name="custom_fields[{{ $field['key'] }}]" type="{{ $inputType }}" value="{{ old('custom_fields.' . $field['key'], $fieldValue) }}" placeholder="{{ $field['placeholder'] ?? '' }}" class="w-full border-0 p-0 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0" {{ $field['required'] ? 'required' : '' }}>
                                    </div>
                                    @if (!empty($field['help_text']))
                                        <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                                    @endif
                                    <span class="error-message text-xs text-red-500 mt-1">{{ $errors->first('custom_fields.' . $field['key']) }}</span>
                                @endif
                            </div>
                        @endforeach
                        {{-- Description --}}
                        <div class="md:col-span-3">
                            <div class="flex items-center justify-between">
                                <x-input-label for="description" value="Description" />
                                <x-speech-to-text target="description" />
                            </div>
                            <textarea id="description" name="description" rows="3"
                                class="mt-1 block w-full rounded-xl border-gray-300 text-sm">{{ old('description', $project->description) }}</textarea>
                        </div>



                        {{-- Team Assignment (Manager & Members) --}}
                        <div>
                             <x-input-label value="Project Manager" />
                             <select name="project_manager_id" class="mt-1 block w-full border-gray-300 text-sm">
                                <option value="">Select Manager...</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('project_manager_id', $project->project_manager_id) == $user->id)>
                                        {{ $user->name }}
                                        [{{ $user->roleRecord?->name ?? \Illuminate\Support\Str::of($user->role)->replace('_', ' ')->headline() }}]
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @php
                            $selectedTeamMemberIds = collect(old('team_member_ids', $project->team_member_ids ?? []))
                                ->map(fn($id) => (string) $id)
                                ->values()
                                ->all();
                        @endphp

                        <div x-data="{
                            open: false,
                            selected: @js($selectedTeamMemberIds),

                            users: @js(
    collect($users)
        ->map(
            fn($user) => [
                'id' => (string) $user->id,
                'name' => $user->name,
                'role' => $user->role,
            ],
        )
        ->values()
        ->all(),
),
                            roleLabel(user) {
                                const role = user?.roleRecord?.name || user?.role || '';
                                return role ? `[${role}]` : '';
                            },
                            toggle(id) {
                                id = id.toString();
                                if (this.selected.includes(id)) {
                                    this.selected = this.selected.filter(item => item !== id);
                                } else {
                                    this.selected.push(id);
                                }
                            },
                            getUserName(id) {
                                return this.users.find(user => user.id === id)?.name || '';
                            },
                            getUserRole(id) {
                                const user = this.users.find(user => user.id === id);
                                return user ? this.roleLabel(user) : '';
                            }
                        }" class="relative">
                            <x-input-label value="Team Members" />
                            <div @click="open = true; $nextTick(() => $refs.searchInput?.focus())"
                                @click.outside="open = false"
                                class="mt-1 min-h-[48px] w-full border border-gray-300 bg-white px-3 py-2 shadow-sm transition hover:border-indigo-400 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <template x-for="id in selected" :key="'selected-'+id">
                                         <span class="inline-flex items-center gap-1 er border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">
                                                <span x-text="getUserName(id)"></span>
                                                <button type="button" @click.stop="toggle(id)" class="text-indigo-400 hover:text-indigo-900">
                                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                                </button>
                                            </span>
                                    </template>
                                    <input x-ref="searchInput" type="text"
                                        class="min-w-[220px] flex-1 border-0 p-1 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                        placeholder="Search and assign team members..." @focus="open = true">
                                </div>
                            </div>

                            <div x-show="open" x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                class="absolute z-50 mt-1 max-h-[410px] w-full overflow-y-auto overscroll-contain border border-gray-200 bg-white shadow-xl"
                                style="display: none;"
                            >
                                @foreach($users as $user)
                                    <button
                                        type="button"
                                        @click="toggle('{{ $user->id }}')"
                                        class="flex w-full items-center justify-between border-b border-gray-50 px-4 py-2.5 text-left text-sm last:border-none hover:bg-indigo-50"
                                        :class="selected.includes('{{ $user->id }}') ? 'bg-indigo-50/60 text-indigo-700' :
                                            'text-slate-700'">
                                        <span class="font-medium">
                                            <span>{{ $user->name }}</span>
                                            <span
                                                class="text-[10px] text-slate-400">[{{ $user->roleRecord?->name ?? \Illuminate\Support\Str::of($user->role)->replace('_', ' ')->headline() }}]</span>
                                        </span>
                                        <span x-show="selected.includes('{{ $user->id }}')" class=" bg-indigo-600 p-0.5">
                                            <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        </span>
                                    </button>
                                @endforeach
                            </div>

                            <template x-for="id in selected" :key="'submit-' + id">
                                <input type="hidden" name="team_member_ids[]" :value="id">
                            </template>
                        </div>
                    </div>

                    {{-- Action Buttons inside the card --}}
                    <div class="mt-8 flex items-center justify-end gap-3 border-t border-slate-100 pt-6">
                        <a href="{{ route('projects.show', $project) }}"
                            class="text-sm font-semibold text-slate-600 hover:text-slate-900">
                            Cancel
                        </a>
                        <x-primary-button type="submit" class="px-8">
                            Save Changes
                        </x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
        <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places">
        </script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const stateSelect = document.getElementById('state_id');
                const citySelect = document.getElementById('city_id');
                const clientNameInput = document.getElementById('client_name');
                const existingClientSearch = document.getElementById('existing_client_search');
                const existingClientSelect = document.getElementById('existing_client_id');
                const existingClientOptions = existingClientSelect ? Array.from(existingClientSelect.options) : [];
                const clientAddressInput = document.getElementById('client_address');
                const clientAddressSuggestions = document.getElementById('client-address-suggestions');
                const cityOptionsByState = @js($cityOptionsByState);
                const clientAddressAutocompleteUrl = clientAddressInput?.dataset.autocompleteUrl || '';
                let clientAddressAutocompleteTimer = null;
                let clientAddressAutocompleteAbortController = null;
                let clientAddressSuggestionsList = [];
                let highlightedClientAddressIndex = -1;
                let suppressClientAddressLookup = false;

                if (!stateSelect || !citySelect) {
                    return;
                }

                function normalizeClientSearch(value) {
                    return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
                }

                function filterExistingClientOptions() {
                    if (!existingClientSelect || !existingClientSearch) {
                        return;
                    }

                    const query = normalizeClientSearch(existingClientSearch.value);

                    existingClientOptions.forEach((option) => {
                        if (!option || option.value === '') {
                            return;
                        }

                        const searchableText = normalizeClientSearch([
                            option.dataset.name,
                            option.dataset.email,
                            option.dataset.mobile,
                            option.dataset.address,
                            option.textContent,
                        ].filter(Boolean).join(' '));

                        const matches = query === '' || searchableText.includes(query);
                        option.hidden = !matches && !option.selected;
                    });
                }

                function renderCities(stateId, selectedCityId = '') {
                    const cities = cityOptionsByState[String(stateId)] || [];
                    citySelect.innerHTML = '<option value="">Select city...</option>';

                    cities.forEach((city) => {
                        const option = document.createElement('option');
                        option.value = String(city.id);
                        option.textContent = city.name;
                        option.selected = String(selectedCityId) === String(city.id);
                        citySelect.appendChild(option);
                    });

                    citySelect.disabled = cities.length === 0;
                }

                function fillClientFieldsFromOption(option) {
                    if (!option) {
                        return;
                    }

                    if (existingClientSearch) {
                        existingClientSearch.value = option.dataset.name || option.textContent.trim();
                        filterExistingClientOptions();
                    }

                    if (clientNameInput) {
                        clientNameInput.value = option.dataset.name || '';
                    }

                    if (clientAddressInput) {
                        clientAddressInput.value = option.dataset.address || '';
                        clientAddressInput.dispatchEvent(new Event('input', {
                            bubbles: true
                        }));
                    }

                    stateSelect.value = option.dataset.stateId || '';
                    stateSelect.dispatchEvent(new Event('change', { bubbles: true }));
                    renderCities(stateSelect.value, option.dataset.cityId || '');
                }

                function resetClientSelection() {
                    if (existingClientSearch) {
                        existingClientSearch.value = '';
                        filterExistingClientOptions();
                    }
                }

                existingClientSearch?.addEventListener('input', filterExistingClientOptions);
                existingClientSearch?.addEventListener('search', filterExistingClientOptions);
                filterExistingClientOptions();

                function escapeHtml(value) {
                    return String(value).replace(/[&<>"']/g, function(char) {
                        return ({
                            '&': '&amp;',
                            '<': '&lt;',
                            '>': '&gt;',
                            '"': '&quot;',
                            "'": '&#039;'
                        })[char];
                    });
                }

                function getClientAddressSuggestionLabel(item) {
                    if (!item || typeof item !== 'object') {
                        return '';
                    }

                    if (typeof item.display_name === 'string' && item.display_name.trim() !== '') {
                        return item.display_name;
                    }

                    const properties = item.properties || {};
                    const parts = [
                        properties.name,
                        properties.street,
                        properties.suburb,
                        properties.district,
                        properties.city,
                        properties.state,
                        properties.country,
                    ].filter((value, index, values) => {
                        const normalized = String(value || '').trim();
                        return normalized !== '' && values.indexOf(value) === index;
                    });

                    return parts.join(', ');
                }

                function normalizeClientAddressSuggestions(payload) {
                    if (Array.isArray(payload)) {
                        return payload;
                    }

                    if (Array.isArray(payload?.features)) {
                        return payload.features;
                    }

                    return [];
                }

                function hideClientAddressSuggestions() {
                    if (!clientAddressSuggestions) {
                        return;
                    }

                    clientAddressSuggestions.classList.add('hidden');
                    clientAddressSuggestions.innerHTML = '';
                    clientAddressSuggestionsList = [];
                    highlightedClientAddressIndex = -1;
                }

                function renderClientAddressSuggestions(results) {
                    if (!clientAddressSuggestions) {
                        return;
                    }

                    clientAddressSuggestionsList = results;
                    highlightedClientAddressIndex = -1;

                    if (!results.length) {
                        clientAddressSuggestions.innerHTML =
                            '<div class="px-4 py-3 text-sm text-slate-500">No matching locations found.</div>';
                        clientAddressSuggestions.classList.remove('hidden');
                        return;
                    }

                    clientAddressSuggestions.innerHTML = results.map((item, index) => {
                        const displayName = escapeHtml(getClientAddressSuggestionLabel(item));
                        return `
                            <button
                                type="button"
                                class="client-address-suggestion-item flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
                                data-address-index="${index}"
                                role="option"
                                aria-selected="false"
                            >
                                <span class="mt-0.5 text-slate-400" aria-hidden="true">
                                    <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 1.75a5.75 5.75 0 0 0-5.75 5.75c0 4.256 4.518 9.388 5.102 10.037a.875.875 0 0 0 1.296 0c.584-.649 5.102-5.781 5.102-10.037A5.75 5.75 0 0 0 10 1.75Zm0 7.5a1.75 1.75 0 1 1 0-3.5 1.75 1.75 0 0 1 0 3.5Z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                                <span class="min-w-0 text-sm leading-5 text-slate-700">${displayName}</span>
                            </button>
                        `;
                    }).join('');

                    clientAddressSuggestions.classList.remove('hidden');
                }

                function setHighlightedClientAddressSuggestion(index) {
                    if (!clientAddressSuggestions || !clientAddressSuggestionsList.length) {
                        return;
                    }

                    const items = clientAddressSuggestions.querySelectorAll('.client-address-suggestion-item');
                    items.forEach((item, itemIndex) => {
                        const isActive = itemIndex === index;
                        item.classList.toggle('bg-slate-50', isActive);
                        item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });
                    highlightedClientAddressIndex = index;
                }

                function applyClientAddressSuggestion(index) {
                    const selectedSuggestion = clientAddressSuggestionsList[index];
                    if (!selectedSuggestion || !clientAddressInput) {
                        return;
                    }

                    suppressClientAddressLookup = true;
                    clientAddressInput.value = getClientAddressSuggestionLabel(selectedSuggestion);
                    hideClientAddressSuggestions();

                    window.setTimeout(() => {
                        suppressClientAddressLookup = false;
                    }, 0);
                }

                async function fetchClientAddressSuggestions(query) {
                    if (!clientAddressAutocompleteUrl || !clientAddressSuggestions) {
                        return;
                    }

                    if (clientAddressAutocompleteAbortController) {
                        clientAddressAutocompleteAbortController.abort();
                    }

                    clientAddressAutocompleteAbortController = new AbortController();

                    try {
                        const response = await fetch(
                        `${clientAddressAutocompleteUrl}${encodeURIComponent(query)}`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            },
                            signal: clientAddressAutocompleteAbortController.signal
                        });

                        if (!response.ok) {
                            hideClientAddressSuggestions();
                            return;
                        }

                        const results = await response.json();
                        renderClientAddressSuggestions(normalizeClientAddressSuggestions(results));
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            hideClientAddressSuggestions();
                        }
                    }
                }

                function queueClientAddressAutocompleteLookup() {
                    if (!clientAddressInput || suppressClientAddressLookup) {
                        return;
                    }

                    const query = clientAddressInput.value.trim();

                    if (clientAddressAutocompleteTimer) {
                        window.clearTimeout(clientAddressAutocompleteTimer);
                    }

                    if (query.length < 3) {
                        hideClientAddressSuggestions();
                        return;
                    }

                    clientAddressAutocompleteTimer = window.setTimeout(() => {
                        fetchClientAddressSuggestions(query);
                    }, 350);
                }

                stateSelect.addEventListener('change', function() {
                    renderCities(this.value);
                });

                clientAddressInput?.addEventListener('input', queueClientAddressAutocompleteLookup);
                clientAddressInput?.addEventListener('keydown', function(event) {
                    if (clientAddressSuggestions?.classList.contains('hidden') || !clientAddressSuggestionsList
                        .length) {
                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        const nextIndex = highlightedClientAddressIndex < clientAddressSuggestionsList.length -
                            1 ?
                            highlightedClientAddressIndex + 1 :
                            0;
                        setHighlightedClientAddressSuggestion(nextIndex);
                        return;
                    }

                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        const previousIndex = highlightedClientAddressIndex > 0 ?
                            highlightedClientAddressIndex - 1 :
                            clientAddressSuggestionsList.length - 1;
                        setHighlightedClientAddressSuggestion(previousIndex);
                        return;
                    }

                    if (event.key === 'Enter' && highlightedClientAddressIndex >= 0) {
                        event.preventDefault();
                        applyClientAddressSuggestion(highlightedClientAddressIndex);
                        return;
                    }

                    if (event.key === 'Escape') {
                        hideClientAddressSuggestions();
                    }
                });

                clientAddressSuggestions?.addEventListener('mousedown', function(event) {
                    const button = event.target.closest('[data-address-index]');
                    if (!button) {
                        return;
                    }

                    event.preventDefault();
                    applyClientAddressSuggestion(Number(button.dataset.addressIndex));
                });

                document.addEventListener('click', function(event) {
                    if (!clientAddressInput || !clientAddressSuggestions) {
                        return;
                    }

                    if (event.target === clientAddressInput || clientAddressSuggestions.contains(event
                        .target)) {
                        return;
                    }

                    hideClientAddressSuggestions();
                });

                const projectLatitudeInput = document.getElementById('latitude');
                const projectLongitudeInput = document.getElementById('longitude');

                if (window.google?.maps?.places && clientAddressInput && projectLatitudeInput &&
                    projectLongitudeInput) {
                    const autocomplete = new google.maps.places.Autocomplete(clientAddressInput, {
                        componentRestrictions: {
                            country: ['in']
                        },
                        fields: ['formatted_address', 'geometry'],
                    });

                    autocomplete.addListener('place_changed', function() {
                        const place = autocomplete.getPlace();
                        if (!place.geometry) {
                            return;
                        }

                        clientAddressInput.value = place.formatted_address || clientAddressInput.value;
                        projectLatitudeInput.value = place.geometry.location.lat();
                        projectLongitudeInput.value = place.geometry.location.lng();
                    });
                }

                const initialSelectedClient = existingClientSelect?.selectedOptions?.[0];
                if (initialSelectedClient && existingClientSelect.value) {
                    fillClientFieldsFromOption(initialSelectedClient);
                }

                existingClientSelect?.addEventListener('change', function() {
                    const option = this.selectedOptions?.[0];
                    if (!option || !this.value) {
                        resetClientSelection();
                        return;
                    }

                    fillClientFieldsFromOption(option);
                });

                renderCities(stateSelect.value, @js(old('city_id', $project->city_id)));
            });
        </script>
    @endpush
</x-app-layout>
