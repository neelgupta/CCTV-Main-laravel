<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionLimitService;
use Closure;
use Illuminate\Http\Request;

class EnsureSubscriptionWriteAccess
{
    public function __construct(
        private readonly SubscriptionLimitService $subscriptionLimitService
    ) {
    }

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin() || ! $user->company_id) {
            return $next($request);
        }

        if ($request->isMethodSafe() || $request->routeIs('subscription.*', 'logout')) {
            return $next($request);
        }

        // Allow company updates even if there is no active subscription/trial.
        // This prevents locking company settings writes when the account has not purchased yet.
        if ($request->routeIs(
            'settings.company.*',
            'settings.statuses.*',
            'settings.project-stages.*',
            'settings.service-stages.*',
            'settings.categories.*'
        )) {
            return $next($request);
        }

        if (! $this->subscriptionLimitService->shouldRestrictWritesForCompany($user->company)) {
            return $next($request);
        }

        return redirect()
            ->route('subscription.show')
            ->with('error', $this->subscriptionLimitService->expiredAccessMessage($user->company));
    }
}
