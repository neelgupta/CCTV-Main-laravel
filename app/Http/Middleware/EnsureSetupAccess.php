<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionLimitService;
use Closure;
use Illuminate\Http\Request;

class EnsureSetupAccess
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

        $company = $user->company;

        if (! $company || $this->isExemptRoute($request)) {
            return $next($request);
        }

        if (! $this->subscriptionLimitService->companyHasActivePlan($company)) {
            return redirect()
                ->route('dashboard')
                ->with('subscription_feature_warning', true);
        }

        if (! $company->hasRequiredProfileDetails()) {
            return redirect()
                ->route('dashboard')
                ->with('setup_completion_warning', true);
        }

        return $next($request);
    }

    private function isExemptRoute(Request $request): bool
    {
        return $request->routeIs(
            'dashboard',
            'subscription.*',
            'settings.company.*',
            'settings.statuses.*',
            'settings.project-stages.*',
            'settings.service-stages.*',
            'settings.categories.*',
            'profile.*',
            'onboarding.*',
            'logout'
        );
    }
}
