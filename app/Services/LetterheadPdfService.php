<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Company;
use App\Models\LetterheadSetting;
use Barryvdh\DomPDF\PDF as DompdfPdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\Fpdi;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LetterheadPdfService
{
    /**
     * @var array<string, ?LetterheadSetting>
     */
    private array $activeLetterheadCache = [];

    public function getActiveLetterhead(?Company $company = null): ?LetterheadSetting
    {
        $company ??= auth()->user()?->company;

        if (! $company) {
            return null;
        }

        $cacheKey = (string) $company->id;
        if (array_key_exists($cacheKey, $this->activeLetterheadCache)) {
            return $this->activeLetterheadCache[$cacheKey];
        }

        $letterhead = LetterheadSetting::query()
            ->where('company_id', $company->id)
            ->where('enabled', true)
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->orderByDesc('id')
            ->first();

        if ($letterhead) {
            return $this->activeLetterheadCache[$cacheKey] = $letterhead;
        }

        return $this->activeLetterheadCache[$cacheKey] = LetterheadSetting::query()
            ->whereNull('company_id')
            ->where('enabled', true)
            ->whereNotNull('file_path')
            ->where('file_path', '!=', '')
            ->orderByDesc('id')
            ->first();
    }

    public function activeLetterhead(?Company $company = null): ?LetterheadSetting
    {
        return $this->getActiveLetterhead($company);
    }

    public function hasActiveLetterhead(?Company $company = null): bool
    {
        return $this->getActiveLetterhead($company) !== null;
    }

    public function isEnabled(?Company $company = null): bool
    {
        return $this->hasActiveLetterhead($company);
    }

    public function getMargins(?LetterheadSetting $letterhead = null): array
    {
        $defaults = LetterheadSetting::defaultMargins();

        if (! $letterhead) {
            return $defaults;
        }

        return [
            'top_margin' => (float) ($letterhead->top_margin ?? $defaults['top_margin']),
            'bottom_margin' => (float) ($letterhead->bottom_margin ?? $defaults['bottom_margin']),
            'left_margin' => (float) ($letterhead->left_margin ?? $defaults['left_margin']),
            'right_margin' => (float) ($letterhead->right_margin ?? $defaults['right_margin']),
        ];
    }

    public function applyToBinary(string $pdfBinary, ?Company $company = null): string
    {
        return $this->applyBackground($pdfBinary, $company);
    }

    public function applyBackground(string $pdfBinary, ?Company $company = null, ?LetterheadSetting $letterhead = null): string
    {
        return $this->applyLetterheadBackground($pdfBinary, $company, $letterhead);
    }

    public function applyLetterheadBackground(string $pdfBinary, ?Company $company = null, ?LetterheadSetting $letterhead = null, bool $contentAlreadyUsesMargins = false, bool $contentIsPrintableArea = false): string
    {
        $letterhead ??= $this->getActiveLetterhead($company);

        if (! $letterhead || ! filled($letterhead->file_path)) {
            return $pdfBinary;
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($letterhead->file_path)) {
            logger()->warning('PDF letterhead file is missing; returning the original document.', [
                'letterhead_id' => $letterhead->id,
                'file_path' => $letterhead->file_path,
            ]);

            return $pdfBinary;
        }

        try {
            return $this->renderBackground(
                $pdfBinary,
                $disk->path($letterhead->file_path),
                $this->getMargins($letterhead),
                $contentAlreadyUsesMargins,
                $contentIsPrintableArea
            );
        } catch (Throwable $e) {
            logger()->warning('Failed to apply PDF letterhead background, returning the original document.', [
                'letterhead_id' => $letterhead->id,
                'file_path' => $letterhead->file_path,
                'error' => $e->getMessage(),
            ]);

            return $pdfBinary;
        }
    }

    public function renderBackground(string $pdfBinary, string $letterheadPath, ?array $margins = null, bool $contentAlreadyUsesMargins = false, bool $contentIsPrintableArea = false): string
    {
        if ($letterheadPath === '' || ! is_file($letterheadPath)) {
            return $pdfBinary;
        }

        try {
            return $this->renderWithLetterhead(
                $pdfBinary,
                $letterheadPath,
                $margins ?? LetterheadSetting::defaultMargins(),
                $contentAlreadyUsesMargins,
                $contentIsPrintableArea
            );
        } catch (Throwable $e) {
            logger()->warning('Failed to render PDF letterhead background, returning the original document.', [
                'file_path' => $letterheadPath,
                'error' => $e->getMessage(),
            ]);

            return $pdfBinary;
        }
    }

    public function applyToBinaryFromPath(string $pdfBinary, string $letterheadPath, ?array $margins = null): string
    {
        return $this->renderBackground($pdfBinary, $letterheadPath, $margins);
    }

    public function download(DompdfPdf $pdf, string $fileName, ?Company $company = null, bool $inline = false, bool $contentAlreadyUsesMargins = false): Response
    {
        $pdfBinary = $this->applyLetterheadBackground($pdf->output(), $company, null, $contentAlreadyUsesMargins);

        $response = $inline
            ? response($pdfBinary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ])
            : response()->streamDownload(
                static function () use ($pdfBinary): void {
                    echo $pdfBinary;
                },
                $fileName,
                ['Content-Type' => 'application/pdf']
            );

        return $response;
    }

    public function prepareUploadedLetterhead(UploadedFile $file): array
    {
        $sourcePath = $file->getRealPath();

        if (! is_string($sourcePath) || ! is_file($sourcePath)) {
            throw new \RuntimeException('Uploaded letterhead file is not available.');
        }

        $reader = StreamReader::createByFile($sourcePath);
        $pdf = new Fpdi();

        $pageCount = $pdf->setSourceFile($reader);
        if ($pageCount < 1) {
            throw new \RuntimeException('The uploaded PDF does not contain any pages.');
        }

        $firstPageId = $pdf->importPage(1);
        $pageSize = $pdf->getTemplateSize($firstPageId);
        $pageWidth = (float) ($pageSize['width'] ?? 0);
        $pageHeight = (float) ($pageSize['height'] ?? 0);
        if ($pageWidth <= 0 || $pageHeight <= 0) {
            throw new \RuntimeException('Unable to determine the letterhead page size.');
        }

        $orientation = $pageSize['orientation'] ?? ($pageWidth > $pageHeight ? 'L' : 'P');
        $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
        $pdf->useTemplate($firstPageId, 0, 0, $pageWidth, $pageHeight);

        return [
            'binary' => $pdf->Output('', 'S'),
            'metadata' => [
                'source_filename' => $file->getClientOriginalName(),
                'page_count' => $pageCount,
                'page_width_mm' => $pageWidth,
                'page_height_mm' => $pageHeight,
                'page_orientation' => $orientation,
            ],
        ];
    }

    /**
     * Overlay each content page onto the letterhead page, positioning the
     * content inside the printable rectangle defined by the configured
     * margins (page size minus top/bottom/left/right margin, in mm).
     *
     * The content is scaled uniformly (aspect ratio preserved, never
     * distorted) so it fits entirely within that printable rectangle, then
     * offset by (left_margin, top_margin) so it never overlaps the
     * letterhead's header/footer artwork outside the margins. The letterhead
     * page itself is always stamped at its native size and is never resized.
     */
    private function renderWithLetterhead(string $pdfBinary, string $letterheadPath, array $margins, bool $contentAlreadyUsesMargins = false, bool $contentIsPrintableArea = false): string
    {
        $pdf = new Fpdi();

        $letterheadReader = StreamReader::createByFile($letterheadPath);
        $pdf->setSourceFile($letterheadReader);
        $letterheadTemplateId = $pdf->importPage(1);
        $letterheadSize = $pdf->getTemplateSize($letterheadTemplateId);
        $pageWidth = (float) ($letterheadSize['width'] ?? 0);
        $pageHeight = (float) ($letterheadSize['height'] ?? 0);
        $pageOrientation = $letterheadSize['orientation'] ?? ($pageWidth > $pageHeight ? 'L' : 'P');

        $topMargin = (float) ($margins['top_margin'] ?? 0);
        $bottomMargin = (float) ($margins['bottom_margin'] ?? 0);
        $leftMargin = (float) ($margins['left_margin'] ?? 0);
        $rightMargin = (float) ($margins['right_margin'] ?? 0);

        $printableWidth = max(1.0, $pageWidth - $leftMargin - $rightMargin);
        $printableHeight = max(1.0, $pageHeight - $topMargin - $bottomMargin);

        $contentReader = StreamReader::createByString($pdfBinary);
        $pageCount = $pdf->setSourceFile($contentReader);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $contentTemplateId = $pdf->importPage($pageNumber);

            $contentNativeSize = $pdf->getTemplateSize($contentTemplateId);
            $contentNativeWidth = max(1.0, (float) ($contentNativeSize['width'] ?? $printableWidth));
            $contentNativeHeight = max(1.0, (float) ($contentNativeSize['height'] ?? $printableHeight));
            $contentOrientation = $contentNativeSize['orientation'] ?? ($pageWidth > $pageHeight ? 'L' : 'P');

            if ($contentIsPrintableArea) {
                $renderedWidth = $printableWidth;
                $renderedHeight = $printableHeight;
                $contentX = $leftMargin;
                $contentY = $topMargin;
            } elseif ($contentAlreadyUsesMargins) {
                $renderedWidth = $pageWidth;
                $renderedHeight = $pageHeight;
                $contentX = 0.0;
                $contentY = 0.0;
            } else {
                [$renderedWidth, $renderedHeight] = $this->fitWithinPrintableArea(
                    $contentNativeWidth,
                    $contentNativeHeight,
                    $printableWidth,
                    $printableHeight
                );
                $contentX = $leftMargin + (($printableWidth - $renderedWidth) / 2);
                $contentY = $topMargin;
            }

            $pdf->AddPage($contentOrientation ?: $pageOrientation, [$pageWidth, $pageHeight]);
            $pdf->useTemplate($letterheadTemplateId, 0, 0, $pageWidth, $pageHeight);
            $pdf->useTemplate($contentTemplateId, $contentX, $contentY, $renderedWidth, $renderedHeight);
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Scale (nativeWidth, nativeHeight) down uniformly, if needed, so it fits
     * entirely inside (printableWidth, printableHeight). Scaling by width
     * alone ignores bottom_margin entirely, letting tall or asymmetric
     * margins push content past the printable area and into the
     * letterhead's footer artwork - so both dimensions are checked.
     *
     * @return array{0: float, 1: float} [renderedWidth, renderedHeight]
     */
    private function fitWithinPrintableArea(
        float $nativeWidth,
        float $nativeHeight,
        float $printableWidth,
        float $printableHeight
    ): array {
        $nativeWidth = max(1.0, $nativeWidth);
        $nativeHeight = max(1.0, $nativeHeight);

        $scale = min(1.0, $printableWidth / $nativeWidth, $printableHeight / $nativeHeight);

        return [
            max(1.0, $nativeWidth * $scale),
            max(1.0, $nativeHeight * $scale),
        ];
    }
}
