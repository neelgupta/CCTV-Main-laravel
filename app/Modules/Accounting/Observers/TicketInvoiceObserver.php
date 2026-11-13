<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Observers;

use App\Models\TicketInvoice;
use App\Modules\Accounting\Services\AccountingTransactionService;

class TicketInvoiceObserver
{
    /** Changes that affect the income entry created for a paid invoice. */
    private const SYNCED_FIELDS = [
        'payment_status',
        'payment_method',
        'total_amount',
        'invoice_total',
        'invoice_number',
        'client_id',
        'staff_id',
        'received_by_admin',
        'received_by_admin_at',
    ];

    public function __construct(
        private readonly AccountingTransactionService $accountingTransactionService,
    ) {
    }

    public function created(TicketInvoice $ticketInvoice): void
    {
        if ((int) $ticketInvoice->payment_status === 1) {
            $this->accountingTransactionService->syncIncomeFromInvoice($ticketInvoice);
        }
    }

    public function updated(TicketInvoice $ticketInvoice): void
    {
        if (! $ticketInvoice->wasChanged(self::SYNCED_FIELDS)) {
            return;
        }

        // Creates/updates the income when paid, removes it when no longer paid.
        $this->accountingTransactionService->syncIncomeFromInvoice($ticketInvoice);
    }

    public function deleting(TicketInvoice $ticketInvoice): void
    {
        $this->accountingTransactionService->deleteIncomeForInvoice($ticketInvoice);
    }
}
