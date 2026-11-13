<?php

return [
    /*
     * Keep the storage cap centralized so it can be changed in one place later.
     */
    'storage_limit_mb' => env('SUBSCRIPTION_STORAGE_LIMIT_MB', 500),

    /*
     * Free trial length, in days. Change here only — every trial activation
     * (onboarding sign-up and in-app plan change) reads from this value.
     */
    'trial_days' => env('SUBSCRIPTION_TRIAL_DAYS', 15),

    /*
     * Defaults for the Razorpay recurring billing settings. Super Admin can
     * override each value from Global Settings (stored in global_settings).
     */
    'recurring' => [
        'enabled' => false,
        'mode' => 'optional',
        'grace_days' => 3,
        'grace_access' => 'full',
        'total_count_monthly' => 120,
        'total_count_yearly' => 10,
        'trial_requires_mandate' => false,
        'renewal_reminder_days' => 3,
    ],
];
