<?php

namespace App\Support;

use App\Models\TicketDocument;

/**
 * Freezes the seller (company) and party (customer) details printed on an invoice or
 * quotation, so later edits to the company settings or the client do not rewrite
 * documents already issued. Line items are already stored on the document itself.
 */
class BillingDocumentSnapshot
{
    /**
     * Fields whose change means the document itself was edited and re-issued.
     */
    public const CONTENT_FIELDS = [
        'ticket_id',
        'client_id',
        'subject',
        'description',
        'items',
        'subtotal',
        'discount',
        'gst_amount',
        'total',
        'has_additional_notes',
        'additional_notes',
        'terms_conditions',
    ];

    public static function capture(TicketDocument $document): array
    {
        $ticket = $document->ticket_id ? $document->ticket()->with(['company', 'user'])->first() : null;
        // Ticket documents print the ticket's customer; standalone documents print the client.
        $client = $ticket ? null : ($document->client_id ? $document->client()->with('company')->first() : null);
        $company = $ticket?->company ?? $document->company()->first() ?? $client?->company;
        $amc = $ticket?->generated_from_amc_id ? $ticket->amc()->first() : null;

        return [
            'seller' => [
                'name' => $company?->name,
                'address' => trim((string) ($company?->address ?? '')),
                'phone_number' => trim((string) ($company?->phone_number ?? '')),
                'email' => trim((string) ($company?->email ?? '')),
                'gst_number' => trim((string) ($company?->gst_number ?? '')),
                'msme_number' => trim((string) ($company?->msme_number ?? '')),
                'invoice_terms_text' => $company?->invoiceTermsConditionsText() ?? '',
                'invoice_terms_html' => $company?->invoiceTermsConditionsHtml() ?? '',
                'quotation_terms_text' => $company?->quotationTermsConditionsText() ?? '',
                'quotation_terms_html' => $company?->quotationTermsConditionsHtml() ?? '',
            ],
            'party' => [
                'name' => trim((string) ($ticket?->customer_name ?? $ticket?->user?->name ?? $client?->name ?? '')),
                'address' => trim((string) ($ticket?->customer_address ?? $client?->address ?? '')),
                'contact' => trim((string) ($ticket?->customer_contact_number ?? $client?->mobile ?? $client?->phone ?? '')),
                'email' => trim((string) ($ticket?->customer_email ?? $client?->email ?? '')),
                'gst_number' => trim((string) ($client?->gst_number ?? '')),
            ],
            // AMC visit invoices print the AMC contract's customer details first.
            'amc_party' => $amc ? [
                'name' => trim((string) (filled($amc->customer_name) ? $amc->customer_name : ($ticket->customer_name ?? ''))),
                'address' => trim((string) (filled($amc->address) ? $amc->address : ($ticket->customer_address ?? ''))),
                'contact' => trim((string) (filled($amc->mobile_number) ? $amc->mobile_number : ($ticket->customer_contact_number ?? ''))),
                'email' => trim((string) (filled($amc->email) ? $amc->email : ($ticket->customer_email ?? ''))),
            ] : null,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    public static function shouldRefresh(TicketDocument $document): bool
    {
        return ! $document->exists
            ? empty($document->billing_snapshot)
            : $document->isDirty(self::CONTENT_FIELDS);
    }
}
