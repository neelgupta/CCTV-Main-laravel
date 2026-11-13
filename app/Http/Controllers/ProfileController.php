<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Company;
use App\Models\UserNotificationPreference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use App\Services\MediaUploadService;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $company = \App\Models\Company::find($user->company_id) ?: new \App\Models\Company();
        $notificationPreference = $user->notificationPreference ?: new UserNotificationPreference([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'push_enabled' => false,
        ]);

        return view('profile.edit', [
            'user' => $user,
            'company'=> $company,
            'notificationPreference' => $notificationPreference,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    // public function update(ProfileUpdateRequest $request): RedirectResponse
    // {
    //     $user = $request->user();
    //     $validated = $request->validated();

    //     $user->fill($validated);

    //     if ($user->isDirty('email')) {
    //         $user->email_verified_at = null;
    //     }

    //     $user->save();

    //     if ($user->isSuperAdmin()) {
    //         return Redirect::route('profile.edit')->with('status', 'profile-updated');
    //     }

    //     // Company admins keep their company profile in sync with the shared form.
    //     $company = \App\Models\Company::find($user->company_id) ?: new \App\Models\Company();

    //     $companyData = $request->only(['phone_number', 'address', 'email']);

    //     // Handle the logo upload for company admins only.
    //     if ($request->hasFile('logo')) {
    //         if ($company->logo && \Storage::disk('public')->exists($company->logo)) {
    //             \Storage::disk('public')->delete($company->logo);
    //         }

    //         $path = app(MediaUploadService::class)->store($request->file('logo'), 'logos', 'public');
    //         $companyData['logo'] = $path;
    //     }

    //     $company->fill($companyData);
    //     if (!$company->exists && $user->company_id) {
    //         $company->company_id = $user->company_id;
    //     }
    //     $company->save();

    //     return Redirect::route('profile.edit')->with('status', 'profile-updated');
    // }
    public function update(ProfileUpdateRequest $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $userData = $validated;
        unset($userData['phone_number'], $userData['address'], $userData['logo'], $userData['profile_photo']);

        $user->fill($userData);

        if ($user->isDirty('email')) {
            $user->email_verified_at = now();
        }

        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo) {
                if ($user->company_id) {
                    app(MediaUploadService::class)->delete($user->profile_photo, 'public', (int) $user->company_id);
                } elseif (Storage::disk('public')->exists($user->profile_photo)) {
                    Storage::disk('public')->delete($user->profile_photo);
                }
            }

            $directory = $user->company_id
                ? 'company_'.$user->company_id.'/profile_photos'
                : 'profile_photos';

            $user->profile_photo = app(MediaUploadService::class)->store($request->file('profile_photo'), $directory, 'public');
        }

        $user->save();

        if ($user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Profile Updated Successfully'
                ]);
            }

            return Redirect::route('profile.edit')->with('status', 'profile-updated');
        }

        $company = \App\Models\Company::find($user->company_id);

        if (! $company) {
            return Redirect::route('profile.edit')->with('status', 'profile-updated');
        }

        $companyData = $request->only(['phone_number', 'address', 'email']);

        if ($request->hasFile('logo')) {
            if ($company->logo && \Storage::disk('public')->exists($company->logo)) {
                \Storage::disk('public')->delete($company->logo);
            }

            $path = app(MediaUploadService::class)->store($request->file('logo'), 'logos', 'public');
            $companyData['logo'] = $path;
        }

        $company->fill($companyData);
        $company->save();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Profile Updated Successfully'
            ]);
        }

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function photo(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->profile_photo, 404);

        $path = str_replace('\\', '/', trim((string) $user->profile_photo));
        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $mimeType = Storage::disk($disk)->mimeType($path);

        return Storage::disk($disk)->response($path, basename($path), [
            'Content-Type' => $mimeType ?: 'application/octet-stream',
        ]);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
