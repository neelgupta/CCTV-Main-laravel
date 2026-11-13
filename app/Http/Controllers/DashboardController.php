<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\LeadSurvey;
use App\Models\LeadDocument;
use App\Models\LeadQuotation;
use App\Models\LeadFollowupHistory;
use App\Models\LeadFollowupSchedule;
use App\Models\CalendarEvent;
use App\Models\ActivityLog;
use App\Models\LeadActivity;
use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\Ticket;
use App\Models\Project;
use App\Models\StaffTask;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffDocument;
use App\Models\ProjectMaterial;
use App\Models\ProjectDrawing;
use App\Models\ProjectProduction;
use App\Models\User;
use App\Modules\Accounting\Services\AccountingDashboardService;
use App\Support\CompanyStorageUsage;
use App\Services\SubscriptionLimitService;
use App\Services\DashboardStaffTaskSummaryService;
use App\Services\TicketPaymentSummaryService;
use App\Services\TicketAssignmentHistoryService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    private function formatDaysLeftBadge(int $daysLeft, string $default = 'Today'): string
    {
        return match (true) {
            $daysLeft <= 0 => $default,
            $daysLeft === 1 => 'Tomorrow',
            default => 'After ' . $daysLeft . ' days',
        };
    }

    private function companyAdminCalendarEventsQuery(int $companyId): Builder
    {
        return CalendarEvent::query()
            ->where('company_id', $companyId)
            ->where('visibility_scope', 'company');
    }

    private function canManageCalendarEvent(User $user, CalendarEvent $calendarEvent): bool
    {
        if ((int) $calendarEvent->user_id === (int) $user->id) {
            return true;
        }

        if (! $user->isCompanyAdmin()) {
            return false;
        }

        return $calendarEvent->visibility_scope === 'company';
    }

    private function canViewTaskBlocker(User $user, StaffTask $task): bool
    {
        if ($user->isSuperAdmin() || $user->isCompanyAdmin()) {
            return (int) $task->company_id === (int) $user->company_id;
        }

        if ((int) $task->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($task->staff?->user_id && (int) $task->staff->user_id === (int) $user->id) {
            return true;
        }

        if ((int) $task->project?->project_manager_id === (int) $user->id) {
            return true;
        }

        $teamMemberIds = array_map('intval', $task->project?->team_member_ids ?? []);

        return in_array((int) $user->id, $teamMemberIds, true);
    }

    private function dashboardTaskBlockers(int $companyId, User $user): Collection
    {
        $tasks = StaffTask::query()
            ->with([
                'staff.user:id,name',
                'project:id,name,project_manager_id,team_member_ids',
            ])
            ->where('company_id', $companyId)
            ->where('has_blocker', true)
            ->latest()
            ->get();

        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            $tasks = $tasks->filter(fn (StaffTask $task) => $this->canViewTaskBlocker($user, $task));
        }

        return $tasks
            ->take(10)
            ->values()
            ->map(function (StaffTask $task) {
                $level = strtolower((string) $task->blocker_level ?: 'low');
                $staffName = $task->staff?->name ?: 'Unassigned';
                $projectName = $task->project?->name;
                $dueDate = $task->due_date?->format('d M Y');
                $description = trim((string) ($task->blocker_description ?? ''));

                return [
                    'id' => $task->id,
                    'title' => $task->task_name ?: 'Task blocker',
                    'description' => $description,
                    'blocker_level' => $level,
                    'badge' => match ($level) {
                        'high' => 'High blocker',
                        'medium' => 'Medium blocker',
                        default => 'Low blocker',
                    },
                    'badge_class' => match ($level) {
                        'high' => 'border-rose-200 bg-rose-50 text-rose-700',
                        'medium' => 'border-amber-200 bg-amber-50 text-amber-700',
                        default => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                    },
                    'dot_color' => match ($level) {
                        'high' => '#dc2626',
                        'medium' => '#d97706',
                        default => '#16a34a',
                    },
                    'meta' => collect([
                        $staffName ? 'Staff: ' . $staffName : null,
                        $projectName ? 'Project: ' . $projectName : null,
                        $dueDate ? 'Due: ' . $dueDate : null,
                    ])->filter()->implode(' | '),
                    'time' => $dueDate ?: optional($task->updated_at)->format('d M Y'),
                    'url' => route('task-management.show', $task),
                ];
            });
    }

    private function followUpStatusColor(string $status): string
    {
        return match (strtolower(trim($status))) {
            'not interested' => '#dc2626',
            'converted' => '#7c3aed',
            'callback' => '#f97316',
            'follow-up required' => '#eab308',
            'follow up required' => '#eab308',
            default => '#2563eb',
        };
    }

    private function followUpCalendarDisplayColor(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => '#8b5cf6',
            'done' => '#16a34a',
            default => $this->followUpStatusColor($status),
        };
    }

    private function followUpStatusRank(string $status): int
    {
        return match (strtolower(trim($status))) {
            'pending' => 1,
            'callback' => 2,
            'follow-up required', 'follow up required' => 3,
            'not interested' => 4,
            'converted' => 5,
            'done' => 99,
            default => 50,
        };
    }

    private function followUpStatusValues(): array
    {
        return [
            'Pending',
            'Done',
            'Not Interested',
            'Converted',
            'Callback',
            'Follow-up Required',
        ];
    }

    private function leadFollowUpCalendarEvent(LeadFollowupSchedule $schedule): ?array
    {
        $lead = $schedule->lead;

        if (! $lead) {
            return null;
        }

        $followUpDate = $schedule->followup_date?->toDateString();
        if (! $followUpDate) {
            return null;
        }

        $status = trim((string) $schedule->status) ?: 'pending';
        $leadTitle = trim($lead->client_name ?: 'Lead');
        $isPendingSchedule = strtolower($status) === 'pending';
        $eventTitle = $isPendingSchedule ? 'Follow up : ' . $leadTitle : $leadTitle;
        $historySummary = $lead->relationLoaded('followupHistories')
            ? optional($lead->followupHistories->first())->summary
            : $lead->followupHistories()->latest()->value('summary');
        $latestSummary = trim((string) ($historySummary ?: ''));

        return [
            'id' => 'lead-followup-' . $schedule->id,
            'title' => $eventTitle,
            'start' => $followUpDate,
            'allDay' => true,
            'url' => route('leads.show', ['lead' => $lead, 'tab' => 'followup_history']),
            'leadId' => $lead->uuid,
            'scheduleId' => $schedule->id,
            'followUpDate' => $followUpDate,
            'status' => $status,
            'statusRank' => $this->followUpStatusRank($status),
            'actionUrl' => route('leads.follow-up.save', $lead),
            'detailsUrl' => route('leads.follow-up.details', $lead),
            'historyUrl' => route('leads.follow-up.history', $lead),
            'type' => 'lead',
            'leadStatus' => $lead->status?->name,
            'companyName' => $lead->company?->name,
            'lastFollowUpSummary' => $latestSummary,
            'color' => $this->followUpCalendarDisplayColor($status),
            'displayTitle' => $eventTitle,
            'tooltip' => trim($eventTitle)
                . ' | Follow-up: ' . $followUpDate
                . ' | Status: ' . Str::headline($status)
                . ($lead->status?->name ? ' | Lead status: ' . $lead->status->name : '')
                . ($latestSummary !== '' ? ' | Last summary: ' . Str::limit($latestSummary, 80) : ''),
        ];
    }

    private function leadFollowUpScheduleQuery(int $companyId, ?User $user = null): Builder
    {
        return LeadFollowupSchedule::query()
            ->with([
                'lead.company:id,name',
                'lead.status:id,name,color',
                'creator:id,name',
                'lead.assignedUsers:id,name',
                'lead.followupHistories' => function ($query) {
                    $query->latest()->limit(5);
                },
            ])
            ->whereHas('lead', function (Builder $query) use ($companyId, $user) {
                $query->where('company_id', $companyId);

                if ($user && ! $user->isCompanyAdmin() && ! $user->isSuperAdmin()) {
                    $query->accessibleTo($user);
                }
            });
    }

    private function leadFollowUpPayload(Lead $lead): array
    {
        $lead->loadMissing(['company:id,name', 'status:id,name,color', 'followupHistories' => function ($query) {
            $query->latest()->limit(5);
        }, 'followupSchedules' => function ($query) {
            $query->orderByDesc('followup_date')->latest()->limit(5);
        }]);

        $customFields = collect($lead->company?->normalizedLeadCustomFields() ?? [])
            ->map(function (array $field) use ($lead) {
                return [
                    'key' => $field['key'] ?? '',
                    'label' => $field['label'] ?? 'Custom Field',
                    'type' => $field['type'] ?? 'text',
                    'value' => $lead->formatCustomFieldValue($field, data_get($lead->custom_fields ?? [], $field['key'] ?? '')),
                ];
            })
            ->values();

        $history = $lead->followupHistories
            ->sortByDesc('created_at')
            ->values()
            ->map(function (LeadFollowupHistory $item) {
                return [
                    'id' => $item->id,
                    'summary' => $item->summary,
                    'status' => $item->status,
                    'followup_date' => $item->followup_date?->toDateString(),
                    'next_followup_date' => $item->next_followup_date?->toDateString(),
                    'created_at' => $item->created_at?->toIso8601String(),
                    'created_by' => $item->created_by,
                    'created_by_name' => $item->creator?->name,
                ];
            });

        $lastFollowUpStatus = $history->first()['status'] ?? null;

        return [
            'lead' => [
                'id' => $lead->id,
                'uuid' => $lead->uuid,
                'name' => $lead->client_name,
                'company_name' => $lead->company?->name,
                'mobile' => $lead->mobile,
                'email' => $lead->email,
                'address' => $lead->address,
                'status' => $lead->status?->name,
                'status_id' => $lead->lead_status_id,
                'last_follow_up_summary' => $history->first()['summary'] ?? null,
                'last_follow_up_status' => $lastFollowUpStatus,
                'can_add_followup' => strtolower(trim((string) $lastFollowUpStatus)) !== 'done',
                'custom_fields' => $customFields,
            ],
            'latest_followup_summaries' => $history->take(5)->values(),
            'followup_history' => $history->values(),
            'current_schedule' => $lead->followupSchedules->first()?->only(['id', 'followup_date', 'status', 'completed_at']),
        ];
    }

    private function buildLeadPipelineChart(int $companyId): array
    {
        $statusRows = LeadStatus::query()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (LeadStatus $status, int $index) use ($companyId) {
                $count = Lead::query()
                    ->where('company_id', $companyId)
                    ->where('lead_status_id', $status->id)
                    ->count();

                $color = trim((string) $status->color);

                return [
                    'label' => trim((string) $status->name) ?: 'Stage ' . ($index + 1),
                    'count' => $count,
                    'color' => $color !== '' ? $color : $this->pipelineFallbackColor($index),
                ];
            })
            ->values();

        $totalLeads = Lead::query()
            ->where('company_id', $companyId)
            ->count();

        $convertedLeads = Lead::query()
            ->where('company_id', $companyId)
            ->whereHas('project')
            ->count();

        return [
            'rows' => $statusRows,
            'total' => $totalLeads,
            'converted' => $convertedLeads,
            'conversion_rate' => $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0,
            'empty' => $statusRows->sum('count') === 0,
        ];
    }

    private function ticketStatusColor(string $status, int $index = 0): string
    {
        return match ($status) {
            'open' => '#f59e0b',
            'in_progress' => '#2563eb',
            'answered' => '#8b5cf6',
            'resolved' => '#10b981',
            'hold' => '#ef4444',
            'reschedule' => '#14b8a6',
            'closed' => '#64748b',
            default => $this->pipelineFallbackColor($index),
        };
    }

    private function supportTicketsQuery(int $companyId, ?User $user = null): Builder
    {
        $query = Ticket::query()
            ->with(['assignedUser:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('type', ['support', 'complain']);

        if ($user && ! $user->isCompanyAdmin() && ! $user->isSuperAdmin()) {
            $query->where(function (Builder $supportScope) use ($user): void {
                $supportScope->where('assigned_to', $user->id)
                    ->orWhere('user_id', $user->id);
            });
        }

        return $query;
    }

    private function buildSupportAnalyticsCharts(int $companyId, Carbon $today, ?User $user = null): array
    {
        return $this->buildSupportAnalyticsChartsForRange(
            $companyId,
            $today->copy()->startOfDay(),
            $today->copy()->endOfDay(),
            'Today',
            $user
        );
    }

    private function buildSupportAnalyticsChartsForRange(int $companyId, Carbon $start, Carbon $end, string $rangeLabel, ?User $user = null): array
    {
        $rangeStart = $start->copy()->startOfDay();
        $rangeEnd = $end->copy()->endOfDay();
        $comparisonDate = $rangeStart->copy()->subDay();
        $comparisonStart = $comparisonDate->copy()->startOfDay();
        $comparisonEnd = $comparisonDate->copy()->endOfDay();
        $pendingStatuses = ['open', 'in_progress', 'answered', 'hold', 'reschedule'];
        $statusOptions = Ticket::statusOptions();
        $supportQuery = $this->supportTicketsQuery($companyId, $user);

        $changedTickets = (clone $supportQuery)
            ->whereBetween('updated_at', [$rangeStart, $rangeEnd])
            ->get();

        $statusRows = collect($statusOptions)
            ->map(function (string $label, string $status) use ($changedTickets): array {
                return [
                    'status' => $status,
                    'label' => $label,
                    'count' => $changedTickets->where('status', $status)->count(),
                ];
            })
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values();

        $statusChart = [
            'empty' => $statusRows->isEmpty(),
            'type' => 'bar',
            'data' => [
                'labels' => $statusRows->pluck('label')->all(),
                'datasets' => [
                    [
                        'label' => $rangeLabel . ' status changes',
                        'data' => $statusRows->pluck('count')->all(),
                        'backgroundColor' => $statusRows
                            ->values()
                            ->map(fn (array $row, int $index) => $this->ticketStatusColor($row['status'], $index))
                            ->all(),
                        'borderWidth' => 0,
                        'borderRadius' => 10,
                        'barThickness' => 18,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'indexAxis' => 'y',
            'plugins' => [
                    'legend' => [
                        'display' => false,
                    ],
                ],
                'scales' => [
                    'x' => [
                        'beginAtZero' => true,
                        'grid' => [
                            'color' => 'rgba(148, 163, 184, 0.18)',
                        ],
                        'ticks' => [
                            'color' => '#64748b',
                            'precision' => 0,
                        ],
                    ],
                    'y' => [
                        'grid' => [
                            'display' => false,
                        ],
                        'ticks' => [
                            'color' => '#475569',
                        ],
                    ],
                ],
            ],
        ];

        $createdTickets = (clone $supportQuery)
            ->whereBetween('created_at', [$rangeStart, $rangeEnd])
            ->get();

        $staffRows = $createdTickets
            ->groupBy(function (Ticket $ticket): string {
                return trim((string) ($ticket->assignedUser?->name ?: 'Unassigned')) ?: 'Unassigned';
            })
            ->map(fn (Collection $tickets, string $staffName): array => [
                'label' => $staffName,
                'count' => $tickets->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $staffPalette = ['#2563eb', '#059669', '#f59e0b', '#8b5cf6', '#ef4444', '#14b8a6', '#6366f1', '#ec4899'];
        $staffChart = [
            'empty' => $staffRows->isEmpty(),
            'type' => 'doughnut',
            'data' => [
                'labels' => $staffRows->pluck('label')->all(),
                'datasets' => [
                    [
                        'data' => $staffRows->pluck('count')->all(),
                        'backgroundColor' => $staffRows
                            ->values()
                            ->map(fn (array $row, int $index) => $staffPalette[$index % count($staffPalette)] ?? '#94a3b8')
                            ->all(),
                        'borderWidth' => 0,
                        'hoverOffset' => 6,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'cutout' => '68%',
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                        'labels' => [
                            'usePointStyle' => true,
                            'boxWidth' => 8,
                            'boxHeight' => 8,
                            'padding' => 14,
                        ],
                    ],
                ],
            ],
        ];

        $pendingTickets = (clone $supportQuery)
            ->whereIn('status', $pendingStatuses)
            ->where(function (Builder $query) use ($comparisonStart, $comparisonEnd): void {
                $query->whereBetween('created_at', [$comparisonStart, $comparisonEnd])
                    ->orWhereBetween('updated_at', [$comparisonStart, $comparisonEnd]);
            })
            ->get();

        $pendingRows = collect($pendingStatuses)
            ->map(function (string $status) use ($statusOptions, $pendingTickets): array {
                return [
                    'status' => $status,
                    'label' => $statusOptions[$status] ?? Str::headline($status),
                    'count' => $pendingTickets->where('status', $status)->count(),
                ];
            })
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values();

        $pendingChart = [
            'empty' => $pendingRows->isEmpty(),
            'type' => 'bar',
            'data' => [
                'labels' => $pendingRows->pluck('label')->all(),
                'datasets' => [
                    [
                        'label' => $rangeLabel === 'Today' ? 'Yesterday pending tickets' : 'Previous day pending tickets',
                        'data' => $pendingRows->pluck('count')->all(),
                        'backgroundColor' => $pendingRows
                            ->values()
                            ->map(fn (array $row, int $index) => $this->ticketStatusColor($row['status'], $index))
                            ->all(),
                        'borderWidth' => 0,
                        'borderRadius' => 10,
                        'barThickness' => 18,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'indexAxis' => 'y',
                'plugins' => [
                    'legend' => [
                        'display' => false,
                    ],
                ],
                'scales' => [
                    'x' => [
                        'beginAtZero' => true,
                        'grid' => [
                            'color' => 'rgba(148, 163, 184, 0.18)',
                        ],
                        'ticks' => [
                            'color' => '#64748b',
                            'precision' => 0,
                        ],
                    ],
                    'y' => [
                        'grid' => [
                            'display' => false,
                        ],
                        'ticks' => [
                            'color' => '#475569',
                        ],
                    ],
                ],
            ],
        ];

        return [
            'status' => $statusChart,
            'staff' => $staffChart,
            'pending' => $pendingChart,
            'headline' => [
                'changes' => $changedTickets->count(),
                'staff' => $staffRows->count(),
                'pending' => $pendingTickets->count(),
            ],
        ];
    }

    private function buildSupportStatusSummaryForRange(int $companyId, Carbon $start, Carbon $end, ?User $user = null): array
    {
        $tickets = $this->supportTicketsQuery($companyId, $user)
            ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
            ->get(['status', 'repeat_status', 'repeat_count']);

        return [
            'completed' => $tickets->whereIn('status', ['resolved', 'closed'])->count(),
            'pending' => $tickets->whereIn('status', ['open', 'in_progress', 'answered', 'reschedule'])->count(),
            'repeat' => (int) $tickets->sum('repeat_count'),
            'hold' => $tickets->where('status', 'hold')->count(),
            'total' => $tickets->count(),
        ];
    }

    private function pipelineFallbackColor(int $index): string
    {
        $palette = ['#2563eb', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#14b8a6', '#6366f1', '#ec4899'];

        return $palette[$index % count($palette)] ?? '#94a3b8';
    }

    private function buildDashboardRangeCharts(int $companyId, Carbon $start, Carbon $end, string $rangeLabel, ?User $user = null): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        $dateCursor = $start->copy();
        $dateLabels = [];
        $dateKeys = [];

        while ($dateCursor->lte($end)) {
            $dateLabels[] = match (true) {
                $rangeLabel === 'Today' => $dateCursor->format('D, d M'),
                $dateCursor->isSameMonth($end) => $dateCursor->format('j M'),
                default => $dateCursor->format('d M'),
            };
            $dateKeys[] = $dateCursor->toDateString();
            $dateCursor->addDay();
        }

        $groupedCounts = static function (Builder $query, string $dateColumn, Carbon $start, Carbon $end): array {
            return $query
                ->whereBetween($dateColumn, [$start->toDateTimeString(), $end->toDateTimeString()])
                ->selectRaw('DATE(' . $dateColumn . ') as day, COUNT(*) as aggregate')
                ->groupBy('day')
                ->pluck('aggregate', 'day')
                ->map(fn ($count) => (int) $count)
                ->all();
        };

        $leadCounts = $groupedCounts(
            Lead::query()
                ->where('company_id', $companyId)
                ->when($user && ! $user->isCompanyAdmin() && ! $user->isSuperAdmin(), fn (Builder $query) => $query->accessibleTo($user)),
            'created_at',
            $start,
            $end
        );
        $projectCounts = $groupedCounts(
            Project::query()
                ->where('company_id', $companyId)
                ->when($user && ! $user->isCompanyAdmin() && ! $user->isSuperAdmin(), fn (Builder $query) => $query->accessibleTo($user)),
            'created_at',
            $start,
            $end
        );
        $amcCounts = $groupedCounts(
            Amc::query()
                ->where('company_id', $companyId)
                ->when($user && ! $user->isCompanyAdmin() && ! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                    if ($user?->staff?->id) {
                        $query->where('assigned_staff_id', $user->staff->id);
                        return;
                    }

                    $query->whereRaw('1 = 0');
                }),
            'created_at',
            $start,
            $end
        );
        $supportCounts = $groupedCounts(
            Ticket::query()
                ->where('company_id', $companyId)
                ->whereIn('type', ['support', 'complain'])
                ->when($user && ! $user->isCompanyAdmin() && ! $user->isSuperAdmin(), function (Builder $query) use ($user): void {
                    $query->where(function (Builder $supportScope) use ($user): void {
                        $supportScope->where('assigned_to', $user->id)
                            ->orWhere('user_id', $user->id);
                    });
                }),
            'created_at',
            $start,
            $end
        );

        $buildSeries = static function (array $counts) use ($dateKeys): array {
            return array_map(static fn (string $dateKey) => $counts[$dateKey] ?? 0, $dateKeys);
        };

        $leadSeries = $buildSeries($leadCounts);
        $projectSeries = $buildSeries($projectCounts);
        $amcSeries = $buildSeries($amcCounts);
        $supportSeries = $buildSeries($supportCounts);

        $activityTotals = array_map(
            static fn (int $lead, int $project, int $amc, int $support): int => $lead + $project + $amc + $support,
            $leadSeries,
            $projectSeries,
            $amcSeries,
            $supportSeries
        );

        $trendChart = [
            'empty' => array_sum($activityTotals) === 0,
            'type' => 'line',
            'data' => [
                'labels' => $dateLabels,
                'datasets' => [
                    [
                        'label' => 'Leads',
                        'data' => $leadSeries,
                        'borderColor' => '#2563eb',
                        'backgroundColor' => 'rgba(37, 99, 235, 0.15)',
                        'pointBackgroundColor' => '#2563eb',
                        'pointBorderColor' => '#ffffff',
                        'fill' => true,
                        'tension' => 0.35,
                    ],
                    [
                        'label' => 'Projects',
                        'data' => $projectSeries,
                        'borderColor' => '#059669',
                        'backgroundColor' => 'rgba(5, 150, 105, 0.14)',
                        'pointBackgroundColor' => '#059669',
                        'pointBorderColor' => '#ffffff',
                        'fill' => true,
                        'tension' => 0.35,
                    ],
                    [
                        'label' => 'AMC',
                        'data' => $amcSeries,
                        'borderColor' => '#f59e0b',
                        'backgroundColor' => 'rgba(245, 158, 11, 0.14)',
                        'pointBackgroundColor' => '#f59e0b',
                        'pointBorderColor' => '#ffffff',
                        'fill' => true,
                        'tension' => 0.35,
                    ],
                    [
                        'label' => 'Support',
                        'data' => $supportSeries,
                        'borderColor' => '#14b8a6',
                        'backgroundColor' => 'rgba(20, 184, 166, 0.14)',
                        'pointBackgroundColor' => '#14b8a6',
                        'pointBorderColor' => '#ffffff',
                        'fill' => true,
                        'tension' => 0.35,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'interaction' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
                'plugins' => [
                    'legend' => [
                        'display' => true,
                        'position' => 'bottom',
                        'labels' => [
                            'usePointStyle' => true,
                            'boxWidth' => 8,
                            'boxHeight' => 8,
                            'padding' => 16,
                        ],
                    ],
                ],
                'scales' => [
                    'x' => [
                        'grid' => [
                            'display' => false,
                        ],
                        'ticks' => [
                            'color' => '#64748b',
                            'maxRotation' => 0,
                            'autoSkip' => true,
                        ],
                    ],
                    'y' => [
                        'beginAtZero' => true,
                        'grid' => [
                            'color' => 'rgba(148, 163, 184, 0.18)',
                        ],
                        'ticks' => [
                            'color' => '#64748b',
                            'precision' => 0,
                        ],
                    ],
                ],
            ],
        ];

        $mixLabels = [];
        $mixValues = [];
        $mixColors = [];

        $mixSource = [
            'Leads' => [$leadSeries, '#2563eb'],
            'Projects' => [$projectSeries, '#059669'],
            'AMC' => [$amcSeries, '#f59e0b'],
            'Support' => [$supportSeries, '#14b8a6'],
        ];

        foreach ($mixSource as $label => [$series, $color]) {
            $value = array_sum($series);

            if ($value <= 0) {
                continue;
            }

            $mixLabels[] = $label;
            $mixValues[] = $value;
            $mixColors[] = $color;
        }

        $mixChart = [
            'empty' => empty($mixValues),
            'type' => 'doughnut',
            'data' => [
                'labels' => $mixLabels,
                'datasets' => [
                    [
                        'data' => $mixValues,
                        'backgroundColor' => $mixColors,
                        'borderWidth' => 0,
                        'hoverOffset' => 6,
                    ],
                ],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'cutout' => '68%',
                'plugins' => [
                    'legend' => [
                        'display' => false,
                    ],
                ],
            ],
        ];

        return [
            'trend' => $trendChart,
            'mix' => $mixChart,
            'headline' => [
                'total_activity' => array_sum($activityTotals),
                'peak_day' => $activityTotals ? max($activityTotals) : 0,
            ],
        ];
    }

    private function buildCompanyCalendarEvents(int $companyId, Carbon $today, int $absentToday = 0): Collection
    {
        $leadEvents = $this->leadFollowUpScheduleQuery($companyId)
            ->whereNotNull('followup_date')
            ->orderBy('followup_date')
            ->orderBy('id')
            ->get()
            ->map(fn (LeadFollowupSchedule $schedule) => $this->leadFollowUpCalendarEvent($schedule))
            ->filter()
            ->sortBy(fn (array $event) => [$event['start'] ?? '', $event['statusRank'] ?? 50, $event['title'] ?? ''])
            ->values();

        $projectEvents = Project::where('company_id', $companyId)
            ->get()
            ->filter(fn (Project $project) => ! empty($project->end_date))
            ->map(function (Project $project) {
                $endDate = $project->end_date?->toDateString();

                return [
                    'title' => trim($project->name ?: 'Project'),
                    'start' => $endDate,
                    'allDay' => true,
                    'url' => route('projects.show', $project),
                    'type' => 'project',
                    'color' => '#059669',
                    'displayTitle' => trim($project->name ?: 'Project'),
                    'tooltip' => trim($project->name ?: 'Project')
                        . ' | End date: ' . ($endDate ?: 'Not set'),
                ];
            })
            ->values();

        $amcEvents = AmcVisit::query()
            ->with(['amc:id,company_id,contract_no,customer_name,project_name'])
            ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
            ->whereNotNull('visit_date')
            ->whereIn('status', ['pending', 'scheduled'])
            ->orderBy('visit_date')
            ->get()
            ->map(function (AmcVisit $visit) {
                $visitDate = $visit->visit_date?->toDateString();
                $amc = $visit->amc;

                return [
                    'title' => trim(($amc?->project_name ?: $amc?->customer_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no),
                    'start' => $visitDate,
                    'allDay' => true,
                    'url' => $amc ? route('amcs.show', $amc) : null,
                    'type' => 'amc',
                    'color' => '#f59e0b',
                    'displayTitle' => trim(($amc?->project_name ?: 'AMC Service') . ' - V' . $visit->visit_no),
                    'tooltip' => trim(($amc?->contract_no ?: 'AMC') . ' | '
                        . ($amc?->customer_name ?: 'Customer') . ' | '
                        . ($amc?->project_name ?: 'Project') . ' | Visit ' . $visit->visit_no
                        . ' | ' . ($visitDate ?: 'Not set')
                        . ($visit->technician ? ' | Technician: ' . $visit->technician : '')),
                ];
            })
            ->values();

        $ticketEvents = Ticket::query()
            ->with(['assignedUser:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('type', ['support', 'complain'])
            ->whereNotNull('visit_date')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderBy('visit_date')
            ->orderBy('ticket_number')
            ->get()
            ->map(function (Ticket $ticket) {
                $visitDate = $ticket->visit_date?->toDateString();
                $ticketTypeLabel = $ticket->type_label ?: 'Ticket';
                $ticketColor = $ticket->type === 'complain' ? '#f97316' : '#14b8a6';

                return [
                    'title' => Str::limit(trim($ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket')), 48),
                    'start' => $visitDate,
                    'allDay' => true,
                    'url' => route('tickets.show', $ticket),
                    'type' => 'ticket',
                    'ticketType' => $ticket->type,
                    'color' => $ticketColor,
                    'displayTitle' => Str::limit(trim($ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket')), 34),
                    'tooltip' => trim(($ticket->ticket_number ?: 'Ticket') . ' | '
                        . $ticketTypeLabel . ' | '
                        . ($ticket->customer_name ?: 'Customer') . ' | '
                        . ($ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name . ' | ' : '')
                        . 'Status: ' . $ticket->status_label . ' | '
                        . 'Visit: ' . ($visitDate ?: 'Not set')),
                ];
            })
            ->values();

        $calendarEvents = collect($leadEvents)
            ->merge($projectEvents)
            ->merge($amcEvents)
            ->merge($ticketEvents);

        if (\Illuminate\Support\Facades\Schema::hasTable('calendar_events')) {
            $calendarEvents = collect($calendarEvents)->merge(
                CalendarEvent::where('company_id', $companyId)
                    ->where('visibility_scope', 'company')
                    ->latest()
                    ->get()
                    ->map(function (CalendarEvent $event) {
                        $isDone = $event->status === 'done';

                        return [
                            'title' => $event->title,
                            'start' => optional($event->event_date)->toDateString(),
                            'allDay' => true,
                            'type' => 'custom-task',
                            'color' => $isDone ? '#16a34a' : '#8b5cf6',
                            'displayTitle' => $isDone ? $event->title . ' (Done)' : $event->title,
                            'location' => $event->location,
                            'description' => $event->description,
                            'eventDate' => optional($event->event_date)->toDateString(),
                            'eventId' => $event->id,
                            'eventType' => $event->event_type,
                            'status' => $event->status,
                            'updateUrl' => route('dashboard.calendar-events.update', $event),
                            'deleteUrl' => route('dashboard.calendar-events.destroy', $event),
                            'tooltip' => trim($event->title)
                                . ' | Status: ' . ucfirst($event->status)
                                . ($event->location ? ' | Location: ' . $event->location : '')
                                . ($event->description ? ' | ' . $event->description : ''),
                        ];
                    })
                    ->unique(function (array $event) {
                        return implode('|', [
                            trim((string) ($event['title'] ?? '')),
                            trim((string) ($event['start'] ?? '')),
                            trim((string) ($event['eventType'] ?? '')),
                            trim((string) ($event['location'] ?? '')),
                        ]);
                    })
                    ->values()
            );
        }

        return $calendarEvents
            ->when($absentToday > 0, function ($events) use ($today, $absentToday) {
                return $events->push([
                    'title' => 'Absent Today: ' . $absentToday,
                    'start' => $today->toDateString(),
                    'allDay' => true,
                    'type' => 'absent',
                    'color' => '#ef4444',
                    'displayTitle' => 'Absent Today',
                    'tooltip' => 'Absent Today: ' . $absentToday,
                ]);
            })
            ->sortBy(fn (array $event) => [$event['start'] ?? '', $event['statusRank'] ?? 50, $event['title'] ?? ''])
            ->values();
    }

    private function buildStaffCalendarEvents(int $companyId, User $staffUser, Carbon $today): Collection
    {
        $staffId = $staffUser->staff?->id;

        $assignedLeadQuery = Lead::query()
            ->where('company_id', $companyId)
            ->accessibleTo($staffUser);

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

                if (Schema::hasTable('ticket_user_assignments')) {
                    $query->orWhereHas('assignedUsers', function (Builder $assignedUsersQuery) use ($staffUser): void {
                        $assignedUsersQuery->whereKey($staffUser->id);
                    });
                }
            });

        $assignedAmcQuery = $staffId
            ? Amc::query()
                ->where('company_id', $companyId)
                ->where('assigned_staff_id', $staffId)
            : Amc::query()->whereRaw('1 = 0');

        $leadEvents = $this->leadFollowUpScheduleQuery($companyId, $staffUser)
            ->whereNotNull('followup_date')
            ->orderBy('followup_date')
            ->orderBy('id')
            ->get()
            ->map(fn (LeadFollowupSchedule $schedule) => $this->leadFollowUpCalendarEvent($schedule))
            ->filter()
            ->sortBy(fn (array $event) => [$event['start'] ?? '', $event['statusRank'] ?? 50, $event['title'] ?? ''])
            ->values();

        $projectEvents = (clone $assignedProjectQuery)
            ->get()
            ->filter(fn (Project $project) => ! empty($project->end_date))
            ->map(function (Project $project) {
                $endDate = $project->end_date?->toDateString();

                return [
                    'title' => trim($project->name ?: 'Project'),
                    'start' => $endDate,
                    'allDay' => true,
                    'url' => route('projects.show', $project),
                    'type' => 'project',
                    'color' => '#059669',
                    'displayTitle' => trim($project->name ?: 'Project'),
                    'tooltip' => trim($project->name ?: 'Project')
                        . ' | End date: ' . ($endDate ?: 'Not set'),
                ];
            })
            ->values();

        $amcEvents = (clone $assignedAmcQuery)
            ->with(['project:id,name', 'assignedStaff:id,name', 'visits:id,amc_id,visit_no,visit_date,technician,status'])
            ->whereNotNull('end_date')
            ->orderBy('end_date')
            ->get()
            ->map(function (Amc $amc) use ($today) {
                $endDate = $amc->end_date?->toDateString();
                $daysLeft = $endDate ? (int) $today->diffInDays($amc->end_date, false) : null;

                return [
                    'title' => ($amc->project_name ?: 'AMC Project') . ' AMC Ending ' . ($daysLeft === 0 ? 'Today' : 'Soon'),
                    'start' => $endDate,
                    'allDay' => true,
                    'url' => route('amcs.show', $amc),
                    'type' => 'amc',
                    'color' => '#f59e0b',
                    'displayTitle' => trim(($amc->project_name ?: 'AMC Project') . ' AMC'),
                    'tooltip' => trim(($amc->contract_no ?: 'AMC') . ' | '
                        . ($amc->customer_name ?: 'Customer') . ' | '
                        . ($amc->project_name ?: 'Project') . ' | '
                        . 'Ends: ' . ($endDate ?: 'Not set')
                        . ($daysLeft ? ' | ' . $this->formatDaysLeftBadge($daysLeft, 'Ends Today') : '')),
                ];
            })
            ->values();

        $ticketEvents = (clone $assignedServiceQuery)
            ->whereNotNull('visit_date')
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderBy('visit_date')
            ->orderBy('ticket_number')
            ->get()
            ->map(function (Ticket $ticket) {
                $visitDate = $ticket->visit_date?->toDateString();
                $ticketTypeLabel = $ticket->type_label ?: 'Ticket';
                $ticketColor = $ticket->type === 'complain' ? '#f97316' : '#14b8a6';

                return [
                    'title' => Str::limit(trim($ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket')), 48),
                    'start' => $visitDate,
                    'allDay' => true,
                    'url' => route('tickets.show', $ticket),
                    'type' => 'ticket',
                    'ticketType' => $ticket->type,
                    'color' => $ticketColor,
                    'displayTitle' => Str::limit(trim($ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket')), 34),
                    'tooltip' => trim(($ticket->ticket_number ?: 'Ticket') . ' | '
                        . $ticketTypeLabel . ' | '
                        . ($ticket->customer_name ?: 'Customer') . ' | '
                        . ($ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name . ' | ' : '')
                        . 'Status: ' . $ticket->status_label . ' | '
                        . 'Visit: ' . ($visitDate ?: 'Not set')),
                ];
            })
            ->values();

        $calendarEvents = collect($leadEvents)
            ->merge($projectEvents)
            ->merge($amcEvents)
            ->merge($ticketEvents);

        if (\Illuminate\Support\Facades\Schema::hasTable('calendar_events')) {
            $calendarEvents = collect($calendarEvents)->merge(
                CalendarEvent::query()
                    ->where('company_id', $companyId)
                    ->where('user_id', $staffUser->id)
                    ->where('visibility_scope', 'private')
                    ->latest()
                    ->get()
                    ->map(function (CalendarEvent $event) {
                        $isDone = $event->status === 'done';

                        return [
                            'title' => $event->title,
                            'start' => optional($event->event_date)->toDateString(),
                            'allDay' => true,
                            'type' => 'custom-task',
                            'color' => $isDone ? '#16a34a' : '#8b5cf6',
                            'displayTitle' => $isDone ? $event->title . ' (Done)' : $event->title,
                            'location' => $event->location,
                            'description' => $event->description,
                            'eventDate' => optional($event->event_date)->toDateString(),
                            'eventId' => $event->id,
                            'eventType' => $event->event_type,
                            'status' => $event->status,
                            'updateUrl' => route('dashboard.calendar-events.update', $event),
                            'deleteUrl' => route('dashboard.calendar-events.destroy', $event),
                            'tooltip' => trim($event->title)
                                . ' | Status: ' . ucfirst($event->status)
                                . ($event->location ? ' | Location: ' . $event->location : '')
                                . ($event->description ? ' | ' . $event->description : ''),
                        ];
                    })
                    ->unique(function (array $event) {
                        return implode('|', [
                            trim((string) ($event['title'] ?? '')),
                            trim((string) ($event['start'] ?? '')),
                            trim((string) ($event['eventType'] ?? '')),
                            trim((string) ($event['location'] ?? '')),
                        ]);
                    })
                    ->values()
            );
        }

        return $calendarEvents
            ->sortBy(fn (array $event) => [$event['start'] ?? '', $event['statusRank'] ?? 50, $event['title'] ?? ''])
            ->values();
    }

    private function activityModuleColor(string $module): string
    {
        $normalized = Str::lower(trim($module));

        return match (true) {
            str_contains($normalized, 'lead') => '#2563eb',
            str_contains($normalized, 'project') => '#059669',
            str_contains($normalized, 'amc') => '#f59e0b',
            str_contains($normalized, 'service'),
            str_contains($normalized, 'ticket'),
            str_contains($normalized, 'support') => '#14b8a6',
            str_contains($normalized, 'staff'),
            str_contains($normalized, 'attendance') => '#8b5cf6',
            str_contains($normalized, 'setting'),
            str_contains($normalized, 'company') => '#0f766e',
            default => '#64748b',
        };
    }

    private function buildTodayActivityFeed(int $companyId, Carbon $today, ?int $userId = null, int $limit = 8): Collection
    {
        return $this->buildActivityFeed($companyId, $today, $today, $userId, $limit);
    }

    private function buildActivityFeed(int $companyId, Carbon $start, Carbon $end, ?int $userId = null, int $limit = 8): Collection
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();

        return ActivityLog::query()
            ->with(['user:id,name'])
            ->where('company_id', $companyId)
            ->when($userId, fn (Builder $query) => $query->where('user_id', $userId))
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (ActivityLog $log) {
                $module = trim((string) $log->module) ?: 'Activity';
                $action = trim((string) $log->action) ?: 'update';
                $moduleLabel = Str::headline(Str::lower($module));
                $actionLabel = Str::headline(Str::lower(str_replace(['_', '-'], ' ', $action)));

                return [
                    'type' => 'activity',
                    'title' => trim($moduleLabel . ' ' . $actionLabel),
                    'meta' => trim((string) ($log->description ?: 'Activity recorded')),
                    'time' => optional($log->created_at)->format('g:i A') ?: 'Now',
                    'color' => $this->activityModuleColor($module),
                    'badge' => $moduleLabel,
                    'user' => $log->user?->name ?: 'System',
                    'url' => null,
                ];
            });
    }

    private function buildCompanyDashboardRangeData(int $companyId, Carbon $start, Carbon $end, string $rangeLabel): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();
        $today = Carbon::today()->toDateString();
        $isToday = $start->isSameDay($end) && $start->isSameDay(Carbon::today());
        $scheduleTitle = $rangeLabel === 'Today' ? "Today's Schedule" : $rangeLabel . "'s Schedule";
        $remindersTitle = $rangeLabel === 'Today' ? 'AMC Reminders' : $rangeLabel . ' AMC Reminders';
        $leadsTitle = $rangeLabel === 'Today' ? 'New Leads' : $rangeLabel . ' New Leads';
        $projectsTitle = $rangeLabel === 'Today' ? 'New Projects' : $rangeLabel . ' New Projects';
        $amcTitle = $rangeLabel === 'Today' ? 'New AMC' : $rangeLabel . ' New AMC';
        $supportTitle = $rangeLabel === 'Today' ? 'New Support' : $rangeLabel . ' New Support';
        $pendingLeadsTitle = $rangeLabel === 'Today' ? 'Pending Leads' : $rangeLabel . ' Pending Leads';
        $overdueLeadsTitle = $rangeLabel === 'Today' ? 'Overdue Leads' : $rangeLabel . ' Overdue Leads';
        $overdueSupportTitle = $rangeLabel === 'Today' ? 'Overdue Support' : $rangeLabel . ' Overdue Support';
        $actionsTitle = $rangeLabel === 'Today' ? 'Recent Actions' : $rangeLabel . ' Actions';
        $charts = $this->buildDashboardRangeCharts($companyId, $start, $end, $rangeLabel);
        $charts['support'] = $this->buildSupportAnalyticsChartsForRange($companyId, $start, $end, $rangeLabel);

        $schedule = collect()
            ->merge(
                $this->leadFollowUpScheduleQuery($companyId)
                    ->whereBetween('followup_date', [$start, $end])
                    ->orderBy('followup_date')
                    ->orderBy('status')
                        ->get()
                        ->map(function (LeadFollowupSchedule $schedule) {
                            $lead = $schedule->lead;
                            if (! $lead) {
                                return null;
                            }
                            $leadName = $lead?->client_name ?: 'Lead';
                            $isPendingSchedule = strtolower(trim((string) $schedule->status)) === 'pending';

                        return [
                            'type' => 'lead',
                            'title' => $isPendingSchedule ? 'Follow up : ' . $leadName : $leadName,
                            'meta' => Str::headline(str_replace(['_', '-'], ' ', (string) $schedule->status ?: 'pending')),
                            'time' => $schedule->followup_date?->format('g:i A') ?: 'All day',
                            'color' => $this->followUpCalendarDisplayColor((string) $schedule->status),
                            'url' => route('leads.show', $lead),
                        ];
                    })
                    ->filter()
                    ->values()
            )
            ->merge(
                Project::query()
                    ->where('company_id', $companyId)
                    ->whereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('name')
                    ->get()
                    ->map(function (Project $project) {
                        return [
                            'type' => 'project',
                            'title' => $project->name ?: 'Project',
                            'meta' => 'Project deadline',
                            'time' => 'Due within range',
                            'color' => '#059669',
                            'url' => route('projects.show', $project),
                        ];
                    })
            )
            ->merge(
                AmcVisit::query()
                    ->with(['amc:id,company_id,contract_no,customer_name,project_name'])
                    ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
                    ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
                    ->whereIn('status', ['pending', 'scheduled'])
                    ->orderBy('visit_no')
                    ->get()
                    ->map(function (AmcVisit $visit) {
                        $amc = $visit->amc;

                        return [
                            'type' => 'amc',
                            'title' => ($amc?->project_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no,
                            'meta' => trim(($amc?->contract_no ?: 'AMC') . ' | ' . ($amc?->customer_name ?: 'Customer')),
                            'time' => $visit->technician ? 'Technician: ' . $visit->technician : 'Scheduled service',
                            'color' => '#f59e0b',
                            'url' => $amc ? route('amcs.show', $amc) : null,
                        ];
                    })
            )
            ->merge(
                Ticket::query()
                    ->with(['assignedUser:id,name'])
                    ->where('company_id', $companyId)
                    ->whereIn('type', ['support', 'complain'])
                    ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->orderBy('ticket_number')
                    ->get()
                    ->map(function (Ticket $ticket) {
                        $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                        return [
                            'type' => 'ticket',
                            'title' => $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                            'meta' => trim(($ticket->ticket_number ?: 'Ticket') . ' | ' . ($ticket->customer_name ?: 'Customer')),
                            'time' => $ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name : 'Scheduled visit',
                            'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                            'url' => route('tickets.show', $ticket),
                        ];
                    })
            )
            ->merge(
                $this->companyAdminCalendarEventsQuery($companyId)
                    ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('title')
                    ->get()
                    ->map(function (CalendarEvent $event) {
                        return [
                            'type' => 'custom-task',
                            'title' => $event->title,
                            'meta' => ucfirst($event->status) . ($event->location ? ' | ' . $event->location : ''),
                            'time' => ucfirst($event->event_type),
                            'color' => $event->status === 'done' ? '#16a34a' : '#8b5cf6',
                            'url' => null,
                        ];
                    })
            )
            ->values();

        $reminders = collect()
            ->merge(
                AmcVisit::query()
                    ->with(['amc:id,company_id,contract_no,project_name,customer_name,end_date'])
                    ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
                    ->whereNotNull('visit_date')
                    ->whereIn('status', ['pending', 'scheduled'])
                    ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('visit_date')
                    ->get()
                    ->map(function (AmcVisit $visit) use ($start) {
                        $amc = $visit->amc;
                        $daysLeft = (int) $start->diffInDays($visit->visit_date, false);

                        return [
                            'type' => 'visit',
                            'title' => ($amc?->project_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no,
                            'meta' => ($amc?->contract_no ?: 'AMC') . ' | ' . ($amc?->customer_name ?: 'Customer'),
                            'employee' => $visit->technician ?: ($amc?->technician_name ?: 'Not Assigned'),
                            'date' => optional($visit->visit_date)->format('d M Y'),
                            'sort_date' => optional($visit->visit_date)?->toDateString(),
                            'url' => $amc ? route('amcs.show', $amc) : route('amcs.index'),
                            'badge' => $this->formatDaysLeftBadge($daysLeft),
                            'color' => '#f59e0b',
                        ];
                    })
            )
            ->merge(
                Amc::query()
                    ->where('company_id', $companyId)
                    ->whereNotNull('end_date')
                    ->whereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('end_date')
                    ->get()
                    ->map(function (Amc $amc) use ($start) {
                        $daysLeft = (int) $start->diffInDays($amc->end_date, false);

                        return [
                            'type' => 'expiry',
                            'title' => ($amc->project_name ?: 'AMC Project') . ' AMC Ending Soon',
                            'meta' => ($amc->contract_no ?: 'AMC') . ' | ' . ($amc->customer_name ?: 'Customer'),
                            'employee' => $amc->technician_name ?: 'Not Assigned',
                            'date' => optional($amc->end_date)->format('d M Y'),
                            'sort_date' => optional($amc->end_date)?->toDateString(),
                            'url' => route('amcs.show', $amc),
                            'badge' => $this->formatDaysLeftBadge($daysLeft, 'Ends Today'),
                            'color' => '#dc2626',
                        ];
                    })
            )
            ->sortBy('sort_date')
            ->values();

        $activityFeed = $this->buildActivityFeed($companyId, $start, $end, null, 20);

        $newLeads = Lead::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->with('status:id,name')
            ->latest('created_at')
            ->get()
            ->map(function (Lead $lead) {
                return [
                    'type' => 'lead',
                    'title' => $lead->client_name ?: 'Lead',
                    'meta' => trim(($lead->status?->name ?: 'New lead') . ($lead->source ? ' | ' . $lead->source : '')),
                    'time' => optional($lead->created_at)->format('g:i A') ?: 'Any time',
                    'color' => '#2563eb',
                    'badge' => 'Lead',
                    'url' => route('leads.show', $lead),
                ];
            })
            ->values();

        $pendingLeads = Lead::query()
            ->where('company_id', $companyId)
            ->whereDoesntHave('project')
            ->whereHas('status', function (Builder $query) {
                $query->whereRaw('LOWER(name) NOT IN (?, ?)', ['won', 'lost']);
            })
            ->where(function (Builder $query) use ($start, $end): void {
                $query->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('next_follow_up', [$start->toDateString(), $end->toDateString()]);
            })
            ->with(['status:id,name,color', 'assignedTo:id,name'])
            ->orderByRaw('CASE WHEN next_follow_up IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_follow_up')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Lead $lead) {
                $statusName = $lead->status?->name ?: 'Pending';

                return [
                    'type' => 'lead',
                    'title' => $lead->client_name ?: 'Lead',
                    'meta' => trim(
                        $statusName
                        . ($lead->source ? ' | ' . $lead->source : '')
                        . ($lead->next_follow_up ? ' | Follow-up: ' . $lead->next_follow_up->format('d M Y') : '')
                    ),
                    'time' => $lead->assignedTo?->name ? 'Assigned: ' . $lead->assignedTo->name : 'Awaiting assignment',
                    'color' => $lead->status?->color ?: '#2563eb',
                    'badge' => $statusName,
                    'user_label' => 'Assigned to',
                    'user' => $lead->assignedTo?->name ?: null,
                    'url' => route('leads.show', $lead),
                ];
            })
            ->values();

        $overdueLeads = Lead::query()
            ->where('company_id', $companyId)
            ->whereDoesntHave('project')
            ->whereHas('status', function (Builder $query) {
                $query->whereRaw('LOWER(name) NOT IN (?, ?)', ['won', 'lost']);
            })
            ->whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<', $today)
            ->with(['status:id,name,color', 'assignedTo:id,name'])
            ->whereDoesntHave('activities')
            ->orderBy('next_follow_up')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Lead $lead) {
                $statusName = $lead->status?->name ?: 'Overdue';

                return [
                    'type' => 'lead',
                    'title' => $lead->client_name ?: 'Lead',
                    'meta' => trim(
                        $statusName
                        . ($lead->source ? ' | ' . $lead->source : '')
                        . ($lead->next_follow_up ? ' | Follow-up: ' . $lead->next_follow_up->format('d M Y') : '')
                    ),
                    'time' => $lead->assignedTo?->name ? 'Assigned: ' . $lead->assignedTo->name : 'Awaiting assignment',
                    'color' => '#dc2626',
                    'badge' => 'Overdue',
                    'user_label' => 'Assigned to',
                    'user' => $lead->assignedTo?->name ?: null,
                    'url' => route('leads.show', $lead),
                ];
            })
            ->values();

        $newProjects = Project::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get()
            ->map(function (Project $project) {
                return [
                    'type' => 'project',
                    'title' => $project->name ?: 'Project',
                    'meta' => trim(($project->current_stage ? Str::headline($project->current_stage) : 'New project') . ($project->reference_no ? ' | ' . $project->reference_no : '')),
                    'time' => optional($project->created_at)->format('g:i A') ?: 'Any time',
                    'color' => '#059669',
                    'badge' => 'Project',
                    'url' => route('projects.show', $project),
                ];
            })
            ->values();

        $newAmcs = Amc::query()
            ->where('company_id', $companyId)
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get()
            ->map(function (Amc $amc) {
                return [
                    'type' => 'amc',
                    'title' => $amc->project_name ?: 'AMC',
                    'meta' => trim(($amc->contract_no ?: 'New AMC') . ($amc->customer_name ? ' | ' . $amc->customer_name : '')),
                    'time' => optional($amc->created_at)->format('g:i A') ?: 'Any time',
                    'color' => '#f59e0b',
                    'badge' => 'AMC',
                    'url' => route('amcs.show', $amc),
                ];
            })
            ->values();

        $newSupports = Ticket::query()
            ->with(['assignedUser:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('type', ['support', 'complain'])
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get()
            ->map(function (Ticket $ticket) {
                $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                return [
                    'type' => 'ticket',
                    'title' => $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                    'meta' => trim(($ticket->ticket_number ?: 'Ticket') . ($ticket->customer_name ? ' | ' . $ticket->customer_name : '') . ($ticket->assignedUser?->name ? ' | Assigned: ' . $ticket->assignedUser->name : '')),
                    'time' => optional($ticket->created_at)->format('g:i A') ?: 'Any time',
                    'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                    'badge' => $ticketTypeLabel,
                    'user_label' => 'Assigned to',
                    'user' => $ticket->assignedUser?->name ?: null,
                    'url' => route('tickets.show', $ticket),
                ];
            })
            ->values();

        $overdueSupports = Ticket::query()
            ->with(['assignedUser:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('type', ['support', 'complain'])
            ->whereNotNull('visit_date')
            ->whereDate('visit_date', '<', $today)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereDoesntHave('replies')
            ->orderBy('visit_date')
            ->latest('created_at')
            ->get()
            ->map(function (Ticket $ticket) {
                $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                return [
                    'type' => 'ticket',
                    'title' => $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                    'meta' => trim(
                        ($ticket->ticket_number ?: 'Ticket')
                        . ($ticket->customer_name ? ' | ' . $ticket->customer_name : '')
                        . ($ticket->visit_date ? ' | Visit: ' . $ticket->visit_date->format('d M Y') : '')
                    ),
                    'time' => $ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name : 'Unassigned',
                    'color' => '#dc2626',
                    'badge' => 'Overdue',
                    'user_label' => 'Assigned to',
                    'user' => $ticket->assignedUser?->name ?: null,
                    'url' => route('tickets.show', $ticket),
                ];
            })
            ->values();

        $summaryCards = [
            ['label' => 'Schedule', 'count' => $schedule->count(), 'note' => $rangeLabel . ' tasks and deadlines'],
            ['label' => 'Reminders', 'count' => $reminders->count(), 'note' => $rangeLabel . ' AMC reminders'],
            ['label' => 'Pending Leads', 'count' => $pendingLeads->count(), 'note' => 'Open leads needing follow-up'],
            ['label' => 'Overdue Leads', 'count' => $overdueLeads->count(), 'note' => 'Follow-ups that missed their date'],
            ['label' => 'Leads', 'count' => $newLeads->count(), 'note' => 'New leads in this period'],
            ['label' => 'Projects', 'count' => $newProjects->count(), 'note' => 'New projects in this period'],
            ['label' => 'AMC', 'count' => $newAmcs->count(), 'note' => 'New AMC records in this period'],
            ['label' => 'Support', 'count' => $newSupports->count(), 'note' => 'New support tickets in this period'],
            ['label' => 'Overdue Support', 'count' => $overdueSupports->count(), 'note' => 'Visits that passed without action'],
            ['label' => 'Actions', 'count' => $activityFeed->count(), 'note' => 'Logged actions in this period'],
        ];

        $sections = collect([
            [
                'key' => 'schedule',
                'title' => $scheduleTitle,
                'subtitle' => $rangeLabel . ' follow-ups, deadlines, visits, and tasks.',
                'items' => $schedule,
                'empty' => 'No items for this period.',
            ],
            [
                'key' => 'reminders',
                'title' => $remindersTitle,
                'subtitle' => 'AMC visit and expiry reminders within the selected range.',
                'items' => $reminders,
                'empty' => 'No AMC reminders in this period.',
            ],
            [
                'key' => 'pending_leads',
                'title' => $pendingLeadsTitle,
                'subtitle' => 'Open leads in the selected range with their assignees and next follow-up dates.',
                'items' => $pendingLeads,
                'empty' => 'No pending leads in this period.',
            ],
            [
                'key' => 'overdue_leads',
                'title' => $overdueLeadsTitle,
                'subtitle' => 'Leads with missed follow-up dates and no recorded action.',
                'items' => $overdueLeads,
                'empty' => 'No overdue leads right now.',
            ],
            [
                'key' => 'leads',
                'title' => $leadsTitle,
                'subtitle' => 'New leads created in the selected range.',
                'items' => $newLeads,
                'empty' => 'No new leads in this period.',
            ],
            [
                'key' => 'projects',
                'title' => $projectsTitle,
                'subtitle' => 'New projects created in the selected range.',
                'items' => $newProjects,
                'empty' => 'No new projects in this period.',
            ],
            [
                'key' => 'amc',
                'title' => $amcTitle,
                'subtitle' => 'New AMC records created in the selected range.',
                'items' => $newAmcs,
                'empty' => 'No new AMC records in this period.',
            ],
            [
                'key' => 'support',
                'title' => $supportTitle,
                'subtitle' => 'Support and complaint tickets created in the selected range, including assigned staff.',
                'items' => $newSupports,
                'empty' => 'No new support tickets in this period.',
            ],
            [
                'key' => 'overdue_support',
                'title' => $overdueSupportTitle,
                'subtitle' => 'Support tickets with visit dates that have passed and no action yet.',
                'items' => $overdueSupports,
                'empty' => 'No overdue support tickets right now.',
            ],
            [
                'key' => 'actions',
                'title' => $actionsTitle,
                'subtitle' => 'Cross-module changes and actions in the selected range.',
                'items' => $activityFeed,
                'empty' => 'No actions recorded in this period.',
            ],
        ]);

        return [
            'range_label' => $rangeLabel,
            'summary_cards' => $summaryCards,
            'sections' => $sections,
            'charts' => $charts,
            'snapshot_total' => $summaryCards ? collect($summaryCards)->sum('count') : 0,
            'totals' => [
                'schedule' => $schedule->count(),
                'reminders' => $reminders->count(),
                'pending_leads' => $pendingLeads->count(),
                'overdue_leads' => $overdueLeads->count(),
                'leads' => $newLeads->count(),
                'projects' => $newProjects->count(),
                'amc' => $newAmcs->count(),
                'support' => $newSupports->count(),
                'overdue_support' => $overdueSupports->count(),
                'actions' => $activityFeed->count(),
            ],
        ];
    }

    private function buildStaffDashboardRangeData(int $companyId, User $staffUser, Carbon $start, Carbon $end, string $rangeLabel): array
    {
        $start = $start->copy()->startOfDay();
        $end = $end->copy()->endOfDay();
        $today = Carbon::today()->toDateString();
        $isToday = $start->isSameDay($end) && $start->isSameDay(Carbon::today());
        $scheduleTitle = $rangeLabel === 'Today' ? "Today's Schedule" : $rangeLabel . "'s Schedule";
        $remindersTitle = $rangeLabel === 'Today' ? 'AMC Reminders' : $rangeLabel . ' AMC Reminders';
        $leadsTitle = $rangeLabel === 'Today' ? 'New Leads' : $rangeLabel . ' New Leads';
        $projectsTitle = $rangeLabel === 'Today' ? 'New Projects' : $rangeLabel . ' New Projects';
        $amcTitle = $rangeLabel === 'Today' ? 'New AMC' : $rangeLabel . ' New AMC';
        $supportTitle = $rangeLabel === 'Today' ? 'New Support' : $rangeLabel . ' New Support';
        $pendingLeadsTitle = $rangeLabel === 'Today' ? 'Pending Leads' : $rangeLabel . ' Pending Leads';
        $overdueLeadsTitle = $rangeLabel === 'Today' ? 'Overdue Leads' : $rangeLabel . ' Overdue Leads';
        $overdueSupportTitle = $rangeLabel === 'Today' ? 'Overdue Support' : $rangeLabel . ' Overdue Support';
        $actionsTitle = $rangeLabel === 'Today' ? 'Recent Actions' : $rangeLabel . ' Actions';

        $assignedLeadQuery = Lead::query()
            ->where('company_id', $companyId)
            ->accessibleTo($staffUser);

        $assignedProjectQuery = Project::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($staffUser): void {
                $query->where('project_manager_id', $staffUser->id)
                    ->orWhereJsonContains('team_member_ids', (int) $staffUser->id)
                    ->orWhereJsonContains('team_member_ids', (string) $staffUser->id)
                    ->orWhereHas('lead', function (Builder $leadQuery) use ($staffUser): void {
                        $leadQuery->where('assigned_to', $staffUser->id)
                            ->orWhereHas('assignedUsers', function (Builder $assignedUsersQuery) use ($staffUser): void {
                                $assignedUsersQuery->whereKey($staffUser->id);
                            });
                    });
            });

        $assignedServiceQuery = Ticket::query()
            ->with(['assignedUser:id,name'])
            ->where('company_id', $companyId)
            ->whereIn('type', ['support', 'complain'])
            ->where(function (Builder $query) use ($staffUser): void {
                $query->where('assigned_to', $staffUser->id)
                    ->orWhere('user_id', $staffUser->id);

                if (Schema::hasTable('ticket_user_assignments')) {
                    $query->orWhereHas('assignedUsers', function (Builder $assignedUsersQuery) use ($staffUser): void {
                        $assignedUsersQuery->whereKey($staffUser->id);
                    });
                }
            });

        $assignedAmcQuery = $staffUser->staff?->id
            ? Amc::query()
                ->where('company_id', $companyId)
                ->where('assigned_staff_id', $staffUser->staff->id)
            : Amc::query()->whereRaw('1 = 0');

        $charts = $this->buildDashboardRangeCharts($companyId, $start, $end, $rangeLabel, $staffUser);
        $charts['support'] = $this->buildSupportAnalyticsChartsForRange($companyId, $start, $end, $rangeLabel, $staffUser);

        $schedule = collect()
            ->merge(
                $this->leadFollowUpScheduleQuery($companyId, $staffUser)
                    ->whereBetween('followup_date', [$start, $end])
                    ->orderBy('followup_date')
                    ->orderBy('status')
                    ->get()
                    ->map(function (LeadFollowupSchedule $schedule) {
                        $lead = $schedule->lead;
                        $leadName = $lead?->client_name ?: 'Lead';
                        $isPendingSchedule = strtolower(trim((string) $schedule->status)) === 'pending';

                        return [
                            'type' => 'lead',
                            'title' => $isPendingSchedule ? 'Follow up : ' . $leadName : $leadName,
                            'meta' => Str::headline(str_replace(['_', '-'], ' ', (string) $schedule->status ?: 'pending')),
                            'time' => $schedule->followup_date?->format('g:i A') ?: 'All day',
                            'color' => $this->followUpCalendarDisplayColor((string) $schedule->status),
                            'url' => route('leads.show', $lead),
                        ];
                    })
            )
            ->merge(
                (clone $assignedProjectQuery)
                    ->whereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('name')
                    ->get()
                    ->map(function (Project $project) {
                        return [
                            'type' => 'project',
                            'title' => $project->name ?: 'Project',
                            'meta' => 'Project deadline',
                            'time' => 'Due within range',
                            'color' => '#059669',
                            'url' => route('projects.show', $project),
                        ];
                    })
            )
            ->merge(
                (clone $assignedAmcQuery)
                    ->whereNotNull('end_date')
                    ->whereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('end_date')
                    ->get()
                    ->map(function (Amc $amc) {
                        return [
                            'type' => 'amc',
                            'title' => ($amc->project_name ?: 'AMC Service') . ' - Ending Soon',
                            'meta' => trim(($amc->contract_no ?: 'AMC') . ' | ' . ($amc->customer_name ?: 'Customer')),
                            'time' => $amc->assignedStaff?->name ? 'Technician: ' . $amc->assignedStaff->name : 'Scheduled service',
                            'color' => '#f59e0b',
                            'url' => route('amcs.show', $amc),
                        ];
                    })
            )
            ->merge(
                (clone $assignedServiceQuery)
                    ->whereBetween('visit_date', [$start->toDateString(), $end->toDateString()])
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->orderBy('ticket_number')
                    ->get()
                    ->map(function (Ticket $ticket) {
                        $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                        return [
                            'type' => 'ticket',
                            'title' => $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                            'meta' => trim(($ticket->ticket_number ?: 'Ticket') . ' | ' . ($ticket->customer_name ?: 'Customer')),
                            'time' => $ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name : 'Scheduled visit',
                            'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                            'url' => route('tickets.show', $ticket),
                        ];
                    })
            )
            ->merge(
                CalendarEvent::query()
                    ->where('company_id', $companyId)
                    ->where('user_id', $staffUser->id)
                    ->where('visibility_scope', 'private')
                    ->whereBetween('event_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('title')
                    ->get()
                    ->map(function (CalendarEvent $event) {
                        return [
                            'type' => 'custom-task',
                            'title' => $event->title,
                            'meta' => ucfirst($event->status) . ($event->location ? ' | ' . $event->location : ''),
                            'time' => ucfirst($event->event_type),
                            'color' => $event->status === 'done' ? '#16a34a' : '#8b5cf6',
                            'url' => null,
                        ];
                    })
            )
            ->values();

        $reminders = collect()
            ->merge(
                Amc::query()
                    ->with(['assignedStaff:id,name'])
                    ->where('company_id', $companyId)
                    ->where('assigned_staff_id', $staffUser->staff?->id ?: 0)
                    ->whereNotNull('end_date')
                    ->whereBetween('end_date', [$start->toDateString(), $end->toDateString()])
                    ->orderBy('end_date')
                    ->get()
                    ->map(function (Amc $amc) use ($start) {
                        $daysLeft = (int) $start->diffInDays($amc->end_date, false);

                        return [
                            'type' => 'expiry',
                            'title' => ($amc->project_name ?: 'AMC Project') . ' AMC Ending Soon',
                            'meta' => ($amc->contract_no ?: 'AMC') . ' | ' . ($amc->customer_name ?: 'Customer'),
                            'employee' => $amc->assignedStaff?->name ?: 'Not Assigned',
                            'date' => optional($amc->end_date)->format('d M Y'),
                            'sort_date' => optional($amc->end_date)?->toDateString(),
                            'url' => route('amcs.show', $amc),
                            'badge' => $this->formatDaysLeftBadge($daysLeft, 'Ends Today'),
                            'color' => '#dc2626',
                        ];
                    })
            )
            ->values();

        $activityFeed = $this->buildActivityFeed($companyId, $start, $end, $staffUser->id, 20);

        $newLeads = (clone $assignedLeadQuery)
            ->whereBetween('created_at', [$start, $end])
            ->with('status:id,name')
            ->latest('created_at')
            ->get()
            ->map(function (Lead $lead) {
                return [
                    'type' => 'lead',
                    'title' => $lead->client_name ?: 'Lead',
                    'meta' => trim(($lead->status?->name ?: 'New lead') . ($lead->source ? ' | ' . $lead->source : '')),
                    'time' => optional($lead->created_at)->format('g:i A') ?: 'Any time',
                    'color' => '#2563eb',
                    'badge' => 'Lead',
                    'url' => route('leads.show', $lead),
                ];
            })
            ->values();

        $pendingLeads = (clone $assignedLeadQuery)
            ->whereDoesntHave('project')
            ->whereHas('status', function (Builder $query) {
                $query->whereRaw('LOWER(name) NOT IN (?, ?)', ['won', 'lost']);
            })
            ->where(function (Builder $query) use ($start, $end): void {
                $query->whereBetween('created_at', [$start, $end])
                    ->orWhereBetween('next_follow_up', [$start->toDateString(), $end->toDateString()]);
            })
            ->with(['status:id,name,color', 'assignedTo:id,name'])
            ->orderByRaw('CASE WHEN next_follow_up IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_follow_up')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Lead $lead) {
                $statusName = $lead->status?->name ?: 'Pending';

                return [
                    'type' => 'lead',
                    'title' => $lead->client_name ?: 'Lead',
                    'meta' => trim(
                        $statusName
                        . ($lead->source ? ' | ' . $lead->source : '')
                        . ($lead->next_follow_up ? ' | Follow-up: ' . $lead->next_follow_up->format('d M Y') : '')
                    ),
                    'time' => $lead->assignedTo?->name ? 'Assigned: ' . $lead->assignedTo->name : 'Awaiting assignment',
                    'color' => $lead->status?->color ?: '#2563eb',
                    'badge' => $statusName,
                    'user_label' => 'Assigned to',
                    'user' => $lead->assignedTo?->name ?: null,
                    'url' => route('leads.show', $lead),
                ];
            })
            ->values();

        $overdueLeads = (clone $assignedLeadQuery)
            ->whereDoesntHave('project')
            ->whereHas('status', function (Builder $query) {
                $query->whereRaw('LOWER(name) NOT IN (?, ?)', ['won', 'lost']);
            })
            ->whereNotNull('next_follow_up')
            ->whereDate('next_follow_up', '<', $today)
            ->with(['status:id,name,color', 'assignedTo:id,name'])
            ->whereDoesntHave('activities')
            ->orderBy('next_follow_up')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Lead $lead) {
                $statusName = $lead->status?->name ?: 'Overdue';

                return [
                    'type' => 'lead',
                    'title' => $lead->client_name ?: 'Lead',
                    'meta' => trim(
                        $statusName
                        . ($lead->source ? ' | ' . $lead->source : '')
                        . ($lead->next_follow_up ? ' | Follow-up: ' . $lead->next_follow_up->format('d M Y') : '')
                    ),
                    'time' => $lead->assignedTo?->name ? 'Assigned: ' . $lead->assignedTo->name : 'Awaiting assignment',
                    'color' => '#dc2626',
                    'badge' => 'Overdue',
                    'user_label' => 'Assigned to',
                    'user' => $lead->assignedTo?->name ?: null,
                    'url' => route('leads.show', $lead),
                ];
            })
            ->values();

        $newProjects = (clone $assignedProjectQuery)
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get()
            ->map(function (Project $project) {
                return [
                    'type' => 'project',
                    'title' => $project->name ?: 'Project',
                    'meta' => trim(($project->current_stage ? Str::headline($project->current_stage) : 'New project') . ($project->reference_no ? ' | ' . $project->reference_no : '')),
                    'time' => optional($project->created_at)->format('g:i A') ?: 'Any time',
                    'color' => '#059669',
                    'badge' => 'Project',
                    'url' => route('projects.show', $project),
                ];
            })
            ->values();

        $newAmcs = (clone $assignedAmcQuery)
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get()
            ->map(function (Amc $amc) {
                return [
                    'type' => 'amc',
                    'title' => $amc->project_name ?: 'AMC',
                    'meta' => trim(($amc->contract_no ?: 'New AMC') . ($amc->customer_name ? ' | ' . $amc->customer_name : '')),
                    'time' => optional($amc->created_at)->format('g:i A') ?: 'Any time',
                    'color' => '#f59e0b',
                    'badge' => 'AMC',
                    'url' => route('amcs.show', $amc),
                ];
            })
            ->values();

        $newSupports = (clone $assignedServiceQuery)
            ->whereBetween('created_at', [$start, $end])
            ->latest('created_at')
            ->get()
            ->map(function (Ticket $ticket) {
                $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                return [
                    'type' => 'ticket',
                    'title' => $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                    'meta' => trim(($ticket->ticket_number ?: 'Ticket') . ($ticket->customer_name ? ' | ' . $ticket->customer_name : '') . ($ticket->assignedUser?->name ? ' | Assigned: ' . $ticket->assignedUser->name : '')),
                    'time' => optional($ticket->created_at)->format('g:i A') ?: 'Any time',
                    'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                    'badge' => $ticketTypeLabel,
                    'user_label' => 'Assigned to',
                    'user' => $ticket->assignedUser?->name ?: null,
                    'url' => route('tickets.show', $ticket),
                ];
            })
            ->values();

        $overdueSupports = (clone $assignedServiceQuery)
            ->whereNotNull('visit_date')
            ->whereDate('visit_date', '<', $today)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->whereDoesntHave('replies')
            ->orderBy('visit_date')
            ->latest('created_at')
            ->get()
            ->map(function (Ticket $ticket) {
                $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                return [
                    'type' => 'ticket',
                    'title' => $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                    'meta' => trim(
                        ($ticket->ticket_number ?: 'Ticket')
                        . ($ticket->customer_name ? ' | ' . $ticket->customer_name : '')
                        . ($ticket->visit_date ? ' | Visit: ' . $ticket->visit_date->format('d M Y') : '')
                    ),
                    'time' => $ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name : 'Unassigned',
                    'color' => '#dc2626',
                    'badge' => 'Overdue',
                    'user_label' => 'Assigned to',
                    'user' => $ticket->assignedUser?->name ?: null,
                    'url' => route('tickets.show', $ticket),
                ];
            })
            ->values();

        $summaryCards = [
            ['label' => 'Schedule', 'count' => $schedule->count(), 'note' => $rangeLabel . ' tasks and deadlines'],
            ['label' => 'Reminders', 'count' => $reminders->count(), 'note' => $rangeLabel . ' AMC reminders'],
            ['label' => 'Pending Leads', 'count' => $pendingLeads->count(), 'note' => 'Open leads needing follow-up'],
            ['label' => 'Overdue Leads', 'count' => $overdueLeads->count(), 'note' => 'Follow-ups that missed their date'],
            ['label' => 'Leads', 'count' => $newLeads->count(), 'note' => 'New leads in this period'],
            ['label' => 'Projects', 'count' => $newProjects->count(), 'note' => 'New projects in this period'],
            ['label' => 'AMC', 'count' => $newAmcs->count(), 'note' => 'New AMC records in this period'],
            ['label' => 'Support', 'count' => $newSupports->count(), 'note' => 'New support tickets in this period'],
            ['label' => 'Overdue Support', 'count' => $overdueSupports->count(), 'note' => 'Visits that passed without action'],
            ['label' => 'Actions', 'count' => $activityFeed->count(), 'note' => 'Logged actions in this period'],
        ];

        $sections = collect([
            [
                'key' => 'schedule',
                'title' => $scheduleTitle,
                'subtitle' => $rangeLabel . ' follow-ups, deadlines, visits, and tasks.',
                'items' => $schedule,
                'empty' => 'No items for this period.',
            ],
            [
                'key' => 'reminders',
                'title' => $remindersTitle,
                'subtitle' => 'AMC visit and expiry reminders within the selected range.',
                'items' => $reminders,
                'empty' => 'No AMC reminders in this period.',
            ],
            [
                'key' => 'pending_leads',
                'title' => $pendingLeadsTitle,
                'subtitle' => 'Open leads in the selected range with their assignees and next follow-up dates.',
                'items' => $pendingLeads,
                'empty' => 'No pending leads in this period.',
            ],
            [
                'key' => 'overdue_leads',
                'title' => $overdueLeadsTitle,
                'subtitle' => 'Leads with missed follow-up dates and no recorded action.',
                'items' => $overdueLeads,
                'empty' => 'No overdue leads right now.',
            ],
            [
                'key' => 'leads',
                'title' => $leadsTitle,
                'subtitle' => 'New leads assigned to you in the selected range.',
                'items' => $newLeads,
                'empty' => 'No new leads in this period.',
            ],
            [
                'key' => 'projects',
                'title' => $projectsTitle,
                'subtitle' => 'Projects you are assigned to in the selected range.',
                'items' => $newProjects,
                'empty' => 'No new projects in this period.',
            ],
            [
                'key' => 'amc',
                'title' => $amcTitle,
                'subtitle' => 'AMC records assigned to you in the selected range.',
                'items' => $newAmcs,
                'empty' => 'No new AMC records in this period.',
            ],
            [
                'key' => 'support',
                'title' => $supportTitle,
                'subtitle' => 'Support and complaint tickets assigned to you in the selected range.',
                'items' => $newSupports,
                'empty' => 'No new support tickets in this period.',
            ],
            [
                'key' => 'overdue_support',
                'title' => $overdueSupportTitle,
                'subtitle' => 'Support tickets with visit dates that have passed and no action yet.',
                'items' => $overdueSupports,
                'empty' => 'No overdue support tickets right now.',
            ],
            [
                'key' => 'actions',
                'title' => $actionsTitle,
                'subtitle' => 'Your activity recorded in the selected range.',
                'items' => $activityFeed,
                'empty' => 'No actions recorded in this period.',
            ],
        ]);

        return [
            'range_label' => $rangeLabel,
            'summary_cards' => $summaryCards,
            'sections' => $sections,
            'charts' => $charts,
            'snapshot_total' => $summaryCards ? collect($summaryCards)->sum('count') : 0,
            'totals' => [
                'schedule' => $schedule->count(),
                'reminders' => $reminders->count(),
                'pending_leads' => $pendingLeads->count(),
                'overdue_leads' => $overdueLeads->count(),
                'leads' => $newLeads->count(),
                'projects' => $newProjects->count(),
                'amc' => $newAmcs->count(),
                'support' => $newSupports->count(),
                'overdue_support' => $overdueSupports->count(),
                'actions' => $activityFeed->count(),
            ],
        ];
    }

    public function index(Request $request)
    {
        $authUser = auth()->user();

        if ($authUser?->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        $today = Carbon::today();
        $inactiveCutoff = Carbon::today()->subDays(15);

        $dashboardCounts = [
            'leads' => [
                'total' => 0,
                'follow_up_today' => 0,
                'inactive_15_days' => 0,
                'fresh' => 0,
                'quote' => 0,
                'won' => 0,
                'lost' => 0,
            ],
            'projects' => [
                'total' => 0,
                'inactive_15_days' => 0,
                'due_today' => 0,
                'drawing' => 0,
                'material' => 0,
                'delivery' => 0,
                'installation' => 0,
                'completed' => 0,
            ],
            'amc' => [
                'total' => 0,
            ],
            'supports' => [
                'total' => 0,
            ],
            'staff' => [
                'total' => 0,
                'present_today' => 0,
                'absent_today' => 0,
            ],
        ];

        $staffList = collect(); // default empty collection
        $leadPipelineChart = [
            'rows' => collect(),
            'total' => 0,
            'converted' => 0,
            'conversion_rate' => 0,
            'empty' => true,
        ];
        $dashboardOverviewCharts = [];
        $leadStatuses = collect();
        $leadEvents = collect();
        $projectEvents = collect();
        $amcEvents = collect();
        $calendarEvents = collect();
        $todaySchedule = collect();
        $amcReminders = collect();
        $todayActivityFeed = collect();
        $todayNewLeads = collect();
        $todayNewProjects = collect();
        $todayNewAmcs = collect();
        $todayNewSupports = collect();
        $todayTodaySections = collect();
        $dashboardRangePayloads = [];
        $dashboardRangePayload = null;
        $dashboardActiveTab = in_array(request('tab'), ['overview', 'today', 'week', 'month'], true) ? request('tab') : 'overview';
        $dashboardCalendarExpanded = request('calendar') === 'expanded';
        $companyPaymentSummary = [];
        $companyPaymentStaffSummary = collect();
        $companyPaymentDoughnutChart = ['empty' => true];
        $companyPaymentBarChart = ['empty' => true];
        $companyPaymentTabLabel = 'Overview';
        $accountingSummary = [
            'total_income' => 0,
            'total_expense' => 0,
            'net_profit' => 0,
            'today_income' => 0,
            'today_expense' => 0,
            'monthly_income' => 0,
            'monthly_expense' => 0,
        ];
        $accountingCharts = [
            'monthly_comparison' => ['labels' => [], 'income' => [], 'expense' => []],
            'profit_trend' => ['labels' => [], 'values' => []],
            'income_breakdown' => ['labels' => [], 'values' => []],
            'expense_breakdown' => ['labels' => [], 'values' => []],
        ];
        $dashboardSupportStaffLabel = 'All Staff';
        $dashboardSelectedStaff = null;
        $dashboardSelectedStaffId = null;
        $materialsPage = collect();
        $storageUsage = [
            'limit_bytes' => 100 * 1024 * 1024,
            'used_bytes' => 0,
            'remaining_bytes' => 0,
            'used_percent' => 0,
            'remaining_percent' => 100,
        ];
        $dashboardStaffTaskSummary = collect();
        $dashboardTaskBlockers = collect();

        $canViewCompanyDashboard = $authUser?->isCompanyAdmin();
        $canViewStaffDashboard = $authUser && ! $authUser->isSuperAdmin() && ! $canViewCompanyDashboard;

        if ($authUser && $authUser->company_id) {
            $dashboardStaffTaskSummary = app(DashboardStaffTaskSummaryService::class)->todayForUser($authUser);
        }

        if ($canViewCompanyDashboard && ($companyId = $authUser?->company_id)) {
            $accountingService = app(AccountingDashboardService::class);
            $staffList = Staff::query()
                ->where('company_id', $companyId)
                ->whereHas('user')
                ->with('user:id,name')
                ->latest()
                ->get();

            $dashboardSelectedStaffId = $request->integer('staff');
            $dashboardSelectedStaff = $dashboardSelectedStaffId
                ? $staffList->firstWhere('id', $dashboardSelectedStaffId)
                : null;
            $dashboardSupportStaffLabel = $dashboardSelectedStaff?->name ?: 'All Staff';
            $dashboardSupportStaffUser = $dashboardSelectedStaff?->user;
            $dashboardTaskBlockers = $this->dashboardTaskBlockers((int) $companyId, $authUser);

            // ======================
            // Leads
            // ======================
            $dashboardCounts['leads']['total'] = Lead::where('company_id', $companyId)->count();

            $companyFollowupSchedules = $this->leadFollowUpScheduleQuery($companyId);

            $dashboardCounts['leads']['followups_total'] = (clone $companyFollowupSchedules)->count();
            $dashboardCounts['leads']['follow_up_today'] = (clone $companyFollowupSchedules)
                ->whereDate('followup_date', $today)
                ->count();
            $dashboardCounts['leads']['followups_done_today'] = (clone $companyFollowupSchedules)
                ->whereDate('followup_date', $today)
                ->whereRaw('LOWER(status) = ?', ['done'])
                ->count();
            $dashboardCounts['leads']['followups_pending_today'] = (clone $companyFollowupSchedules)
                ->whereDate('followup_date', $today)
                ->whereRaw('LOWER(status) = ?', ['pending'])
                ->count();
            $dashboardCounts['leads']['missed_followups'] = (clone $companyFollowupSchedules)
                ->whereDate('followup_date', '<', $today)
                ->whereRaw('LOWER(status) = ?', ['pending'])
                ->whereHas('lead.status', fn ($query) => $query->whereRaw('LOWER(name) NOT IN (?, ?)', ['won', 'loss']))
                ->count();

            $dashboardCounts['leads']['inactive_15_days'] = Lead::where('company_id', $companyId)
                ->whereDoesntHave('activities', function ($query) use ($inactiveCutoff) {
                    $query->where(function ($activityQuery) use ($inactiveCutoff) {
                        $activityQuery->where('occurred_at', '>=', $inactiveCutoff)
                            ->orWhere(function ($q) use ($inactiveCutoff) {
                                $q->whereNull('occurred_at')
                                    ->where('created_at', '>=', $inactiveCutoff);
                            });
                    });
                })
                ->count();

            $dashboardCounts['leads']['fresh'] = Lead::where('company_id', $companyId)
                ->whereHas('status', fn ($query) =>
                    $query->whereRaw('LOWER(name) IN (?, ?)', ['fresh', 'fresh lead'])
                )->count();

            // $dashboardCounts['leads']['quote'] = Lead::where('company_id', $companyId)
            //     ->whereHas('status', fn ($query) =>
            //         $query->whereRaw('LOWER(name) = ?', ['quote'])
            //     )->count();

            $dashboardCounts['leads']['won'] = Lead::where('company_id', $companyId)
                ->whereHas('status', fn ($query) =>
                    $query->whereRaw('LOWER(name) = ?', ['won'])
                )->count();

            $dashboardCounts['leads']['lost'] = Lead::where('company_id', $companyId)
                ->whereHas('status', fn ($query) =>
                    $query->whereRaw('LOWER(name) = ?', ['lost'])
                )->count();

            // ======================
            // Projects
            // ======================
            $dashboardCounts['projects']['total'] = Project::where('company_id', $companyId)->count();

            $dashboardCounts['projects']['due_today'] = Project::where('company_id', $companyId)
                ->whereDate('end_date', $today)
                ->count();

            $dashboardCounts['projects']['inactive_15_days'] = Project::where('company_id', $companyId)
                ->whereDoesntHave('activities', function ($query) use ($inactiveCutoff) {
                    $query->where(function ($activityQuery) use ($inactiveCutoff) {
                        $activityQuery->where('occurred_at', '>=', $inactiveCutoff)
                            ->orWhere(function ($q) use ($inactiveCutoff) {
                                $q->whereNull('occurred_at')
                                    ->where('created_at', '>=', $inactiveCutoff);
                            });
                    });
                })
                ->count();

            $dashboardCounts['projects']['drawing'] = Project::where('company_id', $companyId)
                ->whereIn('current_stage', ['drawing', 'new-project'])->count();

            // $dashboardCounts['projects']['material'] = Project::where('company_id', $companyId)
                //     ->where('current_stage', 'material')->count();



            $dashboardCounts['projects']['installation'] = Project::where('company_id', $companyId)
                ->where('current_stage', 'installation')->count();

            $dashboardCounts['projects']['completed'] = Project::where('company_id', $companyId)
                ->where('current_stage', 'completed')->count();

            $dashboardCounts['amc']['total'] = Amc::where('company_id', $companyId)->count();

            $dashboardCounts['supports']['total'] = Ticket::where('company_id', $companyId)
                ->whereIn('type', ['support', 'complain'])
                ->count();

            $dashboardCounts['supports']['repeat_complaints'] = Ticket::where('company_id', $companyId)
                ->whereIn('type', ['support', 'complain'])
                ->sum('repeat_count');

            $dashboardCounts['supports']['repeat_percentage'] = $dashboardCounts['supports']['total'] > 0
                ? round(($dashboardCounts['supports']['repeat_complaints'] / $dashboardCounts['supports']['total']) * 100, 2)
                : 0;

            $supportAssignmentInsights = app(TicketAssignmentHistoryService::class)->companySummary($companyId);
            $dashboardCounts['supports']['total_reassignments'] = $supportAssignmentInsights['total_reassignments'] ?? 0;
            $dashboardCounts['supports']['average_repeat_before_reassignment'] = $supportAssignmentInsights['average_repeat_count_before_reassignment'] ?? 0;

            // ======================
            // Staff Counts
            // ======================
            $dashboardCounts['staff']['total'] = Staff::where('company_id', $companyId)
                ->whereHas('user')
                ->count();

            $dashboardCounts['staff']['present_today'] = StaffAttendance::whereDate('date', $today)
                ->whereIn('status', ['Present', 'Half Day'])
                ->whereHas('staff', fn ($query) => $query->where('company_id', $companyId))
                ->count();

            $dashboardCounts['staff']['absent_today'] = StaffAttendance::whereDate('date', $today)
                ->where('status', 'Absent')
                ->whereHas('staff', fn ($query) => $query->where('company_id', $companyId))
                ->count();

            $leadStatuses = LeadStatus::query()
                ->where('company_id', $companyId)
                ->orderBy('order')
                ->orderBy('id')
                ->get(['id', 'name', 'color']);

            $leadPipelineChart = $this->buildLeadPipelineChart($companyId);
            $dashboardOverviewCharts = $this->buildSupportAnalyticsCharts($companyId, $today);
            $paymentSummaryService = app(TicketPaymentSummaryService::class);
            $companyPaymentSummary = $paymentSummaryService->getPaymentSummary($companyId, $dashboardActiveTab);
            $companyPaymentStaffSummary = $paymentSummaryService->getStaffPaymentSummary($companyId, $dashboardActiveTab);
            $companyPaymentDoughnutChart = [
                'empty' => (int) ($companyPaymentSummary['total_invoices'] ?? 0) === 0,
                'type' => 'doughnut',
                'data' => [
                    'labels' => ['Paid Amount', 'Pending Amount'],
                    'datasets' => [[
                        'data' => [
                            (float) ($companyPaymentSummary['total_paid_amount'] ?? 0),
                            (float) ($companyPaymentSummary['total_pending_amount'] ?? 0),
                        ],
                        'backgroundColor' => ['#16a34a', '#f97316'],
                        'borderColor' => '#ffffff',
                        'borderWidth' => 2,
                    ]],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'cutout' => '68%',
                    'plugins' => [
                        'legend' => [
                            'position' => 'bottom',
                            'labels' => [
                                'usePointStyle' => true,
                                'pointStyle' => 'circle',
                            ],
                        ],
                    ],
                ],
            ];
            $companyPaymentStaffRows = $companyPaymentStaffSummary->reject(fn (array $row) => ($row['is_grand_total'] ?? false))->values();
            $companyPaymentBarChart = [
                'empty' => $companyPaymentStaffRows->isEmpty(),
                'type' => 'bar',
                'data' => [
                    'labels' => $companyPaymentStaffRows->pluck('staff_name')->all(),
                    'datasets' => [[
                        'label' => 'Collected Amount',
                        'data' => $companyPaymentStaffRows->pluck('paid_amount')->map(fn ($value) => (float) $value)->all(),
                        'backgroundColor' => '#0f172a',
                        'borderRadius' => 8,
                    ]],
                ],
                'options' => [
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => [
                        'legend' => [
                            'display' => false,
                        ],
                    ],
                    'scales' => [
                        'x' => [
                            'grid' => ['display' => false],
                        ],
                        'y' => [
                            'beginAtZero' => true,
                        ],
                    ],
                ],
            ];
            $companyPaymentTabLabel = $companyPaymentSummary['range_label'] ?? 'Overview';
            $accountingSummary = $accountingService->summaryForCompany((int) $companyId);
            $accountingCharts = $accountingService->chartDataForCompany((int) $companyId);

            // ======================
            // Material Todo (paginate)
            // ======================
            $materialsPage = ProjectMaterial::query()
                ->with('project:id,name,company_id')
                ->whereHas('project', fn ($q) => $q->where('company_id', $companyId))
                ->whereDate('created_at', $today)
                ->latest()
                ->select(['id', 'project_id', 'material_name', 'qty', 'created_at'])
                ->paginate(5, ['*'], 'materials_page');

            // ======================
            // Calendar events
            // ======================
            $leadEvents = $this->leadFollowUpScheduleQuery($companyId)
                ->whereNotNull('followup_date')
                ->orderBy('followup_date')
                ->orderBy('status')
                ->orderBy('id')
                ->get()
                ->map(fn (LeadFollowupSchedule $schedule) => $this->leadFollowUpCalendarEvent($schedule))
                ->filter()
                ->sortBy(fn (array $event) => [$event['start'] ?? '', $event['statusRank'] ?? 50, $event['title'] ?? ''])
                ->values();

            $projectEvents = Project::where('company_id', $companyId)
                ->get()
                ->filter(fn (Project $project) => !empty($project->end_date))
                ->map(function (Project $project) {
                    $endDate = $project->end_date?->toDateString();

                    return [
                        'title' => trim($project->name ?: 'Project'),
                        'start' => $endDate,
                        'allDay' => true,
                        'url' => route('projects.show', $project),
                        'type' => 'project',
                        'color' => '#059669',
                        'displayTitle' => trim($project->name ?: 'Project'),
                        'tooltip' => trim($project->name ?: 'Project')
                            . ' | End date: ' . ($endDate ?: 'Not set'),
                    ];
                })
                ->values();

            $amcEvents = AmcVisit::query()
                ->with(['amc:id,company_id,contract_no,customer_name,project_name'])
                ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
                ->whereNotNull('visit_date')
                ->whereIn('status', ['pending', 'scheduled'])
                ->orderBy('visit_date')
                ->get()
                ->map(function (AmcVisit $visit) {
                    $visitDate = $visit->visit_date?->toDateString();
                    $amc = $visit->amc;

                    return [
                        'title' => trim(($amc?->project_name ?: $amc?->customer_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no),
                        'start' => $visitDate,
                        'allDay' => true,
                        'url' => $amc ? route('amcs.show', $amc) : null,
                        'type' => 'amc',
                        'color' => '#f59e0b',
                        'displayTitle' => trim(($amc?->project_name ?: 'AMC Service') . ' - V' . $visit->visit_no),
                        'tooltip' => trim(($amc?->contract_no ?: 'AMC') . ' | '
                            . ($amc?->customer_name ?: 'Customer') . ' | '
                            . ($amc?->project_name ?: 'Project') . ' | Visit ' . $visit->visit_no
                            . ' | ' . ($visitDate ?: 'Not set')
                            . ($visit->technician ? ' | Technician: ' . $visit->technician : '')),
                    ];
                })
                ->values();

            $ticketEvents = Ticket::query()
                ->with(['assignedUser:id,name'])
                ->where('company_id', $companyId)
                ->whereIn('type', ['support', 'complain'])
                ->whereNotNull('visit_date')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->orderBy('visit_date')
                ->orderBy('ticket_number')
                ->get()
                ->map(function (Ticket $ticket) {
                    $visitDate = $ticket->visit_date?->toDateString();
                    $ticketTypeLabel = $ticket->type_label ?: 'Ticket';
                    $ticketColor = $ticket->type === 'complain' ? '#f97316' : '#14b8a6';

                    return [
                        'title' => Str::limit(trim($ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket')), 48),
                        'start' => $visitDate,
                        'allDay' => true,
                        'url' => route('tickets.show', $ticket),
                        'type' => 'ticket',
                        'ticketType' => $ticket->type,
                        'color' => $ticketColor,
                        'displayTitle' => Str::limit(trim($ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket')), 34),
                        'tooltip' => trim(($ticket->ticket_number ?: 'Ticket') . ' | '
                            . $ticketTypeLabel . ' | '
                            . ($ticket->customer_name ?: 'Customer') . ' | '
                            . ($ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name . ' | ' : '')
                            . 'Status: ' . $ticket->status_label . ' | '
                            . 'Visit: ' . ($visitDate ?: 'Not set')),
                    ];
                })
                ->values();

            $calendarEvents = collect($leadEvents)->merge($projectEvents)->merge($amcEvents)->merge($ticketEvents);

            if (\Illuminate\Support\Facades\Schema::hasTable('calendar_events')) {
                $calendarEvents = collect($calendarEvents)->merge(
                    $this->companyAdminCalendarEventsQuery($companyId)
                        ->latest()
                        ->get()
                        ->map(function (CalendarEvent $event) {
                            $isDone = $event->status === 'done';

                            return [
                                'title' => $event->title,
                                'start' => optional($event->event_date)->toDateString(),
                                'allDay' => true,
                                'type' => 'custom-task',
                                'color' => $isDone ? '#16a34a' : '#8b5cf6',
                                'displayTitle' => $isDone ? $event->title . ' (Done)' : $event->title,
                                'location' => $event->location,
                                'description' => $event->description,
                                'eventDate' => optional($event->event_date)->toDateString(),
                                'eventId' => $event->id,
                                'eventType' => $event->event_type,
                                'status' => $event->status,
                                'updateUrl' => route('dashboard.calendar-events.update', $event),
                                'deleteUrl' => route('dashboard.calendar-events.destroy', $event),
                                'tooltip' => trim($event->title)
                                    . ' | Status: ' . ucfirst($event->status)
                                    . ($event->location ? ' | Location: ' . $event->location : '')
                                    . ($event->description ? ' | ' . $event->description : ''),
                            ];
                        })
                        ->unique(function (array $event) {
                            return implode('|', [
                                trim((string) ($event['title'] ?? '')),
                                trim((string) ($event['start'] ?? '')),
                                trim((string) ($event['eventType'] ?? '')),
                                trim((string) ($event['location'] ?? '')),
                            ]);
                        })
                        ->values()
                );
            }

            $calendarEvents = $calendarEvents
                ->when($dashboardCounts['staff']['absent_today'] > 0, function ($events) use ($today, $dashboardCounts) {
                    return $events->push([
                        'title' => 'Absent Today: ' . $dashboardCounts['staff']['absent_today'],
                        'start' => $today->toDateString(),
                        'allDay' => true,
                        'type' => 'absent',
                        'color' => '#ef4444',
                        'displayTitle' => 'Absent Today',
                        'tooltip' => 'Absent Today: ' . $dashboardCounts['staff']['absent_today'],
                    ]);
                })
                // ->when($dashboardCounts['projects']['inactive_15_days'] > 0, function ($events) use ($today, $dashboardCounts) {
                //     return $events->push([
                //         'title' => 'No Activity (15d): ' . $dashboardCounts['projects']['inactive_15_days'],
                //         'start' => $today->toDateString(),
                //         'allDay' => true,
                //         'type' => 'inactive-project',
                //         'color' => '#f59e0b',
                //         'displayTitle' => 'No Activity (15d)',
                //         'tooltip' => 'Projects with no activity for the last 15 days: ' . $dashboardCounts['projects']['inactive_15_days'],
                //     ]);
                // })
                ->sortBy('start')
                ->values();

            $todaySchedule = collect()
                ->merge(
                    $this->leadFollowUpScheduleQuery($companyId)
                        ->whereDate('followup_date', $today)
                        ->orderByRaw("CASE LOWER(status)
                            WHEN 'pending' THEN 1
                            WHEN 'callback' THEN 2
                            WHEN 'follow-up required' THEN 3
                            WHEN 'follow up required' THEN 3
                            WHEN 'not interested' THEN 4
                            WHEN 'converted' THEN 5
                            WHEN 'done' THEN 99
                            ELSE 50 END")
                        ->orderBy('followup_date')
                        ->get()
                        ->map(function (LeadFollowupSchedule $schedule) {
                            $lead = $schedule->lead;
                            if (! $lead) {
                                return null;
                            }
                            $followUpDate = optional($schedule->followup_date)->toDateString();
                            $statusName = Str::headline(str_replace(['_', '-'], ' ', (string) $schedule->status ?: 'pending'));
                            $leadName = $lead?->client_name ?: 'Lead';
                            $isPendingSchedule = strtolower(trim((string) $schedule->status)) === 'pending';

                            return [
                                'type' => 'lead',
                                'title' => $isPendingSchedule ? 'Follow up : ' . $leadName : $leadName,
                                'meta' => $statusName,
                                'assigned_users' => $lead?->assignedUsers?->pluck('name')->filter()->values()->all() ?? [],
                                'employee' => $schedule->creator?->name,
                                'time' => optional($schedule->followup_date)->format('g:i A') ?: 'All day',
                                'color' => $this->followUpCalendarDisplayColor((string) $schedule->status),
                                'url' => route('leads.show', $lead),
                                'lead_url' => route('leads.show', $lead),
                                'action_url' => route('leads.follow-up.save', $lead),
                                'details_url' => route('leads.follow-up.details', $lead),
                                'history_url' => route('leads.follow-up.history', $lead),
                                'follow_up_date' => $followUpDate,
                                'status_name' => $statusName,
                                'status_id' => $lead?->lead_status_id,
                                'schedule_id' => $schedule->id,
                            ];
                        })
                        ->filter()
                        ->values()
                )
                ->merge(
                    Project::where('company_id', $companyId)
                        ->whereDate('end_date', $today)
                        ->orderBy('name')
                        ->get()
                        ->map(function (Project $project) {
                            return [
                                'type' => 'project',
                                'title' => $project->name ?: 'Project',
                                'meta' => 'Project deadline',
                                'time' => 'Due today',
                                'color' => '#059669',
                                'url' => route('projects.show', $project),
                            ];
                        })
                )
                ->merge(
                    AmcVisit::query()
                        ->with(['amc:id,company_id,contract_no,customer_name,project_name'])
                        ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
                        ->whereDate('visit_date', $today)
                        ->whereIn('status', ['pending', 'scheduled'])
                        ->orderBy('visit_no')
                        ->get()
                        ->map(function (AmcVisit $visit) {
                            $amc = $visit->amc;

                            return [
                                'type' => 'amc',
                                'title' => ($amc?->project_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no,
                                'meta' => trim(($amc?->contract_no ?: 'AMC') . ' | ' . ($amc?->customer_name ?: 'Customer')),
                                'time' => $visit->technician ? 'Technician: ' . $visit->technician : 'Scheduled service',
                                'color' => '#f59e0b',
                                'url' => $amc ? route('amcs.show', $amc) : null,
                            ];
                        })
                )
                ->merge(
                    Ticket::query()
                        ->with(['assignedUser:id,name'])
                        ->where('company_id', $companyId)
                        ->whereIn('type', ['support', 'complain'])
                        ->whereDate('visit_date', $today)
                        ->whereNotIn('status', ['resolved', 'closed'])
                        ->orderBy('ticket_number')
                        ->get()
                        ->map(function (Ticket $ticket) {
                            $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                            return [
                                'type' => 'ticket',
                                'title' => $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                                'meta' => trim(($ticket->ticket_number ?: 'Ticket') . ' | ' . ($ticket->customer_name ?: 'Customer')),
                                'time' => $ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name : 'Scheduled visit',
                                'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                                'url' => route('tickets.show', $ticket),
                            ];
                        })
                )
                ->merge(
                    $this->companyAdminCalendarEventsQuery($companyId)
                        ->whereDate('event_date', $today)
                        ->orderBy('title')
                        ->get()
                        ->map(function (CalendarEvent $event) {
                            return [
                                'type' => 'custom-task',
                                'title' => $event->title,
                                'meta' => ucfirst($event->status) . ($event->location ? ' | ' . $event->location : ''),
                                'time' => ucfirst($event->event_type),
                                'color' => $event->status === 'done' ? '#16a34a' : '#8b5cf6',
                                'url' => null,
                            ];
                        })
                )
                ->when($dashboardCounts['staff']['absent_today'] > 0, function ($items) use ($dashboardCounts) {
                    return $items->push([
                        'type' => 'absent',
                        'title' => 'Absent Today',
                        'meta' => 'Staff marked absent',
                        'time' => (string) $dashboardCounts['staff']['absent_today'] . ' staff',
                        'color' => '#ef4444',
                        'url' => null,
                    ]);
                })
                // ->when($dashboardCounts['projects']['inactive_15_days'] > 0, function ($items) use ($dashboardCounts) {
                //     return $items->push([
                //         'type' => 'inactive-project',
                //         'title' => 'No Activity (15d)',
                //         'meta' => 'Projects with no activity',
                //         'time' => (string) $dashboardCounts['projects']['inactive_15_days'] . ' projects',
                //         'color' => '#f59e0b',
                //         'url' => null,
                //     ]);
                // })
                ->values();

            $nextSevenDays = $today->copy()->addDays(7);
            $thirtyDaysFromToday = $today->copy()->addDays(30);

            $upcomingVisitReminders = AmcVisit::query()
                ->with(['amc:id,company_id,contract_no,project_name,customer_name,end_date'])
                ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
                ->whereNotNull('visit_date')
                ->whereIn('status', ['pending', 'scheduled'])
                ->whereBetween('visit_date', [$today->toDateString(), $nextSevenDays->toDateString()])
                ->orderBy('visit_date')
                ->get()
                ->map(function (AmcVisit $visit) use ($today) {
                    $amc = $visit->amc;
                    $daysLeft = (int) $today->diffInDays($visit->visit_date, false);
                    $employeeName = $visit->technician ?: ($amc?->technician_name ?: 'Not Assigned');

                    return [
                        'type' => 'visit',
                        'title' => ($amc?->project_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no,
                        'meta' => ($amc?->contract_no ?: 'AMC') . ' | ' . ($amc?->customer_name ?: 'Customer'),
                        'employee' => $employeeName,
                        'date' => optional($visit->visit_date)->format('d M Y'),
                        'sort_date' => optional($visit->visit_date)?->toDateString(),
                        'url' => $amc ? route('amcs.show', $amc) : route('amcs.index'),
                        'badge' => $this->formatDaysLeftBadge($daysLeft),
                        'color' => '#f59e0b',
                    ];
                });

            $endingAmcReminders = Amc::query()
                ->where('company_id', $companyId)
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [$today->toDateString(), $thirtyDaysFromToday->toDateString()])
                ->orderBy('end_date')
                ->get()
                ->map(function (Amc $amc) use ($today) {
                    $daysLeft = (int) $today->diffInDays($amc->end_date, false);

                    return [
                        'type' => 'expiry',
                        'title' => ($amc->project_name ?: 'AMC Project') . ' AMC Ending Soon',
                        'meta' => ($amc->contract_no ?: 'AMC') . ' | ' . ($amc->customer_name ?: 'Customer'),
                        'employee' => $amc->technician_name ?: 'Not Assigned',
                        'date' => optional($amc->end_date)->format('d M Y'),
                        'sort_date' => optional($amc->end_date)?->toDateString(),
                        'url' => route('amcs.show', $amc),
                        'badge' => $this->formatDaysLeftBadge($daysLeft, 'Ends Today'),
                        'color' => '#dc2626',
                    ];
                });

            $amcReminders = $upcomingVisitReminders
                ->concat($endingAmcReminders)
                ->sortBy('sort_date')
                ->values();

            $todayActivityFeed = $this->buildTodayActivityFeed($companyId, $today, null, 20);

            $todayNewLeads = Lead::query()
                ->where('company_id', $companyId)
                ->whereDate('created_at', $today)
                ->with('status:id,name')
                ->latest('created_at')
                ->get()
                ->map(function (Lead $lead) {
                    return [
                        'type' => 'lead',
                        'title' => $lead->client_name ?: 'Lead',
                        'meta' => trim(($lead->status?->name ?: 'New lead') . ($lead->source ? ' | ' . $lead->source : '')),
                        'time' => optional($lead->created_at)->format('g:i A') ?: 'Today',
                        'color' => '#2563eb',
                        'badge' => 'Lead',
                        'url' => route('leads.show', $lead),
                    ];
                })
                ->values();

            $todayNewProjects = Project::query()
                ->where('company_id', $companyId)
                ->whereDate('created_at', $today)
                ->latest('created_at')
                ->get()
                ->map(function (Project $project) {
                    return [
                        'type' => 'project',
                        'title' => $project->name ?: 'Project',
                        'meta' => trim(($project->current_stage ? Str::headline($project->current_stage) : 'New project') . ($project->reference_no ? ' | ' . $project->reference_no : '')),
                        'time' => optional($project->created_at)->format('g:i A') ?: 'Today',
                        'color' => '#059669',
                        'badge' => 'Project',
                        'url' => route('projects.show', $project),
                    ];
                })
                ->values();

            $todayNewAmcs = Amc::query()
                ->where('company_id', $companyId)
                ->whereDate('created_at', $today)
                ->latest('created_at')
                ->get()
                ->map(function (Amc $amc) {
                    return [
                        'type' => 'amc',
                        'title' => $amc->project_name ?: 'AMC',
                        'meta' => trim(($amc->contract_no ?: 'New AMC') . ($amc->customer_name ? ' | ' . $amc->customer_name : '')),
                        'time' => optional($amc->created_at)->format('g:i A') ?: 'Today',
                        'color' => '#f59e0b',
                        'badge' => 'AMC',
                        'url' => route('amcs.show', $amc),
                    ];
                })
                ->values();

            $todayNewSupports = Ticket::query()
                ->with(['assignedUser:id,name'])
                ->where('company_id', $companyId)
                ->whereIn('type', ['support', 'complain'])
                ->whereDate('created_at', $today)
                ->latest('created_at')
                ->get()
                ->map(function (Ticket $ticket) {
                    $ticketTypeLabel = $ticket->type_label ?: 'Ticket';

                    return [
                        'type' => 'ticket',
                        'title' => $ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket'),
                        'meta' => trim(($ticket->ticket_number ?: 'Ticket') . ($ticket->customer_name ? ' | ' . $ticket->customer_name : '')),
                        'time' => optional($ticket->created_at)->format('g:i A') ?: 'Today',
                        'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                        'badge' => $ticketTypeLabel,
                        'url' => route('tickets.show', $ticket),
                    ];
                })
                ->values();

            $todayTodaySections = collect([
                [
                    'key' => 'schedule',
                    'title' => "Today's Schedule",
                    'subtitle' => 'Follow-ups, deadlines, visits, and tasks due now.',
                    'items' => $todaySchedule,
                    'empty' => 'No items for today.',
                ],
                [
                    'key' => 'reminders',
                    'title' => 'AMC Reminders',
                    'subtitle' => 'Upcoming AMC visits and expiry reminders.',
                    'items' => $amcReminders,
                    'empty' => 'No AMC reminders right now.',
                ],
                [
                    'key' => 'leads',
                    'title' => 'New Leads',
                    'subtitle' => 'Leads created today across the company.',
                    'items' => $todayNewLeads,
                    'empty' => 'No new leads today.',
                ],
                [
                    'key' => 'projects',
                    'title' => 'New Projects',
                    'subtitle' => 'Projects created today.',
                    'items' => $todayNewProjects,
                    'empty' => 'No new projects today.',
                ],
                [
                    'key' => 'amc',
                    'title' => 'New AMC',
                    'subtitle' => 'AMC contracts created today.',
                    'items' => $todayNewAmcs,
                    'empty' => 'No new AMC records today.',
                ],
                [
                    'key' => 'support',
                    'title' => 'New Support',
                    'subtitle' => 'Support and complaint tickets created today.',
                    'items' => $todayNewSupports,
                    'empty' => 'No new support tickets today.',
                ],
                [
                    'key' => 'actions',
                    'title' => 'Recent Actions',
                    'subtitle' => 'Cross-module actions and updates recorded today.',
                    'items' => $todayActivityFeed,
                    'empty' => 'No actions recorded today.',
                ],
            ]);

            $dashboardRangePayloads = [
                'today' => array_merge(
                    $this->buildCompanyDashboardRangeData($companyId, $today, $today, 'Today'),
                    [
                        'support_summary' => $this->buildSupportStatusSummaryForRange($companyId, $today, $today, $dashboardSupportStaffUser),
                    ]
                ),
                'week' => array_merge(
                    $this->buildCompanyDashboardRangeData(
                        $companyId,
                        $today->copy()->startOfWeek(),
                        $today->copy()->endOfWeek(),
                        'This Week'
                    ),
                    [
                        'support_summary' => $this->buildSupportStatusSummaryForRange(
                            $companyId,
                            $today->copy()->startOfWeek(),
                            $today->copy()->endOfWeek(),
                            $dashboardSupportStaffUser
                        ),
                    ]
                ),
                'month' => array_merge(
                    $this->buildCompanyDashboardRangeData(
                        $companyId,
                        $today->copy()->startOfMonth(),
                        $today->copy()->endOfMonth(),
                        'This Month'
                    ),
                    [
                        'support_summary' => $this->buildSupportStatusSummaryForRange(
                            $companyId,
                            $today->copy()->startOfMonth(),
                            $today->copy()->endOfMonth(),
                            $dashboardSupportStaffUser
                        ),
                    ]
                ),
            ];
            $dashboardRangePayload = $dashboardRangePayloads[$dashboardActiveTab] ?? $dashboardRangePayloads['today'];

            // ======================
            // Storage usage (all uploads for this company)
            // ======================
            $limitBytes = app(SubscriptionLimitService::class)->storageLimitBytes((int) $companyId);
            $usedBytes = app(CompanyStorageUsage::class)->usedBytes((int) $companyId);
            $remainingBytes = max(0, $limitBytes - $usedBytes);
            $usedPercent = $limitBytes > 0 ? min(100, round(($usedBytes / $limitBytes) * 100, 1)) : 0;
            $remainingPercent = max(0, 100 - $usedPercent);

            $storageUsage = [
                'limit_bytes' => $limitBytes,
                'used_bytes' => $usedBytes,
                'remaining_bytes' => $remainingBytes,
                'used_percent' => $usedPercent,
                'remaining_percent' => $remainingPercent,
            ];
        } elseif ($canViewStaffDashboard && ($companyId = $authUser?->company_id)) {
            $staffUser = $authUser;
            $staffId = $staffUser->staff?->id;
            $dashboardTaskBlockers = $this->dashboardTaskBlockers((int) $companyId, $staffUser);

            $assignedLeadQuery = Lead::query()
                ->where('company_id', $companyId)
                ->accessibleTo($staffUser);

            $assignedProjectQuery = Project::query()
                ->where('company_id', $companyId)
                ->where(function (Builder $query) use ($staffUser): void {
                    $query->where('project_manager_id', $staffUser->id)
                        ->orWhereJsonContains('team_member_ids', (int) $staffUser->id)
                        ->orWhereJsonContains('team_member_ids', (string) $staffUser->id)
                        ->orWhereHas('lead', function (Builder $leadQuery) use ($staffUser): void {
                            $leadQuery->where('assigned_to', $staffUser->id)
                                ->orWhereHas('assignedUsers', function (Builder $assignedUsersQuery) use ($staffUser): void {
                                    $assignedUsersQuery->whereKey($staffUser->id);
                                });
                        });
                });

            $assignedServiceQuery = Ticket::query()
                ->with(['assignedUser:id,name'])
                ->where('company_id', $companyId)
                ->whereIn('type', ['support', 'complain'])
                ->where(function (Builder $query) use ($staffUser): void {
                    $query->where('assigned_to', $staffUser->id)
                        ->orWhere('user_id', $staffUser->id);

                    if (Schema::hasTable('ticket_user_assignments')) {
                        $query->orWhereHas('assignedUsers', function (Builder $assignedUsersQuery) use ($staffUser): void {
                            $assignedUsersQuery->whereKey($staffUser->id);
                        });
                    }
                });

            $assignedAmcQuery = $staffId
                ? Amc::query()
                    ->where('company_id', $companyId)
                    ->where('assigned_staff_id', $staffId)
                : Amc::query()->whereRaw('1 = 0');

            $dashboardCounts['leads']['total'] = (clone $assignedLeadQuery)->count();
            $staffFollowupSchedules = $this->leadFollowUpScheduleQuery($companyId, $staffUser);
            $dashboardCounts['leads']['followups_total'] = (clone $staffFollowupSchedules)->count();
            $dashboardCounts['leads']['follow_up_today'] = (clone $staffFollowupSchedules)
                ->whereDate('followup_date', $today)
                ->count();
            $dashboardCounts['leads']['followups_done_today'] = (clone $staffFollowupSchedules)
                ->whereDate('followup_date', $today)
                ->whereRaw('LOWER(status) = ?', ['done'])
                ->count();
            $dashboardCounts['leads']['followups_pending_today'] = (clone $staffFollowupSchedules)
                ->whereDate('followup_date', $today)
                ->whereRaw('LOWER(status) = ?', ['pending'])
                ->count();
            $dashboardCounts['leads']['missed_followups'] = (clone $staffFollowupSchedules)
                ->whereDate('followup_date', '<', $today)
                ->whereRaw('LOWER(status) = ?', ['pending'])
                ->whereHas('lead.status', fn ($query) => $query->whereRaw('LOWER(name) NOT IN (?, ?)', ['won', 'loss']))
                ->count();
            $dashboardCounts['leads']['inactive_15_days'] = (clone $assignedLeadQuery)
                ->whereDoesntHave('activities', function ($query) use ($inactiveCutoff) {
                    $query->where(function ($activityQuery) use ($inactiveCutoff) {
                        $activityQuery->where('occurred_at', '>=', $inactiveCutoff)
                            ->orWhere(function ($q) use ($inactiveCutoff) {
                                $q->whereNull('occurred_at')
                                    ->where('created_at', '>=', $inactiveCutoff);
                            });
                    });
                })
                ->count();
            $dashboardCounts['leads']['fresh'] = (clone $assignedLeadQuery)
                ->whereHas('status', fn ($query) =>
                    $query->whereRaw('LOWER(name) IN (?, ?)', ['fresh', 'fresh lead'])
                )->count();
            $dashboardCounts['leads']['won'] = (clone $assignedLeadQuery)
                ->whereHas('status', fn ($query) =>
                    $query->whereRaw('LOWER(name) = ?', ['won'])
                )->count();
            $dashboardCounts['leads']['lost'] = (clone $assignedLeadQuery)
                ->whereHas('status', fn ($query) =>
                    $query->whereRaw('LOWER(name) = ?', ['lost'])
                )->count();

            $dashboardCounts['projects']['total'] = (clone $assignedProjectQuery)->count();
            $dashboardCounts['projects']['due_today'] = (clone $assignedProjectQuery)
                ->whereDate('end_date', $today)
                ->count();
            $dashboardCounts['projects']['inactive_15_days'] = (clone $assignedProjectQuery)
                ->whereDoesntHave('activities', function ($query) use ($inactiveCutoff) {
                    $query->where(function ($activityQuery) use ($inactiveCutoff) {
                        $activityQuery->where('occurred_at', '>=', $inactiveCutoff)
                            ->orWhere(function ($q) use ($inactiveCutoff) {
                                $q->whereNull('occurred_at')
                                    ->where('created_at', '>=', $inactiveCutoff);
                            });
                    });
                })
                ->count();
            $dashboardCounts['projects']['drawing'] = (clone $assignedProjectQuery)
                ->whereIn('current_stage', ['drawing', 'new-project'])
                ->count();
            $dashboardCounts['projects']['installation'] = (clone $assignedProjectQuery)
                ->where('current_stage', 'installation')
                ->count();
            $dashboardCounts['projects']['completed'] = (clone $assignedProjectQuery)
                ->where('current_stage', 'completed')
                ->count();

            $dashboardCounts['services'] = [
                'total' => (clone $assignedServiceQuery)->count(),
                'open' => (clone $assignedServiceQuery)
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->count(),
                'due_today' => (clone $assignedServiceQuery)
                    ->whereDate('visit_date', $today)
                    ->whereNotIn('status', ['resolved', 'closed'])
                    ->count(),
                'amc_assigned' => (clone $assignedAmcQuery)->count(),
            ];

            $leadEvents = $this->leadFollowUpScheduleQuery($companyId, $staffUser)
                ->whereNotNull('followup_date')
                ->orderBy('followup_date')
                ->orderBy('status')
                ->orderBy('id')
                ->get()
                ->map(fn (LeadFollowupSchedule $schedule) => $this->leadFollowUpCalendarEvent($schedule))
                ->filter()
                ->sortBy(fn (array $event) => [$event['start'] ?? '', $event['statusRank'] ?? 50, $event['title'] ?? ''])
                ->values();

            $projectEvents = (clone $assignedProjectQuery)
                ->get()
                ->filter(fn (Project $project) => ! empty($project->end_date))
                ->map(function (Project $project) {
                    $endDate = $project->end_date?->toDateString();

                    return [
                        'title' => trim($project->name ?: 'Project'),
                        'start' => $endDate,
                        'allDay' => true,
                        'url' => route('projects.show', $project),
                        'type' => 'project',
                        'color' => '#059669',
                        'displayTitle' => trim($project->name ?: 'Project'),
                        'tooltip' => trim($project->name ?: 'Project')
                            . ' | End date: ' . ($endDate ?: 'Not set'),
                    ];
                })
                ->values();

            $amcEvents = (clone $assignedAmcQuery)
                ->with(['assignedStaff:id,name', 'supportTicket:id,company_id,ticket_number,subject'])
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
                ->orderBy('end_date')
                ->get()
                ->map(function (Amc $amc) use ($today) {
                    $endDate = $amc->end_date?->toDateString();
                    $daysLeft = $endDate ? (int) $today->diffInDays($amc->end_date, false) : 0;

                    return [
                        'title' => trim(($amc->project_name ?: 'AMC Service') . ' - Ending Soon'),
                        'start' => $endDate,
                        'allDay' => true,
                        'url' => route('amcs.show', $amc),
                        'type' => 'amc',
                        'color' => '#f59e0b',
                        'displayTitle' => trim(($amc->project_name ?: 'AMC Service') . ' - Ending Soon'),
                        'tooltip' => trim(($amc->contract_no ?: 'AMC') . ' | '
                            . ($amc->customer_name ?: 'Customer') . ' | '
                            . ($amc->project_name ?: 'Project') . ' | '
                            . 'Ends: ' . ($endDate ?: 'Not set')
                            . ($daysLeft ? ' | ' . $this->formatDaysLeftBadge($daysLeft, 'Ends Today') : '')),
                    ];
                })
                ->values();

            $ticketEvents = (clone $assignedServiceQuery)
                ->whereNotNull('visit_date')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->orderBy('visit_date')
                ->orderBy('ticket_number')
                ->get()
                ->map(function (Ticket $ticket) {
                    $visitDate = $ticket->visit_date?->toDateString();
                    $ticketTypeLabel = $ticket->type_label ?: 'Ticket';
                    $ticketColor = $ticket->type === 'complain' ? '#f97316' : '#14b8a6';

                    return [
                        'title' => Str::limit(trim($ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket')), 48),
                        'start' => $visitDate,
                        'allDay' => true,
                        'url' => route('tickets.show', $ticket),
                        'type' => 'ticket',
                        'ticketType' => $ticket->type,
                        'color' => $ticketColor,
                        'displayTitle' => Str::limit(trim($ticketTypeLabel . ': ' . ($ticket->subject ?: 'Ticket')), 34),
                        'tooltip' => trim(($ticket->ticket_number ?: 'Ticket') . ' | '
                            . $ticketTypeLabel . ' | '
                            . ($ticket->customer_name ?: 'Customer') . ' | '
                            . ($ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name . ' | ' : '')
                            . 'Status: ' . $ticket->status_label . ' | '
                            . 'Visit: ' . ($visitDate ?: 'Not set')),
                    ];
                })
                ->values();

            $calendarEvents = collect($leadEvents)
                ->merge($projectEvents)
                ->merge($amcEvents)
                ->merge($ticketEvents);

            if (\Illuminate\Support\Facades\Schema::hasTable('calendar_events')) {
                $calendarEvents = collect($calendarEvents)->merge(
                    CalendarEvent::where('company_id', $companyId)
                        ->where('user_id', $staffUser->id)
                        ->where('visibility_scope', 'private')
                        ->latest()
                        ->get()
                        ->map(function (CalendarEvent $event) {
                            $isDone = $event->status === 'done';

                            return [
                                'title' => $event->title,
                                'start' => optional($event->event_date)->toDateString(),
                                'allDay' => true,
                                'type' => 'custom-task',
                                'color' => $isDone ? '#16a34a' : '#8b5cf6',
                                'displayTitle' => $isDone ? $event->title . ' (Done)' : $event->title,
                                'location' => $event->location,
                                'description' => $event->description,
                                'eventDate' => optional($event->event_date)->toDateString(),
                                'eventId' => $event->id,
                                'eventType' => $event->event_type,
                                'status' => $event->status,
                                'updateUrl' => route('dashboard.calendar-events.update', $event),
                                'deleteUrl' => route('dashboard.calendar-events.destroy', $event),
                                'tooltip' => trim($event->title)
                                    . ' | Status: ' . ucfirst($event->status)
                                    . ($event->location ? ' | Location: ' . $event->location : '')
                                    . ($event->description ? ' | ' . $event->description : ''),
                            ];
                        })
                        ->unique(function (array $event) {
                            return implode('|', [
                                trim((string) ($event['title'] ?? '')),
                                trim((string) ($event['start'] ?? '')),
                                trim((string) ($event['eventType'] ?? '')),
                                trim((string) ($event['location'] ?? '')),
                            ]);
                        })
                        ->values()
                );
            }

            $calendarEvents = $calendarEvents->sortBy('start')->values();

            $todaySchedule = collect()
                ->merge(
                    $this->leadFollowUpScheduleQuery($companyId, $staffUser)
                        ->whereDate('followup_date', $today)
                        ->orderByRaw("CASE LOWER(status)
                            WHEN 'pending' THEN 1
                            WHEN 'callback' THEN 2
                            WHEN 'follow-up required' THEN 3
                            WHEN 'follow up required' THEN 3
                            WHEN 'not interested' THEN 4
                            WHEN 'converted' THEN 5
                            WHEN 'done' THEN 99
                            ELSE 50 END")
                        ->orderBy('followup_date')
                        ->get()
                        ->map(function (LeadFollowupSchedule $schedule) {
                            $lead = $schedule->lead;
                            if (! $lead) {
                                return null;
                            }
                            $statusName = Str::headline(str_replace(['_', '-'], ' ', (string) $schedule->status ?: 'pending'));
                            $leadName = $lead?->client_name ?: 'Lead';
                            $isPendingSchedule = strtolower(trim((string) $schedule->status)) === 'pending';

                            return [
                                'type' => 'lead',
                                'title' => $isPendingSchedule ? 'Follow up : ' . $leadName : $leadName,
                                'meta' => $statusName,
                                'assigned_users' => $lead?->assignedUsers?->pluck('name')->filter()->values()->all() ?? [],
                                'employee' => $schedule->creator?->name,
                                'time' => optional($schedule->followup_date)->format('g:i A') ?: 'All day',
                                'color' => $this->followUpCalendarDisplayColor((string) $schedule->status),
                                'url' => route('leads.show', $lead),
                                'lead_url' => route('leads.show', $lead),
                                'action_url' => route('leads.follow-up.save', $lead),
                                'details_url' => route('leads.follow-up.details', $lead),
                                'history_url' => route('leads.follow-up.history', $lead),
                                'follow_up_date' => optional($schedule->followup_date)->toDateString(),
                                'status_name' => $statusName,
                                'status_id' => $lead?->lead_status_id,
                                'schedule_id' => $schedule->id,
                            ];
                        })
                        ->filter()
                        ->values()
                )
                ->merge(
                    (clone $assignedProjectQuery)
                        ->whereDate('end_date', $today)
                        ->orderBy('name')
                        ->get()
                        ->map(function (Project $project) {
                            return [
                                'type' => 'project',
                                'title' => $project->name ?: 'Project',
                                'meta' => 'Project deadline',
                                'time' => 'Due today',
                                'color' => '#059669',
                                'url' => route('projects.show', $project),
                            ];
                        })
                )
                ->merge(
                    (clone $assignedServiceQuery)
                        ->whereDate('visit_date', $today)
                        ->whereNotIn('status', ['resolved', 'closed'])
                        ->orderBy('ticket_number')
                        ->get()
                        ->map(function (Ticket $ticket) {
                            return [
                                'type' => 'service',
                                'title' => ($ticket->type_label ?: 'Ticket') . ': ' . ($ticket->subject ?: 'Ticket'),
                                'meta' => trim(($ticket->ticket_number ?: 'Ticket') . ' | ' . ($ticket->customer_name ?: 'Customer')),
                                'time' => $ticket->assignedUser?->name ? 'Assigned: ' . $ticket->assignedUser->name : 'Scheduled visit',
                                'color' => $ticket->type === 'complain' ? '#f97316' : '#14b8a6',
                                'url' => route('tickets.show', $ticket),
                            ];
                        })
                )
                ->merge(
                    (clone $assignedAmcQuery)
                        ->whereNotNull('end_date')
                        ->whereDate('end_date', $today)
                        ->orderBy('project_name')
                        ->get()
                        ->map(function (Amc $amc) {
                            return [
                                'type' => 'amc',
                                'title' => ($amc->project_name ?: 'AMC Project') . ' AMC Ending Today',
                                'meta' => ($amc->contract_no ?: 'AMC') . ' | ' . ($amc->customer_name ?: 'Customer'),
                                'time' => $amc->assignedStaff?->name ? 'Technician: ' . $amc->assignedStaff->name : 'Ending today',
                                'color' => '#f59e0b',
                                'url' => route('amcs.show', $amc),
                            ];
                        })
                )
                ->merge(
                    CalendarEvent::where('company_id', $companyId)
                        ->where('user_id', $staffUser->id)
                        ->where('visibility_scope', 'private')
                        ->whereDate('event_date', $today)
                        ->orderBy('title')
                        ->get()
                        ->map(function (CalendarEvent $event) {
                            return [
                                'type' => 'custom-task',
                                'title' => $event->title,
                                'meta' => ucfirst($event->status) . ($event->location ? ' | ' . $event->location : ''),
                                'time' => ucfirst($event->event_type),
                                'color' => $event->status === 'done' ? '#16a34a' : '#8b5cf6',
                                'url' => null,
                            ];
                        })
                )
                ->values();

            $amcReminders = (clone $assignedAmcQuery)
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [$today->toDateString(), $today->copy()->addDays(30)->toDateString()])
                ->orderBy('end_date')
                ->get()
                ->map(function (Amc $amc) use ($today) {
                    $daysLeft = (int) $today->diffInDays($amc->end_date, false);

                    return [
                        'type' => 'expiry',
                        'title' => ($amc->project_name ?: 'AMC Project') . ' AMC Ending Soon',
                        'meta' => ($amc->contract_no ?: 'AMC') . ' | ' . ($amc->customer_name ?: 'Customer'),
                        'employee' => $amc->assignedStaff?->name ?: 'Not Assigned',
                        'date' => optional($amc->end_date)->format('d M Y'),
                        'sort_date' => optional($amc->end_date)?->toDateString(),
                        'url' => route('amcs.show', $amc),
                        'badge' => $this->formatDaysLeftBadge($daysLeft, 'Ends Today'),
                        'color' => '#dc2626',
                    ];
                });

            $todayActivityFeed = $this->buildTodayActivityFeed($companyId, $today, $staffUser->id, 8);
            $todayTodaySections = collect([
                [
                    'key' => 'schedule',
                    'title' => "Today's Assignments",
                    'subtitle' => 'Follow-ups, deadlines, visits, tasks, and your day plan.',
                    'items' => $todaySchedule,
                    'empty' => 'No assignments for today.',
                ],
                [
                    'key' => 'reminders',
                    'title' => 'Service Reminders',
                    'subtitle' => 'Your assigned AMC reminders and service deadlines.',
                    'items' => $amcReminders,
                    'empty' => 'No service reminders right now.',
                ],
                [
                    'key' => 'actions',
                    'title' => 'Recent Actions',
                    'subtitle' => 'Your activity recorded today.',
                    'items' => $todayActivityFeed,
                    'empty' => 'No actions recorded today.',
                ],
            ]);

            $dashboardRangePayloads = [
                'today' => $this->buildStaffDashboardRangeData($companyId, $staffUser, $today, $today, 'Today'),
                'week' => $this->buildStaffDashboardRangeData(
                    $companyId,
                    $staffUser,
                    $today->copy()->startOfWeek(),
                    $today->copy()->endOfWeek(),
                    'This Week'
                ),
                'month' => $this->buildStaffDashboardRangeData(
                    $companyId,
                    $staffUser,
                    $today->copy()->startOfMonth(),
                    $today->copy()->endOfMonth(),
                    'This Month'
                ),
            ];
            $dashboardRangePayload = $dashboardRangePayloads[$dashboardActiveTab] ?? $dashboardRangePayloads['today'];

            $materialsPage = collect();
            $staffList = collect();
        }
        $leadPipelinePayload = $leadPipelineChart;

        return view('dashboard', compact('dashboardCounts', 'staffList', 'leadPipelineChart', 'leadPipelinePayload', 'dashboardOverviewCharts', 'leadStatuses', 'leadEvents', 'projectEvents', 'amcEvents', 'calendarEvents', 'todaySchedule', 'amcReminders', 'materialsPage', 'storageUsage', 'canViewCompanyDashboard', 'canViewStaffDashboard', 'todayActivityFeed', 'todayNewLeads', 'todayNewProjects', 'todayNewAmcs', 'todayNewSupports', 'todayTodaySections', 'dashboardActiveTab', 'dashboardCalendarExpanded', 'dashboardRangePayloads', 'dashboardRangePayload', 'companyPaymentSummary', 'companyPaymentStaffSummary', 'companyPaymentDoughnutChart', 'companyPaymentBarChart', 'companyPaymentTabLabel', 'accountingSummary', 'accountingCharts', 'dashboardStaffTaskSummary', 'dashboardTaskBlockers'));
    }

    public function calendar()
    {
        $authUser = auth()->user();

        if ($authUser?->isSuperAdmin()) {
            return redirect()->route('super-admin.dashboard');
        }

        $companyId = $authUser?->company_id;

        if (! $companyId) {
            abort(403, 'Company context required.');
        }

        $calendarEvents = $authUser && ! $authUser->isCompanyAdmin()
            ? $this->buildStaffCalendarEvents((int) $companyId, $authUser, Carbon::today())
            : $this->buildCompanyCalendarEvents((int) $companyId, Carbon::today());

        $leadStatuses = LeadStatus::query()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->orderBy('id')
            ->get(['id', 'name']);

        return view('dashboard-calendar', [
            'calendarEvents' => $calendarEvents,
            'leadStatuses' => $leadStatuses,
        ]);
    }

    public function calendarEvents()
    {
        $authUser = auth()->user();

        if ($authUser?->isSuperAdmin()) {
            return response()->json([]);
        }

        $companyId = $authUser?->company_id;

        if (! $companyId) {
            abort(403, 'Company context required.');
        }

        $events = $authUser && ! $authUser->isCompanyAdmin()
            ? $this->buildStaffCalendarEvents((int) $companyId, $authUser, Carbon::today())
            : $this->buildCompanyCalendarEvents((int) $companyId, Carbon::today());

        return response()->json($events);
    }

    public function leadFollowUpDetails(Lead $lead)
    {
        return response()->json($this->leadFollowUpPayload($lead));
    }

    public function leadFollowUpHistory(Lead $lead)
    {
        $lead->loadMissing(['followupHistories' => function ($query) {
            $query->latest();
        }]);

        return response()->json([
            'history' => $lead->followupHistories
                ->sortByDesc('created_at')
                ->values()
                ->map(function (LeadFollowupHistory $item) {
                    return [
                        'id' => $item->id,
                        'summary' => $item->summary,
                        'status' => $item->status,
                        'followup_date' => $item->followup_date?->toDateString(),
                        'next_followup_date' => $item->next_followup_date?->toDateString(),
                        'created_at' => $item->created_at?->toIso8601String(),
                        'created_by' => $item->created_by,
                        'created_by_name' => $item->creator?->name,
                    ];
                }),
        ]);
    }

    public function saveLeadFollowUp(Request $request, Lead $lead)
    {
        $lastFollowUpStatus = $lead->followupHistories()->latest()->value('status');

        if (strtolower(trim((string) $lastFollowUpStatus)) === 'done') {
            return response()->json([
                'success' => false,
                'message' => 'Lead next follow up already done.',
            ], 422);
        }

        $validated = $request->validate([
            'schedule_id' => ['nullable', 'integer'],
            'summary' => ['required', 'string', 'max:5000'],
            'next_followup_date' => ['required', 'date', 'after_or_equal:today'],
            'status' => ['required', 'string', Rule::in($this->followUpStatusValues())],
        ]);

        $schedule = filled($validated['schedule_id'] ?? null)
            ? $lead->followupSchedules()->whereKey((int) $validated['schedule_id'])->first()
            : null;

        $schedule ??= $lead->followupSchedules()
            ->whereRaw('LOWER(status) = ?', ['pending'])
            ->orderBy('followup_date')
            ->first();

        $result = DB::transaction(function () use ($lead, $schedule, $validated) {
            $currentFollowUpDate = $schedule?->followup_date?->toDateString()
                ?: $lead->next_follow_up?->toDateString()
                ?: Carbon::today()->toDateString();

            $history = LeadFollowupHistory::create([
                'lead_id' => $lead->id,
                'summary' => $validated['summary'],
                'status' => $validated['status'],
                'followup_date' => $currentFollowUpDate,
                'next_followup_date' => $validated['next_followup_date'],
                'created_by' => auth()->id(),
            ]);

            if ($schedule) {
                $schedule->update([
                    'status' => 'done',
                    'completed_at' => now(),
                ]);
            }

            $nextSchedule = LeadFollowupSchedule::create([
                'lead_id' => $lead->id,
                'followup_date' => $validated['next_followup_date'],
                'status' => 'pending',
                'created_by' => auth()->id(),
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'follow_up',
                'description' => 'Follow-up saved with status ' . $validated['status'] . '; next follow-up on ' . Carbon::parse($validated['next_followup_date'])->toDateString(),
                'occurred_at' => now(),
            ]);

            if ($schedule) {
                $schedule->load(['lead.company:id,name', 'lead.status:id,name,color', 'lead.followupHistories' => function ($query) {
                    $query->latest()->limit(5);
                }]);
            }

            $nextSchedule->load(['lead.company:id,name', 'lead.status:id,name,color', 'lead.followupHistories' => function ($query) {
                $query->latest()->limit(5);
            }]);

            return [
                'history' => $history,
                'current_event' => $schedule ? $this->leadFollowUpCalendarEvent($schedule) : null,
                'next_event' => $this->leadFollowUpCalendarEvent($nextSchedule),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Follow-up saved and next schedule created successfully.',
            'lead' => $this->leadFollowUpPayload($lead->fresh()),
            'history' => [
                'id' => $result['history']->id,
                'summary' => $result['history']->summary,
                'status' => $result['history']->status,
                'followup_date' => $result['history']->followup_date?->toDateString(),
                'next_followup_date' => $result['history']->next_followup_date?->toDateString(),
                'created_at' => $result['history']->created_at?->toIso8601String(),
            ],
            'current_event' => $result['current_event'],
            'next_event' => $result['next_event'],
        ]);
    }

    public function storeCalendarEvent(\Illuminate\Http\Request $request)
    {
        $companyId = auth()->user()?->company_id;

        if (!$companyId) {
            abort(403, 'Company context required.');
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_date' => ['required', 'date'],
            'event_type' => ['nullable', 'in:task,appointment'],
            'status' => ['nullable', 'in:pending,done'],
        ]);

        CalendarEvent::create([
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'title' => $validated['title'],
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'event_type' => $validated['event_type'] ?? 'task',
            'status' => $validated['status'] ?? 'pending',
            'visibility_scope' => auth()->user()?->isCompanyAdmin() || auth()->user()?->isSuperAdmin() ? 'company' : 'private',
        ]);

        return redirect()->route('dashboard')->with('success', 'Calendar task added successfully.');
    }

    public function updateCalendarEvent(\Illuminate\Http\Request $request, CalendarEvent $calendarEvent)
    {
        $user = auth()->user();
        abort_unless((int) $calendarEvent->company_id === (int) $user?->company_id, 403);
        abort_unless($user && $this->canManageCalendarEvent($user, $calendarEvent), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'event_date' => ['required', 'date'],
            'event_type' => ['nullable', 'in:task,appointment'],
            'status' => ['required', 'in:pending,done'],
        ]);

        $calendarEvent->update([
            'title' => $validated['title'],
            'location' => $validated['location'] ?? null,
            'description' => $validated['description'] ?? null,
            'event_date' => $validated['event_date'],
            'event_type' => $validated['event_type'] ?? 'task',
            'status' => $validated['status'],
            'visibility_scope' => $calendarEvent->visibility_scope ?: (($user?->isCompanyAdmin() || $user?->isSuperAdmin()) ? 'company' : 'private'),
        ]);

        return redirect()->route('dashboard')->with('success', 'Calendar task updated successfully.');
    }

    public function destroyCalendarEvent(CalendarEvent $calendarEvent)
    {
        $user = auth()->user();
        abort_unless((int) $calendarEvent->company_id === (int) $user?->company_id, 403);
        abort_unless($user && $this->canManageCalendarEvent($user, $calendarEvent), 403);
        $calendarEvent->delete();

        return redirect()->route('dashboard')->with('success', 'Calendar task deleted successfully.');
    }
}
