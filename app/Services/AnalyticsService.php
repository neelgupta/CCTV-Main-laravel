<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AnalyticsService
{
    private const ONLINE_THRESHOLD_MINUTES = 15;

    public function getDashboardData(array $filters): array
    {
        [$from, $to] = $this->resolveDateRange($filters['period'] ?? 'weekly');
        $companyId = $filters['company_id'] ?? null;

        return [
            'generated_at' => now()->toIso8601String(),
            'filters' => [
                'period' => $filters['period'] ?? 'weekly',
                'company_id' => $companyId,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'users' => $this->getUserMetrics($companyId),
            'companies' => $this->getCompanyMetrics($from, $to, $companyId),
            'leads' => $this->getLeadMetrics($from, $to, $companyId),
            'projects' => $this->getProjectMetrics($from, $to, $companyId),
            'activity' => [
                'timeline' => $this->getActivityTimeline($from, $to, $companyId),
                'feed' => $this->getActivityFeed($companyId),
                'module_usage' => $this->getModuleUsage($from, $to, $companyId),
                'recent_users' => $this->getRecentlyActiveUsers($companyId),
            ],
            'charts' => [
                'activity_line' => $this->getActivityLineChart($from, $to, $companyId),
                'role_pie' => $this->getRolePieChart($companyId),
                'lead_status_pie' => $this->getLeadStatusPieChart($from, $to, $companyId),
                'lead_source_bar' => $this->getLeadSourceBarChart($from, $to, $companyId),
                'company_projects_bar' => $this->getCompanyProjectsBarChart($from, $to, $companyId),
            ],
        ];
    }

    public function getUserMetrics(?int $companyId = null): array
    {
        $query = User::query()->whereNotIn('role', ['super_admin', 'admin']);

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        $users = $query->get(['id', 'role', 'role_id', 'is_active']);
        $onlineUserIds = $this->onlineUserIds();

        $roleBreakdown = $this->buildRoleBreakdown($users);

        return [
            'total' => $users->count(),
            'active' => $users->where('is_active', true)->count(),
            'inactive' => $users->where('is_active', false)->count(),
            'online' => $users->whereIn('id', $onlineUserIds)->count(),
            'roles' => $roleBreakdown,
        ];
    }

    public function getCompanyMetrics(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $query = Company::query();

        if ($companyId) {
            $query->whereKey($companyId);
        }

        $companies = $query->get(['id', 'name', 'is_active']);
        $topCompanies = $this->getTopActiveCompanies($from, $to, $companyId);

        return [
            'total' => $companies->count(),
            'active' => $companies->where('is_active', true)->count(),
            'inactive' => $companies->where('is_active', false)->count(),
            'top_activity' => $topCompanies,
        ];
    }

    public function getLeadMetrics(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $query = $this->scopedLeadsQuery($companyId)
            ->whereBetween('created_at', [$from, $to]);

        $leads = $query->with('status:id,name')->get(['id', 'lead_status_id', 'source', 'company_id']);

        $statusGroups = [
            'new' => 0,
            'in_progress' => 0,
            'won' => 0,
            'lost' => 0,
        ];

        foreach ($leads as $lead) {
            $statusName = strtolower(trim((string) $lead->status?->name));
            $group = match (true) {
                in_array($statusName, ['new lead', 'fresh lead'], true) => 'new',
                $statusName === 'won' => 'won',
                $statusName === 'lost' => 'lost',
                default => 'in_progress',
            };
            $statusGroups[$group]++;
        }

        $sources = $leads
            ->groupBy(fn (Lead $lead) => $this->normalizeSource($lead->source))
            ->map(fn (Collection $group, string $source) => [
                'label' => $source,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->take(8)
            ->all();

        return [
            'total' => $leads->count(),
            'by_status' => $statusGroups,
            'by_source' => $sources,
        ];
    }

    public function getProjectMetrics(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $query = $this->scopedProjectsQuery($companyId)
            ->whereBetween('created_at', [$from, $to]);

        $projects = $query->get(['id', 'company_id', 'status', 'completion_percentage']);

        $statusGroups = [
            'ongoing' => 0,
            'completed' => 0,
            'delayed' => 0,
        ];

        foreach ($projects as $project) {
            $status = strtolower(trim((string) $project->status));
            $group = match ($status) {
                'completed' => 'completed',
                'delayed' => 'delayed',
                default => 'ongoing',
            };
            $statusGroups[$group]++;
        }

        $byCompany = $projects
            ->groupBy('company_id')
            ->map(function (Collection $group, $companyKey) {
                $company = Company::query()->find($companyKey);

                return [
                    'company_id' => (int) $companyKey,
                    'company_name' => $company?->name ?? 'Unknown',
                    'total' => $group->count(),
                    'ongoing' => $group->filter(fn (Project $project) => ! in_array(strtolower((string) $project->status), ['completed', 'delayed'], true))->count(),
                    'completed' => $group->where('status', 'completed')->count(),
                    'delayed' => $group->where('status', 'delayed')->count(),
                ];
            })
            ->sortByDesc('total')
            ->values()
            ->take(10)
            ->all();

        return [
            'total' => $projects->count(),
            'by_status' => $statusGroups,
            'by_company' => $byCompany,
        ];
    }

    public function getActivityFeed(?int $companyId = null, int $limit = 20): array
    {
        $activityLogs = ActivityLog::query()
            ->with(['user:id,name,email', 'company:id,name'])
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
            ->latest('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'source' => 'activity_log',
                'user_name' => $log->user?->name ?? 'System',
                'company_name' => $log->company?->name ?? 'Platform',
                'module' => $log->module,
                'action' => $log->action,
                'description' => $log->description,
                'occurred_at' => $log->created_at?->toIso8601String(),
                'occurred_label' => $log->created_at?->diffForHumans() ?? '-',
            ]);

        if ($activityLogs->isNotEmpty()) {
            return $activityLogs->all();
        }

        return $this->legacyActivityFeed($companyId, $limit);
    }

    public function getActivityTimeline(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        return $this->getActivityLineChart($from, $to, $companyId);
    }

    public function getModuleUsage(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $logs = ActivityLog::query()
            ->select('module', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from, $to])
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
            ->groupBy('module')
            ->orderByDesc('total')
            ->get();

        if ($logs->isNotEmpty()) {
            return $logs->map(fn ($row) => [
                'module' => $row->module,
                'count' => (int) $row->total,
            ])->all();
        }

        return collect(['Leads', 'Projects', 'Reports', 'Users'])
            ->map(function (string $module) use ($from, $to, $companyId) {
                $count = match ($module) {
                    'Leads' => $this->scopedLeadsQuery($companyId)->whereBetween('updated_at', [$from, $to])->count(),
                    'Projects' => $this->scopedProjectsQuery($companyId)->whereBetween('updated_at', [$from, $to])->count(),
                    'Users' => User::query()
                        ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
                        ->whereBetween('updated_at', [$from, $to])
                        ->count(),
                    default => 0,
                };

                return ['module' => $module, 'count' => $count];
            })
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    public function getRecentlyActiveUsers(?int $companyId = null, int $limit = 10): array
    {
        $onlineThreshold = now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES)->timestamp;

        return DB::table('sessions')
            ->join('users', 'users.id', '=', 'sessions.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'users.company_id')
            ->when($companyId, fn ($query) => $query->where('users.company_id', $companyId))
            ->where('sessions.last_activity', '>=', $onlineThreshold)
            ->whereNotIn('users.role', ['super_admin', 'admin'])
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.role',
                'companies.name as company_name',
                DB::raw('MAX(sessions.last_activity) as last_activity'),
            ])
            ->groupBy('users.id', 'users.name', 'users.email', 'users.role', 'companies.name')
            ->orderByDesc('last_activity')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                return [
                    'id' => (int) $row->id,
                    'name' => $row->name,
                    'email' => $row->email,
                    'role' => $this->formatRoleLabel($row->role),
                    'company_name' => $row->company_name ?? 'Platform',
                    'last_active_label' => Carbon::createFromTimestamp((int) $row->last_activity)->diffForHumans(),
                    'is_online' => true,
                ];
            })
            ->all();
    }

    public function getActivityLineChart(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $labels = [];
        $values = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->endOfDay();

        while ($cursor->lte($end)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();
            $labels[] = $cursor->format('d M');
            $values[] = ActivityLog::query()
                ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
                ->whereBetween('created_at', [$dayStart, $dayEnd])
                ->count()
                + LeadActivity::query()
                    ->when($companyId, fn (Builder $query) => $query->whereHas('lead', fn (Builder $leadQuery) => $leadQuery->where('company_id', $companyId)))
                    ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                    ->count()
                + ProjectActivity::query()
                    ->when($companyId, fn (Builder $query) => $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery->where('company_id', $companyId)))
                    ->whereBetween('occurred_at', [$dayStart, $dayEnd])
                    ->count();
            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'values' => $values,
        ];
    }

    public function getRolePieChart(?int $companyId = null): array
    {
        $users = User::query()
            ->whereNotIn('role', ['super_admin', 'admin'])
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
            ->with('roleRecord:id,slug,name')
            ->get(['id', 'role', 'role_id']);

        $breakdown = $this->buildRoleBreakdown($users);

        return [
            'labels' => array_column($breakdown, 'label'),
            'values' => array_column($breakdown, 'count'),
        ];
    }

    public function getLeadStatusPieChart(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $metrics = $this->getLeadMetrics($from, $to, $companyId);

        return [
            'labels' => ['New', 'In Progress', 'Won', 'Lost'],
            'values' => [
                $metrics['by_status']['new'],
                $metrics['by_status']['in_progress'],
                $metrics['by_status']['won'],
                $metrics['by_status']['lost'],
            ],
        ];
    }

    public function getLeadSourceBarChart(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $metrics = $this->getLeadMetrics($from, $to, $companyId);

        return [
            'labels' => array_column($metrics['by_source'], 'label'),
            'values' => array_column($metrics['by_source'], 'count'),
        ];
    }

    public function getCompanyProjectsBarChart(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $metrics = $this->getProjectMetrics($from, $to, $companyId);

        return [
            'labels' => array_column($metrics['by_company'], 'company_name'),
            'values' => array_column($metrics['by_company'], 'total'),
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function resolveDateRange(string $period): array
    {
        $to = now();

        return match ($period) {
            'today' => [$to->copy()->startOfDay(), $to],
            'monthly' => [$to->copy()->startOfMonth(), $to],
            default => [$to->copy()->startOfWeek(), $to],
        };
    }

    private function scopedLeadsQuery(?int $companyId): Builder
    {
        return Lead::query()->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId));
    }

    private function scopedProjectsQuery(?int $companyId): Builder
    {
        return Project::query()->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId));
    }

    private function onlineUserIds(): array
    {
        $threshold = now()->subMinutes(self::ONLINE_THRESHOLD_MINUTES)->timestamp;

        return DB::table('sessions')
            ->where('last_activity', '>=', $threshold)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();
    }

    private function buildRoleBreakdown(Collection $users): array
    {
        $groups = [
            'Super Admin' => 0,
            'Org Admin' => 0,
            'Manager' => 0,
            'User' => 0,
        ];

        foreach ($users as $user) {
            $role = strtolower(trim((string) ($user->roleRecord?->slug ?: $user->role)));
            $label = match (true) {
                in_array($role, ['super_admin', 'admin'], true) => 'Super Admin',
                in_array($role, ['company_admin', 'companyadmin'], true) => 'Org Admin',
                str_contains($role, 'manager') => 'Manager',
                default => 'User',
            };
            $groups[$label]++;
        }

        return collect($groups)
            ->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])
            ->filter(fn (array $row) => $row['count'] > 0)
            ->values()
            ->all();
    }

    private function getTopActiveCompanies(Carbon $from, Carbon $to, ?int $companyId = null): array
    {
        $leadCounts = $this->scopedLeadsQuery($companyId)
            ->select('company_id', DB::raw('COUNT(*) as total'))
            ->whereBetween('updated_at', [$from, $to])
            ->groupBy('company_id')
            ->pluck('total', 'company_id');

        $projectCounts = $this->scopedProjectsQuery($companyId)
            ->select('company_id', DB::raw('COUNT(*) as total'))
            ->whereBetween('updated_at', [$from, $to])
            ->groupBy('company_id')
            ->pluck('total', 'company_id');

        $activityCounts = ActivityLog::query()
            ->select('company_id', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from, $to])
            ->when($companyId, fn (Builder $query) => $query->where('company_id', $companyId))
            ->whereNotNull('company_id')
            ->groupBy('company_id')
            ->pluck('total', 'company_id');

        $companyIds = collect($leadCounts->keys())
            ->merge($projectCounts->keys())
            ->merge($activityCounts->keys())
            ->unique();

        return $companyIds
            ->map(function ($id) use ($leadCounts, $projectCounts, $activityCounts) {
                $score = (int) ($leadCounts[$id] ?? 0) + (int) ($projectCounts[$id] ?? 0) + (int) ($activityCounts[$id] ?? 0);
                $company = Company::query()->find($id);

                return [
                    'company_id' => (int) $id,
                    'company_name' => $company?->name ?? 'Unknown',
                    'activity_score' => $score,
                    'leads' => (int) ($leadCounts[$id] ?? 0),
                    'projects' => (int) ($projectCounts[$id] ?? 0),
                    'actions' => (int) ($activityCounts[$id] ?? 0),
                ];
            })
            ->sortByDesc('activity_score')
            ->take(8)
            ->values()
            ->all();
    }

    private function legacyActivityFeed(?int $companyId, int $limit): array
    {
        $leadActivities = LeadActivity::query()
            ->with(['user:id,name', 'lead:id,company_id,client_name'])
            ->when($companyId, fn (Builder $query) => $query->whereHas('lead', fn (Builder $leadQuery) => $leadQuery->where('company_id', $companyId)))
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (LeadActivity $activity) => [
                'source' => 'lead_activity',
                'user_name' => $activity->user?->name ?? 'System',
                'company_name' => Company::query()->find($activity->lead?->company_id)?->name ?? 'Unknown',
                'module' => 'Leads',
                'action' => $activity->type ?? 'update',
                'description' => $activity->description ?? 'Lead activity recorded',
                'occurred_at' => $activity->occurred_at?->toIso8601String(),
                'occurred_label' => $activity->occurred_at?->diffForHumans() ?? '-',
            ]);

        $projectActivities = ProjectActivity::query()
            ->with(['user:id,name', 'project:id,company_id,name'])
            ->when($companyId, fn (Builder $query) => $query->whereHas('project', fn (Builder $projectQuery) => $projectQuery->where('company_id', $companyId)))
            ->latest('occurred_at')
            ->limit($limit)
            ->get()
            ->map(fn (ProjectActivity $activity) => [
                'source' => 'project_activity',
                'user_name' => $activity->user?->name ?? 'System',
                'company_name' => Company::query()->find($activity->project?->company_id)?->name ?? 'Unknown',
                'module' => 'Projects',
                'action' => $activity->type ?? 'update',
                'description' => $activity->description ?? 'Project activity recorded',
                'occurred_at' => $activity->occurred_at?->toIso8601String(),
                'occurred_label' => $activity->occurred_at?->diffForHumans() ?? '-',
            ]);

        return $leadActivities
            ->merge($projectActivities)
            ->sortByDesc('occurred_at')
            ->take($limit)
            ->values()
            ->all();
    }

    private function normalizeSource(?string $source): string
    {
        $normalized = trim((string) $source);

        return $normalized !== '' ? $normalized : 'Unknown';
    }

    private function formatRoleLabel(?string $role): string
    {
        $normalized = strtolower(trim((string) $role));

        return match (true) {
            in_array($normalized, ['company_admin', 'companyadmin'], true) => 'Org Admin',
            str_contains($normalized, 'manager') => 'Manager',
            in_array($normalized, ['super_admin', 'admin'], true) => 'Super Admin',
            default => 'User',
        };
    }
}
