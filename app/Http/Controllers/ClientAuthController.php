<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientAuthController extends Controller
{
    public function create(): View
    {
        return view('auth.client-login');
    }

    public function store(LoginRequest $request)
    {
        $request->authenticate();
        $user = Auth::user();
        if (!$user?->hasRole('client')) {
            Auth::logout();
            return back()->withErrors(['email' => 'Please use the client portal.']);
        }
        $request->session()->regenerate();
        return redirect()->route('client.portal');
    }

    public function portal()
    {
        return view('client.portal', ['user' => auth()->user()]);
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('client.login');
    }
}
