<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LetterheadSetting;
use App\Services\LetterheadPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LetterheadSettingController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('settings.company.index', ['tab' => 'letterhead-settings']);
    }

    public function file(LetterheadPdfService $letterheadPdfService): StreamedResponse
    {
        $company = auth()->user()?->company;
        abort_unless($company, 403);

        $letterhead = $letterheadPdfService->getActiveLetterhead($company);
        abort_unless($letterhead && filled($letterhead->file_path), 404);

        $disk = Storage::disk('public');
        abort_unless($disk->exists($letterhead->file_path), 404);

        $mimeType = $disk->mimeType($letterhead->file_path) ?: 'application/pdf';

        return $disk->response($letterhead->file_path, basename($letterhead->file_path), [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function preview(Request $request, LetterheadPdfService $letterheadPdfService)
    {
        $company = auth()->user()?->company;
        abort_unless($company, 403);

        $activeLetterhead = $letterheadPdfService->getActiveLetterhead($company);

        $validated = $request->validate([
            'letterhead_file' => ['nullable', 'file', 'mimes:pdf', 'max:10240'],
            'top_margin' => ['nullable', 'integer', 'min:0', 'max:100'],
            'bottom_margin' => ['nullable', 'integer', 'min:0', 'max:100'],
            'left_margin' => ['nullable', 'integer', 'min:0', 'max:100'],
            'right_margin' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $previewMargins = [
            'top_margin' => (float) ($validated['top_margin'] ?? $activeLetterhead?->top_margin ?? LetterheadSetting::DEFAULT_TOP_MARGIN),
            'bottom_margin' => (float) ($validated['bottom_margin'] ?? $activeLetterhead?->bottom_margin ?? LetterheadSetting::DEFAULT_BOTTOM_MARGIN),
            'left_margin' => (float) ($validated['left_margin'] ?? $activeLetterhead?->left_margin ?? LetterheadSetting::DEFAULT_LEFT_MARGIN),
            'right_margin' => (float) ($validated['right_margin'] ?? $activeLetterhead?->right_margin ?? LetterheadSetting::DEFAULT_RIGHT_MARGIN),
        ];

        $letterheadPath = null;
        if ($request->hasFile('letterhead_file')) {
            $letterheadPath = $request->file('letterhead_file')->getRealPath();
        } elseif ($activeLetterhead && filled($activeLetterhead->file_path) && Storage::disk('public')->exists($activeLetterhead->file_path)) {
            $letterheadPath = Storage::disk('public')->path($activeLetterhead->file_path);
        }

        $items = collect(range(1, 18))
            ->map(function (int $index): array {
                $unitPrice = 1850 + ($index * 120);
                $quantity = $index % 3 === 0 ? 3 : 2;
                $lineTotal = $unitPrice * $quantity;

                return [
                    'name' => 'Sample Item ' . str_pad((string) $index, 2, '0', STR_PAD_LEFT),
                    'description' => 'Sample service line used for preview alignment.',
                    'qty' => $quantity,
                    'rate' => $unitPrice,
                    'amount' => $lineTotal,
                ];
            });

        $subtotal = (float) $items->sum('amount');
        $gst = round($subtotal * 0.18, 2);
        $grandTotal = round($subtotal + $gst, 2);

        $pdf = Pdf::loadView('settings.letterhead.preview-invoice', [
            'invoiceNumber' => 'PREVIEW-INV-2026-07-27',
            'invoiceDate' => now()->format('d M Y'),
            'companyName' => $company->name ?? config('app.name', 'CCTV Workshop'),
            'companyAddress' => $company->address ?? 'Sample Address, Sample City, Sample State',
            'companyEmail' => $company->email ?? 'accounts@example.com',
            'companyPhone' => $company->phone_number ?? '0000000000',
            'clientName' => 'Preview Client',
            'clientAddress' => 'Sample client address line one, sample city.',
            'items' => $items,
            'subtotal' => $subtotal,
            'gst' => $gst,
            'grandTotal' => $grandTotal,
            'margins' => $previewMargins,
        ])->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 96,
            ]);

        $binary = $pdf->output();

        if ($letterheadPath) {
            $binary = $letterheadPdfService->applyToBinaryFromPath($binary, $letterheadPath, $previewMargins);
        }

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function update(Request $request, LetterheadPdfService $letterheadPdfService): RedirectResponse
    {
        $company = auth()->user()?->company;
        abort_unless($company, 403);

        $letterhead = LetterheadSetting::query()
            ->where('company_id', $company->id)
            ->orderByDesc('enabled')
            ->orderByDesc('id')
            ->first();
        $wasEnabled = (bool) ($letterhead?->enabled ?? false);

        $rules = [
            'enabled' => ['nullable', 'boolean'],
            'top_margin' => ['required', 'integer', 'min:0', 'max:100'],
            'bottom_margin' => ['required', 'integer', 'min:0', 'max:100'],
            'left_margin' => ['required', 'integer', 'min:0', 'max:100'],
            'right_margin' => ['required', 'integer', 'min:0', 'max:100'],
        ];

        $messages = [
            'letterhead_file.required' => 'Please upload a PDF file for the letterhead.',
            'letterhead_file.mimes' => 'Only PDF files are allowed.',
            'letterhead_file.max' => 'The PDF file must not be larger than 10MB.',
        ];

        $rules['letterhead_file'] = ['nullable', 'file', 'mimes:pdf', 'max:10240'];

        $validated = $request->validate($rules, $messages);

        $currentFilePath = $letterhead?->file_path;
        $newFilePath = null;
        $letterheadMetadata = [];

        try {
            if ($request->hasFile('letterhead_file')) {
                $preparedLetterhead = $letterheadPdfService->prepareUploadedLetterhead($request->file('letterhead_file'));
                $newFilePath = 'letterheads/' . Str::uuid()->toString() . '.pdf';
                Storage::disk('public')->put($newFilePath, $preparedLetterhead['binary']);
                $letterheadMetadata = $preparedLetterhead['metadata'] ?? [];
            }

            if (! $letterhead) {
                $letterhead = new LetterheadSetting();
            }

            $letterhead->fill([
                'company_id' => $company->id,
                'enabled' => $request->boolean('enabled'),
                'top_margin' => (int) $validated['top_margin'],
                'bottom_margin' => (int) $validated['bottom_margin'],
                'left_margin' => (int) $validated['left_margin'],
                'right_margin' => (int) $validated['right_margin'],
                'source_filename' => $letterheadMetadata['source_filename'] ?? $letterhead->source_filename,
                'page_count' => $letterheadMetadata['page_count'] ?? $letterhead->page_count,
                'page_width_mm' => $letterheadMetadata['page_width_mm'] ?? $letterhead->page_width_mm,
                'page_height_mm' => $letterheadMetadata['page_height_mm'] ?? $letterhead->page_height_mm,
                'page_orientation' => $letterheadMetadata['page_orientation'] ?? $letterhead->page_orientation,
            ]);

            if ($newFilePath) {
                $letterhead->file_path = $newFilePath;
            } elseif (! $letterhead->exists && filled($currentFilePath)) {
                $letterhead->file_path = $currentFilePath;
            }

            $letterhead->save();

            if ($newFilePath && filled($currentFilePath) && $currentFilePath !== $newFilePath) {
                Storage::disk('public')->delete($currentFilePath);
            }

            if ($request->boolean('enabled') || $wasEnabled) {
                LetterheadSetting::query()
                    ->where('company_id', $company->id)
                    ->where('id', '!=', $letterhead->id)
                    ->update(['enabled' => false]);
            }

            return redirect()
                ->route('settings.company.index', ['tab' => 'letterhead-settings'])
                ->with('success', 'PDF letterhead settings saved successfully.');
        } catch (Throwable $e) {
            if (filled($newFilePath)) {
                Storage::disk('public')->delete($newFilePath);
            }

            report($e);

            return back()
                ->withErrors(['letterhead_file' => 'Unable to save the PDF letterhead. Please try again.'])
                ->withInput();
        }
    }
}
