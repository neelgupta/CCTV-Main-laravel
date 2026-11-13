<?php

namespace App\Modules\Accounting\Repositories;

use App\Models\ExpenseCategory;
use App\Modules\Accounting\Contracts\ExpenseCategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentExpenseCategoryRepository implements ExpenseCategoryRepositoryInterface
{
    public function paginateForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return ExpenseCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function allForCompany(int $companyId): Collection
    {
        return ExpenseCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    public function findForCompany(int $companyId, int|string $id): ?ExpenseCategory
    {
        return ExpenseCategory::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();
    }

    public function create(array $attributes): ExpenseCategory
    {
        return ExpenseCategory::create($attributes);
    }

    public function update(ExpenseCategory $category, array $attributes): bool
    {
        return $category->update($attributes);
    }

    public function delete(ExpenseCategory $category): bool|null
    {
        return $category->delete();
    }
}
