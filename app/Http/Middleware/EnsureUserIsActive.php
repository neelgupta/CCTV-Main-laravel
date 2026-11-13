<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionLimitService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your account is inactive. Please contact your administrator.']);
        }

        if ($user && $user->company && !$user->company->is_active && !$user->isSuperAdmin()) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Your company account is deactivated. Please contact admin.']);
        }

        if ($user && ! app(SubscriptionLimitService::class)->userHasAssignedSubscriptionAccess($user)) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => app(SubscriptionLimitService::class)->expiredAssignedSubscriptionMessage($user)]);
        }

        return $next($request);
    }
}
