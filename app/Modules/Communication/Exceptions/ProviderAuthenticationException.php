<?php

declare(strict_types=1);

namespace App\Modules\Communication\Exceptions;

use Exception;

class ProviderAuthenticationException extends Exception
{
    public static function failed(string $providerName, string $message): self
    {
        return new self("Authentication failed with provider '{$providerName}': {$message}");
    }
}
