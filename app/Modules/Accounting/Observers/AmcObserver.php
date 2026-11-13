<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Observers;

use App\Models\Amc;
use App\Modules\Accounting\Services\AccountingTransactionService;

class AmcObserver
{
    /** Changes that affect the income entry created for a paid AMC contract. */
    private const SYNCED_FIELDS = [
        'payment_status',
        'payment_mode',
        'total_amount',
        'amc_amount',
        'contract_no',
        'client_id',
        'assigned_staff_id',
    ];

    public function __construct(
        private readonly AccountingTransactionService $accountingTransactionService,
    ) {
    }

    public function created(Amc $amc): void
    {
        if (strtolower((string) $amc->payment_status) === 'paid') {
            $this->accountingTransactionService->syncIncomeFromAmc($amc);
        }
    }

    public function updated(Amc $amc): void
    {
        if ($amc->wasChanged(self::SYNCED_FIELDS)) {
            $this->accountingTransactionService->syncIncomeFromAmc($amc);
        }
    }

    public function deleting(Amc $amc): void
    {
        $this->accountingTransactionService->deleteIncomeForAmc($amc);
    }
}
