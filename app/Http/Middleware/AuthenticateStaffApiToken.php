<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionLimitService;
use App\Services\StaffAppActivityService;
use App\Models\User;
use App\Support\StaffMobileTokenStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateStaffApiToken
{
    public function __construct(
        private readonly StaffMobileTokenStore $tokenStore,
        private readonly StaffAppActivityService $activityService
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $plainTextToken = (string) $request->bearerToken();

        if ($plainTextToken === '') {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $tokenPayload = $this->tokenStore->find($plainTextToken);

        if (! is_array($tokenPayload) || empty($tokenPayload['user_id'])) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $user = User::query()
            ->with(['company', 'staff', 'roleRecord.permissions'])
            ->find($tokenPayload['user_id']);

        $role = $user?->normalizedRole();

        // if (! $user || ($role !== 'company_admin' && $role !== 'companyadmin' && (! $user->staff || (int) $user->staff->id !== (int) ($tokenPayload['staff_id'] ?? 0)))) {
        //     $this->tokenStore->forget($plainTextToken);

        //     return response()->json([
        //         'message' => 'Unauthenticated.',
        //     ], 401);
        // }

        // if (! $user->is_active || ! $user->company?->is_active || ! in_array($role, ['employee', 'company_admin', 'companyadmin'], true)) {
        //     $this->tokenStore->forget($plainTextToken);

        //     return response()->json([
        //         'message' => 'This staff account is not allowed to use the mobile API.',
        //     ], 403);
        // }

        if ($role === 'employee' && ! app(SubscriptionLimitService::class)->userHasAssignedSubscriptionAccess($user)) {
            $this->tokenStore->forget($plainTextToken);

            return response()->json([
                'message' => app(SubscriptionLimitService::class)->expiredAssignedSubscriptionMessage($user),
            ], 403);
        }

        $tokenPayload = $this->tokenStore->touch($plainTextToken, $tokenPayload);
        $this->activityService->syncFromRequest($user, $request);

        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('staff_api_token', $tokenPayload);

        return $next($request);
    }
}
