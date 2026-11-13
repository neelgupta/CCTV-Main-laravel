<?php

namespace App\Modules\Campaign\Contracts;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use Illuminate\Support\Collection;

interface CampaignRecipientRepositoryInterface
{
    public function bulkCreate(Campaign $campaign, array $rows): void;

    public function chunkPending(Campaign $campaign, int $size): Collection;

    public function pendingCount(Campaign $campaign): int;

    public function markProcessed(CampaignRecipient $recipient, array $attributes = []): bool;
}
