<?php

namespace App\Modules\Accounting\Policies;

use App\Models\Income;
use App\Models\User;

class IncomePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.incomes.view');
    }

    public function view(User $user, Income $income): bool
    {
        return (int) $income->company_id === (int) $user->company_id && $user->hasPermission('accounting.incomes.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.incomes.create');
    }

    public function update(User $user, Income $income): bool
    {
        return (int) $income->company_id === (int) $user->company_id && $user->hasPermission('accounting.incomes.edit');
    }

    public function delete(User $user, Income $income): bool
    {
        return (int) $income->company_id === (int) $user->company_id && $user->hasPermission('accounting.incomes.delete');
    }
}
