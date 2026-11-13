<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Company;
use App\Models\LeadQuotation;
use App\Models\LeadStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ogwebsolutions\FormBuilder\Models\OgForm;
use Ogwebsolutions\FormBuilder\Models\OgFormSubmission;
use Ogwebsolutions\FormBuilder\Models\OgFormSubmissionValue;
use App\Services\MediaUploadService;

class QuotationFormSubmissionController extends Controller
{
    public function store(Request $request, OgForm $form)
    {
        $request->validate([
            'lead_id' => 'required|integer|exists:leads,id',
        ]);

        $leadId = (int) $request->input('lead_id');
        $lead = Lead::query()->findOrFail($leadId);

        $user = auth()->user();
        if ($user && !$user->isSuperAdmin() && (int) $lead->company_id !== (int) $user->company_id) {
            abort(403);
        }

        $formFields = collect($form->fields);

        $inputKeys = $formFields->filter(function ($field) {
            return in_array($field['type'] ?? '', [
                'text',
                'number',
                'date',
                'textarea',
                'autocomplete',
                'checkbox-group',
                'file',
                'select',
                'hidden',
                'radio-group',
                'email',
                'tel',
            ], true);
        });

        $requiredFields = $inputKeys->filter(function ($field) {
            return (bool) ($field['required'] ?? false);
        });

        $validator = Validator::make(
            $request->all(),
            $requiredFields->flatMap(function ($field) {
                return [($field['name'] ?? '') => 'required'];
            })->toArray(),
            $requiredFields->flatMap(function ($field) {
                $label = $field['label'] ?? $field['name'];
                return [($field['name'] ?? '') . '.required' => "The {$label} field is required."];
            })->toArray()
        );

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $form->load('settings');

        $submission = OgFormSubmission::create([
            'og_form_id' => $form->id,
            'token' => $request->input('_token'),
            'ip' => $request->ip(),
        ]);

        foreach ($request->only($inputKeys->pluck('name')->filter()->toArray()) as $key => $value) {
            $fieldDefinition = $formFields->firstWhere('name', $key);
            $label = $fieldDefinition['label'] ?? $key;

            if ($request->hasFile($key)) {
                $file = $request->file($key);
                $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
                $companyFolder = 'company_' . $lead->company_id . '/form_uploads';
                $filename = app(MediaUploadService::class)->store($file, $companyFolder, $disk);

                OgFormSubmissionValue::create([
                    'og_form_submission_id' => $submission->id,
                    'key' => $key,
                    'label' => $label,
                    'value' => $filename,
                ]);
            } else {
                $inputVal = is_array($value) ? implode(', ', $value) : $value;
                $inputVal = is_string($inputVal) ? trim($inputVal) : $inputVal;

                $submissionValue = OgFormSubmissionValue::create([
                    'og_form_submission_id' => $submission->id,
                    'key' => $key,
                    'label' => $label,
                    'value' => is_string($inputVal) && strlen($inputVal) <= 255 ? $inputVal : '',
                ]);

                if (is_string($inputVal) && strlen($inputVal) > 255) {
                    $submissionValue->largeValue()->create(['value' => $inputVal]);
                }
            }
        }

        $this->tryCreateLeadQuotationFromSubmission($form, $submission, $leadId);

        $redirectUrl = $form->settings?->redirect_url ?: null;
        $successMessage = $form->settings?->success_message ?: 'Form submitted successfully.';

        return response()->json([
            'status' => 'success',
            'message' => $successMessage,
            'redirectUrl' => $redirectUrl,
        ], 200);
    }

    private function tryCreateLeadQuotationFromSubmission(OgForm $form, OgFormSubmission $submission, int $leadId): void
    {
        if (($form->form_key ?? null) !== 'quotation') {
            return;
        }

        $submission->loadMissing(['values.largeValue']);

        $valueMap = [];
        foreach ($submission->values as $val) {
            $valueMap[(string) $val->key] = $val->largeValue?->value ?? $val->value;
        }

        if ($leadId <= 0) {
            return;
        }

        $lead = Lead::query()->find($leadId);
        if (!$lead) {
            return;
        }

        if (!empty($form->company_id) && (int) $lead->company_id !== (int) $form->company_id) {
            return;
        }

        if (LeadQuotation::query()->where('og_form_submission_id', $submission->id)->exists()) {
            return;
        }

        $amount = $valueMap['amount'] ?? null;
        $notes = isset($valueMap['notes']) ? (string) $valueMap['notes'] : null;
        $version = $lead->company?->reserveNextQuotationVersion()['version'] ?? (string) ($valueMap['version'] ?? '');

        if (!is_numeric($amount)) {
            return;
        }

        $reserved = ['lead_id', 'amount', 'version', 'notes', '_token'];
        $dynamic = collect($valueMap)->except($reserved)->all();

        $lead->quotations()->create([
            'file_path' => '',
            'source' => 'form',
            'amount' => (float) $amount,
            'version' => $version,
            'status' => 'sent',
            'notes' => $notes,
            'form_schema' => $form->fields,
            'form_data' => $dynamic,
            'og_form_submission_id' => $submission->id,
        ]);
        $proposalSentStatus = LeadStatus::ensureProposalSent((int) $lead->company_id);
        if ((int) $lead->lead_status_id !== (int) $proposalSentStatus->id) {
            $lead->update(['lead_status_id' => $proposalSentStatus->id]);
        }

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'quotation',
            'description' => "Created form quotation version {$version} for amount â‚¹{$amount}",
        ]);
    }
}
