<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class LetterheadPdfConverter
{
    /**
     * Rasterize page 1 of an uploaded PDF into a PNG sized for A4 portrait,
     * store it on the given disk, and return its stored path.
     *
     * @throws \RuntimeException if the server cannot rasterize PDFs
     */
    public function convertToImage(UploadedFile $file, string $directory, string $disk = 'public'): string
    {
        if (!extension_loaded('imagick')) {
            throw new \RuntimeException(
                'PDF letterhead upload is not supported on this server yet (the Imagick PHP extension is required). '
                . 'Please upload a PNG or JPG image instead, or ask an administrator to enable Imagick + Ghostscript.'
            );
        }

        $sourcePath = $file->getRealPath();
        if (!$sourcePath || !is_file($sourcePath)) {
            throw new \RuntimeException('Uploaded PDF could not be read.');
        }

        $imagick = new \Imagick();

        try {
            // 150 DPI keeps the rendered letterhead crisp for print while staying a
            // reasonable file size; index [0] rasterizes page 1 only.
            $imagick->setResolution(150, 150);
            $imagick->readImage($sourcePath . '[0]');
        } catch (\ImagickException $e) {
            throw new \RuntimeException('Could not read the uploaded PDF: ' . $e->getMessage());
        }

        $imagick->setImageFormat('png');
        $imagick->setImageBackgroundColor('white');
        $imagick = $imagick->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
        $imagick->setImageCompressionQuality(90);

        $blob = $imagick->getImageBlob();
        $imagick->clear();
        $imagick->destroy();

        $path = trim($directory, '/') . '/' . uniqid('letterhead_', true) . '.png';
        Storage::disk($disk)->put($path, $blob);

        return $path;
    }
}
