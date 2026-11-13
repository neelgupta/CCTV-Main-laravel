<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Campaign\Services\CampaignAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignAnalyticsController extends Controller
{
    public function __invoke(Request $request, CampaignAnalyticsService $analyticsService): JsonResponse
    {
        return response()->json([
            'data' => $analyticsService->dashboard((int) $request->user()->company_id),
        ]);
    }
}
