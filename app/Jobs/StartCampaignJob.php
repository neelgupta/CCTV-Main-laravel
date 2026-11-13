<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Modules\Campaign\Services\CampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class StartCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly int $campaignId)
    {
    }

    public function handle(CampaignDispatchService $dispatchService): void
    {
        $campaign = Campaign::withoutGlobalScopes()->find($this->campaignId);

        if (! $campaign || ! in_array($campaign->status, [Campaign::STATUS_SCHEDULED, Campaign::STATUS_RUNNING], true)) {
            return;
        }

        if ($campaign->status === Campaign::STATUS_SCHEDULED) {
            $dispatchService->start($campaign);
        } else {
            $dispatchService->dispatchBatches($campaign);
        }
    }
}
