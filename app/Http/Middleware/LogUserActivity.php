<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() && $this->shouldLog($request)) {
            ActivityLogger::logFromRequest($request);
        }

        return $response;
    }

    private function shouldLog(Request $request): bool
    {
        if (! $request->route()) {
            return false;
        }

        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        if ($request->method() !== 'GET') {
            return false;
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        return str_ends_with($routeName, '.index')
            || str_ends_with($routeName, '.show')
            || str_starts_with($routeName, 'reports.');
    }
}
