<?php

declare(strict_types=1);

namespace App\Modules\Communication\Actions;

use App\Modules\Communication\Contracts\WhatsAppMessageRepositoryInterface;
use App\Modules\Communication\Support\Logger;

class UpdateWhatsAppMessageStatusAction
{
    public function __construct(
        protected WhatsAppMessageRepositoryInterface $messageRepository
    ) {}

    /**
     * Update the delivery/read status of an existing message by its message_id.
     */
    public function execute(string $messageId, string $status): bool
    {
        $message = $this->messageRepository->findByMessageId($messageId);

        if (! $message) {
            Logger::warning("Could not update status to '{$status}' for message ID: {$messageId} - message record not found in database.");

            return false;
        }

        // Only update if status is actually different
        if ($message->status !== $status) {
            $this->messageRepository->update($message, [
                'status' => $status,
            ]);
            Logger::info("Updated message ID: {$messageId} status to: {$status}.");

            return true;
        }

        return false;
    }
}

