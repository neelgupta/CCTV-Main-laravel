<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Ticket;
use App\Models\TicketDocument;
use App\Models\TicketInvoice;
use App\Models\TicketQuote;
use App\Models\TicketReply;
use App\Models\User;

class TicketDocumentLinkService
{
    public function linkInvoice(
        Ticket $ticket,
        TicketDocument $invoice,
        User $actor,
        int $paymentStatus,
        ?int $paymentMethod,
        ?array $summary = null,
        array $changeMessages = []
    ): TicketInvoice {
        $ticket->loadMissing(['assignedUser.staff']);
        $staffId = $ticket->assignedUser?->staff?->id ?? $actor->staff?->id;
        $companyId = $ticket->company_id;
        $totalAmount = $summary['total'] ?? $invoice->total;

        $mapping = TicketInvoice::query()
            ->where('ticket_id', $ticket->id)
            ->latest('id')
            ->first();

        $isNewMapping = ! $mapping;
        $autoReceived = $actor->isCompanyAdmin();
        $mapping ??= new TicketInvoice([
            'ticket_id' => $ticket->id,
            'created_by' => $actor->id,
        ]);

        $mapping->forceFill([
            'invoice_id' => $invoice->id,
            'company_id' => $companyId,
            'staff_id' => $staffId,
            'invoice_number' => $invoice->document_number,
            'invoice_subtotal' => $summary['subtotal'] ?? $invoice->subtotal,
            'invoice_discount' => $summary['discount'] ?? $invoice->discount,
            'invoice_gst_amount' => $summary['gst_amount'] ?? $invoice->gst_amount,
            'invoice_total' => $summary['total'] ?? $invoice->total,
            'total_amount' => $totalAmount,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentStatus === 1 ? $paymentMethod : null,
            'received_by_admin' => $autoReceived,
            'received_by_admin_at' => $autoReceived ? now() : $mapping->received_by_admin_at,
            'received_by_admin_by' => $autoReceived ? $actor->id : $mapping->received_by_admin_by,
            'created_by' => $mapping->created_by ?: $actor->id,
        ])->save();

        if (! $isNewMapping) {
            $this->writeTimelineEntries(
                $ticket,
                $actor,
                array_filter([
                    $this->formatDocumentUpdateMessage('Invoice', $invoice->document_number, $changeMessages),
                    $paymentStatus === 1 ? 'Invoice Marked Paid' : 'Invoice Marked Unpaid',
                    $paymentStatus === 1
                        ? 'Payment Received (' . ($paymentMethod === 0 ? 'Cash' : 'Online') . ')'
                        : null,
                ])
            );
        }

        if ($isNewMapping) {
            $this->writeTimelineEntries(
                $ticket,
                $actor,
                array_filter([
                    'Invoice Created' . ($invoice->document_number ? ' (' . $invoice->document_number . ')' : ''),
                    $paymentStatus === 1 ? 'Invoice Marked Paid' : 'Invoice Marked Unpaid',
                    $paymentStatus === 1
                        ? 'Payment Received (' . ($paymentMethod === 0 ? 'Cash' : 'Online') . ')'
                        : null,
                ])
            );
        }

        return $mapping;
    }

    public function linkStandaloneInvoice(
        TicketDocument $invoice,
        Client $client,
        User $actor,
        int $paymentStatus,
        ?int $paymentMethod,
        ?array $summary = null
    ): TicketInvoice {
        $totalAmount = $summary['total'] ?? $invoice->total;

        $mapping = TicketInvoice::query()
            ->where('invoice_id', $invoice->id)
            ->latest('id')
            ->first();

        $mapping ??= new TicketInvoice([
            'created_by' => $actor->id,
        ]);
        $autoReceived = $actor->isCompanyAdmin();

        $mapping->forceFill([
            'ticket_id' => null,
            'client_id' => $client->id,
            'invoice_id' => $invoice->id,
            'company_id' => $client->company_id,
            'staff_id' => $actor->staff?->id,
            'invoice_number' => $invoice->document_number,
            'invoice_subtotal' => $summary['subtotal'] ?? $invoice->subtotal,
            'invoice_discount' => $summary['discount'] ?? $invoice->discount,
            'invoice_gst_amount' => $summary['gst_amount'] ?? $invoice->gst_amount,
            'invoice_total' => $summary['total'] ?? $invoice->total,
            'total_amount' => $totalAmount,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentStatus === 1 ? $paymentMethod : null,
            'received_by_admin' => $autoReceived,
            'received_by_admin_at' => $autoReceived ? now() : $mapping->received_by_admin_at,
            'received_by_admin_by' => $autoReceived ? $actor->id : $mapping->received_by_admin_by,
            'created_by' => $mapping->created_by ?: $actor->id,
        ])->save();

        return $mapping;
    }

    public function linkStandaloneQuotation(TicketDocument $quotation, ?Client $client, User $actor): TicketQuote
    {
        $mapping = TicketQuote::query()
            ->where('quote_id', $quotation->id)
            ->latest('id')
            ->first();

        $mapping ??= new TicketQuote([
            'created_by' => $actor->id,
        ]);

        $mapping->forceFill([
            'ticket_id' => null,
            'client_id' => $client?->id,
            'quote_id' => $quotation->id,
            'created_by' => $mapping->created_by ?: $actor->id,
        ])->save();

        return $mapping;
    }

    public function linkQuotation(Ticket $ticket, TicketDocument $quotation, User $actor, array $changeMessages = []): TicketQuote
    {
        $mapping = TicketQuote::query()
            ->where('ticket_id', $ticket->id)
            ->latest('id')
            ->first();

        $isNewMapping = ! $mapping;
        $mapping ??= new TicketQuote([
            'ticket_id' => $ticket->id,
            'created_by' => $actor->id,
        ]);

        $mapping->forceFill([
            'quote_id' => $quotation->id,
            'created_by' => $mapping->created_by ?: $actor->id,
        ])->save();

        if ($isNewMapping) {
            $this->writeTimelineEntries(
                $ticket,
                $actor,
                [
                    'Quotation Created' . ($quotation->document_number ? ' (' . $quotation->document_number . ')' : ''),
                ]
            );
        } else {
            $this->writeTimelineEntries(
                $ticket,
                $actor,
                [
                    $this->formatDocumentUpdateMessage('Quotation', $quotation->document_number, $changeMessages),
                ]
            );
        }

        return $mapping;
    }

    /**
     * @param array<int, string> $changeMessages
     */
    private function formatDocumentUpdateMessage(string $label, ?string $documentNumber, array $changeMessages): string
    {
        $message = $label . ' Updated' . ($documentNumber ? ' (' . $documentNumber . ')' : '');

        if ($changeMessages === []) {
            return $message . "\nChanges: No item or amount changes detected.";
        }

        return $message . "\nChanges:\n- " . implode("\n- ", $changeMessages);
    }

    /**
     * @param array<int, string> $messages
     */
    private function writeTimelineEntries(Ticket $ticket, User $actor, array $messages): void
    {
        foreach ($messages as $message) {
            TicketReply::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'message' => $message,
            ]);
        }
    }
}
