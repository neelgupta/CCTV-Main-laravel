<x-app-layout>
    @php
        $assignedUserIds = collect(old('assigned_to', $lead->assignedUsers->pluck('id')->all() ?: ($lead->assigned_to ? [$lead->assigned_to] : [])))
            ->map(fn ($id) => (int) $id)
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
        $selectedStateId = old('state_id', $lead->state_id ?: $defaultStateId);
        $selectedCityId = old('city_id', $lead->city_id ?: $defaultCityId);
        $showLeadGstField = $company?->show_lead_gst_field ?? true;
        $showLeadPanField = $company?->show_lead_pan_field ?? true;
        $selectedLeadSource = old('source', $lead->source);
        $leadSourceReferralMap = collect($leadSources ?? [])
            ->mapWithKeys(fn ($leadSource) => [
                ($leadSource['name'] ?? '') => (bool) ($leadSource['show_referral_person_name'] ?? false),
            ])
            ->filter(fn ($value, $key) => filled($key))
            ->all();
        $showReferralPersonField = filled($selectedLeadSource) && ($leadSourceReferralMap[$selectedLeadSource] ?? false);
        $leadCustomFields = collect($company?->normalizedLeadCustomFields() ?? []);
        $customFieldValues = old('custom_fields', $lead->custom_fields ?? []);
    @endphp

    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Update Lead') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="w-full">
            <div class="bg-white overflow-hidden shadow-sm p-6">
                <form id="lead-form" method="POST" action="{{ route('leads.update', $lead) }}" data-allow-multi-submit="1">
                    @csrf
                    @method('PATCH')

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
                        <div class="md:col-span-3">
                            <x-client-picker
                                :clients="$clients ?? []"
                                :selected="old('existing_client_id', $selectedExistingClientId ?? '')"
                                label="Select Existing Client"
                                helper-text="Search and select exactly one client, or keep New client and edit the fields below manually."
                            />
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-1">
                                <x-input-label for="client_name" :value="__('Client Name')" />
                                <span class="text-red-600">*</span>
                            </div>
                            <x-text-input id="client_name" class="block mt-1 w-full" type="text" name="client_name" :value="old('client_name', $lead->client_name)" placeholder="Enter client's full name" required autofocus />
                            <x-input-error :messages="$errors->get('client_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $lead->email)" placeholder="example@company.com" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-1">
                                <x-input-label for="mobile" :value="__('Mobile')" />
                                <span class="text-red-600">*</span>
                            </div>
                            <x-text-input id="mobile" class="block mt-1 w-full" type="tel" name="mobile" :value="old('mobile', $lead->mobile)" placeholder="Enter mobile number" inputmode="numeric" maxlength="10" pattern="[0-9]{10}" autocomplete="off" />
                            <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
                        </div>

                        @if($showLeadGstField)
                            <div>
                                <x-input-label for="gst_number" :value="__('GST Number')" />
                                <x-text-input id="gst_number" class="block mt-1 w-full" type="text" name="gst_number" :value="old('gst_number', $lead->gst_number)" placeholder="22AAAAA0000A1Z5" />
                                <x-input-error :messages="$errors->get('gst_number')" class="mt-2" />
                            </div>
                        @endif

                        @if($showLeadPanField)
                            <div>
                                <x-input-label for="pan_number" :value="__('PAN Number')" />
                                <x-text-input id="pan_number" class="block mt-1 w-full" type="text" name="pan_number" :value="old('pan_number', $lead->pan_number)" placeholder="AAAAA1234A" />
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

                        <div class="space-y-1">
                            <div class="flex items-center gap-1">
                                <x-input-label for="lead_category_id" :value="__('Lead Category')" />
                                <span class="text-red-600">*</span>
                            </div>
                            <select id="lead_category_id" name="lead_category_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="" disabled {{ old('lead_category_id', $lead->lead_category_id) ? '' : 'selected' }}>Choose a category...</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" {{ (string) old('lead_category_id', $lead->lead_category_id) === (string) $category->id ? 'selected' : '' }}>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('lead_category_id')" class="mt-2" />
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-1">
                                <x-input-label for="lead_status_id" :value="__('Status')" />
                                <span class="text-red-600">*</span>
                            </div>
                            <select id="lead_status_id" name="lead_status_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="" disabled {{ old('lead_status_id', $lead->lead_status_id) ? '' : 'selected' }}>Select status...</option>
                                @foreach($statuses as $status)
                                    <option value="{{ $status->id }}"
                                        data-status-name="{{ strtolower(trim((string) $status->name)) }}"
                                        {{ (string) old('lead_status_id', $lead->lead_status_id) === (string) $status->id ? 'selected' : '' }}>
                                        {{ $status->name }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('lead_status_id')" class="mt-2" />
                        </div>

                        @php
                            $currentStatus = $statuses->firstWhere('id', old('lead_status_id', $lead->lead_status_id));
                            $isCurrentStatusLoss = $currentStatus && in_array(strtolower(trim((string) $currentStatus->name)), ['loss', 'lost'], true);
                        @endphp

                        <div id="loss_reason_section" class="space-y-2 {{ $isCurrentStatusLoss ? '' : 'hidden' }}">
                            <div class="flex items-center gap-1">
                                <x-input-label for="loss_reason_preset" :value="__('Common Loss Reason')" />
                            </div>
                            <select id="loss_reason_preset" class="block mt-1 w-full border-gray-300 focus:border-rose-500 focus:ring-rose-500 shadow-sm text-sm">
                                <option value="">-- Select a reason (or write below) --</option>
                                @foreach($defaultLossReasons ?? \App\Models\Lead::defaultLossReasons() as $presetReason)
                                    <option value="{{ $presetReason }}">{{ $presetReason }}</option>
                                @endforeach
                            </select>

                            <div class="space-y-1 pt-1">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1">
                                        <x-input-label for="loss_reason" :value="__('Loss Reason')" />
                                        <span class="text-red-600">*</span>
                                    </div>
                                    <x-speech-to-text target="loss_reason" />
                                </div>
                                <textarea id="loss_reason" name="loss_reason" rows="3"
                                    placeholder="Explain why the lead was lost or not interested..."
                                    class="block mt-1 w-full border-gray-300 focus:border-rose-500 focus:ring-rose-500 shadow-sm text-sm"
                                    maxlength="1000">{{ old('loss_reason', $lead->loss_reason) }}</textarea>
                                <x-input-error :messages="$errors->get('loss_reason')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="priority" :value="__('Priority')" />
                            <select id="priority" name="priority" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="" disabled {{ old('priority', $lead->priority) ? '' : 'selected' }}>Set priority level...</option>
                                <option value="low" {{ old('priority', $lead->priority) == 'low' ? 'selected' : '' }}>Low</option>
                                <option value="medium" {{ old('priority', $lead->priority) == 'medium' ? 'selected' : '' }}>Medium</option>
                                <option value="high" {{ old('priority', $lead->priority) == 'high' ? 'selected' : '' }}>High</option>
                            </select>
                            <x-input-error :messages="$errors->get('priority')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="estimated_value" :value="__('Estimated Value (₹)')" />
                            <x-text-input id="estimated_value" class="block mt-1 w-full" type="number" step="0.01" name="estimated_value" :value="old('estimated_value', $lead->estimated_value)" placeholder="e.g. 50000.00" />
                            <x-input-error :messages="$errors->get('estimated_value')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="next_follow_up" :value="__('Next Follow Up')" />
                            <x-text-input
                                id="next_follow_up"
                                class="block mt-1 w-full"
                                type="date"
                                name="next_follow_up"
                                :value="old('next_follow_up', optional($lead->next_follow_up)->format('Y-m-d'))"
                                placeholder="Select date"
                            />
                            <x-input-error :messages="$errors->get('next_follow_up')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="source" :value="__('Lead Source')" />
                            <select id="source" name="source" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm">
                                <option value="">Select lead source...</option>
                                @foreach($leadSources ?? [] as $leadSource)
                                    <option value="{{ $leadSource['name'] }}" @selected($selectedLeadSource === $leadSource['name'])>{{ $leadSource['name'] }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('source')" class="mt-2" />
                        </div>

                        <div id="referral-person-name-wrapper" @class(['hidden' => ! $showReferralPersonField])>
                            <x-input-label for="referral_person_name" :value="__('Referral Person Name')" />
                            <x-text-input id="referral_person_name" class="block mt-1 w-full" type="text" name="referral_person_name" :value="old('referral_person_name', $lead->referral_person_name)" placeholder="Enter referral person name" />
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
                                        class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
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
                                            class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
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
                            <p class="mt-1 text-xs text-slate-500">Search and select one or more employees for this lead.</p>
                            <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
                            <x-input-error :messages="$errors->get('assigned_to.*')" class="mt-2" />
                        </div>

                        <div class="md:col-span-3">
                            <x-input-label for="address" :value="__('Address')" />
                        <div class="relative mt-1">
                            <textarea
                                id="address"
                                name="address"
                                class="block w-full border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="Enter full address details here..."
                                autocomplete="off"
                            >{{ old('address', $lead->address) }}</textarea>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Start typing an area, pincode, city, or landmark to get address suggestions.</p>
                        <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                        <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $lead->latitude) }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $lead->longitude) }}">
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <x-primary-button id="lead-submit-btn">
                            <span id="lead-submit-text">{{ __('Update Lead') }}</span>
                        </x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @push('scripts')
        <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/jquery-validation@1.19.5/dist/jquery.validate.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const addressInput = document.getElementById('address');

                if (addressInput && window.google?.maps?.places) {
                    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
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

                        addressInput.value = place.formatted_address;

                        const latitudeInput = document.getElementById('latitude');
                        const longitudeInput = document.getElementById('longitude');

                        if (latitudeInput) {
                            latitudeInput.value = place.geometry.location.lat();
                        }

                        if (longitudeInput) {
                            longitudeInput.value = place.geometry.location.lng();
                        }
                    });
                }

                const $form = $('#lead-form');
                const $btn = $('#lead-submit-btn');
                const $text = $('#lead-submit-text');
                const clientNameInput = document.getElementById('client_name');
                const clientSearchInput = document.getElementById('existing_client_search');
                const clientSelect = document.getElementById('existing_client_id');
                const clientOptions = clientSelect ? Array.from(clientSelect.options) : [];
                const stateSelect = document.getElementById('state_id');
                const citySelect = document.getElementById('city_id');
                const sourceSelect = document.getElementById('source');
                const referralPersonNameWrapper = document.getElementById('referral-person-name-wrapper');
                const referralPersonNameInput = document.getElementById('referral_person_name');
                const cityOptionsByState = @js($cityOptionsByState);
                const leadSourceReferralMap = @js($leadSourceReferralMap);
                const leadCustomFields = @js($leadCustomFields);

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

                function fillClientFromOption(option) {
                    if (!option) {
                        return;
                    }

                    if (clientSearchInput) {
                        clientSearchInput.value = option.dataset.name || option.textContent.trim();
                        filterClientOptions();
                    }

                    if (clientNameInput) {
                        clientNameInput.value = option.dataset.name || '';
                    }

                    const emailInput = document.getElementById('email');
                    const mobileInput = document.getElementById('mobile');
                    const addressInput = document.getElementById('address');

                    if (emailInput) {
                        emailInput.value = option.dataset.email || '';
                    }

                    if (mobileInput) {
                        mobileInput.value = option.dataset.mobile || '';
                    }

                    if (addressInput) {
                        addressInput.value = option.dataset.address || '';
                    }

                    stateSelect.value = option.dataset.stateId || '';
                    renderCities(stateSelect.value, option.dataset.cityId || '');
                }

                function resetClientSelection() {
                    if (clientSearchInput) {
                        clientSearchInput.value = '';
                        filterClientOptions();
                    }
                }

                clientSearchInput?.addEventListener('input', filterClientOptions);
                clientSearchInput?.addEventListener('search', filterClientOptions);
                filterClientOptions();

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

                stateSelect?.addEventListener('change', function () {
                    renderCities(this.value);
                });

                sourceSelect?.addEventListener('change', syncReferralPersonField);

                const initialSelectedClient = clientSelect?.selectedOptions?.[0];
                if (initialSelectedClient && clientSelect.value && clientSelect.value !== 'new') {
                    fillClientFromOption(initialSelectedClient);
                }

                clientSelect?.addEventListener('change', function () {
                    const option = this.selectedOptions?.[0];
                    if (!option || !this.value || this.value === 'new') {
                        resetClientSelection();
                        return;
                    }

                    fillClientFromOption(option);
                });

                const leadStatusSelect = document.getElementById('lead_status_id');
                const lossReasonSection = document.getElementById('loss_reason_section');
                const lossReasonPreset = document.getElementById('loss_reason_preset');
                const lossReasonText = document.getElementById('loss_reason');

                function isLossStatusSelected() {
                    const selectedOption = leadStatusSelect?.selectedOptions?.[0];
                    if (!selectedOption) return false;
                    const statusName = (selectedOption.dataset.statusName || selectedOption.textContent || '').trim().toLowerCase();
                    return statusName === 'loss' || statusName === 'lost';
                }

                function syncLossReasonVisibility() {
                    if (!lossReasonSection) return;
                    const isLoss = isLossStatusSelected();
                    lossReasonSection.classList.toggle('hidden', !isLoss);
                    if (!isLoss && lossReasonText) {
                        // Clear error highlight if hidden
                        $(lossReasonText).removeClass('border-red-500');
                    }
                }

                leadStatusSelect?.addEventListener('change', syncLossReasonVisibility);

                if (lossReasonPreset && lossReasonText) {
                    lossReasonPreset.addEventListener('change', function () {
                        if (this.value) {
                            lossReasonText.value = this.value;
                            $(lossReasonText).removeClass('border-red-500');
                            const errLabel = document.getElementById('loss_reason-error');
                            if (errLabel) errLabel.style.display = 'none';
                        }
                    });
                }

                renderCities(stateSelect?.value || '', @js($selectedCityId));
                syncReferralPersonField();
                syncLossReasonVisibility();

                // Custom Method: Exactly 10 digits for mobile
                $.validator.addMethod('mobilePattern', function (value, element) {
                    if (this.optional(element)) {
                        return true;
                    }

                    return /^[0-9]{10}$/.test(String(value || '').trim());
                }, 'Please enter exactly 10 digits.');

            $.validator.addMethod('strictEmail', function (value, element) {
                if (!value) return true;
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
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
                    messages: {
                        client_name: { required: 'The Client Name field is required.' },
                        mobile: {
                            required: 'The Mobile field is required.',
                            minlength: 'Please enter exactly 10 digits.',
                            maxlength: 'Please enter exactly 10 digits.',
                            mobilePattern: 'Please enter exactly 10 digits.'
                        },
                        state_id: { required: 'The State field is required.' },
                        city_id: { required: 'The City field is required.' },
                        lead_category_id: { required: 'The Lead Category field is required.' },
                        lead_status_id: { required: 'The Lead Status field is required.' },
                        loss_reason: {
                            required: 'Loss reason is required when stage is set to Loss.',
                            maxlength: 'Loss reason must not exceed 1000 characters.'
                        },
                        'assigned_to[]': { required: 'Please select at least one employee.' },
                        estimated_value: { required: 'The Estimated Value field is required.' },
                        email: { strictEmail: 'Please enter a valid email address.' },
                        estimated_value: {
                            max: 'Please enter a realistic amount (max 8 digits).'
                        },
                        ...customFieldMessages
                    },
                    rules: {
                        client_name: { required: true, maxlength: 255 },
                        email: { strictEmail: true, maxlength: 255 },
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
                        loss_reason: {
                            required: function () {
                                return isLossStatusSelected();
                            },
                            maxlength: 1000
                        },
                        'assigned_to[]': { required: true },
                        // Realistic Price Validation (Max 10 Crore)
                        estimated_value: {
                            required: true,
                            min: 0,
                            max: 99999999.99
                        },
                        source: { maxlength: 100 },
                        referral_person_name: { maxlength: 191 },
                        address: { maxlength: 1000 },
                        ...customFieldRules
                    },
                    submitHandler: function (formEl) {
                        const assignedUsers = formEl.querySelectorAll('input[name="assigned_to[]"]');
                        if (!assignedUsers.length) {
                            if (window.Swal) {
                                Swal.fire({
                                    title: 'Validation Error',
                                    text: 'Please select at least one employee.',
                                    icon: 'error',
                                    confirmButtonColor: '#4f46e5',
                                });
                            }
                            return;
                        }

                        const submitNow = () => {
                            $btn.attr('disabled', true).addClass('opacity-75 cursor-not-allowed');
                            $text.text('Updating...');
                            formEl.submit();
                        };

                        if (window.Swal) {
                            Swal.fire({
                                title: 'Save Changes?',
                                text: 'Are you sure you want to update this lead?',
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, update',
                                cancelButtonText: 'Cancel'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    submitNow();
                                }
                            });
                            return;
                        }

                        if (window.confirm('Are you sure you want to save these changes?')) {
                            submitNow();
                        }
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
