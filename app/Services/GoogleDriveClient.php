<?php

namespace App\Services;

use App\Models\DriveConnection;
use App\Models\PlatformIntegrationSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin Google Drive v3 REST client built on Laravel's HTTP client.
 *
 * Works against a DriveConnection (company or super-admin). Access tokens are
 * refreshed transparently using the platform Google OAuth app credentials and
 * the connection's stored refresh token.
 */
class GoogleDriveClient
{
    private const TOKEN_ENDPOINT  = 'https://oauth2.googleapis.com/token';
    private const FILES_ENDPOINT  = 'https://www.googleapis.com/drive/v3/files';
    private const UPLOAD_ENDPOINT = 'https://www.googleapis.com/upload/drive/v3/files';
    private const FOLDER_MIME     = 'application/vnd.google-apps.folder';

    /**
     * Return a valid access token for the connection, refreshing if expired.
     */
    public function accessTokenFor(DriveConnection $connection): string
    {
        if ($connection->access_token && ! $connection->isExpired()) {
            return $connection->access_token;
        }

        if (blank($connection->refresh_token)) {
            throw new RuntimeException('Drive connection has no refresh token; reconnect required.');
        }

        $app = PlatformIntegrationSetting::google();
        if (! $app || ! $app->isConfigured()) {
            throw new RuntimeException('Platform Google OAuth app is not configured.');
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id'     => $app->client_id,
            'client_secret' => $app->client_secret,
            'refresh_token' => $connection->refresh_token,
            'grant_type'    => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Failed to refresh Google Drive token: '.$response->body());
        }

        $data  = $response->json();
        $token = $data['access_token'] ?? null;

        if (! $token) {
            throw new RuntimeException('Google did not return an access token on refresh.');
        }

        $connection->forceFill([
            'access_token'     => $token,
            'token_expires_at' => isset($data['expires_in']) ? now()->addSeconds((int) $data['expires_in']) : null,
        ])->save();

        return $token;
    }

    /**
     * Ensure a folder named $name exists under $parentId (or root). Returns its id.
     */
    public function ensureFolder(string $accessToken, string $name, ?string $parentId = null): string
    {
        $existing = $this->findFolder($accessToken, $name, $parentId);
        if ($existing) {
            return $existing;
        }

        $metadata = [
            'name'     => $name,
            'mimeType' => self::FOLDER_MIME,
        ];
        if ($parentId) {
            $metadata['parents'] = [$parentId];
        }

        $response = Http::withToken($accessToken)
            ->post(self::FILES_ENDPOINT.'?fields=id', $metadata);

        if ($response->failed()) {
            throw new RuntimeException('Failed to create Drive folder "'.$name.'": '.$response->body());
        }

        return $response->json('id');
    }

    /**
     * Ensure the full folder path exists (e.g. we4work-data/projects/project-a),
     * creating each segment as needed. Returns the deepest folder id.
     */
    public function ensurePath(string $accessToken, array $segments, ?string $rootId = null): string
    {
        $parentId = $rootId;

        foreach ($segments as $segment) {
            $segment = trim((string) $segment);
            if ($segment === '') {
                continue;
            }
            $parentId = $this->ensureFolder($accessToken, $segment, $parentId);
        }

        if (! $parentId) {
            throw new RuntimeException('Could not resolve a Drive folder for the given path.');
        }

        return $parentId;
    }

    /**
     * Upload a local file into a Drive folder. Returns ['id' => ..., 'webViewLink' => ...].
     */
    public function uploadFile(string $accessToken, string $folderId, string $absolutePath, string $name, string $mime): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException('Local file not found for Drive upload: '.$absolutePath);
        }

        $metadata = [
            'name'    => $name,
            'parents' => [$folderId],
        ];

        $response = Http::withToken($accessToken)
            ->attach('metadata', json_encode($metadata), 'metadata.json', ['Content-Type' => 'application/json; charset=UTF-8'])
            ->attach('file', file_get_contents($absolutePath), $name, ['Content-Type' => $mime])
            ->post(self::UPLOAD_ENDPOINT.'?uploadType=multipart&fields=id,webViewLink,name');

        if ($response->failed()) {
            throw new RuntimeException('Drive upload failed: '.$response->body());
        }

        return $response->json();
    }

    /**
     * List database backup files in a folder, newest first.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listBackups(string $accessToken, string $folderId, int $limit = 1000): Collection
    {
        $response = Http::withToken($accessToken)->get(self::FILES_ENDPOINT, [
            'q' => sprintf(
                "'%s' in parents and trashed = false and name contains 'database_' and name contains '.sql.zip'",
                str_replace("'", "\\'", $folderId)
            ),
            'orderBy' => 'createdTime desc',
            'pageSize' => min(max($limit, 1), 1000),
            'fields' => 'files(id,name,size,createdTime,webViewLink,webContentLink)',
            'supportsAllDrives' => 'true',
            'includeItemsFromAllDrives' => 'true',
            'spaces' => 'drive',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Unable to list Google Drive backups: '.$response->body());
        }

        return collect($response->json('files', []))
            ->map(static function (array $file): array {
                return [
                    'id' => $file['id'] ?? null,
                    'name' => $file['name'] ?? null,
                    'size' => isset($file['size']) ? (int) $file['size'] : null,
                    'createdTime' => $file['createdTime'] ?? null,
                    'webViewLink' => $file['webViewLink'] ?? null,
                    'webContentLink' => $file['webContentLink'] ?? null,
                ];
            })
            ->values();
    }

    /**
     * Check whether a file still exists and return its metadata.
     *
     * @return array<string, mixed>
     */
    public function checkUploadStatus(string $accessToken, string $fileId): array
    {
        $response = Http::withToken($accessToken)->get(self::FILES_ENDPOINT.'/'.$fileId, [
            'fields' => 'id,name,mimeType,webViewLink,size,createdTime',
            'supportsAllDrives' => 'true',
        ]);

        if ($response->failed()) {
            return [
                'exists' => false,
                'id' => $fileId,
            ];
        }

        return [
            'exists' => true,
            'id' => $response->json('id') ?? $fileId,
            'name' => $response->json('name'),
            'mime_type' => $response->json('mimeType'),
            'web_view_link' => $response->json('webViewLink'),
            'size' => $response->json('size') !== null ? (int) $response->json('size') : null,
            'created_time' => $response->json('createdTime'),
        ];
    }

    public function deleteFile(string $accessToken, string $fileId): bool
    {
        $response = Http::withToken($accessToken)->delete(self::FILES_ENDPOINT.'/'.$fileId, [
            'supportsAllDrives' => 'true',
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Unable to delete Google Drive file "'.$fileId.'": '.$response->body());
        }

        return true;
    }

    /**
     * Download the raw bytes of a Drive file.
     */
    public function downloadFile(string $accessToken, string $fileId): string
    {
        $response = Http::withToken($accessToken)
            ->get(self::FILES_ENDPOINT.'/'.$fileId, ['alt' => 'media']);

        if ($response->failed()) {
            throw new RuntimeException('Drive download failed (HTTP '.$response->status().') for file '.$fileId);
        }

        return $response->body();
    }

    private function findFolder(string $accessToken, string $name, ?string $parentId): ?string
    {
        $escaped = str_replace("'", "\\'", $name);
        $query   = "mimeType='".self::FOLDER_MIME."' and trashed=false and name='".$escaped."'";
        if ($parentId) {
            $query .= " and '".$parentId."' in parents";
        }

        $response = Http::withToken($accessToken)->get(self::FILES_ENDPOINT, [
            'q'      => $query,
            'fields' => 'files(id,name)',
            'spaces' => 'drive',
            'pageSize' => 1,
        ]);

        if ($response->failed()) {
            return null;
        }

        return $response->json('files.0.id');
    }
}
