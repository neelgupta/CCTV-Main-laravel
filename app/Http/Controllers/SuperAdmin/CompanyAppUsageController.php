<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyAppUsageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanyAppUsageController extends Controller
{
    public function __construct(private readonly CompanyAppUsageService $usage)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $filters = $this->resolveCompanyFilters($request);
        $perPage = $this->resolvePerPage($request);
        $sort = $filters['sort'] ?? 'last_active_desc';

        $companiesQuery = $this->usage->companyQuery($filters);

        $companiesQuery = match ($sort) {
            'name_asc' => $companiesQuery->orderBy('companies.name'),
            'name_desc' => $companiesQuery->orderByDesc('companies.name'),
            'using_app_asc' => $companiesQuery->orderBy('staff_using_app'),
            'using_app_desc' => $companiesQuery->orderByDesc('staff_using_app'),
            'active_today_asc' => $companiesQuery->orderBy('active_today'),
            'active_today_desc' => $companiesQuery->orderByDesc('active_today'),
            default => $companiesQuery->orderByDesc('last_app_activity'),
        };

        $companies = $companiesQuery->paginate($perPage)->withQueryString();

        return view('super-admin.company-app-usage.index', [
            'filters' => $filters,
            'companies' => $companies,
            'stats' => $this->usage->dashboardSummary(),
            'plans' => \App\Models\SubscriptionPlan::query()->orderBy('name')->get(['id', 'name']),
            'statusOptions' => ['active', 'trialing', 'pending', 'expired', 'cancelled', 'none'],
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }

    public function show(Request $request, Company $company): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $filters = $this->resolveStaffFilters($request);
        $perPage = $this->resolvePerPage($request);
        $summary = $this->usage->companySummary($company);

        $staff = $this->usage
            ->staffQuery($company, $filters)
            ->paginate($perPage)
            ->withQueryString();

        return view('super-admin.company-app-usage.show', [
            'company' => $company->load(['latestSubscription.plan']),
            'filters' => $filters,
            'staff' => $staff,
            'summary' => $summary,
            'platformSummary' => $this->usage->platformSummary($company),
            'versionSummary' => $this->usage->versionSummary($company),
            'perPageOptions' => [10, 25, 50, 100],
        ]);
    }

    private function resolveCompanyFilters(Request $request): array
    {
        $validated = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:191'],
            'subscription_plan_id' => ['nullable', 'integer', 'exists:subscription_plans,id'],
            'subscription_status' => ['nullable', Rule::in(['active', 'trialing', 'pending', 'expired', 'cancelled', 'none'])],
            'app_usage_status' => ['nullable', Rule::in(['using_app', 'not_using_app'])],
            'activity_period' => ['nullable', Rule::in(['today', 'last_7_days', 'last_30_days', 'custom'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['last_active_desc', 'last_active_asc', 'name_asc', 'name_desc', 'using_app_desc', 'using_app_asc', 'active_today_desc', 'active_today_asc'])],
        ])->validate();

        return [
            'search' => trim((string) ($validated['search'] ?? '')) ?: null,
            'subscription_plan_id' => isset($validated['subscription_plan_id']) ? (int) $validated['subscription_plan_id'] : null,
            'subscription_status' => $validated['subscription_status'] ?? null,
            'app_usage_status' => $validated['app_usage_status'] ?? null,
            'activity_period' => $validated['activity_period'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'sort' => $validated['sort'] ?? 'last_active_desc',
        ];
    }

    private function resolveStaffFilters(Request $request): array
    {
        $validated = Validator::make($request->query(), [
            'search' => ['nullable', 'string', 'max:191'],
            'app_usage_status' => ['nullable', Rule::in(['using_app', 'not_using_app'])],
            'activity_period' => ['nullable', Rule::in(['today', 'last_7_days', 'last_30_days', 'custom'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'platform' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(['last_active_desc', 'last_active_asc', 'name_asc', 'name_desc', 'last_login_desc', 'last_login_asc'])],
        ])->validate();

        return [
            'search' => trim((string) ($validated['search'] ?? '')) ?: null,
            'app_usage_status' => $validated['app_usage_status'] ?? null,
            'activity_period' => $validated['activity_period'] ?? null,
            'date_from' => $validated['date_from'] ?? null,
            'date_to' => $validated['date_to'] ?? null,
            'platform' => trim((string) ($validated['platform'] ?? '')) ?: null,
            'sort' => $validated['sort'] ?? 'last_active_desc',
        ];
    }

    private function resolvePerPage(Request $request): int
    {
        $value = (int) $request->query('per_page', 10);

        return in_array($value, [10, 25, 50, 100], true) ? $value : 10;
    }
}
