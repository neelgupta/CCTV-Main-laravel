<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Models\Client;
use App\Models\Income;
use App\Models\TicketInvoice;
use App\Modules\Accounting\Contracts\IncomeCategoryRepositoryInterface;
use App\Modules\Accounting\Contracts\IncomeRepositoryInterface;
use App\Modules\Accounting\Exports\AccountingTransactionsExport;
use App\Modules\Accounting\Http\Requests\StoreIncomeRequest;
use App\Modules\Accounting\Http\Requests\UpdateIncomeRequest;
use App\Modules\Accounting\Services\AccountingTransactionService;
use App\Services\ClientOutstandingAmountService;
use App\Services\LetterheadPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class IncomeController
{
    public function __construct(
        private readonly IncomeRepositoryInterface $incomeRepository,
        private readonly IncomeCategoryRepositoryInterface $incomeCategoryRepository,
        private readonly AccountingTransactionService $transactionService,
        private readonly ClientOutstandingAmountService $clientOutstandingAmountService,
    ) {
    }

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('accounting.incomes.view'), 403);

        $companyId = (int) $user->company_id;
        $filters = $request->only(['search', 'payment_method', 'client_id', 'staff_id', 'date_from', 'date_to', 'amount_min', 'amount_max']);
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            $filters['staff_id'] = $staffId;
        }
        $incomes = $this->incomeRepository->paginateForCompany($companyId, $filters, 15);

        return view('accounting::incomes.index', [
            'incomes' => $incomes,
            'filters' => $filters,
            'clients' => Client::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'paymentMethods' => Income::paymentMethods(),
            'summary' => [
                'income' => (float) $this->incomeRepository->queryForCompany($companyId)
                    ->when($this->staffScopeId($request) !== null, fn ($query) => $query->where('staff_id', $this->staffScopeId($request)))
                    ->sum('amount'),
            ],
            'isStaffScoped' => $this->staffScopeId($request) !== null,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasPermission('accounting.incomes.create'), 403);

        $companyId = (int) $user->company_id;
        $prefill = $this->prefillFromInvoice($companyId, $request->integer('invoice_id'));

        return view('accounting::incomes.create', [
            'income' => new Income(array_merge([
                'received_date' => now()->toDateString(),
                'payment_method' => 'Cash',
            ], $prefill)),
            'clients' => Client::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            'clientPendingAmounts' => $this->clientOutstandingAmountService->getSalesInvoicePendingTotalsForCompany($companyId),
            'paymentMethods' => Income::paymentMethods(),
            'isStaffScoped' => $this->staffScopeId($request) !== null,
        ]);
    }

    public function store(StoreIncomeRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();
        $data['company_id'] = (int) $user->company_id;
        $data['created_by'] = (int) $user->id;
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            $data['staff_id'] = $staffId;
        }

        $income = $this->transactionService->storeIncome($data, $request->file('attachment'));

        return redirect()->route('accounting.incomes.show', $income)->with('success', 'Income recorded successfully.');
    }

    public function show(Request $request, Income $income)
    {
        $this->ensureIncomeAccess($request, $income, 'accounting.incomes.view');

        return view('accounting::incomes.show', compact('income'));
    }

    public function downloadAttachment(Request $request, Income $income)
    {
        $this->ensureIncomeAccess($request, $income, 'accounting.incomes.view');

        $path = $this->normalizeAttachmentPath($income->attachment);
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Attachment not found.');

        $fileName = basename($path);
        $mime = Storage::disk('public')->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk('public')->response($path, $fileName, [
            'Content-Type' => $mime,
        ]);
    }

    public function edit(Request $request, Income $income)
    {
        $this->ensureIncomeAccess($request, $income, 'accounting.incomes.edit');
        $companyId = (int) $request->user()->company_id;

        return view('accounting::incomes.edit', [
            'income' => $income,
            'categories' => $this->incomeCategoryRepository->allForCompany($companyId),
            'clients' => Client::query()->where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
            // 'staffMembers' => self::staffOptions($request, $companyId),
            'paymentMethods' => Income::paymentMethods(),
            'invoices' => $this->invoiceOptions($companyId, $income),
            'isStaffScoped' => $this->staffScopeId($request) !== null,
        ]);
    }

    public function update(UpdateIncomeRequest $request, Income $income)
    {
        $this->ensureIncomeAccess($request, $income, 'accounting.incomes.edit');
        $data = $request->validated();
        $user = $request->user();
        $data['company_id'] = (int) $user->company_id;
        $data['created_by'] = (int) $user->id;
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            $data['staff_id'] = $staffId;
        }

        $income = $this->transactionService->updateIncome(
            $income,
            $data,
            $request->file('attachment'),
            (bool) $request->boolean('remove_attachment')
        );

        return redirect()->route('accounting.incomes.show', $income)->with('success', 'Income updated successfully.');
    }

    public function destroy(Request $request, Income $income)
    {
        $this->ensureIncomeAccess($request, $income, 'accounting.incomes.delete');
        $this->transactionService->deleteIncome($income);

        return redirect()->route('accounting.incomes.index')->with('success', 'Income deleted successfully.');
    }

    public function export(Request $request, LetterheadPdfService $letterheadPdfService)
    {
        $user = $request->user();
        abort_unless($user && $user->hasPermission('accounting.incomes.view'), 403);

        $companyId = (int) $user->company_id;
        $filters = $request->only(['search', 'payment_method', 'client_id', 'staff_id', 'date_from', 'date_to', 'amount_min', 'amount_max']);
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            $filters['staff_id'] = $staffId;
        }

        $rows = $this->incomeRepository->paginateForCompany($companyId, $filters, 1000)
            ->getCollection()
            ->map(fn (Income $income) => [
                optional($income->received_date)->format('Y-m-d'),
                $income->client?->name,
                $income->title,
                $income->amount,
                $income->payment_method,
                $income->transaction_no,
                $income->creator?->name,
            ]);

        $format = strtolower((string) $request->string('format', 'xlsx'));
        $fileBase = 'income-' . now()->format('Y-m-d-His');

        if ($format === 'csv') {
            return Excel::download(new AccountingTransactionsExport($rows, ['Date', 'Client', 'Title', 'Amount', 'Payment Method', 'Transaction No', 'Created By']), $fileBase . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('accounting::exports.transactions', [
                'title' => 'Income Export',
                'rows' => $rows,
                'headings' => ['Date', 'Client', 'Title', 'Amount', 'Payment Method', 'Transaction No', 'Created By'],
            ]);

            return $letterheadPdfService->download($pdf, $fileBase . '.pdf');
        }

        if ($format === 'print') {
            return view('accounting::exports.transactions', [
                'title' => 'Income Export',
                'rows' => $rows,
                'headings' => ['Date', 'Client', 'Title', 'Amount', 'Payment Method', 'Transaction No', 'Created By'],
            ]);
        }

        return Excel::download(new AccountingTransactionsExport($rows, ['Date', 'Client', 'Title', 'Amount', 'Payment Method', 'Transaction No', 'Created By']), $fileBase . '.xlsx');
    }

    private function ensureIncomeAccess(Request $request, Income $income, string $permission): void
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless((int) $income->company_id === (int) $user->company_id, 403);
        abort_unless($user->hasPermission($permission), 403);
        $staffId = $this->staffScopeId($request);
        if ($staffId !== null) {
            abort_unless((int) $income->staff_id === $staffId, 403);
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

    private function prefillFromInvoice(int $companyId, ?int $invoiceId): array
    {
        if (! $invoiceId) {
            return [];
        }

        $invoice = TicketInvoice::query()
            ->where('company_id', $companyId)
            ->whereDoesntHave('income')
            ->find($invoiceId);
        if (! $invoice || (int) $invoice->payment_status !== 1) {
            return [];
        }

        return [
            'invoice_id' => $invoice->id,
            'client_id' => $invoice->ticket?->client_id ?? null,
            'staff_id' => $invoice->staff_id,
            'title' => $invoice->invoice_number ? 'Invoice Payment ' . $invoice->invoice_number : 'Invoice Payment',
            'amount' => (float) ($invoice->total_amount ?: $invoice->invoice_total ?: 0),
            'payment_method' => $this->paymentMethodFromInvoice($invoice),
            'transaction_no' => $invoice->invoice_number,
            'received_date' => optional($invoice->received_by_admin_at ?? $invoice->updated_at ?? $invoice->created_at ?? now())->toDateString(),
            'description' => 'Auto-filled from support invoice. Edit before saving if needed.',
        ];
    }

    /**
     * @return array<int, array{id:int, label:string}>
     */
    private function invoiceOptions(int $companyId, ?Income $currentIncome = null): array
    {
        $query = TicketInvoice::query()
            ->where('company_id', $companyId)
            ->with(['ticket:id,ticket_number', 'invoice:id,document_number'])
            ->orderByDesc('id');

        if ($currentIncome?->exists && filled($currentIncome->invoice_id)) {
            $query->orWhereKey($currentIncome->invoice_id);
        }

        return $query
            ->limit(50)
            ->get()
            ->map(function (TicketInvoice $invoice): array {
                $label = $invoice->invoice_number
                    ?: $invoice->invoice?->document_number
                    ?: ('INV-' . $invoice->id);

                if ($invoice->ticket?->ticket_number) {
                    $label .= ' | ' . $invoice->ticket->ticket_number;
                }

                return [
                    'id' => $invoice->id,
                    'label' => $label,
                ];
            })
            ->values()
            ->all();
    }

    private function paymentMethodFromInvoice(TicketInvoice $invoice): string
    {
        return match ((int) ($invoice->payment_method ?? -1)) {
            0 => 'Cash',
            1 => 'Bank',
            2 => 'UPI',
            3 => 'Cheque',
            4 => 'Card',
            default => 'Online',
        };
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
