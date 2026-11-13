<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Services\InquiryLeadSyncService;
use Illuminate\Console\Command;

class SyncInquiriesToCompanyLeads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inquiries:sync-to-leads {--company-code= : The company_code to sync inquiries into (default from config)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync landing page inquiries into company leads (e.g. Sourcecube CCTV Pro One)';

    /**
     * Execute the console command.
     */
    public function handle(InquiryLeadSyncService $syncService): int
    {
        $companyCode = $this->option('company-code');
        $company = $syncService->resolveTargetCompany($companyCode);

        if (!$company) {
            $this->error('Target company not found. Please verify company code CC-HY5FLC exists in the companies table or specify --company-code.');
            return Command::FAILURE;
        }

        $this->info("Starting inquiry sync for target company: {$company->name} (Code: {$company->company_code}, ID: {$company->id})");

        $result = $syncService->syncAllInquiries($company);

        $this->info("Sync completed!");
        $this->table(
            ['Metric', 'Value'],
            [
                ['Target Company', $result['company'] ?? 'N/A'],
                ['Total Inquiries', $result['total']],
                ['New Leads Created', $result['created']],
                ['Skipped / Existing', $result['skipped']],
            ]
        );

        return Command::SUCCESS;
    }
}

