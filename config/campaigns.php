<?php

return [
    'batch_size' => (int) env('CAMPAIGN_BATCH_SIZE', 200),
    'batch_delay_seconds' => (int) env('CAMPAIGN_BATCH_DELAY_SECONDS', 30),
    'festival_limit' => (int) env('CAMPAIGN_FESTIVAL_LIMIT', 12),
    'tracking_base_url' => env('CAMPAIGN_TRACKING_BASE_URL', env('APP_URL')),
    'retry_count' => (int) env('CAMPAIGN_RETRY_COUNT', 3),
    'cron_interval' => env('CAMPAIGN_CRON_INTERVAL', 'everyMinute'),

    'ai' => [
        'provider' => env('CAMPAIGN_AI_PROVIDER', 'openai'),
        'banner_provider' => env('CAMPAIGN_BANNER_PROVIDER', env('CAMPAIGN_AI_PROVIDER', 'openai')),
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('OPENAI_CAMPAIGN_MODEL', 'gpt-4.1-mini'),
        ],
        'gemini' => [
            'api_key' => env('GEMINI_API_KEY'),
            'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            'model' => env('GEMINI_CAMPAIGN_MODEL', 'gemini-2.5-flash'),
        ],
        'stability' => [
            'api_key' => env('STABILITY_AI_API_KEY'),
        ],
    ],
];
