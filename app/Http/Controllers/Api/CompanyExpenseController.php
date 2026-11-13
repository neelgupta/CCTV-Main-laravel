<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Staff;
use App\Modules\Accounting\Services\AccountingTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can view expenses.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.expenses.view'), 403, 'You are not allowed to view expenses.');

        $companyId = (int) $user->company_id;
        $perPage = max(1, min(100, (int) $request->integer('per_page', 15)));
        $query = Expense::query()
            ->with(['category:id,name', 'staff:id,name', 'creator:id,name', 'approver:id,name'])
            ->where('company_id', $companyId)
            ->when(! $user->isCompanyAdmin(), fn ($builder) => $builder->where('created_by', $user->id))
            ->when(filled($request->query('search')), function ($builder) use ($request): void {
                $search = trim((string) $request->query('search'));
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('vendor_name', 'like', "%{$search}%")
                        ->orWhere('transaction_no', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn ($staffQuery) => $staffQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($request->query('category_id')), fn ($builder) => $builder->where('category_id', (int) $request->query('category_id')))
            ->when(filled($request->query('payment_method')), fn ($builder) => $builder->where('payment_method', $request->query('payment_method')))
            ->when(filled($request->query('staff_id')), fn ($builder) => $builder->where('staff_id', (int) $request->query('staff_id')))
            ->when(filled($request->query('approval_status')), fn ($builder) => $builder->where('approval_status', $request->query('approval_status')))
            ->when(filled($request->query('vendor_name')), fn ($builder) => $builder->where('vendor_name', 'like', '%' . trim((string) $request->query('vendor_name')) . '%'))
            ->when(filled($request->query('date_from')), fn ($builder) => $builder->whereDate('expense_date', '>=', $request->query('date_from')))
            ->when(filled($request->query('date_to')), fn ($builder) => $builder->whereDate('expense_date', '<=', $request->query('date_to')))
            ->when(filled($request->query('amount_min')), fn ($builder) => $builder->where('amount', '>=', (float) $request->query('amount_min')))
            ->when(filled($request->query('amount_max')), fn ($builder) => $builder->where('amount', '<=', (float) $request->query('amount_max')))
            ->orderByDesc('expense_date')
            ->orderByDesc('id');

        return response()->json([
            'message' => 'Expense list fetched successfully.',
            'data' => [
                'expenses' => $query->paginate($perPage)->withQueryString(),
                'payment_methods' => Expense::paymentMethods(),
                'approval_statuses' => ['pending' => 'Pending Approval', 'approved' => 'Approved'],
            ],
        ]);
    }

    public function store(Request $request, AccountingTransactionService $transactionService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can create expenses.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.expenses.create'), 403, 'You are not allowed to create expenses.');

        $companyId = (int) $user->company_id;
        $categoryIds = ExpenseCategory::query()->where('company_id', $companyId)->pluck('id')->all();
        $staffIds = Staff::query()->where('company_id', $companyId)->pluck('id')->all();
        $isCompanyAdmin = $user->isCompanyAdmin();

        $validated = $this->validateJson($request, [
            'category_id' => ['required', 'integer', Rule::in($categoryIds)],
            'staff_id' => ['nullable', 'integer', Rule::in($staffIds)],
            'vendor_name' => ['required', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::paymentMethods()))],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'expense_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,xls,xlsx', 'max:10240'],
        ], [
            'category_id.required' => 'Category is required.',
            'category_id.in' => 'Selected category is not available for this company.',
            'staff_id.in' => 'Selected staff member is not available for this company.',
            'vendor_name.required' => 'Vendor name is required.',
            'vendor_name.max' => 'Vendor name may not be greater than 191 characters.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title may not be greater than 191 characters.',
            'description.max' => 'Description may not be greater than 5000 characters.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be at least 0.01.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Payment method must be Cash, Bank, UPI, Cheque, Card, or Online.',
            'transaction_no.max' => 'Transaction number may not be greater than 100 characters.',
            'expense_date.required' => 'Expense date is required.',
            'expense_date.date' => 'Please enter a valid expense date.',
            'attachment.file' => 'Attachment must be a valid file.',
            'attachment.mimes' => 'Attachment must be a JPG, JPEG, PNG, PDF, XLS, or XLSX file.',
            'attachment.max' => 'Attachment may not be greater than 10 MB.',
        ]);

        $validated['vendor_name'] = trim((string) $validated['vendor_name']);
        $validated['title'] = trim((string) $validated['title']);
        $validated['description'] = filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null;
        $validated['transaction_no'] = filled($validated['transaction_no'] ?? null) ? trim((string) $validated['transaction_no']) : null;
        $validated['expense_date'] = $isCompanyAdmin
            ? (string) $validated['expense_date']
            : now()->toDateString();
        $validated['category_id'] = (int) $validated['category_id'];
        $validated['staff_id'] = filled($validated['staff_id'] ?? null) ? (int) $validated['staff_id'] : null;
        $validated['amount'] = (float) $validated['amount'];

        $expense = $transactionService->storeExpense([
            'company_id' => $companyId,
            'category_id' => $validated['category_id'],
            'staff_id' => $validated['staff_id'],
            'vendor_name' => $validated['vendor_name'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'transaction_no' => $validated['transaction_no'],
            'expense_date' => $validated['expense_date'],
            'approval_status' => $isCompanyAdmin ? 'approved' : 'pending',
            'approved_by' => $isCompanyAdmin ? (int) $user->id : null,
            'approved_at' => $isCompanyAdmin ? now() : null,
            'created_by' => (int) $user->id,
        ], $request->file('attachment'));

        return response()->json([
            'message' => 'Expense created successfully.',
            'data' => [
                'expense' => $expense->fresh(['category:id,name', 'staff:id,name', 'creator:id,name', 'approver:id,name']),
            ],
        ], 201);
    }

    public function update(Request $request, Expense $expense, AccountingTransactionService $transactionService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can update expenses.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.expenses.edit'), 403, 'You are not allowed to update expenses.');
        abort_unless((int) $expense->company_id === (int) $user->company_id, 404, 'Expense not found.');
        abort_unless($user->isCompanyAdmin() || (int) $expense->created_by === (int) $user->id, 403, 'You are not allowed to update this expense.');

        $companyId = (int) $user->company_id;
        $categoryIds = ExpenseCategory::query()->where('company_id', $companyId)->pluck('id')->all();
        $staffIds = Staff::query()->where('company_id', $companyId)->pluck('id')->all();
        $isCompanyAdmin = $user->isCompanyAdmin();

        $editableFields = [
            'category_id',
            'staff_id',
            'vendor_name',
            'title',
            'description',
            'amount',
            'payment_method',
            'transaction_no',
            'expense_date',
        ];

        $request->merge(array_merge([
            'category_id' => $expense->category_id,
            'staff_id' => $expense->staff_id,
            'vendor_name' => $expense->vendor_name,
            'title' => $expense->title,
            'description' => $expense->description,
            'amount' => $expense->amount,
            'payment_method' => $expense->payment_method,
            'transaction_no' => $expense->transaction_no,
            'expense_date' => optional($expense->expense_date)->toDateString(),
        ], $request->only($editableFields)));

        $validated = $this->validateJson($request, [
            'category_id' => ['required', 'integer', Rule::in($categoryIds)],
            'staff_id' => ['nullable', 'integer', Rule::in($staffIds)],
            'vendor_name' => ['required', 'string', 'max:191'],
            'title' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(array_keys(Expense::paymentMethods()))],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'expense_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,xls,xlsx', 'max:10240'],
            'remove_attachment' => ['nullable', 'boolean'],
        ], [
            'category_id.required' => 'Category is required.',
            'category_id.in' => 'Selected category is not available for this company.',
            'staff_id.in' => 'Selected staff member is not available for this company.',
            'vendor_name.required' => 'Vendor name is required.',
            'vendor_name.max' => 'Vendor name may not be greater than 191 characters.',
            'title.required' => 'Title is required.',
            'title.max' => 'Title may not be greater than 191 characters.',
            'description.max' => 'Description may not be greater than 5000 characters.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be at least 0.01.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Payment method must be Cash, Bank, UPI, Cheque, Card, or Online.',
            'transaction_no.max' => 'Transaction number may not be greater than 100 characters.',
            'expense_date.required' => 'Expense date is required.',
            'expense_date.date' => 'Please enter a valid expense date.',
            'attachment.file' => 'Attachment must be a valid file.',
            'attachment.mimes' => 'Attachment must be a JPG, JPEG, PNG, PDF, XLS, or XLSX file.',
            'attachment.max' => 'Attachment may not be greater than 10 MB.',
        ]);

        $validated['vendor_name'] = trim((string) $validated['vendor_name']);
        $validated['title'] = trim((string) $validated['title']);
        $validated['description'] = filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null;
        $validated['transaction_no'] = filled($validated['transaction_no'] ?? null) ? trim((string) $validated['transaction_no']) : null;
        $validated['expense_date'] = $isCompanyAdmin
            ? (string) $validated['expense_date']
            : now()->toDateString();
        $validated['category_id'] = (int) $validated['category_id'];
        $validated['staff_id'] = filled($validated['staff_id'] ?? null) ? (int) $validated['staff_id'] : null;
        $validated['amount'] = (float) $validated['amount'];

        $payload = [
            'company_id' => $companyId,
            'category_id' => $validated['category_id'],
            'staff_id' => $validated['staff_id'],
            'vendor_name' => $validated['vendor_name'],
            'title' => $validated['title'],
            'description' => $validated['description'],
            'amount' => $validated['amount'],
            'payment_method' => $validated['payment_method'],
            'transaction_no' => $validated['transaction_no'],
            'expense_date' => $validated['expense_date'],
        ];

        if (! $isCompanyAdmin) {
            $payload['approval_status'] = 'pending';
            $payload['approved_by'] = null;
            $payload['approved_at'] = null;
        }

        $expense = $transactionService->updateExpense($expense, $payload, $request->file('attachment'), $request->boolean('remove_attachment'));

        return response()->json([
            'message' => 'Expense updated successfully.',
            'data' => [
                'expense' => $expense->fresh(['category:id,name', 'staff:id,name', 'creator:id,name', 'approver:id,name']),
            ],
        ]);
    }

    public function destroy(Request $request, Expense $expense, AccountingTransactionService $transactionService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can delete expenses.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.expenses.delete'), 403, 'You are not allowed to delete expenses.');
        abort_unless((int) $expense->company_id === (int) $user->company_id, 404, 'Expense not found.');
        abort_unless($user->isCompanyAdmin() || (int) $expense->created_by === (int) $user->id, 403, 'You are not allowed to delete this expense.');

        $transactionService->deleteExpense($expense);

        return response()->json([
            'message' => 'Expense deleted successfully.',
        ]);
    }

    private function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => collect($validator->errors())->flatten()->first(),
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
