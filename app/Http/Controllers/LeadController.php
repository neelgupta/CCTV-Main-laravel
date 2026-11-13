<?php

namespace App\Http\Controllers;

use App\Exports\LeadExport;
use App\Exports\LeadImportSampleExport;
use App\Models\Lead;
use App\Models\Client;
use App\Models\CalendarEvent;
use App\Models\Company;
use App\Models\LeadStatus;
use App\Models\LeadCategory;
use App\Models\LeadComment;
use App\Models\User;
use App\Models\LeadActivity;
use App\Models\LeadFollowupSchedule;
use App\Models\LeadQuotation;
use App\Models\LeadMaterial;
use App\Models\CompanyGstRate;
use App\Models\CompanyUnit;
use App\Models\BoqProduct;
use App\Models\BoqCategory;
use App\Models\Project;
use App\Models\State;
use App\Models\Ticket;
use App\Imports\LeadImport;
use App\Services\MediaUploadService;
use App\Http\Requests\StoreLeadRequest;
use App\Http\Requests\UpdateLeadRequest;
use App\Services\ClientRelationSyncService;
use App\Services\ClientSyncService;
use App\Services\LeadCompanyConversionService;
use App\Services\LeadProjectConversionService;
use App\Services\TicketAssignmentHistoryService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Ogwebsolutions\FormBuilder\Models\OgForm;

class LeadController extends Controller
{
    private function companyId(): ?int
    {
        $user = auth()->user();
        return $user?->company_id ?: $user?->roleRecord?->company_id;
    }

    public function index()
    {
        $companyId = $this->companyId();
        $authUser = auth()->user();
        $company = $companyId ? \App\Models\Company::query()->find($companyId) : null;
        if ($companyId) {
            LeadStatus::ensureWon($companyId);
            LeadStatus::ensureLoss($companyId);
        }

        $allStatuses = LeadStatus::query()
            ->orderBy('order')
            ->get();

        $search = trim((string) request('search', ''));
        $statusFilter = request('status');
        $categoryFilter = request('category');
        $priorityFilter = request('priority');
        $assignedToFilter = request('assigned_to');
        $sourceFilter = $this->normalizeTextFilter(request('source'));
        $stateFilter = request('state_id');
        $cityFilter = request('city_id');
        $followUpFromFilter = $this->normalizeDateFilter(request('follow_up_from'));
        $followUpToFilter = $this->normalizeDateFilter(request('follow_up_to'));
        $followupCardFilter = in_array(request('followup_card'), ['total', 'today', 'done_today', 'pending_today', 'missed'], true)
            ? request('followup_card')
            : null;
        $followupStatusFilter = in_array(
            strtolower(trim((string) request('followup_status'))),
            ['pending', 'done', 'not interested', 'converted', 'callback', 'follow-up required'],
            true
        ) ? trim((string) request('followup_status')) : null;
        $requestedView = request('view') === 'list' ? 'list' : 'kanban';
        $customFieldFilters = $this->normalizeLeadCustomFieldFilters($company, request('custom_fields', []));
        $monthFilter = (int) request('month', 0);
        $yearFilter = (int) request('year', 0);

        if ($monthFilter < 1 || $monthFilter > 12) {
            $monthFilter = null;
        }

        if ($yearFilter < 2000 || $yearFilter > ((int) now()->year + 10)) {
            $yearFilter = null;
        }

        $filters = [
            'search' => $search,
            'category' => $categoryFilter,
            'priority' => $priorityFilter,
            'assigned_to' => $assignedToFilter,
            'source' => $sourceFilter,
            'state_id' => $stateFilter,
            'city_id' => $cityFilter,
            'follow_up_from' => $followUpFromFilter,
            'follow_up_to' => $followUpToFilter,
            'followup_card' => $followupCardFilter,
            'followup_status' => $followupStatusFilter,
            'custom_fields' => $customFieldFilters,
            'custom_field_definitions' => $company?->normalizedLeadCustomFields() ?? [],
            'month' => $monthFilter,
            'year' => $yearFilter,
        ];

        $statuses = LeadStatus::query()
            ->when($statusFilter, fn ($query) => $query->whereKey($statusFilter))
            ->with([
                'leads' => function ($q) use ($filters) {
                    $q->select([
                        'id',
                        'uuid',
                        'company_id',
                        'lead_status_id',
                        'lead_category_id',
                        'assigned_to',
                        'client_name',
                        'email',
                        'mobile',
                        'gst_number',
                        'pan_number',
                        'address',
                        'referral_person_name',
                        'custom_fields',
                        'estimated_value',
                        'priority',
                        'source',
                        'state_id',
                        'city_id',
                        'next_follow_up',
                        'created_company_id',
                        'created_at',
                    ])
                    ->with([
                        'category:id,name',
                        'assignedTo:id,name',
                        'assignedUsers:id,name',
                        'state:id,name',
                        'city:id,name',
                        'project:id,uuid,lead_id',
                        'createdCompany:id,name',
                        'activities' => function ($activityQuery) {
                            $activityQuery->select(['id', 'lead_id', 'user_id', 'type', 'description', 'occurred_at'])
                                ->with('user:id,name')
                                ->latest('occurred_at');
                        },
                        'comments' => function ($commentQuery) {
                            $commentQuery->select(['id', 'lead_id', 'user_id', 'comment', 'created_at'])
                                ->with('user:id,name')
                                ->latest('created_at');
                        },
                        'followupHistories' => function ($historyQuery) {
                            $historyQuery->select(['id', 'lead_id', 'summary', 'status', 'followup_date', 'next_followup_date', 'created_by', 'created_at'])
                                ->with('creator:id,name')
                                ->latest('created_at');
                        },
                        'followupSchedules' => function ($scheduleQuery) {
                            $scheduleQuery->select(['id', 'lead_id', 'followup_date', 'status', 'completed_at', 'created_by', 'created_at'])
                                ->orderByRaw("CASE LOWER(status) WHEN 'pending' THEN 1 ELSE 2 END")
                                ->orderBy('followup_date');
                        },
                    ])
                    ->accessibleTo(auth()->user());

                    $this->applyLeadFilters($q, $filters)
                    ->latest('created_at');
                },
            ])
            ->orderBy('order')
            ->get();

        $leadStatusCounts = $this->applyLeadFilters(
            Lead::query()->accessibleTo($authUser),
            array_merge($filters, ['followup_status' => null])
        )
            ->selectRaw('lead_status_id, COUNT(*) as lead_count')
            ->groupBy('lead_status_id')
            ->pluck('lead_count', 'lead_status_id');

        $followupStatusLabels = [
            'Pending',
            'Done',
            'Not Interested',
            'Converted',
            'Callback',
            'Follow-up Required',
        ];

        $followupStatusCounts = collect($followupStatusLabels)->mapWithKeys(function (string $label) use ($authUser, $filters) {
            $count = $this->applyLeadFilters(
                Lead::query()->accessibleTo($authUser),
                array_merge($filters, ['followup_status' => null])
            )
                ->whereHas('latestFollowupHistory', function ($historyQuery) use ($label) {
                    $historyQuery->whereRaw('LOWER(status) = ?', [strtolower($label)]);
                })
                ->count();

            return [$label => $count];
        })->all();

        $years = Lead::query()
            ->accessibleTo($authUser)
            ->selectRaw('DISTINCT YEAR(created_at) as year')
            ->orderByDesc('year')
            ->pluck('year')
            ->filter()
            ->map(fn ($year) => (int) $year)
            ->values();

        if ($years->isEmpty()) {
            $years = collect([(int) now()->year]);
        }

        $categories = LeadCategory::query()
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $users = User::query()
            ->where('company_id', $companyId)
            ->activeAssigneeOrAdmin()
            ->orderBy('name')
            ->get(['id', 'name']);

        $leadSources = $company?->normalizedLeadSources($sourceFilter) ?? [];
        $leadCustomFields = $company?->normalizedLeadCustomFields() ?? [];
        $kanbanCardFields = collect([
            ['key' => 'category', 'label' => 'Category', 'default_selected' => true],
            ['key' => 'estimated_value', 'label' => 'Estimated Value', 'default_selected' => true],
            ['key' => 'priority', 'label' => 'Priority', 'default_selected' => true],
            ['key' => 'assigned_users', 'label' => 'Assigned Users', 'default_selected' => true],
            ['key' => 'created_at', 'label' => 'Created', 'default_selected' => true],
            ['key' => 'email', 'label' => 'Email', 'default_selected' => false],
            ['key' => 'mobile', 'label' => 'Mobile', 'default_selected' => false],
            ['key' => 'source', 'label' => 'Source', 'default_selected' => false],
            ['key' => 'state', 'label' => 'State', 'default_selected' => false],
            ['key' => 'city', 'label' => 'City', 'default_selected' => false],
            ['key' => 'next_follow_up', 'label' => 'Next Follow Up', 'default_selected' => false],
            ['key' => 'gst_number', 'label' => 'GST Number', 'default_selected' => false],
            ['key' => 'pan_number', 'label' => 'PAN Number', 'default_selected' => false],
            ['key' => 'address', 'label' => 'Address', 'default_selected' => false],
            ['key' => 'referral_person_name', 'label' => 'Referral Person', 'default_selected' => false],
        ])->merge(
            collect($leadCustomFields)->map(function (array $field) {
                return [
                    'key' => 'custom:' . ($field['key'] ?? ''),
                    'label' => (string) ($field['label'] ?? 'Custom Field'),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'default_selected' => false,
                ];
            })
        )->values()->all();
        $filterableLeadCustomFields = collect($leadCustomFields)
            ->filter(fn (array $field) => (bool) ($field['show_in_filters'] ?? false))
            ->values();
        $states = State::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);

        $companyStats = null;
        if ($company) {
            $today = now()->toDateString();
            $followupScheduleBase = LeadFollowupSchedule::query()
                ->whereHas('lead', fn ($leadQuery) => $leadQuery->accessibleTo($authUser));

            $companyStats = [
                'total_followups' => (clone $followupScheduleBase)
                    ->whereRaw('LOWER(status) <> ?', ['done'])
                    ->count(),
                'today_followups' => (clone $followupScheduleBase)
                    ->whereDate('followup_date', $today)
                    ->count(),
                'missed_followups' => (clone $followupScheduleBase)
                    ->whereHas('lead.status', function ($statusQuery) {
                        $statusQuery->whereRaw('LOWER(name) NOT IN (?, ?)', ['won', 'loss']);
                    })
                    ->whereDate('followup_date', '<', $today)
                    ->whereRaw('LOWER(status) = ?', ['pending'])
                    ->count(),
                'done_today' => (clone $followupScheduleBase)
                    ->whereDate('followup_date', $today)
                    ->whereRaw('LOWER(status) = ?', ['done'])
                    ->count(),
                'pending_today' => (clone $followupScheduleBase)
                    ->whereDate('followup_date', $today)
                    ->whereRaw('LOWER(status) = ?', ['pending'])
                    ->count(),
            ];
        }

        $listLeads = $this->applyLeadFilters(
            Lead::query()
                ->accessibleTo($authUser)
                ->when($statusFilter, fn ($query) => $query->where('lead_status_id', $statusFilter))
                ->with([
                    'status:id,name,color',
                    'category:id,name',
                    'assignedTo:id,name',
                    'assignedUsers:id,name',
                    'project:id,uuid,lead_id',
                    'createdCompany:id,name',
                    'activities' => function ($activityQuery) {
                        $activityQuery->select(['id', 'lead_id', 'user_id', 'type', 'description', 'occurred_at'])
                            ->with('user:id,name')
                            ->latest('occurred_at');
                    },
                    'comments' => function ($commentQuery) {
                        $commentQuery->select(['id', 'lead_id', 'user_id', 'comment', 'created_at'])
                            ->with('user:id,name')
                            ->latest('created_at');
                    },
                    'followupHistories' => function ($historyQuery) {
                        $historyQuery->select(['id', 'lead_id', 'summary', 'status', 'followup_date', 'next_followup_date', 'created_by', 'created_at'])
                            ->with('creator:id,name')
                            ->latest('created_at');
                    },
                    'followupSchedules' => function ($scheduleQuery) {
                        $scheduleQuery->select(['id', 'lead_id', 'followup_date', 'status', 'completed_at', 'created_by', 'created_at'])
                            ->orderByRaw("CASE LOWER(status) WHEN 'pending' THEN 1 ELSE 2 END")
                            ->orderBy('followup_date');
                    },
                ]),
            $filters
        )
            ->latest('created_at')
            ->paginate(15, ['*'], 'leads_page')
            ->withQueryString();

        $companyLeadsForAppointments = collect();
        $definedAppointmentSlots = collect();
        if ($company?->allow_appointment_module ?? false) {
            $excludedStatusIds = \App\Models\LeadStatus::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where(function ($query) {
                    $query->whereRaw('LOWER(TRIM(name)) in (?, ?, ?)', ['won', 'lost', 'loss'])
                        ->orWhereRaw('LOWER(name) like ?', ['%won%'])
                        ->orWhereRaw('LOWER(name) like ?', ['%lost%'])
                        ->orWhereRaw('LOWER(name) like ?', ['%loss%']);
                })
                ->pluck('id');

            $companyLeadsForAppointments = Lead::query()
                ->where('company_id', $companyId)
                ->whereNotIn('lead_status_id', $excludedStatusIds)
                ->accessibleTo($authUser)
                ->orderBy('client_name')
                ->get(['id', 'client_name', 'mobile', 'email']);
            $definedAppointmentSlots = collect(\App\Models\LeadAppointment::definedSlots());
        }

        $defaultLossReasons = Lead::defaultLossReasons();

        return view('leads.index', compact(
            'defaultLossReasons',
            'companyLeadsForAppointments',
            'definedAppointmentSlots',
            'statuses',
            'leadStatusCounts',
            'followupStatusCounts',
            'followupStatusLabels',
            'listLeads',
            'allStatuses',
            'companyStats',
            'categories',
            'users',
            'leadSources',
            'leadCustomFields',
            'kanbanCardFields',
            'filterableLeadCustomFields',
            'customFieldFilters',
            'states',
            'search',
            'statusFilter',
            'categoryFilter',
            'priorityFilter',
            'assignedToFilter',
            'sourceFilter',
            'stateFilter',
            'cityFilter',
            'followUpFromFilter',
            'followUpToFilter',
            'followupCardFilter',
            'requestedView',
            'monthFilter',
            'yearFilter',
            'years'
        ));
    }

    public function export(Request $request)
    {
        $fileName = 'leads-' . now()->format('Y-m-d-His') . '.xlsx';
        $companyId = $this->companyId();
        $company = $companyId ? Company::query()->find($companyId) : null;
        $leads = $this->filteredLeadsQuery($request)
            ->with(['category:id,name', 'status:id,name', 'assignedUsers:id,name', 'state:id,name', 'city:id,name'])
            ->orderByDesc('created_at')
            ->get();

        return Excel::download(new LeadExport($leads, $company), $fileName);
    }

    public function import(Request $request)
    {
        $request->validate([
            'lead_import_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'lead_import_file.required' => 'Please choose a lead import file.',
            'lead_import_file.mimes' => 'Lead import file must be CSV, XLS, or XLSX.',
        ]);

        $companyId = $this->companyId();
        abort_unless($companyId, 403);

        $company = Company::query()->findOrFail($companyId);
        $states = State::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);
        ['state_id' => $defaultStateId, 'city_id' => $defaultCityId] = $this->resolveCompanyLocationDefaults($company, $states);

        Excel::import(
            new LeadImport(auth()->user(), $company, $defaultStateId, $defaultCityId),
            $request->file('lead_import_file')
        );

        return redirect()
            ->route('leads.index')
            ->with('success', 'Leads imported successfully.');
    }

    public function downloadImportSample()
    {
        $companyId = $this->companyId();
        $company = $companyId ? Company::query()->find($companyId) : null;

        return Excel::download(
            new LeadImportSampleExport($company),
            'lead-import-sample.xlsx'
        );
    }

    public function create()
    {
        $companyId = $this->companyId();
        $company = $companyId ? \App\Models\Company::query()->find($companyId) : null;
        $newLeadStatus = null;
        if ($companyId) {
            $newLeadStatus = LeadStatus::ensureNewLead($companyId);
            LeadStatus::ensureWon($companyId);
        }
        $statuses = LeadStatus::orderBy('order')->get();
        $defaultStatusId = $newLeadStatus?->id
            ?? $statuses->first(function (LeadStatus $status) {
                return strtolower((string) $status->name) === 'new lead';
            })?->id
            ?? $statuses->firstWhere('is_default', true)?->id
            ?? $statuses->first()?->id;
        $categories = LeadCategory::query()
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->orderBy('name')
            ->get();
        $users = $this->leadAssignableUsers($companyId);
        $leadSources = $company?->normalizedLeadSources() ?? [];
        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile', 'phone', 'address', 'state_id', 'city_id']);
        $states = State::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);
        ['state_id' => $defaultStateId, 'city_id' => $defaultCityId] = $this->resolveCompanyLocationDefaults($company, $states);

        return view('leads.create', compact(
            'statuses',
            'categories',
            'users',
            'leadSources',
            'clients',
            'defaultStatusId',
            'states',
            'company',
            'defaultStateId',
            'defaultCityId'
        ));
    }

    public function edit(Lead $lead)
    {
        $companyId = $this->companyId();
        $company = $companyId ? \App\Models\Company::query()->find($companyId) : null;
        if ($companyId) {
            LeadStatus::ensureWon($companyId);
            LeadStatus::ensureLoss($companyId);
        }
        $statuses = LeadStatus::orderBy('order')->get();
        $categories = LeadCategory::query()
            ->where(function ($query) use ($companyId) {
                $query->whereNull('company_id')->orWhere('company_id', $companyId);
            })
            ->orderBy('name')
            ->get();
        $users = $this->leadAssignableUsers($companyId);
        $leadSources = $company?->normalizedLeadSources($lead->source)
            ?? (filled($lead->source) ? [[
                'name' => Str::squish((string) $lead->source),
                'show_referral_person_name' => false,
            ]] : []);
        $lead->loadMissing('assignedUsers:id,name');
        $states = State::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);
        ['state_id' => $defaultStateId, 'city_id' => $defaultCityId] = $this->resolveCompanyLocationDefaults($company, $states);
        $clients = Client::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile', 'phone', 'address', 'state_id', 'city_id']);
        $selectedExistingClientId = $this->resolveExistingClientIdForLead($companyId, $lead);
        $defaultLossReasons = Lead::defaultLossReasons();

        return view('leads.edit', compact('lead', 'statuses', 'categories', 'users', 'leadSources', 'states', 'company', 'defaultStateId', 'defaultCityId', 'clients', 'selectedExistingClientId', 'defaultLossReasons'));
    }

    public function store(StoreLeadRequest $request)
    {
        // dd($request->all());
        $companyId = $this->companyId();
        abort_unless($companyId, 403);

        $validated = $request->validated();
        $selectedExistingClientId = filled($validated['existing_client_id'] ?? null)
            ? (int) $validated['existing_client_id']
            : null;

        if ($selectedExistingClientId) {
            $selectedClient = Client::query()
                ->where('company_id', $companyId)
                ->findOrFail($selectedExistingClientId);

            $validated = $this->syncLeadPayloadWithExistingClient($validated, $selectedClient);
        }

        if (! $selectedExistingClientId) {
            $this->ensureLeadClientDoesNotAlreadyExistInCompany(
                (int) $companyId,
                $validated['client_name'] ?? null,
                $validated['email'] ?? null,
                $validated['mobile'] ?? null,
            );
        }

        $validated['referral_person_name'] = $this->normalizeReferralPersonNameForSource(
            $companyId ? Company::query()->find($companyId) : null,
            $validated['source'] ?? null,
            $validated['referral_person_name'] ?? null,
        );
        $validated['company_id'] = $companyId;
        $assignedUserIds = collect($validated['assigned_to'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->push((int) auth()->id())
            ->unique()
            ->values()
            ->all();

        $validated['assigned_to'] = $assignedUserIds[0] ?? null;
        $validated['created_by'] = auth()->id();
        $subscriptionLimits = app(SubscriptionLimitService::class);
        $subscriptionLimits->ensureWithinLimit((int) $companyId, 'leads', 1, 'client_name');

        $newlyAssignedUserIds = [];

        DB::transaction(function () use (&$lead, &$newlyAssignedUserIds, $validated, $assignedUserIds) {
            $lead = Lead::create($validated);
            $newlyAssignedUserIds = $this->syncAssignedUsers($lead, $assignedUserIds);

            if (empty($validated['existing_client_id'])) {
                $client = app(\App\Services\ClientSyncService::class)->upsert([
                    'company_id' => $lead->company_id,
                    'name' => $lead->client_name,
                    'email' => $lead->email,
                    'phone' => $lead->mobile,
                    'gst_number' => $lead->gst_number,
                    'pan_number' => $lead->pan_number,
                    'state_id' => $lead->state_id,
                    'city_id' => $lead->city_id,
                    'address' => $lead->address,
                ]);
                if ($client?->email) {
                    app(\App\Services\ClientSyncService::class)->sendPasswordSetup($client);
                }
            }

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'status_change',
                'description' => 'Lead created with status: ' . $lead->status->name,
            ]);
        });

        $additionalAssigneeIds = array_diff($newlyAssignedUserIds, [(int) $lead->assigned_to]);

        if ($additionalAssigneeIds !== []) {
            app(\App\Services\DashboardNotificationService::class)->notifyLeadAssignedToUsers($lead, $additionalAssigneeIds);
        }

        return redirect()->route('leads.index')->with('success', 'Lead created successfully.');
    }

    public function update(UpdateLeadRequest $request, Lead $lead, ClientRelationSyncService $relationSync)
    {
        $validated = $request->validated();
        $company = $lead->company_id ? Company::query()->find($lead->company_id) : null;

        if (filled($validated['email'] ?? null) && $company && $company->isInternalStaffOrAdminEmail($validated['email'])) {
            throw ValidationException::withMessages([
                'email' => 'This email belongs to a company administrator or staff member and cannot be assigned to a lead or client.',
            ]);
        }
        $validated['referral_person_name'] = $this->normalizeReferralPersonNameForSource(
            $company,
            $validated['source'] ?? null,
            $validated['referral_person_name'] ?? null,
        );
        $assignedUserIds = collect($validated['assigned_to'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $validated['assigned_to'] = $assignedUserIds[0] ?? null;

        $targetStatusId = $validated['lead_status_id'] ?? $lead->lead_status_id;
        $targetStatus = $targetStatusId ? LeadStatus::find($targetStatusId) : null;
        $isLoss = $targetStatus && in_array(strtolower(trim((string) $targetStatus->name)), ['loss', 'lost'], true);
        if (!$isLoss) {
            $validated['loss_reason'] = null;
        } elseif (empty(trim((string) ($validated['loss_reason'] ?? '')))) {
            throw ValidationException::withMessages([
                'loss_reason' => 'Loss reason is required when stage is set to Loss.',
            ]);
        }

        $previous = $relationSync->snapshotLead($lead);

        $newlyAssignedUserIds = [];

        DB::transaction(function () use ($lead, &$newlyAssignedUserIds, $validated, $assignedUserIds, $isLoss) {
            $oldStatusId = $lead->lead_status_id;
            $oldStatusName = $lead->status?->name ?? 'Unknown';

            if (!Schema::hasColumn('leads', 'loss_reason')) {
                unset($validated['loss_reason']);
            }

            $dirtyFields = array_keys($lead->getChanges());
            $lead->update($validated);
            $newlyAssignedUserIds = $this->syncAssignedUsers($lead, $assignedUserIds);

            $changes = [];
            foreach ($lead->getChanges() as $attr => $newVal) {
                if (in_array($attr, ['updated_at', 'deleted_at', 'loss_reason'], true)) {
                    continue;
                }
                if ($attr === 'lead_status_id') {
                    continue; // Logged separately below
                }
                $oldVal = $previous[$attr] ?? null;
                $humanAttr = ucwords(str_replace('_', ' ', $attr));
                if (is_array($oldVal) || is_array($newVal)) {
                    $changes[] = "{$humanAttr} updated";
                } elseif ($oldVal === null || $oldVal === '') {
                    $changes[] = "{$humanAttr} set to '{$newVal}'";
                } elseif ($newVal === null || $newVal === '') {
                    $changes[] = "{$humanAttr} cleared";
                } else {
                    $changes[] = "{$humanAttr} changed from '{$oldVal}' to '{$newVal}'";
                }
            }

            if ($changes !== []) {
                try {
                    LeadActivity::create([
                        'lead_id' => $lead->id,
                        'user_id' => auth()->id(),
                        'type' => 'status_change',
                        'description' => 'Updated lead details: ' . implode(', ', array_slice($changes, 0, 5)) . (count($changes) > 5 ? ' and more' : ''),
                        'occurred_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            if (isset($validated['lead_status_id']) && (int) $oldStatusId !== (int) $validated['lead_status_id']) {
                $lead->refresh()->load('status');
                $newStatusName = $lead->status?->name ?? 'Unknown';
                $description = "Status changed from {$oldStatusName} to {$newStatusName}";
                if ($isLoss && !empty($validated['loss_reason'])) {
                    $description .= " (Reason: {$validated['loss_reason']})";
                }
                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'type' => 'status_change',
                    'description' => $description,
                ]);
            }
        });

        $additionalAssigneeIds = array_diff($newlyAssignedUserIds, [(int) $lead->assigned_to]);

        if ($additionalAssigneeIds !== []) {
            app(\App\Services\DashboardNotificationService::class)->notifyLeadAssignedToUsers($lead, $additionalAssigneeIds);
        }

        $relationSync->syncLeadRecord($lead->fresh(), $previous);

        return redirect()
            ->route('leads.show', $lead)
            ->with('success', 'Lead updated successfully.');
    }

    public function updateAssignedUsers(Request $request, Lead $lead)
    {
        $companyId = (int) $lead->company_id;

        $data = $request->validate([
            'assigned_to' => 'nullable|array',
            'assigned_to.*' => [
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)
                        ->where('role', '!=', 'client');
                }),
            ],
        ]);

        $assignedUserIds = collect($data['assigned_to'] ?? [])
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $newlyAssignedUserIds = [];

        DB::transaction(function () use ($lead, &$newlyAssignedUserIds, $assignedUserIds) {
            $newlyAssignedUserIds = $this->syncAssignedUsers($lead, $assignedUserIds);
            $lead->update(['assigned_to' => $assignedUserIds[0] ?? null]);
        });

        $additionalAssigneeIds = array_diff($newlyAssignedUserIds, [(int) $lead->assigned_to]);

        if ($additionalAssigneeIds !== []) {
            app(\App\Services\DashboardNotificationService::class)->notifyLeadAssignedToUsers($lead, $additionalAssigneeIds);
        }

        $lead->load('assignedUsers:id,name');
        $names = $lead->assignedUsers->pluck('name')->values();

        return response()->json([
            'success' => true,
            'message' => 'Assigned staff updated successfully.',
            'assigned_user_ids' => $lead->assignedUsers->pluck('id')->values(),
            'assigned_label' => $names->isNotEmpty() ? $names->join(', ') : '-',
        ]);
    }

    public function getStaffLeads(User $user)
    {
        $authUser = auth()->user();
        $isCompanyAdmin = $authUser && ($authUser->isSuperAdmin() || $authUser->isCompanyAdmin());

        if (! $isCompanyAdmin) {
            abort(403, 'Unauthorized.');
        }

        $companyId = $this->companyId();
        if ((int) $user->company_id !== (int) $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'User does not belong to your company.',
            ], 403);
        }

        $leads = Lead::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($user) {
                $q->where('assigned_to', $user->id)
                  ->orWhereHas('assignedUsers', function ($uq) use ($user) {
                      $uq->where('users.id', $user->id);
                  });
            })
            ->with(['status:id,name,color'])
            ->latest('created_at')
            ->get(['id', 'uuid', 'client_name', 'mobile', 'email', 'estimated_value', 'lead_status_id', 'created_at']);

        return response()->json([
            'success' => true,
            'leads' => $leads->map(function ($lead) {
                return [
                    'id' => $lead->id,
                    'uuid' => $lead->uuid,
                    'client_name' => $lead->client_name,
                    'mobile' => $lead->mobile,
                    'email' => $lead->email,
                    'estimated_value' => $lead->estimated_value !== null ? number_format((float) $lead->estimated_value, 2) : '0.00',
                    'status_name' => $lead->status?->name ?? 'New',
                    'status_color' => $lead->status?->color ?? '#64748b',
                    'created_at' => $lead->created_at?->format('d M Y'),
                ];
            }),
        ]);
    }

    public function transferLeads(Request $request)
    {
        $authUser = auth()->user();
        $isCompanyAdmin = $authUser && ($authUser->isSuperAdmin() || $authUser->isCompanyAdmin());

        if (! $isCompanyAdmin) {
            abort(403, 'Unauthorized. Only company admins can transfer leads.');
        }

        $companyId = $this->companyId();
        if (! $companyId) {
            return response()->json([
                'success' => false,
                'message' => 'Company not found.',
            ], 422);
        }

        $validated = $request->validate([
            'from_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)
                        ->where('role', '!=', 'client');
                }),
            ],
            'to_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($companyId) {
                    $query->where('company_id', $companyId)
                        ->where('role', '!=', 'client');
                }),
            ],
            'transfer_all' => ['nullable', 'boolean'],
            'lead_ids' => ['nullable', 'array'],
            'lead_ids.*' => ['string'],
        ]);

        $fromUserId = (int) $validated['from_user_id'];
        $toUserId = (int) $validated['to_user_id'];

        if ($fromUserId === $toUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Source ("From") and target ("To") staff cannot be the same user.',
            ], 422);
        }

        $fromUser = User::find($fromUserId);
        $toUser = User::find($toUserId);
        if (! $toUser || ! $fromUser) {
            return response()->json(['success' => false, 'message' => 'Selected staff not found.'], 422);
        }

        $transferAll = filter_var($validated['transfer_all'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $leadsToTransferQuery = Lead::query()
            ->where('company_id', $companyId)
            ->where(function ($q) use ($fromUserId) {
                $q->where('assigned_to', $fromUserId)
                  ->orWhereHas('assignedUsers', function ($uq) use ($fromUserId) {
                      $uq->where('users.id', $fromUserId);
                  });
            });

        if (! $transferAll) {
            $leadUuids = collect($validated['lead_ids'] ?? [])->filter()->unique()->values()->all();
            if (empty($leadUuids)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please select at least one lead to transfer.',
                ], 422);
            }

            $leadsToTransferQuery->whereIn('uuid', $leadUuids);
        }

        $leads = $leadsToTransferQuery->with(['assignedUsers:id,name', 'assignedTo:id,name'])->get();

        if ($leads->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No matching leads found for the selected staff.',
            ], 422);
        }

        $transferredCount = 0;
        $notificationService = app(\App\Services\DashboardNotificationService::class);

        DB::transaction(function () use ($leads, $toUserId, $toUser, $authUser, &$transferredCount, $notificationService, $validated) {
            foreach ($leads as $lead) {
                $previousAssigneeName = $lead->assignedTo?->name ?: ($lead->assignedUsers->pluck('name')->first() ?: 'Unassigned');

                // Update lead assigned_to
                $lead->update(['assigned_to' => $toUserId]);

                // Sync assignments to target user
                $newlyAssigned = $this->syncAssignedUsers($lead, [$toUserId]);

                // Record lead activity log
                try {
                    LeadActivity::create([
                        'lead_id' => $lead->id,
                        'user_id' => $authUser->id,
                        'type' => 'status_change',
                        'description' => "Lead transferred from {$previousAssigneeName} to {$toUser->name} by {$authUser->name}",
                        'occurred_at' => now(),
                    ]);
                } catch (\Throwable $t) {
                    // Ignore activity logging errors if any
                }

                // Notify target staff
                try {
                    $notificationService->notifyLeadAssignedToUsers($lead, [$toUserId]);
                } catch (\Throwable $t) {
                    report($t);
                }

                $transferredCount++;
            }
        });

        $message = "Successfully transferred {$transferredCount} lead(s) to {$toUser->name}.";

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'transferred_count' => $transferredCount,
            ]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return redirect()
            ->route('leads.index')
            ->with('success', 'Lead deleted successfully.')
            ->with('lead_deleted_success', 'Lead deleted successfully.');
    }

    public function show(Lead $lead)
    {
        $lead->load([
            'status:id,name,color',
            'category:id,name',
            'assignedTo:id,name',
            'assignedUsers:id,name',
            'creator:id,name',
            'project:id,uuid,lead_id',
            'activities' => function ($q) {
                $q->select(['id', 'lead_id', 'user_id', 'type', 'description', 'occurred_at'])
                    ->with('user:id,name')
                    ->latest('occurred_at');
            },
            'surveys' => function ($q) {
                $q->select(['id', 'lead_id', 'photo_path', 'description', 'created_at'])
                    ->latest('created_at');
            },
            'quotations' => function ($q) {
                $q->select(['id', 'lead_id', 'file_path', 'source', 'amount', 'version', 'status', 'notes', 'form_schema', 'form_data', 'created_at'])
                    ->latest('created_at');
            },
            'materials' => function ($q) {
                $q->select(['id', 'lead_id', 'product_id', 'material_name', 'qty', 'unit', 'rate', 'gst', 'note', 'total', 'created_at'])
                    ->latest('created_at');
            },
            'comments' => function ($q) {
                $q->select(['id', 'lead_id', 'user_id', 'comment', 'created_at'])
                    ->with('user:id,name')
                    ->latest('created_at');
            },
            'followupHistories' => function ($q) {
                $q->select(['id', 'lead_id', 'summary', 'status', 'followup_date', 'next_followup_date', 'created_by', 'created_at'])
                    ->with('creator:id,name')
                    ->latest('created_at');
            },
            'followupSchedules' => function ($q) {
                $q->select(['id', 'lead_id', 'followup_date', 'status', 'completed_at', 'created_by', 'created_at'])
                    ->latest('followup_date');
            },
            'documents' => function ($q) {
                $q->select(['id', 'lead_id', 'name', 'file_path', 'created_at'])
                    ->latest('created_at');
            },
            'company',
        ]);
        LeadStatus::ensureWon((int) $lead->company_id);
        $statuses = LeadStatus::query()->orderBy('order')->get();

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';

        foreach ($lead->surveys as $survey) {
            $survey->display_url = Storage::disk($disk)->url($survey->photo_path);
        }

        foreach ($lead->quotations as $quotation) {
            if (!empty($quotation->file_path) && strtolower((string) $quotation->source) === 'upload') {
                $quotation->display_url = Storage::disk($disk)->url($quotation->file_path);
            }
        }

        $companyForm = OgForm::query()
            ->where('form_key', 'quotation')
            ->where('status', 'active')
            ->where('company_id', (int) $lead->company_id)
            ->latest()
            ->first();

        $defaultForm = OgForm::query()
            ->where('form_key', 'quotation')
            ->where('status', 'active')
            ->whereNull('company_id')
            ->latest()
            ->first();

        $quotationOgForm = $companyForm ?: $defaultForm;
        $units = CompanyUnit::query()
            ->where('company_id', (int) $lead->company_id)
            ->orderBy('name')
            ->get();

        $boqProducts = BoqProduct::query()
            ->where('company_id', (int) $lead->company_id)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'brand', 'model', 'image_path', 'unit', 'price', 'gst', 'description']);

        $boqCategories = BoqCategory::query()
            ->where('company_id', (int) $lead->company_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $gstRates = CompanyGstRate::query()
            ->where('company_id', (int) $lead->company_id)
            ->orderBy('rate')
            ->get(['rate']);

        return view('leads.show', compact('lead', 'statuses', 'quotationOgForm', 'units', 'boqProducts', 'boqCategories', 'gstRates'));
    }

    public function storeMaterial(Request $request, Lead $lead)
    {
        $payload = $request->validate([
            'materials' => 'nullable|array|min:1',
            'materials.*.product_id' => [
                'nullable',
                'integer',
                Rule::exists('boq_products', 'id')->where('company_id', $lead->company_id),
            ],
            'materials.*.material_name' => 'required|string|max:191',
            'materials.*.qty' => 'required|numeric|min:0.01',
            'materials.*.unit' => 'nullable|string|max:50',
            'materials.*.rate' => 'required|numeric|min:0',
            'materials.*.gst' => 'nullable|numeric|min:0|max:100',
            'materials.*.note' => 'nullable|string|max:2000',
            'material_name' => 'nullable|string|max:191',
            'qty' => 'nullable|numeric|min:0.01',
            'unit' => 'nullable|string|max:50',
            'rate' => 'nullable|numeric|min:0',
            'gst' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string|max:2000',
        ]);

        $rows = collect($payload['materials'] ?? []);

        if ($rows->isEmpty() && filled($payload['material_name'] ?? null)) {
            $rows = collect([[
                'material_name' => $payload['material_name'] ?? '',
                'qty' => $payload['qty'] ?? 0,
                'unit' => $payload['unit'] ?? null,
                'rate' => $payload['rate'] ?? 0,
                'gst' => $payload['gst'] ?? 0,
                'note' => $payload['note'] ?? null,
            ]]);
        }

        abort_if($rows->isEmpty(), 422, 'Please add at least one material.');

        foreach ($rows as $row) {
            $total = round(((float) ($row['qty'] ?? 0)) * ((float) ($row['rate'] ?? 0)), 2);

            $lead->materials()->create([
                'product_id' => $row['product_id'] ?? null,
                'material_name' => $row['material_name'],
                'qty' => $row['qty'],
                'unit' => $row['unit'] ?? null,
                'rate' => $row['rate'],
                'gst' => $row['gst'] ?? 0,
                'note' => $row['note'] ?? null,
                'total' => $total,
            ]);
        }

        return redirect()
            ->route('leads.show', ['lead' => $lead, 'tab' => 'material'])
            ->with('success', 'Material item added.');
    }

    public function storeMaterialProduct(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'category_id' => [
                'required',
                'integer',
                Rule::exists('boq_categories', 'id')->where('company_id', $lead->company_id),
            ],
            'name' => 'required|string|max:191',
            'brand' => 'nullable|string|max:191',
            'model' => 'nullable|string|max:191',
            'unit' => [
                'required',
                'string',
                'max:50',
                Rule::exists('company_units', 'name')->where('company_id', $lead->company_id),
            ],
            'price' => 'required|numeric|min:0',
            'gst' => [
                'required',
                'numeric',
                'min:0',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($lead): void {
                    if ((float) $value === 0.0) {
                        return;
                    }

                    $exists = CompanyGstRate::query()
                        ->where('company_id', $lead->company_id)
                        ->where('rate', $value)
                        ->exists();

                    if (! $exists) {
                        $fail('The selected GST rate is invalid.');
                    }
                },
            ],
            'description' => 'nullable|string|max:1000',
            'product_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'qty' => 'nullable|numeric|min:0.01',
            'note' => 'nullable|string|max:2000',
        ]);

        app(SubscriptionLimitService::class)->ensureWithinLimit((int) $lead->company_id, 'products', 1, 'name');

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $imagePath = $request->hasFile('product_image')
            ? app(MediaUploadService::class)->store(
                $request->file('product_image'),
                'company_'.$lead->company_id.'/products',
                $disk
            )
            : null;

        DB::transaction(function () use ($lead, $data, $imagePath) {
            $product = BoqProduct::create([
                'company_id' => $lead->company_id,
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'brand' => $data['brand'] ?? null,
                'model' => $data['model'] ?? null,
                'image_path' => $imagePath,
                'unit' => $data['unit'],
                'price' => $data['price'],
                'gst' => round((float) $data['gst'], 2),
                'description' => $data['description'] ?? null,
                'is_active' => true,
            ]);

            $qty = (float) ($data['qty'] ?? 1);
            $total = round(((float) $qty) * ((float) $data['price']), 2);

            $lead->materials()->create([
                'product_id' => $product->id,
                'material_name' => $product->name,
                'qty' => $qty,
                'unit' => $product->unit,
                'rate' => $product->price,
                'gst' => $product->gst,
                'note' => $data['note'] ?? ($product->description ?: null),
                'total' => $total,
            ]);
        });

        return redirect()
            ->route('leads.show', [$lead, 'tab' => 'material'])
            ->with('success', 'Product added and saved to this lead material list.');
    }

    public function updateMaterial(Request $request, Lead $lead, LeadMaterial $material)
    {
        abort_unless((int) $material->lead_id === (int) $lead->id, 404);

        $data = $request->validate([
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('boq_products', 'id')->where('company_id', $lead->company_id),
            ],
            'material_name' => 'required|string|max:191',
            'qty' => 'required|numeric|min:0.01',
            'unit' => 'nullable|string|max:50',
            'rate' => 'required|numeric|min:0',
            'gst' => 'nullable|numeric|min:0|max:100',
            'note' => 'nullable|string|max:2000',
        ]);
        $total = round(((float) $data['qty']) * ((float) $data['rate']), 2);

        $material->update([
            'product_id' => array_key_exists('product_id', $data) ? $data['product_id'] : $material->product_id,
            'material_name' => $data['material_name'],
            'qty' => $data['qty'],
            'unit' => $data['unit'] ?? null,
            'rate' => $data['rate'],
            'gst' => $data['gst'] ?? 0,
            'note' => $data['note'] ?? null,
            'total' => $total,
        ]);

        return redirect()
            ->route('leads.show', ['lead' => $lead, 'tab' => 'material'])
            ->with('success', 'Material item updated.');
    }

    public function destroyMaterial(Lead $lead, LeadMaterial $material)
    {
        abort_unless((int) $material->lead_id === (int) $lead->id, 404);
        $material->delete();

        return back()->with('success', 'Material item deleted.');
    }

    public function updateStatus(Request $request, Lead $lead)
    {
        $request->validate([
            'lead_status_id' => 'required|exists:lead_statuses,id',
            'project_name' => 'nullable|string|max:191',
            'project_description' => 'nullable|string|max:1000',
            'project_start_date' => 'nullable|date|after_or_equal:today',
            'project_end_date' => 'nullable|date|after_or_equal:today|after_or_equal:project_start_date',
            'project_status' => 'nullable|in:pending,in_progress,completed,cancelled,on_track,at_risk,delayed',
            'loss_reason' => 'nullable|string|max:1000',
        ]);

        $targetStatus = LeadStatus::query()->findOrFail($request->integer('lead_status_id'));
        if ($lead->company_id) {
            LeadStatus::ensureWon((int) $lead->company_id);
            LeadStatus::ensureLoss((int) $lead->company_id);
        }
        $isMovingToWon = strtolower((string) $targetStatus->name) === 'won';
        $isMovingToLoss = in_array(strtolower(trim((string) $targetStatus->name)), ['loss', 'lost'], true);

        $lead->loadMissing('status', 'project');

        if ($isMovingToWon && $lead->project) {
            $message = 'This lead is already converted to a project.';

            if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if ($isMovingToWon && !$lead->project && !$request->filled('project_name')) {
            throw ValidationException::withMessages([
                'project_name' => 'Project name is required when moving status to Won.',
            ]);
        }

        if ($isMovingToLoss && empty(trim((string) $request->input('loss_reason')))) {
            throw ValidationException::withMessages([
                'loss_reason' => 'Loss reason is required when moving status to Loss.',
            ]);
        }

        DB::transaction(function () use ($lead, $request, $isMovingToLoss) {
            $oldStatus = $lead->status?->name ?? 'Unknown';
            $updateData = ['lead_status_id' => $request->lead_status_id];

            if (Schema::hasColumn('leads', 'loss_reason')) {
                $updateData['loss_reason'] = $isMovingToLoss ? trim((string) $request->input('loss_reason')) : null;
            }

            $lead->update($updateData);
            $lead->refresh()->load('status');
            $newStatus = $lead->status?->name ?? 'Unknown';

            $description = "Status changed from {$oldStatus} to {$newStatus}";
            if ($isMovingToLoss && $request->filled('loss_reason')) {
                $description .= " (Reason: " . trim((string) $request->input('loss_reason')) . ")";
            }

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'status_change',
                'description' => $description,
            ]);

            // If status is "Won", convert to project
            if (strtolower($newStatus) === 'won') {
                $this->convertToProject($lead, [
                    'name' => $request->filled('project_name') ? $request->string('project_name')->toString() : null,
                    'description' => $request->filled('project_description') ? $request->string('project_description')->toString() : null,
                    'start_date' => $request->input('project_start_date'),
                    'end_date' => $request->input('project_end_date'),
                    'status' => $request->input('project_status', 'pending'),
                ]);
            }
        });

        if ($request->expectsJson() || $request->isJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Lead status updated successfully.',
                'lead' => [
                    'id' => $lead->id,
                    'uuid' => $lead->uuid,
                    'lead_status_id' => $lead->lead_status_id,
                    'status' => $lead->status?->name,
                    'loss_reason' => $lead->loss_reason ?? null,
                ]
            ]);
        }

        return back()->with('success', 'Lead status updated successfully.');
    }

    public function decideQuotation(Request $request, Lead $lead, LeadQuotation $quotation)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id) {
            abort(404);
        }

        $isBoqQuotation = strtolower((string) $quotation->source) === 'boq';
        $lead->loadMissing('project');

        if ($isBoqQuotation && $lead->project) {
            $message = 'This lead is already converted to a project. BOQ approval and rejection are disabled.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $request->validate([
            'decision' => 'required|in:accepted,rejected',
            'project_name' => 'required_if:decision,accepted|string|max:191',
            'project_description' => 'nullable|string|max:1000',
            'project_start_date' => 'nullable|date|after_or_equal:today',
            'project_end_date' => 'nullable|date|after_or_equal:today|after_or_equal:project_start_date',
            'project_status' => 'nullable|in:pending,in_progress,completed,cancelled,on_track,at_risk,delayed',
        ]);

        $decision = $request->string('decision')->toString();

        if ($decision === 'accepted') {
            $lead->loadMissing('project');

            if ($lead->project) {
                $message = 'This lead is already converted to a project.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }

                return back()->with('error', $message);
            }
        }

        DB::transaction(function () use ($decision, $quotation, $lead, $request) {
            $quotation->update([
                'status' => $decision,
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'quotation',
                'description' => "Quotation version {$quotation->version} was {$decision}",
            ]);

            if ($decision === 'accepted') {
                $lead->loadMissing('status');
                $oldStatus = $lead->status?->name ?? 'Unknown';

                $wonStatus = LeadStatus::query()
                    ->whereRaw('LOWER(name) = ?', ['won'])
                    ->first();
                $wonStatus = $wonStatus ?: LeadStatus::ensureWon((int) $lead->company_id);

                $lead->update(['lead_status_id' => $wonStatus->id]);
                $lead->refresh()->load('status');
                $newStatus = $lead->status?->name ?? 'Won';

                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => auth()->id(),
                    'type' => 'status_change',
                    'description' => "Status changed from {$oldStatus} to {$newStatus}",
                ]);

                $this->convertToProject($lead, [
                    'name' => $request->string('project_name')->toString(),
                    'description' => $request->filled('project_description') ? $request->string('project_description')->toString() : null,
                    'start_date' => $request->input('project_start_date'),
                    'end_date' => $request->input('project_end_date'),
                    'status' => $request->input('project_status', 'pending'),
                ]);
            }
        });

        return back()->with('success', "Quotation {$decision}.");
    }

    private function convertToProject(Lead $lead, array $projectData = []): ?Project
    {
        return app(LeadProjectConversionService::class)->convertLeadToProject($lead, $projectData);
    }

    public function convertToCompany(Request $request, Lead $lead)
    {
        $authUser = auth()->user();
        abort_unless($authUser && ($authUser->isSuperAdmin() || $authUser->isCompanyAdmin()), 403, 'Unauthorized action.');

        if ((int) $lead->company_id !== (int) $this->companyId() && ! $authUser->isSuperAdmin()) {
            abort(403);
        }

        $lead->loadMissing('createdCompany');
        if ($lead->createdCompany) {
            return back()->withErrors([
                'lead' => 'A company has already been created from this lead: ' . $lead->createdCompany->name,
            ])->withInput();
        }

        $validated = $request->validate([
            'company_name' => ['required', 'string', 'min:2', 'max:191'],
            'email' => ['required', 'string', 'email', 'max:191'],
            'phone_number' => ['required', 'string', 'regex:/^[0-9]{10}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'address' => ['nullable', 'string', 'max:500'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'admin_name' => ['nullable', 'string', 'max:191'],
        ], [
            'email.required' => 'The company admin email field is required.',
            'phone_number.required' => 'A 10-digit mobile number is required.',
            'phone_number.regex' => 'Please enter a valid 10-digit mobile number.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Password confirmation does not match.',
        ]);

        try {
            $createdCompany = app(LeadCompanyConversionService::class)->convertLeadToCompany($lead, array_filter($validated, fn ($val) => ! is_null($val)));
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $errorMessage = $e->getMessage();
            if (isset($errors['email'][0])) {
                $errorMessage = $errors['email'][0];
            } elseif (isset($errors['phone_number'][0])) {
                $errorMessage = $errors['phone_number'][0];
            } elseif (! empty($errors)) {
                $firstField = array_key_first($errors);
                $errorMessage = $errors[$firstField][0] ?? $errorMessage;
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => $errorMessage,
                    'errors' => $errors,
                ], 422);
            }

            return back()->withErrors($errors)->withInput()->with('conversion_error', $errorMessage);
        }

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => "Company '{$createdCompany->name}' created successfully from lead. Its onboarding process is ready to begin upon sign-in.",
                'company' => [
                    'id' => $createdCompany->id,
                    'name' => $createdCompany->name,
                ],
            ]);
        }

        return back()->with('success', "Company '{$createdCompany->name}' created successfully from lead. Its onboarding process is ready to begin upon sign-in.");
    }

    public function addComment(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'comment' => 'required|string|min:5|max:500',
        ]);

        $comment = $lead->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $data['comment'],
        ]);

        $comment->load('user:id,name');

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'note',
            'description' => 'Added a comment: "' . $data['comment'] . '"',
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Comment added successfully.',
                'comment' => [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at?->toIso8601String(),
                    'user_name' => $comment->user?->name ?? 'System',
                ],
            ]);
        }

        return back()->with('success', 'Comment added.');
    }

public function latestComments(Lead $lead)
{
    $comments = LeadComment::where('lead_id', $lead->id)
        ->latest()
        ->take(5)
        ->get([
            'id',
            'comment',
            'created_at'
        ]);
    return response()->json([
        'success' => true,
        'comments' => $comments
    ]);
}
    public function dashboardAction(Request $request, Lead $lead)
    {
        $request->merge([
            'remark' => trim((string) $request->input('remark')),
        ]);

        $data = $request->validate([
            'action_type' => 'required|in:reschedule,status_change',
            'remark' => 'required|string|max:2000',
        ]);

        $user = auth()->user();
        abort_unless($user, 403);

        return DB::transaction(function () use ($request, $lead, $data, $user) {
            if ($data['action_type'] === 'reschedule') {
                abort_unless($user->hasPermission('leads.comment'), 403);

                $payload = $request->validate([
                    'next_follow_up' => 'required|date|after_or_equal:today',
                ]);

                $lead->update([
                    'next_follow_up' => $payload['next_follow_up'],
                ]);

                $comment = $lead->comments()->create([
                    'user_id' => $user->id,
                    'comment' => $data['remark'],
                ]);

                $comment->load('user:id,name');

                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $user->id,
                    'type' => 'follow_up',
                    'description' => 'Rescheduled next follow-up to ' . \Illuminate\Support\Carbon::parse($payload['next_follow_up'])->toDateString(),
                ]);

                LeadActivity::create([
                    'lead_id' => $lead->id,
                    'user_id' => $user->id,
                    'type' => 'note',
                    'description' => 'Added a follow-up remark: "' . $data['remark'] . '"',
                ]);

                $lead->refresh();

                return response()->json([
                    'success' => true,
                    'message' => 'Lead follow-up rescheduled successfully.',
                    'lead' => [
                        'id' => $lead->id,
                        'uuid' => $lead->uuid,
                        'next_follow_up' => $lead->next_follow_up?->toDateString(),
                        'lead_status_id' => $lead->lead_status_id,
                        'status' => $lead->status?->name,
                    ],
                    'comment' => [
                        'id' => $comment->id,
                        'comment' => $comment->comment,
                        'created_at' => $comment->created_at?->toIso8601String(),
                        'user_name' => $comment->user?->name ?? 'System',
                    ],
                ]);
            }

            abort_unless($user->hasPermission('leads.status.update'), 403);

            $payload = $request->validate([
                'lead_status_id' => 'required|exists:lead_statuses,id',
            ]);

            $targetStatus = LeadStatus::query()->findOrFail($payload['lead_status_id']);
            abort_unless((int) $targetStatus->company_id === (int) $lead->company_id, 403);

            $lead->loadMissing('status');
            $oldStatus = $lead->status?->name ?? 'Unknown';
            $isTargetLoss = in_array(strtolower(trim((string) $targetStatus->name)), ['loss', 'lost'], true);
            $updatePayload = ['lead_status_id' => $targetStatus->id];
            if (Schema::hasColumn('leads', 'loss_reason')) {
                $updatePayload['loss_reason'] = $isTargetLoss ? ($request->input('loss_reason') ?: $data['remark']) : null;
            }
            $lead->update($updatePayload);
            $lead->refresh()->load('status');
            $newStatus = $lead->status?->name ?? 'Unknown';

            $description = "Status changed from {$oldStatus} to {$newStatus}";
            if ($isTargetLoss && !empty($updatePayload['loss_reason'])) {
                $description .= " (Reason: {$updatePayload['loss_reason']})";
            }

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'type' => 'status_change',
                'description' => $description,
            ]);

            $lead->comments()->create([
                'user_id' => $user->id,
                'comment' => $data['remark'],
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => $user->id,
                'type' => 'note',
                'description' => 'Added a status update remark: "' . $data['remark'] . '"',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Lead status updated successfully.',
                'lead' => [
                    'id' => $lead->id,
                    'uuid' => $lead->uuid,
                    'lead_status_id' => $lead->lead_status_id,
                    'status' => $newStatus,
                    'next_follow_up' => $lead->next_follow_up?->toDateString(),
                ],
            ]);
        });
    }

    public function rescheduleFollowUpWithRemark(Request $request, Lead $lead)
    {
        $request->merge([
            'remark' => trim((string) $request->input('remark')),
        ]);

        $data = $request->validate([
            'next_follow_up' => 'required|date|after_or_equal:today',
            'remark' => 'required|string|max:2000',
        ]);

        $lead->update([
            'next_follow_up' => $data['next_follow_up'],
        ]);

        $comment = $lead->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $data['remark'],
        ]);

        $comment->load('user:id,name');

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'follow_up',
            'description' => 'Rescheduled next follow-up to ' . \Illuminate\Support\Carbon::parse($data['next_follow_up'])->toDateString(),
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'note',
            'description' => 'Added a follow-up remark: "' . $data['remark'] . '"',
        ]);

        if ($request->expectsJson()) {
            $lead->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Lead follow-up rescheduled successfully.',
                'lead' => [
                    'id' => $lead->id,
                    'uuid' => $lead->uuid,
                    'next_follow_up' => $lead->next_follow_up?->toDateString(),
                ],
                'comment' => [
                    'id' => $comment->id,
                    'comment' => $comment->comment,
                    'created_at' => $comment->created_at?->toIso8601String(),
                    'user_name' => $comment->user?->name ?? 'System',
                ],
            ]);
        }

        return back()->with('success', 'Lead follow-up rescheduled successfully.');
    }

    public function logContact(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'type' => 'required|in:call,email,meeting,site_visit',
            'occurred_at' => 'required|date',
            'description' => 'nullable|string|max:2000',
            'assigned_staff_ids' => 'nullable|array',
            'assigned_staff_ids.*' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($lead) {
                    $query->where('company_id', $lead->company_id);
                }),
            ],
            'assigned_staff_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($lead) {
                    $query->where('company_id', $lead->company_id);
                }),
            ],
            'meeting_type' => 'nullable|in:online,offline',
            'meeting_location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|string|max:500',
        ]);
        $assignedStaffIds = $this->extractAssignedStaffIds($data);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => $data['type'],
            'description' => filled($data['description'] ?? null)
                ? $data['description']
                : ucfirst(str_replace('_', ' ', $data['type'])) . ' with ' . $lead->client_name,
            'occurred_at' => $data['occurred_at'],
        ]);

        if (($data['type'] ?? null) === 'meeting' && !empty($assignedStaffIds)) {
            $this->createSupportTicketForLeadAction($lead, $data, 'contact', $assignedStaffIds);

            $occurredAt = \Illuminate\Support\Carbon::parse($data['occurred_at']);
            foreach ($assignedStaffIds as $assignedStaffId) {
                CalendarEvent::create([
                    'company_id' => $lead->company_id,
                    'user_id' => $assignedStaffId,
                    'title' => ucfirst(str_replace('_', ' ', $data['type'])) . ': ' . $lead->client_name,
                    'location' => $data['meeting_type'] === 'offline' ? ($data['meeting_location'] ?? null) : null,
                    'description' => $this->buildLeadActionDescription($lead, $data, 'contact'),
                    'event_date' => $occurredAt->toDateString(),
                    'event_type' => $data['type'] === 'meeting' ? 'appointment' : 'task',
                    'starts_at' => $occurredAt,
                    'meeting_type' => $data['meeting_type'] ?? null,
                    'meeting_link' => $data['meeting_type'] === 'online' ? ($data['meeting_link'] ?? null) : null,
                ]);
            }
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Contact logged successfully.']);
        }

        return back()->with('success', 'Contact logged successfully.');
    }

    public function addLeadCalendarEvent(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'event_date' => 'required|date',
            'event_time' => 'nullable|date_format:H:i',
            'event_type' => 'nullable|in:task,appointment',
            'description' => 'nullable|string|max:2000',
            'assigned_staff_ids' => 'nullable|array',
            'assigned_staff_ids.*' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($lead) {
                    $query->where('company_id', $lead->company_id);
                }),
            ],
            'assigned_staff_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(function ($query) use ($lead) {
                    $query->where('company_id', $lead->company_id);
                }),
            ],
            'meeting_type' => 'nullable|in:online,offline',
            'meeting_location' => 'nullable|string|max:255',
            'meeting_link' => 'nullable|string|max:500',
        ]);
        $assignedStaffIds = $this->extractAssignedStaffIds($data);

        $startsAt = null;
        if (!empty($data['event_time'])) {
            $startsAt = \Illuminate\Support\Carbon::parse($data['event_date'] . ' ' . $data['event_time']);
        }

        $eventAssigneeIds = !empty($assignedStaffIds) ? $assignedStaffIds : [auth()->id()];
        foreach ($eventAssigneeIds as $assignedStaffId) {
            CalendarEvent::create([
                'company_id' => $lead->company_id,
                'user_id' => $assignedStaffId,
                'title' => $data['title'],
                'location' => $data['meeting_type'] === 'offline' ? ($data['meeting_location'] ?? null) : null,
                'description' => $this->buildLeadActionDescription($lead, $data, 'calendar'),
                'event_date' => $data['event_date'],
                'event_type' => $data['event_type'] ?? 'task',
                'starts_at' => $startsAt,
                'meeting_type' => $data['meeting_type'] ?? null,
                'meeting_link' => $data['meeting_type'] === 'online' ? ($data['meeting_link'] ?? null) : null,
            ]);
        }

        if (!empty($assignedStaffIds)) {
            $this->createSupportTicketForLeadAction($lead, $data, 'calendar', $assignedStaffIds);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Calendar event added.']);
        }

        return back()->with('success', 'Calendar event added.');
    }

    private function createSupportTicketForLeadAction(Lead $lead, array $data, string $source, array $assignedToIds = []): void
    {
        $assignedIds = !empty($assignedToIds)
            ? $assignedToIds
            : $this->extractAssignedStaffIds($data);

        if (empty($assignedIds)) {
            return;
        }

        $actionType = $source === 'contact'
            ? ($data['type'] ?? 'follow_up')
            : (($data['event_type'] ?? 'task') === 'appointment' ? 'meeting' : 'task');

        $ticketType = ($source === 'contact' && ($data['type'] ?? null) === 'meeting')
            ? 'meeting'
            : 'support';

        foreach ($assignedIds as $assignedTo) {
            $ticket = Ticket::create([
                'company_id' => $lead->company_id,
                'user_id' => auth()->id(),
                'assigned_to' => $assignedTo,
                'ticket_number' => 'TKT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'customer_contact_number' => $lead->mobile ?: null,
                'customer_name' => $lead->client_name ?: null,
                'customer_email' => $lead->email ?: null,
                'type' => $ticketType,
                'referenced_by' => null,
                'subject' => Str::limit('Lead ' . ucfirst(str_replace('_', ' ', (string) $actionType)) . ' - ' . ($lead->client_name ?: 'Client'), 191, ''),
                'priority' => 'medium',
                'status' => 'open',
                'description' => $this->buildLeadActionDescription($lead, $data, $source),
            ]);

            app(TicketAssignmentHistoryService::class)->syncCurrentAssignment(
                $ticket,
                (int) $assignedTo,
                auth()->user(),
                'Initial'
            );
        }
    }

    private function extractAssignedStaffIds(array $data): array
    {
        $staffIds = collect($data['assigned_staff_ids'] ?? [])
            ->when(isset($data['assigned_staff_id']) && filled($data['assigned_staff_id']), function ($collection) use ($data) {
                return $collection->push($data['assigned_staff_id']);
            })
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $staffIds;
    }

    private function buildLeadActionDescription(Lead $lead, array $data, string $source): string
    {
        $details = [];
        $details[] = 'Lead: ' . ($lead->client_name ?: 'N/A');
        $details[] = 'Source: ' . ($source === 'contact' ? 'Contact log' : 'Calendar schedule');

        if ($source === 'contact' && !empty($data['type'])) {
            $details[] = 'Action type: ' . ucfirst(str_replace('_', ' ', (string) $data['type']));
        }

        if (!empty($data['event_type'])) {
            $details[] = 'Event type: ' . ucfirst((string) $data['event_type']);
        }

        if (!empty($data['occurred_at'])) {
            $details[] = 'Date & time: ' . (string) $data['occurred_at'];
        } elseif (!empty($data['event_date'])) {
            $details[] = 'Date: ' . (string) $data['event_date'] . (!empty($data['event_time']) ? (' ' . (string) $data['event_time']) : '');
        }

        if (!empty($data['meeting_type'])) {
            $details[] = 'Meeting type: ' . ucfirst((string) $data['meeting_type']);
        }

        if (!empty($data['meeting_location'])) {
            $details[] = 'Location: ' . (string) $data['meeting_location'];
        }

        if (!empty($data['meeting_link'])) {
            $details[] = 'Meeting link: ' . (string) $data['meeting_link'];
        }

        $notes = trim((string) ($data['description'] ?? ''));
        if ($notes !== '') {
            $details[] = 'Notes: ' . $notes;
        }

        return implode("\n", $details);
    }

    public function uploadQuotation(Request $request, Lead $lead)
    {
        $media = app(MediaUploadService::class);
        $company = Company::query()->find($lead->company_id);
        $hasQuotationNumbering = $company?->usesQuotationNumbering() ?? false;

        $request->validate([
            'quotation_file' => 'required|file|mimes:pdf,doc,docx',
            'version' => 'nullable|string',
        ], [
            'quotation_file.mimes' => 'Quotation file must be a PDF, DOC, or DOCX.',
        ]);

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $companyFolderName = 'company_' . $lead->company_id;
        $path = $media->store($request->file('quotation_file'), $companyFolderName . '/quotations', $disk);
        // $path = $request->file('quotation_file')->store($companyFolderName . '/quotations', $disk);
        $version = $hasQuotationNumbering
            ? ($company?->reserveNextQuotationVersion()['version'] ?? (($lead->quotations()->count() + 1) . '.0'))
            : ($request->filled('version') ? $request->string('version')->toString() : (($lead->quotations()->count() + 1) . '.0'));

        $lead->quotations()->create([
            'file_path' => $path,
            'version' => $version,
            'status' => 'sent',
        ]);
        $proposalSentStatus = LeadStatus::ensureProposalSent((int) $lead->company_id);
        if ((int) $lead->lead_status_id !== (int) $proposalSentStatus->id) {
            $lead->update(['lead_status_id' => $proposalSentStatus->id]);
        }

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'quotation',
            'description' => "Uploaded quotation version {$version} for amount ₹{$request->amount}",
        ]);

        return back()->with('success', 'Quotation uploaded.');
    }

    // Form submissions are handled by App\Http\Controllers\QuotationFormSubmissionController.

    public function uploadSurvey(Request $request, Lead $lead)
    {
        $media = app(MediaUploadService::class);
        $request->validate([
            'survey_photo' => 'required|image|mimes:jpg,jpeg,png,webp,gif|max:10240',
            'description' => 'nullable|string',
        ], [

            'survey_photo.image' => 'Survey file must be an image.',
            'survey_photo.mimes' => 'Survey image must be JPG, JPEG, PNG, WEBP, or GIF.',
        ]);

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $companyFolderName = 'company_' . $lead->company_id;
        $path = $media->store($request->file('survey_photo'), $companyFolderName . '/surveys', $disk);

        $lead->surveys()->create([
            'photo_path' => $path,
            'description' => $request->description,
        ]);

        LeadActivity::create([
            'lead_id' => $lead->id,
            'user_id' => auth()->id(),
            'type' => 'survey',
            'description' => 'Uploaded a survey photo',
        ]);

        return back()->with('success', 'Survey photo uploaded.');
    }

    public function uploadDocument(Request $request, Lead $lead)
    {
        $media = app(MediaUploadService::class);
        $validator = Validator::make($request->all(), [
            'document_file' => ['required', 'file', 'max:20480', function ($attribute, $value, $fail) {
                $allowedExtensions = [
                    'jpg', 'jpeg', 'png', 'webp', 'gif',
                    'pdf', 'doc', 'docx',
                    'xls', 'xlsx', 'ppt', 'pptx',
                    'txt', 'csv', 'zip', 'rar',
                    'mp4', 'mov', 'avi', 'mkv', 'mp3', 'wav',
                ];

                $extension = strtolower((string) $value->getClientOriginalExtension());

                if (! in_array($extension, $allowedExtensions, true)) {
                    $fail('Unsupported file type. Please upload image, document, archive, audio, or video file.');
                }
            }],
            'document_name' => 'nullable|string|max:255',
        ], [
            'document_file.required' => 'Please select a file to upload.',
            'document_file.max' => 'Document size must not exceed 10 MB.',
            'document_name.max' => 'Document name must not exceed 255 characters.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $companyFolderName = 'company_' . $lead->company_id;
        $file = $request->file('document_file');

        try {
            $path = $media->store($file, $companyFolderName . '/documents', $disk);

            $displayName = trim((string) $request->input('document_name'));
            if ($displayName === '') {
                $displayName = $file->getClientOriginalName();
            }

            $displayName = mb_substr($displayName, 0, 255);

            $lead->documents()->create([
                'file_path' => $path,
                'name' => $displayName,
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'document',
                'description' => 'Uploaded a document',
            ]);
        } catch (\Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                'document_file' => 'The document file could not be uploaded. Please choose a valid file and try again.',
            ]);
        }

        return back()->with('success', 'Document uploaded successfully.');
    }

    public function printQuotation(Lead $lead)
    {
        $lead->load(['quotations', 'company']);
        return view('leads.quotation-print', compact('lead'));
    }

    public function downloadQuotation(Lead $lead, LeadQuotation $quotation)
    {
        if ((int) $quotation->lead_id !== (int) $lead->id) {
            abort(404);
        }

        if (strtolower((string) $quotation->source) !== 'upload' || empty($quotation->file_path)) {
            abort(404, 'Quotation file not available for this record.');
        }

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        $path = ltrim((string) $quotation->file_path, '/');

        if (!Storage::disk($disk)->exists($path)) {
            abort(404, 'Quotation file not found.');
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'file';
        $fileName = Str::slug($lead->client_name) . '-quotation-v' . preg_replace('/[^0-9A-Za-z\.\-_]/', '', (string) $quotation->version) . '.' . $extension;

        return Storage::disk($disk)->download($path, $fileName);
    }

    public function downloadLeadSummary(Request $request, Lead $lead)
    {
        $lead->loadMissing(['company', 'status', 'category', 'assignedTo', 'assignedUsers', 'creator', 'quotations', 'activities', 'comments', 'documents']);

        $acceptedQuotation = $lead->quotations
            ->sortByDesc('created_at')
            ->first(function ($quote) {
                return strtolower((string) $quote->status) === 'accepted';
            });

        $effectiveValue = $acceptedQuotation?->amount ?? $lead->estimated_value;

        $assignedToLabel = $lead->assignedUsers->isNotEmpty()
            ? $lead->assignedUsers->pluck('name')->join(', ')
            : ($lead->assignedTo?->name ?? 'Unassigned');

        $summaryLines = [
            'Lead Summary',
            '------------',
            'Client: ' . ($lead->client_name ?? 'N/A'),
            'Email: ' . ($lead->email ?? 'N/A'),
            'Mobile: ' . ($lead->mobile ?? 'N/A'),
            'GST Number: ' . ($lead->gst_number ?? 'N/A'),
            'PAN Number: ' . ($lead->pan_number ?? 'N/A'),
            'Address: ' . ($lead->address ?? 'N/A'),
            'Lead Source: ' . ($lead->source ?? 'N/A'),
            'BNI / Referral Person Name: ' . ($lead->referral_person_name ?? 'N/A'),
            'State: ' . ($lead->state?->name ?? 'N/A'),
            'City: ' . ($lead->city?->name ?? 'N/A'),
            'Status: ' . ($lead->status?->name ?? 'N/A'),
            'Category: ' . ($lead->category?->name ?? 'N/A'),
            'Assigned To: ' . $assignedToLabel,
            'Created By: ' . ($lead->creator?->name ?? 'N/A'),
            'Estimated Value: ₹' . number_format((float) $lead->estimated_value, 2),
            'Effective Value: ₹' . number_format((float) $effectiveValue, 2),
            'Accepted Quotation: ' . ($acceptedQuotation ? ('v' . $acceptedQuotation->version . ' (₹' . number_format((float) $acceptedQuotation->amount, 2) . ')') : 'No accepted quotation'),
            'Priority: ' . ucfirst((string) $lead->priority),
            'Created At: ' . optional($lead->created_at)->format('Y-m-d H:i:s'),
            'Updated At: ' . optional($lead->updated_at)->format('Y-m-d H:i:s'),
            'Total Quotations: ' . $lead->quotations->count(),
            'Total Comments: ' . $lead->comments->count(),
            'Total Documents: ' . $lead->documents->count(),
        ];

        $customFieldLines = collect($lead->company?->normalizedLeadCustomFields() ?? [])
            ->map(function (array $field) use ($lead) {
                $value = data_get($lead->custom_fields ?? [], $field['key'] ?? '');

                return $field['label'] . ': ' . $lead->formatCustomFieldValue($field, $value);
            })
            ->all();

        $summaryLines = array_merge(
            array_slice($summaryLines, 0, 10),
            $customFieldLines,
            array_slice($summaryLines, 10)
        );

        $format = strtolower((string) $request->query('format', 'doc'));
        if (!in_array($format, ['doc', 'txt'], true)) {
            $format = 'doc';
        }

        $summary = implode(PHP_EOL, $summaryLines);

        if ($format === 'txt') {
            $fileName = Str::slug($lead->client_name) . '-lead-summary.txt';

            return response()->streamDownload(function () use ($summary) {
                echo $summary;
            }, $fileName, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $docFileName = Str::slug($lead->client_name) . '-lead-summary.doc';

        $docHtml = view('leads.summary-doc', [
            'lead' => $lead,
            'acceptedQuotation' => $acceptedQuotation,
            'effectiveValue' => $effectiveValue,
        ])->render();

        return response()->streamDownload(function () use ($docHtml) {
            echo $docHtml;
        }, $docFileName, [
            'Content-Type' => 'application/msword; charset=UTF-8',
        ]);
    }

    private function syncAssignedUsers(Lead $lead, array $assignedUserIds): array
    {
        $existingUserIds = $lead->assignedUsers()->pluck('users.id')->map(fn ($id) => (int) $id)->all();

        $lead->assignedUsers()->sync($assignedUserIds);

        return array_values(array_diff($assignedUserIds, $existingUserIds));
    }

    private function filteredLeadsQuery(Request $request)
    {
        $authUser = auth()->user();
        $monthFilter = (int) $request->query('month', 0);
        $yearFilter = (int) $request->query('year', 0);

        if ($monthFilter < 1 || $monthFilter > 12) {
            $monthFilter = null;
        }

        if ($yearFilter < 2000 || $yearFilter > ((int) now()->year + 10)) {
            $yearFilter = null;
        }

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'category' => $request->query('category'),
            'priority' => $request->query('priority'),
            'assigned_to' => $request->query('assigned_to'),
            'source' => $this->normalizeTextFilter($request->query('source')),
            'state_id' => $request->query('state_id'),
            'city_id' => $request->query('city_id'),
            'follow_up_from' => $this->normalizeDateFilter($request->query('follow_up_from')),
            'follow_up_to' => $this->normalizeDateFilter($request->query('follow_up_to')),
            'followup_card' => in_array($request->query('followup_card'), ['total', 'today', 'done_today', 'pending_today', 'missed'], true)
                ? $request->query('followup_card')
                : null,
            'followup_status' => in_array(
                strtolower(trim((string) $request->query('followup_status'))),
                ['pending', 'done', 'not interested', 'converted', 'callback', 'follow-up required'],
                true
            ) ? trim((string) $request->query('followup_status')) : null,
            'month' => $monthFilter,
            'year' => $yearFilter,
        ];

        $query = Lead::query()
            ->accessibleTo($authUser)
            ->when($request->filled('status'), fn ($leadQuery) => $leadQuery->where('lead_status_id', $request->query('status')));

        return $this->applyLeadFilters($query, $filters);
    }

    private function applyLeadFilters($query, array $filters)
    {
        $search = trim((string) ($filters['search'] ?? ''));
        $categoryFilter = $filters['category'] ?? null;
        $priorityFilter = $filters['priority'] ?? null;
        $assignedToFilter = $filters['assigned_to'] ?? null;
        $sourceFilter = $filters['source'] ?? null;
        $stateFilter = $filters['state_id'] ?? null;
        $cityFilter = $filters['city_id'] ?? null;
        $followUpFromFilter = $filters['follow_up_from'] ?? null;
        $followUpToFilter = $filters['follow_up_to'] ?? null;
        $followupCardFilter = $filters['followup_card'] ?? null;
        $followupStatusFilter = $filters['followup_status'] ?? null;
        $monthFilter = $filters['month'] ?? null;
        $yearFilter = $filters['year'] ?? null;
        $customFieldFilters = $filters['custom_fields'] ?? [];
        $customFieldDefinitions = collect($filters['custom_field_definitions'] ?? [])
            ->keyBy(fn (array $field) => (string) ($field['key'] ?? ''));

        return $query
            ->when($search !== '', function ($leadQuery) use ($search) {
                $like = '%' . $search . '%';

                $leadQuery->where(function ($searchQuery) use ($like) {
                    $searchQuery->where('client_name', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('mobile', 'like', $like)
                        ->orWhereRaw('CAST(estimated_value AS CHAR) LIKE ?', [$like])
                        ->orWhereHas('category', function ($categoryQuery) use ($like) {
                            $categoryQuery->where('name', 'like', $like);
                        });
                });
            })
            ->when($categoryFilter, fn ($leadQuery) => $leadQuery->where('lead_category_id', $categoryFilter))
            ->when($priorityFilter, fn ($leadQuery) => $leadQuery->where('priority', $priorityFilter))
            ->when($assignedToFilter, fn ($leadQuery) => $leadQuery->where(function ($assignedQuery) use ($assignedToFilter) {
                $assignedQuery->where('assigned_to', $assignedToFilter)
                    ->orWhereHas('assignedUsers', function ($usersQuery) use ($assignedToFilter) {
                        $usersQuery->whereKey($assignedToFilter);
                    });
            }))
            ->when($sourceFilter, fn ($leadQuery) => $leadQuery->where('source', $sourceFilter))
            ->when($stateFilter, fn ($leadQuery) => $leadQuery->where('state_id', $stateFilter))
            ->when($cityFilter, fn ($leadQuery) => $leadQuery->where('city_id', $cityFilter))
            ->when($followUpFromFilter, fn ($leadQuery) => $leadQuery->whereDate('next_follow_up', '>=', $followUpFromFilter))
            ->when($followUpToFilter, fn ($leadQuery) => $leadQuery->whereDate('next_follow_up', '<=', $followUpToFilter))
            ->when($followupCardFilter, function ($leadQuery) use ($followupCardFilter) {
                $today = now()->toDateString();

                $leadQuery->whereHas('followupSchedules', function ($scheduleQuery) use ($followupCardFilter, $today) {
                    if ($followupCardFilter === 'today') {
                        $scheduleQuery->whereDate('followup_date', $today);
                    }

                    if ($followupCardFilter === 'done_today') {
                        $scheduleQuery->whereDate('followup_date', $today)
                            ->whereRaw('LOWER(status) = ?', ['done']);
                    }

                    if ($followupCardFilter === 'pending_today') {
                        $scheduleQuery->whereDate('followup_date', $today)
                            ->whereRaw('LOWER(status) = ?', ['pending']);
                    }

                    if ($followupCardFilter === 'missed') {
                        $scheduleQuery->whereDate('followup_date', '<', $today)
                            ->whereRaw('LOWER(status) = ?', ['pending']);

                        $scheduleQuery->whereHas('lead.status', function ($statusQuery) {
                            $statusQuery->whereRaw('LOWER(name) NOT IN (?, ?)', ['won', 'loss']);
                        });
                    }
                });
            })
            ->when($followupStatusFilter, function ($leadQuery) use ($followupStatusFilter) {
                $leadQuery->whereHas('latestFollowupHistory', function ($historyQuery) use ($followupStatusFilter) {
                    $historyQuery->whereRaw('LOWER(status) = ?', [strtolower(trim((string) $followupStatusFilter))]);
                });
            })
            ->when($monthFilter, fn ($leadQuery) => $leadQuery->whereMonth('created_at', $monthFilter))
            ->when($yearFilter, fn ($leadQuery) => $leadQuery->whereYear('created_at', $yearFilter))
            ->when(! empty($customFieldFilters), function ($leadQuery) use ($customFieldFilters, $customFieldDefinitions) {
                foreach ($customFieldFilters as $fieldKey => $fieldFilter) {
                    $this->applyCustomFieldFilter(
                        $leadQuery,
                        (string) $fieldKey,
                        $fieldFilter,
                        $customFieldDefinitions->get((string) $fieldKey)
                    );
                }
            });
    }

    private function normalizeDateFilter($value): ?string
    {
        $date = trim((string) $value);

        if ($date === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::createFromFormat('Y-m-d', $date)->format('Y-m-d');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function normalizeTextFilter($value): ?string
    {
        $text = Str::squish((string) $value);

        return $text !== '' ? $text : null;
    }

    private function normalizeLeadCustomFieldFilters(?Company $company, mixed $rawFilters): array
    {
        $fields = collect($company?->normalizedLeadCustomFields() ?? [])
            ->filter(fn (array $field) => (bool) ($field['show_in_filters'] ?? false))
            ->keyBy('key');

        if (! is_array($rawFilters)) {
            return [];
        }

        $normalized = [];

        foreach ($rawFilters as $key => $value) {
            $field = $fields->get((string) $key);
            if (! $field) {
                continue;
            }

            if (is_array($value)) {
                $value = reset($value);
            }

            if ($value === null) {
                continue;
            }

            $value = is_string($value) ? trim($value) : $value;
            if ($value === '' || $value === []) {
                continue;
            }

            $normalized[(string) $key] = $value;
        }

        return $normalized;
    }

    private function applyCustomFieldFilter($query, string $fieldKey, mixed $value, ?array $field = null): void
    {
        $fieldKey = trim($fieldKey);
        if ($fieldKey === '' || $value === null || $value === '') {
            return;
        }

        $fieldType = (string) ($field['type'] ?? 'text');
        $jsonPath = '$."' . str_replace('"', '', $fieldKey) . '"';

        if ($fieldType === 'checkbox') {
            $normalizedBool = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($normalizedBool === null) {
                return;
            }

            $query->whereRaw(
                'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(custom_fields, ?)), "") = ?',
                [$jsonPath, $normalizedBool ? 'true' : 'false']
            );

            return;
        }

        $textValue = trim((string) $value);

        if ($textValue === '') {
            return;
        }

        if ($fieldType === 'dropdown' || $fieldType === 'radiobutton' || $fieldType === 'number' || $fieldType === 'date') {
            $query->whereRaw(
                'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(custom_fields, ?)), "") = ?',
                [$jsonPath, $textValue]
            );

            return;
        }

        $query->whereRaw(
            'COALESCE(JSON_UNQUOTE(JSON_EXTRACT(custom_fields, ?)), "") LIKE ?',
            [$jsonPath, '%' . $textValue . '%']
        );
    }

    private function ensureLeadClientDoesNotAlreadyExistInCompany(
        int $companyId,
        ?string $clientName,
        ?string $email,
        ?string $mobile
    ): void {
        $normalizedName = Str::lower(trim((string) $clientName));
        $normalizedEmail = filled($email) ? Str::lower(trim((string) $email)) : null;
        $normalizedMobile = filled($mobile) ? trim((string) $mobile) : null;

        $matchingClient = Client::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($normalizedName, $normalizedEmail, $normalizedMobile) {
                if ($normalizedName !== '') {
                    $query->orWhereRaw('LOWER(TRIM(name)) = ?', [$normalizedName]);
                }

                if ($normalizedEmail) {
                    $query->orWhereRaw('LOWER(email) = ?', [$normalizedEmail]);
                }

                if ($normalizedMobile) {
                    $query->orWhere('mobile', $normalizedMobile)
                        ->orWhere('phone', $normalizedMobile);
                }
            })
            ->first();

        $errors = [];

        if ($normalizedEmail) {
            $company = Company::query()->find($companyId);
            if ($company && $company->isInternalStaffOrAdminEmail($normalizedEmail)) {
                $errors['email'] = 'This email belongs to a company administrator or staff member and cannot be assigned to a lead or client.';
            }
        }

        if ($matchingClient) {
            $message = 'Client already exists in this company. Please select the client from the existing client list instead of creating a new one.';

            if ($normalizedName !== '' && Str::lower(trim((string) $matchingClient->name)) === $normalizedName) {
                $errors['client_name'] = $message;
            }

            if ($normalizedEmail && Str::lower((string) $matchingClient->email) === $normalizedEmail) {
                $errors['email'] = $message;
            }

            if ($normalizedMobile && in_array($normalizedMobile, array_filter([$matchingClient->mobile, $matchingClient->phone]), true)) {
                $errors['mobile'] = 'On this contact/phone, client already exists. Please select the existing client instead of creating a new client or contact.';
            }
        }

        $matchingLead = null;

        if ($normalizedMobile) {
            $matchingLead = Lead::query()
                ->where('company_id', $companyId)
                ->where('mobile', $normalizedMobile)
                ->first();
        }

        if ($matchingLead) {
            $errors['mobile'] = 'On this contact/phone, contact already exists. Please use the existing contact/client instead of creating another one.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function syncLeadPayloadWithExistingClient(array $validated, Client $client): array
    {
        $validated['client_name'] = trim((string) $client->name);
        $validated['email'] = filled($client->email) ? strtolower(trim((string) $client->email)) : null;
        $validated['mobile'] = filled($client->mobile)
            ? trim((string) $client->mobile)
            : (filled($client->phone) ? trim((string) $client->phone) : null);
        $validated['gst_number'] = $client->gst_number;
        $validated['pan_number'] = $client->pan_number;
        $validated['state_id'] = $client->state_id;
        $validated['city_id'] = $client->city_id;
        $validated['address'] = $client->address;

        return $validated;
    }

    private function resolveExistingClientIdForLead(?int $companyId, Lead $lead): ?int
    {
        if ($companyId <= 0) {
            return null;
        }

        $normalizedName = Str::lower(trim((string) $lead->client_name));
        $normalizedEmail = filled($lead->email) ? Str::lower(trim((string) $lead->email)) : null;
        $normalizedMobile = filled($lead->mobile) ? preg_replace('/\D+/', '', (string) $lead->mobile) : null;
        $clients = Client::query()
            ->where('company_id', $companyId)
            ->get(['id', 'name', 'email', 'mobile', 'phone']);

        $matchedClient = $clients->first(function (Client $client) use ($normalizedEmail, $normalizedMobile, $normalizedName) {
            if ($normalizedEmail && Str::lower(trim((string) $client->email)) === $normalizedEmail) {
                return true;
            }

            if ($normalizedMobile) {
                $clientMobiles = array_filter([
                    preg_replace('/\D+/', '', (string) $client->mobile),
                    preg_replace('/\D+/', '', (string) $client->phone),
                ]);

                if (in_array($normalizedMobile, $clientMobiles, true)) {
                    return true;
                }
            }

            return $normalizedName !== '' && Str::lower(trim((string) $client->name)) === $normalizedName;
        });

        return $matchedClient?->id ? (int) $matchedClient->id : null;
    }

    private function leadAssignableUsers(?int $companyId)
    {
        return User::query()
            ->where('company_id', $companyId)
            ->activeAssigneeOrAdmin()
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    private function normalizeReferralPersonNameForSource(?Company $company, ?string $source, ?string $referralPersonName): ?string
    {
        if (! $company?->leadSourceShowsReferralPersonName($source)) {
            return null;
        }

        $value = Str::squish((string) $referralPersonName);

        return $value !== '' ? $value : null;
    }

    private function resolveCompanyLocationDefaults(?Company $company, Collection $states): array
    {
        $defaultStateId = null;
        $defaultCityId = null;

        $companyStateName = Str::lower(trim((string) $company?->state));
        $companyCityName = Str::lower(trim((string) $company?->city));

        if ($companyStateName === '') {
            return [
                'state_id' => null,
                'city_id' => null,
            ];
        }

        $matchedState = $states->first(function (State $state) use ($companyStateName) {
            return Str::lower(trim((string) $state->name)) === $companyStateName;
        });

        if (! $matchedState) {
            return [
                'state_id' => null,
                'city_id' => null,
            ];
        }

        $defaultStateId = $matchedState->id;

        if ($companyCityName !== '') {
            $matchedCity = $matchedState->cities->first(function ($city) use ($companyCityName) {
                return Str::lower(trim((string) $city->name)) === $companyCityName;
            });

            $defaultCityId = $matchedCity?->id;
        }

        return [
            'state_id' => $defaultStateId,
            'city_id' => $defaultCityId,
        ];
    }
}
