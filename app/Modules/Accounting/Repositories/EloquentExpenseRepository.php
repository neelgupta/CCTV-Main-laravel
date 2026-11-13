<?php

namespace App\Modules\Accounting\Repositories;

use App\Models\Expense;
use App\Modules\Accounting\Contracts\ExpenseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class EloquentExpenseRepository implements ExpenseRepositoryInterface
{
    public function paginateForCompany(int $companyId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->applyFilters($this->queryForCompany($companyId), $filters)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function queryForCompany(int $companyId): Builder
    {
        return Expense::query()
            ->with(['category:id,name', 'staff:id,name', 'creator:id,name', 'approver:id,name'])
            ->where('company_id', $companyId);
    }

    public function findForCompany(int $companyId, int|string $id): ?Expense
    {
        return $this->queryForCompany($companyId)
            ->whereKey($id)
            ->first();
    }

    public function create(array $attributes): Expense
    {
        return Expense::create($attributes);
    }

    public function update(Expense $expense, array $attributes): bool
    {
        return $expense->update($attributes);
    }

    public function delete(Expense $expense): bool|null
    {
        return $expense->delete();
    }

    public function listForCompany(int $companyId): Collection
    {
        return $this->queryForCompany($companyId)
            ->latest('expense_date')
            ->get();
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(filled($filters['search'] ?? null), function (Builder $builder) use ($filters): void {
                $search = trim((string) $filters['search']);

                $builder->where(function (Builder $inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%")
                        ->orWhere('transaction_no', 'like', "%{$search}%")
                        ->orWhereHas('category', fn (Builder $categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn (Builder $staffQuery) => $staffQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($filters['category_id'] ?? null), fn (Builder $builder) => $builder->where('category_id', (int) $filters['category_id']))
            ->when(filled($filters['payment_method'] ?? null), fn (Builder $builder) => $builder->where('payment_method', $filters['payment_method']))
            ->when(filled($filters['staff_id'] ?? null), fn (Builder $builder) => $builder->where('staff_id', (int) $filters['staff_id']))
            ->when(filled($filters['approval_status'] ?? null), fn (Builder $builder) => $builder->where('approval_status', $filters['approval_status']))
            ->when(filled($filters['vendor_name'] ?? null), fn (Builder $builder) => $builder->where('vendor_name', 'like', '%' . trim((string) $filters['vendor_name']) . '%'))
            ->when(filled($filters['date_from'] ?? null), fn (Builder $builder) => $builder->whereDate('expense_date', '>=', $filters['date_from']))
            ->when(filled($filters['date_to'] ?? null), fn (Builder $builder) => $builder->whereDate('expense_date', '<=', $filters['date_to']))
            ->when(filled($filters['amount_min'] ?? null), fn (Builder $builder) => $builder->where('amount', '>=', (float) $filters['amount_min']))
            ->when(filled($filters['amount_max'] ?? null), fn (Builder $builder) => $builder->where('amount', '<=', (float) $filters['amount_max']));
    }
}
