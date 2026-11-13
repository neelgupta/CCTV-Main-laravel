<?php

namespace App\Notifications;

use App\Models\FestivalCampaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FestivalCampaignGeneratedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly FestivalCampaign $festivalCampaign)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Festival Campaign Generated: ' . $this->festivalCampaign->festival_name)
            ->line('Festival campaign assets are ready for ' . $this->festivalCampaign->festival_name . '.')
            ->line('Date: ' . $this->festivalCampaign->festival_date?->format('d M Y'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'festival_campaign_id' => $this->festivalCampaign->id,
            'festival_name' => $this->festivalCampaign->festival_name,
            'festival_date' => $this->festivalCampaign->festival_date?->toDateString(),
        ];
    }
}
