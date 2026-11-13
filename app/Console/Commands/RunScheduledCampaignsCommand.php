<?php

namespace App\Console\Commands;

use App\Jobs\StartCampaignJob;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use Illuminate\Console\Command;

class RunScheduledCampaignsCommand extends Command
{
    protected $signature = 'campaigns:run-scheduled';

    protected $description = 'Dispatch scheduled campaigns that are due to run.';

    public function handle(CampaignRepositoryInterface $campaigns): int
    {
        $dueCampaigns = $campaigns->dueScheduled();

        foreach ($dueCampaigns as $campaign) {
            StartCampaignJob::dispatch($campaign->id);
        }

        $this->info('Queued ' . $dueCampaigns->count() . ' scheduled campaign(s).');

        return self::SUCCESS;
    }
}
