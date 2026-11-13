<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Contracts\CommunicationProviderInterface;
use App\Modules\Communication\Contracts\IntegrationAccountRepositoryInterface;
use App\Modules\Communication\Contracts\WebhookLogRepositoryInterface;
use App\Modules\Communication\Exceptions\IntegrationAccountNotFoundException;
use App\Modules\Communication\Enums\ProcessedStatusEnum;
use App\Modules\Communication\Jobs\StoreWebhookEventJob;
use App\Modules\Communication\Models\CommunicationWebhookLog;
use App\Modules\Communication\Models\IntegrationAccount;
use App\Modules\Communication\Support\Logger;

class ProcessWebhookPayloadAction
{
    public function __construct(
        protected CommunicationProviderInterface $provider,
        protected IntegrationAccountRepositoryInterface $accountRepository,
        protected WebhookLogRepositoryInterface $logRepository
    ) {}

    /**
     * Parse webhook payloads, audit them, and dispatch background jobs.
     *
     * @throws IntegrationAccountNotFoundException
     */
    public function execute(string $uuid, array $payload, array $headers, ?string $requestIp = null, ?string $userAgent = null): CommunicationWebhookLog
    {
        // 1. Let the provider parse the raw payload
        $parsed = $this->provider->handleWebhook($payload, $headers);

        // 2. Resolve Integration Account & Company by UUID
        $account = IntegrationAccount::withoutGlobalScopes()
            ->where('uuid', $uuid)
            ->first();

        if (! $account) {
            throw new IntegrationAccountNotFoundException("No integration account registered for UUID: {$uuid}");
        }

        // Inject the fingerprint into the payload logs for easy querying
        $enrichedPayload = array_merge($payload, [
            'fingerprint' => $parsed->fingerprint,
        ]);

        // 3. Create Webhook Audit Log (no payload must be lost)
        $logRecord = $this->logRepository->create([
            'company_id' => $account->company_id,
            'integration_account_id' => $account->id,
            'provider' => $parsed->provider->value,
            'event_type' => $parsed->eventType->value,
            'payload' => $enrichedPayload,
            'headers' => $headers,
            'processed' => ProcessedStatusEnum::PENDING->value,
            'attempt_count' => 1,
            'request_ip' => $requestIp,
            'user_agent' => $userAgent,
        ]);

        Logger::info("Logged webhook payload. Audit log ID: {$logRecord->id}, fingerprint: {$parsed->fingerprint}. Dispatching queue job...");

        // 4. Dispatch Asynchronous Queue Job
        dispatch(new StoreWebhookEventJob($logRecord->id, $parsed->fingerprint));

        return $logRecord;
    }
}