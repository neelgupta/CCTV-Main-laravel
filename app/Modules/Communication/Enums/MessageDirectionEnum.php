<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

enum MessageDirectionEnum: int
{
    case INBOUND = 1;
    case OUTBOUND = 2;

    /**
     * Get a human-readable label for the direction.
     */
    public function label(): string
    {
        return match ($this) {
            self::INBOUND => 'Inbound',
            self::OUTBOUND => 'Outbound',
        };
    }
}
