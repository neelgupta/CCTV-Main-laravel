<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Modules\Campaign\Services\CampaignDispatchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FinalizeCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public function __construct(private readonly int $campaignId)
    {
    }

    public function handle(CampaignDispatchService $dispatchService): void
    {
        $campaign = Campaign::withoutGlobalScopes()->find($this->campaignId);

        if (! $campaign || $campaign->status !== Campaign::STATUS_RUNNING) {
            return;
        }

        $pending = CampaignRecipient::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->where('overall_status', 'pending')
            ->count();

        if ($pending > 0) {
            self::dispatch($campaign->id)->delay(now()->addMinute());
            return;
        }

        $dispatchService->markCompleted($campaign);
    }
}
