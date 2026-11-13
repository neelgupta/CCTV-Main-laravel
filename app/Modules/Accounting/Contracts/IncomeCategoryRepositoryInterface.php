<?php

namespace App\Modules\Accounting\Contracts;

use App\Models\IncomeCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface IncomeCategoryRepositoryInterface
{
    public function paginateForCompany(int $companyId, int $perPage = 15): LengthAwarePaginator;

    public function allForCompany(int $companyId): Collection;

    public function findForCompany(int $companyId, int|string $id): ?IncomeCategory;

    public function create(array $attributes): IncomeCategory;

    public function update(IncomeCategory $category, array $attributes): bool;

    public function delete(IncomeCategory $category): bool|null;
}
