<?php

namespace App\Modules\Accounting\Services;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\IncomeCategory;
use App\Models\TicketInvoice;
use App\Modules\Accounting\Contracts\ExpenseRepositoryInterface;
use App\Modules\Accounting\Contracts\IncomeRepositoryInterface;
use App\Services\MediaUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountingTransactionService
{
    public function __construct(
        private readonly IncomeRepositoryInterface $incomeRepository,
        private readonly ExpenseRepositoryInterface $expenseRepository,
        private readonly MediaUploadService $fileUploadService,
    ) {
    }

    public function storeIncome(array $attributes, ?UploadedFile $attachment = null): Income
    {
        return DB::transaction(function () use ($attributes, $attachment): Income {
            $payload = $this->prepareIncomePayload($attributes, $attachment);
            return $this->incomeRepository->create($payload);
        });
    }

    public function updateIncome(Income $income, array $attributes, ?UploadedFile $attachment = null, bool $removeAttachment = false): Income
    {
        return DB::transaction(function () use ($income, $attributes, $attachment, $removeAttachment): Income {
            $payload = $this->prepareIncomePayload($attributes, $attachment, $income, $removeAttachment);
            $this->incomeRepository->update($income, $payload);
            return $income->refresh();
        });
    }

    public function deleteIncome(Income $income): bool|null
    {
        $this->deleteAttachmentIfPresent($income->attachment);
        return $this->incomeRepository->delete($income);
    }

    public function storeExpense(array $attributes, ?UploadedFile $attachment = null): Expense
    {
        return DB::transaction(function () use ($attributes, $attachment): Expense {
            $payload = $this->prepareExpensePayload($attributes, $attachment);
            return $this->expenseRepository->create($payload);
        });
    }

    public function updateExpense(Expense $expense, array $attributes, ?UploadedFile $attachment = null, bool $removeAttachment = false): Expense
    {
        return DB::transaction(function () use ($expense, $attributes, $attachment, $removeAttachment): Expense {
            $payload = $this->prepareExpensePayload($attributes, $attachment, $expense, $removeAttachment);
            $this->expenseRepository->update($expense, $payload);
            return $expense->refresh();
        });
    }

    public function deleteExpense(Expense $expense): bool|null
    {
        $this->deleteAttachmentIfPresent($expense->attachment);
        return $this->expenseRepository->delete($expense);
    }

    public function syncIncomeFromInvoice(TicketInvoice $ticketInvoice): ?Income
    {
        if ((int) $ticketInvoice->payment_status !== 1) {
            return null;
        }

        $companyId = (int) $ticketInvoice->company_id;
        $category = IncomeCategory::query()
            ->where('company_id', $companyId)
            ->where(function ($query): void {
                $query->whereRaw('LOWER(name) = ?', ['support income'])
                    ->orWhereRaw('LOWER(name) = ?', ['service income'])
                    ->orWhereRaw('LOWER(name) = ?', ['sales income'])
                    ->orWhereRaw('LOWER(name) = ?', ['invoice income']);
            })
            ->first()
            ?? IncomeCategory::query()->where('company_id', $companyId)->orderBy('id')->first();

        if (! $category) {
            $category = IncomeCategory::query()->create([
                'company_id' => $companyId,
                'name' => 'Service Income',
                'description' => 'Income from service and support invoices.',
                'status' => 'active',
            ]);
        }

        $clientId = $ticketInvoice->ticket?->customer_id 
            ?? $ticketInvoice->ticket?->client_id 
            ?? $ticketInvoice->client_id 
            ?? null;

        $payload = [
            'company_id' => $companyId,
            'category_id' => $category->id,
            'invoice_id' => $ticketInvoice->id,
            'client_id' => $clientId,
            'staff_id' => $ticketInvoice->staff_id,
            'title' => Str::limit('Invoice Payment ' . ($ticketInvoice->invoice_number ?: $ticketInvoice->id), 191, ''),
            'description' => implode("\n", array_filter([
                'Auto-created from paid invoice.',
                $ticketInvoice->ticket?->ticket_number ? 'Ticket: ' . $ticketInvoice->ticket->ticket_number : null,
                'Invoice No: ' . ($ticketInvoice->invoice_number ?: '-'),
            ])),
            'amount' => (float) ($ticketInvoice->total_amount ?: $ticketInvoice->invoice_total ?: 0),
            'payment_method' => $this->incomePaymentMethodFromTicketInvoice($ticketInvoice),
            'transaction_no' => $ticketInvoice->invoice_number ?: ('INV-' . $ticketInvoice->id),
            'received_date' => optional($ticketInvoice->received_by_admin_at ?? $ticketInvoice->updated_at ?? $ticketInvoice->created_at ?? now())->toDateString(),
            'created_by' => $ticketInvoice->received_by_admin_by ?: $ticketInvoice->created_by,
        ];

        return Income::query()->updateOrCreate(
            ['company_id' => $companyId, 'invoice_id' => $ticketInvoice->id],
            $payload
        );
    }

    private function prepareIncomePayload(array $attributes, ?UploadedFile $attachment = null, ?Income $income = null, bool $removeAttachment = false): array
    {
        $payload = Arr::only($attributes, [
            'company_id',
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
            'created_by',
        ]);

        if (blank($payload['title'] ?? null)) {
            $payload['title'] = $this->generateIncomeTitle($payload);
        }

        if ($attachment) {
            $this->deleteAttachmentIfPresent($income?->attachment);
            $payload['attachment'] = $this->fileUploadService->store(
                $attachment,
                'accounting/' . ($payload['company_id'] ?? 'global') . '/income',
                'public'
            );
        } elseif ($removeAttachment) {
            $this->deleteAttachmentIfPresent($income?->attachment);
            $payload['attachment'] = null;
        } elseif ($income && array_key_exists('attachment', $income->getAttributes())) {
            $payload['attachment'] = $income->attachment;
        }

        return $payload;
    }

    private function prepareExpensePayload(array $attributes, ?UploadedFile $attachment = null, ?Expense $expense = null, bool $removeAttachment = false): array
    {
        $payload = Arr::only($attributes, [
            'company_id',
            'category_id',
            'staff_id',
            'vendor_name',
            'title',
            'description',
            'amount',
            'payment_method',
            'transaction_no',
            'expense_date',
            'approval_status',
            'approved_by',
            'approved_at',
            'created_by',
        ]);

        if ($attachment) {
            $this->deleteAttachmentIfPresent($expense?->attachment);
            $payload['attachment'] = $this->fileUploadService->store(
                $attachment,
                'accounting/' . ($payload['company_id'] ?? 'global') . '/expense',
                'public'
            );
        } elseif ($removeAttachment) {
            $this->deleteAttachmentIfPresent($expense?->attachment);
            $payload['attachment'] = null;
        } elseif ($expense && array_key_exists('attachment', $expense->getAttributes())) {
            $payload['attachment'] = $expense->attachment;
        }

        return $payload;
    }

    private function deleteAttachmentIfPresent(?string $path): void
    {
        if (blank($path)) {
            return;
        }

        $this->fileUploadService->delete($path, 'public');
    }

    private function incomePaymentMethodFromTicketInvoice(TicketInvoice $ticketInvoice): string
    {
        return match ((int) ($ticketInvoice->payment_method ?? -1)) {
            0 => 'Cash',
            1 => 'Bank',
            2 => 'UPI',
            3 => 'Cheque',
            4 => 'Card',
            default => 'Online',
        };
    }

    private function generateIncomeTitle(array $payload): string
    {
        $parts = ['Income'];

        if (! empty($payload['client_id'])) {
            $clientName = \App\Models\Client::query()->find($payload['client_id'])?->name;
            if (filled($clientName)) {
                $parts[] = 'from ' . $clientName;
            }
        }

        if (! empty($payload['payment_method'])) {
            $parts[] = '(' . $payload['payment_method'] . ')';
        }

        return Str::limit(trim(implode(' ', $parts)), 191, '');
    }
}
