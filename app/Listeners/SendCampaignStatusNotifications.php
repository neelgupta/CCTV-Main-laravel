<?php

namespace App\Listeners;

use App\Events\CampaignStatusChanged;
use App\Models\User;
use App\Notifications\CampaignLifecycleNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendCampaignStatusNotifications implements ShouldQueue
{
    public function handle(CampaignStatusChanged $event): void
    {
        $users = User::query()
            ->where('company_id', $event->campaign->company_id)
            ->where(function ($query) {
                $query->whereNotNull('email')->orWhere('id', '>', 0);
            })
            ->get();

        Notification::send($users, new CampaignLifecycleNotification($event->campaign, $event->action));
    }
}
