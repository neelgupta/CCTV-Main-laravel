<?php

declare(strict_types=1);

namespace App\Modules\Communication\Exceptions;

use Exception;

class WebhookVerificationException extends Exception
{
    public static function invalidVerifyToken(): self
    {
        return new self('The provided hub.verify_token did not match the configured verification token.');
    }

    public static function signatureMismatch(): self
    {
        return new self('The computed request signature did not match the X-Hub-Signature header.');
    }
}
