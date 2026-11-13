<?php

namespace App\Modules\Accounting\Repositories;

use App\Models\IncomeCategory;
use App\Modules\Accounting\Contracts\IncomeCategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class EloquentIncomeCategoryRepository implements IncomeCategoryRepositoryInterface
{
    public function paginateForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator
    {
        return IncomeCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function allForCompany(int $companyId): Collection
    {
        return IncomeCategory::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get();
    }

    public function findForCompany(int $companyId, int|string $id): ?IncomeCategory
    {
        return IncomeCategory::query()
            ->where('company_id', $companyId)
            ->whereKey($id)
            ->first();
    }

    public function create(array $attributes): IncomeCategory
    {
        return IncomeCategory::create($attributes);
    }

    public function update(IncomeCategory $category, array $attributes): bool
    {
        return $category->update($attributes);
    }

    public function delete(IncomeCategory $category): bool|null
    {
        return $category->delete();
    }
}
