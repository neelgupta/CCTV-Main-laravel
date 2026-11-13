<?php

namespace App\Listeners;

use App\Events\EmailBounceDetected;
use App\Models\User;
use App\Notifications\EmailBounceDetectedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class SendEmailBounceNotifications implements ShouldQueue
{
    public function handle(EmailBounceDetected $event): void
    {
        $users = User::query()
            ->where('company_id', $event->campaign->company_id)
            ->get();

        Notification::send($users, new EmailBounceDetectedNotification($event->campaign, $event->recipient, $event->emailLog));
    }
}
