@props([
    'name' => 'assigned_to',
    'id' => 'assigned_to',
    'label' => 'Assign To',
    'visitDateInputId' => 'visit_date',
    'selected' => [],
    'workloadUrl' => null,
    'placeholder' => 'Search staff...',
    'emptyLabel' => 'Please select Visit Date first.',
    'helperText' => 'You can select multiple staff members.',
])

@php
    $resolvedWorkloadUrl = $workloadUrl ?: route('tickets.visit-date-workloads');
    $oldInput = old($name);
    if ($oldInput !== null) {
        $initialSelectedIds = collect(is_array($oldInput) ? $oldInput : [$oldInput])
            ->map(fn ($v) => (string) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();
    } else {
        $initialSelectedIds = collect(is_iterable($selected) ? $selected : ($selected ? [$selected] : []))
            ->map(fn ($v) => (string) $v)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
@endphp

<div
    x-data="{
        open: false,
        query: '',
        selectedIds: @js($initialSelectedIds),
        options: [],
        loading: false,
        hasVisitDate: false,
        visitDate: '',
        workloadUrl: @js($resolvedWorkloadUrl),
        visitDateInputId: @js($visitDateInputId),

        init() {
            const dateInput = document.getElementById(this.visitDateInputId);
            if (dateInput) {
                const updateDate = () => {
                    const newDate = (dateInput.value || '').trim();
                    if (newDate !== this.visitDate) {
                        this.visitDate = newDate;
                        this.hasVisitDate = Boolean(newDate);
                        if (this.hasVisitDate) {
                            this.fetchWorkload();
                        } else {
                            this.options = [];
                        }
                    }
                };

                dateInput.addEventListener('change', updateDate);
                dateInput.addEventListener('input', updateDate);

                // Initial load
                if (dateInput.value && dateInput.value.trim()) {
                    this.visitDate = dateInput.value.trim();
                    this.hasVisitDate = true;
                    this.fetchWorkload();
                }
            }
        },

        async fetchWorkload() {
            if (!this.visitDate) return;
            this.loading = true;
            try {
                const response = await fetch(`${this.workloadUrl}?visit_date=${encodeURIComponent(this.visitDate)}`, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                if (!response.ok) throw new Error('Failed to load workload');
                const data = await response.json();
                if (data && Array.isArray(data.assignees)) {
                    this.options = data.assignees.map(a => ({
                        id: String(a.id),
                        name: a.name || 'Staff',
                        label: a.label || `${a.name || 'Staff'} (${a.workload ?? 0} Jobs)`,
                        workload: a.workload ?? 0,
                    }));
                }
            } catch (e) {
                console.error(e);
            } finally {
                this.loading = false;
            }
        },

        toggleStaff(staff) {
            const id = String(staff.id);
            const idx = this.selectedIds.indexOf(id);
            if (idx > -1) {
                this.selectedIds.splice(idx, 1);
            } else {
                this.selectedIds.push(id);
            }
        },

        isSelected(id) {
            return this.selectedIds.includes(String(id));
        },

        removeStaff(id) {
            const idx = this.selectedIds.indexOf(String(id));
            if (idx > -1) {
                this.selectedIds.splice(idx, 1);
            }
        },

        selectAll() {
            this.filteredOptions().forEach(opt => {
                if (!this.selectedIds.includes(String(opt.id))) {
                    this.selectedIds.push(String(opt.id));
                }
            });
        },

        clearAll() {
            this.selectedIds = [];
        },

        filteredOptions() {
            const q = this.query.trim().toLowerCase();
            if (!q) return this.options;
            return this.options.filter(opt =>
                (opt.name && opt.name.toLowerCase().includes(q)) ||
                (opt.label && opt.label.toLowerCase().includes(q))
            );
        },

        getSelectedItems() {
            return this.selectedIds.map(id => {
                const found = this.options.find(opt => String(opt.id) === String(id));
                return found || { id: id, name: 'Staff #' + id, label: 'Staff #' + id, workload: null };
            });
        },

        buttonLabel() {
            if (!this.hasVisitDate) {
                return @js($emptyLabel);
            }
            if (this.loading) {
                return 'Loading staff workload...';
            }
            if (this.selectedIds.length === 0) {
                return 'Select staff (multiple allowed)...';
            }
            if (this.selectedIds.length === 1) {
                const first = this.getSelectedItems()[0];
                return first ? first.label : '1 staff selected';
            }
            return `${this.selectedIds.length} staff selected`;
        }
    }"
    class="relative"
    @click.outside="open = false"
>
    <x-input-label :for="$id" :value="$label" class="ml-1" />

    <!-- Trigger Button -->
    <div class="mt-1">
        <button
            type="button"
            class="flex w-full items-center justify-between rounded-xl border border-gray-300 bg-white px-4 py-2.5 text-left text-sm shadow-sm transition focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed"
            :disabled="!hasVisitDate || loading"
            @click="open = !open"
            @keydown.escape.window="open = false"
        >
            <span class="flex items-center gap-2 min-w-0 truncate">
                <span x-show="loading" class="animate-spin inline-block h-3.5 w-3.5 border-2 border-indigo-600 border-t-transparent rounded-full shrink-0"></span>
                <span class="truncate" x-text="buttonLabel()" :class="!hasVisitDate ? 'text-slate-400' : (selectedIds.length > 0 ? 'text-slate-900 font-medium' : 'text-slate-500')"></span>
            </span>
            <div class="flex items-center gap-1.5 ml-2 shrink-0">
                <span x-show="selectedIds.length > 0" class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700" x-text="selectedIds.length"></span>
                <svg class="h-4 w-4 text-slate-400 transition-transform duration-150" :class="{ 'rotate-180': open }" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.24 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.08z" clip-rule="evenodd" />
                </svg>
            </div>
        </button>

        <!-- Dropdown Panel -->
        <div
            x-show="open && hasVisitDate"
            x-transition
            class="absolute z-50 mt-2 w-full overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
            style="display: none;"
        >
            <!-- Search & Actions -->
            <div class="border-b border-slate-100 p-2.5 bg-slate-50/50 space-y-2">
                <div class="relative">
                    <input
                        type="search"
                        class="block w-full rounded-lg border-gray-300 pl-8 pr-3 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 placeholder:text-slate-400"
                        placeholder="{{ $placeholder }}"
                        x-model="query"
                        autocomplete="off"
                    >
                    <svg class="absolute left-2.5 top-2 h-3.5 w-3.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>
                <div class="flex items-center justify-between text-xs px-1 text-slate-500">
                    <span>Click staff to select/deselect</span>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="selectAll()" class="font-medium text-indigo-600 hover:text-indigo-800">Select All</button>
                        <span>&bull;</span>
                        <button type="button" @click="clearAll()" class="font-medium text-slate-500 hover:text-slate-700">Clear</button>
                    </div>
                </div>
            </div>

            <!-- Staff List -->
            <div class="max-h-[410px] overflow-y-auto overscroll-contain divide-y divide-slate-100 py-1">
                <template x-for="option in filteredOptions()" :key="option.id">
                    <div
                        @click="toggleStaff(option)"
                        class="flex items-center justify-between px-3 py-2 text-sm cursor-pointer transition select-none hover:bg-slate-50"
                        :class="isSelected(option.id) ? 'bg-indigo-50/70 font-medium' : 'text-slate-700'"
                    >
                        <div class="flex items-center gap-2.5 min-w-0 pr-2">
                            <input
                                type="checkbox"
                                :checked="isSelected(option.id)"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer pointer-events-none"
                            >
                            <span class="truncate text-slate-900" x-text="option.name"></span>
                        </div>
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded text-xs shrink-0 font-medium"
                            :class="option.workload > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'"
                            x-text="`${option.workload} Jobs`"
                        ></span>
                    </div>
                </template>

                <div x-show="filteredOptions().length === 0 && !loading" class="px-4 py-3 text-center text-xs text-slate-500">
                    No matching staff found.
                </div>
            </div>
        </div>
    </div>

    <!-- Selected Staff Tags / Badges -->
    <div class="mt-2 flex flex-wrap gap-1.5" x-show="selectedIds.length > 0">
        <template x-for="item in getSelectedItems()" :key="item.id">
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 border border-indigo-200 shadow-sm animate-fadeIn">
                <span x-text="item.label || item.name"></span>
                <button
                    type="button"
                    @click="removeStaff(item.id)"
                    class="rounded-full p-0.5 text-indigo-400 hover:bg-indigo-200 hover:text-indigo-800 focus:outline-none transition"
                    title="Remove"
                >
                    <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>
            </span>
        </template>
    </div>

    <!-- Hidden Inputs for Form Submission -->
    <template x-for="id in selectedIds" :key="id">
        <input type="hidden" name="{{ $name }}[]" :value="id">
    </template>

    @if(filled($helperText))
        <p class="mt-1 text-xs text-slate-500" x-show="selectedIds.length === 0">{{ $helperText }}</p>
    @endif
</div>

