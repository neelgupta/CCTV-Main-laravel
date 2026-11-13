<?php

namespace App\Services;

use App\Models\DriveConnection;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Decides where an uploaded file should live using the priority hierarchy:
 *   1. Company connected Google Drive
 *   2. Super Admin Google Drive (fallback)
 *   3. Local/default storage (handled by caller when this returns null)
 *
 * Always uses the currently-active authenticated Drive account. Returns the
 * metadata to persist against the MediaFile, or null when no Drive is available
 * or the upload failed (so the caller keeps the local copy as final fallback).
 */
class DriveStorageResolver
{
    public function __construct(private GoogleDriveClient $drive)
    {
    }

    /**
     * Resolve the active Drive target for a company, honouring the priority order.
     * Returns [DriveConnection, providerType] or null.
     *
     * @return array{0: DriveConnection, 1: string}|null
     */
    public function resolveTarget(int $companyId): ?array
    {
        $company = DriveConnection::activeForCompany($companyId);
        if ($company && filled($company->refresh_token)) {
            return [$company, MediaFile::PROVIDER_COMPANY_DRIVE];
        }

        $superAdmin = DriveConnection::activeForSuperAdmin();
        if ($superAdmin && filled($superAdmin->refresh_token)) {
            return [$superAdmin, MediaFile::PROVIDER_SUPER_ADMIN_DRIVE];
        }

        return null;
    }

    /**
     * Upload the given local file to the resolved Drive (if any).
     *
     * @param  string  $absoluteLocalPath  full filesystem path to the stored file
     * @param  string[]  $subPathSegments   folders under we4work-data, e.g. ['projects','project-a','designs']
     * @return array<string,mixed>|null  metadata for MediaFile, or null to fall back to local
     */
    public function storeToDrive(int $companyId, string $absoluteLocalPath, string $fileName, string $mime, array $subPathSegments): ?array
    {
        $resolved = $this->resolveTarget($companyId);
        if (! $resolved) {
            return null;
        }

        [$connection, $provider] = $resolved;

        try {
            $accessToken = $this->drive->accessTokenFor($connection);

            // Ensure the we4work-data root folder exists (cache its id on the connection).
            $rootId = $connection->root_folder_id;
            if (blank($rootId)) {
                $rootId = $this->drive->ensureFolder($accessToken, $connection->root_folder_name ?: 'we4work-data');
                $connection->update(['root_folder_id' => $rootId]);
            }

            $folderId = $this->drive->ensurePath($accessToken, $subPathSegments, $rootId);
            $uploaded = $this->drive->uploadFile($accessToken, $folderId, $absoluteLocalPath, $fileName, $mime);

            $pathLabel = '/'.trim($connection->root_folder_name ?: 'we4work-data', '/');
            foreach ($subPathSegments as $segment) {
                if (trim((string) $segment) !== '') {
                    $pathLabel .= '/'.trim((string) $segment);
                }
            }
            $pathLabel .= '/'.$fileName;

            return [
                'storage_provider'    => $provider,
                'drive_account_email' => $connection->account_email,
                'drive_account_id'    => $connection->account_id,
                'drive_folder_id'     => $folderId,
                'drive_file_id'       => $uploaded['id'] ?? null,
                'storage_path_label'  => $pathLabel,
                'drive_connection_id' => $connection->id,
            ];
        } catch (Throwable $e) {
            // Never break uploads: log and let the caller keep the local file.
            Log::warning('Drive upload failed, falling back to local storage: '.$e->getMessage(), [
                'company_id'    => $companyId,
                'connection_id' => $connection->id,
                'provider'      => $provider,
            ]);

            return null;
        }
    }
}
