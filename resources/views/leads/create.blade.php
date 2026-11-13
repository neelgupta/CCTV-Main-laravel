<x-app-layout>
    <x-slot name="header">
        <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <h2 class="text-2xl font-semibold leading-tight text-slate-950">
                {{ __('Create New Lead') }}
            </h2>
            {{-- <p class="mt-1 text-sm text-slate-500">Compact lead form with better spacing and less vertical scroll.</p> --}}
        </div>
    </x-slot>

    <div class="py-4 sm:py-5">
        <div class="border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                <form id="lead-form" method="POST" action="{{ route('leads.store') }}" data-allow-multi-submit="1" class="space-y-4 sm:space-y-5">
                    @csrf
                    @php
                        $defaultInitialAssigneeIds = ! empty($company?->default_lead_assignee_id)
                            ? [(int) $company->default_lead_assignee_id]
                            : [auth()->id()];
                        $assignedUserIds = collect(old('assigned_to', $defaultInitialAssigneeIds))
                            ->filter()
                            ->map(fn ($id) => (int) $id)
                            ->unique()
                            ->values()
                            ->all();
                        $cityOptionsByState = $states->mapWithKeys(function ($state) {
                            return [
                                $state->id => $state->cities->map(fn ($city) => [
                                    'id' => $city->id,
                                    'name' => $city->name,
                                ])->values(),
                            ];
                        });
                        $selectedStateId = old('state_id', $defaultStateId);
                        $selectedCityId = old('city_id', $defaultCityId);
                        $showLeadGstField = $company?->show_lead_gst_field ?? true;
                        $showLeadPanField = $company?->show_lead_pan_field ?? true;
                        $selectedLeadSource = old('source');
                        $leadSourceReferralMap = collect($leadSources ?? [])
                            ->mapWithKeys(fn ($leadSource) => [
                                ($leadSource['name'] ?? '') => (bool) ($leadSource['show_referral_person_name'] ?? false),
                            ])
                            ->filter(fn ($value, $key) => filled($key))
                            ->all();
                        $showReferralPersonField = filled($selectedLeadSource) && ($leadSourceReferralMap[$selectedLeadSource] ?? false);
                        $leadCustomFields = collect($company?->normalizedLeadCustomFields() ?? []);
                        $customFieldValues = old('custom_fields', []);
                        $defaultStatus = $statuses->firstWhere('id', $defaultStatusId);
                    @endphp

                <div class="grid grid-cols-1 gap-4 md:grid-cols-3 sm:gap-5">
                    <div class="md:col-span-3 min-w-0">
                        <x-client-picker
                            :clients="$clients ?? []"
                            :selected="old('existing_client_id', request()->query('existing_client_id') ?: '')"
                            label="Select Existing Client"
                            helper-text="Search and select exactly one client, or keep New client and type the name below."
                        />
                    </div>

                    <div>
                        <x-input-label for="client_name" :value="__('Client Name')" />
                        <x-text-input id="client_name" class="mt-1 block w-full" type="text" name="client_name" :value="old('client_name', request()->query('client_name'))" placeholder="Enter client's full name" required autofocus />
                        <x-input-error :messages="$errors->get('client_name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('Email')" />
                        <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email', request()->query('email'))" placeholder="example@domain.com" />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="mobile" :value="__('Mobile')" />
                        <x-text-input id="mobile" class="mt-1 block w-full rounded-xl" type="tel" name="mobile" :value="old('mobile', request()->query('mobile'))" placeholder="0000000000" inputmode="numeric" maxlength="10" pattern="[0-9]{10}" autocomplete="off" />
                        <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
                    </div>

                    @if($showLeadGstField)
                        <div>
                            <x-input-label for="gst_number" :value="__('GST Number')" />
                            <x-text-input id="gst_number" class="mt-1 block w-full" type="text" name="gst_number" :value="old('gst_number')" placeholder="22AAAAA0000A1Z5" />
                            <x-input-error :messages="$errors->get('gst_number')" class="mt-2" />
                        </div>
                    @endif

                    @if($showLeadPanField)
                        <div>
                            <x-input-label for="pan_number" :value="__('PAN Number')" />
                            <x-text-input id="pan_number" class="mt-1 block w-full" type="text" name="pan_number" :value="old('pan_number')" placeholder="AAAAA1234A" />
                            <x-input-error :messages="$errors->get('pan_number')" class="mt-2" />
                        </div>
                    @endif

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
                        :options="collect()"
                        :selected="$selectedCityId"
                        placeholder="Search city..."
                        empty-label="Select city..."
                        helper-text="Select a state first, then search the matching cities."
                        :disabled="blank($selectedStateId)"
                    />

                    <div>
                        <x-input-label for="lead_category_id" :value="__('Lead Category')" />
                        <select id="lead_category_id" name="lead_category_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            <option value="" disabled selected>Select a category...</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('lead_category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('lead_category_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="lead_status_display" :value="__('Status')" />
                        <input type="hidden" id="lead_status_id" name="lead_status_id" value="{{ $defaultStatusId }}">
                        <x-text-input id="lead_status_display" class="mt-1 block w-full cursor-not-allowed bg-slate-100 text-slate-600" type="text" :value="$defaultStatus?->name ?? __('New Lead')" disabled />
                        <x-input-error :messages="$errors->get('lead_status_id')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="priority" :value="__('Priority')" />
                        <select id="priority" name="priority" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            <option value="" disabled>Select priority...</option>
                            <option value="low" {{ old('priority') == 'low' ? 'selected' : 'selected' }}>Low</option>
                            <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                        </select>
                        <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="estimated_value" :value="__('Estimated Value (₹)')" />
                        <x-text-input id="estimated_value" class="mt-1 block w-full" type="number" step="0.01" name="estimated_value" :value="old('estimated_value', 0)" placeholder="0.00" />
                        <x-input-error :messages="$errors->get('estimated_value')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="next_follow_up" :value="__('Next Follow Up')" />
                        <x-text-input id="next_follow_up" class="mt-1 block w-full" type="date" name="next_follow_up" :value="old('next_follow_up')" placeholder="Select date" />
                        <x-input-error :messages="$errors->get('next_follow_up')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="source" :value="__('Lead Source')" />
                        <select id="source" name="source" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                            <option value="">Select lead source...</option>
                            @foreach($leadSources ?? [] as $leadSource)
                                <option value="{{ $leadSource['name'] }}" @selected($selectedLeadSource === $leadSource['name'])>{{ $leadSource['name'] }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('source')" class="mt-2" />
                    </div>

                    <div id="referral-person-name-wrapper" @class(['hidden' => ! $showReferralPersonField])>
                        <x-input-label for="referral_person_name" :value="__('Referral Person Name')" />
                        <x-text-input id="referral_person_name" class="mt-1 block w-full" type="text" name="referral_person_name" :value="old('referral_person_name')" placeholder="Enter referral person name" />
                        <x-input-error :messages="$errors->get('referral_person_name')" class="mt-2" />
                    </div>

                    @foreach($leadCustomFields as $field)
                        @php
                            $fieldValue = data_get($customFieldValues, $field['key']);
                            if ($fieldValue === null) {
                                $fieldValue = $field['type'] === 'checkbox' ? false : '';
                            }
                            $isTextarea = $field['type'] === 'textarea';
                            $isChoiceField = in_array($field['type'], ['dropdown', 'radiobutton'], true);
                            $inputType = in_array($field['type'], ['email', 'number', 'date'], true) ? $field['type'] : 'text';
                        @endphp

                        <div @class(['md:col-span-3' => $isTextarea || $field['type'] === 'checkbox']) class="space-y-1" data-custom-field-wrapper="{{ $field['key'] }}">
                            @if($field['type'] === 'checkbox')
                                <label class="flex items-start gap-3 border border-slate-200 bg-slate-50 px-4 py-3">
                                    <input type="hidden" name="custom_fields[{{ $field['key'] }}]" value="0">
                                    <input
                                        id="custom_field_{{ $field['key'] }}"
                                        name="custom_fields[{{ $field['key'] }}]"
                                        type="checkbox"
                                        value="1"
                                        class="mt-1 border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                        @checked((bool) $fieldValue)
                                    >
                                    <span>
                                        <span class="block text-sm font-semibold text-slate-900">{{ $field['label'] }} @if($field['required']) <span class="text-rose-500">*</span> @endif</span>
                                        @if(!empty($field['help_text']))
                                            <span class="mt-1 block text-xs text-slate-500">{{ $field['help_text'] }}</span>
                                        @endif
                                    </span>
                                </label>
                                <div class="custom-field-error-slot">
                                    <x-input-error :messages="$errors->get('custom_fields.' . $field['key'])" class="mt-1 block" />
                                </div>
                            @elseif($isTextarea)
                                <div class="flex items-center justify-between">
                                    <x-input-label for="custom_field_{{ $field['key'] }}" :value="$field['label']" />
                                    @if(str_contains(strtolower($field['key'] . ' ' . $field['label']), 'desc'))
                                        <x-speech-to-text target="custom_field_{{ $field['key'] }}" />
                                    @endif
                                </div>
                                <textarea
                                    id="custom_field_{{ $field['key'] }}"
                                    name="custom_fields[{{ $field['key'] }}]"
                                    rows="3"
                                    class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                    placeholder="{{ $field['placeholder'] ?? '' }}"
                                    {{ $field['required'] ? 'required' : '' }}
                                >{{ old('custom_fields.' . $field['key'], $fieldValue) }}</textarea>
                                @if(!empty($field['help_text']))
                                    <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                                @endif
                                <div class="custom-field-error-slot">
                                    <x-input-error :messages="$errors->get('custom_fields.' . $field['key'])" class="mt-1 block" />
                                </div>
                            @elseif($isChoiceField)
                                <x-input-label :value="$field['label']" />
                                @if($field['type'] === 'radiobutton')
                                    <div class="mt-2 space-y-2 border border-gray-200 bg-white p-3">
                                        @foreach(($field['options'] ?? []) as $option)
                                            <label class="flex items-center gap-3 text-sm text-slate-700">
                                                <input
                                                    type="radio"
                                                    name="custom_fields[{{ $field['key'] }}]"
                                                    value="{{ $option }}"
                                                    class="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                    @checked(old('custom_fields.' . $field['key'], $fieldValue) === $option)
                                                    {{ $field['required'] ? 'required' : '' }}
                                                >
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <select
                                        id="custom_field_{{ $field['key'] }}"
                                        name="custom_fields[{{ $field['key'] }}]"
                                        class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                        {{ $field['required'] ? 'required' : '' }}
                                    >
                                        <option value="">Select an option...</option>
                                        @foreach(($field['options'] ?? []) as $option)
                                            <option value="{{ $option }}" @selected(old('custom_fields.' . $field['key'], $fieldValue) === $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @endif
                                @if(!empty($field['help_text']))
                                    <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                                @endif
                                <div class="custom-field-error-slot">
                                    <x-input-error :messages="$errors->get('custom_fields.' . $field['key'])" class="mt-1 block" />
                                </div>
                            @else
                                <x-input-label for="custom_field_{{ $field['key'] }}" :value="$field['label']" />
                                <div class="mt-1 flex items-center border border-gray-300 bg-white px-3 py-2 shadow-sm focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                                    <input
                                        id="custom_field_{{ $field['key'] }}"
                                        name="custom_fields[{{ $field['key'] }}]"
                                        type="{{ $inputType }}"
                                        value="{{ old('custom_fields.' . $field['key'], $fieldValue) }}"
                                        placeholder="{{ $field['placeholder'] ?? '' }}"
                                        class="w-full border-0 p-0 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                        {{ $field['required'] ? 'required' : '' }}
                                    >
                                </div>
                                @if(!empty($field['help_text']))
                                    <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
                                @endif
                                <div class="custom-field-error-slot">
                                    <x-input-error :messages="$errors->get('custom_fields.' . $field['key'])" class="mt-1 block" />
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <div class="md:col-span-3" x-data="{
                        open: false,
                        search: '',
                        selected: @js(collect($assignedUserIds)->map(fn ($id) => (string) $id)->values()->all()),
                        users: @js(collect($users)->map(fn ($user) => [
                            'id' => (string) $user->id,
                            'name' => $user->name,
                            'role' => $user->role,
                        ])->values()->all()),
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
                            return this.users.find(user => user.id === id)?.role || '';
                        },
                        matches(user) {
                            const keyword = this.search.trim().toLowerCase();
                            if (!keyword) return true;
                            return user.name.toLowerCase().includes(keyword) || user.role.toLowerCase().includes(keyword);
                        },
                        get filteredUsers() {
                            return this.users.filter(user => this.matches(user));
                        }
                    }">
                        <x-input-label :value="__('Assigned Employees')" />
                        <div class="relative mt-1">
                            <div
                                @click="open = true; $nextTick(() => $refs.searchInput?.focus())"
                                @click.outside="open = false"
                                class="min-h-[48px] w-full border border-gray-300 bg-white px-3 py-2 shadow-sm transition hover:border-indigo-400 focus-within:border-indigo-500 focus-within:ring-2 focus-within:ring-indigo-500"
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <template x-for="id in selected" :key="'selected-'+id">
                                        <span class="inline-flex items-center gap-1 border border-indigo-200 bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-700">
                                            <span x-text="getUserName(id)"></span>
                                            <button type="button" @click.stop="toggle(id)" class="text-indigo-400 hover:text-indigo-900">
                                                <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                                            </button>
                                        </span>
                                    </template>
                                    <input
                                        x-ref="searchInput"
                                        x-model="search"
                                        @focus="open = true"
                                        type="text"
                                        class="min-w-[220px] flex-1 border-0 p-1 text-sm text-slate-700 placeholder:text-slate-400 focus:outline-none focus:ring-0"
                                        placeholder="Search and assign team members..."
                                    >
                                </div>
                            </div>

                            <div
                                x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 transform scale-95"
                                x-transition:enter-end="opacity-100 transform scale-100"
                                class="absolute z-50 mt-1 max-h-[410px] w-full overflow-y-auto overscroll-contain border border-gray-200 bg-white shadow-xl"
                                style="display: none;"
                            >
                                <template x-if="filteredUsers.length === 0">
                                    <div class="px-4 py-3 text-sm text-slate-500">No employees found.</div>
                                </template>

                                <template x-for="user in filteredUsers" :key="'option-'+user.id">
                                    <button
                                        type="button"
                                        @click="toggle(user.id)"
                                        class="flex w-full items-center justify-between border-b border-gray-50 px-4 py-2.5 text-left text-sm last:border-none hover:bg-indigo-50"
                                        :class="selected.includes(user.id) ? 'bg-indigo-50/60 text-indigo-700' : 'text-slate-700'"
                                    >
                                        <span class="font-medium">
                                            <span x-text="user.name"></span>
                                            <span class="text-[10px] text-slate-400">[<span x-text="user.role"></span>]</span>
                                        </span>
                                        <span x-show="selected.includes(user.id)" class="bg-indigo-600 p-0.5">
                                            <svg class="h-3 w-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path></svg>
                                        </span>
                                    </button>
                                </template>
                            </div>

                            <template x-for="id in selected" :key="'submit-'+id">
                                <input type="hidden" name="assigned_to[]" :value="id">
                            </template>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Search and select one or more employees. The creator stays assigned by default.</p>
                        <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
                        <x-input-error :messages="$errors->get('assigned_to.*')" class="mt-2" />
                    </div>

                    <div class="md:col-span-3">
                        <x-input-label for="address" :value="__('Address')" />
                        <div class="relative mt-1">
                          <textarea
                                id="address"
                                name="address"
                                rows="3"
                                class="block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                placeholder="Enter street, city, state, and pincode"
                                autocomplete="off"
                            ></textarea>
                           {{-- <div
                                id="address-suggestions"
                                class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden border border-slate-200 bg-white shadow-xl"
                                role="listbox"
                                aria-label="Address suggestions"
                            ></div> --}}
                        </div>

                        <p class="mt-1 text-xs text-slate-500">Start typing an area, pincode, city, or landmark to get address suggestions.</p>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                    </div>
                    <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude') }}">
                    <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude') }}">
                </div>

                <div class="flex items-center justify-end border-t border-slate-200 pt-4">
                    <x-primary-button id="lead-submit-btn" class="px-6 py-2 bg-[#1e293b] hover:bg-slate-700">
                        <span id="lead-submit-text">{{ __('Create Lead') }}</span>
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script
      src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places">
    </script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            const input = document.getElementById('address');

            if (!input) {
                return;
            }

            const autocomplete = new google.maps.places.Autocomplete(input, {
                componentRestrictions: {
                    country: ['in']
                },
                fields: [
                    'formatted_address',
                    'geometry'
                ]
            });

            autocomplete.addListener('place_changed', function () {

                const place = autocomplete.getPlace();

                if (!place.geometry) {
                    return;
                }

                input.value = place.formatted_address;

                document.getElementById('latitude').value =
                    place.geometry.location.lat();

                document.getElementById('longitude').value =
                    place.geometry.location.lng();

                console.log({
                    address: place.formatted_address,
                    latitude: place.geometry.location.lat(),
                    longitude: place.geometry.location.lng()
                });

            });

        });
    </script>
    <script>
            document.addEventListener('DOMContentLoaded', function () {
                const $form = $('#lead-form');
                const $btn = $('#lead-submit-btn');
                const $text = $('#lead-submit-text');
                const clientSearchInput = document.getElementById('existing_client_search');
                const clientSelect = document.getElementById('existing_client_id');
                const clientOptions = clientSelect ? Array.from(clientSelect.options) : [];
                const clientName = document.getElementById('client_name');
                const emailInput = document.getElementById('email');
                const mobileInput = document.getElementById('mobile');
                const addressInput = document.getElementById('address');
                const addressSuggestions = document.getElementById('address-suggestions');
                const stateSelect = document.getElementById('state_id');
                const citySelect = document.getElementById('city_id');
                const sourceSelect = document.getElementById('source');
                const referralPersonNameWrapper = document.getElementById('referral-person-name-wrapper');
                const referralPersonNameInput = document.getElementById('referral_person_name');
                const cityOptionsByState = @js($cityOptionsByState);
                const leadSourceReferralMap = @js($leadSourceReferralMap);
                const leadCustomFields = @js($leadCustomFields);
                const defaultStateId = @js($defaultStateId);
                const defaultCityId = @js($defaultCityId);
                const selectedCityId = @js($selectedCityId);
                const hasOldInput = @js(session()->getOldInput() !== null);
                const addressAutocompleteUrl = addressInput?.dataset.autocompleteUrl || '';
                let addressAutocompleteTimer = null;
                let addressAutocompleteAbortController = null;
                let addressSuggestionsList = [];
                let highlightedAddressIndex = -1;
                let suppressAddressLookup = false;

                if (!$form.length) return;

                function normalizeClientSearch(value) {
                    return String(value || '').toLowerCase().replace(/\s+/g, ' ').trim();
                }

                function filterClientOptions() {
                    if (!clientSelect || !clientSearchInput) {
                        return;
                    }

                    const query = normalizeClientSearch(clientSearchInput.value);

                    clientOptions.forEach(function (option) {
                        if (!option || option.value === '' || option.value === 'new') {
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

                clientSearchInput?.addEventListener('input', filterClientOptions);
                clientSearchInput?.addEventListener('search', filterClientOptions);
                filterClientOptions();

                mobileInput?.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 10);
                });

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
                    citySelect.dispatchEvent(new Event('change', { bubbles: true }));
                }

                function fillClientFromOption(option) {
                    if (!option) return;
                    if (clientSearchInput) {
                        clientSearchInput.value = option.dataset.name || option.textContent.trim();
                        filterClientOptions();
                    }
                    clientName.value = option.dataset.name || clientName.value;
                    emailInput.value = option.dataset.email || '';
                    mobileInput.value = option.dataset.mobile || '';
                    addressInput.value = option.dataset.address || '';
                    hideAddressSuggestions();
                    stateSelect.value = option.dataset.stateId || '';
                    renderCities(stateSelect.value, option.dataset.cityId || '');
                }

                function resetClientFields(preserveExistingValues = false) {
                    if (!preserveExistingValues) {
                        clientName.value = '';
                        emailInput.value = '';
                        mobileInput.value = '';
                        addressInput.value = '';
                        hideAddressSuggestions();
                        if (clientSearchInput) {
                            clientSearchInput.value = '';
                            filterClientOptions();
                        }
                    }

                    stateSelect.value = defaultStateId ? String(defaultStateId) : '';
                    renderCities(stateSelect.value, defaultCityId ? String(defaultCityId) : '');
                    clientName.focus();
                }

                function syncReferralPersonField() {
                    if (!sourceSelect || !referralPersonNameWrapper || !referralPersonNameInput) {
                        return;
                    }

                    const shouldShow = Boolean(leadSourceReferralMap[sourceSelect.value]);
                    referralPersonNameWrapper.classList.toggle('hidden', !shouldShow);

                    if (!shouldShow) {
                        referralPersonNameInput.value = '';
                    }
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

                function getAddressSuggestionLabel(item) {
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

                function normalizeAddressSuggestions(payload) {
                    if (Array.isArray(payload)) {
                        return payload;
                    }

                    if (Array.isArray(payload?.features)) {
                        return payload.features;
                    }

                    return [];
                }

                function hideAddressSuggestions() {
                    if (!addressSuggestions) {
                        return;
                    }

                    addressSuggestions.classList.add('hidden');
                    addressSuggestions.innerHTML = '';
                    addressSuggestionsList = [];
                    highlightedAddressIndex = -1;
                }

                function renderAddressSuggestions(results) {
                    if (!addressSuggestions) {
                        return;
                    }

                    addressSuggestionsList = results;
                    highlightedAddressIndex = -1;

                    if (!results.length) {
                        addressSuggestions.innerHTML = '<div class="px-4 py-3 text-sm text-slate-500">No matching locations found.</div>';
                        addressSuggestions.classList.remove('hidden');
                        return;
                    }

                    addressSuggestions.innerHTML = results.map((item, index) => {
                        const displayName = escapeHtml(getAddressSuggestionLabel(item));
                        return `
                            <button
                                type="button"
                                class="address-suggestion-item flex w-full items-start gap-3 border-b border-slate-100 px-4 py-3 text-left last:border-b-0 hover:bg-slate-50 focus:bg-slate-50 focus:outline-none"
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

                    addressSuggestions.classList.remove('hidden');
                }

                function setHighlightedAddressSuggestion(index) {
                    if (!addressSuggestions || !addressSuggestionsList.length) {
                        return;
                    }

                    const items = addressSuggestions.querySelectorAll('.address-suggestion-item');
                    items.forEach((item, itemIndex) => {
                        const isActive = itemIndex === index;
                        item.classList.toggle('bg-slate-50', isActive);
                        item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                    });
                    highlightedAddressIndex = index;
                }

                function applyAddressSuggestion(index) {
                    const selectedSuggestion = addressSuggestionsList[index];
                    if (!selectedSuggestion || !addressInput) {
                        return;
                    }

                    suppressAddressLookup = true;
                    addressInput.value = getAddressSuggestionLabel(selectedSuggestion);
                    hideAddressSuggestions();
                    $(addressInput).valid();

                    window.setTimeout(() => {
                        suppressAddressLookup = false;
                    }, 0);
                }

               async function fetchAddressSuggestions(query) {
                    if (!addressAutocompleteUrl || !addressSuggestions) {
                        return;
                    }

                    if (addressAutocompleteAbortController) {
                        addressAutocompleteAbortController.abort();
                    }

                    addressAutocompleteAbortController = new AbortController();

                    try {
                        const response = await fetch(`${addressAutocompleteUrl}${encodeURIComponent(query)}`, {
                            method: 'GET',
                            headers: {
                                'Accept': 'application/json'
                            },
                            signal: addressAutocompleteAbortController.signal
                        });

                        if (!response.ok) {
                            hideAddressSuggestions();
                            return;
                        }

                        const results = await response.json();
                        renderAddressSuggestions(normalizeAddressSuggestions(results));
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            console.error('Autocomplete error:', error);
                            hideAddressSuggestions();
                        }
                    }
                }

                function queueAddressAutocompleteLookup() {
                    if (!addressInput || suppressAddressLookup) {
                        return;
                    }

                    const query = addressInput.value.trim();

                    if (addressAutocompleteTimer) {
                        window.clearTimeout(addressAutocompleteTimer);
                    }

                    if (query.length < 3) {
                        hideAddressSuggestions();
                        return;
                    }

                    addressAutocompleteTimer = window.setTimeout(() => {
                        fetchAddressSuggestions(query);
                    }, 350);
                }

                stateSelect?.addEventListener('change', function () {
                    renderCities(this.value);
                });

                sourceSelect?.addEventListener('change', syncReferralPersonField);
                addressInput?.addEventListener('input', queueAddressAutocompleteLookup);
                addressInput?.addEventListener('keydown', function (event) {
                    if (addressSuggestions.classList.contains('hidden') || !addressSuggestionsList.length) {
                        return;
                    }

                    if (event.key === 'ArrowDown') {
                        event.preventDefault();
                        const nextIndex = highlightedAddressIndex < addressSuggestionsList.length - 1
                            ? highlightedAddressIndex + 1
                            : 0;
                        setHighlightedAddressSuggestion(nextIndex);
                        return;
                    }

                    if (event.key === 'ArrowUp') {
                        event.preventDefault();
                        const previousIndex = highlightedAddressIndex > 0
                            ? highlightedAddressIndex - 1
                            : addressSuggestionsList.length - 1;
                        setHighlightedAddressSuggestion(previousIndex);
                        return;
                    }

                    if (event.key === 'Enter' && highlightedAddressIndex >= 0) {
                        event.preventDefault();
                        applyAddressSuggestion(highlightedAddressIndex);
                        return;
                    }

                    if (event.key === 'Escape') {
                        hideAddressSuggestions();
                    }
                });

                addressSuggestions?.addEventListener('mousedown', function (event) {
                    const button = event.target.closest('[data-address-index]');
                    if (!button) {
                        return;
                    }

                    event.preventDefault();
                    applyAddressSuggestion(Number(button.dataset.addressIndex));
                });

                document.addEventListener('click', function (event) {
                    if (!addressInput || !addressSuggestions) {
                        return;
                    }

                    if (event.target === addressInput || addressSuggestions.contains(event.target)) {
                        return;
                    }

                    hideAddressSuggestions();
                });

                renderCities(stateSelect?.value || '', selectedCityId ? String(selectedCityId) : '');
                syncReferralPersonField();

                clientSelect?.addEventListener('change', function () {
                    const option = this.selectedOptions?.[0];
                    if (!option || !this.value || this.value === 'new') {
                        if (clientSearchInput) {
                            clientSearchInput.value = '';
                            filterClientOptions();
                        }
                        resetClientFields();
                        return;
                    }
                    fillClientFromOption(option);
                });

                const initialSelected = clientSelect?.selectedOptions?.[0];
                if (initialSelected && clientSelect.value && clientSelect.value !== 'new') {
                    fillClientFromOption(initialSelected);
                } else if (!hasOldInput) {
                    resetClientFields();
                } else {
                    resetClientFields(true);
                }

            function showLeadValidationError(message) {
                if (window.Swal) {
                    Swal.fire({
                        title: 'Validation Error',
                        text: message,
                        icon: 'error',
                        confirmButtonColor: '#4f46e5',
                        confirmButtonText: 'OK',
                    });
                    return;
                }

                alert(message);
            }

            $.validator.addMethod('mobilePattern', function (value, element) {
                if (this.optional(element)) {
                    return true;
                }

                return /^[0-9]{10}$/.test(String(value || '').trim());
            }, 'Please enter exactly 10 digits.');

            $.validator.addMethod('strictEmail', function (value, element) {
                if (!value) return true;
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value.trim());
            }, 'Please enter a valid email address.');

            $.validator.addMethod('gstPattern', function (value, element) {
                if (!value) return true;
                return /^[0-9A-Z]{15}$/.test(value);
            }, 'Please enter a valid GST number.');

            $.validator.addMethod('panPattern', function (value, element) {
                if (!value) return true;
                return /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/.test(value);
            }, 'Please enter a valid PAN number.');

            const customFieldRules = {};
            const customFieldMessages = {};
            const errorBorderStyle = {
                borderColor: '#ef4444',
                boxShadow: '0 0 0 1px #ef4444'
            };

            (leadCustomFields || []).forEach((field) => {
                if (!field || !field.required) {
                    return;
                }

                const fieldName = `custom_fields[${field.key}]`;
                customFieldRules[fieldName] = { required: true };
                customFieldMessages[fieldName] = { required: `${field.label} is required.` };
            });

                $form.validate({
                    ignore: [],
                    errorClass: 'text-red-600 text-sm mt-1 block',
                    errorElement: 'span',
                    errorPlacement: function (error, element) {
                        const wrapper = element.closest('[data-custom-field-wrapper]');
                        if (wrapper) {
                            const slot = wrapper.querySelector('.custom-field-error-slot');
                            if (slot) {
                                slot.innerHTML = '';
                                slot.appendChild(error[0]);
                                return;
                            }
                        }

                        error.insertAfter(element);
                    },
                    highlight: function (element) {
                        const $element = $(element);
                        $element.addClass('border-red-500');
                        element.style.borderColor = '#ef4444';
                        element.style.boxShadow = '0 0 0 1px #ef4444';
                        element.style.outline = 'none';

                        const wrapper = element.closest('[data-custom-field-wrapper]');
                        if (wrapper) {
                            wrapper.classList.add('ring-1', 'ring-red-500');
                            wrapper.style.borderColor = '#ef4444';
                            wrapper.style.boxShadow = '0 0 0 1px #ef4444';

                            const borderBox = wrapper.querySelector('.custom-field-border-box');
                            if (borderBox) {
                                borderBox.classList.add('border-red-500', 'focus-within:border-red-500', 'focus-within:ring-red-500');
                                borderBox.style.borderColor = '#ef4444';
                                borderBox.style.boxShadow = '0 0 0 1px #ef4444';
                            }
                        }
                    },
                unhighlight: function (element) {
                    const $element = $(element);
                    $element.removeClass('border-red-500');
                    element.style.borderColor = '';
                    element.style.boxShadow = '';
                    element.style.outline = '';

                    const wrapper = element.closest('[data-custom-field-wrapper]');
                    if (wrapper) {
                        wrapper.classList.remove('ring-1', 'ring-red-500');
                        wrapper.style.borderColor = '';
                        wrapper.style.boxShadow = '';

                        const borderBox = wrapper.querySelector('.custom-field-border-box');
                        if (borderBox) {
                            borderBox.classList.remove('border-red-500', 'focus-within:border-red-500', 'focus-within:ring-red-500');
                            borderBox.style.borderColor = '';
                            borderBox.style.boxShadow = '';
                        }
                    }
                },
                    rules: {
                        client_name: { required: true, minlength: 3, maxlength: 191 },
                        email: { strictEmail: true, maxlength: 191 },
                        mobile: { required: true, mobilePattern: true, minlength: 10, maxlength: 10 },
                    @if($showLeadGstField)
                    gst_number: { gstPattern: true },
                    @endif
                    @if($showLeadPanField)
                    pan_number: { panPattern: true },
                    @endif
                    state_id: { required: true },
                    city_id: { required: true },
                    lead_category_id: { required: true },
                    lead_status_id: { required: true },
                    'assigned_to[]': { required: true },
                        priority: { required: true },
                        estimated_value: { min: 0, max: 99999999.99 },
                        source: { maxlength: 100 },
                        referral_person_name: { maxlength: 191 },
                        address: { maxlength: 1000 },
                        ...customFieldRules
                    },
                    messages: {
                        email: { strictEmail: 'Please enter a valid email address.' },
                        'assigned_to[]': { required: 'Please select at least one employee.' },
                        ...customFieldMessages
                    },
                    invalidHandler: function (event, validator) {
                        const firstError = validator.errorList?.[0];
                        if (!firstError) {
                            showLeadValidationError('Please check the form and try again.');
                            return;
                        }

                        const label = $(firstError.element).closest('div').find('label').first().text().trim() || firstError.element.name || 'Field';
                        showLeadValidationError(firstError.message || `${label} is invalid.`);
                    },
                    submitHandler: function (formEl) {
                        const assignedUsers = formEl.querySelectorAll('input[name="assigned_to[]"]');
                        if (!assignedUsers.length) {
                            showLeadValidationError('Please select at least one employee.');
                            return;
                        }

                        const submitNow = () => {
                            $btn.attr('disabled', true).addClass('opacity-75 cursor-not-allowed');
                            formEl.submit();
                        };

                    if (window.Swal) {
                        Swal.fire({
                            title: 'Create Lead?',
                            text: 'Do you want to create this lead?',
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, create',
                            cancelButtonText: 'Cancel'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                submitNow();
                            }
                        });
                        return;
                    }

                    if (window.confirm('Do you want to create this lead?')) {
                        submitNow();
                    }
                }
            });
        });
    </script>
    @endpush
</x-app-layout>
