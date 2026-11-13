<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmcPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAmcPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can access AMC plans.');

        $plans = AmcPlan::query()
            ->where('company_id', (int) $user->company_id)
            ->withCount('amcs')
            ->orderBy('duration_months')
            ->orderBy('visit_count')
            ->orderBy('name')
            ->get()
            ->map(function (AmcPlan $amcPlan) {
                return [
                    'id' => $amcPlan->id,
                    'name' => $amcPlan->name,
                    'duration_months' => $amcPlan->duration_months,
                    'duration_label' => $amcPlan->durationLabel(),
                    'visit_count' => $amcPlan->visit_count,
                    'price' => $amcPlan->price,
                    'description' => $amcPlan->description,
                    'includes_on_call_support' => (bool) $amcPlan->includes_on_call_support,
                    'service_mode' => $amcPlan->service_mode,
                    'service_mode_label' => AmcPlan::SERVICE_MODE_OPTIONS[$amcPlan->service_mode] ?? $amcPlan->service_mode,
                    'supports_on_call' => $amcPlan->supportsOnCall(),
                    'generates_scheduled_visits' => $amcPlan->generatesScheduledVisits(),
                    'is_active' => (bool) $amcPlan->is_active,
                    'amcs_count' => (int) ($amcPlan->amcs_count ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'message' => 'AMC plans retrieved successfully.',
            'data' => $plans,
        ]);
    }

    public function show(Request $request, int $amcPlanId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can access AMC plan details.');

        $amcPlan = AmcPlan::query()
            ->where('company_id', (int) $user->company_id)
            ->findOrFail($amcPlanId);

        return response()->json([
            'message' => 'AMC plan details retrieved successfully.',
            'data' => [
                'amc_plan' => [
                    'id' => $amcPlan->id,
                    'name' => $amcPlan->name,
                    'duration_months' => $amcPlan->duration_months,
                    'duration_label' => $amcPlan->durationLabel(),
                    'visit_count' => $amcPlan->visit_count,
                    'price' => $amcPlan->price,
                    'description' => $amcPlan->description,
                    'includes_on_call_support' => (bool) $amcPlan->includes_on_call_support,
                    'service_mode' => $amcPlan->service_mode,
                    'service_mode_label' => AmcPlan::SERVICE_MODE_OPTIONS[$amcPlan->service_mode] ?? $amcPlan->service_mode,
                    'supports_on_call' => $amcPlan->supportsOnCall(),
                    'generates_scheduled_visits' => $amcPlan->generatesScheduledVisits(),
                    'is_active' => (bool) $amcPlan->is_active,
                ],
            ],
        ]);
    }
}
