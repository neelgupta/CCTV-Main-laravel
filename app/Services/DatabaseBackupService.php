<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use ZipArchive;

class DatabaseBackupService
{
    private const BACKUP_DIR = 'backups/database';

    private const TEMP_DIR = 'backups/database/tmp';

    private const RETENTION_LIMIT = 30;

    /**
     * Create a new backup zip and return its metadata.
     *
     * @return array<string, mixed>
     */
    public function createBackup(): array
    {
        $this->ensureDirectories();

        $timestamp = now()->format('Y_m_d_H_i_s');
        $sqlBaseName = 'database_backup_'.$timestamp.'.sql';
        $zipBaseName = 'database_backup_'.$timestamp.'.zip';
        $sqlPath = $this->tempPath($sqlBaseName);
        $zipPath = $this->uniqueBackupPath($zipBaseName);

        Log::info('Database backup started.', [
            'connection' => config('database.default'),
            'database' => config('database.connections.'.config('database.default').'.database'),
            'zip_path' => $zipPath,
        ]);

        try {
            $this->dumpDatabaseToSqlFile($sqlPath);
            $this->createZipArchive($zipPath, $sqlPath, $sqlBaseName);

            $backup = $this->buildBackupMetadata(basename($zipPath));
            $this->pruneOldBackups();

            Log::info('Database backup completed.', [
                'filename' => $backup['filename'],
                'size_bytes' => $backup['size_bytes'],
            ]);

            return $backup;
        } catch (Throwable $e) {
            $this->safeDelete($zipPath);

            Log::error('Database backup failed.', [
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            throw $e;
        } finally {
            $this->safeDelete($sqlPath);
        }
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function listBackups(): Collection
    {
        $this->ensureDirectories();

        return collect(File::files($this->backupDirectory()))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'zip')
            ->map(fn ($file) => $this->buildBackupMetadata($file->getFilename()))
            ->sortByDesc('created_at_timestamp')
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $backups = $this->listBackups();

        return [
            'backup_count' => $backups->count(),
            'latest_backup_time_human' => $backups->first()['backup_date_human'] ?? 'No backups yet',
            'storage_used_bytes' => $backups->sum('size_bytes'),
            'storage_used_human' => $this->formatBytes((int) $backups->sum('size_bytes')),
        ];
    }

    public function backupExists(string $filename): bool
    {
        return File::exists($this->backupPath($this->sanitizeFilename($filename)));
    }

    public function deleteBackup(string $filename): void
    {
        $path = $this->backupPath($this->sanitizeFilename($filename));

        if (! File::exists($path)) {
            abort(404);
        }

        File::delete($path);
    }

    public function downloadPath(string $filename): string
    {
        $path = $this->backupPath($this->sanitizeFilename($filename));

        if (! File::exists($path)) {
            abort(404);
        }

        return $path;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildBackupMetadata(string $filename): array
    {
        $filename = $this->sanitizeFilename($filename);
        $path = $this->backupPath($filename);

        if (! File::exists($path)) {
            abort(404);
        }

        $sizeBytes = (int) File::size($path);
        $lastModified = Carbon::createFromTimestamp((int) File::lastModified($path));
        $backupDate = $this->resolveBackupTimestamp($filename) ?? $lastModified;

        return [
            'filename' => $filename,
            'download_name' => $filename,
            'download_url' => route('super-admin.database-backups.download', ['filename' => $filename]),
            'delete_url' => route('super-admin.database-backups.destroy', ['filename' => $filename]),
            'backup_date' => $backupDate->format('Y-m-d'),
            'backup_date_human' => $backupDate->format('d M Y, h:i A'),
            'created_time' => $lastModified->format('Y-m-d H:i:s'),
            'created_time_human' => $lastModified->format('d M Y, h:i A'),
            'created_at_timestamp' => $lastModified->timestamp,
            'size_bytes' => $sizeBytes,
            'size_human' => $this->formatBytes($sizeBytes),
        ];
    }

    private function ensureDirectories(): void
    {
        File::ensureDirectoryExists($this->backupDirectory());
        File::ensureDirectoryExists($this->tempDirectory());
    }

    private function backupDirectory(): string
    {
        return storage_path('app/'.self::BACKUP_DIR);
    }

    private function tempDirectory(): string
    {
        return storage_path('app/'.self::TEMP_DIR);
    }

    private function backupPath(string $filename): string
    {
        return $this->backupDirectory().DIRECTORY_SEPARATOR.$filename;
    }

    private function tempPath(string $filename): string
    {
        return $this->tempDirectory().DIRECTORY_SEPARATOR.$filename;
    }

    private function uniqueBackupPath(string $filename): string
    {
        $baseName = pathinfo($filename, PATHINFO_FILENAME);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $candidate = $this->backupPath($filename);
        $counter = 1;

        while (File::exists($candidate)) {
            $candidate = $this->backupPath($baseName.'_'.$counter.'.'.$extension);
            $counter++;
        }

        return $candidate;
    }

    private function dumpDatabaseToSqlFile(string $sqlPath): void
    {
        $driver = DB::connection()->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite'], true)) {
            throw new RuntimeException('Database backup is only supported for MySQL, MariaDB, and SQLite in this environment.');
        }

        $handle = fopen($sqlPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Unable to open backup file for writing.');
        }

        try {
            $this->writeSqlHeader($handle);

            match ($driver) {
                'sqlite' => $this->dumpSqliteDatabase($handle),
                default => $this->dumpMysqlDatabase($handle),
            };

            fwrite($handle, "\n-- Backup completed at ".now()->toDateTimeString()."\n");
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param resource $handle
     */
    private function writeSqlHeader($handle): void
    {
        fwrite($handle, "-- Database backup generated by ".config('app.name', 'Laravel')."\n");
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

        $tables = $this->mysqlObjects('BASE TABLE');
        $views = $this->mysqlObjects('VIEW');

        foreach ($tables as $table) {
            $tableName = (string) $table;
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

        foreach ($views as $view) {
            $viewName = (string) $view;
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
            fn ($column) => (string) ($column->Field ?? ''),
            $columns
        );
        $columnNames = array_values(array_filter($columnNames, fn (string $column) => $column !== ''));

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

    /**
     * @param resource $handle
     */
    private function dumpSqliteDatabase($handle): void
    {
        fwrite($handle, "PRAGMA foreign_keys=off;\n\n");

        $tables = DB::select(
            "SELECT name, sql FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
        );

        foreach ($tables as $table) {
            $tableName = (string) ($table->name ?? '');
            $createSql = (string) ($table->sql ?? '');

            if ($tableName === '' || $createSql === '') {
                continue;
            }

            $quotedTable = $this->quoteIdentifier($tableName, '"');
            fwrite($handle, "DROP TABLE IF EXISTS {$quotedTable};\n");
            fwrite($handle, $createSql.";\n\n");

            $columns = DB::select('PRAGMA table_info('.$quotedTable.')');
            $columnNames = array_map(
                fn ($column) => (string) ($column->name ?? ''),
                $columns
            );
            $columnNames = array_values(array_filter($columnNames, fn (string $column) => $column !== ''));

            if ($columnNames === []) {
                continue;
            }

            $insertColumns = implode(', ', array_map(fn (string $column) => $this->quoteIdentifier($column, '"'), $columnNames));
            $batch = [];
            $batchSize = 100;

            foreach (DB::table($tableName)->cursor() as $row) {
                $values = [];

                foreach ($columnNames as $columnName) {
                    $values[] = $this->formatSqlValue($row->{$columnName} ?? null, DB::connection()->getPdo());
                }

                $batch[] = '(' . implode(', ', $values) . ')';

                if (count($batch) >= $batchSize) {
                    fwrite($handle, 'INSERT INTO '.$quotedTable.' ('.$insertColumns.") VALUES\n".implode(",\n", $batch).";\n");
                    $batch = [];
                }
            }

            if ($batch !== []) {
                fwrite($handle, 'INSERT INTO '.$quotedTable.' ('.$insertColumns.") VALUES\n".implode(",\n", $batch).";\n");
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "PRAGMA foreign_keys=on;\n");
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

    private function pruneOldBackups(): void
    {
        $backups = $this->listBackups();

        if ($backups->count() <= self::RETENTION_LIMIT) {
            return;
        }

        $backups
            ->slice(self::RETENTION_LIMIT)
            ->each(function (array $backup): void {
                $path = $this->backupPath($backup['filename']);

                if (File::exists($path)) {
                    File::delete($path);
                }
            });
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

    private function sanitizeFilename(string $filename): string
    {
        $filename = basename(str_replace('\\', '/', $filename));

        if (! preg_match('/^database_backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}(?:_\d+)?\.zip$/', $filename)) {
            abort(404);
        }

        return $filename;
    }

    private function resolveBackupTimestamp(string $filename): ?Carbon
    {
        if (! preg_match('/^database_backup_(\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2})/', $filename, $matches)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y_m_d_H_i_s', $matches[1], config('app.timezone'));
        } catch (Throwable) {
            return null;
        }
    }

    private function safeDelete(string $path): void
    {
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $pow = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $pow);

        return number_format($value, $pow === 0 ? 0 : 2).' '.$units[$pow];
    }
}
