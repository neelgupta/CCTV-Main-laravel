<?php

namespace App\Modules\Accounting\Services;

use App\Models\Expense;
use App\Models\Income;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AccountingDashboardService
{
    public function summaryForCompany(int $companyId, ?int $staffId = null): array
    {
        $today = Carbon::today();
        $monthStart = Carbon::now()->startOfMonth();

        $incomeTotals = Income::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->selectRaw('COALESCE(SUM(amount), 0) as total_income')
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN amount ELSE 0 END), 0) as cash_income")
            // Every non-cash method (Online, Bank, UPI, Cheque, Card) counts as online, so Cash + Online = Total.
            ->selectRaw("COALESCE(SUM(CASE WHEN payment_method = 'Cash' THEN 0 ELSE amount END), 0) as online_income")
            ->selectRaw('COALESCE(SUM(CASE WHEN received_date = ? THEN amount ELSE 0 END), 0) as today_income', [$today->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN received_date >= ? THEN amount ELSE 0 END), 0) as monthly_income', [$monthStart->toDateString()])
            ->first();

        $expenseTotals = Expense::query()
            ->withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('approval_status', Expense::STATUS_APPROVED)
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->selectRaw('COALESCE(SUM(amount), 0) as total_expense')
            ->selectRaw('COALESCE(SUM(CASE WHEN expense_date = ? THEN amount ELSE 0 END), 0) as today_expense', [$today->toDateString()])
            ->selectRaw('COALESCE(SUM(CASE WHEN expense_date >= ? THEN amount ELSE 0 END), 0) as monthly_expense', [$monthStart->toDateString()])
            ->first();

        $totalIncome = (float) ($incomeTotals->total_income ?? 0);
        $totalExpense = (float) ($expenseTotals->total_expense ?? 0);

        return [
            'total_income' => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit' => $totalIncome - $totalExpense,
            'cash_income' => (float) ($incomeTotals->cash_income ?? 0),
            'online_income' => (float) ($incomeTotals->online_income ?? 0),
            'today_income' => (float) ($incomeTotals->today_income ?? 0),
            'today_expense' => (float) ($expenseTotals->today_expense ?? 0),
            'monthly_income' => (float) ($incomeTotals->monthly_income ?? 0),
            'monthly_expense' => (float) ($expenseTotals->monthly_expense ?? 0),
        ];
    }

    public function chartDataForCompany(int $companyId, ?int $staffId = null): array
    {
        $months = collect(range(5, 0))->map(fn (int $offset) => Carbon::now()->copy()->subMonthsNoOverflow($offset));
        $labels = $months->map(fn (Carbon $month) => $month->format('M Y'))->values()->all();
        $driver = DB::getDriverName();
        $monthKeyExpression = match ($driver) {
            'sqlite' => "strftime('%Y-%m', received_date)",
            'pgsql' => "TO_CHAR(received_date, 'YYYY-MM')",
            default => "DATE_FORMAT(received_date, '%Y-%m')",
        };
        $expenseMonthKeyExpression = str_replace('received_date', 'expense_date', $monthKeyExpression);

        $incomeRows = DB::table('incomes')
            ->selectRaw("{$monthKeyExpression} as month_key")
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->where('company_id', $companyId)
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->whereBetween('received_date', [$months->first()->startOfMonth()->toDateString(), $months->last()->endOfMonth()->toDateString()])
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $expenseRows = DB::table('expenses')
            ->selectRaw("{$expenseMonthKeyExpression} as month_key")
            ->selectRaw('COALESCE(SUM(amount), 0) as total')
            ->where('company_id', $companyId)
            ->where('approval_status', Expense::STATUS_APPROVED)
            ->when($staffId !== null, fn ($query) => $query->where('staff_id', $staffId))
            ->whereBetween('expense_date', [$months->first()->startOfMonth()->toDateString(), $months->last()->endOfMonth()->toDateString()])
            ->groupBy('month_key')
            ->pluck('total', 'month_key');

        $incomeValues = $months->map(fn (Carbon $month) => (float) ($incomeRows[$month->format('Y-m')] ?? 0))->values()->all();
        $expenseValues = $months->map(fn (Carbon $month) => (float) ($expenseRows[$month->format('Y-m')] ?? 0))->values()->all();
        $profitValues = array_map(fn (float $income, float $expense) => $income - $expense, $incomeValues, $expenseValues);

        $expenseCategoryRows = DB::table('expenses as e')
            ->join('expense_categories as c', 'c.id', '=', 'e.category_id')
            ->where('e.company_id', $companyId)
            ->where('e.approval_status', Expense::STATUS_APPROVED)
            ->when($staffId !== null, fn ($query) => $query->where('e.staff_id', $staffId))
            ->selectRaw('c.name as label, COALESCE(SUM(e.amount), 0) as total')
            ->groupBy('c.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        $incomeCategoryRows = DB::table('incomes as i')
            ->join('income_categories as c', 'c.id', '=', 'i.category_id')
            ->where('i.company_id', $companyId)
            ->when($staffId !== null, fn ($query) => $query->where('i.staff_id', $staffId))
            ->selectRaw('c.name as label, COALESCE(SUM(i.amount), 0) as total')
            ->groupBy('c.name')
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return [
            'monthly_comparison' => [
                'labels' => $labels,
                'income' => $incomeValues,
                'expense' => $expenseValues,
            ],
            'profit_trend' => [
                'labels' => $labels,
                'values' => $profitValues,
            ],
            'expense_breakdown' => [
                'labels' => $expenseCategoryRows->pluck('label')->all(),
                'values' => $expenseCategoryRows->pluck('total')->map(fn ($value) => (float) $value)->all(),
            ],
            'income_breakdown' => [
                'labels' => $incomeCategoryRows->pluck('label')->all(),
                'values' => $incomeCategoryRows->pluck('total')->map(fn ($value) => (float) $value)->all(),
            ],
        ];
    }
}
