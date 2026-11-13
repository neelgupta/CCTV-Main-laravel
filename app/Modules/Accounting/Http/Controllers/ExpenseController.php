<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Staff;
use App\Modules\Accounting\Contracts\ExpenseCategoryRepositoryInterface;
use App\Modules\Accounting\Contracts\ExpenseRepositoryInterface;
use App\Modules\Accounting\Exports\AccountingTransactionsExport;
use App\Modules\Accounting\Http\Requests\StoreExpenseRequest;
use App\Modules\Accounting\Http\Requests\UpdateExpenseRequest;
use App\Modules\Accounting\Services\AccountingTransactionService;
use App\Services\LetterheadPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;

class ExpenseController
{
    public function __construct(
        private readonly ExpenseRepositoryInterface $expenseRepository,
        private readonly ExpenseCategoryRepositoryInterface $expenseCategoryRepository,
        private readonly AccountingTransactionService $transactionService,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('accounting.expenses.view'), 403);

        $companyId = (int) $user->company_id;
        $filters = $request->only(['search', 'category_id', 'payment_method', 'staff_id', 'approval_status', 'vendor_name', 'date_from', 'date_to', 'amount_min', 'amount_max']);
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            $filters['staff_id'] = $staffId;
        }
        $expenses = $this->expenseRepository->paginateForCompany($companyId, $filters, 15);

        return view('accounting::expenses.index', [
            'expenses' => $expenses,
            'filters' => $filters,
            'categories' => $this->expenseCategoryRepository->allForCompany($companyId),
            'staffMembers' => $this->staffOptions($request, $companyId),
            'paymentMethods' => Expense::paymentMethods(),
            'approvalStatuses' => ['pending' => 'Pending Approval', 'approved' => 'Approved'],
            'isStaffScoped' => $this->staffScopeId($request) !== null,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('accounting.expenses.create'), 403);

        $companyId = (int) $user->company_id;

        return view('accounting::expenses.create', [
            'expense' => new Expense(['expense_date' => now()->toDateString(), 'payment_method' => 'Cash']),
            'categories' => $this->expenseCategoryRepository->allForCompany($companyId),
            'staffMembers' => $this->staffOptions($request, $companyId),
            'paymentMethods' => Expense::paymentMethods(),
            'isStaffScoped' => $this->staffScopeId($request) !== null,
        ]);
    }

    public function show(Request $request, Expense $expense)
    {
        $this->ensureExpenseAccess($request, $expense, 'accounting.expenses.view');

        return view('accounting::expenses.show', compact('expense'));
    }

    public function store(StoreExpenseRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $data['company_id'] = (int) $user->company_id;
        $data['created_by'] = (int) $user->id;
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            $data['staff_id'] = $staffId;
            $data['approval_status'] = 'pending';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
        } else {
            $data['approval_status'] = 'approved';
            $data['approved_by'] = (int) $user->id;
            $data['approved_at'] = now();
        }

        $expense = $this->transactionService->storeExpense($data, $request->file('attachment'));

        return redirect()->route('accounting.expenses.index')->with('success', $expense->approval_status === 'pending' ? 'Expense submitted for admin approval.' : 'Expense recorded successfully.');
    }

    public function edit(Request $request, Expense $expense)
    {
        $this->ensureExpenseAccess($request, $expense, 'accounting.expenses.edit');
        $companyId = (int) $request->user()->company_id;

        return view('accounting::expenses.edit', [
            'expense' => $expense,
            'categories' => $this->expenseCategoryRepository->allForCompany($companyId),
            'staffMembers' => $this->staffOptions($request, $companyId),
            'paymentMethods' => Expense::paymentMethods(),
            'isStaffScoped' => $this->staffScopeId($request) !== null,
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense)
    {
        $this->ensureExpenseAccess($request, $expense, 'accounting.expenses.edit');

        $data = $request->validated();
        $user = $request->user();
        $data['company_id'] = (int) $user->company_id;
        $data['created_by'] = (int) $user->id;
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            $data['staff_id'] = $staffId;
            $data['approval_status'] = 'pending';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
        }

        $this->transactionService->updateExpense(
            $expense,
            $data,
            $request->file('attachment'),
            (bool) $request->boolean('remove_attachment')
        );

        return redirect()->route('accounting.expenses.index')->with('success', 'Expense updated successfully.');
    }

    public function destroy(Request $request, Expense $expense)
    {
        $this->ensureExpenseAccess($request, $expense, 'accounting.expenses.delete');
        $this->transactionService->deleteExpense($expense);

        return redirect()->route('accounting.expenses.index')->with('success', 'Expense deleted successfully.');
    }

    public function approve(Request $request, Expense $expense)
    {
        $this->ensureExpenseAccess($request, $expense, 'accounting.expenses.edit');
        abort_unless($request->user()?->isCompanyAdmin(), 403);

        $expense->update([
            'approval_status' => 'approved',
            'approved_by' => (int) $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Expense approved successfully.');
    }

    public function export(Request $request, LetterheadPdfService $letterheadPdfService)
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('accounting.expenses.view'), 403);

        $companyId = (int) $user->company_id;
        $filters = $request->only(['search', 'category_id', 'payment_method', 'staff_id', 'approval_status', 'vendor_name', 'date_from', 'date_to', 'amount_min', 'amount_max']);
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            $filters['staff_id'] = $staffId;
        }

        $rows = $this->expenseRepository->paginateForCompany($companyId, $filters, 1000)
            ->getCollection()
            ->map(fn (Expense $expense) => [
                optional($expense->expense_date)->format('Y-m-d'),
                $expense->category?->name,
                $expense->vendor_name,
                $expense->title,
                $expense->amount,
                $expense->payment_method,
                $expense->transaction_no,
                ucfirst((string) $expense->approval_status),
                $expense->creator?->name,
            ]);

        $format = strtolower((string) $request->string('format', 'xlsx'));
        $fileBase = 'expense-' . now()->format('Y-m-d-His');
        $headings = ['Date', 'Category', 'Vendor', 'Title', 'Amount', 'Payment Method', 'Transaction No', 'Approval', 'Created By'];

        if ($format === 'csv') {
            return Excel::download(new AccountingTransactionsExport($rows, $headings), $fileBase . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('accounting::exports.transactions', [
                'title' => 'Expense Export',
                'rows' => $rows,
                'headings' => $headings,
            ]);

            return $letterheadPdfService->download($pdf, $fileBase . '.pdf');
        }

        if ($format === 'print') {
            return view('accounting::exports.transactions', [
                'title' => 'Expense Export',
                'rows' => $rows,
                'headings' => $headings,
            ]);
        }

        return Excel::download(new AccountingTransactionsExport($rows, $headings), $fileBase . '.xlsx');
    }

    public function downloadAttachment(Request $request, Expense $expense)
    {
        $this->ensureExpenseAccess($request, $expense, 'accounting.expenses.view');

        $path = $this->normalizeAttachmentPath($expense->attachment);
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Attachment not found.');

        $fileName = basename($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk('public')->response($path, $fileName, [
            'Content-Type' => $mime,
        ]);
    }

    private function ensureExpenseAccess(Request $request, Expense $expense, string $permission): void
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless((int) $expense->company_id === (int) $user->company_id, 403);
        abort_unless($user->hasPermission($permission), 403);
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            abort_unless((int) $expense->staff_id === $staffId, 403);
        }
    }

    private function staffScopeId(Request $request): ?int
    {
        $user = $request->user();
        if (! $user || $user->isCompanyAdmin()) {
            return null;
        }

        $staffId = $user->staff?->id;

        return $staffId ? (int) $staffId : 0;
    }

    private function staffOptions(Request $request, int $companyId)
    {
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            return Staff::query()->whereKey($staffId)->get(['id', 'name']);
        }

        return Staff::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']);
    }

    private function normalizeAttachmentPath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        return $path !== '' ? $path : null;
    }
}
