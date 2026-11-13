<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ __('Add new custom field') }}
            </h2>
            <div class="flex items-center gap-2">
                <a href="{{ route('formbuilder.edit', $ogForm->id) }}" class="inline-flex items-center px-3 py-2 bg-gray-100 rounded-md text-xs text-gray-800 hover:bg-gray-200">
                    Open Drag & Drop Builder
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-4">
            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <form method="POST" action="{{ route('quotation-form.custom-fields.store') }}">
                    @csrf

                    <div class="grid grid-cols-1 gap-4">
                        <div class="rounded-md bg-sky-50 border border-sky-100 p-3 text-sm text-sky-900">
                            {{ $ogForm->title }}
                        </div>

                        <div>
                            <x-input-label for="belongs_to" value="* Field Belongs to" />
                            <select id="belongs_to" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" disabled>
                                <option selected>Quote</option>
                            </select>
                        </div>

                        <div>
                            <x-input-label for="field_name" value="* Field Name" />
                            <x-text-input id="field_name" name="field_name" class="mt-1 block w-full" value="{{ old('field_name') }}" required />
                            <p class="mt-1 text-xs text-gray-500">Example: <code>test</code>, <code>site_address</code> (letters/numbers/underscore only)</p>
                            <x-input-error :messages="$errors->get('field_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="label" value="* Label" />
                            <x-text-input id="label" name="label" class="mt-1 block w-full" value="{{ old('label') }}" required />
                            <x-input-error :messages="$errors->get('label')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="type" value="* Type" />
                            <select id="type" name="type" class="mt-1 block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm" required>
                                <option value="input" {{ old('type') === 'input' ? 'selected' : '' }}>Input</option>
                                <option value="number" {{ old('type') === 'number' ? 'selected' : '' }}>Number</option>
                                <option value="textarea" {{ old('type') === 'textarea' ? 'selected' : '' }}>Textarea</option>
                                <option value="select" {{ old('type') === 'select' ? 'selected' : '' }}>Select</option>
                                <option value="multi_select" {{ old('type') === 'multi_select' ? 'selected' : '' }}>Multi Select</option>
                                <option value="checkbox" {{ old('type') === 'checkbox' ? 'selected' : '' }}>Checkbox</option>
                                <option value="date" {{ old('type') === 'date' ? 'selected' : '' }}>Date Picker</option>
                                <option value="datetime" {{ old('type') === 'datetime' ? 'selected' : '' }}>Datetime Picker</option>
                                <option value="color" {{ old('type') === 'color' ? 'selected' : '' }}>Color Picker</option>
                                <option value="hyperlink" {{ old('type') === 'hyperlink' ? 'selected' : '' }}>Hyperlink</option>
                            </select>
                            <x-input-error :messages="$errors->get('type')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="order" value="Order" />
                                <x-text-input id="order" name="order" type="number" class="mt-1 block w-full" value="{{ old('order') }}" />
                                <x-input-error :messages="$errors->get('order')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="col_md" value="* Grid (Bootstrap Column eq. 12) - Max is 12" />
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="inline-flex items-center px-2 py-2 rounded-md bg-gray-50 border border-gray-200 text-xs text-gray-600">col-md-</span>
                                    <x-text-input id="col_md" name="col_md" type="number" class="block w-full" value="{{ old('col_md', 12) }}" min="1" max="12" required />
                                </div>
                                <x-input-error :messages="$errors->get('col_md')" class="mt-2" />
                            </div>
                        </div>

                        <div>
                            <x-input-label for="options" value="Options (for Select / Multi Select / Checkbox) - one per line" />
                            <textarea id="options" name="options" rows="4" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">{{ old('options') }}</textarea>
                            <x-input-error :messages="$errors->get('options')" class="mt-2" />
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Flags</div>
                                <div class="mt-2 space-y-2">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="disabled" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('disabled') ? 'checked' : '' }}>
                                        Disabled
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="admin_only" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('admin_only') ? 'checked' : '' }}>
                                        Restrict visibility for administrators only
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="required" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('required') ? 'checked' : '' }}>
                                        Required
                                    </label>
                                </div>
                            </div>
                            <div>
                                <div class="text-sm font-semibold text-gray-900">Visibility</div>
                                <div class="mt-2 space-y-2">
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="show_on_table" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('show_on_table') ? 'checked' : '' }}>
                                        Show on table
                                    </label>
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="show_on_client_portal" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ old('show_on_client_portal') ? 'checked' : '' }}>
                                        Show on client portal
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>Save</x-primary-button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100">
                <h3 class="text-base font-bold text-gray-900 mb-3">Existing fields</h3>
                <div class="overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Label</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grid</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Flags</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach(($ogForm->fields ?? []) as $field)
                                @php
                                    if (!is_array($field)) continue;
                                    $name = $field['name'] ?? '';
                                    if ($name === 'lead_id') continue;
                                @endphp
                                <tr>
                                    <td class="px-4 py-2 text-sm text-gray-900">{{ $name }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $field['label'] ?? '' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $field['type'] ?? '' }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-700">{{ $field['grid_class'] ?? '' }}</td>
                                    <td class="px-4 py-2 text-xs text-gray-600">
                                        @if(!empty($field['required'])) req @endif
                                        @if(!empty($field['disabled'])) dis @endif
                                        @if(!empty($field['admin_only'])) admin @endif
                                        @if(!empty($field['show_on_table'])) table @endif
                                        @if(!empty($field['show_on_client_portal'])) portal @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

