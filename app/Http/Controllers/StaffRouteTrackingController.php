<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Models\StaffRoutePoint;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StaffRouteTrackingController extends Controller
{
    public function __construct(
        private readonly SubscriptionLimitService $subscriptionLimitService
    ) {
    }

    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscriptionLimitService = app(SubscriptionLimitService::class);

        abort_unless($user && $user->isCompanyAdmin() && $this->companySupportsTracking($subscriptionLimitService, (int) $user->company_id), 403, 'Staff tracking is not available on the current subscription plan.');

        $validated = $request->validate([
            'date' => ['nullable', 'date'],
            'staff_id' => ['nullable', 'string'],
        ]);

        $date = isset($validated['date'])
            ? Carbon::parse($validated['date'])->toDateString()
            : now()->toDateString();
        $selectedStaffId = $this->normalizeSelectedStaffId($validated['staff_id'] ?? 'all');

        $staffs = Staff::query()
            ->where('company_id', (int) $user->company_id)
            ->whereHas('user')
            ->whereHas('companySubscription.plan', function ($query) {
                $query->where('disable_staff_tracking', false);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'employee_id', 'profile_photo', 'company_subscription_id']);

        $trackableStaffs = $staffs
            ->filter(fn (Staff $staff) => $this->subscriptionLimitService->staffTrackingEnabledForStaff($staff))
            ->values();

        if ($trackableStaffs->isEmpty()) {
            return response()->json([
                'date' => $date,
                'selected_staff_id' => $selectedStaffId,
                'day_window' => [
                    'start' => Carbon::parse($date)->startOfDay()->toIso8601String(),
                    'end' => Carbon::parse($date)->endOfDay()->toIso8601String(),
                ],
                'staffs' => $trackableStaffs,
                'routes' => [],
                'summary' => [
                    'staff_count' => 0,
                    'point_count' => 0,
                    'distance_km' => 0,
                ],
            ]);
        }

        $targetStaffs = $selectedStaffId === 'all'
            ? $trackableStaffs
            : $trackableStaffs->filter(fn (Staff $staff) => (int) $staff->id === (int) Staff::decodeRouteKeyValue($selectedStaffId))->values();

        abort_if($selectedStaffId !== 'all' && $targetStaffs->isEmpty(), 404, 'Staff member not found.');

        $targetStaffIds = $targetStaffs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $dayStart = Carbon::parse($date)->startOfDay();
        $dayEnd = Carbon::parse($date)->endOfDay();

        $points = StaffRoutePoint::query()
            ->where('company_id', (int) $user->company_id)
            ->whereBetween('recorded_at', [$dayStart, $dayEnd])
            ->when($targetStaffIds !== [], fn ($query) => $query->whereIn('staff_id', $targetStaffIds))
            ->with(['staff:id,name,employee_id,profile_photo'])
            ->orderBy('recorded_at')
            ->get();

        $groupedPoints = $points->groupBy('staff_id');
        $palette = $this->routeColors();

        $routes = $targetStaffs
            ->map(function (Staff $staff, int $index) use ($groupedPoints, $palette, $selectedStaffId) {
                $staffPoints = $groupedPoints->get($staff->id, collect());

                if ($selectedStaffId === 'all' && $staffPoints->isEmpty()) {
                    return null;
                }

                $route = $this->buildRoutePayload($staff, $staffPoints, $palette[$index % count($palette)]);

                return $route;
            })
            ->filter()
            ->values();

        $summary = $this->buildSummary($routes);

        return response()->json([
            'date' => $date,
            'selected_staff_id' => $selectedStaffId,
            'day_window' => [
                'start' => $dayStart->toIso8601String(),
                'end' => $dayEnd->toIso8601String(),
            ],
            'staffs' => $trackableStaffs->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'route_key' => $staff->getRouteKey(),
                'name' => $staff->name,
                'employee_id' => $staff->employee_id,
                'profile_photo_url' => $staff->profile_photo_url,
                'tracking_enabled' => true,
                'has_points' => (bool) ($groupedPoints->get($staff->id)?->isNotEmpty() ?? false),
                'point_count' => (int) ($groupedPoints->get($staff->id)?->count() ?? 0),
            ])->values(),
            'routes' => $routes,
            'summary' => $summary,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');

        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'recorded_at' => ['nullable', 'date'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'speed_kph' => ['nullable', 'numeric', 'min:0'],
            'heading_degrees' => ['nullable', 'numeric', 'between:0,360'],
            'source' => ['nullable', 'string', 'max:100'],
            'device_name' => ['nullable', 'string', 'max:191'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $recordedAt = isset($validated['recorded_at'])
            ? Carbon::parse($validated['recorded_at'])
            : now();

        if ($this->subscriptionLimitService->trackingIsDisabledForStaff($user->staff)) {
            return response()->json([
                'message' => 'Route tracking is disabled for this plan.',
                'route_point' => null,
            ]);
        }

        $point = StaffRoutePoint::create([
            'company_id' => (int) $user->company_id,
            'staff_id' => (int) $user->staff->id,
            'recorded_at' => $recordedAt,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'accuracy_meters' => $validated['accuracy_meters'] ?? null,
            'speed_kph' => $validated['speed_kph'] ?? null,
            'heading_degrees' => $validated['heading_degrees'] ?? null,
            'source' => $validated['source'] ?? 'mobile',
            'device_name' => $validated['device_name'] ?? $request->userAgent(),
            'metadata' => array_filter([
                'location_name' => $validated['location_name'] ?? null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $validated['metadata'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);

        return response()->json([
            'message' => 'Route point recorded.',
            'route_point' => $this->formatPoint($point->fresh(['staff:id,name,employee_id'])),
        ], 201);
    }

    private function buildRoutePayload(Staff $staff, Collection $points, string $color): array
    {
        $orderedPoints = $points->sortBy('recorded_at')->values();
        $formattedPoints = $orderedPoints->values()->map(fn (StaffRoutePoint $point, int $index) => $this->formatPoint($point, $index + 1))->values();

        return [
            'staff_id' => $staff->id,
            'staff_key' => $staff->getRouteKey(),
            'staff_name' => $staff->name,
            'employee_id' => $staff->employee_id,
            'profile_photo_url' => $staff->profile_photo_url,
            'color' => $color,
            'points' => $formattedPoints,
            'summary' => [
                'point_count' => $formattedPoints->count(),
                'distance_km' => round($this->distanceKm($orderedPoints), 2),
                'first_seen' => $orderedPoints->first()?->recorded_at?->toIso8601String(),
                'last_seen' => $orderedPoints->last()?->recorded_at?->toIso8601String(),
            ],
        ];
    }

    private function formatPoint(StaffRoutePoint $point, ?int $sequence = null): array
    {
        return [
            'sequence' => $sequence,
            'latitude' => (float) $point->latitude,
            'longitude' => (float) $point->longitude,
            'recorded_at' => $point->recorded_at?->toIso8601String(),
            'recorded_label' => $point->recorded_at?->format('h:i A'),
            'accuracy_meters' => $point->accuracy_meters === null ? null : (float) $point->accuracy_meters,
            'speed_kph' => $point->speed_kph === null ? null : (float) $point->speed_kph,
            'heading_degrees' => $point->heading_degrees === null ? null : (float) $point->heading_degrees,
            'source' => $point->source,
            'device_name' => $point->device_name,
            'location_name' => data_get($point->metadata, 'location_name'),
        ];
    }

    private function normalizeSelectedStaffId(mixed $value): string
    {
        if ($value === null) {
            return 'all';
        }

        $selected = trim((string) $value);

        return $selected === '' ? 'all' : $selected;
    }

    private function companySupportsTracking(SubscriptionLimitService $subscriptionLimitService, int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        return $subscriptionLimitService->activeSubscriptions($companyId)
            ->contains(fn ($subscription) => ! ($subscription->plan?->shouldDisableStaffTracking() ?? true));
    }

    private function buildSummary(Collection $routes): array
    {
        return [
            'staff_count' => $routes->count(),
            'point_count' => (int) $routes->sum(fn (array $route) => $route['summary']['point_count'] ?? 0),
            'distance_km' => round((float) $routes->sum(fn (array $route) => $route['summary']['distance_km'] ?? 0), 2),
        ];
    }

    private function distanceKm(Collection $points): float
    {
        $previous = null;
        $distance = 0.0;

        foreach ($points as $point) {
            if ($previous !== null) {
                $distance += $this->haversineKm(
                    (float) $previous->latitude,
                    (float) $previous->longitude,
                    (float) $point->latitude,
                    (float) $point->longitude,
                );
            }

            $previous = $point;
        }

        return $distance;
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /**
     * @return array<int, string>
     */
    private function routeColors(): array
    {
        return [
            '#2563eb',
            '#7c3aed',
            '#0f766e',
            '#f97316',
            '#dc2626',
            '#0891b2',
            '#16a34a',
            '#db2777',
        ];
    }
}
