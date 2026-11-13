<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class QuotationFormSchema
{
    public const ALLOWED_TYPES = ['text', 'textarea', 'number', 'date', 'select', 'email', 'tel'];

    /**
     * @return array{fields: array<int, array<string, mixed>>}
     */
    public static function fromJsonString(?string $json): array
    {
        $decoded = null;
        if (is_string($json) && trim($json) !== '') {
            $decoded = json_decode($json, true);
        }

        if (!is_array($decoded)) {
            throw ValidationException::withMessages([
                'schema' => 'Invalid form schema JSON.',
            ]);
        }

        $fields = $decoded['fields'] ?? null;
        if (!is_array($fields)) {
            throw ValidationException::withMessages([
                'schema' => 'Schema must contain a fields array.',
            ]);
        }

        $normalizedFields = [];
        $names = [];

        foreach (array_values($fields) as $index => $field) {
            if (!is_array($field)) {
                throw ValidationException::withMessages([
                    'schema' => "Field at index {$index} is invalid.",
                ]);
            }

            $label = trim((string) ($field['label'] ?? ''));
            $name = trim((string) ($field['name'] ?? ''));
            $type = trim((string) ($field['type'] ?? 'text'));
            $required = (bool) ($field['required'] ?? false);

            if ($label === '') {
                throw ValidationException::withMessages([
                    'schema' => "Field #".($index + 1)." label is required.",
                ]);
            }

            if ($name === '' || !preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $name)) {
                throw ValidationException::withMessages([
                    'schema' => "Field #".($index + 1)." name must start with a letter and contain only letters, numbers, and underscores (max 64 chars).",
                ]);
            }

            if (in_array($name, $names, true)) {
                throw ValidationException::withMessages([
                    'schema' => "Field name '{$name}' is duplicated.",
                ]);
            }
            $names[] = $name;

            if (!in_array($type, self::ALLOWED_TYPES, true)) {
                throw ValidationException::withMessages([
                    'schema' => "Field '{$name}' has an unsupported type '{$type}'.",
                ]);
            }

            $options = [];
            if ($type === 'select') {
                $optionsRaw = $field['options'] ?? [];
                if (is_string($optionsRaw)) {
                    $optionsRaw = array_map('trim', explode(',', $optionsRaw));
                }
                if (!is_array($optionsRaw)) {
                    throw ValidationException::withMessages([
                        'schema' => "Field '{$name}' options are invalid.",
                    ]);
                }
                $options = array_values(array_filter(array_map(fn ($o) => trim((string) $o), $optionsRaw), fn ($o) => $o !== ''));
                if (count($options) === 0) {
                    throw ValidationException::withMessages([
                        'schema' => "Field '{$name}' must have at least 1 option.",
                    ]);
                }
            }

            $normalizedFields[] = [
                'id' => (string) ($field['id'] ?? $name),
                'label' => $label,
                'name' => $name,
                'type' => $type,
                'required' => $required,
                'options' => $options,
            ];
        }

        return ['fields' => $normalizedFields];
    }
}

