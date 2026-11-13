<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCampaignBatchJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessBulkEmailCampaigns extends Command
{
    protected $signature = 'campaigns:process-bulk
        {--batch-size= : Override emails per batch}
        {--campaign-id= : Process only a specific campaign}';

    protected $description = 'Process pending bulk email campaigns in batches of 200.';

    public function handle(): int
    {
        $runningCampaigns = Campaign::withoutGlobalScopes()
            ->where('status', Campaign::STATUS_RUNNING)
            ->when($this->option('campaign-id'), fn ($q, $id) => $q->where('id', (int) $id))
            ->get();

        if ($runningCampaigns->isEmpty()) {
            $this->info('No running campaigns to process.');

            return Command::SUCCESS;
        }

        $globalBatchSize = (int) SystemSetting::getValue('email_batch_size', config('campaigns.batch_size', 200));
        $optionBatchSize = $this->option('batch-size');
        $totalDispatched = 0;

        foreach ($runningCampaigns as $campaign) {
            $batchSize = $optionBatchSize ? (int) $optionBatchSize : ($campaign->batch_size ?: $globalBatchSize);

            $pendingRecipients = CampaignRecipient::withoutGlobalScopes()
                ->where('campaign_id', $campaign->id)
                ->where('overall_status', 'pending')
                ->orderBy('id')
                ->limit($batchSize)
                ->pluck('id');

            if ($pendingRecipients->isEmpty()) {
                continue;
            }

            $batchIds = $pendingRecipients->values()->all();

            ProcessCampaignBatchJob::dispatch($campaign->id, $batchIds);

            CampaignRecipient::withoutGlobalScopes()
                ->whereIn('id', $batchIds)
                ->update(['overall_status' => 'processing']);

            $totalDispatched += count($batchIds);

            $this->info(sprintf(
                'Dispatched batch for campaign [%d] %s: %d recipients',
                $campaign->id,
                $campaign->name,
                count($batchIds)
            ));
        }

        if ($totalDispatched > 0) {
            $this->info("Total dispatched: {$totalDispatched} emails across " . $runningCampaigns->count() . ' campaigns.');
            Log::info('Bulk email campaign processing dispatched', [
                'campaigns' => $runningCampaigns->count(),
                'total_recipients' => $totalDispatched,
            ]);
        } else {
            $this->info('No pending recipients found for running campaigns.');
        }

        return Command::SUCCESS;
    }
}
