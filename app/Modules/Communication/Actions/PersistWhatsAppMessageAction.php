<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Contracts\WhatsAppMessageRepositoryInterface;
use App\Modules\Communication\Enums\MessageDirectionEnum;
use App\Modules\Communication\Models\WhatsAppMessage;

class PersistWhatsAppMessageAction
{
    public function __construct(
        protected WhatsAppMessageRepositoryInterface $messageRepository
    ) {}

    /**
     * Persist a WhatsApp message record.
     * Prevents duplication if the message has already been stored (using the unique wamid message_id).
     */
    public function execute(
        int $companyId,
        int $integrationAccountId,
        int $whatsappContactId,
        ?string $messageId,
        MessageDirectionEnum|int $direction,
        string $type = 'text',
        ?string $body = null,
        ?string $mediaId = null,
        ?string $mediaUrl = null,
        string $status = 'sent',
        ?array $rawPayload = null,
        mixed $sentAt = null
    ): WhatsAppMessage {
        // 1. Deduplicate by Meta message ID if provided
        if ($messageId) {
            $existing = $this->messageRepository->findByMessageId($messageId);
            if ($existing) {
                return $existing;
            }
        }

        // 2. Map direction parameter to its raw value if it is an Enum
        $rawDirection = $direction instanceof MessageDirectionEnum ? $direction->value : $direction;

        // 3. Create the message record
        return $this->messageRepository->create([
            'company_id' => $companyId,
            'integration_account_id' => $integrationAccountId,
            'whatsapp_contact_id' => $whatsappContactId,
            'message_id' => $messageId,
            'direction' => $rawDirection,
            'type' => $type,
            'body' => $body,
            'media_id' => $mediaId,
            'media_url' => $mediaUrl,
            'status' => $status,
            'raw_payload' => $rawPayload,
            'sent_at' => $sentAt ?: now(),
        ]);
    }
}
