<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amc;
use App\Models\Client;
use App\Models\Project;
// use App\Models\Staff;
use App\Models\StaffTask;
use App\Models\Ticket;
use App\Models\Lead;
use App\Models\ProjectStage;
use Illuminate\Support\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyProjectController extends Controller
{
    // public function store(Request $request): JsonResponse
    // {
    //     $user = $request->user();

    //     abort_unless(
    //         $user?->isCompanyAdmin(),
    //         403,
    //         'Only company admins can add project records.'
    //     );

    //     $companyId = (int) $user->company_id;

    //     $validated = Validator::make($request->all(), [
    //         'client_id' => [
    //             'required',
    //             'integer',
    //             Rule::exists('clients', 'id')->where(
    //                 fn ($query) => $query->where('company_id', $companyId)
    //             ),
    //         ],

    //         'lead_id' => [
    //             'nullable',
    //             'integer',
    //             Rule::exists('leads', 'id')->where(
    //                 fn ($query) => $query->where('company_id', $companyId)
    //             ),
    //         ],

    //         'name' => [
    //             'required',
    //             'string',
    //             'min:2',
    //             'max:191',
    //         ],

    //         'staff_id' => [
    //             'required',
    //             'integer',
    //             Rule::exists('staff', 'id')->where(
    //                 fn ($query) => $query->where('company_id', $companyId)
    //             ),
    //         ],

    //         'start_date' => [
    //             'nullable',
    //             'date',
    //         ],

    //         'deadline_date' => [
    //             'nullable',
    //             'date',
    //             'after_or_equal:start_date',
    //         ],

    //         'project_description' => [
    //             'nullable',
    //             'string',
    //             'max:2000',
    //         ],
    //     ], [
    //         'client_id.required' => 'client_id not available.',
    //         'client_id.exists' => 'client_id not available.',

    //         'lead_id.integer' => 'lead_id must be a valid integer.',
    //         'lead_id.exists' => 'lead_id not available.',

    //         'name.required' => 'Project name is required.',
    //         'name.min' => 'Project name must be at least 2 characters.',
    //         'name.max' => 'Project name may not be greater than 191 characters.',

    //         'staff_id.required' => 'staff_id not available.',
    //         'staff_id.exists' => 'staff_id not available.',

    //         'deadline_date.after_or_equal' =>
    //             'Deadline date must be the same as or later than the start date.',

    //         'project_description.max' =>
    //             'Project description may not be greater than 2000 characters.',
    //     ])->validate();

    //     /*
    //     * Company-safe client lookup
    //     */
    //     $client = Client::query()
    //         ->where('company_id', $companyId)
    //         ->findOrFail((int) $validated['client_id']);

    //     /*
    //     * Company-safe staff lookup
    //     */
    //     $staff = Staff::query()
    //         ->where('company_id', $companyId)
    //         ->findOrFail((int) $validated['staff_id']);

    //     /*
    //     * Lead is optional.
    //     * If lead_id is provided, make sure it belongs
    //     * to the logged-in user's company.
    //     */
    //     $lead = null;

    //     if (!empty($validated['lead_id'])) {
    //         $lead = Lead::query()
    //             ->where('company_id', $companyId)
    //             ->findOrFail((int) $validated['lead_id']);
    //     }

    //     /*
    //     * Create project
    //     */
    //     $project = DB::transaction(function () use (
    //         $companyId,
    //         $client,
    //         $staff,
    //         $lead,
    //         $validated
    //     ): Project {
    //         return Project::create([
    //             'company_id' => $companyId,

    //             // Optional lead relationship
    //             'lead_id' => $lead?->id,
    //             'name' => trim((string) $validated['name']),
    //             'client_name' => trim((string) $client->name),
    //             'project_manager_id' => $staff->user_id ?: null,
    //             'description' => $validated['project_description'] ?? null,
    //             'start_date' => $validated['start_date'] ?? null,
    //             'end_date' => $validated['deadline_date'] ?? null,
    //         ])->fresh([
    //             'manager',
    //         ]);
    //     });

    //     return response()->json([
    //         'message' => 'Project created successfully.',
    //         'data' => $project,
    //     ], 201);
    // }
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // abort_unless(
        //     $user?->isCompanyAdmin(),
        //     403,
        //     'Only company admins can add project records.'
        // );

        $companyId = (int) $user->company_id;

        $validated = Validator::make($request->all(), [
            /*
            |--------------------------------------------------------------------------
            | Client
            |--------------------------------------------------------------------------
            */
            'client_id' => [
                'required',
                'integer',
                Rule::exists('clients', 'id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Optional Lead
            |--------------------------------------------------------------------------
            */
            'lead_id' => [
                'nullable',
                'integer',
                Rule::exists('leads', 'id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Project
            |--------------------------------------------------------------------------
            */
            'name' => [
                'required',
                'string',
                'min:2',
                'max:191',
            ],

            'project_description' => [
                'nullable',
                'string',
                'max:2000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Project Manager
            |--------------------------------------------------------------------------
            */
            // 'staff_id' => [
            //     'nullable',
            //     'integer',
            //     Rule::exists('staff', 'id')->where(
            //         fn ($query) => $query->where('company_id', $companyId)
            //     ),
            // ],

            /*
            |--------------------------------------------------------------------------
            | Location
            |--------------------------------------------------------------------------
            */
            'state_id' => [
                'nullable',
                'integer',
                'exists:states,id',
            ],

            'city_id' => [
                'nullable',
                'integer',
                'exists:cities,id',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            /*
            |--------------------------------------------------------------------------
            | Client Address
            |--------------------------------------------------------------------------
            */
            'client_address' => [
                'nullable',
                'string',
                'max:1000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Team Members
            |--------------------------------------------------------------------------
            */
            'team_member_ids' => [
                'nullable',
                'array',
            ],

            'team_member_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Project Status
            |--------------------------------------------------------------------------
            */
            'status' => [
                'nullable',
                'string',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | Current Stage
            |--------------------------------------------------------------------------
            */
            'current_stage' => [
                'nullable',
                'string',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Dates
            |--------------------------------------------------------------------------
            */
            'start_date' => [
                'required',
                'date',
            ],

            'deadline_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
            ],

            /*
            |--------------------------------------------------------------------------
            | Project Amount / Commission
            |--------------------------------------------------------------------------
            */
            'commission_type' => [
                'nullable',
                'string',
                'max:50',
            ],

            'commission_value' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            /*
            |--------------------------------------------------------------------------
            | Contractor
            |--------------------------------------------------------------------------
            */
            'contractor_id' => [
                'nullable',
                'integer',
                Rule::exists('contractors', 'id')->where(
                    fn ($query) => $query->where('company_id', $companyId)
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Completion
            |--------------------------------------------------------------------------
            */
            'completion_percentage' => [
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Installation / Project Progress
            |--------------------------------------------------------------------------
            */
            'site_preparation_status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'fixing_welding_status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'testing_commissioning_status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'final_inspection_status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'client_handover_status' => [
                'nullable',
                'string',
                'max:50',
            ],

            'installation_completed_at' => [
                'nullable',
                'date',
            ],

            'installation_fail_reason' => [
                'nullable',
                'string',
                'max:2000',
            ],

        ], [
            'client_id.required' => 'client_id not available.',
            'client_id.exists' => 'client_id not available.',

            'lead_id.exists' => 'lead_id not available.',

            'name.required' => 'Project name is required.',
            'name.min' => 'Project name must be at least 2 characters.',
            'name.max' => 'Project name may not be greater than 191 characters.',

            // 'staff_id.required' => 'staff_id not available.',
            // 'staff_id.exists' => 'staff_id not available.',

            'state_id.exists' => 'state_id not available.',
            'city_id.exists' => 'city_id not available.',

            'team_member_ids.array' => 'team_member_ids must be an array.',
            'team_member_ids.*.exists' => 'One or more team members are invalid.',

            'contractor_id.exists' => 'contractor_id not available.',

            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',

            'deadline_date.required' => 'Due date is required.',
            'deadline_date.date' => 'Due date must be a valid date.',
            'deadline_date.after_or_equal' =>
                'Deadline date must be the same as or later than the start date.',

            'project_description.max' =>
                'Project description may not be greater than 2000 characters.',
        ])->validate();

        /*
        |--------------------------------------------------------------------------
        | Company-safe Client
        |--------------------------------------------------------------------------
        */
        $client = Client::query()
            ->where('company_id', $companyId)
            ->findOrFail((int) $validated['client_id']);

        /*
        |--------------------------------------------------------------------------
        | Company-safe Staff / Project Manager
        |--------------------------------------------------------------------------
        */
        // $staff = Staff::query()
        //     ->where('company_id', $companyId)
        //     ->findOrFail((int) $validated['staff_id']);

        /*
        |--------------------------------------------------------------------------
        | Optional Lead
        |--------------------------------------------------------------------------
        */
        $lead = null;

        if (!empty($validated['lead_id'])) {
            $lead = Lead::query()
                ->where('company_id', $companyId)
                ->findOrFail((int) $validated['lead_id']);
        }

        /*
        |--------------------------------------------------------------------------
        | Optional Contractor
        |--------------------------------------------------------------------------
        */
        $contractor = null;

        if (!empty($validated['contractor_id'])) {
            $contractor = Contractor::query()
                ->where('company_id', $companyId)
                ->findOrFail((int) $validated['contractor_id']);
        }

        /*
        |--------------------------------------------------------------------------
        | Create Project
        |--------------------------------------------------------------------------
        */
        $project = DB::transaction(function () use (
            $companyId,
            $client,
            $lead,
            $contractor,
            $validated
        ): Project {

            return Project::create([
                'company_id' => $companyId,

                /*
                * Relations
                */
                'lead_id' => $lead?->id,
                'contractor_id' => $contractor?->id,
                'project_manager_id' => null,

                /*
                * Basic Project Information
                */
                'name' => trim((string) $validated['name']),
                'client_name' => trim((string) $client->name),
                'description' => $validated['project_description'] ?? null,

                /*
                * Client / Project Location
                */
                'state_id' => $validated['state_id'] ?? null,
                'city_id' => $validated['city_id'] ?? null,
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,

                /*
                * Client Address
                */
                'client_address' => $validated['client_address'] ?? null,

                /*
                * Team
                */
                'team_member_ids' => $validated['team_member_ids'] ?? null,

                /*
                * Project Status
                *
                * If these are not provided, Project model booted()
                * will automatically set:
                *
                * status = on_track
                * current_stage = new-project
                */
                'status' => $validated['status'] ?? null,
                'current_stage' => $validated['current_stage'] ?? null,

                /*
                * Dates
                */
                'start_date' => $validated['start_date'],
                'end_date' => $validated['deadline_date'],

                /*
                * Commission
                */
                'commission_type' => $validated['commission_type'] ?? null,
                'commission_value' => $validated['commission_value'] ?? null,

                /*
                * Completion
                */
                'completion_percentage' =>
                    $validated['completion_percentage'] ?? null,

                /*
                * Installation / Progress
                */
                'site_preparation_status' =>
                    $validated['site_preparation_status'] ?? null,

                'fixing_welding_status' =>
                    $validated['fixing_welding_status'] ?? null,

                'testing_commissioning_status' =>
                    $validated['testing_commissioning_status'] ?? null,

                'final_inspection_status' =>
                    $validated['final_inspection_status'] ?? null,

                'client_handover_status' =>
                    $validated['client_handover_status'] ?? null,

                'installation_completed_at' =>
                    $validated['installation_completed_at'] ?? null,

                'installation_fail_reason' =>
                    $validated['installation_fail_reason'] ?? null,

            ])->fresh([
                'manager',
                'lead',
                'state',
                'city',
                'contractor',
            ]);
        });

        return response()->json([
            'message' => 'Project created successfully.',
            'data' => $project,
        ], 201);
    }

    public function stages(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Unauthenticated.'
        );

        $companyId = (int) $user->company_id;

        $stages = ProjectStage::query()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->orderBy('id')
            ->get([
                'id',
                'name',
                'color',
                'key',
                'order',
                'is_default',
            ]);

        return response()->json([
            'message' => 'Project stages retrieved successfully.',
            'data' => $stages,
        ]);
    }
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can access project records.');

        $search = trim((string) $request->query('search', ''));
        $isCompanyAdmin = (bool) $user?->isCompanyAdmin();

        $query = Project::query()
            ->when(
                $isCompanyAdmin,
                fn ($builder) => $builder->where('company_id', (int) $user->company_id),
                fn ($builder) => $builder->accessibleTo($user)->where('company_id', (int) $user->company_id)
            )
            ->orderBy('name')
            ->select(['id', 'uuid', 'name', 'client_name', 'reference_no', 'current_stage', 'status',  'completion_percentage', 'end_date']);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($builder) use ($like) {
                $builder->where('name', 'like', $like)
                    ->orWhere('reference_no', 'like', $like)
                    ->orWhere('client_name', 'like', $like);
            });
        }

        $projects = $query->get();
        $projectIds = $projects->pluck('id')->all();

        if ($projectIds === []) {
            return response()->json([
                'message' => 'Project records retrieved successfully.',
                'data' => $projects,
            ]);
        }

        $projectTasks = $this->groupTasksByProject((int) $user->company_id, $projectIds);
        $projectAmcs = $this->groupAmcsByProject((int) $user->company_id, $projectIds);
        $projectAmcIds = $projectAmcs
            ->flatten(1)
            ->pluck('id')
            ->all();

        $projectSupportTickets = $this->groupSupportTicketsByProject((int) $user->company_id, $projectIds, $projectAmcIds);

        $projects = $projects->map(function (Project $project) use ($projectTasks, $projectAmcs, $projectSupportTickets) {
            $projectId = (int) $project->id;

            $project->setRelation(
                'tasks',
                $projectTasks->get($projectId, collect())
                    ->map(fn (StaffTask $task) => $task->makeHidden(['reminder_weekdays']))
                    ->values()
            );
            $project->setRelation('amcs', $projectAmcs->get($projectId, collect())->values());
            $project->setRelation('supportTickets', $projectSupportTickets->get($projectId, collect())->values());

            return $project;    
        });

        return response()->json([
            'message' => 'Project records retrieved successfully.',
            'data' => $projects,
        ]);
    }

    public function show(Request $request, int $project): JsonResponse
    {
        $user = $request->user();

        $isCompanyAdmin = (bool) $user?->isCompanyAdmin();

        $projectQuery = Project::query()
            ->when(
                $isCompanyAdmin,
                fn ($builder) => $builder->where('company_id', (int) $user->company_id),
                fn ($builder) => $builder
                    ->accessibleTo($user)
                    ->where('company_id', (int) $user->company_id)
            )
            ->where('id', $project);

        $projectData = $projectQuery
            ->with(['manager'])
            ->firstOrFail();

        $projectId = (int) $projectData->id;
        $companyId = (int) $user->company_id;

        $projectTasks = $this->groupTasksByProject(
            $companyId,
            [$projectId]
        );

        $projectAmcs = $this->groupAmcsByProject(
            $companyId,
            [$projectId]
        );

        $projectAmcIds = $projectAmcs
            ->flatten(1)
            ->pluck('id')
            ->all();

        $projectSupportTickets = $this->groupSupportTicketsByProject(
            $companyId,
            [$projectId],
            $projectAmcIds
        );

        $projectData->setRelation(
            'tasks',
            $projectTasks
                ->get($projectId, collect())
                ->map(fn (StaffTask $task) => $task->makeHidden(['reminder_weekdays']))
                ->values()
        );

        $projectData->setRelation(
            'amcs',
            $projectAmcs
                ->get($projectId, collect())
                ->values()
        );

        $projectData->setRelation(
            'supportTickets',
            $projectSupportTickets
                ->get($projectId, collect())
                ->values()
        );

        return response()->json([
            'message' => 'Project record retrieved successfully.',
            'data' => $projectData,
        ]);
    }
    private function groupTasksByProject(int $companyId, array $projectIds): Collection
    {
        return StaffTask::query()
            ->where('company_id', $companyId)
            ->whereIn('project_id', $projectIds)
            ->with(['staff:id,name', 'updatedBy:id,name'])
            ->latest()
            ->get()
            ->groupBy(fn (StaffTask $task) => (int) $task->project_id);
    }

    private function groupAmcsByProject(int $companyId, array $projectIds): Collection
    {
        return Amc::query()
            ->where('company_id', $companyId)
            ->whereIn('project_id', $projectIds)
            ->with([
                'client:id,name,email,mobile,phone,company_id',
                'amcPlan:id,name,duration_months,price,company_id',
                'assignedStaff:id,name,contact_number,company_id',
                'supportTicket:id,ticket_number,subject,status,company_id,generated_from_amc_id,assigned_to,visit_date,project_id',
                'supportTicket.assignedUser:id,name,email',
            ])
            ->latest()
            ->get()
            ->groupBy(fn (Amc $amc) => (int) $amc->project_id);
    }

    private function groupSupportTicketsByProject(int $companyId, array $projectIds, array $amcIds): Collection
    {
        $ticketIds = collect();

        $ticketIds = $ticketIds
            ->merge(
                Ticket::query()
                    ->where('company_id', $companyId)
                    ->whereIn('project_id', $projectIds)
                    ->pluck('id')
            )
            ->merge(
                Ticket::query()
                    ->where('company_id', $companyId)
                    ->whereIn('generated_from_amc_id', $amcIds)
                    ->pluck('id')
            )
            ->merge(
                Amc::query()
                    ->where('company_id', $companyId)
                    ->whereIn('project_id', $projectIds)
                    ->whereNotNull('support_ticket_id')
                    ->pluck('support_ticket_id')
            )
            ->filter()
            ->unique()
            ->values();

        if ($ticketIds->isEmpty()) {
            return collect();
        }

        return Ticket::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $ticketIds->all())
            ->with([
                'assignedUser:id,name,email',
                'generatedFromAmc:id,contract_no,project_name,customer_name,client_id,project_id',
                'amc:id,contract_no,project_name,customer_name,client_id,project_id,support_ticket_id',
            ])
            ->latest()
            ->get()
            ->groupBy(function (Ticket $ticket) {
                $projectId = $ticket->project_id
                    ?? $ticket->generatedFromAmc?->project_id
                    ?? $ticket->amc?->project_id;

                return (int) ($projectId ?? 0);
            });
    }
}
