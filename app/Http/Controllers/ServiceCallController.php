<?php

namespace App\Http\Controllers;

use App\Exports\ServiceCallRecordsExport;
use App\Models\BoqProduct;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGstRate;
use App\Models\CompanyUnit;
use App\Models\QuotationTemplate;
use App\Models\State;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Services\BillingDocumentItemBuilder;
use App\Services\LetterheadPdfService;
use App\Services\ServiceCallListingService;
use App\Services\TicketDocumentLinkService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class ServiceCallController extends Controller
{
    public function __construct(
        private readonly ServiceCallListingService $listingService,
    ) {
    }

    public function invoices(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('support.view', 'tickets.view', 'tickets.reply', 'tickets.manage'), 403);

        $companyId = (int) $user->company_id;
        $filters = $this->normalizeFilters($request, ['start_date', 'end_date', 'status', 'staff_id', 'search']);
        $staffMembers = $this->listingService->staffOptions($companyId);

        $query = $this->listingService->invoiceQuery($companyId, $filters, $user);
        $paginator = $this->paginateQuery($request, $query, 15);

        if ($redirect = $this->redirectIfInvalidPage($request, $paginator, route('service-call.invoices.index'))) {
            return $redirect;
        }

        return view('service-call.invoices.index', [
            'invoices' => $paginator,
            'filters' => $filters,
            'staffMembers' => $staffMembers,
            'statusOptions' => $this->listingService->invoiceStatusOptions(),
        ]);
    }

    public function createInvoice(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('tickets.reply', 'tickets.manage'), 403);

        $company = $user->company ?: Company::query()->find($user->company_id);
        abort_unless($company, 403);
        $documentNumber = $company->nextAvailableDocumentNumber('invoice');

        if (! $documentNumber) {
            return redirect()
                ->route('service-call.invoices.index')
                ->with('error', 'Please configure a valid Invoice Number in Company Settings.');
        }

        return $this->billingDocumentForm($user, 'invoice', null, $documentNumber);
    }

    public function invoiceExport(Request $request, LetterheadPdfService $letterheadPdfService)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('support.view', 'tickets.view', 'tickets.reply', 'tickets.manage'), 403);

        $companyId = (int) $user->company_id;
        $filters = $this->normalizeFilters($request, ['start_date', 'end_date', 'status', 'staff_id', 'search']);
        $rows = $this->listingService->invoiceQuery($companyId, $filters, $user)
            ->get()
            ->map(function ($invoice) {
                return [
                    optional($invoice->created_at)->format('Y-m-d'),
                    $invoice->invoice_number ?: ('INV-' . $invoice->id),
                    $invoice->ticket?->ticket_number,
                    $invoice->ticket?->customer_name ?? $invoice->client?->name,
                    $invoice->ticket?->project?->client_name,
                    $invoice->ticket?->customer_contact_number ?? $invoice->client?->mobile ?? $invoice->client?->phone,
                    $invoice->ticket?->customer_email ?? $invoice->client?->email,
                    $invoice->staff?->name,
                    $this->listingService->invoiceStatusLabel($invoice),
                    (float) ($invoice->total_amount ?? $invoice->invoice_total ?? 0),
                    $invoice->creator?->name,
                ];
            });

        return $this->downloadRows($request, $rows, [
            'Invoice Date',
            'Invoice No',
            'Ticket No',
            'Client Name',
            'Company Name',
            'Mobile',
            'Email',
            'Staff',
            'Status',
            'Amount',
            'Created By',
        ], 'invoice-service-call', $letterheadPdfService, $user->company);
    }

    public function quotations(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('leads.quotation.manage', 'support.view', 'tickets.view', 'tickets.reply', 'tickets.manage'), 403);

        $companyId = (int) $user->company_id;
        $filters = $this->normalizeFilters($request, ['start_date', 'end_date', 'status', 'staff_id', 'search']);
        $staffMembers = $this->listingService->staffOptions($companyId);

        $records = $this->listingService->quotationRecords($companyId, $filters);
        $paginator = $this->paginateCollection($request, $records, 15);

        if ($redirect = $this->redirectIfInvalidPage($request, $paginator, route('service-call.quotations.index'))) {
            return $redirect;
        }

        return view('service-call.quotations.index', [
            'quotations' => $paginator,
            'filters' => $filters,
            'staffMembers' => $staffMembers,
            'statusOptions' => $this->listingService->quotationStatusOptions(),
        ]);
    }

    public function createQuotation(Request $request)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('tickets.reply', 'tickets.manage'), 403);

        $company = $user->company ?: Company::query()->find($user->company_id);
        abort_unless($company, 403);
        $documentNumber = $company->nextAvailableDocumentNumber('quotation');

        if (! $documentNumber) {
            return redirect()
                ->route('service-call.quotations.index')
                ->with('error', 'Please configure a valid Quotation Number in Company Settings.');
        }

        return $this->billingDocumentForm($user, 'quotation', null, $documentNumber);
    }

    public function quotationExport(Request $request, LetterheadPdfService $letterheadPdfService)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('leads.quotation.manage', 'support.view', 'tickets.view', 'tickets.reply', 'tickets.manage'), 403);

        $companyId = (int) $user->company_id;
        $filters = $this->normalizeFilters($request, ['start_date', 'end_date', 'status', 'staff_id', 'search']);
        $rows = $this->listingService->quotationRecords($companyId, $filters)
            ->map(function (array $record) {
                return [
                    optional($record['date'] ?? null)->format('Y-m-d'),
                    match ($record['source']) {
                        'lead' => 'Lead Quotation',
                        'client' => 'Client Quotation',
                        default => 'Ticket Quotation',
                    },
                    $record['number'],
                    $record['client_name'],
                    $record['company_name'],
                    $record['mobile'],
                    $record['email'],
                    $record['staff_name'],
                    $record['status_label'],
                    (float) ($record['amount'] ?? 0),
                ];
            });

        return $this->downloadRows($request, $rows, [
            'Quotation Date',
            'Source',
            'Quotation No',
            'Client Name',
            'Company Name',
            'Mobile',
            'Email',
            'Staff',
            'Status',
            'Amount',
        ], 'quotation-service-call', $letterheadPdfService, $user->company);
    }

    private function normalizeFilters(Request $request, array $keys): array
    {
        $filters = [];

        foreach ($keys as $key) {
            $filters[$key] = is_string($request->input($key))
                ? trim((string) $request->input($key))
                : $request->input($key);
        }

        return $filters;
    }

    private function billingDocumentForm($user, string $documentType, ?TicketDocument $document = null, ?string $documentNumber = null)
    {
        $company = $user->company ?: Company::query()->find($user->company_id);
        $documentNumber = $document?->document_number
            ?: $documentNumber
            ?: $company?->nextAvailableDocumentNumber($documentType);
        $clients = Client::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'mobile', 'phone', 'gst_number', 'address', 'state_id', 'city_id']);

        $quotationTemplates = QuotationTemplate::query()
            ->where('company_id', $user->company_id)
            ->latest()
            ->get();

        return view('service-call.billing-form', [
            'documentType' => $documentType,
            'clients' => $clients,
            'states' => State::query()
                ->with(['cities' => fn ($query) => $query->orderBy('name')])
                ->orderBy('name')
                ->get(['id', 'name']),
            'companyState' => (string) ($company?->state ?? ''),
            'company' => $company,
            'documentNumber' => $documentNumber,
            'gstRates' => $company
                ? $company->gstRates()->get(['id', 'rate'])
                : collect(),
            'billingUnits' => CompanyUnit::query()
                ->where('company_id', $user->company_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'billingProducts' => BoqProduct::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'brand', 'model', 'unit', 'price', 'gst', 'description']),
            'document' => $document,
            'quotationTemplates' => $quotationTemplates,
        ]);
    }

    public function editClientBillingDocument(Request $request, TicketDocument $document)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('tickets.reply', 'tickets.manage'), 403);
        abort_unless((int) $document->company_id === (int) $user->company_id, 403);
        abort_if($document->ticket_id !== null, 404);

        return $this->billingDocumentForm($user, (string) $document->document_type, $document->loadMissing(['client', 'company']));
    }

    public function showClientBillingDocument(Request $request, TicketDocument $document, LetterheadPdfService $letterheadPdfService)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $document->company_id === (int) $user->company_id, 403);
        abort_if($document->ticket_id !== null, 404);

        $document->loadMissing(['client', 'company']);
        $type = $document->document_type === 'invoice' ? 'invoice' : 'quotation';
        $link = $type === 'invoice'
            ? \App\Models\TicketInvoice::query()->where('invoice_id', $document->id)->latest('id')->first()
            : \App\Models\TicketQuote::query()->where('quote_id', $document->id)->latest('id')->first();

        $items = collect($document->items ?? [])->filter(fn ($item) => is_array($item))->values();
        $subtotal = (float) ($document->subtotal ?? $items->sum(fn ($item) => (float) ($item['base_total'] ?? 0)));
        $discount = (float) ($document->discount ?? 0);
        $gstAmount = (float) ($document->gst_amount ?? $items->sum(fn ($item) => (float) ($item['gst_amount'] ?? 0)));
        $total = (float) ($document->total ?? max(0, $subtotal - $discount + $gstAmount));

        $paymentStatus = null;
        $paymentMethod = null;
        if ($type === 'invoice') {
            $paymentStatus = (int) ($link?->payment_status ?? 0) === 1 ? 'Paid' : 'Unpaid';
            $paymentMethod = (int) ($link?->payment_status ?? 0) === 1
                ? (in_array((int) ($link?->payment_method ?? -1), [0, 2], true) ? 'Cash' : 'Online')
                : '-';
        }

        $useLetterhead = $letterheadPdfService->hasActiveLetterhead($user->company);
        $pdfLayout = $this->resolvePdfLayout($user->company, $useLetterhead, $letterheadPdfService);

        return view('tickets.document', [
            'ticket' => null,
            'client' => $document->client,
            'document' => $document,
            'link' => $link,
            'type' => $type,
            'autoPrint' => $request->boolean('print'),
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'gstAmount' => $gstAmount,
            'total' => $total,
            // 'paymentStatus' => $paymentStatus,
            // 'paymentMethod' => $paymentMethod,
            'backUrl' => $type === 'invoice' ? route('service-call.invoices.index') : route('service-call.quotations.index'),
            'backLabel' => $type === 'invoice' ? 'Back to Invoices' : 'Back to Quotations',
            'useLetterhead' => $useLetterhead,
            'hideDefaultHeader' => $useLetterhead,
            'pdfPreviewUrl' => route('service-call.billing-document.download', ['document' => $document, 'inline' => 1]),
            'pdfMargins' => $pdfLayout['pdfMargins'],
            'pdfPageMargin' => $pdfLayout['pdfPageMargin'],
            'pdfPrintableWidthMm' => $pdfLayout['pdfPrintableWidthMm'],
        ]);
    }

    public function downloadClientBillingDocument(Request $request, TicketDocument $document, LetterheadPdfService $letterheadPdfService)
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_unless((int) $document->company_id === (int) $user->company_id, 403);
        abort_if($document->ticket_id !== null, 404);

        $document->loadMissing(['client', 'company']);
        $type = $document->document_type === 'invoice' ? 'invoice' : 'quotation';
        $link = $type === 'invoice'
            ? \App\Models\TicketInvoice::query()->where('invoice_id', $document->id)->latest('id')->first()
            : \App\Models\TicketQuote::query()->where('quote_id', $document->id)->latest('id')->first();

        $items = collect($document->items ?? [])->filter(fn ($item) => is_array($item))->values();
        $subtotal = (float) ($document->subtotal ?? $items->sum(fn ($item) => (float) ($item['base_total'] ?? 0)));
        $discount = (float) ($document->discount ?? 0);
        $gstAmount = (float) ($document->gst_amount ?? $items->sum(fn ($item) => (float) ($item['gst_amount'] ?? 0)));
        $total = (float) ($document->total ?? max(0, $subtotal - $discount + $gstAmount));

        $paymentStatus = null;
        $paymentMethod = null;
        if ($type === 'invoice') {
            $paymentStatus = (int) ($link?->payment_status ?? 0) === 1 ? 'Paid' : 'Unpaid';
            $paymentMethod = (int) ($link?->payment_status ?? 0) === 1
                ? (in_array((int) ($link?->payment_method ?? -1), [0, 2], true) ? 'Cash' : 'Online')
                : '-';
        }

        $useLetterhead = $letterheadPdfService->hasActiveLetterhead($user->company);
        $pdfLayout = $this->resolvePdfLayout($user->company, $useLetterhead, $letterheadPdfService);

        $view = view('tickets.document', [
            'ticket' => null,
            'client' => $document->client,
            'document' => $document,
            'link' => $link,
            'type' => $type,
            'autoPrint' => false,
            'isPdf' => true,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'gstAmount' => $gstAmount,
            'total' => $total,
            'paymentStatus' => $paymentStatus,
            'paymentMethod' => $paymentMethod,
            'backUrl' => route('service-call.billing-document.show', $document),
            'backLabel' => 'Back to Document',
            'useLetterhead' => $useLetterhead,
            'hideDefaultHeader' => $useLetterhead,
            'pdfPreviewUrl' => route('service-call.billing-document.download', ['document' => $document, 'inline' => 1]),
            'pdfMargins' => $pdfLayout['pdfMargins'],
            'pdfPageMargin' => $pdfLayout['pdfPageMargin'],
            'pdfPrintableWidthMm' => $pdfLayout['pdfPrintableWidthMm'],
        ]);

        $pdf = Pdf::loadView('tickets.document', $view->getData())
            ->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 96,
            ]);

        $fileName = strtolower($type) . '-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string) ($document->document_number ?? $document->id)) . '.pdf';

        return $letterheadPdfService->download($pdf, $fileName, $user->company);
    }

    public function saveClientBillingDocument(Request $request)
    {
        return $this->persistClientBillingDocument($request, null);
    }

    public function updateClientBillingDocument(Request $request, TicketDocument $document)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('tickets.reply', 'tickets.manage'), 403);
        abort_unless((int) $document->company_id === (int) $user->company_id, 403);
        abort_if($document->ticket_id !== null, 404);

        return $this->persistClientBillingDocument($request, $document);
    }

    private function persistClientBillingDocument(Request $request, ?TicketDocument $document)
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($user->hasAnyPermission('tickets.reply', 'tickets.manage'), 403);
        $company = $user->company ?: Company::query()->find($user->company_id);
        abort_unless($company, 403);
       
        $validated = $request->validate([
            'document_type' => ['required', Rule::in(['invoice', 'quotation'])],
            'client_id' => ['nullable', 'integer', Rule::exists('clients', 'id')->where(fn ($query) => $query->where('company_id', $company->id))],
            'subject' => ['nullable', 'string', 'max:255'],
            'client_name' => ['required_without:client_id', 'nullable', 'string', 'min:2', 'max:191'],
            'client_mobile' => ['nullable', 'string', 'max:30'],
            'client_email' => ['nullable', 'email', 'max:191'],
            'client_gst_number' => ['nullable', 'string', 'max:20'],
            'client_address' => ['nullable', 'string', 'max:1000'],
            'client_state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'client_city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'product_id' => [$company->usesManualProductEntry() ? 'nullable' : 'required', 'array', 'min:1'],
            'product_id.*' => ['nullable', 'integer'],
            'name' => [$company->usesManualProductEntry() ? 'required' : 'nullable', 'array', 'min:1'],
            'name.*' => ['nullable', 'string', 'max:500'],
            'unit' => ['nullable', 'array'],
            'unit.*' => ['nullable', 'string', 'max:50'],
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'numeric', 'min:0.01'],
            'price' => ['nullable', 'array'],
            'price.*' => ['nullable', 'numeric', 'min:0'],
            'gst' => ['nullable', 'array'],
            'gst.*' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'brand_model' => ['nullable', 'array'],
            'brand_model.*' => ['nullable', 'string', 'max:500'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'additional_notes' => ['nullable', 'string', 'max:5000'],
            'terms_conditions' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'integer', Rule::in(['0', '1'])],
            'payment_method' => ['nullable', 'integer', Rule::in(['0', '1'])],
        ]);

        $companyId = (int) $company->id;

        if (filled($validated['client_id'] ?? null)) {
            $client = Client::query()->where('company_id', $companyId)->findOrFail((int) $validated['client_id']);
        } else {
            $client = Client::create([
                'company_id' => $companyId,
                'name' => trim((string) $validated['client_name']),
                'email' => filled($validated['client_email'] ?? null) ? Str::lower(trim((string) $validated['client_email'])) : null,
                'mobile' => filled($validated['client_mobile'] ?? null) ? trim((string) $validated['client_mobile']) : null,
                'gst_number' => filled($validated['client_gst_number'] ?? null) ? trim((string) $validated['client_gst_number']) : null,
                'address' => filled($validated['client_address'] ?? null) ? trim((string) $validated['client_address']) : null,
                'state_id' => $validated['client_state_id'] ?? null,
                'city_id' => $validated['client_city_id'] ?? null,
            ]);
        }

        $documentType = (string) $validated['document_type'];
        $paymentStatus = (int) ($validated['payment_status'] ?? 0);
        $paymentMethod = array_key_exists('payment_method', $validated) ? (int) $validated['payment_method'] : null;
        $additionalNotes = filled($validated['additional_notes'] ?? null)
            ? trim((string) $validated['additional_notes'])
            : null;
        $hasAdditionalNotes = $additionalNotes !== null;

        if ($documentType === 'invoice' && $paymentStatus === 1 && $paymentMethod === null) {
            return back()->withErrors(['payment_method' => 'Payment method is required when invoice is paid.'])->withInput();
        }

        $itemBuilder = app(BillingDocumentItemBuilder::class);
        $items = $itemBuilder->buildItems($validated, $companyId, $company->usesManualProductEntry());
        $summary = $itemBuilder->buildSummary($items, round((float) ($validated['discount'] ?? 0), 2));

        $savedDocument = DB::transaction(function () use ($document, $client, $user, $documentType, $items, $summary, $paymentStatus, $paymentMethod, $company, $validated, $hasAdditionalNotes, $additionalNotes): TicketDocument {
            $lockedCompany = Company::query()
                ->whereKey($company->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedCompany) {
                throw ValidationException::withMessages([
                    'document_type' => 'Please configure a valid ' . ucfirst($documentType) . ' Number in Company Settings.',
                ]);
            }

            $payload = [
                'ticket_id' => null,
                'client_id' => $client->id,
                'company_id' => $client->company_id,
                'created_by' => $document?->created_by ?: $user->id,
                'document_type' => $documentType,
                'invoice_or_quote' => $documentType === 'invoice' ? 1 : 2,
                'subject' => trim((string) ($validated['subject'] ?? '')) ?: null,
                'items' => $items,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'gst_amount' => $summary['gst_amount'],
                'total' => $summary['total'],
                'payment_status' => $documentType === 'invoice' ? $paymentStatus : null,
                'payment_method' => $documentType === 'invoice' && $paymentStatus === 1 ? $paymentMethod : null,
                'has_additional_notes' => $documentType === 'invoice' ? $hasAdditionalNotes : false,
                'additional_notes' => $documentType === 'invoice' ? $additionalNotes : null,
                'terms_conditions' => trim((string) ($validated['terms_conditions'] ?? '')) ?: null,
            ];

            if ($document) {
                $document->forceFill($payload)->save();
            } else {
                $allocatedDocument = $lockedCompany->allocateDocumentNumber($documentType);
                $documentNumber = $allocatedDocument['number'] ?? null;

                if (! $documentNumber) {
                    throw ValidationException::withMessages([
                        'document_type' => 'Please configure a valid ' . ucfirst($documentType) . ' Number in Company Settings.',
                    ]);
                }

                $document = TicketDocument::query()->create($payload + [
                    'document_number' => $documentNumber,
                ]);

                $lockedCompany->syncDocumentNumberSequence($documentType, ((int) $allocatedDocument['sequence']) + 1);
            }

            if ($documentType === 'invoice') {
                app(TicketDocumentLinkService::class)->linkStandaloneInvoice(
                    $document->fresh(),
                    $client,
                    $user,
                    $paymentStatus,
                    $paymentStatus === 1 ? $paymentMethod : null,
                    $summary
                );
            } else {
                app(TicketDocumentLinkService::class)->linkStandaloneQuotation($document->fresh(), $client, $user);
            }

            return $document->fresh();
        });

        $label = $documentType === 'invoice' ? 'Invoice' : 'Quotation';

        return redirect()
            ->route('service-call.billing-document.show', $savedDocument)
            ->with('success', $label . ' saved successfully.');
    }

    private function paginateCollection(Request $request, Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = max(1, LengthAwarePaginator::resolveCurrentPage());
        $itemsOnPage = $items->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $itemsOnPage,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );
    }

    private function paginateQuery(Request $request, Builder $query, int $perPage): LengthAwarePaginator
    {
        return $query->paginate($perPage)->withQueryString();
    }

    private function redirectIfInvalidPage(Request $request, LengthAwarePaginator $paginator, string $routeName): ?\Illuminate\Http\RedirectResponse
    {
        $requestedPage = max(1, (int) $request->input('page', 1));

        if ($paginator->lastPage() > 0 && $requestedPage > $paginator->lastPage()) {
            $query = $request->query();
            $query['page'] = $paginator->lastPage();

            return redirect()->to($routeName . '?' . http_build_query($query));
        }

        return null;
    }

    private function downloadRows(Request $request, Collection $rows, array $headings, string $baseName, LetterheadPdfService $letterheadPdfService, ?Company $company = null)
    {
        $format = strtolower((string) $request->string('format', 'xlsx'));
        $fileBase = $baseName . '-' . now()->format('Y-m-d-His');

        if ($format === 'csv') {
            return Excel::download(new ServiceCallRecordsExport($rows, $headings), $fileBase . '.csv', \Maatwebsite\Excel\Excel::CSV);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('service-call.exports.table', [
                'title' => ucwords(str_replace('-', ' ', $baseName)),
                'headings' => $headings,
                'rows' => $rows,
            ]);

            return $letterheadPdfService->download($pdf, $fileBase . '.pdf', $company);
        }

        if ($format === 'print') {
            return view('service-call.exports.table', [
                'title' => ucwords(str_replace('-', ' ', $baseName)),
                'headings' => $headings,
                'rows' => $rows,
            ]);
        }

        return Excel::download(new ServiceCallRecordsExport($rows, $headings), $fileBase . '.xlsx');
    }

    private function resolvePdfLayout(?Company $company, bool $useLetterhead, LetterheadPdfService $letterheadPdfService): array
    {
        $margins = $useLetterhead
            ? $letterheadPdfService->getMargins($letterheadPdfService->activeLetterhead($company))
            : [
                'top_margin' => 15,
                'bottom_margin' => 15,
                'left_margin' => 15,
                'right_margin' => 15,
            ];

        $top = (float) ($margins['top_margin'] ?? 15);
        $right = (float) ($margins['right_margin'] ?? 15);
        $bottom = (float) ($margins['bottom_margin'] ?? 15);
        $left = (float) ($margins['left_margin'] ?? 15);

        return [
            'pdfMargins' => [
                'top_margin' => $top,
                'right_margin' => $right,
                'bottom_margin' => $bottom,
                'left_margin' => $left,
            ],
            'pdfPageMargin' => sprintf('%gmm %gmm %gmm %gmm', $top, $right, $bottom, $left),
            'pdfPrintableWidthMm' => max(0.0, 210.0 - $left - $right),
        ];
    }
}
