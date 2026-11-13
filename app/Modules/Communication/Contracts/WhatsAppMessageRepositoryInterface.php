<?php

declare(strict_types=1);

namespace App\Modules\Communication\Contracts;

use App\Modules\Communication\Models\WhatsAppMessage;

interface WhatsAppMessageRepositoryInterface
{
    public function findByMessageId(string $messageId): ?WhatsAppMessage;

    public function create(array $data): WhatsAppMessage;

    public function update(WhatsAppMessage $message, array $data): bool;

    public function updateStatusByMessageId(string $messageId, string $status): bool;
}
