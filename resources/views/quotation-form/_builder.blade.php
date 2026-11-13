@php
    $schema = $schema ?? ['fields' => []];
    $submitButtonText = $submitButtonText ?? 'Create Quotation';
    $showReset = (bool) ($showReset ?? false);
    $resetAction = $resetAction ?? null;
    $isOverrideActive = (bool) ($isOverrideActive ?? false);
@endphp

<div
    x-data="quotationFormBuilder(@js($schema), @js($submitButtonText))"
    class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100"
>
    <div class="mb-6 flex items-start justify-between gap-4">
        <div>
            <h3 class="text-lg font-bold text-gray-900">Fields</h3>
            <p class="text-sm text-gray-600 mt-1">
                Add / remove fields. Company users will see these fields on quotation creation.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <button
                type="button"
                @click="addField()"
                class="inline-flex items-center px-3 py-2 bg-indigo-600 rounded-md text-xs text-white hover:bg-indigo-700"
            >
                + Add Field
            </button>

            @if($showReset)
                <form method="POST" action="{{ $resetAction }}" onsubmit="return confirm('Reset company quotation form to super admin default?')">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        class="inline-flex items-center px-3 py-2 bg-gray-100 rounded-md text-xs text-gray-800 hover:bg-gray-200"
                        {{ $isOverrideActive ? '' : 'disabled' }}
                        title="{{ $isOverrideActive ? '' : 'No override to reset' }}"
                    >
                        Reset to Default
                    </button>
                </form>
            @endif
        </div>
    </div>

    <form method="POST" action="{{ $action }}" @submit="prepareBeforeSubmit()" class="space-y-6">
        @csrf

        <input type="hidden" name="schema" x-bind:value="JSON.stringify(schema)">

        <div class="space-y-4">
            <template x-if="schema.fields.length === 0">
                <div class="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-600">
                    No fields yet. Click “Add Field”.
                </div>
            </template>

            <template x-for="(field, idx) in schema.fields" x-bind:key="field.id">
                <div class="rounded-lg border border-gray-200 p-4">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-900" x-text="'Field #' + (idx + 1)"></div>
                        <button
                            type="button"
                            @click="removeField(idx)"
                            class="text-xs font-semibold text-red-700 hover:text-red-900"
                        >
                            Remove
                        </button>
                    </div>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-2">
                            <label class="block font-medium text-sm text-gray-700" x-bind:for="'label_' + field.id">Label</label>
                            <input
                                class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                x-bind:id="'label_' + field.id"
                                type="text"
                                x-model="field.label"
                                placeholder="e.g. Site Address"
                                @blur="autoName(field)"
                                required
                            >
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700" x-bind:for="'name_' + field.id">Name (key)</label>
                            <input
                                class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                x-bind:id="'name_' + field.id"
                                type="text"
                                x-model="field.name"
                                placeholder="e.g. site_address"
                                required
                            >
                            <p class="mt-1 text-xs text-gray-500">Letters/numbers/underscore only.</p>
                        </div>

                        <div>
                            <label class="block font-medium text-sm text-gray-700" x-bind:for="'type_' + field.id">Type</label>
                            <select
                                class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                x-bind:id="'type_' + field.id"
                                x-model="field.type"
                            >
                                <option value="text">Text</option>
                                <option value="textarea">Textarea</option>
                                <option value="number">Number</option>
                                <option value="date">Date</option>
                                <option value="select">Select</option>
                                <option value="email">Email</option>
                                <option value="tel">Phone</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        <div class="md:col-span-2" x-show="field.type === 'select'">
                            <label class="block font-medium text-sm text-gray-700" x-bind:for="'options_' + field.id">Options (comma separated)</label>
                            <input
                                class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                x-bind:id="'options_' + field.id"
                                type="text"
                                x-bind:value="(field.options || []).join(', ')"
                                @input="field.options = parseOptions($event.target.value)"
                                placeholder="e.g. Basic, Premium, Enterprise"
                            >
                        </div>

                        <div class="flex items-center gap-2">
                            <input
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                x-bind:id="'required_' + field.id"
                                x-model="field.required"
                            />
                            <label class="text-sm text-gray-700" x-bind:for="'required_' + field.id">Required</label>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="rounded-lg border border-gray-200 p-4">
            <h3 class="text-sm font-semibold text-gray-900">Submit Button</h3>
            <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                <div class="md:col-span-2">
                    <x-input-label for="submit_button_text" value="Button Text" />
                    <x-text-input
                        id="submit_button_text"
                        name="submit_button_text"
                        type="text"
                        class="mt-1 block w-full"
                        x-model="submitButtonText"
                        maxlength="50"
                        required
                    />
                    <x-input-error :messages="$errors->get('submit_button_text')" class="mt-2" />
                </div>
                <div class="md:col-span-1">
                    <x-primary-button class="w-full justify-center">
                        Save Form
                    </x-primary-button>
                </div>
            </div>
            <x-input-error :messages="$errors->get('schema')" class="mt-2" />
        </div>
    </form>
</div>

<script>
function quotationFormBuilder(initialSchema, initialButtonText) {
    const safeSchema = (initialSchema && typeof initialSchema === 'object') ? initialSchema : { fields: [] };

    const normalizeField = (raw) => {
        const id = raw?.id || (crypto?.randomUUID ? crypto.randomUUID() : ('f_' + Date.now() + '_' + Math.random().toString(16).slice(2)));
        return {
            id,
            label: raw?.label || '',
            name: raw?.name || '',
            type: raw?.type || 'text',
            required: !!raw?.required,
            options: Array.isArray(raw?.options) ? raw.options : [],
        };
    };

    return {
        schema: {
            fields: Array.isArray(safeSchema.fields) ? safeSchema.fields.map(normalizeField) : [],
        },
        submitButtonText: initialButtonText || 'Create Quotation',
        parseOptions(text) {
            return String(text || '')
                .split(',')
                .map(s => s.trim())
                .filter(Boolean);
        },
        slugify(text) {
            return String(text || '')
                .trim()
                .toLowerCase()
                .replace(/[^a-z0-9]+/g, '_')
                .replace(/^_+|_+$/g, '')
                .replace(/^[^a-z]+/, 'field_');
        },
        autoName(field) {
            if (!field || field.name) return;
            field.name = this.slugify(field.label);
        },
        addField() {
            this.schema.fields.push(normalizeField({}));
        },
        removeField(idx) {
            this.schema.fields.splice(idx, 1);
        },
        prepareBeforeSubmit() {
            // Ensure all select fields have options array
            this.schema.fields.forEach(f => {
                if (f.type !== 'select') {
                    f.options = [];
                } else if (!Array.isArray(f.options)) {
                    f.options = [];
                }
            });
        }
    };
}
</script>
