<?php

namespace App\Services;

use App\Models\GoogleDriveBackupLog;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Creates a MySQL dump, compresses it, uploads it to Google Drive, and records audit logs.
 */
class GoogleDriveBackupService
{
    private const ROOT_DIRECTORY = 'google-drive-backups';

    private const MAX_UPLOAD_ATTEMPTS = 3;

    private ?Drive $drive = null;

    public function __construct()
    {
    }

    /**
     * Run a full database backup and upload it to Google Drive.
     *
     * @return array<string, mixed>
     */
    public function runBackup(): array
    {
        $this->ensureConfigured();

        $startedAt = now();
        $backupName = $this->backupNameFor($startedAt);
        $sqlFileName = str_replace('.zip', '', $backupName);
        $tempSqlPath = $this->tempPath($sqlFileName);
        $tempZipPath = $this->tempPath($backupName);
        $logEntry = $this->createLogEntry($backupName, $startedAt);

        Log::channel($this->logChannel())->info('Google Drive backup started.', [
            'backup_name' => $backupName,
            'folder_id' => $this->folderId(),
            'started_at' => $startedAt->toDateTimeString(),
        ]);

        try {
            $this->verifyConnection();
            $this->ensureDirectories();
            $this->dumpDatabaseToSqlFile($tempSqlPath, $sqlFileName);
            $this->createZipArchive($tempZipPath, $tempSqlPath, $sqlFileName);

            $uploadResponse = $this->uploadWithRetry($tempZipPath, $backupName);
            $uploadedFileId = $this->getUploadedFileId($uploadResponse);

            if (! $uploadedFileId) {
                throw new RuntimeException('Google Drive did not return an uploaded file ID.');
            }

            $googleLink = $uploadResponse['webViewLink'] ?? null;
            $fileStatus = $this->checkUploadStatus($uploadedFileId);
            $fileSizeBytes = isset($fileStatus['size']) ? (int) $fileStatus['size'] : (int) filesize($tempZipPath);

            $this->updateLogEntry($logEntry, [
                'google_file_id' => $uploadedFileId,
                'google_file_url' => $googleLink,
                'file_size_bytes' => $fileSizeBytes > 0 ? $fileSizeBytes : null,
                'status' => GoogleDriveBackupLog::STATUS_SUCCESS,
                'end_time' => now(),
                'duration_seconds' => $startedAt->diffInSeconds(now()),
                'error_message' => null,
            ]);

            $cleanupCount = $this->cleanupOldRemoteBackups();

            Log::channel($this->logChannel())->info('Google Drive backup completed.', [
                'backup_name' => $backupName,
                'google_file_id' => $uploadedFileId,
                'folder_id' => $this->folderId(),
                'cleanup_deleted' => $cleanupCount,
            ]);

            return [
                'backup_name' => $backupName,
                'google_file_id' => $uploadedFileId,
                'google_file_url' => $googleLink,
                'status' => GoogleDriveBackupLog::STATUS_SUCCESS,
                'started_at' => $startedAt,
                'ended_at' => now(),
                'duration_seconds' => $startedAt->diffInSeconds(now()),
                'cleanup_deleted' => $cleanupCount,
                'log_id' => $logEntry->id,
            ];
        } catch (Throwable $e) {
            $this->updateLogEntry($logEntry, [
                'status' => GoogleDriveBackupLog::STATUS_FAILED,
                'end_time' => now(),
                'duration_seconds' => $startedAt->diffInSeconds(now()),
                'error_message' => $e->getMessage(),
            ]);

            Log::channel($this->logChannel())->error('Google Drive backup failed.', [
                'backup_name' => $backupName,
                'folder_id' => $this->folderId(),
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        } finally {
            $this->safeDelete($tempSqlPath);
            $this->safeDelete($tempZipPath);
        }
    }

    /**
     * Build dashboard data for the backup settings page.
     *
     * @return array<string, mixed>
     */
    public function dashboardData(): array
    {
        $latestLog = $this->latestLog();
        $nextScheduledBackup = $this->nextScheduledBackup();
        $connectionStatus = $this->connectionStatus();

        return [
            'connected' => $connectionStatus['connected'],
            'connection_message' => $connectionStatus['message'],
            'folder_id' => $this->folderId(),
            'last_backup_time' => $latestLog?->start_time?->format('d M Y, h:i A') ?? 'No backups yet',
            'last_backup_status' => $latestLog?->status ? strtoupper($latestLog->status) : 'Not Run',
            'next_scheduled_backup' => $nextScheduledBackup->format('d M Y, h:i A'),
            'latest_backup_file' => $latestLog?->backup_name ?? 'No backups yet',
            'latest_backup_link' => $latestLog?->google_file_url,
            'latest_log' => $latestLog,
            'history' => $this->history(),
        ];
    }

    /**
     * @return Collection<int, GoogleDriveBackupLog>
     */
    public function history(int $limit = 30): Collection
    {
        return GoogleDriveBackupLog::query()
            ->latest('start_time')
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    public function latestLog(): ?GoogleDriveBackupLog
    {
        return GoogleDriveBackupLog::query()
            ->latest('start_time')
            ->latest('id')
            ->first();
    }

    /**
     * @return array{connected:bool,message:string}
     */
    public function connectionStatus(): array
    {
        if (! $this->isConfigured()) {
            return [
                'connected' => false,
                'message' => 'Google Drive backup credentials are not configured.',
            ];
        }

        try {
            $this->verifyFolderAccess();

            return [
                'connected' => true,
                'message' => 'Connected to Google Drive shared folder.',
            ];
        } catch (Throwable $e) {
            Log::channel($this->logChannel())->warning('Google Drive backup status check failed.', [
                'folder_id' => $this->folderId(),
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return [
                'connected' => false,
                'message' => 'Not connected: '.$e->getMessage(),
            ];
        }
    }

    public function nextScheduledBackup(): Carbon
    {
        $scheduled = now()->setTime(2, 0, 0);

        return now()->lessThan($scheduled) ? $scheduled : $scheduled->addDay();
    }

    private function createLogEntry(string $backupName, Carbon $startedAt): GoogleDriveBackupLog
    {
        return GoogleDriveBackupLog::create([
            'backup_name' => $backupName,
            'folder_id' => $this->folderId(),
            'status' => GoogleDriveBackupLog::STATUS_RUNNING,
            'start_time' => $startedAt,
        ]);
    }

    private function updateLogEntry(GoogleDriveBackupLog $logEntry, array $attributes): void
    {
        $logEntry->forceFill($attributes)->save();
    }

    /**
     * Upload a local file to the configured shared Google Drive folder.
     *
     * @return array<string, mixed>
     */
    public function uploadBackup(string $localFilePath, string $outputFileName): array
    {
        $folderId = $this->folderId();
        $storagePath = $this->storagePathForRead($localFilePath);

        if (! Storage::disk('local')->exists($storagePath)) {
            throw new RuntimeException('Local backup file not found: '.$localFilePath);
        }

        $fileContent = Storage::disk('local')->get($storagePath);

        if (! is_string($fileContent) || $fileContent === '') {
            throw new RuntimeException('Unable to read local backup file content.');
        }

        $mimeType = Storage::disk('local')->mimeType($storagePath) ?: 'application/octet-stream';
        $metadata = new DriveFile([
            'name' => $outputFileName,
            'parents' => [$folderId],
        ]);

        try {
            $response = $this->drive()->files->create($metadata, [
                'data' => $fileContent,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,webViewLink,webContentLink,size,createdTime',
                'supportsAllDrives' => true,
                'enforceSingleParent' => true,
            ]);
        } catch (Throwable $e) {
            Log::error('Google Drive backup upload failed.', [
                'local_file_path' => $localFilePath,
                'output_file_name' => $outputFileName,
                'folder_id' => $folderId,
                'message' => $this->extractGoogleErrorMessage($e),
                'exception' => $e::class,
            ]);

            throw new RuntimeException('Google Drive upload failed: '.$this->extractGoogleErrorMessage($e), previous: $e);
        }

        return [
            'id' => $response->id ?? null,
            'name' => $response->name ?? $outputFileName,
            'webViewLink' => $response->webViewLink ?? null,
            'webContentLink' => $response->webContentLink ?? null,
            'size' => isset($response->size) ? (int) $response->size : null,
            'createdTime' => $response->createdTime ?? null,
        ];
    }

    private function ensureConfigured(): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException(
                'Google Drive backup is not configured. Set GOOGLE_DRIVE_FOLDER_ID and either '
                .'GOOGLE_DRIVE_OAUTH_CLIENT_ID/GOOGLE_DRIVE_OAUTH_CLIENT_SECRET/GOOGLE_DRIVE_OAUTH_REFRESH_TOKEN '
                .'or GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON in .env.'
            );
        }
    }

    /**
     * Verify the Google Drive connection and folder access before doing any work.
     */
    private function verifyConnection(): void
    {
        $this->ensureConfigured();
        $this->verifyFolderAccess();
    }

    private function folderId(): string
    {
        $folderId = (string) config('google-drive-backup.folder_id');

        if ($folderId === '') {
            throw new RuntimeException('Google Drive folder ID is not configured.');
        }

        return $folderId;
    }

    private function logChannel(): string
    {
        return (string) config('google-drive-backup.log_channel', 'google-drive-backup');
    }

    private function drive(): Drive
    {
        if ($this->drive instanceof Drive) {
            return $this->drive;
        }

        $client = new Client();
        $client->setApplicationName(config('app.name', 'Laravel'));
        $client->setScopes([Drive::DRIVE]);

        if ($this->usesOauth()) {
            $this->authenticateWithOauth($client);
        } else {
            $client->setAuthConfig($this->serviceAccountConfig());

            $delegateEmail = trim((string) config('google-drive-backup.delegate_email', ''));

            if ($delegateEmail !== '') {
                $client->setSubject($delegateEmail);
            }
        }

        $this->drive = new Drive($client);

        return $this->drive;
    }

    /**
     * Whether OAuth client credentials + refresh token are configured, in
     * preference to a service account (service accounts have no Drive quota
     * of their own unless targeting a Shared Drive).
     */
    private function usesOauth(): bool
    {
        return trim((string) config('google-drive-backup.oauth_refresh_token', '')) !== ''
            && trim((string) config('google-drive-backup.oauth_client_id', '')) !== ''
            && trim((string) config('google-drive-backup.oauth_client_secret', '')) !== '';
    }

    private function authenticateWithOauth(Client $client): void
    {
        $client->setClientId((string) config('google-drive-backup.oauth_client_id'));
        $client->setClientSecret((string) config('google-drive-backup.oauth_client_secret'));

        $refreshToken = (string) config('google-drive-backup.oauth_refresh_token');
        $token = $client->fetchAccessTokenWithRefreshToken($refreshToken);

        if (isset($token['error'])) {
            throw new RuntimeException(
                'Unable to refresh Google Drive OAuth access token: '.($token['error_description'] ?? $token['error'])
            );
        }

        $client->setAccessToken($token);
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceAccountConfig(): array
    {
        $configured = trim((string) config('google-drive-backup.service_account_json', ''));

        if ($configured === '') {
            throw new RuntimeException('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON is not configured.');
        }

        $json = null;

        if (is_file($configured)) {
            $json = file_get_contents($configured);
        } else {
            $absolutePath = $this->isAbsolutePath($configured) ? $configured : base_path($configured);

            if (is_file($absolutePath)) {
                $json = file_get_contents($absolutePath);
            } else {
                $json = $configured;
            }
        }

        if (! is_string($json) || $json === '') {
            throw new RuntimeException('Unable to read Google Drive service account JSON.');
        }

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('The configured Google Service Account JSON is invalid JSON.');
        }

        if (($decoded['type'] ?? null) !== 'service_account') {
            throw new RuntimeException('The configured Google Service Account JSON file is invalid or is not a service account key.');
        }

        $projectId = trim((string) config('google-drive-backup.project_id', ''));
        $clientEmail = trim((string) config('google-drive-backup.client_email', ''));

        if ($projectId !== '') {
            $decoded['project_id'] = $projectId;
        }

        if ($clientEmail !== '') {
            $decoded['client_email'] = $clientEmail;
        }

        if (isset($decoded['private_key']) && is_string($decoded['private_key'])) {
            $decoded['private_key'] = str_replace('\n', "\n", $decoded['private_key']);
        }

        return $decoded;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    private function storagePathForRead(string $path): string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
        $storageRoot = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, storage_path('app')), DIRECTORY_SEPARATOR);

        if (str_starts_with($normalized, $storageRoot.DIRECTORY_SEPARATOR)) {
            return ltrim(substr($normalized, strlen($storageRoot) + 1), '/\\');
        }

        return ltrim($normalized, '/\\');
    }

    private function getUploadedFileId(array $response): ?string
    {
        return isset($response['id']) ? (string) $response['id'] : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkUploadStatus(string $fileId): array
    {
        try {
            $file = $this->drive()->files->get($fileId, [
                'fields' => 'id,name,mimeType,webViewLink,size,createdTime',
                'supportsAllDrives' => true,
            ]);

            return [
                'exists' => true,
                'id' => $file->id ?? $fileId,
                'name' => $file->name ?? null,
                'mime_type' => $file->mimeType ?? null,
                'web_view_link' => $file->webViewLink ?? null,
                'size' => isset($file->size) ? (int) $file->size : null,
                'created_time' => $file->createdTime ?? null,
            ];
        } catch (Throwable) {
            return [
                'exists' => false,
                'id' => $fileId,
            ];
        }
    }

    private function verifyFolderAccess(): void
    {
        try {
            $this->drive()->files->get($this->folderId(), [
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => true,
            ]);
        } catch (GoogleServiceException $e) {
            throw new RuntimeException(
                'Google Drive folder access denied. Make sure the service account has access to folder "'.$this->folderId().'". '.$this->extractGoogleErrorMessage($e),
                previous: $e
            );
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function listBackups(int $limit = 1000): Collection
    {
        try {
            $response = $this->drive()->files->listFiles([
                'q' => sprintf(
                    "'%s' in parents and trashed = false and name contains 'database_' and name contains '.sql.zip'",
                    str_replace("'", "\\'", $this->folderId())
                ),
                'orderBy' => 'createdTime desc',
                'pageSize' => min(max($limit, 1), 1000),
                'fields' => 'files(id,name,size,createdTime,webViewLink,webContentLink)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);
        } catch (GoogleServiceException $e) {
            throw new RuntimeException('Unable to list Google Drive backups: '.$this->extractGoogleErrorMessage($e), previous: $e);
        }

        return collect($response->getFiles() ?: [])
            ->map(static function (DriveFile $file): array {
                return [
                    'id' => $file->id ?? null,
                    'name' => $file->name ?? null,
                    'size' => isset($file->size) ? (int) $file->size : null,
                    'createdTime' => $file->createdTime ?? null,
                    'webViewLink' => $file->webViewLink ?? null,
                    'webContentLink' => $file->webContentLink ?? null,
                ];
            })
            ->values();
    }

    private function deleteFile(string $fileId): bool
    {
        try {
            $this->drive()->files->delete($fileId, [
                'supportsAllDrives' => true,
            ]);
        } catch (GoogleServiceException $e) {
            throw new RuntimeException('Unable to delete Google Drive file "'.$fileId.'": '.$this->extractGoogleErrorMessage($e), previous: $e);
        }

        return true;
    }

    private function extractGoogleErrorMessage(Throwable $e): string
    {
        if ($e instanceof GoogleServiceException) {
            $errors = $e->getErrors();

            if (! empty($errors) && isset($errors[0]['message'])) {
                return (string) $errors[0]['message'];
            }
        }

        return $e->getMessage();
    }

    private function uploadFile(string $zipPath, string $backupName): array
    {
        return $this->uploadBackup($zipPath, $backupName);
    }

    private function backupNameFor(Carbon $timestamp): string
    {
        return 'database_'.$timestamp->format('Y_m_d_H_i_s').'.sql.zip';
    }

    private function ensureDirectories(): void
    {
        File::ensureDirectoryExists($this->backupDirectory());
        File::ensureDirectoryExists($this->tempDirectory());
    }

    private function backupDirectory(): string
    {
        return storage_path('app/'.self::ROOT_DIRECTORY);
    }

    private function tempDirectory(): string
    {
        return storage_path('app/'.trim((string) config('google-drive-backup.temp_directory', 'google-drive-backups/tmp'), '/\\'));
    }

    private function tempPath(string $filename): string
    {
        return $this->tempDirectory().DIRECTORY_SEPARATOR.$filename;
    }

    /**
     * Dump the current MySQL database to a SQL file.
     */
    private function dumpDatabaseToSqlFile(string $sqlPath, string $sqlFileName): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Google Drive backups currently support MySQL databases only.');
        }

        $handle = fopen($sqlPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open the backup file for writing.');
        }

        try {
            $this->writeSqlHeader($handle, $sqlFileName);
            $this->dumpMysqlDatabase($handle);
            fwrite($handle, "\n-- Backup completed at ".now()->toDateTimeString()."\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     */
    private function writeSqlHeader($handle, string $sqlFileName): void
    {
        fwrite($handle, "-- Database backup generated by ".config('app.name', 'Laravel')."\n");
        fwrite($handle, "-- File: ".$sqlFileName."\n");
        fwrite($handle, "-- Generated at ".now()->toDateTimeString()."\n\n");
    }

    /**
     * @param resource $handle
     */
    private function dumpMysqlDatabase($handle): void
    {
        $connection = DB::connection();
        $pdo = $connection->getPdo();

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($handle, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

        foreach ($this->mysqlObjects('BASE TABLE') as $tableName) {
            $quotedTable = $this->quoteIdentifier($tableName);

            fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");

            $createRow = $connection->selectOne('SHOW CREATE TABLE '.$quotedTable);
            $createSql = $this->extractMysqlCreateStatement($createRow, 'Table');

            if (! $createSql) {
                throw new RuntimeException("Unable to read CREATE TABLE statement for {$tableName}.");
            }

            fwrite($handle, $createSql.";\n\n");
            $this->dumpMysqlTableRows($handle, $tableName, $quotedTable, $pdo);
        }

        foreach ($this->mysqlObjects('VIEW') as $viewName) {
            $quotedView = $this->quoteIdentifier($viewName);

            fwrite($handle, "DROP VIEW IF EXISTS {$quotedView};\n");

            $createRow = $connection->selectOne('SHOW CREATE VIEW '.$quotedView);
            $createSql = $this->extractMysqlCreateStatement($createRow, 'View');

            if ($createSql) {
                fwrite($handle, $createSql.";\n\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
    }

    /**
     * @return array<int, string>
     */
    private function mysqlObjects(string $tableType): array
    {
        $rows = DB::select('SHOW FULL TABLES WHERE Table_type = ?', [$tableType]);
        $objects = [];

        foreach ($rows as $row) {
            $values = array_values((array) $row);

            if (! isset($values[0])) {
                continue;
            }

            $objects[] = (string) $values[0];
        }

        sort($objects);

        return $objects;
    }

    private function extractMysqlCreateStatement(?object $row, string $preferredKey): ?string
    {
        if (! $row) {
            return null;
        }

        $array = (array) $row;

        if (isset($array['Create '.$preferredKey])) {
            return (string) $array['Create '.$preferredKey];
        }

        $values = array_values($array);

        return isset($values[1]) ? (string) $values[1] : null;
    }

    /**
     * @param resource $handle
     */
    private function dumpMysqlTableRows($handle, string $tableName, string $quotedTable, mixed $pdo): void
    {
        $columns = DB::select('SHOW COLUMNS FROM '.$quotedTable);
        $columnNames = array_map(
            static fn ($column) => (string) ($column->Field ?? ''),
            $columns
        );
        $columnNames = array_values(array_filter($columnNames, static fn (string $column) => $column !== ''));

        if ($columnNames === []) {
            return;
        }

        $insertColumns = implode(', ', array_map(fn (string $column) => $this->quoteIdentifier($column), $columnNames));
        $batch = [];
        $batchSize = 100;

        foreach (DB::table($tableName)->cursor() as $row) {
            $values = [];

            foreach ($columnNames as $columnName) {
                $values[] = $this->formatSqlValue($row->{$columnName} ?? null, $pdo);
            }

            $batch[] = '(' . implode(', ', $values) . ')';

            if (count($batch) >= $batchSize) {
                fwrite($handle, "INSERT INTO {$quotedTable} ({$insertColumns}) VALUES\n".implode(",\n", $batch).";\n");
                $batch = [];
            }
        }

        if ($batch !== []) {
            fwrite($handle, "INSERT INTO {$quotedTable} ({$insertColumns}) VALUES\n".implode(",\n", $batch).";\n");
        }

        fwrite($handle, "\n");
    }

    private function createZipArchive(string $zipPath, string $sqlPath, string $sqlFileName): void
    {
        $zip = new ZipArchive();
        $result = $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            throw new RuntimeException('Unable to create backup archive.');
        }

        try {
            if (! $zip->addFile($sqlPath, $sqlFileName)) {
                throw new RuntimeException('Unable to add SQL dump to the archive.');
            }
        } finally {
            $zip->close();
        }
    }

    private function uploadWithRetry(string $zipPath, string $backupName): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= self::MAX_UPLOAD_ATTEMPTS; $attempt++) {
            try {
                $response = $this->uploadFile($zipPath, $backupName);

                $fileId = $this->getUploadedFileId($response);

                if ($fileId) {
                    $this->checkUploadStatus($fileId);
                }

                return $response;
            } catch (Throwable $e) {
                $lastException = $e;

                Log::channel($this->logChannel())->warning('Google Drive backup upload attempt failed.', [
                    'backup_name' => $backupName,
                    'attempt' => $attempt,
                    'message' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                if ($attempt < self::MAX_UPLOAD_ATTEMPTS) {
                    usleep((int) (pow(2, $attempt - 1) * 500000));
                    continue;
                }
            }
        }

        throw $lastException ?? new RuntimeException('Google Drive upload failed.');
    }

    /**
     * Keep only the newest Google Drive backups.
     */
    private function cleanupOldRemoteBackups(): int
    {
        $limit = max(1, (int) config('google-drive-backup.retention_limit', 30));

        try {
            $backups = $this->listBackups(1000);
            $olderBackups = $backups->slice($limit)->values();
            $deleted = 0;

            foreach ($olderBackups as $backup) {
                if (! empty($backup['id']) && $this->deleteFile((string) $backup['id'])) {
                    $deleted++;
                }
            }

            return $deleted;
        } catch (Throwable $e) {
            Log::channel($this->logChannel())->warning('Google Drive backup retention cleanup failed.', [
                'folder_id' => $this->folderId(),
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return 0;
        }
    }

    private function formatSqlValue(mixed $value, mixed $pdo): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if ($value instanceof Carbon) {
            return $this->quoteString($value->toDateTimeString(), $pdo);
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $this->quoteString((string) $value, $pdo);
    }

    private function quoteString(string $value, mixed $pdo): string
    {
        if ($pdo && method_exists($pdo, 'quote')) {
            return (string) $pdo->quote($value);
        }

        return "'".str_replace("'", "''", $value)."'";
    }

    private function quoteIdentifier(string $identifier, string $quote = '`'): string
    {
        return $quote.str_replace($quote, $quote.$quote, $identifier).$quote;
    }

    private function safeDelete(string $path): void
    {
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function isConfigured(): bool
    {
        if (! filled(config('google-drive-backup.folder_id'))) {
            return false;
        }

        if ($this->usesOauth()) {
            return true;
        }

        try {
            return $this->serviceAccountConfig() !== null;
        } catch (Throwable) {
            return false;
        }
    }
}
