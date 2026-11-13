<?php

declare(strict_types=1);

namespace App\Modules\Communication\Exceptions;

use Exception;

class WebhookProcessingException extends Exception
{
    public static function forLog(string $uuid, string $reason): self
    {
        return new self("Webhook Log {$uuid} failed to process: {$reason}");
    }
}
