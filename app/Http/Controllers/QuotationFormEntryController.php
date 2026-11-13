<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Ogwebsolutions\FormBuilder\Models\OgForm;
use Ogwebsolutions\FormBuilder\Models\OgFormSetting;

class QuotationFormEntryController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_if(! $user, 403);

        if ($user->isSuperAdmin()) {
            $defaultForm = OgForm::query()
                ->where('form_key', 'quotation')
                ->whereNull('company_id')
                ->latest()
                ->first();

            return $defaultForm
                ? redirect()->route('formbuilder.edit', $defaultForm->id)
                : redirect()->route('formbuilder.create');
        }

        return redirect()->route('settings.quotation-form.edit');
    }

    public function createOverride(Request $request)
    {
        $user = auth()->user();
        abort_if(! $user || $user->isSuperAdmin(), 403);

        $companyId = (int) $user->company_id;
        abort_if($companyId <= 0, 403);

        $existing = OgForm::query()
            ->where('form_key', 'quotation')
            ->where('company_id', $companyId)
            ->first();

        if ($existing) {
            return redirect()->route('formbuilder.edit', $existing->id);
        }

        $defaultForm = OgForm::query()
            ->where('form_key', 'quotation')
            ->whereNull('company_id')
            ->latest()
            ->first();

        if (! $defaultForm) {
            return back()->with('error', 'Super admin has not configured a default quotation form yet.');
        }

        $defaultForm->loadMissing('settings');

        $override = OgForm::create([
            'title' => $defaultForm->title ?: 'Quotation Form',
            'fields' => $defaultForm->fields,
            'status' => $defaultForm->status ?: 'inactive',
            'company_id' => $companyId,
            'form_key' => 'quotation',
        ]);

        $defaultSettings = $defaultForm->settings;
        if ($defaultSettings) {
            OgFormSetting::create([
                'og_form_id' => $override->id,
                'admin_email_enabled' => (bool) $defaultSettings->admin_email_enabled,
                'admin_email_subject' => $defaultSettings->admin_email_subject,
                'admin_email_body' => $defaultSettings->admin_email_body,
                'admin_emails' => $defaultSettings->admin_emails,
                'customer_email_enabled' => (bool) $defaultSettings->customer_email_enabled,
                'customer_email_subject' => $defaultSettings->customer_email_subject,
                'customer_email_body' => $defaultSettings->customer_email_body,
                'customer_emails' => $defaultSettings->customer_emails,
                'success_message' => $defaultSettings->success_message,
                'failure_message' => $defaultSettings->failure_message,
                'redirect_url' => $defaultSettings->redirect_url,
                'admin_csv_enabled' => (bool) $defaultSettings->admin_csv_enabled,
            ]);
        }

        return redirect()->route('formbuilder.edit', $override->id)
            ->with('success', 'Company quotation form override created. You can now edit it.');
    }
}
