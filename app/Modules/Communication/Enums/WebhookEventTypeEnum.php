<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

enum WebhookEventTypeEnum: int
{
    case MESSAGE = 1;
    case DELIVERY = 2;
    case READ = 3;
    case STATUS_UPDATE = 4;
    case OTHER = 99;

    /**
     * Get the label for the event type.
     */
    public function label(): string
    {
        return match ($this) {
            self::MESSAGE => 'Message Received',
            self::DELIVERY => 'Delivery Status',
            self::READ => 'Read Receipt',
            self::STATUS_UPDATE => 'Status Update',
            self::OTHER => 'Other/System Event',
        };
    }
}
