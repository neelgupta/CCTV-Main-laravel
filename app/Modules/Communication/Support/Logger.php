<?php

declare(strict_types=1);

namespace App\Modules\Communication\Support;

use Illuminate\Support\Facades\Log;

class Logger
{
    /**
     * Log an informational message.
     */
    public static function info(string $message, array $context = []): void
    {
        Log::channel('communication')->info($message, $context);
    }

    /**
     * Log an error message.
     */
    public static function error(string $message, array $context = []): void
    {
        Log::channel('communication')->error($message, $context);
    }

    /**
     * Log a warning message.
     */
    public static function warning(string $message, array $context = []): void
    {
        Log::channel('communication')->warning($message, $context);
    }

    /**
     * Log a debug message.
     */
    public static function debug(string $message, array $context = []): void
    {
        Log::channel('communication')->debug($message, $context);
    }
}
