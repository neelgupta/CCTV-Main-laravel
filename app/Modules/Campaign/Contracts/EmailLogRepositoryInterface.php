<?php

namespace App\Modules\Campaign\Contracts;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;

interface EmailLogRepositoryInterface
{
    public function createForRecipient(Campaign $campaign, CampaignRecipient $recipient, array $attributes = []): EmailLog;

    public function findByTrackingToken(string $token): ?EmailLog;

    public function update(EmailLog $emailLog, array $attributes): bool;
}
