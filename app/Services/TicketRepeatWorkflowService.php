<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketRepeatHistory;
use App\Models\User;
use App\Services\TicketAssignmentHistoryService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class TicketRepeatWorkflowService
{
    public function canAutoAssignToPreviousStaff(Ticket $ticket): bool
    {
        $staff = $ticket->assignedUser;

        return (bool) ($staff && $staff->is_active && blank(data_get($staff, 'deleted_at')));
    }

    public function reopenAsRepeatComplaint(Ticket $ticket, User $actor, ?int $assignedTo = null, ?string $note = null): Ticket
    {
        $ticket->loadMissing(['assignedUser', 'repeatHistories']);

        $previousAssignedTo = $ticket->assigned_to ? (int) $ticket->assigned_to : null;
        $targetAssignedTo = $previousAssignedTo;

        if (! $this->canAutoAssignToPreviousStaff($ticket)) {
            if (! $assignedTo) {
                throw ValidationException::withMessages([
                    'assigned_to' => 'The previously assigned staff is unavailable. Please assign another staff member.',
                ]);
            }

            $targetAssignedTo = $assignedTo;
        }

        $previousStatus = $ticket->status;
        $repeatCount = ((int) $ticket->repeat_count) + 1;
        $repeatedAfterDays = $ticket->updated_at ? Carbon::parse($ticket->updated_at)->diffInDays(now()) : null;
        $reopenedAt = now();

        $ticket->forceFill([
            'status' => 'open',
            'board_stage' => Ticket::stageForStatus('open', $ticket->priority, $targetAssignedTo, $ticket->replies()->count(), null, (int) $ticket->company_id),
            'assigned_to' => $targetAssignedTo,
            'repeat_status' => true,
            'repeat_count' => $repeatCount,
            'repeated_after_days' => $repeatedAfterDays,
            'assigned_previous_staff_id' => $previousAssignedTo,
            'last_repeat_at' => $reopenedAt,
        ])->save();

        TicketRepeatHistory::create([
            'ticket_id' => $ticket->id,
            'reopened_by_user_id' => $actor->id,
            'previous_assigned_to' => $previousAssignedTo,
            'assigned_to' => $targetAssignedTo,
            'previous_status' => $previousStatus,
            'reopened_status' => 'open',
            'repeat_count' => $repeatCount,
            'repeated_after_days' => $repeatedAfterDays,
            'note' => $note,
            'repeated_at' => $reopenedAt,
        ]);

        app(TicketAssignmentHistoryService::class)->syncCurrentAssignment(
            $ticket->refresh(),
            $targetAssignedTo,
            $actor,
            'Repeat',
            $note
        );

        app(TicketAssignmentHistoryService::class)->recordRepeatHandled(
            $ticket->refresh(),
            $targetAssignedTo,
            $actor,
            $note
        );

        return $ticket->refresh();
    }
}
