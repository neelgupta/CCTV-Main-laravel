<?php

namespace App\Console\Commands;

use App\Services\GoogleDriveBackupService;
use Illuminate\Console\Command;
use Throwable;

class BackupGoogleDriveCommand extends Command
{
    protected $signature = 'backup:google-drive';

    protected $description = 'Create a MySQL database backup, compress it, upload it to Google Drive, and prune older backups.';

    public function handle(GoogleDriveBackupService $backupService): int
    {
        try {
            $result = $backupService->runBackup();

            $this->info('Google Drive backup completed successfully.');
            $this->line('Backup: '.$result['backup_name']);
            $this->line('File ID: '.$result['google_file_id']);
            $this->line('Duration: '.$result['duration_seconds'].' seconds');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Google Drive backup failed.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }
    }
}
