<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Contracts\IntegrationAccountRepositoryInterface;
use App\Modules\Communication\Exceptions\IntegrationAccountNotFoundException;
use App\Modules\Communication\Exceptions\WebhookVerificationException;
use App\Modules\Communication\Models\IntegrationAccount;
use App\Modules\Communication\Support\Logger;

class VerifyWebhookAction
{
    public function __construct(
        protected IntegrationAccountRepositoryInterface $accountRepository
    ) {}

    /**
     * Verify the incoming Meta webhook handshake.
     *
     * @throws WebhookVerificationException
     * @throws IntegrationAccountNotFoundException
     */
    public function execute(string $uuid, array $params): string
    {
        $verifyToken = $params['hub_verify_token'] ?? '';
        $challenge = $params['hub_challenge'] ?? '';

        Logger::info("Webhook verification request received for UUID: {$uuid}. Token: {$verifyToken}");

        // Resolve integration account globally by UUID
        $account = IntegrationAccount::withoutGlobalScopes()
            ->where('uuid', $uuid)
            ->first();

        if (! $account) {
            throw new IntegrationAccountNotFoundException('No integration account matches the verification criteria.');
        }

        // Validate token
        if ($account->verify_token !== $verifyToken) {
            throw WebhookVerificationException::invalidVerifyToken();
        }

        Logger::info("Webhook verified successfully for Phone Number ID: {$account->phone_number_id}");

        return $challenge;
    }
}
