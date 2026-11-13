<?php

namespace App\Events;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailBounceDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Campaign $campaign,
        public readonly CampaignRecipient $recipient,
        public readonly EmailLog $emailLog,
    ) {
    }
}
