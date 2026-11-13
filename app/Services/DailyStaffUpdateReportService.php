<?php

namespace App\Services;

use App\Models\Company;
use App\Models\DailyStaffUpdateReport;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\Staff;
use App\Models\StaffTask;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DailyStaffUpdateReportService
{
    public function generateForCompany(
        Company $company,
        Carbon $reportDate,
        ?User $actor = null,
        string $generationType = 'manual',
        bool $force = false
    ): array {
        $reportDate = $reportDate->copy()->startOfDay();

        $existingReport = DailyStaffUpdateReport::query()
            ->where('company_id', $company->id)
            ->whereDate('report_date', $reportDate->toDateString())
            ->first();

        if ($existingReport && ! $force) {
            return [
                'report' => $existingReport,
                'created' => false,
                'duplicate' => true,
                'message' => 'A report for ' . $reportDate->format('d/m/Y') . ' already exists.',
            ];
        }

        $payload = $this->buildPayload($company, $reportDate, $actor, $generationType);
        $reportText = $this->buildReportText($payload);
        $attributes = [
            'report_date' => $reportDate->toDateString(),
            'generated_by_id' => $actor?->id,
            'generated_at' => now(),
            'generation_type' => $generationType,
            'status' => 'success',
            'report_text' => $reportText,
            'report_payload' => $payload,
            'error_message' => null,
        ];

        try {
            $report = $existingReport
                ? tap($existingReport)->update($attributes)
                : DailyStaffUpdateReport::query()->create(array_merge($attributes, [
                    'company_id' => $company->id,
                ]));
        } catch (QueryException $exception) {
            if (! $force) {
                $report = DailyStaffUpdateReport::query()
                    ->where('company_id', $company->id)
                    ->whereDate('report_date', $reportDate->toDateString())
                    ->first();

                if ($report) {
                    return [
                        'report' => $report,
                        'created' => false,
                        'duplicate' => true,
                        'message' => 'A report for ' . $reportDate->format('d/m/Y') . ' already exists.',
                    ];
                }
            }

            throw $exception;
        }

        return [
            'report' => $report,
            'created' => true,
            'duplicate' => false,
            'message' => 'Daily staff update report generated successfully.',
        ];
    }

    public function historyIndexData(Company $company, Request $request): array
    {
        $timezone = $company->timezone ?: config('app.timezone');
        $search = trim((string) $request->query('search', ''));
        $sort = in_array($request->query('sort'), ['report_date', 'generated_at', 'generation_type', 'status'], true)
            ? (string) $request->query('sort')
            : 'report_date';
        $direction = strtolower((string) $request->query('direction')) === 'asc' ? 'asc' : 'desc';

        $reports = DailyStaffUpdateReport::query()
            ->where('company_id', $company->id)
            ->with(['generatedBy:id,name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($searchQuery) use ($search): void {
                    $searchQuery->where('report_date', 'like', '%' . $search . '%')
                        ->orWhere('generation_type', 'like', '%' . $search . '%')
                        ->orWhere('status', 'like', '%' . $search . '%')
                        ->orWhereHas('generatedBy', fn ($userQuery) => $userQuery->where('name', 'like', '%' . $search . '%'));
                });
            })
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $currentMonthStart = now($timezone)->startOfMonth();
        $currentMonthEnd = now($timezone)->endOfMonth();
        $currentMonthReports = DailyStaffUpdateReport::query()
            ->where('company_id', $company->id)
            ->whereBetween('report_date', [$currentMonthStart->toDateString(), $currentMonthEnd->toDateString()])
            ->orderBy('report_date')
            ->get()
            ->keyBy(fn (DailyStaffUpdateReport $report) => $report->report_date->toDateString());

        return [
            'company' => $company,
            'reports' => $reports,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
            'currentMonthTodayDateKey' => now($timezone)->toDateString(),
            'currentMonthLabel' => $currentMonthStart->format('F Y'),
            'currentMonthDates' => collect(range(1, $currentMonthStart->daysInMonth))
                ->map(function (int $day) use ($currentMonthStart): Carbon {
                    return $currentMonthStart->copy()->setDay($day)->startOfDay();
                })
                ->values(),
            'currentMonthReports' => $currentMonthReports,
        ];
    }

    public function buildPayload(
        Company $company,
        Carbon $reportDate,
        ?User $actor = null,
        string $generationType = 'manual'
    ): array {
        $activeStaff = Staff::query()
            ->where('company_id', $company->id)
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->with(['user:id,name,is_active', 'role:id,name,slug'])
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'company_id', 'user_id', 'name', 'role_id']);

        $staffIds = $activeStaff->pluck('id')->filter()->map(fn ($value) => (int) $value)->values()->all();
        $userIds = $activeStaff->pluck('user_id')->filter()->map(fn ($value) => (int) $value)->values()->all();

        $projects = $this->loadProjects($company->id, $userIds);
        $tasks = $this->loadTasks($company->id, $projects->pluck('id')->all(), $staffIds);
        $tickets = $this->loadTickets($company->id, $userIds, $reportDate);
        $leadActivities = $this->loadLeadActivities($company->id, $userIds, $reportDate);

        $tasksByProjectAndStaff = $tasks->groupBy('project_id')->map(function (Collection $projectTasks): Collection {
            return $projectTasks->groupBy('staff_id');
        });
        $ticketsByStaff = $tickets->groupBy('assigned_to');
        $leadActivitiesByStaff = $leadActivities->groupBy('user_id');

        $staffReports = $activeStaff->map(function (Staff $staff) use ($projects, $tasksByProjectAndStaff, $ticketsByStaff, $leadActivitiesByStaff): array {
            $staffUserId = (int) $staff->user_id;
            $staffProjects = $projects->filter(fn (Project $project) => $this->projectHasStaff($project, $staffUserId))
                ->sortBy('name')
                ->values();

            $projectRows = $staffProjects->map(function (Project $project) use ($tasksByProjectAndStaff, $staff): array {
                $tasksForProject = $tasksByProjectAndStaff->get($project->id, collect())
                    ->get($staff->id, collect())
                    ->sortBy('task_name')
                    ->values()
                    ->map(fn (StaffTask $task) => $this->formatTaskRow($task))
                    ->all();

                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'tasks' => $tasksForProject,
                ];
            })->all();

            $ticketRows = ($ticketsByStaff->get($staff->user_id, collect()) ?? collect())
                ->sortBy(function (Ticket $ticket): string {
                    return sprintf(
                        '%s|%s',
                        $ticket->visit_date?->toDateString() ?? $ticket->created_at?->toDateString() ?? '',
                        strtolower((string) $ticket->subject)
                    );
                })
                ->values()
                ->map(fn (Ticket $ticket) => $this->formatTicketRow($ticket))
                ->all();

            $activityRows = ($leadActivitiesByStaff->get($staff->user_id, collect()) ?? collect())
                ->sortBy(fn (LeadActivity $activity) => $activity->occurred_at ?? $activity->created_at)
                ->values()
                ->map(fn (LeadActivity $activity) => $this->formatLeadActivityRow($activity))
                ->all();

            return [
                'id' => $staff->id,
                'name' => $this->staffDisplayName($staff),
                'employee_id' => $staff->employee_id,
                'projects' => $projectRows,
                'support_tickets' => $ticketRows,
                'lead_activities' => $activityRows,
            ];
        })->values()->all();

        return [
            'date' => $reportDate->toDateString(),
            'date_human' => $reportDate->format('d/m/Y'),
            'generated_at' => now()->toIso8601String(),
            'generated_by' => [
                'id' => $actor?->id,
                'name' => $actor?->name ?? 'System',
            ],
            'generation_type' => $generationType,
            'summary_message' => $activeStaff->isEmpty() ? 'No active staff found.' : null,
            'staff_count' => $activeStaff->count(),
            'project_count' => $projects->count(),
            'ticket_count' => $tickets->count(),
            'lead_activity_count' => $leadActivities->count(),
            'staff' => $staffReports,
        ];
    }

    public function buildReportText(array $payload): string
    {
        $lines = [];
        $lines[] = 'Date: ' . ($payload['date_human'] ?? now()->format('d/m/Y'));
        $lines[] = '';

        if (! empty($payload['summary_message'])) {
            $lines[] = (string) $payload['summary_message'];
            $lines[] = '';
        }

        foreach (($payload['staff'] ?? []) as $staff) {
            $lines[] = 'Staff : ' . ($staff['name'] ?? 'Unknown');
            $lines[] = 'Projects';

            if (empty($staff['projects'])) {
                $lines[] = '    No Assigned Projects';
            } else {
                foreach ($staff['projects'] as $project) {
                    $lines[] = '    ' . ($project['name'] ?? 'Project');

                    if (empty($project['tasks'])) {
                        $lines[] = '        No Tasks';
                        continue;
                    }

                    foreach ($project['tasks'] as $task) {
                        $label = 'Task : ' . ($task['name'] ?? 'Task');
                        if (! empty($task['completed_date'])) {
                            $label .= ' | Completed: ' . $task['completed_date'];
                        }

                        $lines[] = '        ' . $this->dotLeader($label, (string) ($task['status_label'] ?? ''));
                    }
                }
            }

            $lines[] = 'Support Tickets';

            if (empty($staff['support_tickets'])) {
                $lines[] = '    No Support Tickets';
            } else {
                foreach ($staff['support_tickets'] as $ticket) {
                    $label = 'Ticket : ' . ($ticket['name'] ?? 'Ticket');
                    if (! empty($ticket['ticket_number'])) {
                        $label .= ' (' . $ticket['ticket_number'] . ')';
                    }

                    $lines[] = '    ' . $this->dotLeader($label, (string) ($ticket['status_label'] ?? ''));
                }
            }

            $lines[] = 'Lead Activities';

            if (empty($staff['lead_activities'])) {
                $lines[] = '    No Lead Activities';
            } else {
                foreach ($staff['lead_activities'] as $activity) {
                    $leadName = $activity['lead_name'] ? " [{$activity['lead_name']}]" : '';
                    $label = 'Activity : ' . ($activity['description'] ?? 'Lead activity') . $leadName;
                    $statusLabel = $activity['type_label'] ?? 'Activity';

                    $lines[] = '    ' . $this->dotLeader($label, $statusLabel);
                }
            }

            $lines[] = '';
            $lines[] = '------------------------------------------------------------';
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    private function loadProjects(int $companyId, array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return Project::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($userIds): void {
                foreach ($userIds as $userId) {
                    $query->orWhere('project_manager_id', $userId)
                        ->orWhereJsonContains('team_member_ids', $userId)
                        ->orWhereJsonContains('team_member_ids', (string) $userId);
                }
            })
            ->orderBy('name')
            ->get(['id', 'company_id', 'name', 'project_manager_id', 'team_member_ids']);
    }

    private function loadTasks(int $companyId, array $projectIds, array $staffIds): Collection
    {
        if ($projectIds === [] || $staffIds === []) {
            return collect();
        }

        return StaffTask::query()
            ->where('company_id', $companyId)
            ->whereIn('project_id', $projectIds)
            ->whereIn('staff_id', $staffIds)
            ->with([
                'statusUpdates' => function ($query): void {
                    $query->select(['id', 'company_id', 'staff_task_id', 'user_id', 'status', 'comment', 'notes', 'status_date', 'reminder_date', 'completed_at', 'locked', 'created_at', 'updated_at'])
                        ->orderByDesc('completed_at')
                        ->orderByDesc('id');
                },
            ])
            ->orderBy('task_name')
            ->get(['id', 'company_id', 'staff_id', 'project_id', 'task_name', 'status', 'updated_at', 'created_at']);
    }

    private function loadTickets(int $companyId, array $userIds, Carbon $reportDate): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return Ticket::query()
            ->where('company_id', $companyId)
            ->whereIn('assigned_to', $userIds)
            ->whereDate('visit_date', $reportDate->toDateString())
            ->with(['assignedUser:id,name', 'project:id,name'])
            ->orderBy('visit_date')
            ->orderBy('subject')
            ->get(['id', 'company_id', 'assigned_to', 'project_id', 'ticket_number', 'subject', 'status', 'visit_date', 'created_at']);
    }

    private function loadLeadActivities(int $companyId, array $userIds, Carbon $reportDate): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        return LeadActivity::query()
            ->whereIn('lead_activities.user_id', $userIds)
            ->where(function ($query) use ($reportDate): void {
                $query->whereDate('lead_activities.occurred_at', $reportDate->toDateString())
                    ->orWhere(function ($fallback) use ($reportDate): void {
                        $fallback->whereNull('lead_activities.occurred_at')
                            ->whereDate('lead_activities.created_at', $reportDate->toDateString());
                    });
            })
            ->whereHas('lead', fn ($leadQuery) => $leadQuery->where('company_id', $companyId))
            ->with(['lead:id,uuid,client_name'])
            ->orderBy('lead_activities.occurred_at')
            ->orderBy('lead_activities.id')
            ->get(['id', 'lead_id', 'user_id', 'type', 'description', 'occurred_at', 'created_at']);
    }

    private function projectHasStaff(Project $project, int $userId): bool
    {
        if ((int) $project->project_manager_id === $userId) {
            return true;
        }

        $teamMembers = collect($project->team_member_ids ?? [])
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->values()
            ->all();

        return in_array($userId, $teamMembers, true);
    }

    private function staffDisplayName(Staff $staff): string
    {
        $name = trim((string) $staff->name);

        if ($name !== '') {
            return $name;
        }

        return trim((string) $staff->user?->name) ?: 'Staff #' . $staff->id;
    }

    private function formatTaskRow(StaffTask $task): array
    {
        $statusLabel = $this->taskStatusLabel($task->status);
        $completedDate = $this->taskCompletedDate($task);

        return [
            'id' => $task->id,
            'name' => $task->task_name,
            'status' => $task->status,
            'status_label' => $statusLabel,
            'status_color' => $this->taskStatusColor($task->status),
            'completed_date' => $completedDate?->format('d/m/Y'),
        ];
    }

    private function formatTicketRow(Ticket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'name' => $ticket->subject ?: $ticket->ticket_number ?: 'Ticket',
            'ticket_number' => $ticket->ticket_number,
            'status' => $ticket->status,
            'status_label' => $this->ticketStatusLabel($ticket->status),
            'status_color' => $this->ticketStatusColor($ticket->status),
        ];
    }

    private function formatLeadActivityRow(LeadActivity $activity): array
    {
        $time = ($activity->occurred_at ?? $activity->created_at)?->format('h:i A');

        return [
            'id' => $activity->id,
            'lead_id' => $activity->lead_id,
            'lead_key' => $activity->lead?->uuid ?: $activity->lead_id,
            'lead_name' => $activity->lead?->client_name ?: ('Lead #' . $activity->lead_id),
            'type' => $activity->type,
            'type_label' => $this->leadActivityTypeLabel($activity->type),
            'type_color' => $this->leadActivityTypeColor($activity->type),
            'description' => $activity->description,
            'time' => $time,
        ];
    }

    private function leadActivityTypeLabel(?string $type): string
    {
        return match ($type) {
            'status_change' => 'Status Change',
            'note' => 'Comment / Note',
            'appointment' => 'Appointment',
            'follow_up' => 'Follow Up',
            'quotation' => 'Quotation',
            'document' => 'Document',
            'survey' => 'Survey',
            'call' => 'Call',
            'meeting' => 'Meeting',
            'site_visit' => 'Site Visit',
            'email' => 'Email',
            default => Str::of((string) $type)->replace('_', ' ')->title()->toString() ?: 'Activity',
        };
    }

    private function leadActivityTypeColor(?string $type): string
    {
        return match ($type) {
            'status_change' => 'blue',
            'appointment' => 'indigo',
            'note' => 'amber',
            'quotation' => 'emerald',
            'survey', 'document' => 'purple',
            'follow_up' => 'rose',
            default => 'slate',
        };
    }

    private function taskCompletedDate(StaffTask $task): ?Carbon
    {
        $statusUpdate = $task->statusUpdates
            ->first(fn ($update) => in_array($update->status, ['done', 'cancelled'], true));

        if ($statusUpdate?->completed_at) {
            return $statusUpdate->completed_at;
        }

        if ($statusUpdate?->created_at) {
            return $statusUpdate->created_at;
        }

        if (in_array($task->status, ['done', 'cancelled'], true)) {
            return $task->updated_at ?? $task->created_at;
        }

        return null;
    }

    private function taskStatusLabel(?string $status): string
    {
        return match ($status) {
            'done' => 'Done',
            'in_progress' => 'In Progress',
            'cancelled' => 'Cancelled',
            default => 'Pending',
        };
    }

    private function taskStatusColor(?string $status): string
    {
        return match ($status) {
            'done' => 'emerald',
            'in_progress' => 'blue',
            'cancelled' => 'rose',
            default => 'amber',
        };
    }

    private function ticketStatusLabel(?string $status): string
    {
        return match ($status) {
            'closed' => 'Closed',
            'resolved' => 'Done',
            'in_progress' => 'In Progress',
            'hold', 'reschedule' => 'Blocker',
            'answered' => 'Pending',
            'open' => 'Open',
            default => Str::of((string) $status)->replace('_', ' ')->title()->toString(),
        };
    }

    private function ticketStatusColor(?string $status): string
    {
        return match ($status) {
            'closed', 'resolved' => 'emerald',
            'in_progress' => 'blue',
            'hold', 'reschedule' => 'rose',
            'answered' => 'amber',
            'open' => 'slate',
            default => 'slate',
        };
    }

    private function dotLeader(string $label, string $value, int $width = 76): string
    {
        $label = trim($label);
        $value = trim($value);
        $dots = max(6, $width - mb_strlen($label) - mb_strlen($value));

        return $label . ' ' . str_repeat('.', $dots) . ' ' . ($value !== '' ? $value : '-');
    }
}
