<?php

namespace App\Modules\Accounting\Policies;

use App\Models\ExpenseCategory;
use App\Models\IncomeCategory;
use App\Models\User;

class AccountingCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.categories.manage');
    }

    public function view(User $user, IncomeCategory|ExpenseCategory $category): bool
    {
        return (int) $category->company_id === (int) $user->company_id && $user->hasPermission('accounting.categories.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.categories.manage');
    }

    public function update(User $user, IncomeCategory|ExpenseCategory $category): bool
    {
        return (int) $category->company_id === (int) $user->company_id && $user->hasPermission('accounting.categories.manage');
    }

    public function delete(User $user, IncomeCategory|ExpenseCategory $category): bool
    {
        return (int) $category->company_id === (int) $user->company_id && $user->hasPermission('accounting.categories.manage');
    }
}
