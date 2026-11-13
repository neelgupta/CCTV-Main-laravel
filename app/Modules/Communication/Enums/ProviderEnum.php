<?php

declare(strict_types=1);

namespace App\Modules\Communication\Enums;

enum ProviderEnum: int
{
    case META_WHATSAPP = 1;

    /**
     * Get the human-readable name of the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::META_WHATSAPP => 'Meta WhatsApp Cloud API',
        };
    }
}
