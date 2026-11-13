<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationPreference;
use App\Models\UserPushDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PushNotificationController extends Controller
{
    public function updatePreference(Request $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 422, 'Push notifications are only available for company users.');

        $validated = $request->validate([
            'push_enabled' => ['required', 'boolean'],
        ]);

        $current = UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->first();

        $preferences = array_merge(
            UserNotificationPreference::defaults((int) $user->id, (int) $user->company_id),
            Arr::except($current?->toArray() ?? [], ['id', 'created_at', 'updated_at', 'user', 'company'])
        );

        $preferences['push_enabled'] = (bool) $validated['push_enabled'];

        UserNotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            $preferences
        );

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'push_enabled' => (bool) $preferences['push_enabled'],
            ]);
        }

        return back()->with('success', 'Push notification preference updated.');
    }

    public function storeToken(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 422, 'Push notifications are only available for company users.');

        $validated = $request->validate([
            'token' => ['required', 'string', 'min:20'],
            'browser_name' => ['nullable', 'string', 'max:100'],
            'platform_name' => ['nullable', 'string', 'max:100'],
            'user_agent' => ['nullable', 'string', 'max:1000'],
        ]);

        UserPushDevice::updateOrCreate(
            [
                'fcm_token_hash' => UserPushDevice::tokenHash($validated['token']),
            ],
            [
                'user_id' => $user->id,
                'company_id' => $user->company_id,
                'fcm_token' => $validated['token'],
                'browser_name' => $validated['browser_name'] ?? null,
                'platform_name' => $validated['platform_name'] ?? null,
                'user_agent' => $validated['user_agent'] ?? $request->userAgent(),
                'is_enabled' => true,
                'last_seen_at' => now(),
            ]
        );

        return response()->json(['success' => true]);
    }

    public function destroyToken(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 422, 'Push notifications are only available for company users.');

        $validated = $request->validate([
            'token' => ['nullable', 'string', 'min:20'],
        ]);

        $query = UserPushDevice::query()
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id);

        if (! empty($validated['token'])) {
            $query->where('fcm_token_hash', UserPushDevice::tokenHash($validated['token']));
        }

        $query->delete();

        return response()->json(['success' => true]);
    }
}
