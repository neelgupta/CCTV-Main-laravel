<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
   public function update(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // --- ADD THIS BLOCK TO FIX THE SWEETALERT ERROR ---
        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Your Password has been Changed Successfully'
            ]);
        }

        return back()->with('status', 'password-updated');
    }
}
