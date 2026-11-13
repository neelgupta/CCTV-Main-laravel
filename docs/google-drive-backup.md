# Google Drive Database Backup

## Overview

This project includes a production-ready Google Drive backup flow for the MySQL database only, authenticated with a **Google Service Account** (no OAuth login, no refresh tokens, no manual re-authentication).

The backup process:

1. Verifies the Google Drive connection and folder access.
2. Dumps the active MySQL database to SQL.
3. Compresses the SQL file into a ZIP archive.
4. Uploads the ZIP to Google Drive.
5. Writes a database log entry and a dedicated log file entry.
6. Deletes local temporary files.
7. Keeps only the newest 30 backups in the configured Drive folder (older ones are deleted automatically).

## Required Composer Packages

```bash
composer require google/apiclient:^2.19
```

This project already has `google/apiclient` installed and declared in `composer.json`. Also required:

- PHP `ext-zip`
- PHP `ext-json`

## Why Service Account Instead of OAuth

The previous implementation used OAuth Client ID / Client Secret / Refresh Token. Refresh tokens can be revoked, expire after long inactivity, or be invalidated by security policy changes, which caused recurring failures:

- `Token has been expired or revoked`
- `Unauthorized`
- `unauthorized_client`

A Service Account uses a private key file (JSON) issued by Google Cloud that does not expire and requires no user consent or re-authorization. It authenticates directly, server-to-server, which is the correct pattern for unattended cron/scheduled jobs.

## Step 1 — Create a Service Account

1. Open the [Google Cloud Console](https://console.cloud.google.com/).
2. Select (or create) the project that should own the backup credentials.
3. Enable the **Google Drive API** for the project (APIs & Services → Library → search "Google Drive API" → Enable).
4. Go to **APIs & Services → Credentials**.
5. Click **Create Credentials → Service Account**.
6. Give it a name, e.g. `laravel-backup`.
7. Skip granting it project-level IAM roles (not needed — Drive folder sharing handles access).
8. Click **Done**.

## Step 2 — Generate and Download the JSON Key

1. Open the newly created service account.
2. Go to the **Keys** tab.
3. Click **Add Key → Create new key**.
4. Choose **JSON** and click **Create**.
5. A JSON key file downloads automatically. Keep it private — this file is a credential.

## Step 3 — Store the JSON File

Upload the downloaded file to the server and store it outside of `public/`, for example:

```
storage/app/google-drive-service-account.json
```

Do not commit this file to version control. Confirm it's excluded (a project-wide `storage/app/*.json` or explicit entry in `.gitignore` is recommended).

## Step 4 — Share the Drive Folder with the Service Account

The service account has its own email address, shown on its details page and inside the JSON key as `client_email`, e.g.:

```
laravel-backup@project-id.iam.gserviceaccount.com
```

1. Open Google Drive and create (or locate) the folder that will hold backups.
2. Right-click the folder → **Share**.
3. Paste the service account email.
4. Set its role to **Editor**.
5. Click **Send** (no real email is delivered, but sharing is applied).
6. Copy the folder ID from the folder's URL, e.g.:
   `https://drive.google.com/drive/folders/1EP8l5HpVGdL0g2bKUVApMISAWcfyAI83`
   → folder ID is `1EP8l5HpVGdL0g2bKUVApMISAWcfyAI83`.

**Without this share step, every backup will fail with a folder access error** — the service account only has permission to files/folders explicitly shared with it.

## Step 5 — Configure `.env`

```env
GOOGLE_DRIVE_FOLDER_ID=1EP8l5HpVGdL0g2bKUVApMISAWcfyAI83
GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON=storage/app/google-drive-service-account.json
GOOGLE_DRIVE_BACKUP_RETENTION_LIMIT=30
GOOGLE_DRIVE_BACKUP_TEMP_DIRECTORY=google-drive-backups/tmp
GOOGLE_DRIVE_BACKUP_LOG_CHANNEL=google-drive-backup
```

`GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON` may be:

- A path relative to the project root (as above), or
- An absolute path (e.g. `/var/www/app/storage/app/google-drive-service-account.json` or `C:\path\to\key.json`).
- The raw JSON contents copied directly into `.env` if your deployment process supports multi-line or escaped values.

Then clear cached config:

```bash
php artisan config:clear
```

## Step 6 — Run the Backup Command

```bash
php artisan backup:google-drive
```

Expected flow on success:

1. Verifies the Google Drive connection (service account auth).
2. Verifies folder access (fails fast with a clear message if not shared).
3. Generates the database dump.
4. Compresses it into a ZIP.
5. Uploads the ZIP to Drive.
6. Saves a `google_drive_backup_logs` row.
7. Cleans up local temp files.
8. Prunes old Drive backups beyond the retention limit.
9. Prints a success summary (backup name, Drive file ID, duration).

## Step 7 — Configure the Scheduler

Registered in `routes/console.php`:

```php
Schedule::command('backup:google-drive')->dailyAt('02:00')->withoutOverlapping();
```

Other supported frequencies (examples, pick one instead of daily as needed):

```php
// Hourly
Schedule::command('backup:google-drive')->hourly()->withoutOverlapping();

// Weekly (every Monday at 02:00)
Schedule::command('backup:google-drive')->weeklyOn(1, '02:00')->withoutOverlapping();

// Monthly (1st of the month at 02:00)
Schedule::command('backup:google-drive')->monthlyOn(1, '02:00')->withoutOverlapping();
```

## Step 8 — Configure the Cron Job

Make sure the server cron triggers Laravel's scheduler every minute:

```cron
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

On Windows/XAMPP/WAMP, use Task Scheduler to run the same command every minute instead of cron.

## Retention Policy

Only the newest `GOOGLE_DRIVE_BACKUP_RETENTION_LIMIT` (default 30) backups are kept in the Drive folder. After each successful upload, older backups matching the `database_*.sql.zip` naming pattern are deleted automatically.

## Logging

Backup runs are written to:

- Database table: `google_drive_backup_logs` (backup name, Google file ID, file size, status, start/end time, duration, error message)
- File log: `storage/logs/google-drive-backup-*.log` (channel configured via `GOOGLE_DRIVE_BACKUP_LOG_CHANNEL`)

Recorded fields include:

- Backup start time and end time
- File name
- File size
- Google Drive file ID
- Upload status (success/failed)
- Error message (on failure)
- Duration in seconds

Service account private keys and JSON contents are never logged.

## Error Handling Reference

| Situation | Behavior |
|---|---|
| `GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON` not set | Fails fast: "GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON is not configured." |
| JSON file missing on disk | Fails fast: "Google Service Account JSON file not found at: ..." |
| JSON file present but not a valid service account key | Fails fast: "...is invalid or is not a service account key." |
| Folder not shared with the service account / wrong folder ID | Fails fast: "Google Drive folder access denied. Make sure the service account email is shared as Editor on folder ..." |
| Upload fails transiently (network/API error) | Retried up to 3 times with exponential backoff before failing the run |
| Any failure during the run | Logged to the database and the log channel with the error message and duration; local temp files are still cleaned up |

## Testing Steps

1. Confirm `.env` values are set (`GOOGLE_DRIVE_FOLDER_ID`, `GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON`).
2. Confirm the JSON key file exists at the configured path.
3. Confirm the Drive folder is shared with the service account email as Editor.
4. Run `php artisan config:clear`.
5. Execute the backup manually:

```bash
php artisan backup:google-drive
```

6. Verify a new row appears in `google_drive_backup_logs` with `status = success`.
7. Confirm the ZIP file appears in the Google Drive folder.
8. Open the Backup Settings page in the admin panel and confirm the status cards and history table update.

## Restore Process

To restore a backup:

1. Download the ZIP file from Google Drive.
2. Extract the SQL file inside the archive.
3. Import the SQL into the target MySQL database.

Example:

```bash
mysql -u your_user -p your_database < database_YYYY_MM_DD_HH_MM_SS.sql
```

If the environment is empty or a new database is being used, create the schema first before importing the SQL dump.

## Security Notes

- Never commit the service account JSON file to version control.
- Never log the JSON file contents or private key.
- The application validates the JSON file's existence and shape (must be `"type": "service_account"`) before attempting authentication.
- Rotate the key periodically from the Google Cloud Console (Service Account → Keys → add a new key, delete the old one) — no application changes are required, only replacing the file on disk.
- If a previous OAuth Client ID / Client Secret / Refresh Token was ever used for this feature and committed or shared, revoke/regenerate those credentials in the Google Cloud Console, since OAuth support has been fully removed from this codebase.
