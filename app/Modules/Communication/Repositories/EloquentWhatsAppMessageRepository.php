<?php

declare(strict_types=1);

namespace App\Modules\Communication\Repositories;

use App\Modules\Communication\Contracts\WhatsAppMessageRepositoryInterface;
use App\Modules\Communication\Models\WhatsAppMessage;

class EloquentWhatsAppMessageRepository implements WhatsAppMessageRepositoryInterface
{
    public function findByMessageId(string $messageId): ?WhatsAppMessage
    {
        return WhatsAppMessage::withoutGlobalScopes()
            ->where('message_id', $messageId)
            ->first();
    }

    public function create(array $data): WhatsAppMessage
    {
        return WhatsAppMessage::create($data);
    }

    public function update(WhatsAppMessage $message, array $data): bool
    {
        return $message->update($data);
    }

    public function updateStatusByMessageId(string $messageId, string $status): bool
    {
        return WhatsAppMessage::withoutGlobalScopes()
            ->where('message_id', $messageId)
            ->update(['status' => $status]);
    }
}
