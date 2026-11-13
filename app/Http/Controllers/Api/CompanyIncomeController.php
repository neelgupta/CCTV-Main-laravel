<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\Staff;
use App\Models\TicketInvoice;
use App\Modules\Accounting\Services\AccountingTransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CompanyIncomeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can view incomes.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.incomes.view'), 403, 'You are not allowed to view incomes.');

        $companyId = (int) $user->company_id;
        $perPage = max(1, min(100, (int) $request->integer('per_page', 15)));
        $query = Income::query()
            ->with(['category:id,name', 'client:id,name', 'staff:id,name', 'creator:id,name', 'invoice:id,invoice_number,ticket_id', 'invoice.ticket:id,ticket_number'])
            ->where('company_id', $companyId)
            ->when(! $user->isCompanyAdmin(), fn ($builder) => $builder->where('created_by', $user->id))
            ->when(filled($request->query('search')), function ($builder) use ($request): void {
                $search = trim((string) $request->query('search'));
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('transaction_no', 'like', "%{$search}%")
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn ($staffQuery) => $staffQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($request->query('category_id')), fn ($builder) => $builder->where('category_id', (int) $request->query('category_id')))
            ->when(filled($request->query('payment_method')), fn ($builder) => $builder->where('payment_method', $request->query('payment_method')))
            ->when(filled($request->query('client_id')), fn ($builder) => $builder->where('client_id', (int) $request->query('client_id')))
            ->when(filled($request->query('staff_id')), fn ($builder) => $builder->where('staff_id', (int) $request->query('staff_id')))
            ->when(filled($request->query('invoice_id')), fn ($builder) => $builder->where('invoice_id', (int) $request->query('invoice_id')))
            ->when(filled($request->query('date_from')), fn ($builder) => $builder->whereDate('received_date', '>=', $request->query('date_from')))
            ->when(filled($request->query('date_to')), fn ($builder) => $builder->whereDate('received_date', '<=', $request->query('date_to')))
            ->when(filled($request->query('amount_min')), fn ($builder) => $builder->where('amount', '>=', (float) $request->query('amount_min')))
            ->when(filled($request->query('amount_max')), fn ($builder) => $builder->where('amount', '<=', (float) $request->query('amount_max')))
            ->orderByDesc('received_date')
            ->orderByDesc('id');

        return response()->json([
            'message' => 'Income list fetched successfully.',
            'data' => [
                'incomes' => $query->paginate($perPage)->withQueryString(),
                'payment_methods' => Income::paymentMethods(),
            ],
        ]);
    }

    public function store(Request $request, AccountingTransactionService $transactionService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can create incomes.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.incomes.create'), 403, 'You are not allowed to create incomes.');

        $companyId = (int) $user->company_id;

        $categoryIds = IncomeCategory::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $clientIds = Client::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $staffIds = Staff::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $invoiceIds = TicketInvoice::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $validated = $this->validateJson($request, [
            'category_id' => ['nullable', 'integer', Rule::in($categoryIds)],
            'invoice_id' => ['nullable', 'integer', Rule::in($invoiceIds), Rule::unique('incomes', 'invoice_id')->where(fn ($query) => $query->where('company_id', $companyId))],
            'client_id' => ['required', 'integer', Rule::in($clientIds)],
            'staff_id' => ['nullable', 'integer', Rule::in($staffIds)],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(array_keys(Income::paymentMethods()))],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'received_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,xls,xlsx', 'max:10240'],
        ], [
            'category_id.in' => 'Selected income category is not available for this company.',
            'invoice_id.in' => 'Selected invoice is not available for this company.',
            'invoice_id.unique' => 'An income entry already exists for this invoice.',
            'client_id.in' => 'Selected client is not available for this company.',
            'client_id.required' => 'Client is required.',
            'staff_id.in' => 'Selected staff member is not available for this company.',
            'title.max' => 'Title may not be greater than 191 characters.',
            'description.max' => 'Description may not be greater than 5000 characters.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be at least 0.01.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Payment method must be Cash, Bank, UPI, Cheque, Card, or Online.',
            'transaction_no.max' => 'Transaction number may not be greater than 100 characters.',
            'received_date.required' => 'Received date is required.',
            'received_date.date' => 'Please enter a valid received date.',
            'attachment.file' => 'Attachment must be a valid file.',
            'attachment.mimes' => 'Attachment must be a JPG, JPEG, PNG, PDF, XLS, or XLSX file.',
            'attachment.max' => 'Attachment may not be greater than 10 MB.',
        ]);

        $title = filled($validated['title'] ?? null)
            ? trim((string) $validated['title'])
            : null;

        $income = $transactionService->storeIncome([
            'company_id' => $companyId,
            'category_id' => array_key_exists('category_id', $validated) && filled($validated['category_id'] ?? null) ? (int) $validated['category_id'] : null,
            'invoice_id' => array_key_exists('invoice_id', $validated) && filled($validated['invoice_id'] ?? null) ? (int) $validated['invoice_id'] : null,
            'client_id' => array_key_exists('client_id', $validated) && filled($validated['client_id'] ?? null) ? (int) $validated['client_id'] : null,
            'staff_id' => array_key_exists('staff_id', $validated) && filled($validated['staff_id'] ?? null) ? (int) $validated['staff_id'] : null,
            'title' => $title,
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            'amount' => (float) $validated['amount'],
            'payment_method' => (string) $validated['payment_method'],
            'transaction_no' => filled($validated['transaction_no'] ?? null) ? trim((string) $validated['transaction_no']) : null,
            'received_date' => (string) $validated['received_date'],
            'created_by' => (int) $user->id,
        ], $request->file('attachment'));

        return response()->json([
            'message' => 'Income created successfully.',
            'data' => [
                'income' => $income->fresh(['category:id,name', 'client:id,name', 'staff:id,name', 'creator:id,name', 'invoice:id,invoice_number,ticket_id', 'invoice.ticket:id,ticket_number']),
            ],
        ], 201);
    }

    public function update(Request $request, Income $income, AccountingTransactionService $transactionService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can update incomes.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.incomes.edit'), 403, 'You are not allowed to update incomes.');
        abort_unless((int) $income->company_id === (int) $user->company_id, 404, 'Income not found.');
        abort_unless($user->isCompanyAdmin() || (int) $income->created_by === (int) $user->id, 403, 'You are not allowed to update this income.');

        $companyId = (int) $user->company_id;

        $categoryIds = IncomeCategory::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $clientIds = Client::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $staffIds = Staff::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $invoiceIds = TicketInvoice::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->all();

        $request->merge(array_merge([
            'category_id' => $income->category_id,
            'invoice_id' => $income->invoice_id,
            'client_id' => $income->client_id,
            'staff_id' => $income->staff_id,
            'title' => $income->title,
            'description' => $income->description,
            'amount' => $income->amount,
            'payment_method' => $income->payment_method,
            'transaction_no' => $income->transaction_no,
            'received_date' => optional($income->received_date)->toDateString(),
        ], $request->only([
            'category_id',
            'invoice_id',
            'client_id',
            'staff_id',
            'title',
            'description',
            'amount',
            'payment_method',
            'transaction_no',
            'received_date',
        ])));

        $validated = $this->validateJson($request, [
            'category_id' => ['nullable', 'integer', Rule::in($categoryIds)],
            'invoice_id' => ['nullable', 'integer', Rule::in($invoiceIds), Rule::unique('incomes', 'invoice_id')->ignore($income->id)->where(fn ($query) => $query->where('company_id', $companyId))],
            'client_id' => ['required', 'integer', Rule::in($clientIds)],
            'staff_id' => ['nullable', 'integer', Rule::in($staffIds)],
            'title' => ['nullable', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:5000'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', Rule::in(array_keys(Income::paymentMethods()))],
            'transaction_no' => ['nullable', 'string', 'max:100'],
            'received_date' => ['required', 'date'],
            'attachment' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,xls,xlsx', 'max:10240'],
            'remove_attachment' => ['nullable', 'boolean'],
        ], [
            'category_id.in' => 'Selected income category is not available for this company.',
            'invoice_id.in' => 'Selected invoice is not available for this company.',
            'invoice_id.unique' => 'An income entry already exists for this invoice.',
            'client_id.in' => 'Selected client is not available for this company.',
            'client_id.required' => 'Client is required.',
            'staff_id.in' => 'Selected staff member is not available for this company.',
            'title.max' => 'Title may not be greater than 191 characters.',
            'description.max' => 'Description may not be greater than 5000 characters.',
            'amount.required' => 'Amount is required.',
            'amount.numeric' => 'Amount must be a number.',
            'amount.min' => 'Amount must be at least 0.01.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Payment method must be Cash, Bank, UPI, Cheque, Card, or Online.',
            'transaction_no.max' => 'Transaction number may not be greater than 100 characters.',
            'received_date.required' => 'Received date is required.',
            'received_date.date' => 'Please enter a valid received date.',
            'attachment.file' => 'Attachment must be a valid file.',
            'attachment.mimes' => 'Attachment must be a JPG, JPEG, PNG, PDF, XLS, or XLSX file.',
            'attachment.max' => 'Attachment may not be greater than 10 MB.',
        ]);

        $title = filled($validated['title'] ?? null)
            ? trim((string) $validated['title'])
            : null;

        $income = $transactionService->updateIncome($income, [
            'company_id' => $companyId,
            'category_id' => array_key_exists('category_id', $validated) && filled($validated['category_id'] ?? null) ? (int) $validated['category_id'] : null,
            'invoice_id' => array_key_exists('invoice_id', $validated) && filled($validated['invoice_id'] ?? null) ? (int) $validated['invoice_id'] : null,
            'client_id' => array_key_exists('client_id', $validated) && filled($validated['client_id'] ?? null) ? (int) $validated['client_id'] : null,
            'staff_id' => array_key_exists('staff_id', $validated) && filled($validated['staff_id'] ?? null) ? (int) $validated['staff_id'] : null,
            'title' => $title,
            'description' => filled($validated['description'] ?? null) ? trim((string) $validated['description']) : null,
            'amount' => (float) $validated['amount'],
            'payment_method' => (string) $validated['payment_method'],
            'transaction_no' => filled($validated['transaction_no'] ?? null) ? trim((string) $validated['transaction_no']) : null,
            'received_date' => (string) $validated['received_date'],
        ], $request->file('attachment'), $request->boolean('remove_attachment'));

        return response()->json([
            'message' => 'Income updated successfully.',
            'data' => [
                'income' => $income->fresh(['category:id,name', 'client:id,name', 'staff:id,name', 'creator:id,name', 'invoice:id,invoice_number,ticket_id', 'invoice.ticket:id,ticket_number']),
            ],
        ]);
    }

    public function destroy(Request $request, Income $income, AccountingTransactionService $transactionService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403, 'Only company users can delete incomes.');
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('accounting.incomes.delete'), 403, 'You are not allowed to delete incomes.');
        abort_unless((int) $income->company_id === (int) $user->company_id, 404, 'Income not found.');
        abort_unless($user->isCompanyAdmin() || (int) $income->created_by === (int) $user->id, 403, 'You are not allowed to delete this income.');

        $transactionService->deleteIncome($income);

        return response()->json([
            'message' => 'Income deleted successfully.',
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
