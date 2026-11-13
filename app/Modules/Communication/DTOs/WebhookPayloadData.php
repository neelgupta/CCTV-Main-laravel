<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

use App\Modules\Communication\Enums\ProviderEnum;
use App\Modules\Communication\Enums\WebhookEventTypeEnum;

class WebhookPayloadData
{
    public function __construct(
        public readonly ProviderEnum $provider,
        public readonly WebhookEventTypeEnum $eventType,
        public readonly string $fingerprint,
        public readonly array $payload,
        public readonly array $headers,
        public readonly ?string $phoneNumberId = null,
        public readonly ?string $messageId = null
    ) {}
}
