<?php

namespace App\Modules\Accounting\Contracts;

use App\Models\ExpenseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ExpenseCategoryRepositoryInterface
{
    public function paginateForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function allForCompany(int $companyId): Collection;

    public function findForCompany(int $companyId, int|string $id): ?ExpenseCategory;

    public function create(array $attributes): ExpenseCategory;

    public function update(ExpenseCategory $category, array $attributes): bool;

    public function delete(ExpenseCategory $category): bool|null;
}
