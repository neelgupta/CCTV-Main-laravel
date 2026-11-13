<?php

return [
    'project_id' => env('GOOGLE_DRIVE_PROJECT_ID', 'stage-we4work'),
    'client_email' => env('GOOGLE_DRIVE_CLIENT_EMAIL', 'we4workbackup@stage-we4work.iam.gserviceaccount.com'),
    'delegate_email' => env('GOOGLE_DRIVE_DELEGATE_EMAIL'),
    'folder_id' => env('GOOGLE_DRIVE_FOLDER_ID'),
    'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'),

    // OAuth client credentials (used instead of a service account when set).
    // A service account has no Drive storage quota of its own, so most setups
    // authorize a real Google account via OAuth and reuse its refresh token.
    'oauth_client_id' => env('GOOGLE_DRIVE_OAUTH_CLIENT_ID'),
    'oauth_client_secret' => env('GOOGLE_DRIVE_OAUTH_CLIENT_SECRET'),
    'oauth_refresh_token' => env('GOOGLE_DRIVE_OAUTH_REFRESH_TOKEN'),
    'retention_limit' => (int) env('GOOGLE_DRIVE_BACKUP_RETENTION_LIMIT', 30),
    'temp_directory' => env('GOOGLE_DRIVE_BACKUP_TEMP_DIRECTORY', 'google-drive-backups/tmp'),
    'log_channel' => env('GOOGLE_DRIVE_BACKUP_LOG_CHANNEL', 'google-drive-backup'),
];
