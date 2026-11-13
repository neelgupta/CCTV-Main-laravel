<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Communication Provider
    |--------------------------------------------------------------------------
    |
    | This option controls the default communication provider that will be used
    | by the application. You can define various provider configurations below.
    |
    */

    'default' => env('COMMUNICATION_DEFAULT_PROVIDER', 'meta_whatsapp'),

    /*
    |--------------------------------------------------------------------------
    | Communication Providers
    |--------------------------------------------------------------------------
    |
    | Here you can configure the connection details, API endpoints, and setup
    | settings for each communication provider supported by your system.
    |
    */

    'providers' => [

        'meta_whatsapp' => [
            'name' => 'Meta WhatsApp Cloud API',
            'enabled' => env('COMMUNICATION_WHATSAPP_ENABLED', true),
            'class' => \App\Modules\Communication\Services\Providers\MetaWhatsAppProvider::class,
            'api_version' => env('COMMUNICATION_WHATSAPP_API_VERSION', 'v20.0'),
            'base_url' => env('COMMUNICATION_WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
            'timeout' => (int) env('COMMUNICATION_WHATSAPP_TIMEOUT_SECONDS', 15),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration Settings
    |--------------------------------------------------------------------------
    |
    | Configuration options for handling incoming webhooks. Define paths,
    | signature validation policies, and payload logging behavior.
    |
    */

    'webhooks' => [
        'log_payloads' => env('COMMUNICATION_LOG_WEBHOOK_PAYLOADS', true),
        'verify_token' => env('COMMUNICATION_WEBHOOK_VERIFY_TOKEN'),
        'ip_verification' => env('COMMUNICATION_WEBHOOK_IP_VERIFY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Webhook event payloads are written to the audit log and immediately
    | dispatched to a queue to ensure response speed. Define connection
    | and queue names here.
    |
    */

    'queue' => [
        'connection' => env('COMMUNICATION_QUEUE_CONNECTION', 'database'),
        'queue_name' => env('COMMUNICATION_QUEUE_NAME', 'communication-webhooks'),
        'retry_limit' => (int) env('COMMUNICATION_QUEUE_RETRY_LIMIT', 3),
        'retry_delay_seconds' => (int) env('COMMUNICATION_QUEUE_RETRY_DELAY', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags & Rate Limits
    |--------------------------------------------------------------------------
    |
    | Control specific features of the communication module and enforce limits.
    |
    */

    'features' => [
        'track_metrics' => env('COMMUNICATION_TRACK_METRICS', true),
        'encryption' => env('COMMUNICATION_ENCRYPT_SECRETS', true),
    ],

];
