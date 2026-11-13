<?php

declare(strict_types=1);

namespace App\Modules\Communication\Exceptions;

use Exception;

class ProviderConnectionException extends Exception
{
    public static function unavailable(string $providerName, string $message): self
    {
        return new self("Could not connect to provider '{$providerName}': {$message}");
    }
}
