<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$client = App\Models\Client::query()->where('name', 'Katell Mills')->firstOrFail();

$ticketInvoiceAmountColumn = Illuminate\Support\Facades\Schema::hasColumn('ticket_invoices', 'total_amount')
    ? 'total_amount'
    : 'invoice_total';

$ticketInvoicePending = (float) Illuminate\Support\Facades\DB::table('ticket_invoices as ti')
    ->leftJoin('tickets as t', 't.id', '=', 'ti.ticket_id')
    ->leftJoin('ticket_documents as td', 'td.id', '=', 'ti.invoice_id')
    ->where('ti.company_id', (int) $client->company_id)
    ->where('ti.payment_status', 0)
    ->where(function ($query) use ($client): void {
        $query->where('ti.client_id', $client->id)
            ->orWhere('td.client_id', $client->id)
            ->orWhere('t.customer_name', $client->name)
            ->orWhere('t.customer_contact_number', $client->mobile)
            ->orWhere('t.customer_email', $client->email);
    })
    ->sum($ticketInvoiceAmountColumn);

$standaloneDocumentAmountColumn = Illuminate\Support\Facades\Schema::hasColumn('ticket_documents', 'total')
    ? 'total'
    : 'subtotal';

$standaloneInvoicePending = (float) Illuminate\Support\Facades\DB::table('ticket_documents')
    ->where('company_id', (int) $client->company_id)
    ->where('document_type', 'invoice')
    ->whereNull('ticket_id')
    ->where('client_id', $client->id)
    ->where(function ($query): void {
        $query->whereNull('payment_status')
            ->orWhere('payment_status', 0);
    })
    ->sum($standaloneDocumentAmountColumn);

$amcPending = (float) App\Models\Amc::query()
    ->where('company_id', (int) $client->company_id)
    ->where(function ($query) use ($client): void {
        $query->where('client_id', $client->id)
            ->orWhere('customer_name', $client->name);
    })
    ->get()
    ->sum(fn (App\Models\Amc $amc): float => (float) $amc->remainingVisitInvoiceAmount());

$total = round($ticketInvoicePending + $standaloneInvoicePending + $amcPending, 2);

echo 'client_id=' . $client->id . PHP_EOL;
echo 'ticket_invoice_pending=' . number_format($ticketInvoicePending, 2, '.', '') . PHP_EOL;
echo 'standalone_invoice_pending=' . number_format($standaloneInvoicePending, 2, '.', '') . PHP_EOL;
echo 'amc_pending=' . number_format($amcPending, 2, '.', '') . PHP_EOL;
echo 'total_pending=' . number_format($total, 2, '.', '') . PHP_EOL;
