<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Staff;
use App\Models\Client;
use App\Modules\Accounting\Contracts\ExpenseRepositoryInterface;
use App\Modules\Accounting\Contracts\IncomeRepositoryInterface;
use App\Modules\Accounting\Services\AccountingReportService;
use App\Modules\Accounting\Services\AccountingDashboardService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class AccountingReportController
{
    public function __construct(
        private readonly IncomeRepositoryInterface $incomeRepository,
        private readonly ExpenseRepositoryInterface $expenseRepository,
        private readonly AccountingReportService $reportService,
        private readonly AccountingDashboardService $dashboardService,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('accounting.reports.view'), 403);

        $companyId = (int) $user->company_id;
        $staffId = $user->isCompanyAdmin() ? null : ($user->staff?->id ? (int) $user->staff->id : 0);
        [$from, $to, $periodLabel] = $this->reportService->periodFromRequest($request);
        $incomeFilters = $request->only(['category_id', 'payment_method', 'staff_id', 'client_id', 'amount_min', 'amount_max']);
        $expenseFilters = $request->only(['expense_category_id', 'payment_method', 'staff_id', 'vendor_name', 'amount_min', 'amount_max']);
        if ($staffId !== null) {
            $incomeFilters['staff_id'] = $staffId;
            $expenseFilters['staff_id'] = $staffId;
        }

        $incomeQuery = $this->applyIncomeFilters(
            $this->incomeRepository->queryForCompany($companyId)->whereBetween('received_date', [$from->toDateString(), $to->toDateString()]),
            $incomeFilters
        );
        $expenseQuery = $this->applyExpenseFilters(
            $this->expenseRepository->queryForCompany($companyId)->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()]),
            $expenseFilters
        );

        $incomeSummary = $this->summaryPayload($incomeQuery);
        $expenseSummary = $this->summaryPayload($expenseQuery);
        $dashboardSummary = $this->dashboardService->summaryForCompany($companyId, $staffId);
        $charts = $this->dashboardService->chartDataForCompany($companyId, $staffId);

        return view('accounting::reports.index', [
            'periodLabel' => $periodLabel,
            'dateFrom' => $from,
            'dateTo' => $to,
            'incomeSummary' => $incomeSummary,
            'expenseSummary' => $expenseSummary,
            'dashboardSummary' => $dashboardSummary,
            'charts' => $charts,
            'paymentMethods' => Income::paymentMethods(),
            'incomeCategories' => IncomeCategory::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'expenseCategories' => ExpenseCategory::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'staffMembers' => $staffId !== null
                ? Staff::query()->whereKey($staffId)->get(['id', 'name'])
                : Staff::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'clients' => Client::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function summaryPayload(Builder $query): array
    {
        return [
            'count' => (clone $query)->count(),
            'amount' => (float) (clone $query)->sum('amount'),
        ];
    }

    private function applyIncomeFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['category_id'] ?? null), fn (Builder $builder) => $builder->where('category_id', (int) $filters['category_id']))
            ->when(filled($filters['payment_method'] ?? null), fn (Builder $builder) => $builder->where('payment_method', $filters['payment_method']))
            ->when(filled($filters['staff_id'] ?? null), fn (Builder $builder) => $builder->where('staff_id', (int) $filters['staff_id']))
            ->when(filled($filters['client_id'] ?? null), fn (Builder $builder) => $builder->where('client_id', (int) $filters['client_id']))
            ->when(filled($filters['amount_min'] ?? null), fn (Builder $builder) => $builder->where('amount', '>=', (float) $filters['amount_min']))
            ->when(filled($filters['amount_max'] ?? null), fn (Builder $builder) => $builder->where('amount', '<=', (float) $filters['amount_max']));
    }

    private function applyExpenseFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['expense_category_id'] ?? null), fn (Builder $builder) => $builder->where('category_id', (int) $filters['expense_category_id']))
            ->when(filled($filters['payment_method'] ?? null), fn (Builder $builder) => $builder->where('payment_method', $filters['payment_method']))
            ->when(filled($filters['staff_id'] ?? null), fn (Builder $builder) => $builder->where('staff_id', (int) $filters['staff_id']))
            ->when(filled($filters['vendor_name'] ?? null), fn (Builder $builder) => $builder->where('vendor_name', 'like', '%' . trim((string) $filters['vendor_name']) . '%'))
            ->when(filled($filters['amount_min'] ?? null), fn (Builder $builder) => $builder->where('amount', '>=', (float) $filters['amount_min']))
            ->when(filled($filters['amount_max'] ?? null), fn (Builder $builder) => $builder->where('amount', '<=', (float) $filters['amount_max']));
    }
}
