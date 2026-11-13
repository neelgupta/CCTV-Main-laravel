<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Exceptions\StorageLimitExceededException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user();

            if ($user?->isSuperAdmin()) {
                return route('super-admin.dashboard');
            }

            if ($request->routeIs('client.*')) {
                return route('client.portal');
            }

            if ($user?->company && ! $user->company->hasCompletedOnboarding()) {
                return route('onboarding.step4');
            }

            return route('dashboard');
        });

        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
            'permission' => \App\Http\Middleware\PermissionMiddleware::class,
            'active' => \App\Http\Middleware\EnsureUserIsActive::class,
            'onboarding' => \App\Http\Middleware\CheckOnboarding::class,
            'staff.api' => \App\Http\Middleware\AuthenticateStaffApiToken::class,
            'subscription.access' => \App\Http\Middleware\EnsureSubscriptionWriteAccess::class,
            'setup.access' => \App\Http\Middleware\EnsureSetupAccess::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\LogUserActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->report(function (MethodNotAllowedHttpException $e) {
            \Illuminate\Support\Facades\Log::warning('HTTP method not allowed for route.', [
                'method' => request()->method(),
                'path' => request()->path(),
                'route' => request()->route()?->getName(),
                'allowed_methods' => $e->getHeaders()['Allow'] ?? null,
                'user_id' => request()->user()?->id,
            ]);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->expectsJson()) {
                if ($e instanceof ValidationException) {
                    $firstMessage = collect($e->errors())->flatten()->first() ?: $e->getMessage();

                    return response()->json([
                        'message' => $firstMessage,
                        'errors' => $e->errors(),
                    ], $e->status);
                }

                return null;
            }

            $redirectToLogin = function () use ($request) {
                if ($request->hasSession()) {
                    auth()->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }

                $routeName = (string) ($request->route()?->getName() ?? '');
                $loginRoute = str_starts_with($routeName, 'client.')
                    ? 'client.login'
                    : 'login';

                return redirect()->route($loginRoute);
            };

            // A stale form token on the public QR complaint form should not log the
            // visitor out; reload the form with their input so they can submit again.
            $redirectToQrComplaintForm = function () use ($request) {
                return redirect()
                    ->route('customer.support.qr.show', ['token' => $request->route('token')])
                    ->withInput($request->except(['_token', 'attachment']))
                    ->with('qr_form_error', 'Your session expired before the complaint was sent. Please check the details and submit again.');
            };

            if ($e instanceof ValidationException) {
                return null;
            }

            if ($e instanceof AuthenticationException) {
                return $redirectToLogin();
            }

            if ($e instanceof TokenMismatchException) {
                return $request->routeIs('customer.support.qr.store')
                    ? $redirectToQrComplaintForm()
                    : $redirectToLogin();
            }

            if ($e instanceof ValidationException) {
                $firstMessage = collect($e->errors())->flatten()->first() ?: $e->getMessage();
                $messageLower = strtolower((string) $firstMessage);
                $isSubscriptionLimit = str_contains($messageLower, 'upgrade your subscription')
                    || str_contains($messageLower, 'upgrade your plan')
                    || str_contains($messageLower, 'current subscription plan')
                    || str_contains($messageLower, 'is deactivated');

                if ($isSubscriptionLimit) {
                    return redirect()->route('subscription.show')->with('error', $firstMessage);
                }

                return back()->withErrors($e->errors(), $e->errorBag)->withInput();
            }

            if ($e instanceof StorageLimitExceededException) {
                return back()->withInput()->with('error', $e->getMessage());
            }

            $statusCode = 500;
            $title = 'Something Went Wrong';
            $description = 'We could not complete this request. Please try again in a moment.';

            if ($e instanceof HttpExceptionInterface) {
                $statusCode = $e->getStatusCode();
            }

            if ($statusCode === 403) {
                $title = 'Access Denied';
                $description = 'You do not have permission to access this page.';
            } elseif ($statusCode === 413) {
                $title = 'File Too Large';
                $description = 'The uploaded file exceeds the server limit. Try a smaller image or increase upload_max_filesize and post_max_size.';
            } elseif ($statusCode === 404) {
                $title = 'Page Not Found';
                $description = 'The page, file, or resource you requested could not be found.';
            } elseif ($statusCode === 419) {
                return $request->routeIs('customer.support.qr.store')
                    ? $redirectToQrComplaintForm()
                    : $redirectToLogin();
            } elseif ($statusCode === 422) {
                $title = 'Validation Error';
                $description = 'Some form values were invalid. Please review the fields and try again.';
            } elseif ($statusCode === 429) {
                $title = 'Too Many Requests';
                $description = 'You sent too many requests in a short period. Please wait a moment and try again.';
            } elseif ($statusCode >= 500) {
                $title = 'Something Went Wrong';
                $description = 'We could not complete this request. Please try again in a moment.';
            }

            return response()->view('errors.problem', [
                'statusCode' => $statusCode,
                'title' => $title,
                'description' => $description,
            ], $statusCode);
        });
    })->create();
