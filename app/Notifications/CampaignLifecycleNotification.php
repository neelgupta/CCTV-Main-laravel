<?php

namespace App\Notifications;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CampaignLifecycleNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Campaign $campaign,
        private readonly string $action,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Campaign ' . ucfirst($this->action) . ': ' . $this->campaign->name)
            ->line('Campaign "' . $this->campaign->name . '" has been ' . $this->action . '.')
            ->line('Status: ' . ucfirst($this->campaign->status))
            ->line('Scheduled At: ' . optional($this->campaign->scheduled_at)->format('d M Y h:i A'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'campaign_name' => $this->campaign->name,
            'action' => $this->action,
            'status' => $this->campaign->status,
            'scheduled_at' => optional($this->campaign->scheduled_at)->toDateTimeString(),
        ];
    }
}
