<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ActivityLogger
{
    public static function logFromRequest(Request $request, ?User $user = null): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('activity_logs')) {
            return;
        }

        $user ??= $request->user();

        if (! $user) {
            return;
        }

        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName === '' || self::shouldSkipRoute($routeName)) {
            return;
        }

        if (self::shouldIgnoreMutationRoute($request, $routeName)) {
            return;
        }

        [$module, $action] = self::resolveModuleAndAction($request, $routeName);

        if ($module === null) {
            return;
        }

        $activityLog = ActivityLog::query()->create([
            'user_id' => $user->id,
            'company_id' => $user->company_id,
            'module' => $module,
            'action' => $action,
            'activity_type' => strtolower($module . '.' . $action),
            'description' => self::buildDescription($user, $module, $action, $routeName),
            'metadata' => [
                'route' => $routeName,
                'method' => $request->method(),
                'path' => $request->path(),
                'route_parameters' => self::normalizeRouteParameters($request),
            ],
            'ip_address' => $request->ip(),
            'browser' => $request->userAgent(),
            'created_at' => now(),
        ]);

        if (in_array($action, ['create', 'update', 'delete'], true)) {
            app(DashboardNotificationService::class)->recordActivityNotification(
                user: $user,
                module: $module,
                action: $action,
                routeName: $routeName,
                activityLogId: (int) $activityLog->id
            );
        }
    }

    private static function shouldSkipRoute(string $routeName): bool
    {
        $skipPrefixes = [
            'super-admin.',
            'superadmin.',
            'login',
            'logout',
            'password.',
            'verification.',
            'profile.',
            'sanctum.',
            'livewire.',
            'notifications.',
        ];

        foreach ($skipPrefixes as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return in_array($routeName, ['dashboard', 'welcome'], true);
    }

    private static function shouldIgnoreMutationRoute(Request $request, string $routeName): bool
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return true;
        }

        $skipFragments = [
            '.check',
            '.lookup',
            '.preview',
            '.sample',
            '.download',
            '.export',
            '.print',
            '.open',
            '.test',
            '.callback',
            '.refresh',
            '.invoice',
        ];

        foreach ($skipFragments as $fragment) {
            if (str_contains($routeName, $fragment)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private static function resolveModuleAndAction(Request $request, string $routeName): array
    {
        $module = self::resolveModuleLabel($routeName);

        if ($module === null) {
            return [null, 'view'];
        }

        $action = match (true) {
            str_contains($routeName, '.destroy')
                || str_contains($routeName, '.delete')
                || str_contains($routeName, '.remove') => 'delete',
            str_contains($routeName, '.store')
                || str_contains($routeName, '.create')
                || str_contains($routeName, '.add')
                || str_contains($routeName, '.import')
                || str_contains($routeName, '.upload')
                || str_contains($routeName, '.assign') => 'create',
            in_array($request->method(), ['PUT', 'PATCH'], true) => 'update',
            in_array($request->method(), ['DELETE'], true) => 'delete',
            default => 'view',
        };

        if ($action === 'view' && in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $action = 'update';
        }

        return [$module, $action];
    }

    private static function buildDescription(User $user, string $module, string $action, string $routeName): string
    {
        $verb = match ($action) {
            'create' => 'created',
            'update' => 'updated',
            'delete' => 'deleted',
            'export' => 'exported',
            default => 'viewed',
        };

        $target = Str::headline(str_replace('.', ' ', Str::after($routeName, '.')));

        return sprintf('%s %s %s (%s)', $user->name, $verb, $module, $target !== '' ? $target : 'records');
    }

    private static function normalizeRouteParameters(Request $request): array
    {
        $parameters = $request->route()?->parameters() ?? [];

        return collect($parameters)->mapWithKeys(function (mixed $value, string|int $key): array {
            if ($value instanceof Model) {
                return [
                    (string) $key => [
                        'type' => class_basename($value),
                        'id' => $value->getRouteKey(),
                        'label' => self::resolveReadableModelLabel($value),
                    ],
                ];
            }

            if (is_array($value)) {
                return [(string) $key => $value];
            }

            return [(string) $key => is_scalar($value) || $value === null ? $value : (string) $value];
        })->all();
    }

    private static function resolveReadableModelLabel(Model $model): ?string
    {
        $preferredFields = [
            'version',
            'challan_no',
            'invoice_no',
            'reference_no',
            'code',
            'number',
            'client_name',
            'name',
            'title',
            'subject',
            'company_name',
            'project_name',
            'customer_name',
            'contract_no',
        ];

        foreach ($preferredFields as $field) {
            $value = $model->getAttribute($field);

            if (filled($value)) {
                return trim((string) $value);
            }
        }

        $routeKey = $model->getRouteKey();
        if (filled($routeKey)) {
            return class_basename($model) . ' #' . $routeKey;
        }

        return class_basename($model);
    }

    private static function resolveModuleLabel(string $routeName): ?string
    {
        $modulePatterns = [
            'leads.' => 'Lead',
            'projects.' => 'Project',
            'amc-plans.' => 'AMC Plan',
            'amcs.' => 'AMC',
            'clients.' => 'Client',
            'teams.' => 'Staff',
            'services.' => 'Service',
            'tickets.' => 'Service',
            'settings.company.' => 'Company Settings',
            'settings.' => 'Settings',
            'company.units.' => 'Unit',
            'company.contractors.' => 'Contractor',
            'company.gst-rates.' => 'GST Rate',
            'project-stages.' => 'Project Stage',
            'service-stages.' => 'Service Stage',
            'statuses.' => 'Lead Status',
            'categories.' => 'Category',
            'boq.categories.' => 'Product Category',
            'boq.products.' => 'Product',
            'gallery.' => 'Gallery',
            'users.' => 'User',
            'roles.' => 'Role',
            'subscription.' => 'Subscription',
            'marketing.campaigns.' => 'Campaign',
            'marketing.email-template.' => 'Marketing Template',
        ];

        foreach ($modulePatterns as $prefix => $label) {
            if (str_starts_with($routeName, $prefix)) {
                return $label;
            }
        }

        return null;
    }
}
