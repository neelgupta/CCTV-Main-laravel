@props([
    'clients' => collect(),
    'selected' => '',
    'allowNew' => true,
    'newLabel' => 'New client',
    'label' => 'Select Existing Client',
    'searchPlaceholder' => 'Search clients by name, email, phone, or address',
    'helperText' => 'Type to quickly find one client and select only one record.',
])

@php
    $selectedValue = old('existing_client_id', $selected);
    if ($selectedValue === 'new') {
        $selectedValue = '';
    }
    $normalizedClients = collect($clients)->map(function ($client) {
        $name = (string) ($client->name ?? '');
        $email = (string) ($client->email ?? '');
        $mobile = (string) ($client->mobile ?? $client->phone ?? '');
        $address = (string) ($client->address ?? '');
        $searchText = trim(strtolower(implode(' ', array_filter([
            $name,
            $email,
            $mobile,
            $address,
        ]))));

        return [
            'id' => (string) ($client->id ?? ''),
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
            'address' => $address,
            'state_id' => (string) ($client->state_id ?? ''),
            'city_id' => (string) ($client->city_id ?? ''),
            'label' => $name . ($email !== '' ? ' (' . $email . ')' : ''),
            'search' => preg_replace('/\s+/', ' ', $searchText),
        ];
    })->values();
    $selectedClient = $normalizedClients->firstWhere('id', (string) $selectedValue);
    $selectedLabel = $selectedClient['label'] ?? $newLabel;
@endphp

<div
    x-data="{
        open: false,
        query: '',
        selectedId: @js((string) $selectedValue),
        selectedLabel: @js($selectedLabel),
        clients: @js($normalizedClients),
        newLabel: @js($newLabel),
        allowNew: @js($allowNew),
        setClient(client) {
            this.selectedId = client ? String(client.id) : '';
            this.selectedLabel = client ? client.label : this.newLabel;
            this.open = false;
            this.query = '';

            const hiddenSelect = this.$refs.hiddenSelect;
            if (hiddenSelect) {
                hiddenSelect.value = this.selectedId;
                hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        },
        filteredClients() {
            const query = this.query.trim().toLowerCase();
            if (!query) {
                return this.clients;
            }

            return this.clients.filter(client => String(client.search || '').includes(query));
        },
        isSelected(client) {
            return String(this.selectedId) === String(client.id);
        },
        selectById(value) {
            const client = this.clients.find(item => String(item.id) === String(value));
            this.setClient(client || null);
        },
        clearSelection() {
            this.setClient(null);
        },
    }"
    class="relative"
    @click.outside="open = false"
>
    <x-input-label for="existing_client_id" :value="$label" />

    <div class="mt-1">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-none border border-gray-300 bg-white px-4 py-3 text-left text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @click="open = !open"
        >
            <span class="min-w-0 truncate" x-text="selectedLabel || newLabel"></span>
            <svg class="ml-3 h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
            </svg>
        </button>

        <div x-show="open" x-transition class="absolute z-50 mt-2 w-full overflow-hidden rounded-none border border-slate-200 bg-white shadow-xl" style="display: none;">
            <div class="border-b border-slate-100 p-3">
                <input
                    type="search"
                    class="block w-full rounded-none border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="{{ $searchPlaceholder }}"
                    x-model="query"
                    @focus="open = true"
                    autocomplete="off"
                >
            </div>

            <div class="max-h-[410px] overflow-y-auto overscroll-contain py-1">
                <template x-if="allowNew">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                        :class="!selectedId ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'"
                        @click="clearSelection()"
                    >
                        <span x-text="newLabel"></span>
                        <span x-show="!selectedId" class="text-xs font-semibold text-indigo-600">Selected</span>
                    </button>
                </template>

                <template x-for="client in filteredClients()" :key="client.id">
                    <button
                        type="button"
                        class="flex w-full items-start justify-between gap-3 border-t border-slate-50 px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                        :class="isSelected(client) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'"
                        @click="setClient(client)"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium" x-text="client.name"></span>
                            <span class="block truncate text-xs text-slate-500" x-text="[client.email, client.mobile, client.address].filter(Boolean).join(' • ')"></span>
                        </span>
                        <span x-show="isSelected(client)" class="text-xs font-semibold text-indigo-600">Selected</span>
                    </button>
                </template>

                <div x-show="filteredClients().length === 0" class="px-4 py-3 text-sm text-slate-500">
                    No matching clients found.
                </div>
            </div>
        </div>
    </div>

    <select id="existing_client_id" name="existing_client_id" class="hidden" x-ref="hiddenSelect">
        <option value="" @selected((string) $selectedValue === '')>{{ $newLabel }}</option>
        @foreach($normalizedClients as $client)
            <option
                value="{{ $client['id'] }}"
                data-name="{{ $client['name'] }}"
                data-email="{{ $client['email'] }}"
                data-mobile="{{ $client['mobile'] }}"
                data-address="{{ $client['address'] }}"
                data-state-id="{{ $client['state_id'] }}"
                data-city-id="{{ $client['city_id'] }}"
                @selected((string) $selectedValue === (string) $client['id'])
            >{{ $client['label'] }}</option>
        @endforeach
    </select>
    <p class="mt-1 text-xs text-slate-500">{{ $helperText }}</p>
</div>
