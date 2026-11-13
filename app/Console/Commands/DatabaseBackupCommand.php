<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'database:backup';

    protected $description = 'Create a compressed backup of the current database and prune old backups.';

    public function handle(DatabaseBackupService $backupService): int
    {
        try {
            $backup = $backupService->createBackup();

            $this->info('Database backup created successfully.');
            $this->line('File: '.$backup['filename']);
            $this->line('Size: '.$backup['size_human']);

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Database backup failed.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }
    }
}
