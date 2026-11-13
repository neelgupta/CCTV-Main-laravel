<?php

namespace App\Modules\Campaign\Repositories;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Modules\Campaign\Contracts\CampaignRecipientRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentCampaignRecipientRepository implements CampaignRecipientRepositoryInterface
{
    public function bulkCreate(Campaign $campaign, array $rows): void
    {
        CampaignRecipient::insert($rows);
    }

    public function chunkPending(Campaign $campaign, int $size): Collection
    {
        return CampaignRecipient::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->where('overall_status', 'pending')
            ->orderBy('id')
            ->limit($size)
            ->get();
    }

    public function pendingCount(Campaign $campaign): int
    {
        return CampaignRecipient::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->where('overall_status', 'pending')
            ->count();
    }

    public function markProcessed(CampaignRecipient $recipient, array $attributes = []): bool
    {
        return $recipient->update(array_merge([
            'processed_at' => now(),
        ], $attributes));
    }
}
