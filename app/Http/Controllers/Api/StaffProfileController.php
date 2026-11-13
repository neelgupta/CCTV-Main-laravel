<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangeStaffPasswordRequest;
use App\Http\Requests\Api\UpdateStaffProfileRequest;
use App\Http\Resources\StaffProfileResource;
use App\Services\StaffProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Services\MediaFileStreamer;
use App\Models\MediaFile;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StaffProfileController extends Controller
{
    public function __construct(
        private readonly StaffProfileService $profileService
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $staff = $this->profileService->resolveStaff($user);
        $profile = (new StaffProfileResource($staff))->resolve($request);

        return response()->json([
            'profile' => $profile,
            'dashboard_statistics' => $this->profileService->dashboardStatistics($staff),
        ]);
    }

    public function update(UpdateStaffProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        try {
            $staff = $this->profileService->updateProfile(
                $user,
                $request->validated(),
                $request->file('profile_image')
            );

            return response()->json([
                'message' => 'Profile updated successfully.',
                'profile' => (new StaffProfileResource($staff))->resolve($request),
            ]);
        } catch (Throwable $throwable) {
            report($throwable);
            dd($throwable);
            return response()->json([
                'message' => 'Unable to update profile.',
            ], 500);
        }
    }

    public function changePassword(ChangeStaffPasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 401, 'Unauthenticated.');

        $currentPassword = (string) $request->input('current_password');
        $newPassword = (string) $request->input('new_password');

        if (! Hash::check($currentPassword, (string) $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
                'error_code' => 'incorrect_current_password',
                'errors' => [
                    'current_password' => ['Current password is incorrect.'],
                ],
            ], 422);
        }

        if (Hash::check($newPassword, (string) $user->password)) {
            return response()->json([
                'message' => 'New password must be different from the current password.',
                'error_code' => 'password_unchanged',
                'errors' => [
                    'new_password' => ['New password must be different from the current password.'],
                ],
            ], 422);
        }

        try {
            $this->profileService->changePassword($user, $newPassword);

            return response()->json([
                'message' => 'Password changed successfully.',
            ]);
        } catch (Throwable $throwable) {
            report($throwable);

            return response()->json([
                'message' => 'Unable to change password.',
            ], 500);
        }
    }

    public function profileImage(Request $request): Response
    {
        abort_unless($request->hasValidSignature(), 403, 'Invalid or expired image link.');

        return $this->serveProfileImage($request);
    }

    public function publicProfileImage(Request $request): Response
    {
        return $this->serveProfileImage($request);
    }

    private function serveProfileImage(Request $request): Response
    {
        $path = $this->normalizePath((string) $request->query('path', ''));
        abort_unless($path !== '', 404, 'Image not found.');

        $companyId = $request->query('company_id');
        $companyId = is_numeric($companyId) ? (int) $companyId : null;
        $disk = (string) $request->query('disk', '');

        $media = MediaFile::query()
            ->when($companyId !== null, fn ($query) => $query->where('company_id', $companyId))
            ->where('path', $path)
            ->first();

        if ($media) {
            $disk = (string) ($media->disk ?: $disk ?: (env('AWS_ACCESS_KEY_ID') ? 's3' : 'public'));
            return app(MediaFileStreamer::class)->respond($media);
        }

        $disk = $disk !== '' ? $disk : (env('AWS_ACCESS_KEY_ID') ? 's3' : 'public');
        abort_unless(Storage::disk($disk)->exists($path), 404, 'Image not found.');

        $fileName = (string) $request->query('name', basename($path));
        $mimeType = Storage::disk($disk)->mimeType($path) ?: 'application/octet-stream';

        return Storage::disk($disk)->response($path, $fileName, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        return $path;
    }
}
