<?php

namespace App\Notifications;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailBounceDetectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Campaign $campaign,
        private readonly CampaignRecipient $recipient,
        private readonly EmailLog $emailLog,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Email Bounce Detected: ' . $this->campaign->name)
            ->line('A bounce was detected for campaign "' . $this->campaign->name . '".')
            ->line('Recipient: ' . ($this->recipient->email ?: 'Unknown'))
            ->line('Reason: ' . ($this->emailLog->error_message ?: 'Unknown error'));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'campaign_id' => $this->campaign->id,
            'recipient_id' => $this->recipient->id,
            'recipient_email' => $this->recipient->email,
            'email_log_id' => $this->emailLog->id,
            'reason' => $this->emailLog->error_message,
        ];
    }
}
