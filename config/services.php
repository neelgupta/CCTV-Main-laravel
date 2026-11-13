<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        // Overridden at boot from Global Settings (payment_gateway_mode / *_webhook_key).
        'mode' => 'live',
        'webhook_secret' => null,
        'currency' => env('RAZORPAY_CURRENCY', 'INR'),
        'verify_ssl' => env('RAZORPAY_VERIFY_SSL', env('APP_ENV', 'production') === 'production'),
    ],

    // 'location_autocomplete' => [
    //     'url' => env('LOCATION_AUTOCOMPLETE_URL', 'https://photon.komoot.io/api/?limit=5&q='),
    // ],

       // New
    'google_maps' => [
        'key' => env('GOOGLE_MAPS_API_KEY'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'client_email' => env('FIREBASE_CLIENT_EMAIL'),
        'private_key' => env('FIREBASE_PRIVATE_KEY'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'api_key' => env('FIREBASE_API_KEY'),
        'app_id' => env('FIREBASE_APP_ID'),
        'vapid_key' => env('FIREBASE_VAPID_KEY'),
    ],

    'inquiry' => [
        'target_company_code' => 'CC-HY5FLC',
    ],

];
