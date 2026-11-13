<x-app-layout>
    <x-slot name="header">
        <div class="border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">{{ __('Create New Project') }}</h2>
            <p class="mt-1 text-sm text-slate-500">Clean project form with compact spacing and responsive grid.</p>
        </div>
    </x-slot>

    <div id="project-create-page" class="py-4">
    <div class="w-full sm:px-6 lg:px-8">
        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                <form id="project-create-form" method="POST" action="{{ route('projects.store') }}" novalidate class="space-y-5">
                    @csrf
                    @php
                        $cityOptionsByState = $states->mapWithKeys(function ($state) {
                            return [
                                $state->id => $state->cities->map(fn ($city) => [
                                    'id' => $city->id,
                                    'name' => $city->name,
                                ])->values(),
                            ];
                        });
                        $selectedStateId = old('state_id');
                        $selectedCityId = old('city_id');
                        $initialCityOptions = collect($cityOptionsByState[$selectedStateId] ?? [])->map(fn ($city) => [
                            'id' => $city['id'],
                            'name' => $city['name'],
                        ])->values();
                    @endphp
           <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
    <div class="md:col-span-3">
        <x-client-picker
            :clients="$clients ?? []"
            :selected="old('existing_client_id', request()->query('existing_client_id'))"
            label="Select Existing Client"
            helper-text="Search and select exactly one client, or keep New client and fill the form manually."
        />
    </div>

    <div>
        <x-input-label for="name" value="Project Name" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" :value="old('name')" required minlength="2" maxlength="191" placeholder="e.g. Modern Villa Interior" />
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>

    <div>
        <x-input-label for="client_name" value="Client" />
        <x-text-input id="client_name" name="client_name" class="mt-1 block w-full" :value="old('client_name', request()->query('client_name'))" required minlength="2" maxlength="191" placeholder="Full name of the client" />
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>

    <div>
        <x-input-label for="client_email" value="Client Email" />
        <x-text-input id="client_email" name="client_email" type="email" class="mt-1 block w-full" :value="old('client_email', request()->query('client_email'))" placeholder="client@example.com" />
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>

    <x-searchable-select
        name="state_id"
        id="state_id"
        label="State"
        :options="$states->map(fn ($state) => ['id' => $state->id, 'name' => $state->name])->values()"
        :selected="$selectedStateId"
        placeholder="Search state..."
        empty-label="Select state..."
        helper-text="Search and select the state."
    />

    <x-searchable-select
        name="city_id"
        id="city_id"
        label="City"
        :options="$initialCityOptions"
        :selected="$selectedCityId"
        placeholder="Search city..."
        empty-label="Select city..."
        helper-text="Select a state first, then search the matching cities."
        :disabled="blank($selectedStateId)"
    />

                    <div>
                        <x-input-label for="current_stage" value="Current Stage" />
                        <select id="current_stage" name="current_stage" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            @foreach($projectStages as $stageKey => $stageData)
                                <option value="{{ $stageKey }}" @selected(old('current_stage', 'new-project') === $stageKey)>{{ $stageData['name'] }}</option>
                            @endforeach
                        </select>
                        <span class="error-message text-xs text-red-500 mt-1"></span>
                    </div>

    <div>
        <x-input-label for="reference_no" value="Reference Number" />
        <x-text-input id="reference_no" name="reference_no" class="mt-1 block w-full" :value="old('reference_no', $nextReferenceNo)" placeholder="REF-2024-001" readonly/>
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>

    <div>
        <x-input-label for="lead_id" value="Linked Lead" />
        <select id="lead_id" name="lead_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
            <option value="" selected>-- Link an inquiry/lead --</option>
            @foreach($leads as $lead)
                <option value="{{ $lead->id }}" @selected((string) old('lead_id') === (string) $lead->id)>{{ $lead->client_name }} ({{ $lead->status?->name }})</option>
            @endforeach
        </select>
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>

    <div>
        <x-input-label for="contractor_id" value="Contractor (Optional)" />
        <select id="contractor_id" name="contractor_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
            <option value="">-- No contractor --</option>
            @foreach($contractors ?? [] as $contractor)
                <option value="{{ $contractor->id }}" @selected((string) old('contractor_id') === (string) $contractor->id)>{{ $contractor->name }}</option>
            @endforeach
        </select>
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>

    <div x-data="{
        open: false,
        selected: @js(collect(old('team_member_ids', []))->map(fn($id) => (string) $id)->values()),
        users: @js($users),
        roleLabel(user) {
            const role = user?.roleRecord?.name || user?.role || '';
            return role ? `[${role}]` : '';
        },
        toggle(id) {
            id = id.toString();
            if (this.selected.includes(id)) {
                this.selected = this.selected.filter(i => i !== id);
            } else {
                this.selected.push(id);
            }
        },
        getUserName(id) {
            let user = this.users.find(u => u.id == id);
            return user ? user.name : '';
        },
        getUserRole(id) {
            let user = this.users.find(u => u.id == id);
            return user ? this.roleLabel(user) : '';
        }
    }" class="relative">
        <x-input-label :value="__('Team Members')" />
        <div
            @click="open = !open"
            @click.outside="open = false"
            class="mt-1 min-h-[42px] w-full flex flex-wrap gap-2 border border-gray-300 bg-white p-2 shadow-sm cursor-pointer transition hover:border-indigo-400 focus-within:ring-2 focus-within:ring-indigo-500"
        >
            <template x-if="selected.length === 0">
                <span class="text-gray-400 text-sm py-1 ml-1">Search and assign team members...</span>
            </template>

            <template x-for="id in selected" :key="id">
                <span class="inline-flex items-center gap-1 px-2 py-1 bg-indigo-50 text-indigo-700 text-xs font-semibold border border-indigo-200">
                    <span>
                        <span x-text="getUserName(id)"></span>
                        <span class="text-[10px] text-indigo-400" x-text="getUserRole(id)"></span>
                    </span>
                    <button type="button" @click.stop="toggle(id)" class="text-indigo-400 hover:text-indigo-900">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </span>
            </template>

            <template x-for="id in selected" :key="'submit-'+id">
                <input type="hidden" name="team_member_ids[]" :value="id">
            </template>
            <span class="error-message text-xs text-red-500 mt-1"></span>
        </div>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 transform scale-95"
            x-transition:enter-end="opacity-100 transform scale-100"
            class="absolute z-50 mt-1 w-full max-h-[410px] overflow-y-auto overscroll-contain border border-gray-200 bg-white shadow-xl"
            style="display: none;"
        >
            @foreach($users as $user)
                <div
                    @click="toggle('{{ $user->id }}')"
                    class="px-4 py-2.5 text-sm cursor-pointer flex items-center justify-between border-b border-gray-50 last:border-none hover:bg-indigo-50"
                    :class="selected.includes('{{ $user->id }}') ? 'bg-indigo-50/50 text-indigo-700' : 'text-gray-700'"
                >
                    <div class="flex flex-col">
                        <span class="font-medium" :class="selected.includes('{{ $user->id }}') ? 'text-indigo-800' : ''">
                            {{ $user->name }} <span class="text-[10px] text-gray-400">[{{ $user->roleRecord?->name ?? \Illuminate\Support\Str::of($user->role)->replace('_', ' ')->headline() }}]</span>
                        </span>
                    </div>
                    <div class="flex items-center">
                        <template x-if="selected.includes('{{ $user->id }}')">
                            <span class="bg-indigo-600 p-0.5">
                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                            </span>
                        </template>
                    </div>
                </div>
            @endforeach
        </div>
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>

    <div>
        <x-input-label for="status" value="Overall Status" />
        <select id="status" name="status" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
            @foreach($statusOptions as $statusKey => $statusLabel)
                <option value="{{ $statusKey }}" @selected(old('status', 'on_track') === $statusKey)>{{ $statusLabel }}</option>
            @endforeach
        </select>
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>

   <div>
    <x-input-label for="start_date" value="Start Date" />
    <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" />
    <span class="error-message text-xs text-red-500 mt-1"></span>
</div>

<div>
    <x-input-label for="end_date" value="Deadline" />
    <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date')" />
    <span class="error-message text-xs text-red-500 mt-1"></span>
</div>
        @foreach($projectCustomFields ?? [] as $field)
        @php
            $fieldValue = data_get(old('custom_fields', []), $field['key']);
            if ($fieldValue === null) {
                $fieldValue = $field['type'] === 'checkbox' ? false : '';
            }
            $isTextarea = $field['type'] === 'textarea';
            $isChoiceField = in_array($field['type'], ['dropdown', 'radiobutton'], true);
            $inputType = in_array($field['type'], ['email', 'number', 'date'], true) ? $field['type'] : 'text';
        @endphp

        <div @class(['md:col-span-3' => $isTextarea || $field['type'] === 'checkbox'])>
            @if($field['type'] === 'checkbox')
                <label class="flex items-start gap-3 border border-slate-200 bg-slate-50 px-4 py-3">
                    <input type="hidden" name="custom_fields[{{ $field['key'] }}]" value="0">
                    <input id="custom_field_{{ $field['key'] }}" name="custom_fields[{{ $field['key'] }}]" type="checkbox" value="1" class="mt-1 border-slate-300 text-indigo-600 focus:ring-indigo-500" @checked((bool) $fieldValue)>
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">{{ $field['label'] }} @if($field['required']) <span class="text-rose-500">*</span> @endif</span>
                        @if(!empty($field['help_text']))
                            <span class="mt-1 block text-xs text-slate-500">{{ $field['help_text'] }}</span>
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
                <textarea id="custom_field_{{ $field['key'] }}" name="custom_fields[{{ $field['key'] }}]" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" placeholder="{{ $field['placeholder'] ?? '' }}" {{ $field['required'] ? 'required' : '' }}>{{ old('custom_fields.' . $field['key'], $fieldValue) }}</textarea>
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
                                <input type="radio" name="custom_fields[{{ $field['key'] }}]" value="{{ $option }}" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('custom_fields.' . $field['key'], $fieldValue) === $option) {{ $field['required'] ? 'required' : '' }}>
                                <span>{{ $option }}</span>
                            </label>
                        @endforeach
                    </div>
                @else
                    <select id="custom_field_{{ $field['key'] }}" name="custom_fields[{{ $field['key'] }}]" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" {{ $field['required'] ? 'required' : '' }}>
                        <option value="">Select an option...</option>
                        @foreach(($field['options'] ?? []) as $option)
                            <option value="{{ $option }}" @selected(old('custom_fields.' . $field['key'], $fieldValue) === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                @endif
                @if(!empty($field['help_text']))
                    <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                @endif
                <span class="error-message text-xs text-red-500 mt-1">{{ $errors->first('custom_fields.' . $field['key']) }}</span>
            @else
                <x-input-label for="custom_field_{{ $field['key'] }}" :value="$field['label']" />
                <div class="custom-field-error-group mt-1">
                    <div class="flex items-center border border-gray-300 bg-white px-3 py-2 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                        <input
                            id="custom_field_{{ $field['key'] }}"
                            name="custom_fields[{{ $field['key'] }}]"
                            type="{{ $inputType }}"
                            value="{{ old('custom_fields.' . $field['key'], $fieldValue) }}"
                            placeholder="{{ $field['placeholder'] ?? '' }}"
                            class="w-full border-0 p-0 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0 bg-transparent"
                            {{ $field['required'] ? 'required' : '' }}
                        >
                    </div>
                </div>
                @if(!empty($field['help_text']))
                    <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                @endif
                <span class="error-message text-xs text-red-500 mt-1">{{ $errors->first('custom_fields.' . $field['key']) }}</span>
            @endif
        </div>
    @endforeach

    <div class="md:col-span-3">
        <x-input-label for="client_address" value="Client Address" />
        <div class="relative mt-1">
            <textarea id="client_address" name="client_address" rows="2" minlength="5" maxlength="1000" placeholder="Street name, City, State, ZIP..." class="block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" autocomplete="off" data-location-autocomplete="project-client-address" data-autocomplete-url="{{ config('services.location_autocomplete.url') }}">{{ old('client_address', request()->query('client_address')) }}</textarea>
            <div
                id="client-address-suggestions"
                class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                role="listbox"
                aria-label="Client address suggestions"
            ></div>
        </div>
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>
    <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">

    <div class="md:col-span-3">
        <div class="flex items-center justify-between">
            <x-input-label for="description" value="Description" />
            <x-speech-to-text target="description" />
        </div>
        <textarea id="description" name="description" rows="4" minlength="2" maxlength="2000" placeholder="Briefly describe project requirements and goals..." class="mt-1 block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">{{ old('description') }}</textarea>
        <span class="error-message text-xs text-red-500 mt-1"></span>
    </div>
</div>



                <div class="flex justify-end border-t border-slate-200 pt-4">
                    <x-primary-button id="project-create-submit" type="submit" class="px-5">Create Project</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>

    {{-- @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const startInput = document.getElementById('start_date');
            const endInput = document.getElementById('end_date');
            const startError = document.getElementById('start-date-error');
            const endError = document.getElementById('end-date-error');
            const requiredFields = [
                { id: 'name', label: 'Project Name' },
                { id: 'client_name', label: 'Client' },
            ];

            function clearDateErrorsIfValid() {
                if (!startInput || !endInput) return;
                const start = startInput.value ? new Date(startInput.value) : null;
                const end = endInput.value ? new Date(endInput.value) : null;
                if (start && end && end >= start) {
                    if (startError) { startError.innerHTML = ''; startError.classList.add('hidden'); }
                    if (endError) { endError.innerHTML = ''; endError.classList.add('hidden'); }
                }
            }

            startInput?.addEventListener('change', clearDateErrorsIfValid);
            endInput?.addEventListener('change', clearDateErrorsIfValid);

            // Custom required messages
            requiredFields.forEach(({ id, label }) => {
                const el = document.getElementById(id);
                if (!el) return;
                el.addEventListener('invalid', function (e) {
                    e.target.setCustomValidity(`${label} is required.`);
                });
                el.addEventListener('input', function (e) {
                    e.target.setCustomValidity('');
                });
            });
            const emailInput = document.getElementById('client_name') ? document.getElementById('email') : document.getElementById('client_email');
            const clientEmail = document.getElementById('client_name') ? document.querySelector('#client_name') : null;
            const emailEl = document.getElementById('client_name') ? document.querySelector('#client_name') : null;
            const emailField = document.getElementById('client_name') ? document.querySelector('#client_name') : null;
            const emailInputEl = document.getElementById('client_name') ? document.querySelector('#client_name') : document.getElementById('email');
            const emailElInput = document.getElementById('email');
            if (emailElInput) {
                emailElInput.addEventListener('invalid', function (e) {
                    e.target.setCustomValidity('Please enter a valid email address.');
                });
                emailElInput.addEventListener('input', function (e) {
                    e.target.setCustomValidity('');
                });
            }
        });
    </script>
    @endpush --}}
@push('scripts')
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/jquery.validate.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.5/additional-methods.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    const today = new Date().toISOString().split('T')[0];
    const cityOptionsByState = @js($cityOptionsByState);
    const clientAddressInput = document.getElementById('client_address');
    const clientAddressSuggestions = document.getElementById('client-address-suggestions');
    const clientAddressAutocompleteUrl = clientAddressInput?.dataset.autocompleteUrl || '';
    let clientAddressAutocompleteTimer = null;
    let clientAddressAutocompleteAbortController = null;
    let clientAddressSuggestionsList = [];
    let highlightedClientAddressIndex = -1;
    let suppressClientAddressLookup = false;
    $('#start_date').attr('min', today);
    $('#end_date').attr('min', today);

    function renderCities(stateId, selectedCityId = '') {
        const citySelect = document.getElementById('city_id');
        if (!citySelect) return;

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

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (char) {
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
            clientAddressSuggestions.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500">No matching locations found.</div>';
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
        $(clientAddressInput).valid();

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
            const response = await fetch(`${clientAddressAutocompleteUrl}${encodeURIComponent(query)}`, {
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

    $('#state_id').on('change', function() {
        renderCities(this.value);
    });

    clientAddressInput?.addEventListener('input', queueClientAddressAutocompleteLookup);
    clientAddressInput?.addEventListener('keydown', function (event) {
        if (clientAddressSuggestions?.classList.contains('hidden') || !clientAddressSuggestionsList.length) {
            return;
        }

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            const nextIndex = highlightedClientAddressIndex < clientAddressSuggestionsList.length - 1
                ? highlightedClientAddressIndex + 1
                : 0;
            setHighlightedClientAddressSuggestion(nextIndex);
            return;
        }

        if (event.key === 'ArrowUp') {
            event.preventDefault();
            const previousIndex = highlightedClientAddressIndex > 0
                ? highlightedClientAddressIndex - 1
                : clientAddressSuggestionsList.length - 1;
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

    clientAddressSuggestions?.addEventListener('mousedown', function (event) {
        const button = event.target.closest('[data-address-index]');
        if (!button) {
            return;
        }

        event.preventDefault();
        applyClientAddressSuggestion(Number(button.dataset.addressIndex));
    });

    document.addEventListener('click', function (event) {
        if (!clientAddressInput || !clientAddressSuggestions) {
            return;
        }

        if (event.target === clientAddressInput || clientAddressSuggestions.contains(event.target)) {
            return;
        }

        hideClientAddressSuggestions();
    });

    const projectLatitudeInput = document.getElementById('latitude');
    const projectLongitudeInput = document.getElementById('longitude');

    if (window.google?.maps?.places && clientAddressInput && projectLatitudeInput && projectLongitudeInput) {
        const autocomplete = new google.maps.places.Autocomplete(clientAddressInput, {
            componentRestrictions: { country: ['in'] },
            fields: ['formatted_address', 'geometry'],
        });

        autocomplete.addListener('place_changed', function () {
            const place = autocomplete.getPlace();
            if (!place.geometry) {
                return;
            }

            clientAddressInput.value = place.formatted_address || clientAddressInput.value;
            projectLatitudeInput.value = place.geometry.location.lat();
            projectLongitudeInput.value = place.geometry.location.lng();
        });
    }

    renderCities($('#state_id').val(), @js(old('city_id')));

    // Update Deadline min date whenever Start Date changes
    $('#start_date').on('change', function() {
        if (this.value) {
            $('#end_date').attr('min', this.value);
        }
    });
    // 1. Custom Email Method (Strict .com, .in, etc.)
    $.validator.addMethod("strictEmail", function(value, element) {
        return this.optional(element) || /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.(com|in|net|org|edu|gov|io|co)$/.test(value);
    }, "Please enter a valid email address (e.g. .com, .in)");

    // 2. Future Date Method
    $.validator.addMethod("futureDate", function(value, element) {
        if (!value) return true;
        var now = new Date();
        now.setHours(0, 0, 0, 0);
        return new Date(value) >= now;
    }, "Date must be today or in the future");

    // 3. Initialize Validation
    $("#project-create-form").validate({
        errorClass: "text-red-600 text-xs mt-1",
        errorElement: "span",

        // Rules for your fields
        rules: {
            name: {
                required: true,
                minlength: 2,
                maxlength: 191,
                normalizer: function(value) {
                    return $.trim(value);
                }
            },
            client_name: {
                required: true,
                minlength: 2,
                maxlength: 191,
                normalizer: function(value) {
                    return $.trim(value);
                }
            },
            client_email: {
                required: false,
                maxlength: 191,
                strictEmail: true
            },
            client_address: {
                required: false,
                minlength: 5,
                maxlength: 1000,
                normalizer: function(value) {
                    return $.trim(value);
                }
            },
            state_id: {
                required: true
            },
            city_id: {
                required: true
            },
            description: {
                required: false,
                minlength: 2,
                maxlength: 2000,
                normalizer: function(value) {
                    return $.trim(value);
                }
            },
            start_date: {
                required: true,
                futureDate: true
            },
            end_date: {
                required: true,
                futureDate: true
            }
        },

        // Custom Messages
        messages: {
            name: {
                required: "Project name is required.",
                minlength: "Project name must be at least 2 characters.",
                maxlength: "Project name may not be greater than 191 characters."
            },
            client_name: {
                required: "Client is required.",
                minlength: "Client must be at least 2 characters.",
                maxlength: "Client may not be greater than 191 characters."
            },
            client_email: {
                maxlength: "Client email may not be greater than 191 characters.",
                strictEmail: "Please enter a valid email address."
            },
            client_address: {
                minlength: "Client address must be at least 5 characters.",
                maxlength: "Client address may not be greater than 1000 characters."
            },
            state_id: {
                required: "State is required."
            },
            city_id: {
                required: "City is required."
            },
            description: {
                minlength: "Description must be at least 2 characters.",
                maxlength: "Description may not be greater than 2000 characters."
            },
            start_date: {
                required: "Start date is required.",
                futureDate: "The start date cannot be in the past."
            },
            end_date: {
                required: "Deadline is required.",
                futureDate: "Deadline cannot be in the past."
            }
        },

        // Placement logic to match your UI
        errorPlacement: function(error, element) {
            // Prefer the dedicated error slot next to fixed fields and custom field wrappers.
            let container = element.siblings(".error-message");
            if (!container.length) {
                container = element.closest(".custom-field-error-group").siblings(".error-message");
            }
            if (!container.length) {
                container = element.parent().find(".error-message").first();
            }
            if (container.length) {
                container.empty();
                error.appendTo(container);
            } else {
                error.insertAfter(element);
            }
        },

        // Submit Handler
        submitHandler: function(form) {
            // Final Date Check: End Date must be after Start Date
            const start = new Date($('#start_date').val());
            const end = new Date($('#end_date').val());

            if (end < start) {
                Swal.fire({
                    icon: 'error',
                    title: 'Date Logic Error',
                    text: 'Deadline cannot be earlier than the Start Date.',
                    confirmButtonColor: '#4f46e5'
                });
                return false;
            }

            form.submit();
        },

        invalidHandler: function(event, validator) {
            // Optional: Show a quick SweetAlert if the user misses fields
            Swal.fire({
                icon: 'error',
                title: 'Check Form',
                text: 'Please fix the highlighted errors before submitting.',
                confirmButtonColor: '#4f46e5',
                timer: 2000,
                showConfirmButton: false
            });
        }
    });

    // Client Selection Auto-fill Logic (Keep your existing logic here)
    const existingClientSearch = document.getElementById('existing_client_search');
    const existingClientSelect = document.getElementById('existing_client_id');
    const existingClientOptions = existingClientSelect ? Array.from(existingClientSelect.options) : [];

    function normalizeClientSearch(value) {
        return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
    }

    function filterExistingClientOptions() {
        if (!existingClientSearch || !existingClientSelect) {
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

    existingClientSearch?.addEventListener('input', filterExistingClientOptions);
    existingClientSearch?.addEventListener('search', filterExistingClientOptions);
    filterExistingClientOptions();

    $('#existing_client_id').on('change', function() {
        const option = $(this).find(':selected')[0];
        if (this.value) {
            if (existingClientSearch && option) {
                existingClientSearch.value = $(option).data('name') || option.textContent.trim();
                filterExistingClientOptions();
            }
            $('#client_name').val($(option).data('name')).valid(); // .valid() triggers re-validation
            $('#client_email').val($(option).data('email')).valid();
            $('#client_address').val($(option).data('address')).valid();
            hideClientAddressSuggestions();
            const stateId = String($(option).data('stateId') || '');
            const stateSelect = document.getElementById('state_id');
            if (stateSelect) {
                stateSelect.value = stateId;
                stateSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
            renderCities($('#state_id').val(), String($(option).data('cityId') || ''));
            $('#city_id').valid();
        } else {
            if (existingClientSearch) {
                existingClientSearch.value = '';
                filterExistingClientOptions();
            }
            $('#client_name, #client_email, #client_address, #state_id, #city_id').val('');
            hideClientAddressSuggestions();
            renderCities('', '');
        }
    });
});
</script>
@endpush

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('project-create-form');
            if (!form) return;

            const showAlert = (title, message) => {
                if (typeof Swal === 'undefined') {
                    alert(message);
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title,
                    text: message,
                    confirmButtonColor: '#4f46e5',
                    customClass: { popup: 'rounded-none' },
                });
            };

            form.addEventListener('submit', function (e) {
                const requiredFields = Array.from(form.querySelectorAll('[required]'))
                    .filter((el) => !el.disabled)
                    .filter((el) => !['hidden', 'button', 'submit'].includes(el.type));

                const missing = requiredFields.find((el) => String(el.value || '').trim() === '');
                if (missing) {
                    e.preventDefault();
                    const label = form.querySelector(`label[for="${missing.id}"]`);
                    showAlert('Required Fields', `${label?.textContent?.trim() || missing.name || 'This field'} is required.`);
                    missing.focus();
                    return;
                }
            }, true);
        });
    </script>

</x-app-layout>
