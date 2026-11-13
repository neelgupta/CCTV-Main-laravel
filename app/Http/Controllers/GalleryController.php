<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Services\MediaFileStreamer;
use App\Services\MediaUploadService;
use Illuminate\Support\Facades\Auth;

class GalleryController extends Controller
{
    public function index()
    {
        $companyId = $this->companyId();

        $mediaFiles = MediaFile::query()
            ->where('company_id', $companyId)
            ->where('gallery_visible', true)
            ->latest()
            ->paginate(24);

        return view('gallery.index', compact('mediaFiles'));
    }

    public function open(MediaFile $mediaFile)
    {
        $this->ensureCompanyAccess($mediaFile);

        // Serves from Google Drive when the file lives there, with local fallback.
        return app(MediaFileStreamer::class)->respond($mediaFile);
    }

    public function destroy(MediaFile $mediaFile)
    {
        $this->ensureCompanyAccess($mediaFile);

        app(MediaUploadService::class)->delete(
            (string) $mediaFile->path,
            (string) ($mediaFile->disk ?: 'public'),
            (int) $mediaFile->company_id
        );

        return back()->with('success', 'Media file removed successfully.');
    }

    private function ensureCompanyAccess(MediaFile $mediaFile): void
    {
        $companyId = $this->companyId();

        if ((int) $mediaFile->company_id !== (int) $companyId) {
            abort(403);
        }
    }

    private function companyId(): int
    {
        return (int) Auth::user()->company_id;
    }
}
