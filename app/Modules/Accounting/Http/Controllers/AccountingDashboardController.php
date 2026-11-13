<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Services\AccountingDashboardService;
use Illuminate\Http\Request;

class AccountingDashboardController
{
    public function index(Request $request, AccountingDashboardService $dashboardService)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('accounting.dashboard.view', 'accounting.incomes.view', 'accounting.expenses.view', 'accounting.categories.manage', 'accounting.reports.view'), 403);

        $companyId = (int) $user->company_id;
        $staffId = $user->isCompanyAdmin() ? null : ($user->staff?->id ? (int) $user->staff->id : 0);

        return view('accounting::dashboard', [
            'summary' => $dashboardService->summaryForCompany($companyId, $staffId),
            'charts' => $dashboardService->chartDataForCompany($companyId, $staffId),
        ]);
    }
}
