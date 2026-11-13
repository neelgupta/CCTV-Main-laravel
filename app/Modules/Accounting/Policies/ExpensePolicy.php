<?php

namespace App\Modules\Accounting\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.expenses.view');
    }

    public function view(User $user, Expense $expense): bool
    {
        return (int) $expense->company_id === (int) $user->company_id && $user->hasPermission('accounting.expenses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.expenses.create');
    }

    public function update(User $user, Expense $expense): bool
    {
        return (int) $expense->company_id === (int) $user->company_id && $user->hasPermission('accounting.expenses.edit');
    }

    public function delete(User $user, Expense $expense): bool
    {
        return (int) $expense->company_id === (int) $user->company_id && $user->hasPermission('accounting.expenses.delete');
    }
}
