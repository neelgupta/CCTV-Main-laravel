<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

enum ConnectionStatusEnum: int
{
    case DISCONNECTED = 1;
    case CONNECTED = 2;
    case FAILED = 3;

    /**
     * Get the human-readable label for status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DISCONNECTED => 'Disconnected',
            self::CONNECTED => 'Connected',
            self::FAILED => 'Failed',
        };
    }
}
