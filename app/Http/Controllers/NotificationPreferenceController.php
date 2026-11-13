<?php

namespace App\Http\Controllers;

use App\Models\UserNotificationPreference;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationPreferenceController extends Controller
{
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'whatsapp_enabled' => 'boolean',
            'email_enabled'    => 'boolean',
            'notify_lead'      => 'boolean',
            'notify_ticket'    => 'boolean',
            'notify_project'   => 'boolean',
            'notify_payment'   => 'boolean',
            'notify_staff'     => 'boolean',
        ]);

        // Checkboxes not submitted = false
        $data = array_merge([
            'whatsapp_enabled' => false,
            'email_enabled'    => false,
            'notify_lead'      => false,
            'notify_ticket'    => false,
            'notify_project'   => false,
            'notify_payment'   => false,
            'notify_staff'     => false,
        ], $validated);

        UserNotificationPreference::updateOrCreate(
            ['user_id' => $user->id],
            array_merge($data, ['company_id' => (int) $user->company_id])
        );

        return back()->with('success', 'Notification preferences saved.');
    }
}
