<?php

namespace App\Services;

use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\ActivityLog;
use App\Models\CalendarEvent;
use App\Models\DashboardNotification;
use App\Models\Lead;
use App\Models\Project;
use App\Models\StaffAttendance;
use App\Models\StaffTask;
use App\Models\Ticket;
use App\Models\User;
use App\Services\FirebasePushService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DashboardNotificationService
{
    public function buildSidebar(User $user): array
    {
        if ($user->isSuperAdmin() || ! $user->company_id) {
            return [
                'unread_count' => 0,
                'activity_notifications' => collect(),
                'today_schedule' => collect(),
                'task_reminders' => collect(),
                'amc_reminders' => collect(),
                'total_count' => 0,
                'has_items' => false,
            ];
        }

        $today = Carbon::today();
        $companyId = (int) $user->company_id;
        $userId = (int) $user->id;
        $activityNotifications = $this->activityNotifications($user);

        [$scheduleItems, $reminderItems] = $user->isCompanyAdmin()
            ? $this->companySidebarItems($user, $userId, $companyId, $today)
            : $this->staffSidebarItems($user, $userId, $companyId, $today);

        $taskReminderItems = $scheduleItems
            ->where('source_type', 'staff_task')
            ->values();

        $scheduleItems = $scheduleItems
            ->reject(fn (array $item) => ($item['source_type'] ?? null) === 'staff_task')
            ->values();

        $notifications = $activityNotifications
            ->concat($this->blockerNotifications($user))
            ->concat($scheduleItems)
            ->concat($taskReminderItems)
            ->concat($reminderItems)
            ->sortBy(fn (array $item) => $this->notificationSortKey($item))
            ->values();

        return [
            'unread_count' => $notifications->where('status', 'unread')->count(),
            'activity_notifications' => $activityNotifications
                ->sortBy(fn (array $item) => $this->activityNotificationSortKey($item))
                ->values(),
            'blocker_notifications' => $this->blockerNotifications($user)
                ->sortBy(fn (array $item) => $this->notificationSortKey($item))
                ->values(),
            'today_schedule' => $scheduleItems
                ->sortBy(fn (array $item) => $this->notificationSortKey($item))
                ->values(),
            'task_reminders' => $taskReminderItems
                ->sortBy(fn (array $item) => $this->notificationSortKey($item))
                ->values(),
            'amc_reminders' => $reminderItems
                ->sortBy(fn (array $item) => $this->notificationSortKey($item))
                ->values(),
            'total_count' => $notifications->count(),
            'has_items' => $notifications->isNotEmpty(),
        ];
    }

    public function markAllAsRead(User $user): void
    {
        if (! $user->company_id || $user->isSuperAdmin()) {
            return;
        }

        DashboardNotification::query()
            ->forUser($user)
            ->whereIn('status', ['unread', 'archived'])
            ->update([
                'status' => 'read',
                'read_at' => now(),
                'archived_at' => null,
                'updated_at' => now(),
            ]);
    }

    public function recordActivityNotification(User $user, string $module, string $action, string $routeName, int $activityLogId): void
    {
        if ($user->isSuperAdmin() || ! $user->company_id) {
            return;
        }

        $moduleKey = $this->normalizeNotificationModule($module);
        $activityLog = ActivityLog::query()->find($activityLogId);
        $title = $this->normalizeNotificationTitle($this->buildActivityTitle($module, $action, $routeName, $activityLog));

        $notification = DashboardNotification::updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_key' => 'activity-log-' . $activityLogId,
            ],
            [
                'company_id' => (int) $user->company_id,
                'category' => 'activity',
                'source_type' => 'activity_log',
                'source_id' => (string) $activityLogId,
                'title' => $title,
                'message' => null,
                'url' => $this->resolveActivityUrl($routeName),
                'notification_date' => now()->toDateString(),
                'status' => 'unread',
                'read_at' => null,
                'archived_at' => null,
                'payload' => [
                    'module' => $moduleKey ?? $module,
                    'action' => $action,
                    'route' => $routeName,
                    'activity_log_id' => $activityLogId,
                ],
            ]
        );

        if ($notification->wasRecentlyCreated) {
            try {
                app(FirebasePushService::class)->sendDashboardNotification($notification, $moduleKey);
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }
    }

    public function notifyQrComplaintCreated(Ticket $ticket): void
    {
        if (! $ticket->company_id) {
            return;
        }

        $admins = User::query()
            ->where('company_id', (int) $ticket->company_id)
            ->get()
            ->filter(fn (User $user) => $user->isCompanyAdmin() || $user->isSuperAdmin());

        foreach ($admins as $admin) {
            $notification = DashboardNotification::updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'notification_key' => 'qr-complaint-ticket-' . $ticket->id,
                ],
                [
                    'company_id' => (int) $ticket->company_id,
                    'category' => 'activity',
                    'source_type' => 'ticket',
                    'source_id' => (string) $ticket->id,
                    'title' => 'QR Complaint Created',
                    'message' => trim(($ticket->ticket_number ?: 'Ticket') . ' | ' . ($ticket->customer_name ?: 'Customer')),
                    'url' => route('tickets.show', $ticket),
                    'notification_date' => now()->toDateString(),
                    'status' => 'unread',
                    'read_at' => null,
                    'archived_at' => null,
                    'payload' => [
                        'type' => 'ticket',
                        'ticket_id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'source' => 'qr_complaint',
                    ],
                ]
            );

            if ($notification->wasRecentlyCreated) {
                try {
                    app(FirebasePushService::class)->sendDashboardNotification($notification, 'ticket');
                } catch (\Throwable $throwable) {
                    report($throwable);
                }
            }
        }
    }

    public function notifyLeadAssigned(Lead $lead): void
    {
        if (! $lead->company_id || ! $lead->assigned_to) {
            return;
        }

        $this->notifyLeadAssignedToUsers($lead, [(int) $lead->assigned_to]);
    }

    public function notifyLeadAssignedToUsers(Lead $lead, array $userIds): void
    {
        if (! $lead->company_id || $userIds === []) {
            return;
        }

        $assignees = User::query()->whereIn('id', $userIds)->get();

        foreach ($assignees as $assignee) {
            if ((int) $assignee->id === (int) auth()->id()) {
                continue;
            }

            $notification = DashboardNotification::updateOrCreate(
                [
                    'user_id' => $assignee->id,
                    'notification_key' => 'lead-assigned-' . $lead->id . '-' . $assignee->id . '-' . $lead->updated_at?->timestamp,
                ],
                [
                    'company_id' => (int) $lead->company_id,
                    'category' => 'activity',
                    'source_type' => 'lead',
                    'source_id' => (string) $lead->id,
                    'title' => 'You have been assigned to Lead (' . $lead->client_name . ')',
                    'message' => 'You have been assigned to lead ' . $lead->client_name,
                    'url' => route('leads.show', $lead),
                    'notification_date' => now()->toDateString(),
                    'status' => 'unread',
                    'read_at' => null,
                    'archived_at' => null,
                    'payload' => [
                        'type' => 'lead_assigned',
                        'lead_id' => $lead->id,
                    ],
                ]
            );

            if ($notification->wasRecentlyCreated) {
                try {
                    app(FirebasePushService::class)->sendDashboardNotification($notification, 'lead');
                } catch (\Throwable $throwable) {
                    report($throwable);
                }
            }
        }
    }

    public function notifyProjectManagerAssigned(Project $project): void
    {
        if (! $project->company_id || ! $project->project_manager_id) {
            return;
        }

        $assignee = User::query()->find($project->project_manager_id);

        if (! $assignee || (int) $assignee->id === (int) auth()->id()) {
            return;
        }

        $notification = DashboardNotification::updateOrCreate(
            [
                'user_id' => $assignee->id,
                'notification_key' => 'project-assigned-' . $project->id . '-' . $assignee->id . '-' . $project->updated_at?->timestamp,
            ],
            [
                'company_id' => (int) $project->company_id,
                'category' => 'activity',
                'source_type' => 'project',
                'source_id' => (string) $project->id,
                'title' => 'You have been assigned to Project (' . $project->name . ')',
                'message' => 'You have been assigned as manager for ' . $project->name,
                'url' => route('projects.show', $project),
                'notification_date' => now()->toDateString(),
                'status' => 'unread',
                'read_at' => null,
                'archived_at' => null,
                'payload' => [
                    'type' => 'project_assigned',
                    'project_id' => $project->id,
                ],
            ]
        );

        if ($notification->wasRecentlyCreated) {
            try {
                app(FirebasePushService::class)->sendDashboardNotification($notification, 'project');
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }
    }

    public function notifyStaffTaskAssigned(StaffTask $task): void
    {
        if (! $task->company_id || ! $task->staff_id) {
            return;
        }

        $task->loadMissing('staff.user');
        $assignee = $task->staff?->user;

        if (! $assignee || (int) $assignee->id === (int) auth()->id()) {
            return;
        }

        $notification = DashboardNotification::updateOrCreate(
            [
                'user_id' => $assignee->id,
                'notification_key' => 'task-assigned-' . $task->id . '-' . $assignee->id . '-' . $task->updated_at?->timestamp,
            ],
            [
                'company_id' => (int) $task->company_id,
                'category' => 'activity',
                'source_type' => 'staff_task',
                'source_id' => (string) $task->id,
                'title' => 'You have been assigned to Task (' . $task->task_name . ')',
                'message' => 'You have been assigned the task ' . $task->task_name,
                'url' => route('task-management.show', $task),
                'notification_date' => now()->toDateString(),
                'status' => 'unread',
                'read_at' => null,
                'archived_at' => null,
                'payload' => [
                    'type' => 'task_assigned',
                    'task_id' => $task->id,
                ],
            ]
        );

        if ($notification->wasRecentlyCreated) {
            try {
                app(FirebasePushService::class)->sendDashboardNotification($notification, 'staff');
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }
    }

    public function notifyTicketAssigned(Ticket $ticket, array $assigneeIds = []): void
    {
        if (! $ticket->company_id) {
            return;
        }

        if (empty($assigneeIds)) {
            if (Schema::hasTable('ticket_user_assignments')) {
                $ticket->loadMissing('assignedUsers');
                $assigneeIds = $ticket->assignedUsers->pluck('id')->all();
            }
            if (empty($assigneeIds) && $ticket->assigned_to) {
                $assigneeIds = [(int) $ticket->assigned_to];
            }
        }

        if (empty($assigneeIds)) {
            return;
        }

        $assignees = User::query()->whereIn('id', $assigneeIds)->get();

        foreach ($assignees as $assignee) {
            if (! $assignee || (int) $assignee->id === (int) auth()->id()) {
                continue;
            }

            $notification = DashboardNotification::updateOrCreate(
                [
                    'user_id' => $assignee->id,
                    'notification_key' => 'ticket-assigned-' . $ticket->id . '-' . $assignee->id . '-' . $ticket->updated_at?->timestamp,
                ],
                [
                    'company_id' => (int) $ticket->company_id,
                    'category' => 'activity',
                    'source_type' => 'ticket',
                    'source_id' => (string) $ticket->id,
                    'title' => 'You have been assigned to Service (' . ($ticket->ticket_number ?: 'Ticket') . ')',
                    'message' => 'You have been assigned to ticket ' . ($ticket->ticket_number ?: '') . ' | ' . ($ticket->customer_name ?: 'Customer'),
                    'url' => route('tickets.show', $ticket),
                    'notification_date' => now()->toDateString(),
                    'status' => 'unread',
                    'read_at' => null,
                    'archived_at' => null,
                    'payload' => [
                        'type' => 'ticket_assigned',
                        'ticket_id' => $ticket->id,
                    ],
                ]
            );

            if ($notification->wasRecentlyCreated) {
                try {
                    app(FirebasePushService::class)->sendDashboardNotification($notification, 'ticket');
                } catch (\Throwable $throwable) {
                    report($throwable);
                }
            }
        }
    }

    public function notifyTaskBlockerChanged(StaffTask $task): void
    {
        if (! $task->company_id || ! $task->has_blocker) {
            return;
        }

        $task->loadMissing(['staff.user', 'project.manager']);

        $audience = User::query()
            ->where('company_id', (int) $task->company_id)
            ->get()
            ->filter(fn (User $user) => $this->taskBlockerVisibleToUser($task, $user))
            ->unique('id')
            ->values();

        foreach ($audience as $user) {
            $notification = DashboardNotification::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_key' => 'task-blocker-' . $task->id,
                ],
                [
                    'company_id' => (int) $task->company_id,
                    'category' => 'activity',
                    'source_type' => 'staff_task',
                    'source_id' => (string) $task->id,
                    'title' => 'Task Blocker: ' . $task->task_name,
                    'message' => $this->buildTaskBlockerMessage($task),
                    'url' => route('task-management.show', $task),
                    'notification_date' => now()->toDateString(),
                    'status' => 'unread',
                    'read_at' => null,
                    'archived_at' => null,
                    'payload' => [
                        'type' => 'task_blocker',
                        'task_id' => $task->id,
                        'project_id' => $task->project_id,
                        'blocker_level' => $task->blocker_level,
                    ],
                ]
            );

            if ($notification->wasRecentlyCreated) {
                try {
                    app(FirebasePushService::class)->sendDashboardNotification($notification, 'staff');
                } catch (\Throwable $throwable) {
                    report($throwable);
                }
            }
        }
    }

    private function companySidebarItems(User $user, int $userId, int $companyId, Carbon $today): array
    {
        $schedule = collect();

        $schedule = $schedule->concat(
            Lead::where('company_id', $companyId)
                ->with('status:id,name')
                ->whereDate('next_follow_up', $today)
                ->orderBy('client_name')
                ->get()
                ->map(function (Lead $lead) use ($userId, $companyId, $today) {
                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                        key: 'company-schedule-lead-' . $lead->id . '-' . $today->toDateString(),
                        category: 'today_schedule',
                        sourceType: 'lead',
                        sourceId: (string) $lead->id,
                        title: $lead->client_name ?: 'Lead',
                        message: $lead->status?->name ?: 'Lead follow-up',
                        url: route('leads.show', $lead),
                        date: $today,
                        payload: [
                            'type' => 'lead',
                            'lead_id' => $lead->id,
                            'lead_uuid' => $lead->uuid,
                        ],
                        extras: [
                            'color' => '#2563eb',
                            'badge' => $lead->status?->name ?: 'Lead',
                            'time' => optional($lead->next_follow_up)->format('g:i A') ?: 'All day',
                        ],
                    );
                })
        );

        $schedule = $schedule->concat(
            Project::where('company_id', $companyId)
                ->whereDate('end_date', $today)
                ->orderBy('name')
                ->get()
                ->map(function (Project $project) use ($userId, $companyId, $today) {
                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                        key: 'company-schedule-project-' . $project->id . '-' . $today->toDateString(),
                        category: 'today_schedule',
                        sourceType: 'project',
                        sourceId: (string) $project->id,
                        title: $project->name ?: 'Project',
                        message: 'Project deadline',
                        url: route('projects.show', $project),
                        date: $today,
                        payload: [
                            'type' => 'project',
                            'project_id' => $project->id,
                        ],
                        extras: [
                            'color' => '#059669',
                            'badge' => 'Due today',
                            'time' => 'Deadline',
                        ],
                    );
                })
        );

        $schedule = $schedule->concat(
            AmcVisit::query()
                ->with(['amc:id,company_id,contract_no,customer_name,project_name'])
                ->whereHas('amc', fn (Builder $query) => $query->where('company_id', $companyId))
                ->whereDate('visit_date', $today)
                ->whereIn('status', ['pending', 'scheduled'])
                ->orderBy('visit_no')
                ->get()
                ->map(function (AmcVisit $visit) use ($userId, $companyId, $today) {
                    $amc = $visit->amc;

                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                        key: 'company-schedule-amc-visit-' . $visit->id . '-' . $today->toDateString(),
                        category: 'today_schedule',
                        sourceType: 'amc_visit',
                        sourceId: (string) $visit->id,
                        title: ($amc?->project_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no,
                        message: trim(($amc?->contract_no ?: 'AMC') . ' | ' . ($amc?->customer_name ?: 'Customer')),
                        url: $amc ? route('amcs.show', $amc) : route('amcs.index'),
                        date: $today,
                        payload: [
                            'type' => 'amc_visit',
                            'amc_id' => $amc?->id,
                            'visit_id' => $visit->id,
                        ],
                        extras: [
                            'color' => '#f59e0b',
                            'badge' => 'Scheduled service',
                            'time' => $visit->technician ? 'Technician: ' . $visit->technician : 'Scheduled service',
                        ],
                    );
                })
        );

        $schedule = $schedule->concat(
            Ticket::query()
                ->with(['assignedUser:id,name'])
                ->where('company_id', $companyId)
                ->whereIn('type', ['support', 'complain'])
                ->whereDate('visit_date', $today)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->orderBy('ticket_number')
                ->get()
                ->map(function (Ticket $ticket) use ($userId, $companyId, $today) {
                    $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                        key: 'company-schedule-ticket-' . $ticket->id . '-' . $today->toDateString(),
                        category: 'today_schedule',
                        sourceType: 'ticket',
                        sourceId: (string) $ticket->id,
                        title: $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                        message: trim(($ticket->ticket_number ?: 'Ticket') . ' | ' . ($ticket->customer_name ?: 'Customer')),
                        url: route('tickets.show', $ticket),
                        date: $today,
                        payload: [
                            'type' => 'ticket',
                            'ticket_id' => $ticket->id,
                        ],
                        extras: [
                            'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                            'badge' => $ticket->assignedUser?->name ? 'Assigned' : 'Visit scheduled',
                            'time' => $ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name : 'Scheduled visit',
                        ],
                    );
                })
        );

        $schedule = $schedule->concat(
            CalendarEvent::query()
                ->where('company_id', $companyId)
                ->where('visibility_scope', 'company')
                ->whereDate('event_date', $today)
                ->orderBy('title')
                ->get()
                ->map(function (CalendarEvent $event) use ($userId, $companyId, $today) {
                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                        key: 'company-schedule-calendar-' . $event->id . '-' . $today->toDateString(),
                        category: 'today_schedule',
                        sourceType: 'calendar_event',
                        sourceId: (string) $event->id,
                        title: $event->title,
                        message: ucfirst($event->status) . ($event->location ? ' | ' . $event->location : ''),
                        url: null,
                        date: $today,
                        payload: [
                            'type' => 'calendar_event',
                            'event_id' => $event->id,
                        ],
                        extras: [
                            'color' => $event->status === 'done' ? '#16a34a' : '#8b5cf6',
                            'badge' => ucfirst($event->event_type),
                            'time' => ucfirst($event->status),
                        ],
                    );
                })
        );

        $schedule = $schedule->concat(
            $this->taskReminderScheduleItems($userId, $companyId, $today, $user)
        );

        $absentCount = StaffAttendance::whereDate('date', $today)
            ->where('status', 'Absent')
            ->whereHas('staff', fn ($query) => $query->where('company_id', $companyId))
            ->count();

        if ($absentCount > 0) {
            $schedule->push($this->persistNotification(
                userId: $userId,
                companyId: $companyId,
                key: 'company-schedule-absent-' . $today->toDateString(),
                category: 'today_schedule',
                sourceType: 'staff_attendance',
                sourceId: 'absent-' . $today->toDateString(),
                title: 'Absent Today',
                message: 'Staff marked absent',
                url: null,
                date: $today,
                payload: [
                    'type' => 'absent',
                    'count' => $absentCount,
                ],
                extras: [
                    'color' => '#ef4444',
                    'badge' => $absentCount . ' staff',
                    'time' => 'Attendance alert',
                ],
            ));
        }

        $nextSevenDays = $today->copy()->addDays(7);
        $thirtyDaysFromToday = $today->copy()->addDays(30);

        $reminders = collect();

        $reminders = $reminders->concat(
            AmcVisit::query()
            ->with(['amc:id,company_id,contract_no,project_name,customer_name,end_date'])
            ->whereHas('amc', fn (Builder $query) => $query->where('company_id', $companyId))
            ->whereNotNull('visit_date')
            ->whereIn('status', ['pending', 'scheduled'])
            ->whereBetween('visit_date', [$today->toDateString(), $nextSevenDays->toDateString()])
            ->orderBy('visit_date')
            ->get()
            ->map(function (AmcVisit $visit) use ($userId, $companyId, $today) {
                $amc = $visit->amc;
                $daysLeft = (int) $today->diffInDays($visit->visit_date, false);

                return $this->persistNotification(
                    userId: $userId,
                    companyId: $companyId,
                    key: 'company-reminder-visit-' . $visit->id . '-' . $visit->visit_date?->toDateString(),
                    category: 'reminder',
                    sourceType: 'amc_visit',
                    sourceId: (string) $visit->id,
                    title: ($amc?->project_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no,
                    message: ($amc?->contract_no ?: 'AMC') . ' | ' . ($amc?->customer_name ?: 'Customer'),
                    url: $amc ? route('amcs.show', $amc) : route('amcs.index'),
                    date: $visit->visit_date,
                    payload: [
                        'type' => 'visit',
                        'visit_no' => $visit->visit_no,
                        'days_left' => $daysLeft,
                    ],
                    extras: [
                        'color' => '#f59e0b',
                        'badge' => $this->formatDaysLeftBadge($daysLeft),
                        'time' => $visit->technician ? 'Technician: ' . $visit->technician : 'Scheduled service',
                        'employee' => $visit->technician ?: ($amc?->technician_name ?: 'Not Assigned'),
                        'date_label' => optional($visit->visit_date)->format('d M Y'),
                    ],
                );
            })
        );

        $reminders = $reminders->concat(
            Amc::query()
            ->where('company_id', $companyId)
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today->toDateString(), $thirtyDaysFromToday->toDateString()])
            ->orderBy('end_date')
            ->get()
            ->map(function (Amc $amc) use ($userId, $companyId, $today) {
                $daysLeft = (int) $today->diffInDays($amc->end_date, false);

                return $this->persistNotification(
                    userId: $userId,
                    companyId: $companyId,
                    key: 'company-reminder-expiry-' . $amc->id . '-' . $amc->end_date?->toDateString(),
                    category: 'reminder',
                    sourceType: 'amc',
                    sourceId: (string) $amc->id,
                    title: ($amc->project_name ?: 'AMC Project') . ' AMC Ending Soon',
                    message: ($amc->contract_no ?: 'AMC') . ' | ' . ($amc->customer_name ?: 'Customer'),
                    url: route('amcs.show', $amc),
                    date: $amc->end_date,
                    payload: [
                        'type' => 'expiry',
                        'days_left' => $daysLeft,
                    ],
                    extras: [
                        'color' => '#dc2626',
                        'badge' => $this->formatDaysLeftBadge($daysLeft, 'Ends Today'),
                        'time' => $amc->technician_name ?: 'Not Assigned',
                        'employee' => $amc->technician_name ?: 'Not Assigned',
                        'date_label' => optional($amc->end_date)->format('d M Y'),
                    ],
                );
            })
        );

        return [$schedule, $reminders];
    }

    private function activityNotifications(User $user): Collection
    {
        $notifications = DashboardNotification::query()
            ->forUser($user)
            ->where('category', 'activity')
            ->where(function (Builder $query): void {
                $query->whereNull('payload->type')
                    ->orWhere('payload->type', '!=', 'task_blocker');
            })
            ->latest()
            ->limit(10)
            ->get();

        $activityLogIds = $notifications
            ->pluck('payload.activity_log_id')
            ->filter()
            ->map(fn ($value) => (int) $value)
            ->values();

        $activityLogs = $activityLogIds->isNotEmpty()
            ? ActivityLog::query()->whereIn('id', $activityLogIds)->get()->keyBy('id')
            : collect();

        $assignmentTypes = ['lead_assigned', 'project_assigned', 'task_assigned', 'ticket_assigned'];

        return $notifications
            ->map(function (DashboardNotification $notification) use ($activityLogs, $assignmentTypes) {
                $payloadType = (string) data_get($notification->payload, 'type', '');

                if (in_array($payloadType, $assignmentTypes, true)) {
                    return $this->notificationRow($notification, [
                        'title' => $notification->title,
                        'url' => $notification->url,
                        'badge' => 'Assigned',
                        'color' => '#7c3aed',
                    ]);
                }

                $activityLogId = (int) data_get($notification->payload, 'activity_log_id', $notification->source_id);
                $activityLog = $activityLogs->get($activityLogId);
                $routeName = (string) data_get($notification->payload, 'route', '');

                return $this->notificationRow($notification, [
                    'title_only' => true,
                    'url' => $notification->url ?: $this->resolveActivityUrl($routeName),
                    'title' => $this->buildActivityTitle(
                        (string) data_get($notification->payload, 'module', 'Activity'),
                        (string) data_get($notification->payload, 'action', 'view'),
                        $routeName,
                        $activityLog
                    ),
                ]);
            })
            ->values();
    }

    private function staffSidebarItems(User $user, int $userId, int $companyId, Carbon $today): array
    {
        $staffUser = $user;
        $staffId = $staffUser->staff?->id;

        $assignedLeadQuery = Lead::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($staffUser): void {
                $query->where('assigned_to', $staffUser->id)
                    ->orWhereHas('assignedUsers', function (Builder $assignedUsersQuery) use ($staffUser) {
                        $assignedUsersQuery->whereKey($staffUser->id);
                    });
            });

        $assignedProjectQuery = Project::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($staffUser): void {
                $query->where('project_manager_id', $staffUser->id)
                    ->orWhereJsonContains('team_member_ids', (int) $staffUser->id);
            });

        $assignedServiceQuery = Ticket::query()
            ->with(['assignedUser:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('type', ['support', 'complain'])
            ->where(function (Builder $query) use ($staffUser): void {
                $query->where('assigned_to', $staffUser->id)
                    ->orWhere('user_id', $staffUser->id);
            });

        $assignedAmcQuery = $staffId
            ? Amc::query()
                ->where('company_id', $companyId)
                ->where('assigned_staff_id', $staffId)
            : Amc::query()->whereRaw('1 = 0');

        $schedule = collect();

        $schedule = $schedule->concat(
            (clone $assignedLeadQuery)
                ->with('status:id,name')
                ->whereDate('next_follow_up', $today)
                ->orderBy('next_follow_up')
                ->get()
                ->map(function (Lead $lead) use ($today, $companyId, $userId) {
                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                            key: 'staff-schedule-lead-' . $lead->id . '-' . $today->toDateString(),
                            category: 'today_schedule',
                            sourceType: 'lead',
                            sourceId: (string) $lead->id,
                            title: $lead->client_name ?: 'Lead',
                            message: $lead->status?->name ?: 'Lead follow-up',
                            url: route('leads.show', $lead),
                            date: $today,
                            payload: [
                                'type' => 'lead',
                                'lead_id' => $lead->id,
                            ],
                        extras: [
                            'color' => '#2563eb',
                            'badge' => $lead->status?->name ?: 'Lead',
                            'time' => optional($lead->next_follow_up)->format('g:i A') ?: 'All day',
                        ],
                    );
                })
        );

        $schedule = $schedule->concat(
            (clone $assignedProjectQuery)
                ->get()
                ->filter(fn (Project $project) => ! empty($project->end_date))
                ->map(function (Project $project) use ($today, $companyId, $userId) {
                    $endDate = $project->end_date?->toDateString();

                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                        key: 'staff-schedule-project-' . $project->id . '-' . $today->toDateString(),
                        category: 'today_schedule',
                        sourceType: 'project',
                        sourceId: (string) $project->id,
                        title: trim($project->name ?: 'Project'),
                        message: 'Project deadline',
                        url: route('projects.show', $project),
                        date: $today,
                        payload: [
                            'type' => 'project',
                            'project_id' => $project->id,
                        ],
                        extras: [
                            'color' => '#059669',
                            'badge' => $endDate === $today->toDateString() ? 'Due today' : 'Deadline',
                            'time' => $endDate === $today->toDateString() ? 'Due today' : ($endDate ?: 'Deadline'),
                        ],
                    );
                })
        );

        $schedule = $schedule->concat(
            (clone $assignedServiceQuery)
                ->whereDate('visit_date', $today)
                ->whereNotIn('status', ['resolved', 'closed'])
                ->orderBy('visit_date')
                ->orderBy('ticket_number')
                ->get()
                ->map(function (Ticket $ticket) use ($today, $companyId, $userId) {
                    $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                        key: 'staff-schedule-ticket-' . $ticket->id . '-' . $today->toDateString(),
                        category: 'today_schedule',
                        sourceType: 'ticket',
                        sourceId: (string) $ticket->id,
                        title: $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                        message: trim(($ticket->ticket_number ?: 'Ticket') . ' | ' . ($ticket->customer_name ?: 'Customer')),
                        url: route('tickets.show', $ticket),
                        date: $today,
                        payload: [
                            'type' => 'ticket',
                            'ticket_id' => $ticket->id,
                        ],
                        extras: [
                            'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                            'badge' => $ticket->assignedUser?->name ? 'Assigned' : 'Visit scheduled',
                            'time' => $ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name : 'Scheduled visit',
                        ],
                    );
                })
        );

        $schedule = $schedule->concat(
            $this->taskReminderScheduleItems($userId, $companyId, $today, $staffUser)
        );

        $reminders = collect();

        $reminders = $reminders->concat(
            (clone $assignedAmcQuery)
                ->with(['assignedStaff:id,name'])
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
                ->orderBy('end_date')
                ->get()
                ->map(function (Amc $amc) use ($today, $companyId, $userId) {
                    $daysLeft = (int) $today->diffInDays($amc->end_date, false);

                    return $this->persistNotification(
                        userId: $userId,
                        companyId: $companyId,
                        key: 'staff-reminder-expiry-' . $amc->id . '-' . $amc->end_date?->toDateString(),
                        category: 'reminder',
                        sourceType: 'amc',
                        sourceId: (string) $amc->id,
                        title: ($amc->project_name ?: 'AMC Project') . ' AMC Ending Soon',
                        message: ($amc->contract_no ?: 'AMC') . ' | ' . ($amc->customer_name ?: 'Customer'),
                        url: route('amcs.show', $amc),
                        date: $amc->end_date,
                        payload: [
                            'type' => 'expiry',
                            'days_left' => $daysLeft,
                        ],
                        extras: [
                            'color' => '#dc2626',
                            'badge' => $this->formatDaysLeftBadge($daysLeft, 'Ends Today'),
                            'time' => $amc->assignedStaff?->name ?: 'Not Assigned',
                            'employee' => $amc->assignedStaff?->name ?: 'Not Assigned',
                            'date_label' => optional($amc->end_date)->format('d M Y'),
                        ],
                    );
                })
        );

        return [$schedule, $reminders];
    }

    private function taskReminderScheduleItems(int $userId, int $companyId, Carbon $today, ?User $staffUser = null): Collection
    {
        $windows = collect([
            [
                'due_date' => $today->copy(),
                'notification_time' => '10:00 AM',
                'time_label' => 'Today 10:00 AM',
            ],
            [
                'due_date' => $today->copy()->addDay(),
                'notification_time' => '6:00 PM',
                'time_label' => 'Today 6:00 PM',
            ],
        ]);

        return $windows->flatMap(function (array $window) use ($userId, $companyId, $today, $staffUser) {
            $reminderDate = $window['due_date'];
            $timeLabel = $window['time_label'];

            $query = StaffTask::query()
                ->with(['staff.user', 'project'])
                ->where('company_id', $companyId)
                ->whereNotNull('due_date')
                ->whereDate('due_date', $reminderDate->toDateString())
                ->whereIn('status', ['pending', 'in_progress', 'reschedule'])
                ->where(function (Builder $builder) use ($staffUser): void {
                    if ($staffUser?->isCompanyAdmin()) {
                        return;
                    }

                    if ($staffUser?->staff?->id) {
                        $builder->where('staff_id', $staffUser->staff->id);
                        return;
                    }

                    $builder->whereRaw('1 = 0');
                })
                ->get();

            return $query->map(function (StaffTask $task) use ($userId, $companyId, $today, $reminderDate, $timeLabel) {
            $staffName = $task->staff?->name ?: 'Unassigned';
            $projectName = $task->project?->name ?: null;
            $statusLabel = $task->statusLabel();
            $details = collect([
                'Status: ' . $statusLabel,
                $projectName ? 'Project: ' . $projectName : null,
                'Staff: ' . $staffName,
                $task->due_date ? 'Due: ' . $task->due_date->format('d M Y') : null,
            ])->filter()->implode(' | ');

            return $this->persistNotification(
                userId: $userId,
                companyId: $companyId,
                key: 'company-schedule-task-reminder-' . $task->id . '-' . $reminderDate->toDateString(),
                category: 'task_reminder',
                sourceType: 'staff_task',
                sourceId: (string) $task->id,
                title: 'Task Reminder: ' . $task->task_name,
                message: $details,
                url: route('task-management.show', $task),
                date: $today,
                payload: [
                    'type' => 'staff_task',
                    'task_id' => $task->id,
                    'due_date' => $task->due_date?->toDateString(),
                    'staff_id' => $task->staff_id,
                    'status' => $task->status,
                    'status_label' => $statusLabel,
                ],
                extras: [
                    'color' => '#0f766e',
                    'badge' => 'Task reminder',
                    'time' => $timeLabel,
                    'employee' => $staffName,
                ]
            );
            });
        });
    }

    private function blockerNotifications(User $user): Collection
    {
        return DashboardNotification::query()
            ->forUser($user)
            ->where('category', 'activity')
            ->where('payload->type', 'task_blocker')
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (DashboardNotification $notification) {
                $level = strtolower((string) data_get($notification->payload, 'blocker_level', 'low'));

                return $this->notificationRow($notification, [
                    'title' => $notification->title,
                    'badge' => match ($level) {
                        'high' => 'High blocker',
                        'medium' => 'Medium blocker',
                        default => 'Low blocker',
                    },
                    'time' => $notification->notification_date?->format('d M Y') ?? optional($notification->created_at)->format('d M Y'),
                    'color' => match ($level) {
                        'high' => '#dc2626',
                        'medium' => '#d97706',
                        default => '#16a34a',
                    },
                ]);
            })
            ->values();
    }

    private function taskBlockerVisibleToUser(StaffTask $task, User $user): bool
    {
        if ($user->isSuperAdmin() || $user->isCompanyAdmin()) {
            return (int) $user->company_id === (int) $task->company_id;
        }

        if ((int) $user->company_id !== (int) $task->company_id) {
            return false;
        }

        if ($task->staff?->user_id && (int) $task->staff->user_id === (int) $user->id) {
            return true;
        }

        if ($task->project && (int) $task->project->project_manager_id === (int) $user->id) {
            return true;
        }

        $teamMemberIds = array_map('intval', $task->project?->team_member_ids ?? []);

        return in_array((int) $user->id, $teamMemberIds, true);
    }

    private function buildTaskBlockerMessage(StaffTask $task): string
    {
        $level = match ($task->blocker_level) {
            'high' => 'High',
            'medium' => 'Medium',
            default => 'Low',
        };

        $projectName = $task->project?->name ? ' | Project: ' . $task->project->name : '';

        return $level . ' blocker' . $projectName;
    }

    private function notificationRow(DashboardNotification $notification, array $extras = []): array
    {
        $date = $notification->notification_date ?? $notification->created_at ?? now();
        $status = (string) ($notification->status ?? 'unread');

        return [
            'id' => $notification->id,
            'key' => $notification->notification_key,
            'category' => $notification->category,
            'source_type' => $notification->source_type,
            'source_id' => $notification->source_id,
            'title' => $extras['title'] ?? $notification->title,
            'message' => ($extras['title_only'] ?? false) ? null : $notification->message,
            'url' => $extras['url'] ?? $notification->url,
            'date' => optional($date)->toDateString(),
            'status' => $status,
            'status_label' => Str::headline($status),
            'status_sort' => $status === 'unread' ? 0 : 1,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'sort_timestamp' => $notification->created_at?->timestamp ?? $date->timestamp,
            'badge' => $extras['badge'] ?? null,
            'time' => $extras['time'] ?? null,
            'employee' => $extras['employee'] ?? null,
            'date_label' => optional($date)->format('d M Y'),
            'payload' => $notification->payload ?? [],
            'color' => $extras['color'] ?? '#64748b',
            'sort_date' => optional($date)->toDateString(),
            'sort_time' => optional($notification->created_at)->format('H:i:s') ?? '99:99:99',
            'notification' => $notification,
        ];
    }

    private function persistNotification(
        int $userId,
        int $companyId,
        string $key,
        string $category,
        string $sourceType,
        ?string $sourceId,
        string $title,
        ?string $message,
        ?string $url,
        Carbon $date,
        array $payload = [],
        array $extras = []
    ): array {
        $notification = DashboardNotification::firstOrNew([
            'user_id' => $userId,
            'notification_key' => $key,
        ]);

        $existingStatus = $notification->exists ? $notification->status : 'unread';
        $existingReadAt = $notification->exists ? $notification->read_at : null;
        $existingArchivedAt = $notification->exists ? $notification->archived_at : null;

        $notification->fill([
            'company_id' => $companyId,
            'category' => $category,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'title' => $this->normalizeNotificationTitle($title),
            'message' => $message,
            'url' => $url,
            'notification_date' => $date->toDateString(),
            'status' => $existingStatus,
            'read_at' => $existingReadAt,
            'archived_at' => $existingArchivedAt,
            'payload' => $payload,
        ]);

        $notification->save();

        if ($notification->wasRecentlyCreated) {
            try {
                app(FirebasePushService::class)->sendDashboardNotification(
                    $notification,
                    $this->resolveNotificationModule($category, $sourceType, $payload)
                );
            } catch (\Throwable $throwable) {
                report($throwable);
            }
        }

        return [
            'id' => $notification->id,
            'key' => $notification->notification_key,
            'category' => $category,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'title' => $title,
            'message' => $message,
            'url' => $url,
            'date' => $date->toDateString(),
            'status' => $notification->status,
            'status_label' => Str::headline($notification->status),
            'status_sort' => $notification->status === 'unread' ? 0 : 1,
            'read_at' => $notification->read_at?->toDateTimeString(),
            'sort_timestamp' => $notification->created_at?->timestamp ?? $date->timestamp,
            'badge' => $extras['badge'] ?? null,
            'time' => $extras['time'] ?? null,
            'employee' => $extras['employee'] ?? null,
            'date_label' => $date->format('d M Y'),
            'payload' => $payload,
            'color' => $extras['color'] ?? '#64748b',
            'sort_date' => $date->toDateString(),
            'sort_time' => $this->notificationTimeSortValue($extras['time'] ?? null),
            'notification' => $notification,
        ];
    }

    private function formatDaysLeftBadge(int $daysLeft, string $default = 'Today'): string
    {
        return match (true) {
            $daysLeft <= 0 => $default,
            $daysLeft === 1 => 'Tomorrow',
            default => 'After ' . $daysLeft . ' days',
        };
    }

    private function buildActivityTitle(string $module, string $action, string $routeName, ?ActivityLog $activityLog = null): string
    {
        $verb = match (true) {
            str_contains($routeName, '.import') => 'Imported',
            str_contains($routeName, '.assign') => 'Assigned',
            str_contains($routeName, '.reorder') => 'Reordered',
            str_contains($routeName, '.toggle') => 'Updated',
            str_contains($routeName, '.status') => 'Updated',
            str_contains($routeName, '.board-stage') => 'Updated',
            str_contains($routeName, '.payment') => 'Updated',
            $action === 'create' => 'Created',
            $action === 'update' => 'Updated',
            $action === 'delete' => 'Deleted',
            default => 'Changed',
        };

        $entityLabel = $this->resolveActivityEntityLabel($activityLog);

        if ($entityLabel !== null) {
            return trim($entityLabel . ' - ' . $module . ' ' . $verb);
        }

        return trim($module . ' ' . $verb);
    }

    private function normalizeNotificationTitle(string $title): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        return Str::limit($title, 191, '');
    }

    private function resolveActivityEntityLabel(?ActivityLog $activityLog): ?string
    {
        $label = data_get($activityLog?->metadata, 'entity_label');

        if (filled($label)) {
            return trim((string) $label);
        }

        $routeParameters = data_get($activityLog?->metadata, 'route_parameters', []);
        if (! is_array($routeParameters)) {
            return null;
        }

        foreach ($routeParameters as $parameter) {
            if (is_array($parameter) && filled($parameter['label'] ?? null)) {
                return trim((string) $parameter['label']);
            }
        }

        return null;
    }

    private function notificationSortKey(array $item): string
    {
        return implode('|', [
            (string) ($item['status_sort'] ?? 1),
            (string) ($item['sort_date'] ?? '9999-12-31'),
            (string) ($item['sort_time'] ?? '99:99'),
            Str::lower((string) ($item['title'] ?? '')),
        ]);
    }

    private function activityNotificationSortKey(array $item): string
    {
        $sortTimestamp = (int) ($item['sort_timestamp'] ?? 0);
        $inverseTimestamp = max(0, 9999999999 - $sortTimestamp);

        return sprintf(
            '%d|%010d|%s',
            (int) ($item['status_sort'] ?? 1),
            $inverseTimestamp,
            Str::lower((string) ($item['title'] ?? ''))
        );
    }

    private function resolveActivityUrl(string $routeName): ?string
    {
        $routes = [
            'leads.' => 'leads.index',
            'projects.' => 'projects.index',
            'amcs.' => 'amcs.index',
            'amc-plans.' => 'amc-plans.index',
            'tickets.' => 'tickets.index',
            'services.' => 'services.index',
            'settings.company.' => 'settings.company.index',
            'settings.roles.' => 'settings.roles.index',
            'settings.users.' => 'settings.users.index',
            'settings.gallery.' => 'settings.gallery.index',
            'settings.clients.' => 'settings.clients.index',
            'settings.boq.' => 'settings.boq.index',
            'reports.' => 'reports.index',
            'teams.' => 'teams.staff.index',
            'attendance.' => 'attendance.index',
            'subscription.' => 'subscription.show',
        ];

        foreach ($routes as $prefix => $targetRoute) {
            if (! str_starts_with($routeName, $prefix)) {
                continue;
            }

            if (! function_exists('route') || ! \Route::has($targetRoute)) {
                return null;
            }

            try {
                return route($targetRoute);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function resolveNotificationModule(string $category, string $sourceType, array $payload = []): ?string
    {
        if ($category === 'activity') {
            return $this->normalizeNotificationModule((string) ($payload['module'] ?? ''));
        }

        return match ($sourceType) {
            'lead' => 'lead',
            'project' => 'project',
            'ticket' => 'ticket',
            'calendar_event' => 'project',
            'staff_attendance' => 'staff',
            'amc', 'amc_visit' => 'payment',
            default => null,
        };
    }

    private function normalizeNotificationModule(string $module): ?string
    {
        $module = Str::lower(trim($module));

        return match ($module) {
            'lead', 'leads' => 'lead',
            'project', 'projects' => 'project',
            'ticket', 'tickets', 'service', 'services' => 'ticket',
            'amc', 'amcs', 'payment', 'payments', 'subscription' => 'payment',
            'staff', 'teams' => 'staff',
            default => $module !== '' ? $module : null,
        };
    }

    private function notificationTimeSortValue(?string $time): string
    {
        if (! filled($time)) {
            return '99:99';
        }

        if (preg_match('/^\d{1,2}:\d{2}\s?(AM|PM)$/i', $time)) {
            return Carbon::createFromFormat('g:i A', strtoupper(trim($time)))->format('H:i');
        }

        return '00:00';
    }
}
