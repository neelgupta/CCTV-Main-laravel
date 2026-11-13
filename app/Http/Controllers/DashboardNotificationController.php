<?php

namespace App\Http\Controllers;

use App\Models\DashboardNotification;
use App\Services\DashboardNotificationService;
use Illuminate\Http\Request;

class DashboardNotificationController extends Controller
{
    public function updateStatus(Request $request, DashboardNotification $dashboardNotification)
    {
        $user = $request->user();

        abort_unless($dashboardNotification->user_id === $user->id, 403);

        $validated = $request->validate([
            'status' => ['required', 'in:unread,read'],
        ]);

        $dashboardNotification->status = $validated['status'];
        $dashboardNotification->read_at = $validated['status'] === 'read' ? now() : null;
        $dashboardNotification->archived_at = null;
        $dashboardNotification->save();

        if ($request->boolean('redirect_back', true)) {
            return back()->with('success', 'Notification status updated.');
        }

        return response()->json([
            'success' => true,
            'status' => $dashboardNotification->status,
            'read_at' => optional($dashboardNotification->read_at)?->toDateTimeString(),
        ]);
    }

    public function markAllRead(Request $request, DashboardNotificationService $notificationService)
    {
        $user = $request->user();

        $notificationService->markAllAsRead($user);

        return back()->with('success', 'All notifications marked as read.');
    }
}
