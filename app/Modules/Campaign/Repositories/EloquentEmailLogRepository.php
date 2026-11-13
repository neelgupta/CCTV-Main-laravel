<?php

namespace App\Modules\Campaign\Repositories;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Modules\Campaign\Contracts\EmailLogRepositoryInterface;
use Illuminate\Support\Str;

class EloquentEmailLogRepository implements EmailLogRepositoryInterface
{
    public function createForRecipient(Campaign $campaign, CampaignRecipient $recipient, array $attributes = []): EmailLog
    {
        return EmailLog::create(array_merge([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'company_id' => $campaign->company_id,
            'email' => (string) $recipient->email,
            'subject' => $campaign->subject,
            'tracking_token' => (string) Str::uuid(),
            'status' => 'queued',
        ], $attributes));
    }

    public function findByTrackingToken(string $token): ?EmailLog
    {
        return EmailLog::query()
            ->with(['campaign', 'recipient'])
            ->where('tracking_token', $token)
            ->first();
    }

    public function update(EmailLog $emailLog, array $attributes): bool
    {
        return $emailLog->update($attributes);
    }
}
