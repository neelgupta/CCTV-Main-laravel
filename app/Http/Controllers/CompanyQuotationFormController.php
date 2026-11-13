<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ogwebsolutions\FormBuilder\Models\OgForm;

class CompanyQuotationFormController extends Controller
{
    public function edit()
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if($companyId <= 0, 403);

        // Get forms assigned to this company via pivot table
        $assignedForm = \App\Models\OgForm::with('companies')
            ->whereHas('companies', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->where('status', 'active')
            ->latest()
            ->first();

        // Get company's own override form
        $override = \App\Models\OgForm::query()
            ->where('form_key', 'quotation')
            ->where('company_id', $companyId)
            ->latest()
            ->first();

        // If company has override, use it
        if ($override) {
            $form = $override;
        }
        // If company has assigned form from super admin, use it as base
        elseif ($assignedForm) {
            $form = new \stdClass;
            $form->id = null;
            $form->title = $assignedForm->title ?? '';
            $form->fields = $assignedForm->fields ?? [];
            $form->status = 'inactive';
            $form->isOverride = true;
            $form->baseFormId = $assignedForm->id;
        }
        // No form assigned and no override - show empty
        else {
            $form = new \stdClass;
            $form->id = null;
            $form->title = '';
            $form->fields = [];
            $form->status = 'inactive';
        }

        return view('quotation-form.common-formbuilder', [
            'form' => $form,
            'action' => route('settings.quotation-form.update'),
            'isCompany' => true,
            'isNew' => ! $override,
        ]);
    }

    public function update(Request $request)
    {
        $companyId = (int) auth()->user()?->company_id;
        abort_if($companyId <= 0, 403);

        $request->validate([
            'title' => 'required|string|max:255',
            'form_json' => 'required|json',
            'status' => 'required|in:active,inactive',
        ], [
            'title.required' => 'Form title is required.',
            'form_json.required' => 'Form fields are required.',
        ]);

        $fields = json_decode($request->form_json, true);

        if (empty($fields) || ! is_array($fields) || count($fields) === 0) {
            return back()->withErrors(['form_json' => 'Please add at least one field to the form.'])->withInput();
        }

        $fieldErrors = [];
        foreach ($fields as $field) {
            $fieldType = $field['type'] ?? '';
            $fieldLabel = $field['label'] ?? 'Unnamed Field';
            $fieldName = $field['name'] ?? '';

            if (empty($fieldName) && $fieldType !== 'header' && $fieldType !== 'paragraph') {
                $fieldErrors[] = "Field '{$fieldLabel}' is missing a name attribute.";

                continue;
            }

            switch ($fieldType) {
                case 'text':
                    if (isset($field['required']) && $field['required']) {
                        if (isset($field['subtype'])) {
                            if ($field['subtype'] === 'email' && ! empty($field['value'])) {
                                if (! filter_var($field['value'], FILTER_VALIDATE_EMAIL)) {
                                    $fieldErrors[] = "{$fieldLabel}: Invalid email format in default value.";
                                }
                            } elseif ($field['subtype'] === 'url' && ! empty($field['value'])) {
                                if (! filter_var($field['value'], FILTER_VALIDATE_URL)) {
                                    $fieldErrors[] = "{$fieldLabel}: Invalid URL format in default value.";
                                }
                            } elseif ($field['subtype'] === 'tel' && ! empty($field['value'])) {
                                if (! preg_match('/^[\d\s\-\+\(\)]+$/', $field['value'])) {
                                    $fieldErrors[] = "{$fieldLabel}: Invalid phone format in default value.";
                                }
                            }
                        }
                        if (isset($field['minlength']) && isset($field['value'])) {
                            if (strlen($field['value']) < $field['minlength']) {
                                $fieldErrors[] = "{$fieldLabel}: Default value is shorter than minimum length ({$field['minlength']}).";
                            }
                        }
                        if (isset($field['maxlength']) && isset($field['value'])) {
                            if (strlen($field['value']) > $field['maxlength']) {
                                $fieldErrors[] = "{$fieldLabel}: Default value exceeds maximum length ({$field['maxlength']}).";
                            }
                        }
                    }
                    break;

                case 'number':
                    if (isset($field['required']) && $field['required']) {
                        if (isset($field['min']) && isset($field['value']) && $field['value'] !== '') {
                            if (floatval($field['value']) < floatval($field['min'])) {
                                $fieldErrors[] = "{$fieldLabel}: Default value is less than minimum ({$field['min']}).";
                            }
                        }
                        if (isset($field['max']) && isset($field['value']) && $field['value'] !== '') {
                            if (floatval($field['value']) > floatval($field['max'])) {
                                $fieldErrors[] = "{$fieldLabel}: Default value exceeds maximum ({$field['max']}).";
                            }
                        }
                    }
                    break;

                case 'textarea':
                    if (isset($field['required']) && $field['required']) {
                        if (isset($field['minlength']) && isset($field['value'])) {
                            if (strlen($field['value']) < $field['minlength']) {
                                $fieldErrors[] = "{$fieldLabel}: Default value is shorter than minimum length ({$field['minlength']}).";
                            }
                        }
                        if (isset($field['maxlength']) && isset($field['value'])) {
                            if (strlen($field['value']) > $field['maxlength']) {
                                $fieldErrors[] = "{$fieldLabel}: Default value exceeds maximum length ({$field['maxlength']}).";
                            }
                        }
                    }
                    break;

                case 'select':
                    if (isset($field['required']) && $field['required']) {
                        if (empty($field['values']) || count($field['values']) === 0) {
                            $fieldErrors[] = "{$fieldLabel}: Select field must have at least one option.";
                        }
                        $hasEmptyOption = false;
                        foreach ($field['values'] ?? [] as $option) {
                            if (empty($option['value'])) {
                                $hasEmptyOption = true;
                                break;
                            }
                        }
                        if ($hasEmptyOption) {
                            $fieldErrors[] = "{$fieldLabel}: Select options cannot have empty values.";
                        }
                    }
                    break;

                case 'checkbox-group':
                case 'radio-group':
                    if (isset($field['required']) && $field['required']) {
                        if (empty($field['values']) || count($field['values']) === 0) {
                            $fieldErrors[] = "{$fieldLabel}: At least one option is required.";
                        }
                    }
                    break;

                case 'file':
                    if (isset($field['extensions'])) {
                        $allowedExt = explode(',', strtolower($field['extensions']));
                        foreach ($allowedExt as $ext) {
                            $ext = trim($ext);
                            if (! preg_match('/^[a-z0-9]+$/', $ext)) {
                                $fieldErrors[] = "{$fieldLabel}: Invalid file extension '{$ext}'. Use only alphanumeric characters.";
                                break;
                            }
                        }
                    }
                    if (isset($field['max_size']) && intval($field['max_size']) <= 0) {
                        $fieldErrors[] = "{$fieldLabel}: Maximum file size must be greater than 0.";
                    }
                    break;

                case 'date':
                    if (! empty($field['value'])) {
                        $date = \DateTime::createFromFormat('Y-m-d', $field['value']);
                        if (! $date || $date->format('Y-m-d') !== $field['value']) {
                            $fieldErrors[] = "{$fieldLabel}: Invalid date format. Use YYYY-MM-DD.";
                        }
                        if (isset($field['min']) && $field['value'] < $field['min']) {
                            $fieldErrors[] = "{$fieldLabel}: Default date is before minimum date ({$field['min']}).";
                        }
                        if (isset($field['max']) && $field['value'] > $field['max']) {
                            $fieldErrors[] = "{$fieldLabel}: Default date is after maximum date ({$field['max']}).";
                        }
                    }
                    break;

                case 'hidden':
                    if (empty($field['name'])) {
                        $fieldErrors[] = "{$fieldLabel}: Hidden field must have a name.";
                    }
                    break;

                case 'captcha':
                    break;

                case 'button':
                    if (empty($field['label']) && empty($field['name'])) {
                        $fieldErrors[] = "{$fieldLabel}: Button must have a label or name.";
                    }
                    break;

                case 'autocomplete':
                    if (isset($field['required']) && $field['required']) {
                        if (empty($field['values']) || count($field['values']) === 0) {
                            $fieldErrors[] = "{$fieldLabel}: Autocomplete field must have at least one suggestion.";
                        }
                    }
                    break;

                case 'paragraph':
                case 'header':
                    break;

                default:
                    break;
            }
        }

        if (! empty($fieldErrors)) {
            return back()->withErrors(['fields' => implode(' ', $fieldErrors)])->withInput();
        }

        $override = OgForm::query()
            ->where('form_key', 'quotation')
            ->where('company_id', $companyId)
            ->latest()
            ->first();

        if ($override) {
            $override->update([
                'title' => $request->title,
                'fields' => $fields,
                'status' => $request->status,
            ]);
        } else {
            $override = OgForm::create([
                'title' => $request->title,
                'fields' => $fields,
                'status' => $request->status,
                'company_id' => $companyId,
                'form_key' => 'quotation',
            ]);
        }

        return redirect()->route('settings.quotation-form.edit')
            ->with('success', 'Quotation form saved successfully.');
    }

    public function reset()
    {
        $companyId = auth()->user()?->company_id;
        abort_if(! $companyId, 403);

        OgForm::query()
            ->where('form_key', 'quotation')
            ->where('company_id', (int) $companyId)
            ->delete();

        return redirect()->route('settings.quotation-form.edit')
            ->with('success', 'Quotation form reset to super admin default.');
    }
}
