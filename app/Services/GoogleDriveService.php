<?php

namespace App\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Google\Service\Exception as GoogleServiceException;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Reusable Google Drive v3 API wrapper, authenticated via a Google Service Account.
 *
 * The service account's client email must be shared as "Editor" on the target
 * Drive folder before uploads or folder access checks will succeed.
 */
class GoogleDriveService
{
    private ?Drive $drive = null;

    public function isConfigured(): bool
    {
        $serviceAccountConfig = $this->serviceAccountConfig();

        return $serviceAccountConfig !== null
            && filled(config('google-drive-backup.folder_id'));
    }

    /**
     * Verify the configured service account can see and access the given folder.
     *
     * @return array<string, mixed>
     */
    public function verifyFolderAccess(string $folderId): array
    {
        try {
            $file = $this->drive()->files->get($folderId, [
                'fields' => 'id,name,mimeType,webViewLink',
                'supportsAllDrives' => true,
            ]);
        } catch (GoogleServiceException $e) {
            throw new RuntimeException(
                'Google Drive folder access denied. Make sure the service account email is shared as Editor on folder "'.$folderId.'". '
                .$this->extractGoogleErrorMessage($e),
                previous: $e
            );
        }

        return [
            'id' => $file->id ?? null,
            'name' => $file->name ?? null,
            'mime_type' => $file->mimeType ?? null,
            'web_view_link' => $file->webViewLink ?? null,
        ];
    }

    /**
     * Upload a local file to Google Drive and return the API response metadata.
     *
     * @return array<string, mixed>
     */
    public function uploadFile(string $folderId, string $absolutePath, string $name, string $mimeType = 'application/zip'): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Local file not found for Google Drive upload: '.$absolutePath);
        }

        $payload = file_get_contents($absolutePath);

        if ($payload === false) {
            throw new RuntimeException('Unable to read the local backup file for Google Drive upload.');
        }

        $metadata = new DriveFile([
            'name' => $name,
            'parents' => [$folderId],
        ]);

        try {
            $response = $this->drive()->files->create($metadata, [
                'data' => $payload,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,name,webViewLink,webContentLink,size,createdTime',
                'supportsAllDrives' => true,
            ]);
        } catch (GoogleServiceException $e) {
            throw new RuntimeException('Google Drive upload failed: '.$this->extractGoogleErrorMessage($e), previous: $e);
        }

        return [
            'id' => $response->id ?? null,
            'name' => $response->name ?? $name,
            'webViewLink' => $response->webViewLink ?? null,
            'webContentLink' => $response->webContentLink ?? null,
            'size' => $response->size ?? null,
            'createdTime' => $response->createdTime ?? null,
        ];
    }

    public function deleteFile(string $fileId): bool
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

    /**
     * List database backup files in a folder, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listBackups(string $folderId, int $limit = 1000): Collection
    {
        try {
            $response = $this->drive()->files->listFiles([
                'q' => sprintf(
                    "'%s' in parents and trashed = false and name contains 'database_' and name contains '.sql.zip'",
                    str_replace("'", "\\'", $folderId)
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
            ->map(function (DriveFile $file): array {
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

    /**
     * Check whether a file still exists and return its metadata.
     *
     * @return array<string, mixed>
     */
    public function checkUploadStatus(string $fileId): array
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

    /**
     * Return the uploaded file identifier from a Drive API upload payload.
     */
    public function getUploadedFileId(array $response): ?string
    {
        return isset($response['id']) ? (string) $response['id'] : null;
    }

    private function drive(): Drive
    {
        if ($this->drive instanceof Drive) {
            return $this->drive;
        }

        $serviceAccountConfig = $this->serviceAccountConfig();

        if ($serviceAccountConfig === null) {
            throw new RuntimeException('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON is not configured.');
        }

        if (is_string($serviceAccountConfig)) {
            if (! is_file($serviceAccountConfig)) {
                throw new RuntimeException('Google Service Account JSON file not found at: '.$serviceAccountConfig);
            }

            $decoded = json_decode((string) file_get_contents($serviceAccountConfig), true);
        } else {
            $decoded = $serviceAccountConfig;
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The configured Google Service Account JSON file is invalid JSON.');
        }

        if (($decoded['type'] ?? null) !== 'service_account') {
            if (isset($decoded['web'])) {
                throw new RuntimeException('The configured Google credential is an OAuth web client JSON, not a service account key. Download a Service Account JSON file from Google Cloud instead.');
            }

            throw new RuntimeException('The configured Google Service Account JSON file is invalid or is not a service account key.');
        }

        try {
            $client = new Client();
            $client->setApplicationName(config('app.name', 'Laravel'));
            $client->setAuthConfig($decoded);
            $client->setScopes([
                Drive::DRIVE,
            ]);

            $this->drive = new Drive($client);
        } catch (Throwable $e) {
            throw new RuntimeException('Unable to authenticate with Google Drive using the service account: '.$e->getMessage(), previous: $e);
        }

        return $this->drive;
    }

    /**
     * @return array<string, mixed>|string|null
     */
    private function serviceAccountConfig(): array|string|null
    {
        $configured = trim((string) config('google-drive-backup.service_account_json', ''));

        if ($configured === '') {
            return null;
        }

        $decoded = json_decode($configured, true);

        if (is_array($decoded) && ($decoded['type'] ?? null) === 'service_account') {
            return $decoded;
        }

        $path = $this->isAbsolutePath($configured) ? $configured : base_path($configured);

        if (is_file($path)) {
            return $path;
        }

        return null;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1;
    }

    private function extractGoogleErrorMessage(GoogleServiceException $e): string
    {
        $errors = $e->getErrors();

        if (! empty($errors) && isset($errors[0]['message'])) {
            return (string) $errors[0]['message'];
        }

        return $e->getMessage();
    }
}
