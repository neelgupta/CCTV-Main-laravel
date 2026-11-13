<?php

namespace App\Modules\Accounting\Repositories;

use App\Models\Income;
use App\Modules\Accounting\Contracts\IncomeRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentIncomeRepository implements IncomeRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->applyFilters($this->queryForCompany($companyId), $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function queryForCompany(int $companyId): Builder
    {
        return Income::query()
            ->with(['category:id,name', 'client:id,name', 'staff:id,name', 'creator:id,name', 'invoice:id,invoice_number,ticket_id', 'invoice.ticket:id,ticket_number'])
            ->where('company_id', $companyId);
    }

    public function findForCompany(int $companyId, int|string $id): ?Income
    {
        return $this->queryForCompany($companyId)
            ->whereKey($id)
            ->first();
    }

    public function create(array $attributes): Income
    {
        return Income::create($attributes);
    }

    public function update(Income $income, array $attributes): bool
    {
        return $income->update($attributes);
    }

    public function delete(Income $income): bool|null
    {
        return $income->delete();
    }

    public function listForCompany(int $companyId): Collection
    {
        return $this->queryForCompany($companyId)
            ->latest('received_date')
            ->get();
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['search'] ?? null), function (Builder $builder) use ($filters): void {
                $search = trim((string) $filters['search']);

                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('transaction_no', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('client', fn (Builder $clientQuery) => $clientQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn (Builder $staffQuery) => $staffQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($filters['category_id'] ?? null), fn (Builder $builder) => $builder->where('category_id', (int) $filters['category_id']))
            ->when(filled($filters['payment_method'] ?? null), fn (Builder $builder) => $builder->where('payment_method', $filters['payment_method']))
            ->when(filled($filters['client_id'] ?? null), fn (Builder $builder) => $builder->where('client_id', (int) $filters['client_id']))
            ->when(filled($filters['staff_id'] ?? null), fn (Builder $builder) => $builder->where('staff_id', (int) $filters['staff_id']))
            ->when(filled($filters['invoice_id'] ?? null), fn (Builder $builder) => $builder->where('invoice_id', (int) $filters['invoice_id']))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $builder) => $builder->whereDate('received_date', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $builder) => $builder->whereDate('received_date', '<=', $filters['date_to']))
            ->when(filled($filters['amount_min'] ?? null), fn (Builder $builder) => $builder->where('amount', '>=', (float) $filters['amount_min']))
            ->when(filled($filters['amount_max'] ?? null), fn (Builder $builder) => $builder->where('amount', '<=', (float) $filters['amount_max']));
    }
}
