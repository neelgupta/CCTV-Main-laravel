<?php

namespace App\Modules\Accounting\Contracts;

use App\Models\Expense;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface ExpenseRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function queryForCompany(int $companyId): Builder;

    public function findForCompany(int $companyId, int|string $id): ?Expense;

    public function create(array $attributes): Expense;

    public function update(Expense $expense, array $attributes): bool;

    public function delete(Expense $expense): bool|null;

    public function listForCompany(int $companyId): Collection;
}
