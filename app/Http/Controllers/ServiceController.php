<?php

namespace App\Http\Controllers;

use App\Models\Amc;
use App\Models\ServiceStage;
use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        $filters = $this->serviceFilters($request);
        $assignees = $this->serviceAssignees($user->company_id);

        $ticketsQuery = $this->applyServiceFilters(
            Ticket::query()
            ->where('company_id', $user->company_id)
            ->whereIn('type', ['complain', 'support'])
            ->with([
                'assignedUser:id,name',
                'user:id,name',
                'amc:id,support_ticket_id,contract_no,project_name',
                'generatedFromAmc:id,contract_no,project_name',
            ])
            ->withCount('replies')
            ->latest(),
            $filters
        );

        if (! $user->isCompanyAdmin()) {
            $ticketsQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);

                if (\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments')) {
                    $query->orWhereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id));
                }
            });
        }

        $tickets = $ticketsQuery->get();

        $stages = ServiceStage::orderedForCompany((int) $user->company_id);

        $ticketsByStage = $tickets->groupBy(fn (Ticket $ticket) => Ticket::stageForStatus(
            $ticket->status ?: 'open',
            $ticket->priority,
            $ticket->assigned_to ? (int) $ticket->assigned_to : null,
            (int) $ticket->replies_count,
            $ticket->board_stage,
            (int) $user->company_id
        ));

        $serviceSummary = [
            'total_tickets' => $tickets->count(),
            'open_workload' => $tickets->filter(fn (Ticket $ticket) => ! in_array($ticket->status, ['resolved', 'closed'], true))->count(),
            'urgent_cases' => $tickets->where('priority', 'urgent')->count(),
            'amc_linked' => $tickets->filter(fn (Ticket $ticket) => $ticket->generatedFromAmc !== null || $ticket->amc !== null)->count(),
        ];

        $amcSummary = [
            'total_contracts' => Amc::query()->where('company_id', $user->company_id)->count(),
            'on_call_contracts' => Amc::query()->where('company_id', $user->company_id)->where('service_mode', 'on_call')->count(),
        ];

        return view('services.index', [
            'stages' => $stages,
            'ticketsByStage' => $ticketsByStage,
            'serviceSummary' => $serviceSummary,
            'amcSummary' => $amcSummary,
            'filters' => $filters,
            'assignees' => $assignees,
        ]);
    }

    private function serviceFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'type' => ['nullable', 'string', Rule::in(['complain', 'support'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', auth()->user()?->company_id)),
            ],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'type' => $validated['type'] ?? null,
            'priority' => $validated['priority'] ?? null,
            'assigned_to' => isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
            'start_date' => $validated['start_date'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
        ];
    }

    private function applyServiceFilters($query, array $filters)
    {
        $search = $filters['search'] ?? null;
        $type = $filters['type'] ?? null;
        $priority = $filters['priority'] ?? null;
        $assignedTo = $filters['assigned_to'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $searchDate = null;

        if ($search) {
            try {
                $searchDate = Carbon::parse($search)->toDateString();
            } catch (\Throwable $exception) {
                $searchDate = null;
            }
        }

        return $query
            ->when($type, fn ($builder) => $builder->where('type', $type))
            ->when($priority, fn ($builder) => $builder->where('priority', $priority))
            ->when($assignedTo, function ($builder) use ($assignedTo) {
                $builder->where(function ($q) use ($assignedTo) {
                    $q->where('assigned_to', $assignedTo);
                    if (\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments')) {
                        $q->orWhereHas('assignedUsers', fn ($sub) => $sub->where('users.id', $assignedTo));
                    }
                });
            })
            ->when($startDate || $endDate, function ($builder) use ($startDate, $endDate) {
                if ($startDate) {
                    $builder->whereDate('visit_date', '>=', $startDate);
                }

                if ($endDate) {
                    $builder->whereDate('visit_date', '<=', $endDate);
                }
            })
            ->when($search, function ($builder) use ($search, $searchDate) {
                $like = '%' . $search . '%';

                $builder->where(function ($searchQuery) use ($like, $searchDate) {
                    $searchQuery->where('ticket_number', 'like', $like)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('service_notes', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_email', 'like', $like)
                        ->orWhere('customer_contact_number', 'like', $like)
                        ->orWhere('customer_address', 'like', $like)
                        ->orWhere('priority', 'like', $like)
                        ->orWhere('status', 'like', $like)
                        ->orWhere('type', 'like', $like)
                        ->orWhere('board_stage', 'like', $like)
                        ->orWhereHas('assignedUser', fn ($assignedUserQuery) => $assignedUserQuery->where('name', 'like', $like))
                        ->when(\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments'), fn ($q) => $q->orWhereHas('assignedUsers', fn ($assignedUsersQuery) => $assignedUsersQuery->where('name', 'like', $like)))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $like))
                        ->orWhereHas('referencedUser', fn ($userQuery) => $userQuery->where('name', 'like', $like))
                        ->orWhereHas('amc', fn ($amcQuery) => $amcQuery->where('contract_no', 'like', $like)->orWhere('project_name', 'like', $like))
                        ->orWhereHas('generatedFromAmc', fn ($amcQuery) => $amcQuery->where('contract_no', 'like', $like)->orWhere('project_name', 'like', $like));

                    if ($searchDate) {
                        $searchQuery->orWhereDate('visit_date', $searchDate);
                    }
                });
            });
    }

    private function serviceAssignees(int $companyId)
    {
        return \App\Models\User::query()
            ->where('company_id', $companyId)
            ->activeAssigneeOrAdmin()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
