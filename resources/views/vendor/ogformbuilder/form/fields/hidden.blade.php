<div class="form-group">
    <input
        type="hidden"
        name="{{ $field['name'] }}"
        id="{{ 'form-' . $form['id'] . '-' . $field['name'] }}"
        value="{{ ($prefill[$field['name']] ?? null) ?? ($field['value'] ?? '') }}"
    />
</div>
