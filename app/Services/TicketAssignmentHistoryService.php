<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketAssignmentHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketAssignmentHistoryService
{
    public function syncCurrentAssignments(
        Ticket $ticket,
        array $staffIds,
        ?User $actor = null,
        string $assignmentType = 'Manual',
        ?string $note = null
    ): Collection {
        return DB::transaction(function () use ($ticket, $staffIds, $actor, $assignmentType, $note): Collection {
            $ticket = $ticket->fresh();
            $targetStaffIds = collect($staffIds)
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $currentHistories = TicketAssignmentHistory::query()
                ->where('ticket_id', $ticket->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->get();

            $now = now();
            $currentStaffIds = $currentHistories->pluck('staff_id')->filter()->map(fn ($id) => (int) $id)->all();

            // Release any staff not in the target list
            foreach ($currentHistories as $history) {
                if (! in_array((int) $history->staff_id, $targetStaffIds, true)) {
                    $history->update([
                        'released_at' => $now,
                        'is_current' => false,
                    ]);
                } elseif (filled($note) && $history->note !== $note) {
                    $history->update(['note' => $note]);
                }
            }

            // Create new histories for newly added staff
            foreach ($targetStaffIds as $staffId) {
                if (! in_array($staffId, $currentStaffIds, true)) {
                    TicketAssignmentHistory::create([
                        'ticket_id' => $ticket->id,
                        'staff_id' => $staffId,
                        'assigned_at' => $now,
                        'released_at' => null,
                        'assigned_by' => $actor?->id,
                        'assignment_type' => $assignmentType,
                        'repeat_count_handled' => 0,
                        'is_current' => true,
                        'note' => $note,
                    ]);
                }
            }

            return TicketAssignmentHistory::query()
                ->where('ticket_id', $ticket->id)
                ->where('is_current', true)
                ->get();
        });
    }

    public function syncCurrentAssignment(
        Ticket $ticket,
        ?int $staffId,
        ?User $actor = null,
        string $assignmentType = 'Manual',
        ?string $note = null
    ): ?TicketAssignmentHistory {
        $staffIds = $staffId ? [$staffId] : [];
        $histories = $this->syncCurrentAssignments($ticket, $staffIds, $actor, $assignmentType, $note);

        return $histories->first();
    }

    public function recordRepeatHandled(
        Ticket $ticket,
        ?int $staffId = null,
        ?User $actor = null,
        ?string $note = null
    ): ?TicketAssignmentHistory {
        $staffId = $staffId ?? ($ticket->assigned_to ? (int) $ticket->assigned_to : null);

        if (! $staffId) {
            return null;
        }

        return DB::transaction(function () use ($ticket, $staffId, $actor, $note): TicketAssignmentHistory {
            $history = TicketAssignmentHistory::query()
                ->where('ticket_id', $ticket->id)
                ->where('staff_id', $staffId)
                ->where('is_current', true)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if (! $history) {
                $history = TicketAssignmentHistory::create([
                    'ticket_id' => $ticket->id,
                    'staff_id' => $staffId,
                    'assigned_at' => now(),
                    'released_at' => null,
                    'assigned_by' => $actor?->id,
                    'assignment_type' => 'Repeat',
                    'repeat_count_handled' => 0,
                    'is_current' => true,
                    'note' => $note,
                ]);
            }

            $history->increment('repeat_count_handled');

            if (filled($note) && $history->note !== $note) {
                $history->update(['note' => $note]);
            }

            return $history->refresh();
        });
    }

    public function ticketTimeline(Ticket $ticket): Collection
    {
        return TicketAssignmentHistory::query()
            ->where('ticket_id', $ticket->id)
            ->with(['staff:id,name', 'assignedBy:id,name'])
            ->orderBy('assigned_at')
            ->orderBy('id')
            ->get();
    }

    public function staffSummary(int $companyId, int $staffUserId): array
    {
        $currentTickets = Ticket::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($staffUserId) {
                $query->where('assigned_to', $staffUserId);
                if (Schema::hasTable('ticket_user_assignments')) {
                    $query->orWhereHas('assignedUsers', fn ($q) => $q->whereKey($staffUserId));
                }
            })
            ->get(['id', 'status']);

        $histories = TicketAssignmentHistory::query()
            ->whereHas('ticket', fn ($query) => $query->where('company_id', $companyId))
            ->where('staff_id', $staffUserId)
            ->get(['id', 'ticket_id', 'assigned_at', 'released_at', 'assignment_type', 'repeat_count_handled', 'is_current']);

        $reassignedTickets = $histories->whereNotNull('released_at')->count();
        $receivedTickets = $histories->filter(fn (TicketAssignmentHistory $history) => in_array($history->assignment_type, ['Manual', 'Repeat', 'Auto'], true))->count();
        $repeatComplaints = (int) $histories->sum('repeat_count_handled');

        return [
            'assigned_tickets' => $currentTickets->count(),
            'current_open_tickets' => $currentTickets->whereIn('status', ['open', 'pending', 'assigned', 'in_progress'])->count(),
            'completed_tickets' => $currentTickets->whereIn('status', ['closed', 'resolved'])->count(),
            'historical_tickets' => $histories->count(),
            'reassigned_tickets' => $reassignedTickets,
            'received_tickets' => $receivedTickets,
            'repeat_complaints_handled' => $repeatComplaints,
        ];
    }

    public function companySummary(int $companyId): array
    {
        $histories = TicketAssignmentHistory::query()
            ->whereHas('ticket', fn ($query) => $query->where('company_id', $companyId))
            ->with(['ticket:id,company_id,ticket_number,subject,assigned_to,status,repeat_count', 'staff:id,name'])
            ->get();

        return [
            'total_reassignments' => $histories->whereNotNull('released_at')->count(),
            'average_repeat_count_before_reassignment' => $histories
                ->filter(fn (TicketAssignmentHistory $history) => $history->released_at !== null)
                ->avg('repeat_count_handled') ?: 0,
            'most_reassigned_tickets' => $histories
                ->groupBy('ticket_id')
                ->map(fn (Collection $rows) => [
                    'ticket' => $rows->first()->ticket,
                    'reassignments' => $rows->whereNotNull('released_at')->count(),
                    'current_owner' => $rows->where('is_current', true)->first()?->staff,
                    'previous_owners' => $rows->pluck('staff')->filter()->unique('id')->values(),
                ])
                ->sortByDesc('reassignments')
                ->take(10)
                ->values(),
            'most_repeated_tickets' => Ticket::query()
                ->where('company_id', $companyId)
                ->whereIn('type', ['support', 'complain'])
                ->orderByDesc('repeat_count')
                ->limit(10)
                ->get(['id', 'ticket_number', 'subject', 'assigned_to', 'repeat_count', 'status'])
                ->map(fn (Ticket $ticket) => [
                    'ticket' => $ticket,
                    'current_owner' => $ticket->assignedUser,
                ])
                ->values(),
            'employee_repeat_rows' => $histories
                ->groupBy('staff_id')
                ->map(function (Collection $rows) {
                    $staff = $rows->first()?->staff;

                    return [
                        'staff' => $staff,
                        'repeat_complaints' => (int) $rows->sum('repeat_count_handled'),
                        'assigned_tickets' => $rows->count(),
                        'reassigned_tickets' => $rows->where('released_at', '!=', null)->count(),
                    ];
                })
                ->sortByDesc('repeat_complaints')
                ->values(),
        ];
    }

    public function currentAssignmentForTicket(Ticket $ticket): ?TicketAssignmentHistory
    {
        return TicketAssignmentHistory::query()
            ->where('ticket_id', $ticket->id)
            ->where('is_current', true)
            ->latest('id')
            ->first();
    }
}
