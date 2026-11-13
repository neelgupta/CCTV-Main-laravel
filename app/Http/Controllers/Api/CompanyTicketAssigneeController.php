<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CompanyTicketAssigneeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasPermission('support.view') || $user->hasPermission('support.create') || $user->isCompanyAdmin()), 403, 'You are not allowed to view ticket assignees.');

        $validated = $request->validate([
            'visit_date' => ['nullable', 'date'],
        ]);

        $visitDate = isset($validated['visit_date'])
            ? Carbon::parse($validated['visit_date'])->toDateString()
            : null;

        $assignees = User::query()
            ->where('company_id', $user->company_id)
            ->activeAssigneeOrAdmin()
            ->with([
                'staff:id,user_id,employee_id,department,contact_number,company_id',
                'roleRecord:id,name,slug',
            ])
            ->withCount([
                'assignedTickets as active_ticket_count' => function (Builder $ticketQuery) use ($visitDate) {
                    $ticketQuery->whereIn('status', ['open', 'pending', 'assigned', 'in_progress'])
                        ->when($visitDate, fn (Builder $builder) => $builder->whereDate('visit_date', $visitDate));
                },
                'assignedTickets as support_ticket_count' => function (Builder $ticketQuery) use ($visitDate) {
                    $ticketQuery->where('type', 'support')
                        ->when($visitDate, fn (Builder $builder) => $builder->whereDate('visit_date', $visitDate));
                },
            ])
            ->orderBy('name')
            ->get()
            ->map(function (User $assignee) {
                return [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                    'email' => $assignee->email,
                    'role' => $assignee->normalizedRole(),
                    'active_ticket_count' => (int) ($assignee->active_ticket_count ?? 0),
                    'support_ticket_count' => (int) ($assignee->support_ticket_count ?? 0),
                    'staff' => $assignee->staff ? [
                        'id' => $assignee->staff->id,
                        'employee_id' => $assignee->staff->employee_id,
                        'department' => $assignee->staff->department,
                        'contact_number' => $assignee->staff->contact_number,
                    ] : null,
                ];
            })
            ->values();

        return response()->json([
            'message' => 'Ticket assignees retrieved successfully.',
            'data' => $assignees,
        ]);
    }
}
