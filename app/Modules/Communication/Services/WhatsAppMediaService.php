<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services;

use App\Modules\Communication\Models\IntegrationAccount;
use App\Modules\Communication\Support\Logger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

class WhatsAppMediaService
{
    protected string $apiVersion;
    protected string $baseUrl;
    protected int $timeout;

    public function __construct()
    {
        $this->apiVersion = config('communication.providers.meta_whatsapp.api_version', 'v20.0');
        $this->baseUrl = config('communication.providers.meta_whatsapp.base_url', 'https://graph.facebook.com');
        $this->timeout = (int) config('communication.providers.meta_whatsapp.timeout', 15);
    }

    /**
     * Download media from Meta and store it on the configured disk.
     */
    public function downloadAndStore(string $mediaId, IntegrationAccount $account): ?string
    {
        try {
            Logger::info("Starting WhatsApp media download for Media ID: {$mediaId}");

            // 1. Fetch media details from Meta (to get download URL and MIME type)
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$mediaId}";
            $response = Http::withToken($account->access_token)
                ->timeout($this->timeout)
                ->get($url);

            if ($response->failed()) {
                Logger::error("Failed to fetch media details from Meta for ID: {$mediaId}. Status: {$response->status()}, Body: {$response->body()}");
                return null;
            }

            $downloadUrl = $response->json('url');
            $mimeType = $response->json('mime_type');

            if (!$downloadUrl) {
                Logger::error("No download URL found in Meta response for Media ID: {$mediaId}");
                return null;
            }

            // 2. Fetch the raw media bytes
            $fileResponse = Http::withToken($account->access_token)
                ->timeout($this->timeout)
                ->get($downloadUrl);

            if ($fileResponse->failed()) {
                Logger::error("Failed to download media bytes from Meta URL. Status: {$fileResponse->status()}");
                return null;
            }

            $fileContent = $fileResponse->body();

            // 3. Resolve file extension
            $extension = $this->getExtensionFromMimeType($mimeType);

            // 4. Generate dynamic path: whatsapp/company_{id}/media_id.ext
            $diskName = config('communication.media_disk', 'public');
            $path = "whatsapp/company_{$account->company_id}/{$mediaId}.{$extension}";

            // 5. Store the file on the configured disk
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk($diskName);
            $disk->put($path, $fileContent);
            Logger::info("WhatsApp media saved successfully. Path: {$path} on Disk: {$diskName}");

            // 6. Return public/storage URL
            return $disk->url($path);
        } catch (Throwable $e) {
            Logger::error("Exception occurred during WhatsApp media download/store: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get file extension from common MIME types.
     */
    protected function getExtensionFromMimeType(?string $mimeType): string
    {
        if (!$mimeType) {
            return 'bin';
        }

        $map = [
            // Images
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',

            // Audio
            'audio/aac' => 'aac',
            'audio/mp4' => 'mp4',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/amr' => 'amr',

            // Video
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',

            // Documents / Spreadsheets / Presentations
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'text/plain' => 'txt',
        ];

        return $map[$mimeType] ?? 'bin';
    }
}
