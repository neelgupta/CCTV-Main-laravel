<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use App\Modules\Communication\Contracts\WebhookLogRepositoryInterface;
use App\Modules\Communication\Models\CommunicationWebhookLog;
use Illuminate\Support\Collection;

class EloquentWebhookLogRepository implements WebhookLogRepositoryInterface
{
    public function create(array $data): CommunicationWebhookLog
    {
        return CommunicationWebhookLog::create($data);
    }

    public function findByCompanyAndId(int $companyId, int $id): ?CommunicationWebhookLog
    {
        return CommunicationWebhookLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();
    }

    public function findByCompanyAndUuid(int $companyId, string $uuid): ?CommunicationWebhookLog
    {
        return CommunicationWebhookLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('uuid', $uuid)
            ->first();
    }

    public function getLogsForAccount(int $companyId, int $accountId, int $limit = 50): Collection
    {
        return CommunicationWebhookLog::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('integration_account_id', $accountId)
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    public function updateStatus(CommunicationWebhookLog $log, int $processedStatus, ?string $error = null): bool
    {
        return $log->update([
            'processed' => $processedStatus,
            'processing_error' => $error,
            'processed_at' => now(),
        ]);
    }

    /**
     * Check if a webhook payload with the same event ID / message ID has already been received.
     * This is useful to enforce idempotency when Meta sends duplicate events.
     */
    public function isDuplicatePayload(string $fingerprint): bool
    {
        if (empty($fingerprint)) {
            return false;
        }

        // We search the logs where the fingerprint matches.
        // During logging, we can put the fingerprint inside the metadata or directly query the payload.
        // To be fast, we query if there is a record with the same fingerprint in logs payload.
        // We will store the fingerprint in a standardized path within payload/metadata, or compute it.
        return CommunicationWebhookLog::withoutGlobalScopes()
            ->where('payload->fingerprint', $fingerprint)
            ->exists();
    }
}
