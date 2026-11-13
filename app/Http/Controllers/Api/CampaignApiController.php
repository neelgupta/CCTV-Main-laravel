<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Jobs\GenerateFestivalCampaignAssetsJob;
use App\Jobs\StartCampaignJob;
use App\Models\Campaign;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaign\Services\CampaignDispatchService;
use App\Modules\Campaign\Services\CampaignFestivalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignApiController extends Controller
{
    public function index(Request $request, CampaignRepositoryInterface $campaigns): JsonResponse
    {
        return response()->json($campaigns->paginateForCompany((int) $request->user()->company_id, $request->only(['status', 'type'])));
    }

    public function store(StoreCampaignRequest $request, CampaignDispatchService $dispatchService, CampaignFestivalService $festivalService): JsonResponse
    {
        $payload = $request->validated();

        if (($payload['type'] ?? null) === Campaign::TYPE_FESTIVAL && empty($payload['festival_campaign_id']) && filled($payload['festival_name'] ?? null) && filled($payload['festival_date'] ?? null)) {
            $festivalCampaign = $festivalService->storeSelection($request->user(), [
                'festival_name' => $payload['festival_name'],
                'date' => $payload['festival_date'],
                'description' => $payload['festival_description'] ?? null,
            ], [
                'greetings_text' => $payload['greetings_text'] ?? null,
                'brand_name' => $payload['brand_name'] ?? null,
                'brand_logo_path' => $payload['brand_logo_path'] ?? null,
            ]);

            $payload['festival_campaign_id'] = $festivalCampaign->id;
        }

        $campaign = $dispatchService->createCampaign($request->user(), $payload);

        if ($campaign->type === Campaign::TYPE_FESTIVAL && $campaign->festival_campaign_id) {
            GenerateFestivalCampaignAssetsJob::dispatch((int) $campaign->festival_campaign_id);
        }

        return response()->json(['message' => 'Campaign created successfully.', 'data' => $campaign], 201);
    }

    public function show(Request $request, string $campaign): JsonResponse
    {
        $record = Campaign::withoutGlobalScopes()
            ->with(['recipients', 'emailLogs', 'whatsappLogs', 'festivalCampaign', 'generatedBanner'])
            ->where('company_id', (int) $request->user()->company_id)
            ->where(fn ($query) => $query->where('id', $campaign)->orWhere('uuid', $campaign))
            ->firstOrFail();

        return response()->json(['data' => $record]);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): JsonResponse
    {
        abort_if((int) $campaign->company_id !== (int) $request->user()->company_id, 403);
        $campaign->update($request->validated());

        return response()->json(['message' => 'Campaign updated successfully.', 'data' => $campaign->fresh()]);
    }

    public function send(Request $request, Campaign $campaign): JsonResponse
    {
        abort_if((int) $campaign->company_id !== (int) $request->user()->company_id, 403);

        $campaign->update([
            'status' => Campaign::STATUS_SCHEDULED,
            'scheduled_at' => now(),
        ]);

        StartCampaignJob::dispatch($campaign->id);

        return response()->json(['message' => 'Campaign queued successfully.']);
    }
}
