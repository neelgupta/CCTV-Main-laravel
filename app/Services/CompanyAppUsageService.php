<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Staff;
use App\Models\StaffAppActivity;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CompanyAppUsageService
{
    public function dashboardSummary(): array
    {
        return Cache::remember($this->summaryCacheKey(), now()->addMinutes(10), function (): array {
            $totalCompanies = (int) Company::query()->count();
            $companiesUsingApp = (int) StaffAppActivity::query()->distinct()->count('company_id');
            $totalStaff = (int) Staff::query()->count();
            $totalStaffUsingApp = (int) StaffAppActivity::query()->distinct()->count('staff_id');

            return [
                'total_companies' => $totalCompanies,
                'companies_using_app' => $companiesUsingApp,
                'companies_not_using_app' => max(0, $totalCompanies - $companiesUsingApp),
                'total_staff' => $totalStaff,
                'total_staff_using_app' => $totalStaffUsingApp,
                'active_app_users_today' => $this->activeStaffCount(now()->startOfDay(), now()->endOfDay()),
                'active_app_users_7_days' => $this->activeStaffCount(now()->subDays(6)->startOfDay(), now()->endOfDay()),
                'active_app_users_30_days' => $this->activeStaffCount(now()->subDays(29)->startOfDay(), now()->endOfDay()),
            ];
        });
    }

    public function companySummary(Company $company): array
    {
        $totalStaff = (int) Staff::query()->where('company_id', $company->id)->count();
        $staffUsingApp = (int) StaffAppActivity::query()->where('company_id', $company->id)->distinct()->count('staff_id');

        return [
            'total_staff' => $totalStaff,
            'staff_using_app' => $staffUsingApp,
            'staff_not_using_app' => max(0, $totalStaff - $staffUsingApp),
            'active_today' => $this->companyActiveCount($company, now()->startOfDay(), now()->endOfDay()),
            'active_7_days' => $this->companyActiveCount($company, now()->subDays(6)->startOfDay(), now()->endOfDay()),
            'active_30_days' => $this->companyActiveCount($company, now()->subDays(29)->startOfDay(), now()->endOfDay()),
            'last_app_activity' => StaffAppActivity::query()
                ->where('company_id', $company->id)
                ->max('last_active_at'),
        ];
    }

    public function companyQuery(array $filters): Builder
    {
        $today = now()->toDateString();
        $staffCounts = Staff::query()
            ->select('company_id', DB::raw('COUNT(*) as total_staff'))
            ->groupBy('company_id');

        $usingCounts = StaffAppActivity::query()
            ->select('company_id', DB::raw('COUNT(DISTINCT staff_id) as staff_using_app'), DB::raw('MAX(last_active_at) as last_app_activity'))
            ->groupBy('company_id');

        $todayCounts = StaffAppActivity::query()
            ->select('company_id', DB::raw('COUNT(DISTINCT staff_id) as active_today'))
            ->whereDate('last_active_at', $today)
            ->groupBy('company_id');

        $weekCounts = StaffAppActivity::query()
            ->select('company_id', DB::raw('COUNT(DISTINCT staff_id) as active_7_days'))
            ->whereBetween('last_active_at', [now()->subDays(6)->startOfDay(), now()->endOfDay()])
            ->groupBy('company_id');

        $monthCounts = StaffAppActivity::query()
            ->select('company_id', DB::raw('COUNT(DISTINCT staff_id) as active_30_days'))
            ->whereBetween('last_active_at', [now()->subDays(29)->startOfDay(), now()->endOfDay()])
            ->groupBy('company_id');

        $customCounts = null;
        if (($filters['activity_period'] ?? null) === 'custom' && filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)) {
            $customCounts = StaffAppActivity::query()
                ->select('company_id', DB::raw('COUNT(DISTINCT staff_id) as active_custom'))
                ->whereBetween('last_active_at', [
                    Carbon::parse((string) $filters['date_from'])->startOfDay(),
                    Carbon::parse((string) $filters['date_to'])->endOfDay(),
                ])
                ->groupBy('company_id');
        }

        $query = Company::query()
            ->leftJoinSub($staffCounts, 'staff_counts', 'staff_counts.company_id', '=', 'companies.id')
            ->leftJoinSub($usingCounts, 'using_counts', 'using_counts.company_id', '=', 'companies.id')
            ->leftJoinSub($todayCounts, 'today_counts', 'today_counts.company_id', '=', 'companies.id')
            ->leftJoinSub($weekCounts, 'week_counts', 'week_counts.company_id', '=', 'companies.id')
            ->leftJoinSub($monthCounts, 'month_counts', 'month_counts.company_id', '=', 'companies.id')
            ->select([
                'companies.*',
                DB::raw('COALESCE(staff_counts.total_staff, 0) as total_staff'),
                DB::raw('COALESCE(using_counts.staff_using_app, 0) as staff_using_app'),
                DB::raw('COALESCE(staff_counts.total_staff, 0) - COALESCE(using_counts.staff_using_app, 0) as staff_not_using_app'),
                DB::raw('COALESCE(today_counts.active_today, 0) as active_today'),
                DB::raw('COALESCE(week_counts.active_7_days, 0) as active_7_days'),
                DB::raw('COALESCE(month_counts.active_30_days, 0) as active_30_days'),
                DB::raw('using_counts.last_app_activity as last_app_activity'),
            ])
            ->with(['latestSubscription.plan'])
            ->when(filled($filters['search'] ?? null), function (Builder $builder) use ($filters): void {
                $search = trim((string) $filters['search']);
                $builder->where(function (Builder $query) use ($search): void {
                    $query->where('companies.name', 'like', '%' . $search . '%')
                        ->orWhere('companies.email', 'like', '%' . $search . '%')
                        ->orWhere('companies.company_code', 'like', '%' . $search . '%');
                });
            })
            ->when(filled($filters['subscription_plan_id'] ?? null), function (Builder $builder) use ($filters): void {
                $builder->whereHas('latestSubscription', function (Builder $subscriptionQuery) use ($filters): void {
                    $subscriptionQuery->where('subscription_plan_id', (int) $filters['subscription_plan_id']);
                });
            })
            ->when(filled($filters['subscription_status'] ?? null), function (Builder $builder) use ($filters): void {
                $status = (string) $filters['subscription_status'];

                if ($status === 'none') {
                    $builder->whereDoesntHave('latestSubscription');

                    return;
                }

                $builder->whereHas('latestSubscription', function (Builder $subscriptionQuery) use ($status): void {
                    $subscriptionQuery->where('status', $status);
                });
            })
            ->when(($filters['app_usage_status'] ?? null) === 'using_app', fn (Builder $builder) => $builder->whereRaw('COALESCE(using_counts.staff_using_app, 0) > 0'))
            ->when(($filters['app_usage_status'] ?? null) === 'not_using_app', fn (Builder $builder) => $builder->whereRaw('COALESCE(using_counts.staff_using_app, 0) = 0'))
            ->when(($filters['activity_period'] ?? null) === 'today', fn (Builder $builder) => $builder->whereRaw('COALESCE(today_counts.active_today, 0) > 0'))
            ->when(($filters['activity_period'] ?? null) === 'last_7_days', fn (Builder $builder) => $builder->whereRaw('COALESCE(week_counts.active_7_days, 0) > 0'))
            ->when(($filters['activity_period'] ?? null) === 'last_30_days', fn (Builder $builder) => $builder->whereRaw('COALESCE(month_counts.active_30_days, 0) > 0'))
            ->when(($filters['activity_period'] ?? null) === 'custom' && $customCounts, function (Builder $builder) use ($customCounts): void {
                $builder->leftJoinSub($customCounts, 'custom_counts', 'custom_counts.company_id', '=', 'companies.id')
                    ->whereRaw('COALESCE(custom_counts.active_custom, 0) > 0')
                    ->addSelect(DB::raw('COALESCE(custom_counts.active_custom, 0) as active_custom'));
            });

        return $query;
    }

    public function staffQuery(Company $company, array $filters): Builder
    {
        $activityWindow = $this->activityWindow($filters);

        $latestActivityQuery = StaffAppActivity::query()
            ->select('staff_id', DB::raw('MAX(last_active_at) as last_active_at'))
            ->where('company_id', $company->id)
            ->groupBy('staff_id');

        $latestLoginQuery = DB::table('staff_api_tokens')
            ->select('staff_id', DB::raw('MAX(created_at) as last_login_at'))
            ->where('staff_id', '>', 0)
            ->groupBy('staff_id');

        $query = Staff::query()
            ->where('company_id', $company->id)
            ->leftJoinSub($latestActivityQuery, 'staff_latest_activity', 'staff_latest_activity.staff_id', '=', 'staff.id')
            ->leftJoinSub($latestLoginQuery, 'staff_latest_login', 'staff_latest_login.staff_id', '=', 'staff.id')
            ->select([
                'staff.*',
                DB::raw('staff_latest_activity.last_active_at as last_active_at'),
                DB::raw('staff_latest_login.last_login_at as last_login_at'),
            ])
            ->with([
                'role:id,name,slug',
                'user:id,name,email,is_active',
                'latestAppActivity',
                'latestMobileToken',
            ])
            ->when(filled($filters['search'] ?? null), function (Builder $builder) use ($filters): void {
                $search = trim((string) $filters['search']);
                $builder->where(function (Builder $query) use ($search): void {
                    $query->where('staff.name', 'like', '%' . $search . '%')
                        ->orWhere('staff.contact_number', 'like', '%' . $search . '%')
                        ->orWhere('staff.employee_id', 'like', '%' . $search . '%')
                        ->orWhereHas('user', function (Builder $userQuery) use ($search): void {
                            $userQuery->where('email', 'like', '%' . $search . '%');
                        });
                });
            })
            ->when(($filters['app_usage_status'] ?? null) === 'using_app', fn (Builder $builder) => $builder->whereNotNull('staff_latest_activity.last_active_at'))
            ->when(($filters['app_usage_status'] ?? null) === 'not_using_app', fn (Builder $builder) => $builder->whereNull('staff_latest_activity.last_active_at'))
            ->when(filled($filters['platform'] ?? null), function (Builder $builder) use ($filters): void {
                $platform = trim((string) $filters['platform']);
                $builder->whereHas('latestAppActivity', fn (Builder $activityQuery) => $activityQuery->where('platform', $platform));
            })
            ->when($activityWindow !== null, function (Builder $builder) use ($activityWindow): void {
                [$start, $end] = $activityWindow;
                $builder->whereExists(function ($query) use ($company, $start, $end): void {
                    $query->selectRaw('1')
                        ->from('staff_app_activities')
                        ->whereColumn('staff_app_activities.staff_id', 'staff.id')
                        ->where('staff_app_activities.company_id', $company->id)
                        ->whereBetween('staff_app_activities.last_active_at', [$start, $end]);
                });
            })
            ->orderByRaw(match ($filters['sort'] ?? 'last_active_desc') {
                'name_asc' => 'staff.name asc',
                'name_desc' => 'staff.name desc',
                'last_login_asc' => 'last_login_at asc',
                'last_login_desc' => 'last_login_at desc',
                'last_active_asc' => 'last_active_at asc',
                default => 'last_active_at desc',
            });

        return $query;
    }

    public function forgetCache(): void
    {
        Cache::forget($this->summaryCacheKey());
    }

    public function formatDeviceId(?string $deviceId): string
    {
        $value = trim((string) $deviceId);

        if ($value === '') {
            return '-';
        }

        if (mb_strlen($value) <= 8) {
            return str_repeat('*', mb_strlen($value));
        }

        return mb_substr($value, 0, 3) . str_repeat('*', max(4, mb_strlen($value) - 6)) . mb_substr($value, -3);
    }

    public function platformSummary(Company $company): array
    {
        return StaffAppActivity::query()
            ->where('company_id', $company->id)
            ->whereNotNull('platform')
            ->select('platform', DB::raw('COUNT(DISTINCT staff_id) as total'))
            ->groupBy('platform')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->platform ?: 'Unknown',
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    public function versionSummary(Company $company): array
    {
        return StaffAppActivity::query()
            ->where('company_id', $company->id)
            ->whereNotNull('app_version')
            ->select('app_version', DB::raw('COUNT(DISTINCT staff_id) as total'))
            ->groupBy('app_version')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row) => [
                'label' => $row->app_version ?: 'Unknown',
                'count' => (int) $row->total,
            ])
            ->values()
            ->all();
    }

    private function activeStaffCount(Carbon $start, Carbon $end): int
    {
        return (int) StaffAppActivity::query()
            ->whereBetween('last_active_at', [$start, $end])
            ->distinct()
            ->count('staff_id');
    }

    private function companyActiveCount(Company $company, Carbon $start, Carbon $end): int
    {
        return (int) StaffAppActivity::query()
            ->where('company_id', $company->id)
            ->whereBetween('last_active_at', [$start, $end])
            ->distinct()
            ->count('staff_id');
    }

    private function activityWindow(array $filters): ?array
    {
        return match ($filters['activity_period'] ?? null) {
            'today' => [now()->startOfDay(), now()->endOfDay()],
            'last_7_days' => [now()->subDays(6)->startOfDay(), now()->endOfDay()],
            'last_30_days' => [now()->subDays(29)->startOfDay(), now()->endOfDay()],
            'custom' => filled($filters['date_from'] ?? null) && filled($filters['date_to'] ?? null)
                ? [Carbon::parse((string) $filters['date_from'])->startOfDay(), Carbon::parse((string) $filters['date_to'])->endOfDay()]
                : null,
            default => null,
        };
    }

    private function summaryCacheKey(): string
    {
        return 'company-app-usage.summary.' . now()->toDateString();
    }
}
