<?php

namespace App\Jobs;

use App\Models\FestivalCampaign;
use App\Models\Campaign;
use App\Models\User;
use App\Modules\Campaign\Services\CampaignBannerService;
use App\Notifications\FestivalCampaignGeneratedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;

class GenerateFestivalCampaignAssetsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $festivalCampaignId)
    {
    }

    public function handle(CampaignBannerService $bannerService): void
    {
        $festivalCampaign = FestivalCampaign::withoutGlobalScopes()->find($this->festivalCampaignId);

        if (! $festivalCampaign) {
            return;
        }

        $user = User::query()->find($festivalCampaign->selected_by_user_id);

        if (! $user) {
            return;
        }

        $banner = $bannerService->generate($festivalCampaign, $user);

        $linkedCampaign = $festivalCampaign->campaign
            ?: Campaign::withoutGlobalScopes()->where('festival_campaign_id', $festivalCampaign->id)->first();

        if ($linkedCampaign) {
            $linkedCampaign->update([
                'generated_banner_id' => $banner->id,
                'banner_image' => $banner->file_path,
            ]);
        }

        $users = User::query()->where('company_id', $festivalCampaign->company_id)->get();
        Notification::send($users, new FestivalCampaignGeneratedNotification($festivalCampaign->fresh()));
    }
}
