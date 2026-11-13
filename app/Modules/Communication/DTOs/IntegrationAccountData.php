<?php

declare(strict_types=1);

namespace App\Modules\Communication\DTOs;

class IntegrationAccountData
{
    public function __construct(
        public readonly int $companyId,
        public readonly int $provider,
        public readonly string $displayName,
        public readonly string $phoneNumber,
        public readonly string $phoneNumberId,
        public readonly string $businessAccountId,
        public readonly string $accessToken,
        public readonly string $verifyToken,
        public readonly ?string $appId = null,
        public readonly ?string $webhookSecret = null,
        public readonly ?array $metadata = null
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            companyId: (int) $data['company_id'],
            provider: (int) $data['provider'],
            displayName: (string) $data['display_name'],
            phoneNumber: (string) $data['phone_number'],
            phoneNumberId: (string) $data['phone_number_id'],
            businessAccountId: (string) $data['business_account_id'],
            accessToken: (string) $data['access_token'],
            verifyToken: (string) $data['verify_token'],
            appId: isset($data['app_id']) ? (string) $data['app_id'] : null,
            webhookSecret: isset($data['webhook_secret']) ? (string) $data['webhook_secret'] : null,
            metadata: isset($data['metadata']) ? (array) $data['metadata'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'company_id' => $this->companyId,
            'provider' => $this->provider,
            'display_name' => $this->displayName,
            'phone_number' => $this->phoneNumber,
            'phone_number_id' => $this->phoneNumberId,
            'business_account_id' => $this->businessAccountId,
            'access_token' => $this->accessToken,
            'verify_token' => $this->verifyToken,
            'app_id' => $this->appId,
            'webhook_secret' => $this->webhookSecret,
            'metadata' => $this->metadata,
        ];
    }
}
