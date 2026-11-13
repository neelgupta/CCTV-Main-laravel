<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Models\Company;
use App\Support\CompanyStorageUsage;
use App\Models\MediaFile;
use Illuminate\Support\Facades\DB;
use App\Exceptions\StorageLimitExceededException;
use App\Services\DriveStorageResolver;

class MediaUploadService
{
    private int $defaultStorageLimitBytes = 104857600;
    private int $maxWidth = 1600;
    private int $maxHeight = 1600;
    private int $minQuality = 58;
    private int $startQuality = 82;

    public function store(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        $this->ensureStorageAvailable($file);
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());

        if ($this->isCompressibleImage($extension, $mime)) {
            return $this->storeCompressedImage($file, $directory, $disk, $extension, $mime);
        }

        $path = $file->store($directory, $disk);
        $this->registerMediaFile($file, $path, $disk, $directory);

        return $path;
    }

    public function delete(string $path, string $disk = 'public', ?int $companyId = null): void
    {
        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');

        if (Storage::disk($disk)->exists($normalizedPath)) {
            Storage::disk($disk)->delete($normalizedPath);
        }

        DB::transaction(function () use ($normalizedPath, $disk, $companyId) {
            $query = MediaFile::query()
                ->where('path', $normalizedPath)
                ->where('disk', $disk);

            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            }

            $mediaFiles = $query->get();

            foreach ($mediaFiles as $mediaFile) {
                $this->purgeReferencesByPath($normalizedPath, (int) $mediaFile->company_id);
                $mediaFile->update(['storage_deleted_at' => now(), 'gallery_visible' => false]);
                $mediaFile->delete();
            }
        });
    }

    private function ensureStorageAvailable(UploadedFile $file): void
    {
        $user = auth()->user();
        if (! $user || $user->isSuperAdmin() || ! $user->company_id) {
            return;
        }

        $limitBytes = app(SubscriptionLimitService::class)->storageLimitBytes((int) $user->company_id);
        $usedBytes = app(CompanyStorageUsage::class)->usedBytes((int) $user->company_id);
        $incomingBytes = (int) $file->getSize();

        if (($usedBytes + $incomingBytes) > $limitBytes) {
            throw new StorageLimitExceededException('Your company storage limit has been reached.');
        }
    }

    private function isCompressibleImage(string $extension, string $mime): bool
    {
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            || in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true);
    }

    private function storeCompressedImage(UploadedFile $file, string $directory, string $disk, string $extension, string $mime): string
    {
        $sourcePath = $file->getRealPath();
        if (!$sourcePath || !is_file($sourcePath)) {
            return $file->store($directory, $disk);
        }

        $image = null;
        $sourceType = @exif_imagetype($sourcePath) ?: null;

        if ($mime === 'image/png' || $extension === 'png' || $sourceType === IMAGETYPE_PNG) {
            $image = @imagecreatefrompng($sourcePath);
        } elseif ($mime === 'image/webp' || $extension === 'webp' || $sourceType === IMAGETYPE_WEBP) {
            $image = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null;
        } else {
            $image = @imagecreatefromjpeg($sourcePath);
        }

        if (!$image) {
            $path = $file->store($directory, $disk);
            $this->registerMediaFile($file, $path, $disk, $directory);
            return $path;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min($this->maxWidth / max($width, 1), $this->maxHeight / max($height, 1), 1);
        $targetWidth = (int) max(1, floor($width * $ratio));
        $targetHeight = (int) max(1, floor($height * $ratio));

        $resized = imagecreatetruecolor($targetWidth, $targetHeight);

        if ($sourceType === IMAGETYPE_PNG || $mime === 'image/png' || $extension === 'png') {
            imagealphablending($resized, false);
            imagesavealpha($resized, true);
            $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
            imagefilledrectangle($resized, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled($resized, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        $path = trim($directory, '/') . '/' . uniqid('media_', true) . '.jpg';
        $tmpPath = storage_path('app/temp_' . uniqid('img_', true) . '.jpg');

        $quality = $this->startQuality;
        $written = false;

        while ($quality >= $this->minQuality) {
            imagejpeg($resized, $tmpPath, $quality);
            clearstatcache(true, $tmpPath);

            if (filesize($tmpPath) <= 1024 * 1024 || $quality === $this->minQuality) {
                $written = true;
                break;
            }

            $quality -= 8;
        }

        imagedestroy($image);
        imagedestroy($resized);

        if (!$written || !is_file($tmpPath)) {
            $path = $file->store($directory, $disk);
            $this->registerMediaFile($file, $path, $disk, $directory);
            return $path;
        }

        Storage::disk($disk)->put($path, file_get_contents($tmpPath));
        @unlink($tmpPath);
        $this->registerMediaFile($file, $path, $disk, $directory);

        return $path;
    }

    private function registerMediaFile(UploadedFile $file, string $path, string $disk, string $directory): void
    {
        $user = auth()->user();
        $segments = array_values(array_filter(explode('/', trim($directory, '/'))));
        $moduleKey = collect($segments)
            ->first(fn ($segment) => !str_starts_with($segment, 'company_')) ?? ($segments[0] ?? null);

        $module = match ($moduleKey) {
            'quotations', 'surveys', 'documents' => 'leads',
            'staff_profiles', 'staff_docs' => 'staff',
            'projects', 'drawings', 'materials', 'production', 'challans' => 'projects',
            default => $moduleKey,
        };

        $companyId = $user?->company_id ? (int) $user->company_id : null;

        // Default: keep the locally-stored copy as the served file.
        $storageMeta = [
            'storage_provider'   => MediaFile::PROVIDER_LOCAL,
            'storage_path_label' => '/'.ltrim($path, '/'),
            'upload_source'      => $module,
            'uploaded_at'        => now(),
        ];

        // Priority: Company Drive -> Super Admin Drive -> local (handled by null return).
        if ($companyId) {
            $driveMeta = $this->pushToDrive($companyId, $file, $path, $disk, $directory);
            if ($driveMeta) {
                $storageMeta = array_merge($storageMeta, $driveMeta);
            }
        }

        MediaFile::create(array_merge([
            'company_id' => $user?->company_id,
            'module' => $module,
            'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'gallery_visible' => true,
            'disk' => $disk,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
        ], $storageMeta));
    }

    /**
     * Mirror the stored file to the resolved Google Drive (if connected) and
     * return its storage metadata, or null to keep the local copy as fallback.
     *
     * @return array<string,mixed>|null
     */
    private function pushToDrive(int $companyId, UploadedFile $file, string $path, string $disk, string $directory): ?array
    {
        $segments = collect(explode('/', trim($directory, '/')))
            ->map(fn ($s) => trim((string) $s))
            ->filter(fn ($s) => $s !== '' && ! str_starts_with($s, 'company_'))
            ->values()
            ->all();

        $absolutePath = Storage::disk($disk)->path($path);
        $fileName     = basename($path);
        $mime         = (string) ($file->getMimeType() ?: 'application/octet-stream');

        return app(DriveStorageResolver::class)
            ->storeToDrive($companyId, $absolutePath, $fileName, $mime, $segments);
    }

    private function purgeReferencesByPath(string $normalizedPath, int $companyId): void
    {
        $path = ltrim(str_replace('\\', '/', $normalizedPath), '/');

        \App\Models\LeadSurvey::query()
            ->where('photo_path', $path)
            ->whereHas('lead', fn ($query) => $query->where('company_id', $companyId))
            ->delete();

        \App\Models\LeadDocument::query()
            ->where('file_path', $path)
            ->whereHas('lead', fn ($query) => $query->where('company_id', $companyId))
            ->delete();

        \App\Models\LeadQuotation::query()
            ->where('file_path', $path)
            ->whereHas('lead', fn ($query) => $query->where('company_id', $companyId))
            ->delete();

        \App\Models\ProjectDrawing::query()
            ->where('file_path', $path)
            ->whereHas('project', fn ($query) => $query->where('company_id', $companyId))
            ->delete();

        \App\Models\ProjectMaterial::query()
            ->where('attachment_path', $path)
            ->whereHas('project', fn ($query) => $query->where('company_id', $companyId))
            ->delete();

        \App\Models\ProjectProduction::query()
            ->where('attachment_path', $path)
            ->whereHas('project', fn ($query) => $query->where('company_id', $companyId))
            ->delete();

        \App\Models\StaffDocument::query()
            ->where('document_path', $path)
            ->whereHas('staff', fn ($query) => $query->where('company_id', $companyId))
            ->delete();

        \App\Models\Staff::query()
            ->where('company_id', $companyId)
            ->where('profile_photo', $path)
            ->update(['profile_photo' => null]);

        \App\Models\User::query()
            ->where('company_id', $companyId)
            ->where('profile_photo', $path)
            ->update(['profile_photo' => null]);

        $company = Company::query()->find($companyId);
        if ($company) {
            $updated = false;
            if ($company->logo === $path) {
                $company->logo = null;
                $updated = true;
            }

            if ($company->favicon === $path) {
                $company->favicon = null;
                $updated = true;
            }

            if ($updated) {
                $company->save();
            }
        }
    }
}
