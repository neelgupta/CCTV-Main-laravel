<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyGstRate;
use App\Models\CompanyHoliday;
use App\Models\CompanyUnit;
use App\Models\Client;
use App\Models\Lead;
use App\Models\LeadQuotation;
use App\Models\LeadCategory;
use App\Models\Contractor;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\ServiceStage;
use App\Models\QuotationTemplate;
use App\Models\State;
use App\Models\Ticket;
use App\Models\User;
use App\Services\MediaUploadService;
use App\Services\LetterheadPdfConverter;
use App\Services\LetterheadPdfService;
use App\Services\DailyStaffUpdateReportService;
use App\Services\LeadAssignmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanySettingsController extends Controller
{
    private function resolveCompanyForCurrentUser(): ?Company
    {
        $user = auth()->user();
        if (!$user) {
            return null;
        }

        if ($user->company_id) {
            return Company::query()->find($user->company_id);
        }

        return null;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $company = $this->resolveCompanyForCurrentUser();

        if (!$company && $user->roleRecord?->company_id) {
            $company = Company::query()->find($user->roleRecord->company_id);
        }

        abort_unless($company, 404);
        $company->loadMissing('mailSetting');

        $requestedTab = $request->string('tab')->toString();
        $tabAliases = [
            'company-info' => 'company-details',
            'branding' => 'company-details',
            'categories' => 'lead-pipeline',
            'projects' => 'project-pipeline',
            'service-pipeline' => 'service-settings',
            'project-settings' => 'project-pipeline',
            'unit-master' => 'crm',
            'gst-master' => 'crm',
        ];
        $requestedTab = $tabAliases[$requestedTab] ?? $requestedTab;

        $activeTab = in_array($requestedTab, [
            'company-details',
            'lead-pipeline',
            'project-pipeline',
            'service-settings',
            'crm',
            'invoice-settings',
            'quotation-settings',
            'letterhead-settings',
            'daily-update-history',
            'payroll',
            'email',
            'project-settings',
            'unit-master',
            'gst-master',
            'task-master',
        ], true) ? $requestedTab : 'company-details';

        $categories = LeadCategory::query()
            ->where(function ($query) use ($company) {
                $query->whereNull('company_id')->orWhere('company_id', $company->id);
            })
            ->orderBy('name')
            ->paginate(10, ['*'], 'categories_page')
            ->withQueryString();

        $leadSources = collect($company->normalizedLeadSources())
            ->values();
        $leadSourceNames = $leadSources
            ->pluck('name')
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values();
        $leadSourcesPage = LengthAwarePaginator::resolveCurrentPage('lead_sources_page');
        $leadSourcesPerPage = 5;
        $leadSources = new LengthAwarePaginator(
            $leadSources->forPage($leadSourcesPage, $leadSourcesPerPage),
            $leadSources->count(),
            $leadSourcesPerPage,
            $leadSourcesPage,
            [
                'path' => $request->url(),
                'pageName' => 'lead_sources_page',
            ]
        );
        $leadSources->withQueryString();

        $leadCustomFieldLabels = collect($company->normalizedLeadCustomFields())
            ->pluck('label')
            ->map(fn ($label) => trim((string) $label))
            ->filter()
            ->values();
        $leadCustomFields = collect($company->normalizedLeadCustomFields())
            ->values();
        $leadCustomFieldsPage = LengthAwarePaginator::resolveCurrentPage('lead_custom_fields_page');
        $leadCustomFieldsPerPage = 8;
        $leadCustomFields = new LengthAwarePaginator(
            $leadCustomFields->forPage($leadCustomFieldsPage, $leadCustomFieldsPerPage),
            $leadCustomFields->count(),
            $leadCustomFieldsPerPage,
            $leadCustomFieldsPage,
            [
                'path' => $request->url(),
                'pageName' => 'lead_custom_fields_page',
            ]
        );
        $leadCustomFields->withQueryString();

        $projects = Project::query()
            ->where('company_id', $company->id)
            ->latest('created_at')
            ->limit(10)
            ->get();

        $users = User::query()
            ->where('company_id', $company->id)
            ->with('roleRecord:id,name,slug')
            ->orderBy('name')
            ->get();

        $holidays = CompanyHoliday::query()
            ->where('company_id', $company->id)
            ->orderBy('holiday_date')
            ->paginate(10, ['*'], 'holidays_page')
            ->withQueryString();

        $statuses = \App\Models\LeadStatus::query()
            ->tap(fn () => \App\Models\LeadStatus::ensureNewLead($company->id))
            ->tap(fn () => \App\Models\LeadStatus::ensureProposalSent($company->id))
            ->tap(fn () => \App\Models\LeadStatus::ensureWon($company->id))
            ->tap(fn () => \App\Models\LeadStatus::ensureLoss($company->id))
            ->tap(fn () => \App\Models\LeadStatus::normalizeSystemStages($company->id))
            ->where('company_id', $company->id)
            ->orderBy('order')
            ->paginate(10, ['*'], 'statuses_page')
            ->withQueryString();

        $projectStages = ProjectStage::query()
            ->tap(fn () => ProjectStage::ensureDefaults($company->id))
            ->where('company_id', $company->id)
            ->orderBy('order')
            ->paginate(10, ['*'], 'project_stages_page')
            ->withQueryString();

        $serviceStages = ServiceStage::query()
            ->tap(fn () => ServiceStage::ensureDefaults($company->id))
            ->where('company_id', $company->id)
            ->orderBy('order')
            ->get();

        $serviceStatusOptions = Ticket::statusOptions();

        $unitsQuery = CompanyUnit::query()
            ->where('company_id', $company->id)
            ->orderBy('name');
        $unitsPage = LengthAwarePaginator::resolveCurrentPage('units_page');
        $unitsPerPage = 5;
        $units = $unitsQuery->paginate($unitsPerPage, ['*'], 'units_page', $unitsPage)
            ->withQueryString();

        $gstRatesQuery = CompanyGstRate::query()
            ->where('company_id', $company->id)
            ->orderBy('rate');
        $gstRatesPage = LengthAwarePaginator::resolveCurrentPage('gst_rates_page');
        $gstRatesPerPage = 5;
        $gstRates = $gstRatesQuery->paginate($gstRatesPerPage, ['*'], 'gst_rates_page', $gstRatesPage)
            ->withQueryString();

        $contractors = Contractor::query()
            ->where('company_id', $company->id)
            ->withCount('projects')
            ->orderBy('name')
            ->get();

        $taskMasters = collect($company->task_master_list ?? [])
            ->map(function ($value) {
                if (is_array($value)) {
                    $name = Str::squish((string) ($value['name'] ?? ''));

                    return $name === '' ? null : [
                        'name' => $name,
                    ];
                }

                $name = Str::squish((string) $value);

                return $name === '' ? null : [
                    'name' => $name,
                ];
            })
            ->filter()
            ->unique(fn ($value) => strtolower($value['name']))
            ->values();

        $states = State::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
        $timezones = collect(\DateTimeZone::listIdentifiers())->values();

        $letterhead = app(LetterheadPdfService::class)->getActiveLetterhead($company);
        $dailyStaffUpdateHistory = $activeTab === 'daily-update-history'
            ? app(DailyStaffUpdateReportService::class)->historyIndexData($company, $request)
            : [];
        $quotationTemplates = QuotationTemplate::query()
            ->where('company_id', $company->id)
            ->with('creator:id,name')
            ->latest()
            ->get();

        $eligibleStaffUsers = app(LeadAssignmentService::class)->getEligibleStaffUsers($company);

        return view('settings.company.index', compact('company', 'categories', 'leadSources', 'leadSourceNames', 'leadCustomFields', 'leadCustomFieldLabels', 'projects', 'users', 'eligibleStaffUsers', 'holidays', 'statuses', 'projectStages', 'serviceStages', 'serviceStatusOptions', 'units', 'gstRates', 'contractors', 'taskMasters', 'activeTab', 'states', 'timezones', 'letterhead', 'dailyStaffUpdateHistory', 'quotationTemplates'));
    }

    public function update(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);
        $company = $this->resolveCompanyForCurrentUser();
        if (!$company && $user->roleRecord?->company_id) {
            $company = Company::query()->find($user->roleRecord->company_id);
        }
        abort_unless($company, 403);

        $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'email' => 'nullable|email|max:191',
            'mobile_number' => 'nullable|string|max:50',
            'phone_number' => 'nullable|digits:10',
            'address' => 'nullable|string|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'city' => 'nullable|string|max:191',
            'state' => 'nullable|string|max:191',
            'timezone' => 'nullable|timezone',
            'gst_number' => 'nullable|string|max:50',
            'pan_number' => 'nullable|string|max:50',
            'msme_number' => 'nullable|string|max:50',
            'show_msme_number_in_invoice' => 'nullable|boolean',
            'show_msme_number_in_quotation' => 'nullable|boolean',
            'registration_number' => 'nullable|string|max:100',
            'registration_date' => 'nullable|date',
            'business_type' => 'nullable|string|in:CCTV',
            'enable_quotation_generator' => 'nullable|boolean',
            'enable_boq' => 'nullable|boolean',
            'manual_product_entry' => 'nullable|boolean',
            'show_lead_gst_field' => 'nullable|boolean',
            'show_lead_pan_field' => 'nullable|boolean',
            'default_lead_assignee_id' => [
                'nullable',
                'integer',
                \Illuminate\Validation\Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $company->id)),
            ],
            'enable_print_settings' => 'nullable|boolean',
            'enable_daily_staff_update' => 'nullable|boolean',
            'daily_staff_update_time' => 'nullable|date_format:H:i',
            'print_settings.accent_color' => 'nullable|string|max:20',
            'print_settings.page_margin_top' => 'nullable|integer|min:0|max:100',
            'print_settings.page_margin_right' => 'nullable|integer|min:0|max:100',
            'print_settings.page_margin_bottom' => 'nullable|integer|min:0|max:100',
            'print_settings.page_margin_left' => 'nullable|integer|min:0|max:100',
            'print_settings.use_letterhead' => 'nullable|boolean',
            'print_settings.show_quotation_logo' => 'nullable|boolean',
            'print_settings.letterhead_title' => 'nullable|string|max:191',
            'print_settings.letterhead_subtitle' => 'nullable|string|max:191',
            'print_settings.field_positions' => 'nullable|array',
            'print_settings.field_positions.*.shortcode' => 'required_with:print_settings.field_positions|string',
            'print_settings.field_positions.*.x_pct' => 'required_with:print_settings.field_positions|numeric|min:0|max:100',
            'print_settings.field_positions.*.y_pct' => 'required_with:print_settings.field_positions|numeric|min:0|max:100',
            'print_settings.field_positions.*.width_pct' => 'nullable|numeric|min:0|max:100',
            'print_settings.field_positions.*.height_pct' => 'nullable|numeric|min:0|max:100',
            'lead_source_master_list' => 'nullable|string|max:4000',
            'invoice_prefix' => 'sometimes|required|string|max:50',
            'invoice_year' => 'sometimes|required|integer|min:2000|max:2100',
            'invoice_sequence' => 'sometimes|required|integer|min:1',
            'has_default_invoice_notes' => 'nullable|boolean',
            'default_invoice_notes' => 'nullable|string|max:5000',
            'invoice_terms_conditions' => 'nullable|string',
            'quotation_terms_conditions' => 'nullable|string',
            'quotation_prefix' => 'sometimes|required|string|max:50',
            'quotation_year' => 'sometimes|required|integer|min:2000|max:2100',
            'quotation_sequence' => 'sometimes|required|integer|min:1',
            'challan_prefix' => 'nullable|string|max:50',
            'challan_sequence' => 'nullable|integer|min:1',
            'project_reference_prefix' => 'nullable|string|max:50',
            'project_reference_year' => 'nullable|integer|min:2000|max:2100',
            'project_reference_sequence' => 'nullable|integer|min:1',
            'mail_username' => 'nullable|string|max:191',
            'mail_password' => 'nullable|string|max:191',
            'mail_encryption' => 'nullable|in:tls,ssl,none',
            'from_address' => 'nullable|email|max:191',
            'from_name' => 'nullable|string|max:191',
            'to_address' => 'nullable|email|max:191',
            'to_name' => 'nullable|string|max:191',
            'working_days' => 'nullable|array',
            'working_days.*' => 'in:mon,tue,wed,thu,fri,sat,sun',
            'pf_enabled' => 'nullable|boolean',
          
            'quotation_terms.warranty' => 'nullable|string|max:2000',
            'quotation_terms.payment' => 'nullable|string|max:2000',
            'quotation_terms.delivery' => 'nullable|string|max:2000',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:4096',
            'print_logo' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:51200',
            'favicon' => 'nullable|image|mimes:jpg,jpeg,png,webp,svg|max:2048',
            'letterhead_file' => 'nullable|mimes:jpg,jpeg,png,webp,pdf|max:51200',
        ], [
            'invoice_prefix.required' => 'The invoice prefix is required.',
            'invoice_year.required' => 'The invoice year is required.',
            'invoice_sequence.required' => 'The invoice sequence is required.',
            'quotation_prefix.required' => 'The quotation prefix is required.',
            'quotation_year.required' => 'The quotation year is required.',
            'quotation_sequence.required' => 'The quotation sequence is required.',
        ]);

        $canManagePayrollSettings = $user->isSuperAdmin() || $user->isCompanyAdmin();

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $companyFolderName = 'company_' . $company->id;

        $payload = array_merge($company->only([
            'name',
            'email',
            'mobile_number',
            'phone_number',
            'address',
            'latitude',
            'longitude',
            'city',
            'state',
            'timezone',
            'business_type',
            'enable_quotation_generator',
            'enable_boq',
            'manual_product_entry',
            'show_lead_gst_field',
            'show_lead_pan_field',
            'default_lead_assignee_id',
            'enable_print_settings',
            'enable_daily_staff_update',
            'daily_staff_update_time',
            'print_settings',
            'gst_number',
            'pan_number',
            'msme_number',
            'show_msme_number_in_invoice',
            'show_msme_number_in_quotation',
            'registration_number',
            'registration_date',
            'lead_source_master_list',
            'invoice_prefix',
            'invoice_year',
            'invoice_sequence',
            'has_default_invoice_notes',
            'default_invoice_notes',
            'quotation_prefix',
            'quotation_year',
            'quotation_sequence',
            'challan_prefix',
            'challan_sequence',
            'project_reference_prefix',
            'project_reference_year',
            'project_reference_sequence',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'from_address',
            'from_name',
            'to_address',
            'to_name',
            'working_days',
            'pf_enabled',
            'default_terms_conditions',
            'invoice_terms_conditions',
            'quotation_terms_conditions',
            'quotation_terms',
            'logo',
            'print_logo',
            'favicon',
        ]), $request->only([
            'name',
            'email',
            'mobile_number',
            'phone_number',
            'address',
            'latitude',
            'longitude',
            'city',
            'state',
            'timezone',
            'business_type',
            'enable_quotation_generator',
            'enable_boq',
            'manual_product_entry',
            'show_lead_gst_field',
            'show_lead_pan_field',
            'default_lead_assignee_id',
            'enable_print_settings',
            'enable_daily_staff_update',
            'daily_staff_update_time',
            'print_settings',
            'gst_number',
            'pan_number',
            'msme_number',
            'registration_number',
            'registration_date',
            'invoice_prefix',
            'invoice_year',
            'invoice_sequence',
            'default_invoice_notes',
            'invoice_terms_conditions',
            'quotation_terms_conditions',
            'quotation_prefix',
            'quotation_year',
            'quotation_sequence',
            'challan_prefix',
            'challan_sequence',
            'project_reference_prefix',
            'project_reference_year',
            'project_reference_sequence',
            'mail_username',
            'mail_password',
            'mail_encryption',
            'from_address',
            'from_name',
            'to_address',
            'to_name',
        ]));

        if ($request->has('timezone')) {
            $timezoneInput = trim((string) $request->input('timezone', ''));
            $payload['timezone'] = $timezoneInput !== '' ? $timezoneInput : null;
        }

        if ($request->exists('default_terms_conditions')) {
            $payload['default_terms_conditions'] = trim((string) $request->input('default_terms_conditions', '')) ?: null;
        }

        if ($request->exists('invoice_terms_conditions')) {
            $payload['invoice_terms_conditions'] = trim((string) $request->input('invoice_terms_conditions', '')) ?: null;
        }

        if ($request->exists('default_invoice_notes')) {
            $payload['default_invoice_notes'] = trim((string) $request->input('default_invoice_notes', '')) ?: null;
        }

        if ($request->exists('quotation_terms_conditions')) {
            $payload['quotation_terms_conditions'] = trim((string) $request->input('quotation_terms_conditions', '')) ?: null;
        }

        if ($request->has('quotation_terms')) {
            $terms = $request->input('quotation_terms', []);
            $payload['quotation_terms'] = [
                'warranty' => trim((string) ($terms['warranty'] ?? '')),
                'payment'  => trim((string) ($terms['payment'] ?? '')),
                'delivery' => trim((string) ($terms['delivery'] ?? '')),
            ];
        }

        $payload['pf_enabled'] = $canManagePayrollSettings && $request->has('pf_enabled')
            ? $request->boolean('pf_enabled')
            : $company->pf_enabled;
        $payload['enable_quotation_generator'] = $request->exists('enable_quotation_generator')
            ? $request->boolean('enable_quotation_generator')
            : $company->enable_quotation_generator;
        $payload['enable_boq'] = $request->exists('enable_boq')
            ? $request->boolean('enable_boq')
            : $company->enable_boq;
        $payload['manual_product_entry'] = $request->exists('manual_product_entry')
            ? $request->boolean('manual_product_entry')
            : $company->manual_product_entry;
        $payload['has_default_invoice_notes'] = $request->exists('has_default_invoice_notes')
            ? $request->boolean('has_default_invoice_notes')
            : $company->has_default_invoice_notes;
        $payload['show_lead_gst_field'] = $request->exists('show_lead_gst_field')
            ? $request->boolean('show_lead_gst_field')
            : $company->show_lead_gst_field;
        $payload['show_lead_pan_field'] = $request->exists('show_lead_pan_field')
            ? $request->boolean('show_lead_pan_field')
            : $company->show_lead_pan_field;
        if ($request->has('default_lead_assignee_id')) {
            $assigneeInput = $request->input('default_lead_assignee_id');
            $payload['default_lead_assignee_id'] = filled($assigneeInput) ? (int) $assigneeInput : null;
        }
        $payload['show_msme_number_in_invoice'] = $request->exists('show_msme_number_in_invoice')
            ? $request->boolean('show_msme_number_in_invoice')
            : $company->show_msme_number_in_invoice;
        $payload['show_msme_number_in_quotation'] = $request->exists('show_msme_number_in_quotation')
            ? $request->boolean('show_msme_number_in_quotation')
            : $company->show_msme_number_in_quotation;
        $payload['business_type'] = 'CCTV';
        $payload['working_days'] = $canManagePayrollSettings && $request->has('working_days')
            ? array_values($request->input('working_days', []))
            : $company->working_days;

        if ($request->has('email')) {
            $payload['email'] = filled($request->input('email'))
                ? strtolower(trim((string) $request->input('email')))
                : null;
        }

        if ($request->has('print_settings')) {
            $printSettings = $request->input('print_settings', []);
            $payload['print_settings'] = [
                'accent_color' => $printSettings['accent_color'] ?? '#4f46e5',
                'page_margin_top' => (int) ($printSettings['page_margin_top'] ?? 20),
                'page_margin_right' => (int) ($printSettings['page_margin_right'] ?? 20),
                'page_margin_bottom' => (int) ($printSettings['page_margin_bottom'] ?? 20),
                'page_margin_left' => (int) ($printSettings['page_margin_left'] ?? 20),
                'use_letterhead' => array_key_exists('use_letterhead', $printSettings) ? (bool) $printSettings['use_letterhead'] : true,
                'show_quotation_logo' => array_key_exists('show_quotation_logo', $printSettings) ? (bool) $printSettings['show_quotation_logo'] : true,
                'letterhead_title' => $printSettings['letterhead_title'] ?? $company->name,
                'letterhead_subtitle' => $printSettings['letterhead_subtitle'] ?? 'Official Document',
                // This form doesn't submit field_positions (it's saved separately by the
                // letterhead format builder), so preserve whatever is already stored.
                'field_positions' => $printSettings['field_positions'] ?? data_get($company->print_settings, 'field_positions', []),
            ];
        }

        if ($request->has('enable_print_settings')) {
            $payload['enable_print_settings'] = $request->boolean('enable_print_settings');
        }

        $payload['enable_daily_staff_update'] = $request->has('enable_daily_staff_update')
            ? $request->boolean('enable_daily_staff_update')
            : $company->enable_daily_staff_update;

        if ($request->has('daily_staff_update_time')) {
            $timeInput = trim((string) $request->input('daily_staff_update_time', ''));
            $payload['daily_staff_update_time'] = $timeInput !== ''
                ? \Illuminate\Support\Carbon::createFromFormat('H:i', $timeInput)->format('H:i:s')
                : null;
        }

        if ($request->has('lead_source_master_list')) {
            $leadSources = collect(preg_split('/\r\n|\r|\n|,/', (string) $request->input('lead_source_master_list', '')))
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique(fn ($value) => strtolower($value))
                ->values()
                ->all();
            $payload['lead_source_master_list'] = $leadSources ?: null;
        }

        if ($request->hasFile('logo')) {
            if ($company->logo) {
                app(MediaUploadService::class)->delete($company->logo, $disk, $company->id);
            }

            $payload['logo'] = app(MediaUploadService::class)->store($request->file('logo'), $companyFolderName . '/logos', $disk);
        }

        if ($request->hasFile('print_logo')) {
            if ($company->print_logo) {
                app(MediaUploadService::class)->delete($company->print_logo, $disk, $company->id);
            }

            $payload['print_logo'] = app(MediaUploadService::class)->store($request->file('print_logo'), $companyFolderName . '/print-logos', $disk);
        }

        if ($request->hasFile('favicon')) {
            if ($company->favicon) {
                app(MediaUploadService::class)->delete($company->favicon, $disk, $company->id);
            }

            $payload['favicon'] = app(MediaUploadService::class)->store($request->file('favicon'), $companyFolderName . '/favicons', $disk);
        }

        if ($request->hasFile('letterhead_file')) {
            $uploadedLetterhead = $request->file('letterhead_file');
            $isPdf = strtolower($uploadedLetterhead->getClientOriginalExtension()) === 'pdf'
                || $uploadedLetterhead->getMimeType() === 'application/pdf';

            if ($isPdf) {
                // Rasterize page 1 so the rest of the system (print/PDF views, the
                // <img> based asset route) keeps treating the letterhead as a plain image.
                try {
                    $newLetterheadPath = app(LetterheadPdfConverter::class)->convertToImage(
                        $uploadedLetterhead,
                        $companyFolderName . '/letterheads',
                        $disk
                    );
                } catch (\RuntimeException $e) {
                    return back()->withErrors(['letterhead_file' => $e->getMessage()])->withInput();
                }
            } else {
                $newLetterheadPath = app(MediaUploadService::class)->store($uploadedLetterhead, $companyFolderName . '/letterheads', $disk);
            }

            if ($company->letterhead_file) {
                app(MediaUploadService::class)->delete($company->letterhead_file, $disk, $company->id);
            }

            $payload['letterhead_file'] = $newLetterheadPath;
        }

        $company->update($payload);

        if ($request->has('email') && $user->email !== ($payload['email'] ?? null)) {
            $user->forceFill([
                'email' => $payload['email'] ?? null,
                'email_verified_at' => now(),
            ])->save();
        }

        return back()->with('success', 'Company details updated successfully.');
    }

    public function letterheadFormatBuilder(Request $request)
    {
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 403);

        return view('settings.company.letterhead-format-builder', [
            'company' => $company,
            'shortcodes' => config('letterhead_shortcodes', []),
            'fieldPositions' => data_get($company->print_settings, 'field_positions', []),
        ]);
    }

    public function updateLetterheadFieldPositions(Request $request)
    {
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 403);

        $validated = $request->validate([
            'field_positions' => 'required|array',
            'field_positions.*.shortcode' => 'required|string',
            'field_positions.*.x_pct' => 'required|numeric|min:0|max:100',
            'field_positions.*.y_pct' => 'required|numeric|min:0|max:100',
            'field_positions.*.width_pct' => 'nullable|numeric|min:0|max:100',
            'field_positions.*.height_pct' => 'nullable|numeric|min:0|max:100',
        ]);

        $printSettings = is_array($company->print_settings) ? $company->print_settings : [];
        $printSettings['field_positions'] = $validated['field_positions'];
        $company->update(['print_settings' => $printSettings]);

        return back()->with('success', 'Letterhead format saved.');
    }

    public function asset(Request $request, string $type)
    {
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 403);

        $field = match ($type) {
            'logo' => $company->logo,
            'print-logo' => $company->print_logo,
            'favicon' => $company->favicon,
            'letterhead' => $company->letterhead_file,
            default => null,
        };

        abort_unless($field, 404);

        $path = str_replace('\\', '/', trim((string) $field));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';

        abort_unless(Storage::disk($disk)->exists($path), 404);

        $mimeType = Storage::disk($disk)->mimeType($path);
        if (! is_string($mimeType) || $mimeType === '' || in_array($mimeType, ['application/octet-stream', 'text/plain'], true)) {
            $mimeType = $this->guessAssetMimeType($path);
        }

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => $mimeType,
        ]);
    }

    public function checkChallanSequence(Request $request)
    {
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 404);

        $validated = $request->validate([
            'prefix' => 'nullable|string|max:50',
            'sequence' => 'required|integer|min:1',
        ]);

        $prefix = trim((string) ($validated['prefix'] ?? ''));
        $sequence = (int) $validated['sequence'];
        $number = $company->formatChallanNumber($sequence, $prefix);
        $available = ! $company->challanNumberExists($number);
        $nextSequence = $available ? $sequence : $company->nextAvailableChallanSequence($sequence, $prefix);

        return response()->json([
            'number' => $number,
            'available' => $available,
            'next_sequence' => $nextSequence,
            'next_number' => $company->formatChallanNumber($nextSequence, $prefix),
        ]);
    }

    public function refreshQuotationTaxContext(Request $request)
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('leads.quotation.manage'), 403);

        $company = $this->resolveCompanyForCurrentUser();
        if (! $company && $user->roleRecord?->company_id) {
            $company = Company::query()->find($user->roleRecord->company_id);
        }

        abort_unless($company, 404);

        $updatedCount = 0;
        $skippedCount = 0;

        LeadQuotation::query()
            ->whereHas('lead', function ($query) use ($company) {
                $query->where('company_id', $company->id);
            })
            ->with([
                'lead:' . implode(',', $this->leadRefreshColumns()),
                'lead.state:id,name',
                'lead.city:id,name,state_id',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($quotations) use (&$updatedCount, &$skippedCount) {
                foreach ($quotations as $quotation) {
                    $lead = $quotation->lead;

                    if (! $lead) {
                        $skippedCount++;
                        continue;
                    }

                    $client = $this->resolveClientForLead($lead);
                    $formData = is_array($quotation->form_data) ? $quotation->form_data : [];
                    $updatedFormData = $this->applyTaxContextToFormData($formData, $lead, $client);

                    if ($updatedFormData === $formData) {
                        $skippedCount++;
                        continue;
                    }

                    $quotation->forceFill([
                        'form_data' => $updatedFormData,
                    ])->save();

                    $updatedCount++;
                }
            });

        return redirect()
            ->route('settings.company.index', ['tab' => 'company-details'])
            ->with('success', "Quotation/BOQ tax context refreshed. Updated {$updatedCount} records, skipped {$skippedCount} records.");
    }

    private function storeOptimizedImageAsset(UploadedFile $file, string $directory, string $disk): string
    {
        $sourcePath = $file->getRealPath();
        if (! $sourcePath || ! is_file($sourcePath)) {
            return $file->store($directory, $disk);
        }

        $originalSize = @filesize($sourcePath) ?: null;
        $targetBytes = 1024 * 1024;

        $mime = strtolower((string) $file->getMimeType());
        $extension = strtolower($file->getClientOriginalExtension());

        if ($mime === 'image/svg+xml' || $extension === 'svg') {
            return $file->store($directory, $disk);
        }

        $sourceType = @exif_imagetype($sourcePath) ?: null;
        $image = match (true) {
            $mime === 'image/png', $extension === 'png', $sourceType === IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
            $mime === 'image/webp', $extension === 'webp', $sourceType === IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => @imagecreatefromjpeg($sourcePath),
        };

        if (! $image) {
            return $file->store($directory, $disk);
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $baseScale = min(1200 / max($width, 1), 1200 / max($height, 1), 1);
        $scale = max(0.35, $baseScale);
        $bestTmpPath = null;
        $bestSize = null;
        $targetReached = false;

        while ($scale >= 0.35 && ! $targetReached) {
            $targetWidth = (int) max(1, floor($width * $scale));
            $targetHeight = (int) max(1, floor($height * $scale));

            $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefilledrectangle($canvas, 0, 0, $targetWidth, $targetHeight, $white);
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

            $quality = 86;
            while ($quality >= 40) {
                $tmpPath = storage_path('app/temp_' . uniqid('brand_', true) . '.jpg');
                $written = imagejpeg($canvas, $tmpPath, $quality);

                if (! $written || ! is_file($tmpPath)) {
                    @unlink($tmpPath);
                    break;
                }

                clearstatcache(true, $tmpPath);
                $currentSize = @filesize($tmpPath) ?: null;

                if ($currentSize !== null && ($bestSize === null || $currentSize < $bestSize)) {
                    if ($bestTmpPath && is_file($bestTmpPath)) {
                        @unlink($bestTmpPath);
                    }

                    $bestTmpPath = $tmpPath;
                    $bestSize = $currentSize;
                } else {
                    @unlink($tmpPath);
                }

                if ($currentSize !== null && $currentSize <= $targetBytes) {
                    $targetReached = true;
                    break;
                }

                $quality -= 10;
            }

            imagedestroy($canvas);
            $scale *= 0.85;
        }

        imagedestroy($image);

        if (! $bestTmpPath || ! is_file($bestTmpPath)) {
            return $file->store($directory, $disk);
        }

        clearstatcache(true, $bestTmpPath);
        $optimizedSize = @filesize($bestTmpPath) ?: null;

        if ($originalSize !== null && $optimizedSize !== null && $optimizedSize >= $originalSize) {
            @unlink($bestTmpPath);
            return $file->store($directory, $disk);
        }

        $path = trim($directory, '/') . '/' . uniqid('brand_', true) . '.jpg';
        Storage::disk($disk)->put($path, file_get_contents($bestTmpPath));
        @unlink($bestTmpPath);

        return $path;
    }

    private function guessAssetMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }

    private function resolveClientForLead(Lead $lead): ?Client
    {
        $companyId = (int) $lead->company_id;
        $email = filled($lead->email) ? strtolower(trim((string) $lead->email)) : null;
        $mobile = filled($lead->mobile) ? trim((string) $lead->mobile) : null;
        $phone = filled($lead->phone) ? trim((string) $lead->phone) : null;
        $name = trim((string) $lead->client_name);

        return Client::query()
            ->with(['state:id,name', 'city:id,name,state_id'])
            ->where('company_id', $companyId)
            ->where(function ($query) use ($email, $mobile, $phone, $name) {
                if ($email) {
                    $query->whereRaw('LOWER(email) = ?', [$email]);
                }

                if ($mobile) {
                    $query->orWhere('mobile', $mobile)
                        ->orWhere('phone', $mobile);
                }

                if ($phone) {
                    $query->orWhere('mobile', $phone)
                        ->orWhere('phone', $phone);
                }

                if ($name !== '') {
                    $query->orWhere('name', $name);
                }
            })
            ->first();
    }

    private function leadRefreshColumns(): array
    {
        $columns = [
            'id',
            'company_id',
            'client_name',
            'email',
            'mobile',
            'address',
            'state_id',
            'city_id',
            'uuid',
        ];

        if (Schema::hasColumn('leads', 'phone')) {
            $columns[] = 'phone';
        }

        return $columns;
    }

    private function applyTaxContextToFormData(array $formData, Lead $lead, ?Client $client): array
    {
        $clientBlock = is_array($formData['client'] ?? null) ? $formData['client'] : [];

        $stateId = $client?->state_id ?: $lead->state_id;
        $cityId = $client?->city_id ?: $lead->city_id;
        $stateName = $client?->state?->name ?: $lead->state?->name;
        $cityName = $client?->city?->name ?: $lead->city?->name;

        if (! $stateId && ! $cityId && ! filled($stateName) && ! filled($cityName)) {
            return $formData;
        }

        $formData['client'] = array_merge($clientBlock, [
            'state_id' => $stateId,
            'state_name' => $stateName,
            'city_id' => $cityId,
            'city_name' => $cityName,
        ]);

        return $formData;
    }

    public function storeHoliday(Request $request)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 403);
        abort_unless($user?->isSuperAdmin() || $user?->isCompanyAdmin(), 403);

        $data = $request->validate([
            'holiday_date' => 'required|date',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string|max:1000',
        ]);

        CompanyHoliday::updateOrCreate(
            ['company_id' => $company->id, 'holiday_date' => $data['holiday_date']],
            ['title' => $data['title'], 'description' => $data['description'] ?? null]
        );

        return back()->with('success', 'Holiday saved successfully.');
    }

    public function updateHoliday(Request $request, CompanyHoliday $holiday)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company && (int) $holiday->company_id === (int) $company->id, 403);
        abort_unless($user?->isSuperAdmin() || $user?->isCompanyAdmin(), 403);

        $data = $request->validate([
            'holiday_date' => 'required|date',
            'title' => 'required|string|max:191',
            'description' => 'nullable|string|max:1000',
        ]);

        $holiday->update($data);
        return back()->with('success', 'Holiday updated successfully.');
    }

    public function destroyHoliday(CompanyHoliday $holiday)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company && (int) $holiday->company_id === (int) $company->id, 403);
        abort_unless($user?->isSuperAdmin() || $user?->isCompanyAdmin(), 403);
        $holiday->delete();

        return back()->with('success', 'Holiday deleted successfully.');
    }

    public function storeLeadSource(Request $request)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $data = $request->validate([
            'lead_source_name' => 'required|string|max:191',
            'show_referral_person_name' => 'nullable|boolean',
        ]);

        $leadSources = $this->normalizedCompanyLeadSources($company);
        $sourceName = trim((string) $data['lead_source_name']);

        if ($this->leadSourceExists($leadSources, $sourceName)) {
            return $this->leadSourceRedirect()
                ->withErrors(['lead_source_name' => 'This lead source already exists.'])
                ->withInput($request->only(['lead_source_name', 'lead_source_mode', 'show_referral_person_name']));
        }

        $leadSources[] = [
            'key' => $this->generateLeadSourceKey($sourceName, $leadSources),
            'name' => $sourceName,
            'show_referral_person_name' => $request->boolean('show_referral_person_name'),
        ];
        $company->update([
            'lead_source_master_list' => array_values($leadSources),
        ]);

        return $this->leadSourceRedirect()->with('success', 'Lead source added successfully.');
    }

    public function storeTaskMaster(Request $request)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $data = $request->validate([
            'task_master_name' => 'required|string|max:191',
        ]);

        $taskMasters = $this->normalizedCompanyTaskMasters($company);
        $taskName = Str::squish((string) $data['task_master_name']);

        if ($this->taskMasterExists($taskMasters, $taskName)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This task already exists.',
                ], 422);
            }

            return $this->taskMasterRedirect()
                ->withErrors(['task_master_name' => 'This task already exists.'])
                ->withInput($request->only(['task_master_name']));
        }

        $taskMasters[] = [
            'name' => $taskName,
        ];

        $company->update([
            'task_master_list' => array_values($taskMasters),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Task added successfully.',
                'task' => [
                    'name' => $taskName,
                ],
                'tasks' => array_values($taskMasters),
            ], 201);
        }

        return $this->taskMasterRedirect()->with('success', 'Task added successfully.');
    }

    public function updateLeadSource(Request $request, string $key)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $data = $request->validate([
            'lead_source_name' => 'required|string|max:191',
            'show_referral_person_name' => 'nullable|boolean',
        ]);

        $leadSources = $this->normalizedCompanyLeadSources($company);
        $leadSourceIndex = $this->findLeadSourceIndexByKey($leadSources, $key);
        abort_unless($leadSourceIndex !== null, 404);

        $sourceName = trim((string) $data['lead_source_name']);

        if ($this->leadSourceExists($leadSources, $sourceName, $key)) {
            return $this->leadSourceRedirect()
                ->withErrors(['lead_source_name' => 'This lead source already exists.'])
                ->withInput($request->only(['lead_source_name', 'lead_source_mode', 'lead_source_key', 'show_referral_person_name']));
        }

        $existingKey = (string) ($leadSources[$leadSourceIndex]['key'] ?? $this->generateLeadSourceKey($sourceName, $leadSources));

        $leadSources[$leadSourceIndex] = [
            'key' => $existingKey,
            'name' => $sourceName,
            'show_referral_person_name' => $request->boolean('show_referral_person_name'),
        ];
        $company->update([
            'lead_source_master_list' => array_values($leadSources),
        ]);

        return $this->leadSourceRedirect()->with('success', 'Lead source updated successfully.');
    }

    public function updateTaskMaster(Request $request, int $index)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $data = $request->validate([
            'task_master_name' => 'required|string|max:191',
        ]);

        $taskMasters = $this->normalizedCompanyTaskMasters($company);
        abort_unless(array_key_exists($index, $taskMasters), 404);

        $taskName = Str::squish((string) $data['task_master_name']);

        if ($this->taskMasterExists($taskMasters, $taskName, $index)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This task already exists.',
                ], 422);
            }

            return $this->taskMasterRedirect()
                ->withErrors(['task_master_name' => 'This task already exists.'])
                ->withInput($request->only(['task_master_name', 'task_master_index']));
        }

        $taskMasters[$index] = [
            'name' => $taskName,
        ];

        $company->update([
            'task_master_list' => array_values($taskMasters),
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Task updated successfully.',
                'task' => [
                    'name' => $taskName,
                ],
                'tasks' => array_values($taskMasters),
            ]);
        }

        return $this->taskMasterRedirect()->with('success', 'Task updated successfully.');
    }

    public function destroyLeadSource(string $key)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $leadSources = $this->normalizedCompanyLeadSources($company);
        $leadSourceIndex = $this->findLeadSourceIndexByKey($leadSources, $key);
        abort_unless($leadSourceIndex !== null, 404);

        unset($leadSources[$leadSourceIndex]);

        $company->update([
            'lead_source_master_list' => $leadSources ? array_values($leadSources) : null,
        ]);

        return $this->leadSourceRedirect()->with('success', 'Lead source deleted successfully.');
    }

    public function destroyTaskMaster(Request $request, int $index)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $taskMasters = $this->normalizedCompanyTaskMasters($company);
        abort_unless(array_key_exists($index, $taskMasters), 404);

        unset($taskMasters[$index]);

        $company->update([
            'task_master_list' => $taskMasters ? array_values($taskMasters) : null,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Task deleted successfully.',
                'tasks' => array_values($taskMasters),
            ]);
        }

        return $this->taskMasterRedirect()->with('success', 'Task deleted successfully.');
    }

    public function storeLeadCustomField(Request $request)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $data = $request->validate([
            'label' => 'required|string|max:191',
            'type' => 'required|string|in:text,textarea,email,number,date,dropdown,checkbox,radiobutton',
            'required' => 'nullable|boolean',
            'show_in_filters' => 'nullable|boolean',
            'show_in_boq_quotation' => 'nullable|boolean',
            'placeholder' => 'nullable|string|max:191',
            'help_text' => 'nullable|string|max:500',
            'options' => 'nullable|string|max:4000',
        ]);

        $fields = $this->normalizedCompanyLeadCustomFields($company);
        $label = Str::squish((string) $data['label']);

        if ($label === '') {
            return $this->leadCustomFieldRedirect()
                ->withErrors(['label' => 'Please enter a field label.'])
                ->withInput($request->only(['label', 'type', 'required', 'show_in_filters', 'show_in_boq_quotation', 'placeholder', 'help_text', 'options']));
        }

        if ($this->leadCustomFieldLabelExists($fields, $label)) {
            return $this->leadCustomFieldRedirect()
                ->withErrors(['label' => 'This field label already exists.'])
                ->withInput($request->only(['label', 'type', 'required', 'show_in_filters', 'show_in_boq_quotation', 'placeholder', 'help_text', 'options']));
        }

        $options = $this->normalizeLeadCustomFieldOptions($request->input('options'));
        if ($this->leadCustomFieldTypeUsesOptions((string) $data['type']) && $options === []) {
            return $this->leadCustomFieldRedirect()
                ->withErrors(['options' => 'Please provide at least one option for this field type.'])
                ->withInput($request->only(['label', 'type', 'required', 'show_in_filters', 'show_in_boq_quotation', 'placeholder', 'help_text', 'options']));
        }

        $fields[] = [
            'key' => $this->generateLeadCustomFieldKey($label, $fields),
            'label' => $label,
            'type' => (string) $data['type'],
            'required' => $request->boolean('required'),
            'show_in_filters' => $request->boolean('show_in_filters'),
            'show_in_boq_quotation' => $request->boolean('show_in_boq_quotation'),
            'placeholder' => trim((string) ($data['placeholder'] ?? '')) ?: null,
            'help_text' => trim((string) ($data['help_text'] ?? '')) ?: null,
            'options' => $options ?: null,
        ];

        $company->update([
            'lead_custom_fields' => array_values($fields),
        ]);

        return $this->leadCustomFieldRedirect()->with('success', 'Lead custom field added successfully.');
    }

    public function updateLeadCustomField(Request $request, string $key)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $data = $request->validate([
            'label' => 'required|string|max:191',
            'type' => 'required|string|in:text,textarea,email,number,date,dropdown,checkbox,radiobutton',
            'required' => 'nullable|boolean',
            'show_in_filters' => 'nullable|boolean',
            'show_in_boq_quotation' => 'nullable|boolean',
            'placeholder' => 'nullable|string|max:191',
            'help_text' => 'nullable|string|max:500',
            'options' => 'nullable|string|max:4000',
        ]);

        $fields = $this->normalizedCompanyLeadCustomFields($company);
        $fieldIndex = $this->findLeadCustomFieldIndexByKey($fields, $key);
        abort_unless($fieldIndex !== null, 404);

        $label = Str::squish((string) $data['label']);
        if ($label === '') {
            return $this->leadCustomFieldRedirect()
                ->withErrors(['label' => 'Please enter a field label.'])
                ->withInput(array_merge($request->only(['label', 'type', 'required', 'show_in_filters', 'show_in_boq_quotation', 'placeholder', 'help_text', 'options']), [
                    'lead_custom_field_key' => $key,
                ]));
        }

        if ($this->leadCustomFieldLabelExists($fields, $label, $key)) {
            return $this->leadCustomFieldRedirect()
                ->withErrors(['label' => 'This field label already exists.'])
                ->withInput(array_merge($request->only(['label', 'type', 'required', 'show_in_filters', 'show_in_boq_quotation', 'placeholder', 'help_text', 'options']), [
                    'lead_custom_field_key' => $key,
                ]));
        }

        $options = $this->normalizeLeadCustomFieldOptions($request->input('options'));
        if ($this->leadCustomFieldTypeUsesOptions((string) $data['type']) && $options === []) {
            return $this->leadCustomFieldRedirect()
                ->withErrors(['options' => 'Please provide at least one option for this field type.'])
                ->withInput(array_merge($request->only(['label', 'type', 'required', 'show_in_filters', 'show_in_boq_quotation', 'placeholder', 'help_text', 'options']), [
                    'lead_custom_field_key' => $key,
                ]));
        }

        $existingKey = (string) ($fields[$fieldIndex]['key'] ?? $this->generateLeadCustomFieldKey($label, $fields, $fieldIndex));

        $fields[$fieldIndex] = [
            'key' => $existingKey,
            'label' => $label,
            'type' => (string) $data['type'],
            'required' => $request->boolean('required'),
            'show_in_filters' => $request->boolean('show_in_filters'),
            'show_in_boq_quotation' => $request->boolean('show_in_boq_quotation'),
            'placeholder' => trim((string) ($data['placeholder'] ?? '')) ?: null,
            'help_text' => trim((string) ($data['help_text'] ?? '')) ?: null,
            'options' => $options ?: null,
        ];

        $company->update([
            'lead_custom_fields' => array_values($fields),
        ]);

        return $this->leadCustomFieldRedirect()->with('success', 'Lead custom field updated successfully.');
    }

    public function destroyLeadCustomField(string $key)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $fields = $this->normalizedCompanyLeadCustomFields($company);
        $fieldIndex = $this->findLeadCustomFieldIndexByKey($fields, $key);
        abort_unless($fieldIndex !== null, 404);

        unset($fields[$fieldIndex]);

        $company->update([
            'lead_custom_fields' => $fields ? array_values($fields) : null,
        ]);

        return $this->leadCustomFieldRedirect()->with('success', 'Lead custom field deleted successfully.');
    }

    public function storeProjectCustomField(Request $request)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $data = $request->validate([
            'project_label' => 'required|string|max:191',
            'project_type' => 'required|string|in:text,textarea,email,number,date,dropdown,checkbox,radiobutton',
            'project_required' => 'nullable|boolean',
            'project_show_in_filters' => 'nullable|boolean',
            'project_placeholder' => 'nullable|string|max:191',
            'project_help_text' => 'nullable|string|max:500',
            'project_options' => 'nullable|string|max:4000',
        ]);

        $fields = $this->normalizedCompanyProjectCustomFields($company);
        $label = Str::squish((string) $data['project_label']);

        if ($label === '') {
            return $this->projectCustomFieldRedirect()
                ->withErrors(['project_label' => 'Please enter a field label.'])
                ->withInput($request->only(['project_label', 'project_type', 'project_required', 'project_show_in_filters', 'project_placeholder', 'project_help_text', 'project_options']));
        }

        if ($this->projectCustomFieldLabelExists($fields, $label)) {
            return $this->projectCustomFieldRedirect()
                ->withErrors(['project_label' => 'This field label already exists.'])
                ->withInput($request->only(['project_label', 'project_type', 'project_required', 'project_show_in_filters', 'project_placeholder', 'project_help_text', 'project_options']));
        }

        $options = $this->normalizeProjectCustomFieldOptions($request->input('project_options'));
        if ($this->projectCustomFieldTypeUsesOptions((string) $data['project_type']) && $options === []) {
            return $this->projectCustomFieldRedirect()
                ->withErrors(['project_options' => 'Please provide at least one option for this field type.'])
                ->withInput($request->only(['project_label', 'project_type', 'project_required', 'project_show_in_filters', 'project_placeholder', 'project_help_text', 'project_options']));
        }

        $fields[] = [
            'key' => $this->generateProjectCustomFieldKey($label, $fields),
            'label' => $label,
            'type' => (string) $data['project_type'],
            'required' => $request->boolean('project_required'),
            'show_in_filters' => $request->boolean('project_show_in_filters'),
            'placeholder' => trim((string) ($data['project_placeholder'] ?? '')) ?: null,
            'help_text' => trim((string) ($data['project_help_text'] ?? '')) ?: null,
            'options' => $options ?: null,
        ];

        $company->update([
            'project_custom_fields' => array_values($fields),
        ]);

        return $this->projectCustomFieldRedirect()->with('success', 'Project custom field added successfully.');
    }

    public function updateProjectCustomField(Request $request, string $key)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $data = $request->validate([
            'project_label' => 'required|string|max:191',
            'project_type' => 'required|string|in:text,textarea,email,number,date,dropdown,checkbox,radiobutton',
            'project_required' => 'nullable|boolean',
            'project_show_in_filters' => 'nullable|boolean',
            'project_placeholder' => 'nullable|string|max:191',
            'project_help_text' => 'nullable|string|max:500',
            'project_options' => 'nullable|string|max:4000',
        ]);

        $fields = $this->normalizedCompanyProjectCustomFields($company);
        $fieldIndex = $this->findProjectCustomFieldIndexByKey($fields, $key);
        abort_unless($fieldIndex !== null, 404);

        $label = Str::squish((string) $data['project_label']);
        if ($label === '') {
            return $this->projectCustomFieldRedirect()
                ->withErrors(['project_label' => 'Please enter a field label.'])
                ->withInput(array_merge($request->only(['project_label', 'project_type', 'project_required', 'project_show_in_filters', 'project_placeholder', 'project_help_text', 'project_options']), [
                    'project_custom_field_key' => $key,
                ]));
        }

        if ($this->projectCustomFieldLabelExists($fields, $label, $key)) {
            return $this->projectCustomFieldRedirect()
                ->withErrors(['project_label' => 'This field label already exists.'])
                ->withInput(array_merge($request->only(['project_label', 'project_type', 'project_required', 'project_show_in_filters', 'project_placeholder', 'project_help_text', 'project_options']), [
                    'project_custom_field_key' => $key,
                ]));
        }

        $options = $this->normalizeProjectCustomFieldOptions($request->input('project_options'));
        if ($this->projectCustomFieldTypeUsesOptions((string) $data['project_type']) && $options === []) {
            return $this->projectCustomFieldRedirect()
                ->withErrors(['project_options' => 'Please provide at least one option for this field type.'])
                ->withInput(array_merge($request->only(['project_label', 'project_type', 'project_required', 'project_show_in_filters', 'project_placeholder', 'project_help_text', 'project_options']), [
                    'project_custom_field_key' => $key,
                ]));
        }

        $existingKey = (string) ($fields[$fieldIndex]['key'] ?? $this->generateProjectCustomFieldKey($label, $fields, $fieldIndex));

        $fields[$fieldIndex] = [
            'key' => $existingKey,
            'label' => $label,
            'type' => (string) $data['project_type'],
            'required' => $request->boolean('project_required'),
            'show_in_filters' => $request->boolean('project_show_in_filters'),
            'placeholder' => trim((string) ($data['project_placeholder'] ?? '')) ?: null,
            'help_text' => trim((string) ($data['project_help_text'] ?? '')) ?: null,
            'options' => $options ?: null,
        ];

        $company->update([
            'project_custom_fields' => array_values($fields),
        ]);

        return $this->projectCustomFieldRedirect()->with('success', 'Project custom field updated successfully.');
    }

    public function destroyProjectCustomField(string $key)
    {
        $user = auth()->user();
        $company = $this->resolveCompanyForCurrentUser();

        abort_unless($company, 403);
        abort_unless($user?->isCompanyAdmin() || $user?->isSuperAdmin(), 403);

        $fields = $this->normalizedCompanyProjectCustomFields($company);
        $fieldIndex = $this->findProjectCustomFieldIndexByKey($fields, $key);
        abort_unless($fieldIndex !== null, 404);

        unset($fields[$fieldIndex]);

        $company->update([
            'project_custom_fields' => $fields ? array_values($fields) : null,
        ]);

        return $this->projectCustomFieldRedirect()->with('success', 'Project custom field deleted successfully.');
    }

    private function normalizedCompanyLeadSources(Company $company): array
    {
        return $company->normalizedLeadSources();
    }

    private function findLeadSourceIndexByKey(array $leadSources, string $key): ?int
    {
        $normalizedKey = Str::lower(trim($key));

        foreach ($leadSources as $index => $leadSource) {
            if (Str::lower(trim((string) ($leadSource['key'] ?? ''))) === $normalizedKey) {
                return $index;
            }
        }

        return null;
    }

    private function generateLeadSourceKey(string $sourceName, array $leadSources): string
    {
        $baseKey = Str::of($sourceName)->slug('_')->toString();

        if ($baseKey === '') {
            $baseKey = 'lead_source_' . ((int) count($leadSources) + 1);
        }

        $candidate = $baseKey;
        $suffix = 2;
        $usedKeys = collect($leadSources)
            ->pluck('key')
            ->map(fn ($value) => Str::lower(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        while (in_array(Str::lower($candidate), $usedKeys, true)) {
            $candidate = $baseKey . '_' . $suffix++;
        }

        return $candidate;
    }

    private function normalizedCompanyTaskMasters(Company $company): array
    {
        return $company->normalizedTaskMasterList();
    }

    private function leadSourceExists(array $leadSources, string $sourceName, ?string $ignoreKey = null): bool
    {
        $normalizedName = strtolower(trim($sourceName));

        foreach ($leadSources as $leadSource) {
            if ($ignoreKey !== null && Str::lower(trim((string) ($leadSource['key'] ?? ''))) === Str::lower(trim($ignoreKey))) {
                continue;
            }

            if (strtolower(trim((string) ($leadSource['name'] ?? ''))) === $normalizedName) {
                return true;
            }
        }

        return false;
    }

    private function taskMasterExists(array $taskMasters, string $taskName, ?int $ignoreIndex = null): bool
    {
        $normalizedName = Str::lower(Str::squish($taskName));

        foreach ($taskMasters as $index => $taskMaster) {
            if ($ignoreIndex !== null && $index === $ignoreIndex) {
                continue;
            }

            if (Str::lower(Str::squish((string) ($taskMaster['name'] ?? ''))) === $normalizedName) {
                return true;
            }
        }

        return false;
    }

    private function normalizedCompanyLeadCustomFields(Company $company): array
    {
        return $company->normalizedLeadCustomFields();
    }

    private function normalizedCompanyProjectCustomFields(Company $company): array
    {
        return $company->normalizedProjectCustomFields();
    }

    private function leadCustomFieldLabelExists(array $fields, string $label, ?string $ignoreKey = null): bool
    {
        $normalizedLabel = strtolower(trim($label));

        foreach ($fields as $field) {
            if ($ignoreKey !== null && Str::lower(trim((string) ($field['key'] ?? ''))) === Str::lower(trim($ignoreKey))) {
                continue;
            }

            if (strtolower(trim((string) ($field['label'] ?? ''))) === $normalizedLabel) {
                return true;
            }
        }

        return false;
    }

    private function projectCustomFieldLabelExists(array $fields, string $label, ?string $ignoreKey = null): bool
    {
        $normalizedLabel = strtolower(trim($label));

        foreach ($fields as $field) {
            if ($ignoreKey !== null && Str::lower(trim((string) ($field['key'] ?? ''))) === Str::lower(trim($ignoreKey))) {
                continue;
            }

            if (strtolower(trim((string) ($field['label'] ?? ''))) === $normalizedLabel) {
                return true;
            }
        }

        return false;
    }

    private function findLeadCustomFieldIndexByKey(array $fields, string $key): ?int
    {
        $normalizedKey = Str::lower(trim($key));

        foreach ($fields as $index => $field) {
            if (Str::lower(trim((string) ($field['key'] ?? ''))) === $normalizedKey) {
                return $index;
            }
        }

        return null;
    }

    private function findProjectCustomFieldIndexByKey(array $fields, string $key): ?int
    {
        $normalizedKey = Str::lower(trim($key));

        foreach ($fields as $index => $field) {
            if (Str::lower(trim((string) ($field['key'] ?? ''))) === $normalizedKey) {
                return $index;
            }
        }

        return null;
    }

    private function leadCustomFieldTypeUsesOptions(string $type): bool
    {
        return in_array($type, ['dropdown', 'radiobutton'], true);
    }

    private function projectCustomFieldTypeUsesOptions(string $type): bool
    {
        return in_array($type, ['dropdown', 'radiobutton'], true);
    }

    private function normalizeLeadCustomFieldOptions(mixed $options): array
    {
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n|,/', $options);
        }

        return collect(is_array($options) ? $options : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => strtolower($value))
            ->values()
            ->all();
    }

    private function normalizeProjectCustomFieldOptions(mixed $options): array
    {
        if (is_string($options)) {
            $options = preg_split('/\r\n|\r|\n|,/', $options);
        }

        return collect(is_array($options) ? $options : [])
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->unique(fn ($value) => strtolower($value))
            ->values()
            ->all();
    }

    private function generateLeadCustomFieldKey(string $label, array $fields, ?int $ignoreIndex = null): string
    {
        $baseKey = Str::of($label)->slug('_')->toString();
        if ($baseKey === '') {
            $baseKey = 'field_' . ((int) count($fields) + 1);
        }

        $candidate = $baseKey;
        $suffix = 2;
        $usedKeys = collect($fields)
            ->except($ignoreIndex === null ? [] : [$ignoreIndex])
            ->pluck('key')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        while (in_array(strtolower($candidate), $usedKeys, true)) {
            $candidate = $baseKey . '_' . $suffix++;
        }

        return $candidate;
    }

    private function generateProjectCustomFieldKey(string $label, array $fields, ?int $ignoreIndex = null): string
    {
        $baseKey = Str::of($label)->slug('_')->toString();
        if ($baseKey === '') {
            $baseKey = 'field_' . ((int) count($fields) + 1);
        }

        $candidate = $baseKey;
        $suffix = 2;
        $usedKeys = collect($fields)
            ->except($ignoreIndex === null ? [] : [$ignoreIndex])
            ->pluck('key')
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        while (in_array(strtolower($candidate), $usedKeys, true)) {
            $candidate = $baseKey . '_' . $suffix++;
        }

        return $candidate;
    }

    private function leadSourceRedirect()
    {
        return redirect()->route('settings.company.index', ['tab' => 'lead-pipeline']);
    }

    private function taskMasterRedirect()
    {
        return redirect()->route('settings.company.index', ['tab' => 'task-master']);
    }

    private function leadCustomFieldRedirect()
    {
        return redirect()->route('settings.company.index', ['tab' => 'lead-pipeline']);
    }

    private function projectCustomFieldRedirect()
    {
        return redirect()->route('settings.company.index', ['tab' => 'project-pipeline']);
    }
}
