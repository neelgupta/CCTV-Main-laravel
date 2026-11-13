<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Ogwebsolutions\FormBuilder\Models\OgForm;

class QuotationCustomFieldController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_if(!$user, 403);

        $form = $this->targetForm();
        if (!$form) {
            return redirect()->route('quotation-form.entry');
        }

        return view('quotation-form.custom-field', [
            'ogForm' => $form,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_if(!$user, 403);

        $form = $this->targetForm();
        if (!$form) {
            return redirect()->route('quotation-form.entry');
        }

        $validated = $request->validate([
            'field_name' => 'required|string|max:64|regex:/^[a-zA-Z][a-zA-Z0-9_]*$/',
            'label' => 'required|string|max:255',
            'type' => 'required|string|in:input,number,textarea,select,multi_select,checkbox,date,datetime,color,hyperlink',
            'order' => 'nullable|integer|min:0|max:9999',
            'col_md' => 'nullable|integer|min:1|max:12',
            'disabled' => 'nullable|boolean',
            'admin_only' => 'nullable|boolean',
            'required' => 'nullable|boolean',
            'show_on_table' => 'nullable|boolean',
            'show_on_client_portal' => 'nullable|boolean',
            'options' => 'nullable|string',
        ]);

        $existingNames = collect($form->fields)->pluck('name')->filter()->map(fn ($n) => (string) $n)->all();
        if (in_array($validated['field_name'], $existingNames, true)) {
            throw ValidationException::withMessages([
                'field_name' => 'This field name already exists in the quotation form.',
            ]);
        }

        [$ogType, $ogSubtype, $extra] = $this->mapType($validated['type'], $validated['options'] ?? null);

        $field = array_merge([
            'type' => $ogType,
            'subtype' => $ogSubtype,
            'label' => $validated['label'],
            'name' => $validated['field_name'],
            'required' => (bool) ($validated['required'] ?? false),
            'disabled' => (bool) ($validated['disabled'] ?? false),
            'admin_only' => (bool) ($validated['admin_only'] ?? false),
            'show_on_table' => (bool) ($validated['show_on_table'] ?? false),
            'show_on_client_portal' => (bool) ($validated['show_on_client_portal'] ?? false),
            'order' => $validated['order'] ?? null,
            'grid_class' => 'col-md-' . (int) ($validated['col_md'] ?? 12),
            'className' => ($ogType === 'button')
                ? 'btn btn-primary og-builder-form-' . $form->id . '-btn'
                : 'form-control',
        ], $extra);

        $fields = collect($form->fields)->values();

        // Insert before submit button if present
        $buttonIndex = $fields->search(fn ($f) => is_array($f) && (($f['type'] ?? null) === 'button' || ($f['subtype'] ?? null) === 'submit'));
        if (is_int($buttonIndex) && $buttonIndex >= 0) {
            $fields->splice($buttonIndex, 0, [$field]);
        } else {
            $fields->push($field);
        }

        $fields = $this->sortFields($fields);

        $form->update([
            'fields' => $fields->values()->all(),
        ]);

        return back()->with('success', 'Custom field added to quotation form.');
    }

    private function targetForm(): ?OgForm
    {
        $user = auth()->user();
        $isSuperAdmin = $user && $user->isSuperAdmin();

        if ($isSuperAdmin) {
            $default = OgForm::query()
                ->where('form_key', 'quotation')
                ->whereNull('company_id')
                ->latest()
                ->first();

            abort_if(!$default, 404, 'Default quotation form not found. Create it from /formbuilder first.');
            return $default;
        }

        $companyId = (int) ($user?->company_id);
        abort_if($companyId <= 0, 403);

        $override = OgForm::query()
            ->where('form_key', 'quotation')
            ->where('company_id', $companyId)
            ->latest()
            ->first();

        return $override;
    }

    /**
     * @return array{0:string,1:?string,2:array<string,mixed>}
     */
    private function mapType(string $type, ?string $options): array
    {
        $extra = [];
        $subtype = null;

        switch ($type) {
            case 'input':
                return ['text', null, $extra];
            case 'number':
                return ['number', null, $extra];
            case 'textarea':
                return ['textarea', null, $extra];
            case 'select':
                $extra['values'] = $this->optionsToValues($options);
                return ['select', null, $extra];
            case 'multi_select':
                $extra['values'] = $this->optionsToValues($options);
                $extra['multiple'] = true;
                return ['select', null, $extra];
            case 'checkbox':
                $extra['values'] = $this->optionsToValues($options ?: 'Yes');
                return ['checkbox-group', null, $extra];
            case 'date':
                return ['date', null, $extra];
            case 'datetime':
                $subtype = 'datetime-local';
                return ['text', $subtype, $extra];
            case 'color':
                $subtype = 'color';
                return ['text', $subtype, $extra];
            case 'hyperlink':
                $subtype = 'url';
                return ['text', $subtype, $extra];
            default:
                return ['text', null, $extra];
        }
    }

    private function optionsToValues(?string $options): array
    {
        $items = collect(explode("\n", (string) $options))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->values();

        if ($items->isEmpty()) {
            return [];
        }

        return $items->map(function ($label) {
            return [
                'label' => $label,
                'value' => $label,
                'selected' => false,
            ];
        })->all();
    }

    private function sortFields($fields)
    {
        return collect($fields)->sort(function ($a, $b) {
            $aOrder = is_array($a) ? ($a['order'] ?? null) : null;
            $bOrder = is_array($b) ? ($b['order'] ?? null) : null;

            if ($aOrder === null && $bOrder === null) return 0;
            if ($aOrder === null) return 1;
            if ($bOrder === null) return -1;

            return (int) $aOrder <=> (int) $bOrder;
        });
    }
}
