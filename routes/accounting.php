<?php

use App\Modules\Accounting\Http\Controllers\AccountingCategoryController;
use App\Modules\Accounting\Http\Controllers\AccountingDashboardController;
use App\Modules\Accounting\Http\Controllers\AccountingReportController;
use App\Modules\Accounting\Http\Controllers\ExpenseController;
use App\Modules\Accounting\Http\Controllers\IncomeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'active', 'subscription.access', 'setup.access'])->group(function (): void {
    Route::middleware('permission:accounting.dashboard.view,accounting.incomes.view,accounting.expenses.view,accounting.categories.manage,accounting.reports.view')->group(function (): void {
        Route::get('/accounting/dashboard', [AccountingDashboardController::class, 'index'])->name('accounting.dashboard');
    });

    Route::middleware('permission:accounting.incomes.view')->group(function (): void {
        Route::get('/accounting/incomes', [IncomeController::class, 'index'])->name('accounting.incomes.index');
        Route::get('/accounting/incomes/export', [IncomeController::class, 'export'])->name('accounting.incomes.export');
        Route::get('/accounting/incomes/{income}', [IncomeController::class, 'show'])->whereNumber('income')->name('accounting.incomes.show');
        Route::get('/accounting/incomes/{income}/attachment', [IncomeController::class, 'downloadAttachment'])->whereNumber('income')->name('accounting.incomes.attachment');
    });
    Route::middleware('permission:accounting.incomes.create')->group(function (): void {
        Route::get('/accounting/incomes/create', [IncomeController::class, 'create'])->name('accounting.incomes.create');
        Route::post('/accounting/incomes', [IncomeController::class, 'store'])->name('accounting.incomes.store');
    });
    Route::middleware('permission:accounting.incomes.edit')->group(function (): void {
        Route::get('/accounting/incomes/{income}/edit', [IncomeController::class, 'edit'])->whereNumber('income')->name('accounting.incomes.edit');
        Route::patch('/accounting/incomes/{income}', [IncomeController::class, 'update'])->whereNumber('income')->name('accounting.incomes.update');
    });
    Route::middleware('permission:accounting.incomes.delete')->group(function (): void {
        Route::delete('/accounting/incomes/{income}', [IncomeController::class, 'destroy'])->whereNumber('income')->name('accounting.incomes.destroy');
    });

    Route::middleware('permission:accounting.expenses.view')->group(function (): void {
        Route::get('/accounting/expenses', [ExpenseController::class, 'index'])->name('accounting.expenses.index');
        Route::get('/accounting/expenses/export', [ExpenseController::class, 'export'])->name('accounting.expenses.export');
        Route::get('/accounting/expenses/{expense}', [ExpenseController::class, 'show'])->whereNumber('expense')->name('accounting.expenses.show');
        Route::get('/accounting/expenses/{expense}/attachment', [ExpenseController::class, 'downloadAttachment'])->whereNumber('expense')->name('accounting.expenses.attachment');
    });
    Route::middleware('permission:accounting.expenses.create')->group(function (): void {
        Route::get('/accounting/expenses/create', [ExpenseController::class, 'create'])->name('accounting.expenses.create');
        Route::post('/accounting/expenses', [ExpenseController::class, 'store'])->name('accounting.expenses.store');
    });
    Route::middleware('permission:accounting.expenses.edit')->group(function (): void {
        Route::get('/accounting/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->whereNumber('expense')->name('accounting.expenses.edit');
        Route::patch('/accounting/expenses/{expense}', [ExpenseController::class, 'update'])->whereNumber('expense')->name('accounting.expenses.update');
        Route::patch('/accounting/expenses/{expense}/approve', [ExpenseController::class, 'approve'])->whereNumber('expense')->name('accounting.expenses.approve');
    });
    Route::middleware('permission:accounting.expenses.delete')->group(function (): void {
        Route::delete('/accounting/expenses/{expense}', [ExpenseController::class, 'destroy'])->whereNumber('expense')->name('accounting.expenses.destroy');
    });

    Route::middleware('permission:accounting.categories.manage')->group(function (): void {
        Route::get('/accounting/categories', [AccountingCategoryController::class, 'index'])->name('accounting.categories.index');
        Route::post('/accounting/categories/expense', [AccountingCategoryController::class, 'storeExpense'])->name('accounting.categories.expense.store');
        Route::patch('/accounting/categories/expense/{expenseCategory}', [AccountingCategoryController::class, 'updateExpense'])->whereNumber('expenseCategory')->name('accounting.categories.expense.update');
        Route::delete('/accounting/categories/expense/{expenseCategory}', [AccountingCategoryController::class, 'destroyExpense'])->whereNumber('expenseCategory')->name('accounting.categories.expense.destroy');
    });

    Route::middleware('permission:accounting.reports.view')->group(function (): void {
        Route::get('/accounting/reports', [AccountingReportController::class, 'index'])->name('accounting.reports.index');
    });
});
