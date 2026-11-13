<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, ...$permissions)
    {
        if (!Auth::check()) {
            return redirect('login');
        }

        $user = Auth::user();
        foreach ($permissions as $permission) {
            if ($user->hasPermission((string) $permission)) {
                return $next($request);
            }
        }

        abort(403, 'Insufficient permissions.');
    }
}

