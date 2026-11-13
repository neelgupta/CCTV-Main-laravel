<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\DTOs\WebhookPayloadData;
use App\Modules\Communication\Models\IntegrationAccount;

interface CommunicationProviderInterface
{
    public function verifyConnection(IntegrationAccount $account): bool;

    public function verifyWebhook(array $params, IntegrationAccount $account): bool;

    public function handleWebhook(array $payload, array $headers): WebhookPayloadData;

    public function sendRequest(string $method, string $endpoint, array $options = []): array;

    public function sendMessage(IntegrationAccount $account, string $to, string $text): array;

    public function markAsRead(IntegrationAccount $account, string $messageId): array;

    public function healthCheck(IntegrationAccount $account): bool;
}

