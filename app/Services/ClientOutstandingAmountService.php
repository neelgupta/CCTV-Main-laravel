<?php

namespace App\Services;

use App\Models\Client;
use App\Models\TicketDocument;
use App\Models\TicketInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class ClientOutstandingAmountService
{
    public function getTotal(Client $client): float
    {
        return (float) $this->ledgerSummary($client)['pending_amount'];
    }

    public function getInvoicePendingTotal(Client $client): float
    {
        return round($this->ledgerSummary($client)['pending_amount'], 2);
    }

    public function ledgerSummary(Client $client): array
    {
        $invoiceRows = $this->ledgerInvoiceRows($client);
        $incomeRows = $this->ledgerIncomeRows($client);

        $invoiceTotal = (float) $invoiceRows->sum('amount');
        $incomeReceivedAmount = (float) $incomeRows->sum('amount');
        $totalAmount = round($invoiceTotal, 2);
        $invoiceReceivedAmount = round((float) $invoiceRows->where('status', 'Paid')->sum('amount'), 2);
        $receivedAmount = round($invoiceReceivedAmount + $incomeReceivedAmount, 2);
        $pendingAmount = round(max(0, $totalAmount - $receivedAmount), 2);

        return [
            'total_amount' => $totalAmount,
            'invoice_total' => round($invoiceTotal, 2),
            'paid_amount' => $receivedAmount,
            'invoice_received_amount' => $invoiceReceivedAmount,
            'income_received_amount' => round($incomeReceivedAmount, 2),
            'received_amount' => $receivedAmount,
            'pending_amount' => $pendingAmount,
        ];
    }

    public function ledgerEntries(Client $client): Collection
    {
        return collect()
            ->concat($this->ledgerInvoiceRows($client)->map(function (array $row): array {
                $isPaid = ($row['status'] ?? '') === 'Paid';

                return [
                    'date' => $row['date'],
                    'reference' => $row['reference'],
                    'description' => $row['description'],
                    'type' => $row['type'],
                    'debit' => $isPaid ? 0.0 : $row['amount'],
                    'credit' => $isPaid ? $row['amount'] : 0.0,
                    'balance_impact' => $isPaid ? -$row['amount'] : $row['amount'],
                    'status' => $row['status'],
                ];
            }))
            ->concat($this->ledgerIncomeRows($client)->map(function (array $row): array {
                return [
                    'date' => $row['date'],
                    'reference' => $row['reference'],
                    'description' => $row['description'],
                    'type' => 'income',
                    'debit' => 0.0,
                    'credit' => $row['amount'],
                    'balance_impact' => -$row['amount'],
                    'status' => $row['status'],
                ];
            }))
            ->sortByDesc(fn (array $entry) => optional($entry['date'])->timestamp ?? 0)
            ->values();
    }

    public function getTotalsForCompany(int $companyId): array
    {
        return $this->getSalesInvoicePendingTotalsForCompany($companyId);
    }

    public function getInvoicePendingTotalsForCompany(int $companyId): array
    {
        return $this->getSalesInvoicePendingTotalsForCompany($companyId);
    }

    public function getSalesInvoicePendingTotalsForCompany(int $companyId): array
    {
        return Client::query()
            ->where('company_id', $companyId)
            ->orderBy('id')
            ->get(['id', 'name', 'company_id'])
            ->mapWithKeys(function (Client $client): array {
                try {
                    return [(string) $client->id => $this->getTotal($client)];
                } catch (\Throwable $e) {
                    report($e);

                    return [(string) $client->id => 0.0];
                }
            })
            ->all();
    }

    private function ledgerInvoiceRows(Client $client): Collection
    {
        $driver = DB::connection()->getDriverName();
        $invoiceReferenceExpr = $driver === 'sqlite'
            ? "COALESCE(ti.invoice_number, td.document_number, 'INV-' || ti.id)"
            : "COALESCE(ti.invoice_number, td.document_number, CONCAT('INV-', ti.id))";
        $invoiceDescriptionExpr = $driver === 'sqlite'
            ? "COALESCE(t.subject, 'Ticket ' || t.ticket_number, 'Support invoice')"
            : "COALESCE(t.subject, CONCAT('Ticket ', t.ticket_number), 'Support invoice')";
        $standaloneReferenceExpr = $driver === 'sqlite'
            ? "COALESCE(td.document_number, 'DOC-' || td.id)"
            : "COALESCE(td.document_number, CONCAT('DOC-', td.id))";

        $supportInvoices = DB::table('ticket_invoices as ti')
            ->leftJoin('tickets as t', 't.id', '=', 'ti.ticket_id')
            ->leftJoin('ticket_documents as td', 'td.id', '=', 'ti.invoice_id')
            ->where('ti.company_id', (int) $client->company_id)
            ->where(function ($query) use ($client): void {
                $query->where('ti.client_id', $client->id)
                    ->orWhere('td.client_id', $client->id)
                    ->orWhere(function ($ticketQuery) use ($client): void {
                        $ticketQuery->whereNotNull('ti.ticket_id')
                            ->where(function ($match) use ($client): void {
                                $clientName = mb_strtolower(trim((string) $client->name));
                                $clientEmail = filled($client->email) ? mb_strtolower(trim((string) $client->email)) : null;
                                $clientPhone = $this->normalizePhone($client->mobile ?: $client->phone);

                                $match->whereRaw('LOWER(TRIM(t.customer_name)) = ?', [$clientName]);

                                if (filled($clientEmail)) {
                                    $match->orWhereRaw('LOWER(TRIM(t.customer_email)) = ?', [$clientEmail]);
                                }

                                if (filled($clientPhone)) {
                                    $match->orWhere('t.customer_contact_number', 'like', '%' . $clientPhone . '%');
                                }
                            });
                    });
            })
            ->selectRaw("ti.id, td.id as invoice_document_id, COALESCE(ti.total_amount, ti.invoice_total, 0) as amount")
            ->selectRaw("COALESCE(ti.created_at, ti.updated_at) as date")
            ->selectRaw("{$invoiceReferenceExpr} as reference")
            ->selectRaw("{$invoiceDescriptionExpr} as description")
            ->selectRaw("CASE WHEN ti.payment_status = 1 THEN 'Paid' ELSE 'Unpaid' END as status")
            ->get()
            ->map(fn ($row) => [
                'source_key' => 'invoice-document:' . (int) ($row->invoice_document_id ?? $row->id),
                'date' => $row->date ? \Carbon\Carbon::parse($row->date) : null,
                'reference' => (string) $row->reference,
                'description' => (string) $row->description,
                'amount' => (float) $row->amount,
                'status' => (string) $row->status,
                'type' => 'invoice',
                'priority' => 0,
            ]);

        $standaloneInvoices = DB::table('ticket_documents as td')
            ->where('td.company_id', (int) $client->company_id)
            ->where('td.document_type', 'invoice')
            ->whereNull('td.ticket_id')
            ->where('td.client_id', $client->id)
            ->selectRaw("td.id, COALESCE(td.total, td.subtotal, 0) as amount")
            ->selectRaw("td.created_at as date")
            ->selectRaw("{$standaloneReferenceExpr} as reference")
            ->selectRaw("'Project/Standalone invoice' as description")
            ->selectRaw("CASE WHEN COALESCE(td.payment_status, 0) = 1 THEN 'Paid' ELSE 'Unpaid' END as status")
            ->get()
            ->map(fn ($row) => [
                'source_key' => 'ticket-document:' . (int) $row->id,
                'date' => $row->date ? \Carbon\Carbon::parse($row->date) : null,
                'reference' => (string) $row->reference,
                'description' => (string) $row->description,
                'amount' => (float) $row->amount,
                'status' => (string) $row->status,
                'type' => 'invoice',
                'priority' => 1,
            ]);

        return collect($supportInvoices)
            ->concat($standaloneInvoices)
            ->sortBy([
                fn (array $row) => $row['priority'] ?? 99,
                fn (array $row) => strtolower(trim((string) ($row['reference'] ?? ''))),
                fn (array $row) => $row['date']?->timestamp ?? 0,
            ])
            ->unique(function (array $row): string {
                $reference = strtolower(trim((string) ($row['reference'] ?? '')));

                return $reference !== ''
                    ? 'reference:' . $reference
                    : 'source:' . (string) ($row['source_key'] ?? '');
            })
            ->values();
    }

    private function ledgerIncomeRows(Client $client): Collection
    {
        return DB::table('incomes as i')
            ->leftJoin('users as u', 'u.id', '=', 'i.created_by')
            ->where('i.company_id', (int) $client->company_id)
            ->where('i.client_id', $client->id)
            ->selectRaw("i.id, i.amount, i.received_date as date")
            ->selectRaw("COALESCE(i.transaction_no, CONCAT('INC-', i.id)) as reference")
            ->selectRaw("COALESCE(i.title, 'Accounting income') as description")
            ->selectRaw("COALESCE(i.payment_method, 'Cash') as payment_method")
            ->selectRaw("COALESCE(u.name, 'System') as created_by_name")
            ->selectRaw("'Received' as status")
            ->get()
            ->map(fn ($row) => [
                'source_key' => 'income:' . (int) $row->id,
                'date' => $row->date ? \Carbon\Carbon::parse($row->date) : null,
                'reference' => (string) $row->reference,
                'description' => trim((string) $row->description) !== ''
                    ? (string) $row->description
                    : 'Accounting income',
                'amount' => (float) $row->amount,
                'status' => (string) $row->status,
                'type' => 'income',
                'priority' => 2,
            ]);
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! filled($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', trim((string) $value));

        return filled($digits) ? $digits : null;
    }
}
