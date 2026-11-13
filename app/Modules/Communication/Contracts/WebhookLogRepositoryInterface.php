<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\Models\CommunicationWebhookLog;
use Illuminate\Support\Collection;

interface WebhookLogRepositoryInterface
{
    public function create(array $data): CommunicationWebhookLog;

    public function findByCompanyAndId(int $companyId, int $id): ?CommunicationWebhookLog;

    public function findByCompanyAndUuid(int $companyId, string $uuid): ?CommunicationWebhookLog;

    public function getLogsForAccount(int $companyId, int $accountId, int $limit = 50): Collection;

    public function updateStatus(CommunicationWebhookLog $log, int $processedStatus, ?string $error = null): bool;

    public function isDuplicatePayload(string $fingerprint): bool;
}
