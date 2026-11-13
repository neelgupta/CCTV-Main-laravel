<?php

namespace App\Services;

use App\Models\DriveConnection;
use App\Models\MediaFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Serves a MediaFile's bytes, preferring its Google Drive copy and falling back
 * to the local copy. Historical files remain accessible even after the Drive
 * account is disconnected/reconnected, because each MediaFile references the
 * specific DriveConnection it was stored against.
 */
class MediaFileStreamer
{
    public function __construct(private GoogleDriveClient $drive)
    {
    }

    public function respond(MediaFile $mediaFile): Response
    {
        $fileName = $mediaFile->original_name ?: basename((string) $mediaFile->path);
        $mime     = $mediaFile->mime_type ?: 'application/octet-stream';

        if ($mediaFile->isOnDrive() && filled($mediaFile->drive_file_id) && filled($mediaFile->drive_connection_id)) {
            $fromDrive = $this->streamFromDrive($mediaFile, $fileName, $mime);
            if ($fromDrive !== null) {
                return $fromDrive;
            }
        }

        return $this->streamFromLocal($mediaFile, $fileName, $mime);
    }

    private function streamFromDrive(MediaFile $mediaFile, string $fileName, string $mime): ?Response
    {
        try {
            // Load by id regardless of status so disconnected (historical) drives still serve.
            $connection = DriveConnection::find($mediaFile->drive_connection_id);
            if (! $connection) {
                return null;
            }

            $token = $this->drive->accessTokenFor($connection);
            $bytes = $this->drive->downloadFile($token, (string) $mediaFile->drive_file_id);

            return response($bytes, 200, [
                'Content-Type'        => $mime,
                'Content-Disposition' => 'inline; filename="'.addslashes($fileName).'"',
                'Content-Length'      => (string) strlen($bytes),
                'X-Storage-Source'    => 'google-drive',
            ]);
        } catch (Throwable $e) {
            Log::warning('Drive stream failed, falling back to local: '.$e->getMessage(), [
                'media_file_id' => $mediaFile->id,
                'drive_file_id' => $mediaFile->drive_file_id,
            ]);

            return null;
        }
    }

    private function streamFromLocal(MediaFile $mediaFile, string $fileName, string $mime): Response
    {
        $disk = $mediaFile->disk ?: 'public';
        $path = $this->normalizePath($mediaFile->path);

        if (! $path || ! Storage::disk($disk)->exists($path)) {
            abort(404);
        }

        return Storage::disk($disk)->response($path, $fileName, [
            'Content-Type'     => $mime,
            'X-Storage-Source' => 'local',
        ]);
    }

    private function normalizePath(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, strlen('public/'));
        }

        return $path !== '' ? $path : null;
    }
}
