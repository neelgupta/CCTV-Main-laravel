<?php

namespace App\Http\Controllers;

use App\Exports\AmcExport;
use App\Exports\AmcImportSampleExport;
use App\Imports\AmcImport;
use App\Models\Amc;
use App\Models\AmcPlan;
use App\Models\AmcVisit;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGstRate;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Ticket;
use App\Services\ClientSyncService;
use App\Services\AmcSupportWorkflowService;
use App\Services\AmcTechnicianAssignmentService;
use App\Services\LetterheadPdfService;
use App\Services\TicketAssignmentHistoryService;
use App\Services\SubscriptionLimitService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class AmcController extends Controller
{
    private const SUPPORT_TICKET_SUBJECT_PREFIX = 'AMC Service Setup - ';
    private const SUPPORT_TICKET_SUBJECT_MAX_LENGTH = 191;

    public function index(Request $request)
    {
        $mode = $request->string('mode')->toString();
        $baseQuery = $this->filteredAmcsQuery($request);
        $allAmcs = $this->amcListingCollection($request);
        $visitRange = $this->normalizeVisitRange($request->query('visit_range'));
        $lastVisitStatus = $this->normalizeVisitStatus($request->query('last_visit_status'));
        $amcType = $this->normalizeAmcType($request->query('amc_type'));
        $filteredAmcs = $this->filterAmcsByVisitStatus(
            $this->filterAmcsByVisitRange($allAmcs, $visitRange),
            $lastVisitStatus
        );
        $filteredAmcs = $this->filterAmcsByTechnician($filteredAmcs, $request->query('technician'));
        $filteredAmcs = $this->filterAmcsByMode($filteredAmcs, $mode);
        $amcFilterTechnicians = $allAmcs
            ->map(fn (Amc $amc) => $this->amcTechnicianLabel($amc))
            ->filter(fn ($technician) => filled($technician))
            ->unique(fn ($technician) => mb_strtolower(trim((string) $technician)))
            ->sort()
            ->values();

        return view('amcs.index', [
            'amcs' => $this->paginateAmcs($filteredAmcs, $request, 10),
            'amcModeCounters' => $this->buildModeCounters($baseQuery),
            'activeAmcMode' => $mode ?: 'all',
            'amcPlans' => AmcPlan::query()
                ->where('company_id', (int) auth()->user()->company_id)
                ->where('is_active', true)
                ->orderBy('duration_months')
                ->orderBy('name')
                ->get(['id', 'name', 'duration_months']),
            'amcFilterTechnicians' => $amcFilterTechnicians,
            'amcTechnicians' => Staff::query()
                ->where('company_id', (int) auth()->user()->company_id)
                ->activeWithActiveSubscription()
                ->orderBy('name')
                ->get(['id', 'name']),
            'amcTypeOptions' => Amc::TYPE_OPTIONS,
            'amcPaymentModeOptions' => Amc::PAYMENT_MODE_OPTIONS,
            'amcPaymentStatusOptions' => Amc::PAYMENT_STATUS_OPTIONS,
            'amcVisitRangeCounts' => $this->buildVisitRangeCounts($allAmcs),
            'activeVisitRange' => $visitRange,
            'visitRangeOptions' => $this->visitRangeOptions(),
            'amcVisitStatusCounts' => $this->buildVisitStatusCounts($allAmcs),
            'activeLastVisitStatus' => $lastVisitStatus,
            'amcTypeCounts' => $this->buildAmcTypeCounts($allAmcs),
            'activeAmcType' => $amcType,
            'visitStatusOptions' => $this->visitStatusOptions(),
            'canManageAmcPlans' => auth()->user()?->isCompanyAdmin(),
            'canEditAmc' => $this->canEditAmc(auth()->user()),
            'canDeleteAmc' => $this->canDeleteAmc(auth()->user()),
            'canCreateTicket' => auth()->user()?->hasPermission('support.create'),
        ]);
    }

    public function export(Request $request)
    {
        $mode = $request->string('mode')->toString();
        $amcs = $this->filterAmcsByVisitStatus(
            $this->filterAmcsByVisitRange(
                $this->amcListingCollection($request),
                $this->normalizeVisitRange($request->query('visit_range'))
            ),
            $this->normalizeVisitStatus($request->query('last_visit_status'))
        );
        $amcs = $this->filterAmcsByTechnician($amcs, $request->query('technician'));
        $amcs = $this->filterAmcsByMode($amcs, $mode);

        return Excel::download(
            new AmcExport($amcs),
            'amcs-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function import(Request $request)
    {
        abort_unless($this->canCreateAmc(auth()->user()), 403);

        $request->validate([
            'amc_import_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'amc_import_file.required' => 'Please choose an AMC import file.',
            'amc_import_file.mimes' => 'AMC import file must be CSV, XLS, or XLSX.',
        ]);

        Excel::import(
            new AmcImport(auth()->user()),
            $request->file('amc_import_file')
        );

        return redirect()
            ->route('amcs.index')
            ->with('success', 'AMCs imported successfully.');
    }

    public function downloadImportSample()
    {
        abort_unless($this->canCreateAmc(auth()->user()), 403);

        return Excel::download(
            new AmcImportSampleExport(),
            'amc-import-sample.xlsx'
        );
    }

    public function create()
    {
        $companyId = (int) auth()->user()->company_id;
        abort_unless($this->canCreateAmc(auth()->user()), 403);

        return view('amcs.create', $this->buildFormViewData($companyId));
    }

    public function edit(Amc $amc)
    {
        $user = auth()->user();
        abort_unless($this->canEditAmc($user), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);

        if ($amc->isServiceClosed()) {
            return redirect()
                ->route('amcs.show', $amc)
                ->with('error', $amc->serviceClosureMessage());
        }

        $companyId = (int) $user->company_id;

        return view('amcs.create', $this->buildFormViewData($companyId, $amc));
    }

    public function store(Request $request, ClientSyncService $clientSyncService)
    {
        $user = auth()->user();
        abort_unless($this->canCreateAmc($user), 403);
        $subscriptionLimits = app(SubscriptionLimitService::class);
        $mobileNumber = preg_replace('/\D+/', '', (string) $request->input('mobile_number'));
        $email = trim((string) $request->input('email'));
        $technicianContactNumber = preg_replace('/\D+/', '', (string) $request->input('technician_contact_number'));
        $gstNumber = strtoupper(preg_replace('/\s+/', '', (string) $request->input('gst_number')));

        $request->merge([
            'mobile_number' => $mobileNumber,
            'email' => $email !== '' ? Str::lower($email) : null,
            'technician_contact_number' => $technicianContactNumber !== '' ? $technicianContactNumber : null,
            'gst_number' => $gstNumber !== '' ? $gstNumber : null,
        ]);

        $technicianIds = Staff::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $projectIds = Project::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $clientIds = Client::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $amcPlanIds = AmcPlan::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'client_mode' => ['required', Rule::in(['existing', 'new'])],
            'client_id' => [
                Rule::requiredIf(fn () => $request->input('client_mode') === 'existing'),
                'nullable',
                'integer',
                Rule::in($clientIds),
            ],
            'customer_name' => 'required|string|min:2|max:191',
            'project_mode' => ['required', Rule::in(['existing', 'new'])],
            'existing_project_id' => [
                Rule::requiredIf(fn () => $request->input('project_mode') === 'existing'),
                'nullable',
                'integer',
                Rule::in($projectIds),
            ],
            'project_name' => 'required|string|min:2|max:191',
            'mobile_number' => ['required', 'digits:10'],
            'email' => 'nullable|email:rfc|max:191',
            'address' => 'required|string|min:5|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'gst_number' => 'nullable|string|max:15|regex:/^[0-9A-Z]{15}$/',
            'start_date' => ['required', 'date'],
            'amc_plan_id' => ['required', 'integer', Rule::in($amcPlanIds)],
            'amc_type' => ['required', Rule::in(array_keys(Amc::TYPE_OPTIONS))],
            'total_cameras' => 'nullable|integer|min:0|max:10000',
            'camera_types' => 'nullable|array',
            'camera_types.*' => ['string', Rule::in(array_keys(Amc::CAMERA_TYPE_OPTIONS))],
            'dvr_nvr_model' => 'nullable|string|min:2|max:191',
            'hard_disk_size' => 'nullable|string|min:2|max:191',
            'installation_location' => 'nullable|string|max:1000',
            'service_scope' => 'nullable|array',
            'service_scope.*' => ['string', Rule::in(array_keys(Amc::SERVICE_SCOPE_OPTIONS))],
            'amc_amount' => 'required|numeric|min:0|max:999999999.99',
            'discount' => 'nullable|numeric|min:0|max:999999999.99',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'total_amount' => 'required|numeric|min:0|max:999999999.99',
            'payment_mode' => ['required', Rule::in(array_keys(Amc::PAYMENT_MODE_OPTIONS))],
            'payment_status' => ['required', Rule::in(array_keys(Amc::PAYMENT_STATUS_OPTIONS))],
            'service_timing' => 'nullable|string|max:1000',
            'parts_replacement_policy' => 'nullable|string|max:1000',
            'emergency_support_terms' => 'nullable|string|max:1000',
            'assigned_staff_id' => ['nullable', 'integer', Rule::in($technicianIds)],
            'technician_contact_number' => ['nullable', 'digits:10'],
        ]);

        foreach (['customer_name', 'project_name', 'dvr_nvr_model', 'hard_disk_size'] as $field) {
            if (filled($validated[$field] ?? null)) {
                $validated[$field] = Str::limit($validated[$field], 191, '');
            }
        }

        $validated['address'] = filled($validated['address'] ?? null)
            ? trim((string) $validated['address'])
            : null;

        $validated['address'] = filled($validated['address'] ?? null)
            ? trim((string) $validated['address'])
            : null;

        if (($validated['discount'] ?? 0) > $validated['amc_amount']) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot be greater than AMC amount.',
            ]);
        }

        $selectedClient = ($validated['client_mode'] ?? 'new') === 'existing' && filled($validated['client_id'] ?? null)
            ? Client::query()->where('company_id', $user->company_id)->find($validated['client_id'])
            : null;

        $this->ensureClientPhoneIsAvailable(
            (int) $user->company_id,
            (string) $validated['mobile_number'],
            $selectedClient?->id
        );

        $this->ensureAmcEmailNotInternal(
            (int) $user->company_id,
            $validated['email'] ?? null
        );

        $subscriptionLimits->ensureWithinLimit((int) $user->company_id, 'amcs', 1, 'customer_name');

        $selectedPlan = AmcPlan::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->findOrFail($validated['amc_plan_id']);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Amc::calculateEndDate($startDate, (int) $selectedPlan->duration_months);
        $includesOnCallSupport = (bool) $selectedPlan->includes_on_call_support;
        $serviceMode = $selectedPlan->service_mode ?: ($includesOnCallSupport ? 'on_call' : 'scheduled');

        $selectedProject = filled($validated['existing_project_id'] ?? null)
            ? Project::query()->where('company_id', $user->company_id)->find($validated['existing_project_id'])
            : null;

        $shouldCreateNewClient = ! $selectedClient
            || $this->amcCustomerIdentityChanged($selectedClient, $validated);

        if ($shouldCreateNewClient && filled($validated['customer_name'])) {
            $selectedClient = $clientSyncService->upsert([
                'company_id' => (int) $user->company_id,
                'name' => $validated['customer_name'],
                'email' => $validated['email'] ?? null,
                'mobile' => $validated['mobile_number'] ?? null,
                'phone' => $validated['mobile_number'] ?? null,
                'gst_number' => strtoupper(trim((string) ($validated['gst_number'] ?? ''))),
                'address' => $validated['address'],
            ], $shouldCreateNewClient);
        }

        $assignedStaff = filled($validated['assigned_staff_id'] ?? null)
            ? Staff::query()->with('user:id,name')->where('company_id', $user->company_id)->find($validated['assigned_staff_id'])
            : null;

        if ($assignedStaff && mb_strlen($this->buildSupportTicketSubject($validated['project_name'])) > self::SUPPORT_TICKET_SUBJECT_MAX_LENGTH) {
            throw ValidationException::withMessages([
                'project_name' => 'Project name is too long for the generated support ticket subject when a technician is assigned.',
            ]);
        }

        if ($assignedStaff?->user_id) {
            $subscriptionLimits->ensureWithinLimit((int) $user->company_id, 'support', 1, 'assigned_staff_id');
        }

        $amc = null;
        $supportTicketVisitDate = Carbon::parse($validated['start_date'])->toDateString();

        DB::transaction(function () use ($validated, $user, $selectedProject, $selectedClient, $assignedStaff, $selectedPlan, $endDate, $includesOnCallSupport, $serviceMode, &$amc) {
            $ticket = null;
            $workflowService = app(AmcSupportWorkflowService::class);

            if ($selectedClient) {
                $selectedClient->update([
                    'mobile' => $validated['mobile_number'] ?? null,
                    'phone' => $validated['mobile_number'] ?? null,
                ]);
            }

            $amc = Amc::create([
                'company_id' => $user->company_id,
                'project_id' => $selectedProject?->id,
                'client_id' => $selectedClient?->id,
                'amc_plan_id' => $selectedPlan->id,
                'assigned_staff_id' => $assignedStaff?->id,
                'support_ticket_id' => $ticket?->id,
                'created_by' => $user->id,
                'contract_no' => Amc::nextContractNo((int) $user->company_id),
                'customer_name' => $validated['customer_name'],
                'project_name' => $validated['project_name'],
                'mobile_number' => $validated['mobile_number'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'gst_number' => strtoupper(trim((string) ($validated['gst_number'] ?? ''))) ?: null,
                'start_date' => $validated['start_date'],
                'end_date' => $endDate->toDateString(),
                'amc_type' => $validated['amc_type'],
                'plan_name' => $selectedPlan->name,
                'plan_duration_months' => $selectedPlan->duration_months,
                'plan_price' => $selectedPlan->price,
                'plan_description' => $selectedPlan->description,
                'includes_on_call_support' => $includesOnCallSupport,
                'service_mode' => $serviceMode,
                'visit_frequency' => $selectedPlan->generatesScheduledVisits() ? 'manual' : 'on_call',
                'total_visits' => (int) $selectedPlan->visit_count,
                'scheduled_visits' => 0,
                'remaining_visits' => (int) $selectedPlan->visit_count,
                'total_cameras' => $validated['total_cameras'] ?? 0,
                'camera_types' => array_values($validated['camera_types'] ?? []),
                'dvr_nvr_model' => $validated['dvr_nvr_model'] ?? null,
                'hard_disk_size' => $validated['hard_disk_size'] ?? null,
                'installation_location' => $validated['installation_location'] ?? null,
                'service_scope' => array_values($validated['service_scope'] ?? []),
                'amc_amount' => $validated['amc_amount'],
                'discount' => $validated['discount'] ?? 0,
                'tax_percent' => $validated['tax_percent'] ?? 0,
                'total_amount' => $validated['total_amount'],
                'payment_mode' => $validated['payment_mode'],
                'payment_status' => $validated['payment_status'],
                'service_timing' => $validated['service_timing'] ?? null,
                'parts_replacement_policy' => $validated['parts_replacement_policy'] ?? null,
                'emergency_support_terms' => $validated['emergency_support_terms'] ?? null,
                'technician_name' => $assignedStaff?->name ?? null,
                'technician_contact_number' => $validated['technician_contact_number'] ?? $assignedStaff?->contact_number,
            ]);

            app(AmcSupportWorkflowService::class)->seedVisitSlots($amc);

            $amc->load('visits');
            foreach ($amc->visits as $scheduledVisit) {
                app(AmcTechnicianAssignmentService::class)->assignTechnicianIfRequired(
                    $scheduledVisit,
                    now(),
                    'amc_create'
                );
            }

            $ticket = $workflowService->ensureCurrentSupportTicketForAmc($amc, $user);

            $amc->update(['support_ticket_id' => $ticket->id]);

        });

        return redirect()
            ->route('amcs.show', $amc)
            ->with('success', 'AMC created successfully.');
    }

    private function buildSupportTicketSubject(string $projectName): string
    {
        return self::SUPPORT_TICKET_SUBJECT_PREFIX . trim($projectName);
    }

    private function ensureClientPhoneIsAvailable(int $companyId, string $mobileNumber, ?int $ignoreClientId = null): void
    {
        $normalizedPhone = preg_replace('/\D+/', '', $mobileNumber);

        if ($normalizedPhone === '') {
            return;
        }

        $existingClientQuery = Client::query()
            ->where('company_id', $companyId);

        if ($ignoreClientId !== null) {
            $existingClientQuery->where('id', '!=', $ignoreClientId);
        }

        $existingClient = $existingClientQuery
            ->get(['id', 'name', 'mobile', 'phone'])
            ->first(function (Client $client) use ($normalizedPhone) {
                $clientPhones = array_filter([
                    preg_replace('/\D+/', '', (string) $client->mobile),
                    preg_replace('/\D+/', '', (string) $client->phone),
                ]);

                return in_array($normalizedPhone, $clientPhones, true);
            });

        if ($existingClient) {
            throw ValidationException::withMessages([
                'mobile_number' => 'A client already exists with this phone number. Please choose the existing client instead.',
            ]);
        }
    }

    private function ensureAmcEmailNotInternal(int $companyId, ?string $email): void
    {
        if (! filled($email)) {
            return;
        }

        $company = Company::query()->find($companyId);
        if ($company && $company->isInternalStaffOrAdminEmail($email)) {
            throw ValidationException::withMessages([
                'email' => 'This email belongs to a company administrator or staff member and cannot be assigned to an AMC client.',
            ]);
        }
    }

    private function amcCustomerIdentityChanged(Client $client, array $validated): bool
    {
        $submittedName = Str::squish((string) ($validated['customer_name'] ?? ''));
        $submittedEmail = Str::lower(Str::squish((string) ($validated['email'] ?? '')));
        $submittedMobile = preg_replace('/\D+/', '', (string) ($validated['mobile_number'] ?? ''));

        $existingName = Str::squish((string) $client->name);
        $existingEmail = Str::lower(Str::squish((string) ($client->email ?? '')));
        $existingMobile = preg_replace('/\D+/', '', (string) ($client->mobile ?: $client->phone));

        return $submittedName !== $existingName
            || $submittedEmail !== $existingEmail
            || $submittedMobile !== $existingMobile;
    }

    public function show(Amc $amc)
    {
        abort_unless((int) $amc->company_id === (int) auth()->user()->company_id, 403);

        $amc->load(['project', 'client', 'visits.histories', 'visits.supportTicket.assignedUser', 'visits.ticketInvoice.invoice', 'assignedStaff.user', 'supportTicket']);

        return view('amcs.show', [
            'amc' => $amc,
            'amcTechnicians' => Staff::query()
                ->where('company_id', (int) auth()->user()->company_id)
                ->activeWithActiveSubscription()
                ->orderBy('name')
                ->get(['id', 'name']),
            'amcTypeOptions' => Amc::TYPE_OPTIONS,
            'amcServiceScopeOptions' => Amc::SERVICE_SCOPE_OPTIONS,
            'amcPaymentModeOptions' => Amc::PAYMENT_MODE_OPTIONS,
            'amcPaymentStatusOptions' => Amc::PAYMENT_STATUS_OPTIONS,
            'amcCameraTypeOptions' => Amc::CAMERA_TYPE_OPTIONS,
        ]);
    }

    public function downloadVisitInvoice(Request $request, Amc $amc, AmcVisit $visit, LetterheadPdfService $letterheadPdfService)
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);
        abort_unless((int) $visit->amc_id === (int) $amc->id, 404);

        $visit->loadMissing([
            'ticketInvoice.invoice',
            'ticketInvoice.ticket.company',
            'ticketInvoice.ticket.user',
            'ticketInvoice.ticket.assignedUser',
        ]);

        abort_unless($visit->ticketInvoice?->invoice, 404);

        $ticketInvoice = $visit->ticketInvoice;
        $ticket = $ticketInvoice->ticket;
        $document = $ticketInvoice->invoice;
        $items = collect($document->items ?? [])->filter(fn ($item) => is_array($item))->values();

        $subtotal = (float) ($document->subtotal ?? $items->sum(fn ($item) => (float) ($item['base_total'] ?? 0)));
        $discount = (float) ($document->discount ?? 0);
        $gstAmount = (float) ($document->gst_amount ?? $items->sum(fn ($item) => (float) ($item['gst_amount'] ?? 0)));
        $total = (float) ($document->total ?? max(0, $subtotal - $discount + $gstAmount));
        $isPaid = (int) ($ticketInvoice->payment_status ?? 0) === 1 || strtolower((string) $visit->status) === 'completed';
        $paymentStatus = $isPaid ? 'Paid' : 'Unpaid';
        $paymentMethod = $isPaid
            ? (filled($ticketInvoice->payment_method) && ! in_array((int) $ticketInvoice->payment_method, [0, 2], true) ? 'Online' : 'Cash')
            : '-';
        $company = $user->company;
        $useLetterhead = $letterheadPdfService->hasActiveLetterhead($company);
        $pdfMargins = $useLetterhead
            ? $letterheadPdfService->getMargins($letterheadPdfService->activeLetterhead($company))
            : [
                'top_margin' => 20,
                'bottom_margin' => 20,
                'left_margin' => 20,
                'right_margin' => 20,
            ];

        $pdf = Pdf::loadView('amcs.visit-invoice', [
            'company' => $company,
            'amc' => $visit->amc()->with(['client', 'project', 'visits'])->first(),
            'visit' => $visit->loadMissing(['amc.client', 'amc.project']),
            'ticket' => $ticket->loadMissing(['company', 'user', 'assignedUser', 'referencedUser']),
            'document' => $document,
            'link' => $ticketInvoice,
            'type' => 'invoice',
            'autoPrint' => false,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'gstAmount' => $gstAmount,
            'total' => $total,
            'paymentStatus' => $paymentStatus,
            'paymentMethod' => $paymentMethod,
            'pdfMargins' => $pdfMargins,
        ])->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 96,
            ]);

        $fileName = 'amc-visit-invoice-' . preg_replace('/[^A-Za-z0-9._-]/', '-', $document->document_number ?: ('visit-' . $visit->visit_no)) . '.pdf';

        return $letterheadPdfService->download($pdf, $fileName, $company, $request->boolean('inline'));
    }

    public function receipt(Amc $amc)
    {
        abort_unless((int) $amc->company_id === (int) auth()->user()->company_id, 403);

        $amc->load(['project', 'client', 'visits', 'assignedStaff.user', 'supportTicket']);

        return view('amcs.receipt', [
            'amc' => $amc,
            'company' => auth()->user()?->company,
            'amcTypeOptions' => Amc::TYPE_OPTIONS,
            'amcServiceScopeOptions' => Amc::SERVICE_SCOPE_OPTIONS,
            'amcPaymentModeOptions' => Amc::PAYMENT_MODE_OPTIONS,
            'amcPaymentStatusOptions' => Amc::PAYMENT_STATUS_OPTIONS,
            'amcCameraTypeOptions' => Amc::CAMERA_TYPE_OPTIONS,
        ]);
    }

    public function schedule(Amc $amc)
    {
        abort_unless((int) $amc->company_id === (int) auth()->user()->company_id, 403);

        $amc->load(['project', 'client', 'visits.supportTicket.assignedUser', 'assignedStaff.user', 'supportTicket']);

        return view('amcs.schedule', [
            'amc' => $amc,
            'company' => auth()->user()?->company,
            'amcTypeOptions' => Amc::TYPE_OPTIONS,
            'amcServiceScopeOptions' => Amc::SERVICE_SCOPE_OPTIONS,
            'amcPaymentModeOptions' => Amc::PAYMENT_MODE_OPTIONS,
            'amcPaymentStatusOptions' => Amc::PAYMENT_STATUS_OPTIONS,
            'amcCameraTypeOptions' => Amc::CAMERA_TYPE_OPTIONS,
        ]);
    }

    public function update(Request $request, Amc $amc, ClientSyncService $clientSyncService)
    {
        $user = auth()->user();
        abort_unless($this->canEditAmc($user), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);

        if ($amc->isServiceClosed()) {
            return $this->amcLockedResponse($request, $amc);
        }

        $mobileNumber = preg_replace('/\D+/', '', (string) $request->input('mobile_number'));
        $email = trim((string) $request->input('email'));
        $technicianContactNumber = preg_replace('/\D+/', '', (string) $request->input('technician_contact_number'));
        $gstNumber = strtoupper(preg_replace('/\s+/', '', (string) $request->input('gst_number')));
        $technicianAssignmentProvided = $request->exists('assigned_staff_id');
        $technicianContactNumberProvided = $request->exists('technician_contact_number');

        $request->merge([
            'mobile_number' => $mobileNumber,
            'email' => $email !== '' ? Str::lower($email) : null,
            'technician_contact_number' => $technicianContactNumber !== '' ? $technicianContactNumber : null,
            'gst_number' => $gstNumber !== '' ? $gstNumber : null,
        ]);

        $technicianIds = Staff::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $projectIds = Project::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $clientIds = Client::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $amcPlanIds = AmcPlan::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'client_mode' => ['required', Rule::in(['existing', 'new'])],
            'client_id' => [
                Rule::requiredIf(fn () => $request->input('client_mode') === 'existing'),
                'nullable',
                'integer',
                Rule::in($clientIds),
            ],
            'customer_name' => 'required|string|min:2|max:191',
            'project_mode' => ['required', Rule::in(['existing', 'new'])],
            'existing_project_id' => [
                Rule::requiredIf(fn () => $request->input('project_mode') === 'existing'),
                'nullable',
                'integer',
                Rule::in($projectIds),
            ],
            'project_name' => 'required|string|min:2|max:191',
            'mobile_number' => ['required', 'digits:10'],
            'email' => 'nullable|email:rfc|max:191',
            'address' => 'required|string|min:5|max:1000',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'gst_number' => 'nullable|string|max:15|regex:/^[0-9A-Z]{15}$/',
            'start_date' => ['required', 'date'],
            'amc_plan_id' => ['required', 'integer', Rule::in($amcPlanIds)],
            'amc_type' => ['required', Rule::in(array_keys(Amc::TYPE_OPTIONS))],
            'total_cameras' => 'nullable|integer|min:0|max:10000',
            'camera_types' => 'nullable|array',
            'camera_types.*' => ['string', Rule::in(array_keys(Amc::CAMERA_TYPE_OPTIONS))],
            'dvr_nvr_model' => 'nullable|string|min:2|max:191',
            'hard_disk_size' => 'nullable|string|min:2|max:191',
            'installation_location' => 'nullable|string|max:1000',
            'service_scope' => 'nullable|array',
            'service_scope.*' => ['string', Rule::in(array_keys(Amc::SERVICE_SCOPE_OPTIONS))],
            'amc_amount' => 'required|numeric|min:0|max:999999999.99',
            'discount' => 'nullable|numeric|min:0|max:999999999.99',
            'tax_percent' => 'nullable|numeric|min:0|max:100',
            'total_amount' => 'required|numeric|min:0|max:999999999.99',
            'payment_mode' => ['required', Rule::in(array_keys(Amc::PAYMENT_MODE_OPTIONS))],
            'payment_status' => ['required', Rule::in(array_keys(Amc::PAYMENT_STATUS_OPTIONS))],
            'service_timing' => 'nullable|string|max:1000',
            'parts_replacement_policy' => 'nullable|string|max:1000',
            'emergency_support_terms' => 'nullable|string|max:1000',
            'assigned_staff_id' => ['nullable', 'integer', Rule::in($technicianIds)],
            'technician_contact_number' => ['nullable', 'digits:10'],
        ]);

        foreach (['customer_name', 'project_name', 'dvr_nvr_model', 'hard_disk_size'] as $field) {
            if (filled($validated[$field] ?? null)) {
                $validated[$field] = Str::limit($validated[$field], 191, '');
            }
        }

        if (($validated['discount'] ?? 0) > $validated['amc_amount']) {
            throw ValidationException::withMessages([
                'discount' => 'Discount cannot be greater than AMC amount.',
            ]);
        }

        $selectedPlan = AmcPlan::query()
            ->where('company_id', $user->company_id)
            ->where('is_active', true)
            ->findOrFail($validated['amc_plan_id']);

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Amc::calculateEndDate($startDate, (int) $selectedPlan->duration_months);
        $includesOnCallSupport = (bool) $selectedPlan->includes_on_call_support;
        $serviceMode = $selectedPlan->service_mode ?: ($includesOnCallSupport ? 'on_call' : 'scheduled');

        $selectedProject = filled($validated['existing_project_id'] ?? null)
            ? Project::query()->where('company_id', $user->company_id)->find($validated['existing_project_id'])
            : null;

        $selectedClient = ($validated['client_mode'] ?? 'new') === 'existing' && filled($validated['client_id'] ?? null)
            ? Client::query()->where('company_id', $user->company_id)->find($validated['client_id'])
            : null;

        $this->ensureClientPhoneIsAvailable(
            (int) $user->company_id,
            (string) $validated['mobile_number'],
            $selectedClient?->id
        );

        $this->ensureAmcEmailNotInternal(
            (int) $user->company_id,
            $validated['email'] ?? null
        );

        $shouldCreateNewClient = ! $selectedClient
            || $this->amcCustomerIdentityChanged($selectedClient, $validated);

        if ($shouldCreateNewClient && filled($validated['customer_name'])) {
            $selectedClient = $clientSyncService->upsert([
                'company_id' => (int) $user->company_id,
                'name' => $validated['customer_name'],
                'email' => $validated['email'] ?? null,
                'mobile' => $validated['mobile_number'] ?? null,
                'phone' => $validated['mobile_number'] ?? null,
                'gst_number' => strtoupper(trim((string) ($validated['gst_number'] ?? ''))),
                'address' => $validated['address'],
            ], $shouldCreateNewClient);
        }

        $assignedStaff = null;
        if ($technicianAssignmentProvided) {
            $assignedStaff = filled($validated['assigned_staff_id'] ?? null)
                ? Staff::query()->with('user:id,name')->where('company_id', $user->company_id)->find($validated['assigned_staff_id'])
                : null;
        }

        if ($technicianAssignmentProvided && $assignedStaff && mb_strlen($this->buildSupportTicketSubject($validated['project_name'])) > self::SUPPORT_TICKET_SUBJECT_MAX_LENGTH) {
            throw ValidationException::withMessages([
                'project_name' => 'Project name is too long for the generated support ticket subject when a technician is assigned.',
            ]);
        }

        if ($technicianAssignmentProvided && ! $amc->support_ticket_id && $assignedStaff?->user_id) {
            app(SubscriptionLimitService::class)->ensureWithinLimit((int) $user->company_id, 'support', 1, 'assigned_staff_id');
        }

        $supportTicketVisitDate = Carbon::parse($validated['start_date'])->toDateString();

        DB::transaction(function () use ($validated, $user, $amc, $selectedProject, $selectedClient, $assignedStaff, $selectedPlan, $endDate, $includesOnCallSupport, $serviceMode, $technicianAssignmentProvided, $technicianContactNumberProvided, $supportTicketVisitDate) {
            $ticket = $amc->supportTicket;
            $workflowService = app(AmcSupportWorkflowService::class);

            if ($selectedClient) {
                $selectedClient->update([
                    'mobile' => $validated['mobile_number'] ?? null,
                    'phone' => $validated['mobile_number'] ?? null,
                ]);
            }

            if ($technicianAssignmentProvided) {
                if ($assignedStaff?->user_id) {
                    if (! $ticket) {
                        $ticket = Ticket::create([
                            'company_id' => $user->company_id,
                            'user_id' => $user->id,
                            'assigned_to' => $assignedStaff->user_id,
                            'ticket_number' => 'TKT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                            'customer_contact_number' => $validated['mobile_number'] ?? null,
                            'customer_name' => Str::limit($validated['customer_name'], 191, ''),
                            'customer_email' => $validated['email'] ?? null,
                            'type' => 'support',
                            'subject' => $this->buildSupportTicketSubject($validated['project_name']),
                            'priority' => 'medium',
                            'status' => 'open',
                            'visit_date' => $supportTicketVisitDate,
                            'description' => 'AMC updated for project ' . $validated['project_name'] . ' with contract setup and technician assignment.',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        if ($ticket->assigned_to) {
                            app(TicketAssignmentHistoryService::class)->syncCurrentAssignment(
                                $ticket,
                                (int) $ticket->assigned_to,
                                $user,
                                'Initial'
                            );
                        }
                    } else {
                        $ticket->update([
                            'assigned_to' => $assignedStaff->user_id,
                            'customer_contact_number' => $validated['mobile_number'] ?? null,
                            'customer_name' => Str::limit($validated['customer_name'], 191, ''),
                            'customer_email' => $validated['email'] ?? null,
                            'subject' => $this->buildSupportTicketSubject($validated['project_name']),
                            'visit_date' => $supportTicketVisitDate,
                            'description' => 'AMC updated for project ' . $validated['project_name'] . ' with contract setup and technician assignment.',
                        ]);

                        app(TicketAssignmentHistoryService::class)->syncCurrentAssignment(
                            $ticket->refresh(),
                            (int) $assignedStaff->user_id,
                            $user,
                            'Manual'
                        );
                    }
                } elseif ($ticket) {
                    $ticket->update([
                        'assigned_to' => null,
                        'customer_contact_number' => $validated['mobile_number'] ?? null,
                        'customer_name' => Str::limit($validated['customer_name'], 191, ''),
                        'customer_email' => $validated['email'] ?? null,
                        'subject' => $this->buildSupportTicketSubject($validated['project_name']),
                        'visit_date' => $supportTicketVisitDate,
                        'description' => 'AMC updated for project ' . $validated['project_name'] . '.',
                    ]);

                    app(TicketAssignmentHistoryService::class)->syncCurrentAssignment($ticket->refresh(), null, $user, 'Manual');
                }
            } elseif ($ticket) {
                    $ticket->update([
                        'customer_contact_number' => $validated['mobile_number'] ?? null,
                        'customer_name' => Str::limit($validated['customer_name'], 191, ''),
                        'customer_email' => $validated['email'] ?? null,
                        'subject' => $this->buildSupportTicketSubject($validated['project_name']),
                    'visit_date' => $supportTicketVisitDate,
                    'description' => 'AMC updated for project ' . $validated['project_name'] . '.',
                ]);
            }

            $amc->update([
                'project_id' => $selectedProject?->id,
                'client_id' => $selectedClient?->id,
                'amc_plan_id' => $selectedPlan->id,
                'support_ticket_id' => $ticket?->id,
                'customer_name' => $validated['customer_name'],
                'project_name' => $validated['project_name'],
                'mobile_number' => $validated['mobile_number'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'],
                'latitude' => $validated['latitude'] ?? $amc->latitude,
                'longitude' => $validated['longitude'] ?? $amc->longitude,
                'gst_number' => strtoupper(trim((string) ($validated['gst_number'] ?? ''))) ?: null,
                'start_date' => $validated['start_date'],
                'end_date' => $endDate->toDateString(),
                'amc_type' => $validated['amc_type'],
                'plan_name' => $selectedPlan->name,
                'plan_duration_months' => $selectedPlan->duration_months,
                'plan_price' => $selectedPlan->price,
                'plan_description' => $selectedPlan->description,
                'includes_on_call_support' => $includesOnCallSupport,
                'service_mode' => $serviceMode,
                'visit_frequency' => $selectedPlan->generatesScheduledVisits() ? 'manual' : 'on_call',
                'total_visits' => (int) $selectedPlan->visit_count,
                'scheduled_visits' => 0,
                'remaining_visits' => (int) $selectedPlan->visit_count,
                'total_cameras' => $validated['total_cameras'] ?? $amc->total_cameras,
                'camera_types' => array_key_exists('camera_types', $validated) ? array_values($validated['camera_types'] ?? []) : ($amc->camera_types ?? []),
                'dvr_nvr_model' => $validated['dvr_nvr_model'] ?? $amc->dvr_nvr_model,
                'hard_disk_size' => $validated['hard_disk_size'] ?? $amc->hard_disk_size,
                'installation_location' => $validated['installation_location'] ?? $amc->installation_location,
                'service_scope' => array_key_exists('service_scope', $validated) ? array_values($validated['service_scope'] ?? []) : ($amc->service_scope ?? []),
                'amc_amount' => $validated['amc_amount'],
                'discount' => $validated['discount'] ?? 0,
                'tax_percent' => $validated['tax_percent'] ?? 0,
                'total_amount' => $validated['total_amount'],
                'payment_mode' => $validated['payment_mode'],
                'payment_status' => $validated['payment_status'],
                'service_timing' => $validated['service_timing'] ?? null,
                'parts_replacement_policy' => $validated['parts_replacement_policy'] ?? null,
                'emergency_support_terms' => $validated['emergency_support_terms'] ?? null,
                'assigned_staff_id' => $technicianAssignmentProvided ? $assignedStaff?->id : $amc->assigned_staff_id,
                'technician_name' => $technicianAssignmentProvided ? $assignedStaff?->name : $amc->technician_name,
                'technician_contact_number' => $technicianAssignmentProvided
                    ? ($validated['technician_contact_number'] ?? $assignedStaff?->contact_number)
                    : ($technicianContactNumberProvided ? $validated['technician_contact_number'] : $amc->technician_contact_number),
            ]);

            app(AmcSupportWorkflowService::class)->seedVisitSlots($amc->refresh());

            $amc->load('visits');
            foreach ($amc->visits as $scheduledVisit) {
                app(AmcTechnicianAssignmentService::class)->assignTechnicianIfRequired(
                    $scheduledVisit,
                    now(),
                    'amc_update'
                );
            }

            $ticket = $ticket ?: $workflowService->generateSupportTicketForAmc($amc, null, $user);
            $ticket = $workflowService->syncSupportTicketForAmc($amc, null, $user) ?? $ticket;

            $amc->update([
                'support_ticket_id' => $ticket?->id ?? $amc->support_ticket_id,
            ]);
        });

        return redirect()
            ->route('amcs.show', $amc)
            ->with('success', 'AMC updated successfully.');
    }

    public function destroy(Amc $amc)
    {
        $user = auth()->user();

        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($this->canDeleteAmc($user), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);

        $amc->delete();

        return redirect()
            ->route('amcs.index')
            ->with('success', 'AMC deleted successfully.');
    }

    private function buildFormViewData(int $companyId, ?Amc $amc = null): array
    {
        return [
            'amc' => $amc,
            'isEdit' => $amc !== null,
            'amcClients' => Client::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'mobile', 'phone', 'gst_number', 'address', 'latitude', 'longitude']),
            'amcProjects' => Project::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'uuid', 'name', 'client_name']),
            'gstRates' => CompanyGstRate::query()
                ->where('company_id', $companyId)
                ->orderBy('rate')
                ->get(['id', 'rate']),
            'amcPlans' => AmcPlan::query()
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('duration_months')
                ->orderBy('visit_count')
                ->orderBy('name')
                ->get(['id', 'name', 'duration_months', 'visit_count', 'price', 'description', 'includes_on_call_support', 'service_mode']),
            'nextAmcContractNo' => $amc?->contract_no ?: Amc::nextContractNo($companyId),
            'amcTypeOptions' => Amc::TYPE_OPTIONS,
            'amcCameraTypeOptions' => Amc::CAMERA_TYPE_OPTIONS,
            'amcServiceScopeOptions' => Amc::SERVICE_SCOPE_OPTIONS,
            'amcPaymentModeOptions' => Amc::PAYMENT_MODE_OPTIONS,
            'amcPaymentStatusOptions' => Amc::PAYMENT_STATUS_OPTIONS,
            'canManageAmcPlans' => auth()->user()?->isCompanyAdmin(),
        ];
    }

    private function canEditAmc($user): bool
    {
        return (bool) $user && (
            $user->isCompanyAdmin()
            || $user->hasPermission('amc.edit')
        );
    }

    private function canCreateAmc($user): bool
    {
        return (bool) $user && (
            $user->isCompanyAdmin()
            || $user->hasPermission('amc.create')
        );
    }

    private function canDeleteAmc($user): bool
    {
        return (bool) $user && (
            $user->isCompanyAdmin()
            || $user->hasPermission('amc.delete')
        );
    }

    private function amcLockedResponse(Request $request, Amc $amc)
    {
        $message = $amc->serviceClosureMessage();

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        return redirect()
            ->route('amcs.show', $amc)
            ->with('error', $message);
    }

    public function updateVisitStatus(Request $request, Amc $amc, AmcVisit $visit)
    {
        $user = auth()->user();
        abort_unless($this->canEditAmc($user), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);
        abort_unless((int) $visit->amc_id === (int) $amc->id, 404);

        if ($amc->isServiceClosed()) {
            return $this->amcLockedResponse($request, $amc);
        }

        $validated = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'scheduled', 'completed', 'cancelled'])],
            'visit_date' => ['nullable', 'date'],
            'visit_type' => ['nullable', Rule::in(['scheduled', 'on_call', 'hybrid'])],
            'technician' => ['nullable', 'string', 'max:191'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        app(AmcSupportWorkflowService::class)->scheduleVisit($amc, $visit, [
            'status' => $validated['status'] ?? 'pending',
            'visit_date' => $validated['visit_date'] ?? $visit->visit_date?->toDateString(),
            'visit_type' => $validated['visit_type'] ?? $visit->visit_type,
            'technician' => $request->has('technician')
                ? trim((string) $request->input('technician'))
                : $visit->technician,
            'reason' => $validated['reason'] ?? null,
        ], $user);

        app(AmcTechnicianAssignmentService::class)->assignTechnicianIfRequired(
            $visit->fresh(['amc']),
            Carbon::today(),
            'manual_schedule'
        );

        app(AmcSupportWorkflowService::class)->syncSupportTicketForAmc($amc, $visit->fresh(['amc']), $user);

        if ($request->expectsJson()) {
            $amc = $amc->fresh([
                'visits' => function ($query) {
                    $query->select(['id', 'amc_id', 'visit_no', 'visit_date', 'status', 'technician'])
                        ->orderBy('visit_no');
                },
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Visit details updated successfully.',
                'visit_id' => $visit->id,
                'row_html' => view('amcs.partials.row', [
                    'amc' => $amc,
                    'amcTypeOptions' => Amc::TYPE_OPTIONS,
                    'amcPaymentStatusOptions' => Amc::PAYMENT_STATUS_OPTIONS,
                    'canEditAmc' => $this->canEditAmc($user),
                    'canDeleteAmc' => $this->canDeleteAmc($user),
                ])->render(),
            ]);
        }

        return redirect()
            ->route('amcs.show', $amc)
            ->with('success', 'Visit details updated successfully.');
    }

    public function updatePaymentDetails(Request $request, Amc $amc)
    {
        $user = auth()->user();
        abort_unless($this->canEditAmc($user), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);

        if ($amc->isServiceClosed()) {
            return $this->amcLockedResponse($request, $amc);
        }

        $validated = $request->validate([
            'payment_mode' => ['required', Rule::in(array_keys(Amc::PAYMENT_MODE_OPTIONS))],
            'payment_status' => ['required', Rule::in(array_keys(Amc::PAYMENT_STATUS_OPTIONS))],
        ]);

        $amc->update([
            'payment_mode' => $validated['payment_mode'],
            'payment_status' => $validated['payment_status'],
        ]);

        return redirect()
            ->route('amcs.show', $amc)
            ->with('success', 'Payment details updated successfully.');
    }

    public function updateVisitTechnician(Request $request, Amc $amc, AmcVisit $visit)
    {
        $user = auth()->user();

        abort_unless($this->canEditAmc($user), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);
        abort_unless((int) $visit->amc_id === (int) $amc->id, 404);

        if ($amc->isServiceClosed()) {
            return $this->amcLockedResponse($request, $amc);
        }

        $technicianIds = Staff::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'technician_id' => ['nullable', 'integer', Rule::in($technicianIds)],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $technician = filled($validated['technician_id'] ?? null)
            ? Staff::query()->where('company_id', $user->company_id)->find($validated['technician_id'])
            : null;

        app(AmcTechnicianAssignmentService::class)->updateVisitTechnician(
            $visit,
            $technician,
            $user,
            $request->filled('reason') ? trim((string) $request->input('reason')) : null,
            'manual'
        );

        app(AmcSupportWorkflowService::class)->syncSupportTicketForAmc($amc, $visit->fresh(['amc']), $user);

        return response()->json([
            'success' => true,
            'message' => 'Visit technician updated successfully.',
            'visit_id' => $visit->id,
            'technician_id' => $technician?->id,
            'technician_name' => $technician?->name,
        ]);
    }

    public function updateOnCallSupportDetails(Request $request, Amc $amc)
    {
        $user = auth()->user();

        abort_unless($this->canEditAmc($user), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);
        abort_unless($amc->isOnCall(), 404);

        if ($amc->isServiceClosed()) {
            return $this->amcLockedResponse($request, $amc);
        }

        $technicianIds = Staff::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'visit_date' => ['required', 'date', 'after_or_equal:today'],
            'assigned_staff_id' => ['nullable', 'integer', Rule::in($technicianIds)],
        ]);

        $assignedStaff = filled($validated['assigned_staff_id'] ?? null)
            ? Staff::query()->with('user:id,name')->where('company_id', $user->company_id)->find($validated['assigned_staff_id'])
            : null;

        DB::transaction(function () use ($amc, $assignedStaff, $validated, $user): void {
            $workflowService = app(AmcSupportWorkflowService::class);
            $ticket = $workflowService->ensureCurrentSupportTicketForAmc($amc, $user);

            $ticket->update([
                'visit_date' => $validated['visit_date'],
                'assigned_to' => $assignedStaff?->user_id,
            ]);

            app(TicketAssignmentHistoryService::class)->syncCurrentAssignment(
                $ticket->refresh(),
                $assignedStaff?->user_id ? (int) $assignedStaff->user_id : null,
                $user,
                'Manual'
            );

            $amc->update([
                'assigned_staff_id' => $assignedStaff?->id,
                'technician_name' => $assignedStaff?->name ?? null,
                'technician_contact_number' => $assignedStaff?->contact_number ?? null,
                'support_ticket_id' => $ticket->id,
            ]);
        });

        $amc = $amc->fresh(['project', 'client', 'visits', 'assignedStaff.user', 'supportTicket']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'On-call support details updated successfully.',
                'support_ticket_id' => $amc->support_ticket_id,
                'technician_name' => $amc->technician_name,
                'visit_date' => $amc->supportTicket?->visit_date?->toDateString(),
            ]);
        }

        return redirect()
            ->route('amcs.show', $amc)
            ->with('success', 'On-call support details updated successfully.');
    }

    public function updateAssignedTechnician(Request $request, Amc $amc)
    {
        $user = auth()->user();

        abort_unless($this->canEditAmc($user), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);

        if ($amc->isServiceClosed()) {
            return $this->amcLockedResponse($request, $amc);
        }

        if (! $request->has('assigned_staff_id') && $request->has('technician_id')) {
            $request->merge([
                'assigned_staff_id' => $request->input('technician_id'),
            ]);
        }

        $technicianIds = Staff::query()
            ->where('company_id', $user->company_id)
            ->pluck('id')
            ->all();

        $validated = $request->validate([
            'assigned_staff_id' => ['nullable', 'integer', Rule::in($technicianIds)],
        ]);

        $assignedStaff = filled($validated['assigned_staff_id'] ?? null)
            ? Staff::query()->with('user:id,name')->where('company_id', $user->company_id)->find($validated['assigned_staff_id'])
            : null;

        $amc->update([
            'assigned_staff_id' => $assignedStaff?->id,
            'technician_name' => $assignedStaff?->name ?? null,
            'technician_contact_number' => $assignedStaff?->contact_number ?? null,
        ]);

        app(AmcSupportWorkflowService::class)->syncSupportTicketForAmc($amc, null, $user);

        $amc = $amc->fresh([
            'project',
            'client',
            'visits',
            'assignedStaff.user',
            'supportTicket',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Technician updated successfully.',
            'assigned_staff_id' => $amc->assigned_staff_id,
            'assigned_staff_name' => $amc->technician_name,
            'assigned_staff_contact_number' => $amc->technician_contact_number,
            'row_html' => view('amcs.partials.row', [
                'amc' => $amc,
                'amcTypeOptions' => Amc::TYPE_OPTIONS,
                'amcPaymentStatusOptions' => Amc::PAYMENT_STATUS_OPTIONS,
                'canEditAmc' => $this->canEditAmc($user),
                'canDeleteAmc' => $this->canDeleteAmc($user),
            ])->render(),
        ]);
    }

    private function filteredAmcsQuery(Request $request)
    {
        $search = trim((string) $request->string('search'));
        $amcTypeFilter = $this->normalizeAmcType($request->query('amc_type'));

        return Amc::query()
            ->where('company_id', (int) auth()->user()->company_id)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';

                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery->where('contract_no', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('project_name', 'like', $like)
                        ->orWhere('mobile_number', 'like', $like)
                        ->orWhere('email', 'like', $like);
                });
            })
            ->when($request->filled('start_date_from'), fn ($query) => $query->whereDate('start_date', '>=', $request->date('start_date_from')))
            ->when($request->filled('start_date_to'), fn ($query) => $query->whereDate('start_date', '<=', $request->date('start_date_to')))
            ->when($request->filled('plan'), fn ($query) => $query->where('amc_plan_id', $request->integer('plan')))
            ->when($amcTypeFilter !== null, fn ($query) => $query->where('amc_type', $amcTypeFilter))
            ->when($request->filled('price_min'), fn ($query) => $query->where('total_amount', '>=', (float) $request->input('price_min')))
            ->when($request->filled('price_max'), fn ($query) => $query->where('total_amount', '<=', (float) $request->input('price_max')))
            ->when($request->filled('payment_mode'), fn ($query) => $query->where('payment_mode', $request->string('payment_mode')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')));
    }

    private function filterAmcsByMode(Collection $amcs, ?string $mode): Collection
    {
        $mode = trim((string) $mode);

        if ($mode === '' || $mode === 'all') {
            return $amcs->values();
        }

        return $amcs
            ->filter(function (Amc $amc) use ($mode) {
                if ($mode === 'schedule') {
                    return in_array($amc->service_mode, ['scheduled', null, ''], true);
                }

                return $amc->service_mode === $mode;
            })
            ->values();
    }

    private function buildModeCounters(Builder $query): array
    {
        return [
            'all' => (clone $query)->count(),
            'schedule' => (clone $query)->where(function ($builder) {
                $builder->where('service_mode', 'scheduled')
                    ->orWhereNull('service_mode');
            })->count(),
            'on_call' => (clone $query)->where('service_mode', 'on_call')->count(),
            'hybrid' => (clone $query)->where('service_mode', 'hybrid')->count(),
        ];
    }

    private function amcListingCollection(Request $request): Collection
    {
        return $this->filteredAmcsQuery($request)
            ->with(['project', 'client', 'visits', 'assignedStaff.user', 'supportTicket'])
            ->latest()
            ->get();
    }

    private function visitRangeOptions(): array
    {
        return [
            'today' => [
                'label' => 'Today AMC',
                'tone' => 'bg-amber-50 text-amber-800 border-amber-200',
                'active_tone' => 'bg-amber-100 text-amber-900 border-amber-300 ring-1 ring-amber-200',
            ],
            'week' => [
                'label' => 'This Week AMC',
                'tone' => 'bg-sky-50 text-sky-800 border-sky-200',
                'active_tone' => 'bg-sky-100 text-sky-900 border-sky-300 ring-1 ring-sky-200',
            ],
            'month' => [
                'label' => 'This Month AMC',
                'tone' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'active_tone' => 'bg-emerald-100 text-emerald-900 border-emerald-300 ring-1 ring-emerald-200',
            ],
            'next_month' => [
                'label' => 'Next Month AMC',
                'tone' => 'bg-violet-50 text-violet-800 border-violet-200',
                'active_tone' => 'bg-violet-100 text-violet-900 border-violet-300 ring-1 ring-violet-200',
            ],
        ];
    }

    private function visitStatusOptions(): array
    {
        return [
            'pending' => [
                'label' => 'Pending',
                'tone' => 'bg-amber-50 text-amber-800 border-amber-200',
                'active_tone' => 'bg-amber-100 text-amber-900 border-amber-300 ring-1 ring-amber-200',
            ],
            'scheduled' => [
                'label' => 'Scheduled',
                'tone' => 'bg-sky-50 text-sky-800 border-sky-200',
                'active_tone' => 'bg-sky-100 text-sky-900 border-sky-300 ring-1 ring-sky-200',
            ],
            'completed' => [
                'label' => 'Completed',
                'tone' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
                'active_tone' => 'bg-emerald-100 text-emerald-900 border-emerald-300 ring-1 ring-emerald-200',
            ],
            'cancelled' => [
                'label' => 'Cancelled',
                'tone' => 'bg-rose-50 text-rose-800 border-rose-200',
                'active_tone' => 'bg-rose-100 text-rose-900 border-rose-300 ring-1 ring-rose-200',
            ],
        ];
    }

    private function normalizeVisitRange(?string $visitRange): ?string
    {
        $visitRange = trim((string) $visitRange);
        $validRanges = array_keys($this->visitRangeOptions());

        return in_array($visitRange, $validRanges, true) ? $visitRange : null;
    }

    private function normalizeVisitStatus(?string $visitStatus): ?string
    {
        $visitStatus = trim((string) $visitStatus);
        $validStatuses = array_keys($this->visitStatusOptions());

        return in_array($visitStatus, $validStatuses, true) ? $visitStatus : null;
    }

    private function normalizeAmcType(?string $amcType): ?string
    {
        $amcType = trim((string) $amcType);
        $validTypes = array_keys(Amc::TYPE_OPTIONS);

        return in_array($amcType, $validTypes, true) ? $amcType : null;
    }

    private function filterAmcsByVisitRange(Collection $amcs, ?string $visitRange): Collection
    {
        if (! $visitRange) {
            return $amcs->values();
        }

        return $amcs
            ->filter(fn (Amc $amc) => $this->matchesVisitRange($amc, $visitRange))
            ->values();
    }

    private function filterAmcsByVisitStatus(Collection $amcs, ?string $lastVisitStatus, ?string $nextVisitStatus = null): Collection
    {
        if (! $lastVisitStatus && ! $nextVisitStatus) {
            return $amcs->values();
        }

        return $amcs
            ->filter(function (Amc $amc) use ($lastVisitStatus, $nextVisitStatus) {
                $lastStatusMatches = ! $lastVisitStatus || $amc->lastVisitStatusKey() === $lastVisitStatus;
                $nextStatusMatches = ! $nextVisitStatus || $amc->nextVisitStatusKey() === $nextVisitStatus;

                return $lastStatusMatches && $nextStatusMatches;
            })
            ->values();
    }

    private function filterAmcsByTechnician(Collection $amcs, ?string $technicianFilter): Collection
    {
        $technicianFilter = trim((string) $technicianFilter);

        if ($technicianFilter === '') {
            return $amcs->values();
        }

        if ($technicianFilter === 'unassigned') {
            return $amcs
                ->filter(fn (Amc $amc) => blank($this->amcTechnicianLabel($amc)))
                ->values();
        }

        return $amcs
            ->filter(function (Amc $amc) use ($technicianFilter) {
                return mb_strtolower(trim((string) $this->amcTechnicianLabel($amc))) === mb_strtolower($technicianFilter);
            })
            ->values();
    }

    private function amcTechnicianLabel(Amc $amc): ?string
    {
        if ($amc->isOnCall()) {
            return $amc->assignedStaff?->name ?: $amc->technician_name ?: null;
        }

        return $amc->nextVisit()?->technician ?: null;
    }

    private function buildVisitRangeCounts(Collection $amcs): array
    {
        $counts = [];

        foreach (array_keys($this->visitRangeOptions()) as $visitRange) {
            $counts[$visitRange] = $amcs->filter(fn (Amc $amc) => $this->matchesVisitRange($amc, $visitRange))->count();
        }

        return $counts;
    }

    private function buildVisitStatusCounts(Collection $amcs): array
    {
        $counts = [
            'last' => [],
            'next' => [],
        ];

        foreach (array_keys($this->visitStatusOptions()) as $statusKey) {
            $counts['last'][$statusKey] = $amcs->filter(fn (Amc $amc) => $amc->lastVisitStatusKey() === $statusKey)->count();
            $counts['next'][$statusKey] = $amcs->filter(fn (Amc $amc) => $amc->nextVisitStatusKey() === $statusKey)->count();
        }

        return $counts;
    }

    private function buildAmcTypeCounts(Collection $amcs): array
    {
        $counts = [];

        foreach (array_keys(Amc::TYPE_OPTIONS) as $typeKey) {
            $counts[$typeKey] = $amcs->where('amc_type', $typeKey)->count();
        }

        return $counts;
    }

    private function matchesVisitRange(Amc $amc, string $visitRange): bool
    {
        $visitDate = $amc->nextVisit()?->visit_date;

        if (! $visitDate) {
            return false;
        }

        $today = Carbon::today();

        [$startDate, $endDate] = match ($visitRange) {
            'today' => [
                $today->copy()->startOfDay(),
                $today->copy()->endOfDay(),
            ],
            'week' => [
                $today->copy()->startOfWeek()->startOfDay(),
                $today->copy()->endOfWeek()->endOfDay(),
            ],
            'month' => [
                $today->copy()->startOfMonth()->startOfDay(),
                $today->copy()->endOfMonth()->endOfDay(),
            ],
            'next_month' => [
                $today->copy()->addMonthNoOverflow()->startOfMonth()->startOfDay(),
                $today->copy()->addMonthNoOverflow()->endOfMonth()->endOfDay(),
            ],
            default => [null, null],
        };

        if (! $startDate || ! $endDate) {
            return false;
        }

        return $visitDate->between($startDate, $endDate);
    }

    private function paginateAmcs(Collection $amcs, Request $request, int $perPage = 10): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $pageItems = $amcs->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $pageItems,
            $amcs->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]
        );
    }
}
