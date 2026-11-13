<div class="form-group">
    <label for="{{ 'form-' . $form['id'] . '-' . $field['name'] }}" @if(isset($field['required'])) required @endif>{{ $field['label'] }}</label>
    <textarea
        id="{{ 'form-' . $form['id'] . '-' . $field['name'] }}"
        class="{{ $field['className'] }}"
        name="{{ $field['name'] }}"
        placeholder="{{ $field['placeholder'] ?? '' }}"
        @if(!empty($field['required'])) required @endif
        @if(!empty($field['disabled'])) disabled @endif
        >{{ old($field['name'], $field['value'] ?? '') }}</textarea>
    @if(isset($field['description']))
        <small class="form-text text-muted">{{ $field['description'] }}</small>
    @endif
</div>
