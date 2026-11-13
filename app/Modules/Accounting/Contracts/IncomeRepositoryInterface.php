<?php

namespace App\Modules\Accounting\Contracts;

use App\Models\Income;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

interface IncomeRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator;

    public function queryForCompany(int $companyId): Builder;

    public function findForCompany(int $companyId, int|string $id): ?Income;

    public function create(array $attributes): Income;

    public function update(Income $income, array $attributes): bool;

    public function delete(Income $income): bool|null;

    public function listForCompany(int $companyId): Collection;
}
