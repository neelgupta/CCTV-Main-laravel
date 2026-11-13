<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Observers;

use App\Models\TicketInvoice;
use App\Modules\Accounting\Services\AccountingTransactionService;

class TicketInvoiceObserver
{
    public function __construct(
        private readonly AccountingTransactionService $accountingTransactionService,
    ) {
    }

    public function created(TicketInvoice $ticketInvoice): void
    {
        $this->syncIfPaid($ticketInvoice);
    }

    public function updated(TicketInvoice $ticketInvoice): void
    {
        if (! $ticketInvoice->wasChanged('payment_status') && ! $ticketInvoice->wasChanged('received_by_admin') && ! $ticketInvoice->wasChanged('received_by_admin_at')) {
            return;
        }

        $this->syncIfPaid($ticketInvoice);
    }

    private function syncIfPaid(TicketInvoice $ticketInvoice): void
    {
        if ((int) $ticketInvoice->payment_status !== 1) {
            return;
        }

        $this->accountingTransactionService->syncIncomeFromInvoice($ticketInvoice);
    }
}
