<?php

declare(strict_types=1);

namespace App\Modules\Communication\Exceptions;

use Exception;

class IntegrationAccountNotFoundException extends Exception
{
    public static function forId(int $id): self
    {
        return new self("Integration account with ID {$id} was not found.");
    }

    public static function forPhoneNumberId(string $phoneNumberId): self
    {
        return new self("Integration account with Phone Number ID '{$phoneNumberId}' was not found.");
    }
}
