<?php

return [
    // Public Terms & Conditions page linked from the registration form.
    'terms_url' => env('TERMS_URL', 'https://www.cctvproone.com/terms-conditions.html'),

    // Bump this whenever the Terms & Conditions text changes, so each user's
    // stored acceptance shows which version they agreed to.
    'terms_version' => env('TERMS_VERSION', '2026-10-14'),
];
