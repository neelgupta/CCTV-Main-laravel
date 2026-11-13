<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOnboarding
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Only check for logged in users with a company
        if ($user && $user->company_id && ! $user->isSuperAdmin()) {
            $company = $user->company;
            $hasCompletedOnboarding = $company?->hasCompletedOnboarding() ?? false;

            // Force onboarding (subscription plan step) if not completed
            if (! $hasCompletedOnboarding && ! $request->routeIs('onboarding.*', 'logout')) {
                return redirect()->route('onboarding.step4');
            }

            // If already onboarded and trying to access onboarding
            if ($hasCompletedOnboarding && $request->routeIs('onboarding.*')) {
                return redirect()->route('dashboard');
            }
        }

        return $next($request);
    }
}
