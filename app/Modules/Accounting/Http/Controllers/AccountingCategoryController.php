<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Models\ExpenseCategory;
use App\Modules\Accounting\Contracts\ExpenseCategoryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AccountingCategoryController
{
    public function __construct(
        private readonly ExpenseCategoryRepositoryInterface $expenseCategoryRepository,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('accounting.categories.manage'), 403);

        $companyId = (int) $user->company_id;

        return view('accounting::categories.index', [
            'expenseCategories' => $this->expenseCategoryRepository->allForCompany($companyId),
        ]);
    }

    public function storeExpense(Request $request)
    {
        $this->storeCategory($request);
        return back()->with('success', 'Expense category created successfully.');
    }

    public function updateExpense(Request $request, ExpenseCategory $expenseCategory)
    {
        $this->updateCategory($request, $expenseCategory);
        return back()->with('success', 'Expense category updated successfully.');
    }

    public function destroyExpense(Request $request, ExpenseCategory $expenseCategory)
    {
        $this->destroyCategory($request, $expenseCategory);
        return back()->with('success', 'Expense category deleted successfully.');
    }

    private function storeCategory(Request $request): void
    {
        $this->ensureManageAccess($request);
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('expense_categories', 'name')->where(fn ($query) => $query->where('company_id', $companyId))],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        ExpenseCategory::create([
            'company_id' => $companyId,
            'name' => trim((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
        ]);
    }

    private function updateCategory(Request $request, ExpenseCategory $category): void
    {
        $this->ensureManageAccess($request);
        $companyId = (int) $request->user()->company_id;
        abort_unless((int) $category->company_id === $companyId, 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191', Rule::unique('expense_categories', 'name')->where(fn ($query) => $query->where('company_id', $companyId))->ignore($category->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $category->update([
            'name' => trim((string) $validated['name']),
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'],
        ]);
    }

    private function destroyCategory(Request $request, ExpenseCategory $category): void
    {
        $this->ensureManageAccess($request);
        abort_unless((int) $category->company_id === (int) $request->user()->company_id, 403);
        $category->delete();
    }

    private function ensureManageAccess(Request $request): void
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('accounting.categories.manage'), 403);
    }
}
