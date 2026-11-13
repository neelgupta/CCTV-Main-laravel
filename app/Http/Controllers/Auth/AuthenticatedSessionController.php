<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user && ! $user->isSuperAdmin() && $user->company && ! $user->company->hasCompletedOnboarding()) {
                return redirect()->route('onboarding.step4');
            }

            $homeRoute = $user?->isSuperAdmin() ? 'super-admin.dashboard' : 'dashboard';

            return redirect()->route($homeRoute);
        }

        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
       try {
            $request->authenticate();
            $request->session()->regenerate();

            $user = $request->user();
            if ($user) {
                ActivityLog::query()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                    'module' => 'Auth',
                    'action' => 'login',
                    'activity_type' => 'auth.login',
                    'description' => $user->name . ' logged in',
                    'metadata' => [
                        'route' => 'login',
                        'method' => $request->method(),
                        'path' => $request->path(),
                    ],
                    'ip_address' => $request->ip(),
                    'browser' => $request->userAgent(),
                    'created_at' => now(),
                ]);
            }
            
            if ($user && ! $user->isSuperAdmin() && $user->company && ! $user->company->hasCompletedOnboarding()) {
                return redirect()->route('onboarding.step4');
            }

            $homeRoute = $user?->isSuperAdmin() ? 'super-admin.dashboard' : 'dashboard';

            return redirect()->route($homeRoute);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput($request->only('email', 'remember'));
        }

        // $user = \App\Models\User::where('email', $request->email)->first();

        // if ($user && !$user->company->is_active) {
        //     return back()->withErrors([
        //         'email' => 'Your company account is deactivated. Please contact admin.',
        //     ]);
        // }

        // $request->authenticate();

        // $request->session()->regenerate();

        // return redirect()->intended(route('dashboard', false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
