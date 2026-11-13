@foreach(($fields ?? []) as $field)
    @php
        $fieldValue = data_get($values ?? [], $field['key']);
        if ($fieldValue === null) {
            $fieldValue = $field['type'] === 'checkbox' ? false : '';
        }
        $isTextarea = $field['type'] === 'textarea';
        $isChoiceField = in_array($field['type'], ['dropdown', 'radiobutton'], true);
        $inputType = in_array($field['type'], ['email', 'number', 'date'], true) ? $field['type'] : 'text';
        $inputName = "form_data[custom_fields][{$field['key']}]";
    @endphp

    <div @class(['md:col-span-2' => $isTextarea || $field['type'] === 'checkbox']) class="space-y-1">
        @if($field['type'] === 'checkbox')
            <label class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <input type="hidden" name="{{ $inputName }}" value="0">
                <input
                    type="checkbox"
                    name="{{ $inputName }}"
                    value="1"
                    class="mt-1 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    @checked((bool) $fieldValue)
                >
                <span>
                    <span class="block text-sm font-semibold text-slate-900">{{ $field['label'] }} @if($field['required']) <span class="text-rose-500">*</span> @endif</span>
                    @if(!empty($field['help_text']))
                        <span class="mt-1 block text-xs text-slate-500">{{ $field['help_text'] }}</span>
                    @endif
                </span>
            </label>
        @elseif($isTextarea)
            <x-input-label :value="$field['label']" />
            <textarea
                name="{{ $inputName }}"
                rows="3"
                class="mt-1 block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                placeholder="{{ $field['placeholder'] ?? '' }}"
                {{ $field['required'] ? 'required' : '' }}
            >{{ $fieldValue }}</textarea>
            @if(!empty($field['help_text']))
                <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
            @endif
        @elseif($isChoiceField)
            <x-input-label :value="$field['label']" />
            @if($field['type'] === 'radiobutton')
                <div class="mt-2 space-y-2 rounded-xl border border-gray-200 bg-white p-3">
                    @foreach(($field['options'] ?? []) as $option)
                        <label class="flex items-center gap-3 text-sm text-slate-700">
                            <input
                                type="radio"
                                name="{{ $inputName }}"
                                value="{{ $option }}"
                                class="border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                @checked($fieldValue === $option)
                                {{ $field['required'] ? 'required' : '' }}
                            >
                            <span>{{ $option }}</span>
                        </label>
                    @endforeach
                </div>
            @else
                <select
                    name="{{ $inputName }}"
                    class="mt-1 block w-full rounded-xl border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                    {{ $field['required'] ? 'required' : '' }}
                >
                    <option value="">Select an option...</option>
                    @foreach(($field['options'] ?? []) as $option)
                        <option value="{{ $option }}" @selected($fieldValue === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            @endif
            @if(!empty($field['help_text']))
                <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
            @endif
        @else
            <x-input-label :value="$field['label']" />
            <x-text-input
                type="{{ $inputType }}"
                name="{{ $inputName }}"
                class="mt-1 block w-full"
                value="{{ $fieldValue }}"
                placeholder="{{ $field['placeholder'] ?? '' }}"
                :required="(bool) $field['required']"
            />
            @if(!empty($field['help_text']))
                <p class="mt-1 text-xs text-slate-500">{{ $field['help_text'] }}</p>
            @endif
        @endif
    </div>
@endforeach
