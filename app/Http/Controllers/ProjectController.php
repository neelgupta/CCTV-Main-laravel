<?php

namespace App\Http\Controllers;

use App\Exports\ProjectExport;
use App\Exports\ProjectImportSampleExport;
use App\Exceptions\StorageLimitExceededException;
use App\Http\Requests\StoreProjectRequest;
use App\Imports\ProjectImport;
use App\Models\Amc;
use App\Models\AmcPlan;
use App\Models\BoqProduct;
use App\Models\CompanyGstRate;
use App\Models\CompanyUnit;
use App\Models\Contractor;
use App\Models\Lead;
use App\Models\LeadQuotation;
use App\Models\Client;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectDrawing;
use App\Models\ProjectStage;
use App\Models\StaffTask;
use App\Models\Ticket;
use App\Services\TicketAssignmentHistoryService;
use App\Services\MediaUploadService;
use App\Models\ProjectChallan;
use App\Models\ProjectMaterial;
use App\Models\ProjectProduction;
use App\Models\User;
use App\Models\Company;
use App\Models\State;
use App\Services\AmcTechnicianAssignmentService;
use App\Services\ClientSyncService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use ZipArchive;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $authUser = auth()->user();
        $company = $authUser?->company;
        ProjectStage::ensureDefaults((int) auth()->user()->company_id);
        $search = trim((string) $request->string('search'));

        $projectsQuery = $this->filteredProjectsQuery($request, $company);

        $projects = $projectsQuery
            ->with([
                'lead:id,client_name',
                'manager:id,name,role,role_id',
                'manager.roleRecord:id,name,slug',
                'contractor:id,name',
                'state:id,name',
                'city:id,name',
                'drawings',
            ])
            ->withCount(['materials as productions_count' => fn ($query) => $query->whereNull('source_drawing_id')])
            ->withCount(['materials as materials_count' => fn ($query) => $query->whereNull('source_drawing_id')])
            ->withCount(['materials as productions_qa_done_count' => fn ($query) => $query->whereNull('source_drawing_id')->where('qa_done', true)])
            ->withCount(['amcs'])
            ->addSelect([
                'latest_challan_status' => ProjectChallan::query()
                    ->select('status')
                    ->whereColumn('project_id', 'projects.id')
                    ->latest()
                    ->limit(1),
            ])
            ->orderByRaw('CASE WHEN end_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('end_date')
            ->orderByDesc('created_at')
            ->get();

        $projectSummaryQuery = Project::query()->accessibleTo($authUser);
        $projectSummary = [
            'total' => $projectSummaryQuery->count(),
            'completed' => (clone $projectSummaryQuery)->where('status', 'completed')->count(),
            'delayed' => (clone $projectSummaryQuery)->where('status', 'delayed')->count(),
        ];

        $users = User::query()
            ->where('company_id', $authUser?->company_id)
            ->activeAssigneeOrAdmin()
            ->orderBy('name')
            ->get();

        if ($authUser && ! $authUser->isSuperAdmin() && ! $authUser->isCompanyAdmin()) {
            $users = collect([$authUser]);
        }

        $projectCustomFields = $company?->normalizedProjectCustomFields() ?? [];
        $projectKanbanCardFields = collect([
            ['key' => 'reference_no', 'label' => 'Reference No', 'default_selected' => true],
            ['key' => 'client_name', 'label' => 'Client', 'default_selected' => true],
            ['key' => 'manager', 'label' => 'Assigned Staff', 'default_selected' => true],
            ['key' => 'status', 'label' => 'Status', 'default_selected' => true],
            ['key' => 'completion_percentage', 'label' => 'Completion', 'default_selected' => true],
            ['key' => 'end_date', 'label' => 'Deadline', 'default_selected' => true],
            ['key' => 'lead', 'label' => 'Linked Lead', 'default_selected' => false],
            ['key' => 'contractor', 'label' => 'Contractor', 'default_selected' => false],
            ['key' => 'team_members', 'label' => 'Team Members', 'default_selected' => false],
            ['key' => 'start_date', 'label' => 'Start Date', 'default_selected' => false],
            ['key' => 'current_stage', 'label' => 'Stage', 'default_selected' => false],
            ['key' => 'state', 'label' => 'State', 'default_selected' => false],
            ['key' => 'city', 'label' => 'City', 'default_selected' => false],
            ['key' => 'commission_type', 'label' => 'Commission Type', 'default_selected' => false],
            ['key' => 'commission_value', 'label' => 'Commission Value', 'default_selected' => false],
            ['key' => 'created_at', 'label' => 'Created', 'default_selected' => false],
            ['key' => 'drawings_count', 'label' => 'Drawings', 'default_selected' => false],
            ['key' => 'materials_count', 'label' => 'Materials', 'default_selected' => false],
            ['key' => 'productions_count', 'label' => 'Productions', 'default_selected' => false],
            ['key' => 'amcs_count', 'label' => 'AMCs', 'default_selected' => false],
        ])->merge(
            collect($projectCustomFields)->map(function (array $field) {
                return [
                    'key' => 'custom:' . ($field['key'] ?? ''),
                    'label' => (string) ($field['label'] ?? 'Custom Field'),
                    'type' => (string) ($field['type'] ?? 'text'),
                    'default_selected' => false,
                ];
            })
        )->values()->all();
        $filterableProjectCustomFields = collect($projectCustomFields)
            ->filter(fn (array $field) => (bool) ($field['show_in_filters'] ?? false))
            ->values();
        $projectCustomFieldFilters = $this->normalizeProjectCustomFieldFilters($company, $request->input('custom_fields', []));
        $projectTeamMemberIds = $projects
            ->pluck('team_member_ids')
            ->flatten()
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $projectTeamMembersById = $projectTeamMemberIds->isNotEmpty()
            ? User::query()
                ->where('company_id', $authUser?->company_id)
                ->whereIn('id', $projectTeamMemberIds->all())
                ->with('roleRecord:id,name,slug')
                ->get(['id', 'name', 'role', 'role_id'])
                ->keyBy('id')
            : collect();

        $projectStages = $this->projectStages();
        $knownStageKeys = array_keys($projectStages);

        return view('projects.index', [
            'projects' => $projects,
            'projectsByStage' => $projects->groupBy(fn (Project $project) => $this->normalizeBoardStageKey($project->current_stage, $knownStageKeys)),
            'projectStages' => $projectStages,
            'statusOptions' => $this->statusOptions(),
            'users' => $users,
            'projectCustomFields' => $projectCustomFields,
            'projectKanbanCardFields' => $projectKanbanCardFields,
            'projectTeamMembersById' => $projectTeamMembersById,
            'filterableProjectCustomFields' => $filterableProjectCustomFields,
            'projectCustomFieldFilters' => $projectCustomFieldFilters,
            'clients' => Project::query()->whereNotNull('client_name')->distinct()->orderBy('client_name')->pluck('client_name'),
            'states' => State::query()
                ->with(['cities' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name']),
            'tab' => $request->filled('tab') ? $request->string('tab')->toString() : null,
            'projectSummary' => $projectSummary,
            'search' => $search,
            'completedStageAmcPlans' => AmcPlan::query()
                ->where('company_id', auth()->user()->company_id)
                ->where('is_active', true)
                ->where('duration_months', 12)
                ->orderBy('price')
                ->orderBy('visit_count')
                ->orderBy('name')
                ->get(['id', 'name', 'duration_months', 'visit_count', 'price', 'description', 'service_mode']),
        ]);
    }

    public function export(Request $request)
    {
        $company = auth()->user()?->company;

        $projects = $this->filteredProjectsQuery($request, $company)
            ->with(['lead.state:id,name', 'lead.city:id,name', 'manager:id,name', 'state:id,name', 'city:id,name'])
            ->orderByRaw('CASE WHEN end_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('end_date')
            ->orderByDesc('created_at')
            ->get();

        $clientNames = $projects->pluck('client_name')->filter()->map(fn ($name) => trim((string) $name))->unique()->values();
        $clientsByName = Client::query()
            ->where('company_id', auth()->user()->company_id)
            ->whereIn('name', $clientNames)
            ->with(['state:id,name', 'city:id,name'])
            ->get()
            ->keyBy(fn (Client $client) => mb_strtolower(trim((string) $client->name)));
        $usersById = User::query()
            ->where('company_id', auth()->user()->company_id)
            ->get(['id', 'name'])
            ->keyBy('id');

        return Excel::download(
            new ProjectExport($projects, $clientsByName, $usersById, $company),
            'projects-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function import(Request $request)
    {
        $request->validate([
            'project_import_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'project_import_file.required' => 'Please choose a project import file.',
            'project_import_file.mimes' => 'Project import file must be CSV, XLS, or XLSX.',
        ]);

        $company = Company::query()->findOrFail((int) auth()->user()->company_id);
        $states = State::query()
            ->with(['cities' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get(['id', 'name']);
        ['state_id' => $defaultStateId, 'city_id' => $defaultCityId] = $this->resolveCompanyLocationDefaults($company, $states);

        Excel::import(
            new ProjectImport(auth()->user(), $company, $defaultStateId, $defaultCityId),
            $request->file('project_import_file')
        );

        return redirect()
            ->route('projects.index')
            ->with('success', 'Projects imported successfully.');
    }

    public function downloadImportSample()
    {
        return Excel::download(
            new ProjectImportSampleExport(auth()->user()?->company),
            'project-import-sample.xlsx'
        );
    }

    public function create()
    {
        $companyId = auth()->user()->company_id;
        $company = auth()->user()?->company;
        ProjectStage::ensureDefaults((int) $companyId);
        return view('projects.create', [
            'projectStages' => $this->projectStages(),
            'statusOptions' => $this->statusOptions(),
            'users' => User::query()
                ->where('company_id', $companyId)
                ->activeAssigneeOrAdmin()
                ->with('roleRecord:id,name,slug')
                ->orderBy('name')
                ->get(),
            'leads' => Lead::query()->where('company_id', $companyId)->with('status')->orderByDesc('created_at')->get(),
            'clients' => Client::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'email', 'mobile', 'phone', 'address', 'state_id', 'city_id']),
            'states' => State::query()->with(['cities' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(['id', 'name']),
            'nextReferenceNo' => Project::nextReferenceNo($companyId),
            'contractors' => Contractor::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'projectCustomFields' => $company?->normalizedProjectCustomFields() ?? [],
        ]);
    }

    public function edit(Project $project)
    {
        $companyId = auth()->user()->company_id;
        $company = auth()->user()?->company;
        ProjectStage::ensureDefaults((int) $companyId);
        $selectedExistingClientId = $this->resolveExistingClientIdForProject($companyId, $project->client_name);
        return view('projects.edit', [
            'project' => $project,
            'projectStages' => $this->projectStages(),
            'statusOptions' => $this->statusOptions(),
            'users' => User::query()
                ->where('company_id', $companyId)
                ->activeAssigneeOrAdmin()
                ->with('roleRecord:id,name,slug')
                ->orderBy('name')
                ->get(),
            'leads' => Lead::query()->with('status')->orderByDesc('created_at')->get(),
            'clients' => Client::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name', 'email', 'mobile', 'phone', 'address', 'state_id', 'city_id']),
            'states' => State::query()->with(['cities' => fn ($query) => $query->orderBy('name')])->orderBy('name')->get(['id', 'name']),
            'contractors' => Contractor::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'projectCustomFields' => $company?->normalizedProjectCustomFields() ?? [],
            'selectedExistingClientId' => $selectedExistingClientId,
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $validated = $request->validated();
        $validated['current_stage'] = $this->resolvePersistedStageKey($validated['current_stage'] ?? null);
        $validated['reference_no'] = $validated['reference_no'] ?: Project::nextReferenceNo(auth()->user()->company_id);
        app(SubscriptionLimitService::class)->ensureWithinLimit((int) auth()->user()->company_id, 'projects', 1, 'name');
        $selectedExistingClientId = filled($validated['existing_client_id'] ?? null) ? (int) $validated['existing_client_id'] : null;
        $selectedLeadId = filled($validated['lead_id'] ?? null) ? (int) $validated['lead_id'] : null;

        DB::transaction(function () use ($validated, $request, $selectedExistingClientId, $selectedLeadId, &$project) {
            $project = Project::create($validated);

            // If the project was created from an existing client or linked lead,
            // keep that source record unchanged and only save project data.
            if (! $selectedExistingClientId && ! $selectedLeadId) {
                app(ClientSyncService::class)->upsert([
                    'company_id' => $project->company_id,
                    'name' => $project->client_name,
                    'email' => $request->input('client_email'),
                    'mobile' => $request->input('client_mobile'),
                    'state_id' => $validated['state_id'] ?? null,
                    'city_id' => $validated['city_id'] ?? null,
                    'address' => $request->input('client_address'),
                ]);
            }

            $this->logActivity($project, 'project', 'Project created.');
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function update(StoreProjectRequest $request, Project $project)
    {
        $validated = $request->validated();
        $validated['current_stage'] = $this->resolvePersistedStageKey($validated['current_stage'] ?? null);
        $validated['reference_no'] = $validated['reference_no'] ?: $project->reference_no ?: Project::nextReferenceNo(auth()->user()->company_id);

        DB::transaction(function () use ($project, $validated) {
            $project->update($validated);

            $this->logActivity($project, 'project', 'Project updated.');
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    public function show(Request $request, Project $project)
    {
        $project->load(['lead.quotations', 'manager', 'contractor', 'drawings', 'materials.product.category', 'productions', 'challans', 'activities.user', 'comments.user'])
            ->loadCount('amcs');
        $projectCustomFields = $project->company?->normalizedProjectCustomFields() ?? [];

        $acceptedDrawing = $project->drawings
            ->sortByDesc('accepted_at')
            ->first(fn (ProjectDrawing $drawing) => $drawing->accepted_at !== null);
        $pendingDrawingForAccept = $project->drawings
            ->sortByDesc('created_at')
            ->first(fn (ProjectDrawing $drawing) => $drawing->accepted_at === null);
        $nextDrawingVersion = $this->nextDrawingVersion($project);

        $activeTab = $request->string('tab')->toString()
            ?: (string) (session()->getOldInput('tab') ?: 'overview');
        $activeTab = match ($activeTab) {
            'production' => 'material',
            'delivery' => 'ready_to_dispatch',
            default => $activeTab,
        };
        $selectedChallanId = null;
        $requestedChallanKey = trim((string) $request->query('challan', ''));

        if ($requestedChallanKey !== '') {
            $decodedChallanKey = ProjectChallan::decodeRouteKeyValue($requestedChallanKey);

            if (is_int($decodedChallanKey) || ctype_digit((string) $decodedChallanKey)) {
                $candidateChallanId = (int) $decodedChallanKey;

                if ($project->challans->contains('id', $candidateChallanId)) {
                    $selectedChallanId = $candidateChallanId;
                }
            }
        }

        $requestedMaterialSource = trim((string) $request->string('load_material_from')->toString());
        [$linkedLeadMaterialRows, $linkedLeadMaterialSource] = $this->linkedLeadMaterialRows(
            $project,
            $requestedMaterialSource !== '' ? $requestedMaterialSource : null
        );
        $financialSummary = $this->financialSummary($project);
        $contractorCommissionSummary = $this->contractorCommissionSummary($project);
        $catalogProducts = BoqProduct::query()
            ->where('company_id', $project->company_id)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'description', 'unit', 'price', 'gst']);
        $materialsPaginator = $project->materials()
            ->whereNull('source_drawing_id')
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'material_page')
            ->appends([
                'tab' => 'material',
                'load_material_from' => $requestedMaterialSource !== '' ? $requestedMaterialSource : null,
            ]);
        $readyToDispatchDispatchedItems = $project->materials()
            ->whereNull('source_drawing_id')
            ->where('qa_done', true)
            ->whereNotNull('dispatched_at')
            ->orderByDesc('dispatched_at')
            ->paginate(10, ['*'], 'dispatched_page')
            ->appends($request->query());
        $challans = $project->challans()
            ->orderByDesc('created_at')
            ->paginate(10, ['*'], 'challans_page')
            ->appends($request->query());
        $projectTasks = StaffTask::query()
            ->where('company_id', (int) $project->company_id)
            ->where('project_id', (int) $project->id)
            ->with(['staff:id,name'])
            ->latest()
            ->get();

        return view('projects.show', [
            'project' => $project,
            'projectStages' => $this->projectStages(),
            'statusOptions' => $this->statusOptions(),
            'activeTab' => $activeTab,
            'selectedChallanId' => $selectedChallanId,
            'projectValue' => $financialSummary['project_value'],
            'financialSummary' => $financialSummary,
            'acceptedDrawing' => $acceptedDrawing,
            'readyToDispatchDispatchedItems' => $readyToDispatchDispatchedItems,
            'challans' => $challans,
            'pendingDrawingForAccept' => $pendingDrawingForAccept,
            'nextDrawingVersion' => $nextDrawingVersion,
            'completedStageAmcPlans' => AmcPlan::query()
                ->where('company_id', auth()->user()->company_id)
                ->where('is_active', true)
                ->where('duration_months', 12)
                ->orderBy('price')
                ->orderBy('visit_count')
                ->orderBy('name')
                ->get(['id', 'name', 'duration_months', 'visit_count', 'price', 'description', 'service_mode']),
            'teamMembers' => User::query()
                ->whereIn('id', $project->team_member_ids ?? [])
                ->orderBy('name')
                ->get(),
            'catalogProducts' => $catalogProducts,
            'projectUnits' => CompanyUnit::query()
                ->where('company_id', $project->company_id)
                ->pluck('name')
                ->merge($catalogProducts->pluck('unit'))
                ->merge($project->materials->pluck('unit'))
                ->map(fn ($unit) => trim((string) $unit))
                ->filter()
                ->unique()
                ->values(),
            'projectGstRates' => CompanyGstRate::query()
                ->where('company_id', $project->company_id)
                ->pluck('rate')
                ->merge($catalogProducts->pluck('gst'))
                ->merge([0])
                ->map(fn ($rate) => (float) $rate)
                ->unique()
                ->sort()
                ->values(),
            'requestedMaterialSource' => $requestedMaterialSource,
            'linkedLeadMaterialRows' => $linkedLeadMaterialRows,
            'linkedLeadMaterialSource' => $linkedLeadMaterialSource,
            'materialsPaginator' => $materialsPaginator,
            'projectTasks' => $projectTasks,
            'contractorCommissionSummary' => $contractorCommissionSummary,
            'contractors' => Contractor::query()
                ->where('company_id', $project->company_id)
                ->orderBy('name')
                ->get(['id', 'name', 'phone_number', 'email', 'referral_name']),
            'projectCustomFields' => $projectCustomFields,
        ]);
    }

    public function updateContractor(Request $request, Project $project)
    {
        $validated = $request->validate([
            'contractor_id' => [
                'nullable',
                Rule::exists('contractors', 'id')->where(fn ($query) => $query->where('company_id', $project->company_id)),
            ],
            'commission_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'commission_value' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $contractorId = filled($validated['contractor_id'] ?? null) ? (int) $validated['contractor_id'] : null;
        $commissionType = filled($validated['commission_type'] ?? null) ? (string) $validated['commission_type'] : null;
        $commissionValue = filled($validated['commission_value'] ?? null) ? round((float) $validated['commission_value'], 2) : null;

        if ($contractorId !== null) {
            if ($commissionType === null) {
                return back()
                    ->withErrors(['commission_type' => 'Commission type is required when a contractor is assigned.'])
                    ->withInput();
            }

            if ($commissionValue === null) {
                return back()
                    ->withErrors(['commission_value' => 'Commission value is required when a contractor is assigned.'])
                    ->withInput();
            }

            if ($commissionType === 'percentage' && $commissionValue > 100) {
                return back()
                    ->withErrors(['commission_value' => 'Percentage commission cannot be greater than 100.'])
                    ->withInput();
            }
        } else {
            $commissionType = null;
            $commissionValue = null;
        }

        $project->update([
            'contractor_id' => $contractorId,
            'commission_type' => $commissionType,
            'commission_value' => $commissionValue,
        ]);

        $contractorName = $project->fresh('contractor')->contractor?->name;
        $message = $contractorName
            ? "Contractor assigned: {$contractorName}."
            : 'Contractor removed from this project.';

        $this->logActivity($project, 'project', $message);

        return redirect()
            ->route('projects.show', [$project, 'tab' => 'overview'])
            ->with('success', 'Project contractor and commission updated successfully.');
    }

    private function contractorCommissionSummary(Project $project): array
    {
        $contractor = $project->contractor;
        $commissionType = $project->commission_type ?: $contractor?->commission_type;
        $commissionValue = $project->commission_value ?? $contractor?->commission_value;

        if (! $contractor || $commissionType === null || $commissionValue === null) {
            return [
                'has_contractor' => (bool) $contractor,
                'has_commission' => false,
                'type' => null,
                'value' => 0.0,
                'amount' => 0.0,
                'materials_count' => 0,
                'base_total' => 0.0,
            ];
        }

        $materials = $project->materials
            ->reject(fn (ProjectMaterial $material) => $material->source_drawing_id !== null);

        $baseTotal = round($materials->sum(function (ProjectMaterial $material) {
            $qty = (float) ($material->qty ?? 0);
            $basisRate = $material->contractor_price !== null
                ? (float) $material->contractor_price
                : (float) ($material->rate ?? 0);

            return $qty * $basisRate;
        }), 2);

        $amount = $commissionType === 'percentage'
            ? round($baseTotal * (((float) $commissionValue) / 100), 2)
            : round((float) $commissionValue, 2);

        return [
            'has_contractor' => true,
            'has_commission' => true,
            'type' => (string) $commissionType,
            'value' => round((float) $commissionValue, 2),
            'amount' => $amount,
            'materials_count' => $materials->count(),
            'base_total' => $baseTotal,
        ];
    }

    private function financialSummary(Project $project): array
    {
        $projectValue = optional(
            $project->lead?->quotations
                ?->where('status', 'accepted')
                ->sortByDesc('created_at')
                ->first()
        )->amount;

        $materials = $project->materials
            ->reject(fn (ProjectMaterial $material) => $material->source_drawing_id !== null)
            ->values();

        $materialTotal = round($materials->sum(function (ProjectMaterial $material) {
            return (float) ($material->qty ?? 0) * (float) ($material->rate ?? 0);
        }), 2);

        $contractorTotal = round($materials->sum(function (ProjectMaterial $material) {
            $qty = (float) ($material->qty ?? 0);
            $basisRate = $material->contractor_price !== null
                ? (float) $material->contractor_price
                : (float) ($material->rate ?? 0);

            return $qty * $basisRate;
        }), 2);

        $netValue = $projectValue !== null
            ? round((float) $projectValue - $materialTotal, 2)
            : null;

        $status = 'neutral';
        if ($netValue !== null) {
            if ($netValue > 0.009) {
                $status = 'positive';
            } elseif ($netValue < -0.009) {
                $status = 'negative';
            }
        }

        return [
            'project_value' => $projectValue !== null ? round((float) $projectValue, 2) : null,
            'material_total' => $materialTotal,
            'contractor_total' => $contractorTotal,
            'material_count' => $materials->count(),
            'net_value' => $netValue,
            'status' => $status,
        ];
    }

    private function linkedLeadMaterialRows(Project $project, ?string $requestedSource = null): array
    {
        if (!$project->lead) {
            return [[], null];
        }

        $products = BoqProduct::query()
            ->where('company_id', $project->company_id)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get(['id', 'category_id', 'name', 'description', 'unit', 'price', 'gst']);

        $quotations = collect($project->lead->quotations ?? [])
            ->filter(function (LeadQuotation $quotation) {
                return in_array(strtolower((string) $quotation->source), ['boq', 'form'], true)
                    && is_array($quotation->form_data ?? null)
                    && !empty($quotation->form_data['items']);
            });

        $normalizedRequestedSource = strtolower((string) $requestedSource);
        $sourceMap = [
            'quotation' => 'form',
            'form' => 'form',
            'boq' => 'boq',
        ];

        if (array_key_exists($normalizedRequestedSource, $sourceMap)) {
            $quotations = $quotations->filter(fn (LeadQuotation $quotation) => strtolower((string) $quotation->source) === $sourceMap[$normalizedRequestedSource]);
        }

        $sortedQuotations = $quotations
            ->sortByDesc(fn (LeadQuotation $quotation) => optional($quotation->created_at)->timestamp ?? 0)
            ->values();

        $quotation = $sortedQuotations->first(function (LeadQuotation $quotation) {
            return !in_array(strtolower((string) $quotation->status), ['accepted', 'approved'], true);
        });

        if (!$quotation) {
            $quotation = $sortedQuotations->first(function (LeadQuotation $quotation) {
                return in_array(strtolower((string) $quotation->status), ['accepted', 'approved'], true);
            });
        }

        if (!$quotation) {
            $quotation = $sortedQuotations->first();
        }

        if (!$quotation) {
            return [[], null];
        }

        $sourceName = strtolower((string) $quotation->source) === 'boq' ? 'BOQ' : 'quotation';
        $label = ucfirst(strtolower((string) $quotation->status)) . ' ' . $sourceName . ' ' . (string) $quotation->version;

        $rows = collect($quotation->form_data['items'] ?? [])
            ->map(function ($item) use ($products, $quotation, $label) {
                $name = trim((string) ($item['description'] ?? $item['name'] ?? $item['title'] ?? $item['item_name'] ?? ''));
                $rawQty = (float) ($item['qty'] ?? 1);
                $qty = $rawQty > 0 ? $rawQty : 1;
                $unit = trim((string) ($item['unit'] ?? $item['unit_name'] ?? ''));
                $rate = round((float) ($item['price'] ?? $item['rate'] ?? $item['unit_price'] ?? 0), 2);
                $gst = round((float) ($item['gst'] ?? 0), 2);
                $note = trim((string) ($item['note'] ?? $item['brand_model'] ?? $item['remarks'] ?? $item['description_note'] ?? ''));

                $product = null;
                if (!empty($item['product_id'])) {
                    $product = $products->firstWhere('id', (int) $item['product_id']);
                }

                if (!$product && $name !== '') {
                    $needle = strtolower($name);
                    $product = $products->first(function (BoqProduct $candidate) use ($needle, $unit, $rate) {
                        $candidateName = strtolower(trim((string) $candidate->name));
                        $candidateDescription = strtolower(trim((string) ($candidate->description ?? '')));
                        $candidateUnit = trim((string) ($candidate->unit ?? ''));
                        $candidateRate = (float) ($candidate->price ?? 0);

                        if ($candidateName !== $needle && $candidateDescription !== $needle) {
                            return false;
                        }

                        return $unit === ''
                            || $candidateUnit === ''
                            || strcasecmp($candidateUnit, $unit) === 0
                            || abs($candidateRate - $rate) < 0.01;
                    });
                }

                return [
                    'product_id' => $product?->id ? (string) $product->id : '',
                    'material_name' => $name !== '' ? $name : (string) ($product?->name ?? ''),
                    'qty' => $qty,
                    'unit' => $unit !== '' ? $unit : (string) ($product?->unit ?? ''),
                    'rate' => $rate > 0 ? $rate : (float) ($product?->price ?? 0),
                    'contractor_price' => '',
                    'gst' => $gst > 0 ? $gst : (float) ($product?->gst ?? 0),
                    'note' => $note !== '' ? $note : trim((string) ($product?->description ?? '')),
                    'source_type' => strtolower((string) $quotation->source) === 'boq' ? 'boq' : 'quotation',
                    'source_label' => $label,
                ];
            })
            ->filter(fn (array $row) => $row['material_name'] !== '')
            ->values()
            ->all();

        if ($rows === []) {
            return [[], null];
        }

        return [$rows, $label];
    }

    public function dispatchToDelivery(Request $request, Project $project)
    {
        $dispatchAction = $request->input('dispatch_action', 'dispatch_and_challan');
        $isChallan = $dispatchAction === 'dispatch_and_challan';

        $rules = [
            'material_ids' => 'required|array|min:1',
            'material_ids.*' => 'integer',
            'dispatch_action' => 'nullable|in:dispatch_only,dispatch_and_challan',
            'name_of_person' => $isChallan ? 'required|string|max:191' : 'nullable|string|max:191',
            'needed_for' => $isChallan ? 'required|string|max:191' : 'nullable|string|max:191',
            'delivery_address' => $isChallan ? 'required|string|max:1000' : 'nullable|string|max:1000',
        ];

        $validated = $request->validate($rules);

        $pendingIds = $project->materials()
            ->whereNull('source_drawing_id')
            ->where('qa_done', true)
            ->whereNull('dispatched_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $selectedIds = collect($request->input('material_ids', []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty() || $selectedIds->diff($pendingIds)->isNotEmpty()) {
            return redirect()
                ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'ready_to_dispatch'])
                ->with('error', 'Please tick only pending QA done items before dispatching.');
        }

        $dispatchAction = $validated['dispatch_action'] ?? 'dispatch_and_challan';
        $challan = null;

        DB::transaction(function () use ($project, $selectedIds, $dispatchAction, $validated, &$challan) {
            $now = now();

            $project->materials()
                ->whereIn('id', $selectedIds)
                ->update(['dispatched_at' => $now]);

            $project->update([
                'current_stage' => 'delivery',
                'completion_percentage' => $this->completionForStage('delivery'),
            ]);

            $this->logActivity($project, 'ready_to_dispatch', 'Dispatched to delivery: '.$selectedIds->count().' item'.($selectedIds->count() === 1 ? '' : 's').'.');

            if ($dispatchAction === 'dispatch_and_challan') {
                $challan = $this->createProjectChallan($project, $selectedIds, [
                    'name_of_person' => $validated['name_of_person'] ?? null,
                    'needed_for' => $validated['needed_for'] ?? null,
                    'delivery_address' => $validated['delivery_address'] ?? null,
                ]);
                $this->logActivity($project, 'delivery', 'Created challan: '.$challan->challan_no);
            }
        });

        if ($challan) {
            return redirect()
                ->route('projects.show', [$project, 'tab' => 'ready_to_dispatch', 'challan' => $challan->getRouteKey()])
                ->with('success', 'Items dispatched and challan created successfully.');
        }

        return redirect()
            ->route('projects.show', [$project, 'tab' => 'ready_to_dispatch'])
            ->with('success', 'Items dispatched successfully.');
    }

    public function storeChallan(Request $request, Project $project)
    {
        $validated = $request->validate([
            'material_ids' => 'required|array|min:1',
            'material_ids.*' => 'integer',
            'name_of_person' => 'required|string|max:191',
            'needed_for' => 'required|string|max:191',
            'delivery_address' => 'required|string|max:1000',
            'transport_name' => 'nullable|string|max:191',
            'vehicle_no' => 'nullable|string|max:191',
            'driver_name' => 'nullable|string|max:191',
            'lr_no' => 'nullable|string|max:191',
            'dispatch_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'status' => 'nullable|in:created,ongoing,delivered',
            'signature' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
        ]);

        $ids = collect($validated['material_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $dispatchedIds = $project->materials()
            ->whereNull('source_drawing_id')
            ->where('qa_done', true)
            ->whereNotNull('dispatched_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $alreadyChallanedIds = $project->challans
            ->flatMap(fn (ProjectChallan $challan) => $challan->production_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->diff($dispatchedIds)->isNotEmpty()) {
            return redirect()
                ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'ready_to_dispatch'])
                ->with('error', 'Please select only dispatched items for challan.');
        }

        if ($ids->intersect($alreadyChallanedIds)->isNotEmpty()) {
            return redirect()
                ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'ready_to_dispatch'])
                ->with('error', 'Some selected items are already part of another challan.');
        }

        $nextStatus = $validated['status'] ?? 'created';

        $signaturePath = $request->file('signature')
            ? app(MediaUploadService::class)->store($request->file('signature'), 'company_'.$project->company_id.'/projects/challans', 'public')
            : null;

        $challan = $this->createProjectChallan($project, $ids, [
            'status' => $nextStatus,
            'name_of_person' => $validated['name_of_person'] ?? null,
            'needed_for' => $validated['needed_for'] ?? null,
            'delivery_address' => $validated['delivery_address'] ?? null,
            'transport_name' => $validated['transport_name'] ?? null,
            'vehicle_no' => $validated['vehicle_no'] ?? null,
            'driver_name' => $validated['driver_name'] ?? null,
            'lr_no' => $validated['lr_no'] ?? null,
            'dispatch_date' => $validated['dispatch_date'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'signature_path' => $signaturePath,
        ]);

        $this->logActivity($project, 'delivery', 'Created challan: '.$challan->challan_no);

        if ($challan->status === 'delivered') {
            $project->update([
                'current_stage' => 'installation',
                'completion_percentage' => $this->completionForStage('installation'),
            ]);
            $this->logActivity($project, 'delivery', 'Marked challan delivered: '.$challan->challan_no);

            return redirect()
                ->route('projects.show', [$project, 'tab' => 'installation'])
                ->with('success', 'Challan created and marked as delivered. Moved to Installation.');
        }

        return redirect()
            ->route('projects.show', [$project, 'tab' => 'ready_to_dispatch', 'challan' => $challan->getRouteKey()])
            ->with('success', 'Challan created.');
    }

    public function updateChallan(Request $request, Project $project, ProjectChallan $challan)
    {
        abort_unless((int) $challan->project_id === (int) $project->id, 404);

        $validated = $request->validate([
            'name_of_person' => 'required|string|max:191',
            'needed_for' => 'required|string|max:191',
            'delivery_address' => 'required|string|max:1000',
            'transport_name' => 'nullable|string|max:191',
            'vehicle_no' => 'nullable|string|max:191',
            'driver_name' => 'nullable|string|max:191',
            'lr_no' => 'nullable|string|max:191',
            'dispatch_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'status' => 'nullable|in:created,ongoing,delivered',
            'signature' => 'nullable|file|mimes:jpg,jpeg,png,webp,pdf|max:10240',
            'challans_page' => 'nullable|integer|min:1',
        ]);

        $signaturePath = $request->file('signature')
            ? app(MediaUploadService::class)->store($request->file('signature'), 'company_'.$project->company_id.'/projects/challans', 'public')
            : null;

        if ($signaturePath) {
            $validated['signature_path'] = $signaturePath;
        }

        $challan->update($validated);

        $this->logActivity($project, 'delivery', 'Updated challan details: '.$challan->challan_no);

        if (($validated['status'] ?? null) === 'delivered') {
            $project->update([
                'current_stage' => 'installation',
                'completion_percentage' => $this->completionForStage('installation'),
            ]);
            $this->logActivity($project, 'delivery', 'Marked challan delivered: '.$challan->challan_no);

            if ($request->ajax() || $request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'refresh_url' => route('projects.show', [
                        $project,
                        'tab' => 'ready_to_dispatch',
                        'challans_page' => $request->input('challans_page', 1),
                    ]),
                ]);
            }

            return redirect()
                ->route('projects.show', [$project, 'tab' => 'ready_to_dispatch', 'challans_page' => $request->input('challans_page', 1)])
                ->with('success', 'Marked as delivered.');
        }

        if ($request->ajax() || $request->expectsJson()) {
            return response()->json([
                'success' => true,
                'refresh_url' => route('projects.show', [
                    $project,
                    'tab' => $request->string('tab')->toString() ?: 'ready_to_dispatch',
                    'challan' => $challan->getRouteKey(),
                    'challans_page' => $request->input('challans_page', 1),
                ]),
            ]);
        }

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'ready_to_dispatch', 'challans_page' => $request->input('challans_page', 1)])
            ->with('success', 'Challan details updated.');
    }

    public function printChallan(Project $project, ProjectChallan $challan)
    {
        abort_unless((int) $challan->project_id === (int) $project->id, 404);

        $materialIds = collect($challan->production_ids ?? [])->map(fn ($id) => (int) $id)->values();
        $materials = $project->materials
            ->whereIn('id', $materialIds)
            ->whereNull('source_drawing_id')
            ->values();

        return view('projects.challan-print', [
            'project' => $project,
            'challan' => $challan,
            'materials' => $materials,
        ]);
    }

    public function printCompletionSummary(Project $project)
    {
        $project->load(['company', 'lead', 'manager', 'drawings', 'materials', 'productions', 'challans']);

        $acceptedDrawing = $project->drawings
            ->sortByDesc('accepted_at')
            ->first(fn (ProjectDrawing $drawing) => $drawing->accepted_at !== null);

        $latestChallan = $project->challans->sortByDesc('created_at')->first();
        $challanMaterialIds = collect($latestChallan?->production_ids ?? [])->map(fn ($id) => (int) $id)->values();
        $challanMaterials = $project->materials
            ->whereIn('id', $challanMaterialIds)
            ->whereNull('source_drawing_id')
            ->values();

        $materials = $project->materials
            ->sortByDesc('created_at')
            ->reject(fn (ProjectMaterial $material) => $material->source_drawing_id !== null)
            ->values();

        return view('projects.completion-print', [
            'project' => $project,
            'acceptedDrawing' => $acceptedDrawing,
            'latestChallan' => $latestChallan,
            'challanMaterials' => $challanMaterials,
            'materials' => $materials,
        ]);
    }

    public function updateInstallation(Request $request, Project $project)
    {
        $validated = $request->validate([
            'site_preparation_status' => 'required|in:not_started,in_progress,done',
            'fixing_welding_status' => 'required|in:not_started,in_progress,done',
            'testing_commissioning_status' => 'required|in:not_started,pass,fail',
            'final_inspection_status' => 'required|in:ready,passed,failed',
            'client_handover_status' => 'required|in:pending,completed',
            'testing_failed' => 'nullable|boolean',
            'fail_reason' => 'nullable|string|max:500',
        ]);

        $testingFailed = $request->boolean('testing_failed');
        $failReason = $request->string('fail_reason')->toString() ?: null;

        if ($testingFailed) {
            if (empty($failReason)) {
                $message = 'Failure reason is required.';
                if ($request->expectsJson()) {
                    return response()->json(['message' => $message], 422);
                }
                return redirect()
                    ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'installation'])
                    ->with('error', $message);
            }

            $validated['site_preparation_status'] = 'not_started';
            $validated['fixing_welding_status'] = 'not_started';
            $validated['testing_commissioning_status'] = 'not_started';
            $validated['final_inspection_status'] = 'ready';
            $validated['client_handover_status'] = 'pending';
            $validated['installation_fail_reason'] = $failReason;
        }

        $project->update($validated);

        if ($testingFailed) {
            $this->logActivity($project, 'installation', 'Testing failed. Restarted installation checklist. Reason: '.$failReason);
        } else {
            $this->logActivity($project, 'installation', 'Updated installation checklist.');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'installation'])
            ->with('success', 'Installation checklist updated.');
    }

    public function completeInstallation(Request $request, Project $project)
    {
        $project->refresh();

        $isComplete =
            $project->site_preparation_status === 'done'
            && $project->fixing_welding_status === 'done'
            && $project->testing_commissioning_status === 'pass'
            && $project->final_inspection_status === 'passed'
            && $project->client_handover_status === 'completed';

        if (!$isComplete) {
            return redirect()
                ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'installation'])
                ->with('error', 'Please complete all installation steps (positive statuses) before completing the order.');
        }

        $project->update([
            'current_stage' => 'completed',
            'completion_percentage' => $this->completionForStage('completed'),
            'status' => 'completed',
            'installation_completed_at' => now(),
        ]);

        $this->logActivity($project, 'completed', 'Order completed after installation.');

        return redirect()
            ->route('projects.show', [$project, 'tab' => 'completed'])
            ->with('success', 'Order completed.')
            ->with('show_completed_amc_prompt', true);
    }

    public function updateStage(Request $request, Project $project)
    {
        $oldStage = $project->stage_label;

        $request->validate([
            'current_stage' => ['required', Rule::in($this->projectStageKeys())],
        ]);

        $currentStage = $this->resolvePersistedStageKey($request->string('current_stage')->toString());

        $project->update([
            'current_stage' => $currentStage,
            'completion_percentage' => $this->completionForStage($currentStage),
            'status' => $currentStage === 'completed' ? 'completed' : $project->status,
        ]);

        $project->refresh();
        $this->logActivity($project, 'status_change', "Stage changed from {$oldStage} to {$project->stage_label}");

        return response()->json(['success' => true]);
    }

    public function storeCompletedStageAmc(Request $request, Project $project)
    {
        $user = auth()->user();

        abort_unless((int) $project->company_id === (int) $user->company_id, 403);

        $validated = $request->validate([
            'amc_plan_id' => [
                'required',
                'integer',
                Rule::exists('amc_plans', 'id')->where(function ($query) use ($user) {
                    $query->where('company_id', (int) $user->company_id)
                        ->where('is_active', true)
                        ->where('duration_months', 12);
                }),
            ],
        ]);

        if ($this->normalizeStageKey($project->current_stage) !== 'completed') {
            return response()->json([
                'message' => 'AMC can only be created after the project is moved to the Completed stage.',
            ], 422);
        }

        $selectedPlan = AmcPlan::query()
            ->where('company_id', (int) $user->company_id)
            ->where('is_active', true)
            ->where('duration_months', 12)
            ->findOrFail($validated['amc_plan_id']);

        $existingAmc = Amc::query()
            ->where('company_id', (int) $user->company_id)
            ->where('project_id', $project->id)
            ->where('amc_plan_id', $selectedPlan->id)
            ->first();

        if ($existingAmc) {
            return response()->json([
                'success' => true,
                'message' => 'An AMC for this project and plan already exists.',
                'amc_id' => $existingAmc->id,
                'redirect_url' => route('amcs.show', $existingAmc),
                'already_exists' => true,
            ]);
        }

        $client = Client::query()
            ->where('company_id', (int) $user->company_id)
            ->where('name', $project->client_name)
            ->first();

        $startDate = now()->startOfDay();
        $endDate = Amc::calculateEndDate($startDate, (int) $selectedPlan->duration_months);
        $generatedVisits = $selectedPlan->generatesScheduledVisits()
            ? Amc::generateVisitScheduleForPlan(
                $startDate->toDateString(),
                (int) $selectedPlan->duration_months,
                (int) $selectedPlan->visit_count
            )
            : [];

        $amc = null;
        $assignedTo = $project->project_manager_id ?: null;
        $serviceMode = $selectedPlan->service_mode ?: ($selectedPlan->includes_on_call_support ? 'on_call' : 'scheduled');

        app(SubscriptionLimitService::class)->ensureWithinLimit((int) $user->company_id, 'support', 1, 'amc_plan_id');

        DB::transaction(function () use ($user, $project, $client, $selectedPlan, $startDate, $endDate, $generatedVisits, $assignedTo, $serviceMode, &$amc) {
            $ticket = Ticket::create([
                'company_id' => (int) $user->company_id,
                'user_id' => $user->id,
                'assigned_to' => $assignedTo,
                'ticket_number' => 'TKT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'customer_contact_number' => $client?->mobile ?: $client?->phone,
                'customer_name' => Str::limit($client?->name ?: $project->client_name ?: 'Project Client', 191, ''),
                'customer_email' => $client?->email,
                'type' => 'support',
                'subject' => Str::limit('AMC Service Setup - ' . $project->name, 191, ''),
                'priority' => 'medium',
                'status' => 'open',
                'description' => 'AMC created automatically from the completed project stage for project ' . $project->name . '.',
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

            $amc = Amc::create([
                'company_id' => (int) $user->company_id,
                'project_id' => $project->id,
                'client_id' => $client?->id,
                'amc_plan_id' => $selectedPlan->id,
                'support_ticket_id' => $ticket->id,
                'created_by' => $user->id,
                'contract_no' => Amc::nextContractNo((int) $user->company_id),
                'customer_name' => $client?->name ?: $project->client_name ?: 'Project Client',
                'project_name' => $project->name,
                'mobile_number' => $client?->mobile ?: $client?->phone,
                'email' => $client?->email,
                'address' => $client?->address,
                'gst_number' => $client?->gst_number,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'amc_type' => 'non_comprehensive',
                'plan_name' => $selectedPlan->name,
                'plan_duration_months' => (int) $selectedPlan->duration_months,
                'plan_price' => $selectedPlan->price,
                'plan_description' => $selectedPlan->description,
                'includes_on_call_support' => (bool) $selectedPlan->includes_on_call_support,
                'service_mode' => $serviceMode,
                'visit_frequency' => $selectedPlan->generatesScheduledVisits() ? 'plan_based' : 'on_call',
                'total_visits' => count($generatedVisits),
                'total_cameras' => 0,
                'camera_types' => [],
                'service_scope' => array_keys(Amc::SERVICE_SCOPE_OPTIONS),
                'amc_amount' => $selectedPlan->price,
                'discount' => 0,
                'tax_percent' => 0,
                'total_amount' => $selectedPlan->price,
                'payment_status' => 'pending',
                'service_timing' => 'Visits will be scheduled as per the selected AMC plan.',
                'parts_replacement_policy' => 'Parts replacement is excluded unless approved separately.',
                'emergency_support_terms' => 'Emergency support will be handled as per AMC plan terms.',
            ]);

            foreach ($generatedVisits as $visit) {
                $createdVisit = $amc->visits()->create([
                    'visit_no' => $visit['visit_no'],
                    'visit_date' => $visit['visit_date'],
                    'status' => $visit['status'],
                    'technician' => $visit['technician'] ?? null,
                ]);

                app(AmcTechnicianAssignmentService::class)->assignTechnicianIfRequired(
                    $createdVisit,
                    now(),
                    'project_completion'
                );
            }
        });

        $this->logActivity($project, 'amc', 'Created AMC from completed stage: '.$selectedPlan->name.'.');

        return response()->json([
            'success' => true,
            'message' => 'AMC created successfully.',
            'amc_id' => $amc->id,
            'redirect_url' => route('amcs.show', $amc),
        ]);
    }

    public function storeDrawing(Request $request, Project $project)
    {
        $media = app(MediaUploadService::class);
        abort_if($project->drawings()->whereNotNull('accepted_at')->exists(), 422, 'An accepted drawing already exists for this project.');

        $request->validate([
            'drawing_files' => 'required|array|min:1',
            'drawing_files.*' => 'file|mimes:jpg,jpeg,png,webp,pdf,dwg,dxf|max:51200', // 50 MB
            'drawing_status' => 'nullable|string|max:100',
        ], [
            'drawing_files.required' => 'Please select at least one drawing file.',
            'drawing_files.*.mimes' => 'Allowed file types: JPG, JPEG, PNG, PDF, DWG, DXF.',
            'drawing_files.*.max' => 'Each drawing file must be 50 MB or smaller.',
            'drawing_files.*.file' => 'Each drawing must be a valid file.',
        ]);

        $version = $this->nextDrawingVersion($project);
        $drawingStatus = $request->string('drawing_status')->toString() ?: 'pending';

        try {
            foreach ($request->file('drawing_files', []) as $file) {
                $path = $media->store($file, 'company_'.$project->company_id.'/projects/drawings', 'public');

                $project->drawings()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'version' => $version,
                    'drawing_status' => $drawingStatus,
                ]);
            }
        } catch (StorageLimitExceededException $e) {
            return redirect()
                ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'drawing'])
                ->withInput()
                ->withErrors(['drawing_files' => $e->getMessage()]);
        }

        $this->logActivity($project, 'drawing', 'Uploaded drawing files.');

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'drawing'])
            ->with('success', 'Drawing files uploaded.');
    }

    public function acceptDrawing(Project $project, ProjectDrawing $drawing)
    {
        abort_unless((int) $drawing->project_id === (int) $project->id, 404);

        return $this->acceptDrawingsInternal($project, [$drawing->id], 'drawing');
    }

    public function acceptDrawings(Request $request, Project $project)
    {
        $validated = $request->validate([
            'drawing_ids' => 'required|array|min:1',
            'drawing_ids.*' => 'integer',
        ]);

        $ids = collect($validated['drawing_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $tab = $request->string('tab')->toString() ?: 'drawing';

        return $this->acceptDrawingsInternal($project, $ids, $tab);
    }

    private function acceptDrawingsInternal(Project $project, array $drawingIds, string $tab): \Illuminate\Http\RedirectResponse
    {
        $drawingIds = collect($drawingIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (empty($drawingIds)) {
            return redirect()
                ->route('projects.show', ['project' => $project, 'tab' => $tab])
                ->with('error', 'Please select at least one drawing to accept.');
        }

        $acceptedDrawing = $project->drawings()
            ->whereNotNull('accepted_at')
            ->orderByDesc('accepted_at')
            ->first();

        $drawings = $project->drawings()
            ->whereIn('id', $drawingIds)
            ->get();

        if ($drawings->count() !== count($drawingIds)) {
            return redirect()
                ->route('projects.show', ['project' => $project, 'tab' => $tab])
                ->with('error', 'Some selected drawings are invalid.');
        }

        if ($drawings->contains(fn (ProjectDrawing $drawing) => $drawing->accepted_at !== null)) {
            return redirect()
                ->route('projects.show', ['project' => $project, 'tab' => $tab])
                ->with('success', 'Selected drawings are already accepted.');
        }

        $versions = $drawings->pluck('version')->unique()->values();
        if ($versions->count() !== 1) {
            return redirect()
                ->route('projects.show', ['project' => $project, 'tab' => $tab])
                ->with('error', 'Please select drawings from a single version to accept.');
        }

        $selectedVersion = (string) $versions->first();

        if ($acceptedDrawing && (string) $acceptedDrawing->version !== $selectedVersion) {
            return redirect()
                ->route('projects.show', ['project' => $project, 'tab' => $tab])
                ->with('error', 'A different drawing version has already been accepted for this project.');
        }

        $now = now();

        $project->drawings()
            ->whereIn('id', $drawingIds)
            ->whereNull('accepted_at')
            ->update([
                'drawing_status' => 'accepted',
                'accepted_at' => $now,
            ]);

        if (in_array($project->current_stage, ['drawing', 'new-project'], true)) {
            $project->update([
                'current_stage' => 'material',
                'completion_percentage' => $this->completionForStage('material'),
            ]);
            $this->logActivity($project, 'status_change', 'Stage changed from New Project to Material (drawing accepted).');
        }

        $this->logActivity(
            $project,
            'drawing',
            'Accepted drawing version '.$selectedVersion.' ('.count($drawingIds).' file'.(count($drawingIds) === 1 ? '' : 's').').'
        );

        return redirect()
            ->route('projects.show', ['project' => $project, 'tab' => $tab])
            ->with('success', 'Drawing(s) accepted.');
    }

    public function downloadDrawings(Project $project)
    {
        $project->load('drawings');

        abort_if($project->drawings->isEmpty(), 404);

        $zipPath = storage_path('app/temp-project-drawings-'.$project->id.'.zip');

        if (file_exists($zipPath)) {
            @unlink($zipPath);
        }

        $zip = new ZipArchive();
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($project->drawings as $drawing) {
            $fullPath = storage_path('app/public/'.$drawing->file_path);
            if (file_exists($fullPath)) {
                $zip->addFile($fullPath, $drawing->file_name);
            }
        }

        $zip->close();

        return response()->download($zipPath, $project->reference_no.'-drawings.zip')->deleteFileAfterSend(true);
    }

    public function viewDrawing(Project $project, ProjectDrawing $drawing)
    {
        abort_unless((int) $drawing->project_id === (int) $project->id, 404);

        $path = storage_path('app/public/'.$drawing->file_path);
        abort_unless(is_file($path), 404);

        $mimeType = mime_content_type($path) ?: 'application/octet-stream';

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$drawing->file_name.'"',
        ]);
    }

    public function viewMaterial(Project $project, ProjectMaterial $material)
    {
        abort_unless((int) $material->project_id === (int) $project->id, 404);
        abort_if(empty($material->attachment_path), 404);

        $path = storage_path('app/public/'.$material->attachment_path);
        abort_unless(is_file($path), 404);

        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
        $fileName = basename($material->attachment_path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    public function storeMaterial(Request $request, Project $project)
    {
        if ($request->has('materials')) {
            $validated = $request->validate([
                'materials' => 'required|array|min:1',
                'materials.*.product_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('boq_products', 'id')->where('company_id', $project->company_id),
                ],
                'materials.*.material_name' => 'required|string|max:191',
                'materials.*.qty' => 'required|numeric|min:0.01',
                'materials.*.unit' => [
                    'nullable',
                    'string',
                    'max:50',
                    Rule::exists('company_units', 'name')->where('company_id', $project->company_id),
                ],
                'materials.*.rate' => 'nullable|numeric|min:0',
                'materials.*.contractor_price' => 'nullable|numeric|min:0',
                'materials.*.gst' => [
                    'nullable',
                    'numeric',
                    'min:0',
                    'max:100',
                    function (string $attribute, mixed $value, \Closure $fail) use ($project): void {
                        if ($value === null || (float) $value === 0.0) {
                            return;
                        }

                        $exists = CompanyGstRate::query()
                            ->where('company_id', $project->company_id)
                            ->where('rate', $value)
                            ->exists();

                        if (! $exists) {
                            $fail('The selected GST rate is invalid.');
                        }
                    },
                ],
                'materials.*.note' => 'nullable|string|max:2000',
                'materials.*.source_type' => ['nullable', 'string', Rule::in(['manual', 'quotation', 'boq'])],
                'materials.*.source_label' => 'nullable|string|max:191',
            ]);

            $rows = collect($validated['materials'] ?? [])
                ->filter(fn ($row) => is_array($row))
                ->values();

            foreach ($rows as $row) {
                $qty = round((float) ($row['qty'] ?? 1), 2);
                $rate = round((float) ($row['rate'] ?? 0), 2);
                $contractorPrice = array_key_exists('contractor_price', $row) && $row['contractor_price'] !== null && $row['contractor_price'] !== ''
                    ? round((float) $row['contractor_price'], 2)
                    : null;
                $project->materials()->create([
                    'product_id' => $row['product_id'] ?? null,
                    'material_name' => (string) ($row['material_name'] ?? ''),
                    'qty' => $qty,
                    'unit' => ($row['unit'] ?? null) ?: null,
                    'rate' => $rate,
                    'contractor_price' => $contractorPrice,
                    'gst' => round((float) ($row['gst'] ?? 0), 2),
                    'total' => $this->calculateMaterialTotal($qty, $rate, $contractorPrice),
                    'note' => ($row['note'] ?? null) ?: null,
                    'attachment_path' => null,
                    'source_type' => $row['source_type'] ?? 'manual',
                    'source_label' => ($row['source_label'] ?? null) ?: null,
                ]);
            }

            $this->logActivity($project, 'material', 'Added material items: '.$rows->count());

            $materialCount = $project->materials()->whereNull('source_drawing_id')->count();
            if ($project->current_stage === 'material' && $materialCount > 0) {
                $project->update([
                    'completion_percentage' => $this->completionForStage('material'),
                ]);
            }

            return redirect()
                ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'material'])
                ->with('success', 'Material items added.');
        }

        $request->validate([
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('boq_products', 'id')->where('company_id', $project->company_id),
            ],
            'material_name' => 'required|string|max:191',
            'qty' => 'required|numeric|min:0.01',
            'unit' => [
                'nullable',
                'string',
                'max:50',
                Rule::exists('company_units', 'name')->where('company_id', $project->company_id),
            ],
            'rate' => 'nullable|numeric|min:0',
            'contractor_price' => 'nullable|numeric|min:0',
            'gst' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($project): void {
                    if ($value === null || (float) $value === 0.0) {
                        return;
                    }

                    $exists = CompanyGstRate::query()
                        ->where('company_id', $project->company_id)
                        ->where('rate', $value)
                        ->exists();

                    if (! $exists) {
                        $fail('The selected GST rate is invalid.');
                    }
                },
            ],
            'note' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:20480',
        ]);

            $attachmentPath = $request->file('attachment')
            ? app(MediaUploadService::class)->store($request->file('attachment'), 'company_'.$project->company_id.'/projects/materials', 'public')
            : null;

        $rate = round((float) $request->input('rate', 0), 2);
        $contractorPrice = $request->filled('contractor_price')
            ? round((float) $request->input('contractor_price'), 2)
            : null;

        $project->materials()->create([
            'product_id' => $request->input('product_id'),
            'material_name' => $request->string('material_name')->toString(),
            'qty' => $request->input('qty'),
            'unit' => $request->string('unit')->toString() ?: null,
            'rate' => $rate,
            'contractor_price' => $contractorPrice,
            'gst' => round((float) $request->input('gst', 0), 2),
            'total' => $this->calculateMaterialTotal((float) $request->input('qty'), $rate, $contractorPrice),
            'note' => $request->string('note')->toString() ?: null,
            'attachment_path' => $attachmentPath,
            'source_type' => 'manual',
            'source_label' => null,
        ]);

        $this->logActivity($project, 'material', 'Added material item: '.$request->string('material_name')->toString());

        $materialCount = $project->materials()->whereNull('source_drawing_id')->count();
        if ($project->current_stage === 'material' && $materialCount > 0) {
            $project->update([
                'completion_percentage' => $this->completionForStage('material'),
            ]);
        }

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'material'])
            ->with('success', 'Material item added.');
    }

    public function updateMaterial(Request $request, Project $project, ProjectMaterial $material)
    {
        abort_unless((int) $material->project_id === (int) $project->id, 404);

        $request->validate([
            'product_id' => [
                'nullable',
                'integer',
                Rule::exists('boq_products', 'id')->where('company_id', $project->company_id),
            ],
            'material_name' => 'required|string|max:191',
            'qty' => 'required|numeric|min:0.01',
            'unit' => [
                'nullable',
                'string',
                'max:50',
                Rule::exists('company_units', 'name')->where('company_id', $project->company_id),
            ],
            'rate' => 'nullable|numeric|min:0',
            'contractor_price' => 'nullable|numeric|min:0',
            'gst' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                function (string $attribute, mixed $value, \Closure $fail) use ($project): void {
                    if ($value === null || (float) $value === 0.0) {
                        return;
                    }

                    $exists = CompanyGstRate::query()
                        ->where('company_id', $project->company_id)
                        ->where('rate', $value)
                        ->exists();

                    if (! $exists) {
                        $fail('The selected GST rate is invalid.');
                    }
                },
            ],
            'note' => 'nullable|string|max:2000',
            'attachment' => 'nullable|file|max:20480',
        ]);

        $oldAttachmentPath = $material->attachment_path;
            $newAttachmentPath = $request->file('attachment')
            ? app(MediaUploadService::class)->store($request->file('attachment'), 'company_'.$project->company_id.'/projects/materials', 'public')
            : null;

        $rate = round((float) $request->input('rate', 0), 2);
        $contractorPrice = $request->filled('contractor_price')
            ? round((float) $request->input('contractor_price'), 2)
            : null;

        $material->update([
            'product_id' => $request->input('product_id'),
            'material_name' => $request->string('material_name')->toString(),
            'qty' => $request->input('qty'),
            'unit' => $request->string('unit')->toString() ?: null,
            'rate' => $rate,
            'contractor_price' => $contractorPrice,
            'gst' => round((float) $request->input('gst', 0), 2),
            'total' => $this->calculateMaterialTotal((float) $request->input('qty'), $rate, $contractorPrice),
            'note' => $request->string('note')->toString() ?: null,
            'attachment_path' => $newAttachmentPath ?: $oldAttachmentPath,
        ]);

        if ($newAttachmentPath && $oldAttachmentPath) {
            app(MediaUploadService::class)->delete($oldAttachmentPath, 'public', $project->company_id);
        }

        $this->logActivity($project, 'material', 'Updated material item: '.$material->material_name);

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'material'])
            ->with('success', 'Material item updated.');
    }

    public function destroyMaterial(Request $request, Project $project, ProjectMaterial $material)
    {
        abort_unless((int) $material->project_id === (int) $project->id, 404);

        if (!empty($material->attachment_path)) {
            app(MediaUploadService::class)->delete($material->attachment_path, 'public', $project->company_id);
        }

        $materialName = $material->material_name;
        $material->delete();

        $this->logActivity($project, 'material', 'Deleted material item: '.$materialName);

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'material'])
            ->with('success', 'Material item deleted.');
    }

    public function updateMaterialQa(Request $request, Project $project, ProjectMaterial $material)
    {
        abort_unless((int) $material->project_id === (int) $project->id, 404);

        $request->validate([
            'qa_done' => 'required|boolean',
        ]);

        $material->update([
            'qa_done' => $request->boolean('qa_done'),
        ]);

        $this->logActivity(
            $project,
            'material',
            'Updated dispatch status for material: '.$material->material_name.' ('.($material->qa_done ? 'Ready to dispatch' : 'Dispatch pending').')'
        );

        $hasMaterials = $project->materials()->whereNull('source_drawing_id')->exists();
        $hasPendingQa = $project->materials()
            ->whereNull('source_drawing_id')
            ->where('qa_done', false)
            ->exists();

        if ($hasMaterials && !$hasPendingQa) {
            $project->update([
                'current_stage' => 'ready_to_dispatch',
                'completion_percentage' => $this->completionForStage('ready_to_dispatch'),
            ]);
            $this->logActivity($project, 'status_change', 'Stage changed from Material to Ready to Dispatch (all material ready to dispatch).');

            return redirect()
                ->route('projects.show', [$project, 'tab' => 'ready_to_dispatch'])
                ->with('success', 'Material ready to dispatch.');
        }

        if ($project->current_stage === 'ready_to_dispatch' && $hasPendingQa) {
            $project->update([
                'current_stage' => 'material',
                'completion_percentage' => $this->completionForStage('material'),
            ]);
            $this->logActivity($project, 'status_change', 'Stage changed from Ready to Dispatch to Material (material QA pending).');
        }

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'material'])
            ->with('success', $material->qa_done ? 'Material ready to dispatch.' : 'Material dispatch pending.');
    }

    public function storeProduction(Request $request, Project $project)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|min:2|max:191',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|max:20480',
            'qa_done' => 'nullable|boolean',
        ], [
            'title.required' => 'Title is required.',
            'title.min' => 'Title must be at least 2 characters.',
            'title.max' => 'Title may not be greater than 191 characters.',
            'note.max' => 'Note may not be greater than 1000 characters.',
            'attachment.max' => 'File size is too large (max 20 MB).',
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('projects.show', [$project, 'tab' => 'production'])
                ->withErrors($validator)
                ->withInput();
        }

        $validated = $validator->validated();

        $attachmentPath = $request->file('attachment')
            ? app(MediaUploadService::class)->store($request->file('attachment'), 'company_'.$project->company_id.'/projects/production', 'public')
            : null;

        $project->productions()->create([
            'title' => trim((string) ($validated['title'] ?? '')),
            'note' => filled($validated['note'] ?? null) ? trim((string) $validated['note']) : null,
            'attachment_path' => $attachmentPath,
            'qa_done' => $request->boolean('qa_done'),
        ]);

        $this->logActivity($project, 'production', 'Added production update: '.trim((string) ($validated['title'] ?? '')));

        if ($project->current_stage === 'material') {
            $project->update([
                'current_stage' => 'production',
                'completion_percentage' => $this->completionForStage('production'),
            ]);
            $this->logActivity($project, 'status_change', 'Stage changed from Material to Production (production update added).');
        }

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'production'])
            ->with('success', 'Production update added.');
    }

    public function updateProduction(Request $request, Project $project, ProjectProduction $production)
    {
        abort_unless((int) $production->project_id === (int) $project->id, 404);

        $request->validate([
            'title' => 'required|string|min:2|max:191',
            'note' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|max:20480',
        ]);

        $oldAttachmentPath = $production->attachment_path;
        $newAttachmentPath = $request->file('attachment')
            ? app(MediaUploadService::class)->store($request->file('attachment'), 'company_'.$project->company_id.'/projects/production', 'public')
            : null;

        $production->update([
            'title' => $request->string('title')->toString(),
            'note' => $request->string('note')->toString() ?: null,
            'attachment_path' => $newAttachmentPath ?: $oldAttachmentPath,
        ]);

        if ($newAttachmentPath && $oldAttachmentPath) {
            app(MediaUploadService::class)->delete($oldAttachmentPath, 'public', $project->company_id);
        }

        $this->logActivity($project, 'production', 'Updated production update: '.$production->title);

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'production'])
            ->with('success', 'Production update updated.');
    }

    public function destroyProduction(Request $request, Project $project, ProjectProduction $production)
    {
        abort_unless((int) $production->project_id === (int) $project->id, 404);

        if (!empty($production->attachment_path)) {
            app(MediaUploadService::class)->delete($production->attachment_path, 'public', $project->company_id);
        }

        $title = $production->title;
        $production->delete();

        $this->logActivity($project, 'production', 'Deleted production update: '.$title);

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'production'])
            ->with('success', 'Production update deleted.');
    }

    public function updateProductionQa(Request $request, Project $project, ProjectProduction $production)
    {
        abort_unless((int) $production->project_id === (int) $project->id, 404);

        $request->validate([
            'qa_done' => 'required|boolean',
        ]);

        $production->update([
            'qa_done' => $request->boolean('qa_done'),
        ]);

        $this->logActivity(
            $project,
            'production',
            'Updated QA status for production: '.$production->title.' ('.($production->qa_done ? 'Done' : 'Pending').')'
        );

        if ($project->current_stage === 'production') {
            $hasProductions = $project->productions()->exists();
            $hasPendingQa = $project->productions()->where('qa_done', false)->exists();

            if ($hasProductions && !$hasPendingQa) {
                $project->update([
                    'current_stage' => 'ready_to_dispatch',
                    'completion_percentage' => $this->completionForStage('ready_to_dispatch'),
                ]);
                $this->logActivity($project, 'status_change', 'Stage changed from Production to Ready to Dispatch (all QA done).');

                return redirect()
                    ->route('projects.show', [$project, 'tab' => 'ready_to_dispatch'])
                    ->with('success', 'QA status updated.');
            }
        }

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'production'])
            ->with('success', 'QA status updated.');
    }

    public function viewProduction(Project $project, ProjectProduction $production)
    {
        abort_unless((int) $production->project_id === (int) $project->id, 404);
        abort_if(empty($production->attachment_path), 404);

        $path = storage_path('app/public/'.$production->attachment_path);
        abort_unless(is_file($path), 404);

        $mimeType = mime_content_type($path) ?: 'application/octet-stream';
        $fileName = basename($production->attachment_path);

        return response()->file($path, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }

    public function printProductionSummary(Project $project)
    {
        $project->load(['company', 'manager', 'lead', 'productions']);

        return view('projects.production-print', compact('project'));
    }

    public function addComment(Request $request, Project $project)
    {
        $request->validate(['comment' => 'required|string']);

        $project->comments()->create([
            'user_id' => auth()->id(),
            'comment' => $request->string('comment')->toString(),
        ]);

        $this->logActivity($project, 'note', 'Added a comment');

        return redirect()
            ->route('projects.show', [$project, 'tab' => $request->string('tab')->toString() ?: 'comments'])
            ->with('success', 'Comment added.');
    }

    private function projectStages(): array
    {
        ProjectStage::ensureDefaults((int) auth()->user()->company_id);

        return \App\Models\ProjectStage::query()
            ->where('company_id', auth()->user()->company_id)
            ->orderBy('order')
            ->get()
            ->mapWithKeys(function ($stage) {
                $key = $this->normalizeStageKey($stage->key ?: \Illuminate\Support\Str::slug($stage->name));

                return [$key => [
                    'name' => $stage->name,
                    'color' => $stage->color ?: '#4f46e5',
                    'is_default' => (bool) $stage->is_default,
                ]];
            })
            ->toArray();
    }

    private function statusOptions(): array
    {
        return [
            'on_track' => 'On Track',
            'at_risk' => 'At Risk',
            'delayed' => 'Delayed',
            'completed' => 'Completed',
        ];
    }

    private function completionForStage(string $stage): int
    {
        return match ($stage) {
            'new-project', 'drawing' => 0,
            'in-progress' => 45,
            'material' => 25,
            'production' => 45,
            'ready_to_dispatch' => 65,
            'delivery' => 80,
            'installation' => 95,
            'completed' => 100,
            default => 0,
        };
    }

    private function nextDrawingVersion(Project $project): string
    {
        $latestVersion = $project->drawings
            ->map(function (ProjectDrawing $drawing) {
                return is_numeric($drawing->version) ? (float) $drawing->version : null;
            })
            ->filter(fn ($version) => $version !== null)
            ->max();

        return number_format(($latestVersion ?? 0) + 1, 1, '.', '');
    }

    private function calculateMaterialTotal(float|int $qty, float|int $rate, float|int|null $contractorPrice = null): float
    {
        $qtyValue = (float) $qty;
        $basisRate = $contractorPrice !== null
            ? (float) $contractorPrice
            : (float) $rate;

        return round($qtyValue * $basisRate, 2);
    }

    private function logActivity(Project $project, string $type, string $description): void
    {
        ProjectActivity::create([
            'project_id' => $project->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
            'occurred_at' => now(),
        ]);
    }

    private function createProjectChallan(Project $project, Collection $materialIds, array $attributes = []): ProjectChallan
    {
        return ProjectChallan::create([
            'project_id' => $project->id,
            'company_id' => $project->company_id,
            'production_ids' => $materialIds->values()->all(),
            'status' => $attributes['status'] ?? 'created',
            'name_of_person' => $attributes['name_of_person'] ?? null,
            'needed_for' => $attributes['needed_for'] ?? null,
            'delivery_address' => $attributes['delivery_address'] ?? null,
            'transport_name' => $attributes['transport_name'] ?? null,
            'vehicle_no' => $attributes['vehicle_no'] ?? null,
            'driver_name' => $attributes['driver_name'] ?? null,
            'lr_no' => $attributes['lr_no'] ?? null,
            'dispatch_date' => $attributes['dispatch_date'] ?? now()->toDateString(),
            'remarks' => $attributes['remarks'] ?? null,
            'signature_path' => $attributes['signature_path'] ?? null,
            'created_by' => auth()->id(),
        ]);
    }

    private function normalizeStageKey(?string $stage): string
    {
        $stage = trim((string) $stage);

        return in_array($stage, ['drawing', 'new-project'], true) ? 'new-project' : $stage;
    }

    private function normalizeBoardStageKey(?string $stage, ?array $knownStageKeys = null): string
    {
        $stage = $this->normalizeStageKey($stage);

        if ($stage === 'in-progress') {
            return 'in-progress';
        }

        $legacySubStages = ['material', 'production', 'ready_to_dispatch', 'delivery', 'installation'];

        // These used to be internal fulfillment sub-stages folded into a single
        // "In Progress" column. A company may now define its own stage with a
        // colliding key (e.g. a custom "Installation" stage) — in that case the
        // real stage column takes precedence and must not be folded away.
        if (in_array($stage, $legacySubStages, true) && ! in_array($stage, $knownStageKeys ?? [], true)) {
            return 'in-progress';
        }

        return $stage;
    }

    private function resolvePersistedStageKey(?string $stage): string
    {
        $stage = $this->normalizeStageKey($stage);

        if ($stage === 'drawing') {
            return 'new-project';
        }

        return $stage === 'in-progress' ? 'material' : $stage;
    }

    private function resolveExistingClientIdForProject(?int $companyId, ?string $clientName): ?int
    {
        $normalizedName = Str::lower(trim((string) $clientName));

        if ($companyId <= 0 || $normalizedName === '') {
            return null;
        }

        return Client::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->value('id');
    }

    private function projectStageQueryKeys(string $stage): array
    {
        return [$stage];
    }

    private function projectBoardStageQueryKeys(string $stage, ?array $knownStageKeys = null): array
    {
        if ($stage === 'new-project') {
            return ['new-project', 'drawing'];
        }

        if ($stage !== 'in-progress') {
            return [$stage];
        }

        $legacySubStages = ['material', 'production', 'ready_to_dispatch', 'delivery', 'installation'];
        $foldedLegacyKeys = array_diff($legacySubStages, $knownStageKeys ?? []);

        return array_values(array_unique(array_merge(['in-progress'], $foldedLegacyKeys)));
    }

    private function projectStageKeys(): array
    {
        ProjectStage::ensureDefaults((int) auth()->user()->company_id);

        return array_values(array_unique(array_merge(
            \App\Models\ProjectStage::query()
                ->where('company_id', auth()->user()->company_id)
                ->pluck('key')
                ->filter()
                ->map(fn ($key) => $this->normalizeStageKey((string) $key))
                ->reject(fn ($key) => $key === 'drawing')
                ->all()
        )));
    }

    private function filteredProjectsQuery(Request $request, ?Company $company = null)
    {
        $user = auth()->user();
        $search = trim((string) $request->string('search'));
        $knownStageKeys = array_keys($this->projectStages());
        $selectedStage = $request->filled('stage')
            ? $this->normalizeBoardStageKey($request->string('stage')->toString(), $knownStageKeys)
            : null;
        $startDateFrom = $request->filled('start_date_from')
            ? $request->date('start_date_from')
            : ($request->filled('date_from') ? $request->date('date_from') : null);
        $startDateTo = $request->filled('start_date_to')
            ? $request->date('start_date_to')
            : ($request->filled('date_to') ? $request->date('date_to') : null);
        $customFieldFilters = $this->normalizeProjectCustomFieldFilters($company, $request->input('custom_fields', []));
        $customFieldDefinitions = collect($company?->normalizedProjectCustomFields() ?? [])
            ->keyBy(fn (array $field) => (string) ($field['key'] ?? ''));

        return Project::query()
            ->accessibleTo($user)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';

                $query->where(function ($searchQuery) use ($like) {
                    $searchQuery->where('name', 'like', $like)
                        ->orWhere('reference_no', 'like', $like)
                        ->orWhere('client_name', 'like', $like)
                        ->orWhereHas('lead', function ($leadQuery) use ($like) {
                            $leadQuery->where('client_name', 'like', $like);
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('manager'), fn ($query) => $query->where('project_manager_id', $request->integer('manager')))
            ->when($request->filled('team_member'), fn ($query) => $query->whereJsonContains('team_member_ids', (int) $request->integer('team_member')))
            ->when($request->filled('client'), fn ($query) => $query->where('client_name', $request->string('client')))
            ->when($request->filled('state'), fn ($query) => $query->where('state_id', $request->integer('state')))
            ->when($request->filled('city'), fn ($query) => $query->where('city_id', $request->integer('city')))
            ->when($selectedStage, fn ($query) => $query->whereIn('current_stage', $this->projectBoardStageQueryKeys($selectedStage, $knownStageKeys)))
            ->when($startDateFrom, fn ($query) => $query->whereDate('start_date', '>=', $startDateFrom))
            ->when($startDateTo, fn ($query) => $query->whereDate('start_date', '<=', $startDateTo))
            ->when(! empty($customFieldFilters), function ($query) use ($customFieldFilters, $customFieldDefinitions) {
                foreach ($customFieldFilters as $fieldKey => $fieldFilter) {
                    $this->applyProjectCustomFieldFilter(
                        $query,
                        (string) $fieldKey,
                        $fieldFilter,
                        $customFieldDefinitions->get((string) $fieldKey)
                    );
                }
            });
    }

    private function normalizeProjectCustomFieldFilters(?Company $company, mixed $rawFilters): array
    {
        $fields = collect($company?->normalizedProjectCustomFields() ?? [])
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

    private function applyProjectCustomFieldFilter($query, string $fieldKey, mixed $value, ?array $field = null): void
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

    private function resolveCompanyLocationDefaults(?Company $company, Collection $states): array
    {
        $companyStateName = Str::lower(trim((string) $company?->state));
        $companyCityName = Str::lower(trim((string) $company?->city));

        if ($companyStateName === '') {
            return ['state_id' => null, 'city_id' => null];
        }

        $matchedState = $states->first(function (State $state) use ($companyStateName) {
            return Str::lower(trim((string) $state->name)) === $companyStateName;
        });

        if (! $matchedState) {
            return ['state_id' => null, 'city_id' => null];
        }

        $matchedCity = $companyCityName !== ''
            ? $matchedState->cities->first(function ($city) use ($companyCityName) {
                return Str::lower(trim((string) $city->name)) === $companyCityName;
            })
            : null;

        return [
            'state_id' => $matchedState->id,
            'city_id' => $matchedCity?->id,
        ];
    }
}
