<x-app-layout>
    <x-slot name="header">
        <div class="rounded-none border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Edit Client</h2>
            <p class="mt-1 text-sm text-slate-500">Update the client profile and contact details.</p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-none border border-slate-200 bg-white p-5 shadow-sm">
                <form method="POST" action="{{ route('settings.clients.update', $client) }}" class="space-y-5">
                    @csrf
                    @method('PATCH')
                    @php
                        $cityOptionsByState = $states->mapWithKeys(function ($state) {
                            return [
                                $state->id => $state->cities->map(fn ($city) => [
                                    'id' => $city->id,
                                    'name' => $city->name,
                                ])->values(),
                            ];
                        });
                    @endphp
                    <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <x-input-label for="name" value="Client Name" />
                            <x-text-input id="name" name="name" class="mt-1 block w-full rounded-none" :value="old('name', $client->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="email" value="Email" />
                            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full rounded-none" :value="old('email', $client->email)" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="phone" value="Phone" />
                            <x-text-input
                                id="phone"
                                name="phone"
                                class="mt-1 block w-full rounded-none"
                                :value="old('phone', $client->phone ?: $client->mobile)"
                                maxlength="20"
                                oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 20);"
                            />
                            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="gst_number" value="GST Number" />
                            <x-text-input id="gst_number" name="gst_number" class="mt-1 block w-full rounded-none" :value="old('gst_number', $client->gst_number)" placeholder="22AAAAA0000A1Z5" oninput="this.value = this.value.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 15);" />
                            <x-input-error :messages="$errors->get('gst_number')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="pan_number" value="PAN Number" />
                            <x-text-input id="pan_number" name="pan_number" class="mt-1 block w-full rounded-none" :value="old('pan_number', $client->pan_number)" placeholder="AAAAA1234A" oninput="this.value = this.value.toUpperCase().replace(/[^0-9A-Z]/g, '').slice(0, 10);" />
                            <x-input-error :messages="$errors->get('pan_number')" class="mt-2" />
                        </div>
                        <div>
                            <x-searchable-select
                                name="state_id"
                                id="state_id"
                                label="State"
                                :options="$states->map(fn ($state) => ['id' => $state->id, 'label' => $state->name])"
                                :selected="old('state_id', $client->state_id)"
                                placeholder="Search state..."
                                empty-label="Select state"
                            />
                            <x-input-error :messages="$errors->get('state_id')" class="mt-2" />
                        </div>
                        <div>
                            <x-searchable-select
                                name="city_id"
                                id="city_id"
                                label="City"
                                :options="collect()"
                                :selected="old('city_id', $client->city_id)"
                                placeholder="Search city..."
                                empty-label="Select city"
                                :disabled="true"
                            />
                            <x-input-error :messages="$errors->get('city_id')" class="mt-2" />
                        </div>
                        <div class="md:col-span-2">
                            <x-input-label for="address" value="Address" />
                            <div class="relative mt-1">
                                <textarea id="address" name="address" rows="4" class="block w-full rounded-none border-slate-300 focus:border-cyan-500 focus:ring-cyan-500" autocomplete="off" data-location-autocomplete="client-address" data-autocomplete-url="{{ config('services.location_autocomplete.url') }}">{{ old('address', $client->address) }}</textarea>
                                <div
                                    id="address-suggestions"
                                    class="absolute left-0 right-0 z-50 mt-2 hidden overflow-hidden rounded-none border border-slate-200 bg-white shadow-xl"
                                    role="listbox"
                                    aria-label="Client address suggestions"
                                ></div>
                            </div>
                            <x-input-error :messages="$errors->get('address')" class="mt-2" />
                        </div>
                        <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $client->latitude) }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $client->longitude) }}">
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <a href="{{ route('settings.clients.show', $client) }}" class="rounded-none border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</a>
                        <button type="submit" class="rounded-none bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-cyan-500">Update Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const cityOptionsByState = @js($cityOptionsByState);
            const stateSelect = document.getElementById('state_id');
            const citySelect = document.getElementById('city_id');
            const selectedCityId = @js(old('city_id', $client->city_id));
            const addressInput = document.getElementById('address');
            const addressSuggestions = document.getElementById('address-suggestions');
            const addressAutocompleteUrl = addressInput?.dataset.autocompleteUrl || '';
            let addressAutocompleteTimer = null;
            let addressAutocompleteAbortController = null;
            let addressSuggestionsList = [];
            let highlightedAddressIndex = -1;
            let suppressAddressLookup = false;

            function renderCities(stateId, keepCityId = '') {
                if (!citySelect) return;

                const cities = cityOptionsByState[String(stateId)] || [];
                citySelect.innerHTML = '<option value="">Select city</option>';
                cities.forEach((city) => {
                    const option = document.createElement('option');
                    option.value = String(city.id);
                    option.textContent = city.name;
                    if (String(keepCityId) === String(city.id)) {
                        option.selected = true;
                    }
                    citySelect.appendChild(option);
                });
                citySelect.disabled = !cities.length;
                citySelect.dispatchEvent(new Event('change', { bubbles: true }));
                citySelect.dispatchEvent(new Event('input', { bubbles: true }));
            }

            stateSelect?.addEventListener('change', function () {
                renderCities(this.value);
            });

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

            addressInput?.addEventListener('input', queueAddressAutocompleteLookup);
            addressInput?.addEventListener('keydown', function (event) {
                if (addressSuggestions?.classList.contains('hidden') || !addressSuggestionsList.length) {
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

            const clientLatitudeInput = document.getElementById('latitude');
            const clientLongitudeInput = document.getElementById('longitude');

            if (window.google?.maps?.places && addressInput && clientLatitudeInput && clientLongitudeInput) {
                const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                    componentRestrictions: { country: ['in'] },
                    fields: ['formatted_address', 'geometry'],
                });

                autocomplete.addListener('place_changed', function () {
                    const place = autocomplete.getPlace();
                    if (!place.geometry) {
                        return;
                    }

                    addressInput.value = place.formatted_address || addressInput.value;
                    clientLatitudeInput.value = place.geometry.location.lat();
                    clientLongitudeInput.value = place.geometry.location.lng();
                });
            }

            renderCities(stateSelect?.value || '', selectedCityId);
        });
    </script>
</x-app-layout>
