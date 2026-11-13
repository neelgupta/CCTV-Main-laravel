<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Providers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Modules\Accounting\Contracts\ExpenseCategoryRepositoryInterface;
use App\Modules\Accounting\Contracts\ExpenseRepositoryInterface;
use App\Modules\Accounting\Contracts\IncomeCategoryRepositoryInterface;
use App\Modules\Accounting\Contracts\IncomeRepositoryInterface;
use App\Modules\Accounting\Policies\AccountingCategoryPolicy;
use App\Modules\Accounting\Policies\ExpensePolicy;
use App\Modules\Accounting\Policies\IncomePolicy;
use App\Modules\Accounting\Observers\TicketInvoiceObserver;
use App\Modules\Accounting\Repositories\EloquentExpenseCategoryRepository;
use App\Modules\Accounting\Repositories\EloquentExpenseRepository;
use App\Modules\Accounting\Repositories\EloquentIncomeCategoryRepository;
use App\Modules\Accounting\Repositories\EloquentIncomeRepository;
use App\Models\TicketInvoice;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AccountingModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(IncomeCategoryRepositoryInterface::class, EloquentIncomeCategoryRepository::class);
        $this->app->bind(ExpenseCategoryRepositoryInterface::class, EloquentExpenseCategoryRepository::class);
        $this->app->bind(IncomeRepositoryInterface::class, EloquentIncomeRepository::class);
        $this->app->bind(ExpenseRepositoryInterface::class, EloquentExpenseRepository::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/accounting.php'));
        $this->loadViewsFrom(__DIR__ . '/../views', 'accounting');
        TicketInvoice::observe(TicketInvoiceObserver::class);

        Gate::policy(Income::class, IncomePolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(IncomeCategory::class, AccountingCategoryPolicy::class);
        Gate::policy(ExpenseCategory::class, AccountingCategoryPolicy::class);
    }
}
