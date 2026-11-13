@props([
    'name',
    'id',
    'label',
    'options' => collect(),
    'selected' => '',
    'placeholder' => 'Search...',
    'emptyLabel' => 'Select an option...',
    'helperText' => null,
    'disabled' => false,
])

@php
    $selectedValue = old($name, $selected);
    $normalizedOptions = collect($options)->map(function ($option) {
        $label = (string) ($option['label'] ?? $option['name'] ?? '');
        $search = trim(strtolower(implode(' ', array_filter([
            $label,
            $option['meta'] ?? '',
        ]))));

        return [
            'id' => (string) ($option['id'] ?? ''),
            'label' => $label,
            'search' => preg_replace('/\s+/', ' ', $search),
        ];
    })->values();
    $selectedOption = $normalizedOptions->firstWhere('id', (string) $selectedValue);
    $selectedLabel = $selectedOption['label'] ?? '';
@endphp

<div
    x-data="{
        open: false,
        query: '',
        selectedId: @js((string) $selectedValue),
        selectedLabel: @js($selectedLabel),
        options: @js($normalizedOptions),
        isDisabled: @js((bool) $disabled),
        setOption(option) {
            this.selectedId = option ? String(option.id) : '';
            this.selectedLabel = option ? option.label : '';
            this.query = '';
            this.open = false;

            const hiddenSelect = this.$refs.hiddenSelect;
            if (hiddenSelect) {
                hiddenSelect.value = this.selectedId;
                hiddenSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }

            this.$root.dispatchEvent(new CustomEvent('searchable-select:change', {
                bubbles: true,
                detail: {
                    id: hiddenSelect ? hiddenSelect.id : '',
                    name: hiddenSelect ? hiddenSelect.name : '',
                    value: this.selectedId,
                    label: this.selectedLabel,
                },
            }));
        },
        loadOptionsFromSelect() {
            const hiddenSelect = this.$refs.hiddenSelect;
            if (!hiddenSelect) return;

            this.isDisabled = Boolean(hiddenSelect.disabled);
            this.options = Array.from(hiddenSelect.options)
                .filter(option => option && option.value !== '')
                .map(option => ({
                    id: String(option.value),
                    label: option.textContent || '',
                    search: String(option.textContent || '').toLowerCase().replace(/\s+/g, ' ').trim(),
                }));

            const selected = this.options.find(option => String(option.id) === String(hiddenSelect.value || ''));
            this.selectedId = String(hiddenSelect.value || '');
            this.selectedLabel = selected ? selected.label : '';
        },
        filteredOptions() {
            const query = this.query.trim().toLowerCase();
            if (!query) {
                return this.options;
            }

            return this.options.filter(option => String(option.search || '').includes(query));
        },
        syncFromSelect() {
            this.loadOptionsFromSelect();
        }
    }"
    class="relative"
    @click.outside="open = false"
    x-init="
        loadOptionsFromSelect();
        $watch('open', value => { if (value) query = ''; });
        const hiddenSelect = $refs.hiddenSelect;
        if (hiddenSelect) {
            hiddenSelect.addEventListener('change', () => loadOptionsFromSelect());
            hiddenSelect.addEventListener('input', () => loadOptionsFromSelect());
            new MutationObserver(() => loadOptionsFromSelect()).observe(hiddenSelect, {
                childList: true,
                subtree: true,
                attributes: true,
                attributeFilter: ['disabled', 'value'],
            });
        }
    "
>
    <x-input-label :for="$id" :value="$label" />

    <div class="mt-1">
            <button
            type="button"
            class="flex w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-4 py-3 text-left text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
            @click="open = !open"
            @keydown.escape.window="open = false"
            :disabled="isDisabled"
        >
            <span class="min-w-0 truncate" x-text="selectedLabel || @js($emptyLabel)"></span>
            <svg class="ml-3 h-4 w-4 shrink-0 text-slate-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
            </svg>
        </button>

        <div x-show="open" x-transition class="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl" style="display: none;">
            <div class="border-b border-slate-100 p-3">
                <input
                    type="search"
                    class="block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="{{ $placeholder }}"
                    x-model="query"
                    @focus="open = true"
                    autocomplete="off"
                >
            </div>

            <div class="max-h-[410px] overflow-y-auto overscroll-contain py-1">
                <button
                    type="button"
                    class="flex w-full items-center justify-between px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                    :class="!selectedId ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'"
                    @click="setOption(null)"
                >
                    <span>{{ $emptyLabel }}</span>
                    <span x-show="!selectedId" class="text-xs font-semibold text-indigo-600">Selected</span>
                </button>

                <template x-for="option in filteredOptions()" :key="option.id">
                    <button
                        type="button"
                        class="flex w-full items-start justify-between gap-3 border-t border-slate-50 px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                        :class="String(selectedId) === String(option.id) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'"
                        @click="setOption(option)"
                    >
                        <span class="min-w-0">
                            <span class="block truncate font-medium" x-text="option.label"></span>
                        </span>
                        <span x-show="String(selectedId) === String(option.id)" class="text-xs font-semibold text-indigo-600">Selected</span>
                    </button>
                </template>

                <div x-show="filteredOptions().length === 0" class="px-4 py-3 text-sm text-slate-500">
                    No matching options found.
                </div>
            </div>
        </div>
    </div>

    <select id="{{ $id }}" name="{{ $name }}" class="hidden" x-ref="hiddenSelect">
        <option value="" @selected((string) $selectedValue === '')>{{ $emptyLabel }}</option>
        @foreach($normalizedOptions as $option)
            <option value="{{ $option['id'] }}" @selected((string) $selectedValue === (string) $option['id'])>{{ $option['label'] }}</option>
        @endforeach
    </select>

    @if(filled($helperText))
        <p class="mt-1 text-xs text-slate-500">{{ $helperText }}</p>
    @endif
</div>
