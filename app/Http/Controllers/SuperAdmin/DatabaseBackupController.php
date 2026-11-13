<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\DatabaseBackupService;
use App\Services\GoogleDriveBackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class DatabaseBackupController extends Controller
{
    public function __construct(
        private readonly DatabaseBackupService $backupService,
        private readonly GoogleDriveBackupService $googleDriveBackupService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorizeSuperAdmin($request);

        return view('super-admin.database-backups.index', [
            'backups' => $this->backupService->listBackups(),
            'stats' => $this->backupService->summary(),
            'googleDriveBackup' => $this->googleDriveBackupService->dashboardData(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        try {
            $backup = $this->backupService->createBackup();
            $stats = $this->backupService->summary();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Database backup created successfully.',
                    'backup' => $backup,
                    'stats' => $stats,
                ]);
            }

            return back()->with('success', 'Database backup created successfully.');
        } catch (Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Database backup failed.',
                ], 500);
            }

            return back()->with('error', 'Database backup failed.');
        }
    }

    public function storeGoogleDrive(Request $request): JsonResponse|RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        try {
            $backup = $this->googleDriveBackupService->runBackup();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Google Drive backup created successfully.',
                    'backup' => $backup,
                    'dashboard' => $this->googleDriveBackupService->dashboardData(),
                ]);
            }

            return back()->with('success', 'Google Drive backup created successfully.');
        } catch (Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Google Drive backup failed.',
                ], 500);
            }

            return back()->with('error', 'Google Drive backup failed.');
        }
    }

    public function download(Request $request, string $filename): BinaryFileResponse
    {
        $this->authorizeSuperAdmin($request);

        $path = $this->backupService->downloadPath($filename);

        return response()->download($path, basename($path));
    }

    public function destroy(Request $request, string $filename): JsonResponse|RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        try {
            $this->backupService->deleteBackup($filename);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Database backup deleted successfully.',
                ]);
            }

            return back()->with('success', 'Database backup deleted successfully.');
        } catch (Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Database backup could not be deleted.',
                ], 500);
            }

            return back()->with('error', 'Database backup could not be deleted.');
        }
    }

    private function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
