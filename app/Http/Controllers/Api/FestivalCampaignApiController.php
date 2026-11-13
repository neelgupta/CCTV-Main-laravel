<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateFestivalCampaignAssetsJob;
use App\Models\FestivalCampaign;
use App\Models\GeneratedBanner;
use App\Modules\Campaign\Services\CampaignBannerService;
use App\Modules\Campaign\Services\CampaignFestivalService;
use App\Services\MediaUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FestivalCampaignApiController extends Controller
{
    public function upcoming(Request $request, CampaignFestivalService $festivalService): JsonResponse
    {
        return response()->json(['data' => $festivalService->upcomingFestivals()]);
    }

    public function store(Request $request, CampaignFestivalService $festivalService): JsonResponse
    {
        $validated = $request->validate([
            'festival_name' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'description' => ['nullable', 'string'],
            'greetings_text' => ['nullable', 'string', 'max:255'],
            'brand_name' => ['nullable', 'string', 'max:255'],
            'brand_logo_path' => ['nullable', 'string', 'max:500'],
        ]);

        $festivalCampaign = $festivalService->storeSelection($request->user(), $validated, $validated);
        GenerateFestivalCampaignAssetsJob::dispatch($festivalCampaign->id);

        return response()->json(['message' => 'Festival campaign saved successfully.', 'data' => $festivalCampaign], 201);
    }

    public function uploadBanner(Request $request, MediaUploadService $uploadService): JsonResponse
    {
        $validated = $request->validate([
            'festival_campaign_id' => ['required', 'exists:festival_campaigns,id'],
            'banner' => ['required', 'image', 'max:4096'],
        ]);

        $festivalCampaign = FestivalCampaign::withoutGlobalScopes()
            ->where('company_id', (int) $request->user()->company_id)
            ->findOrFail((int) $validated['festival_campaign_id']);

        $path = $uploadService->store($request->file('banner'), 'campaign-banners/company_' . $request->user()->company_id);

        $banner = GeneratedBanner::create([
            'company_id' => (int) $request->user()->company_id,
            'festival_campaign_id' => $festivalCampaign->id,
            'created_by' => $request->user()->id,
            'source' => 'manual',
            'file_path' => $path,
            'mime_type' => $request->file('banner')->getMimeType() ?: 'image/jpeg',
            'metadata' => ['manual_upload' => true],
        ]);

        return response()->json(['message' => 'Banner uploaded successfully.', 'data' => $banner]);
    }

    public function generateBanner(Request $request, CampaignBannerService $bannerService): JsonResponse
    {
        $validated = $request->validate([
            'festival_campaign_id' => ['required', 'exists:festival_campaigns,id'],
            'campaign_id' => ['nullable', 'exists:campaigns,id'],
        ]);

        $festivalCampaign = FestivalCampaign::withoutGlobalScopes()
            ->where('company_id', (int) $request->user()->company_id)
            ->findOrFail((int) $validated['festival_campaign_id']);

        try {
            $banner = $bannerService->generate($festivalCampaign, $request->user());

            if (! empty($validated['campaign_id'])) {
                \App\Models\Campaign::withoutGlobalScopes()
                    ->where('company_id', (int) $request->user()->company_id)
                    ->where('id', (int) $validated['campaign_id'])
                    ->update(['generated_banner_id' => $banner->id, 'banner_image' => $banner->file_path]);
            }

            $metadata = $banner->metadata ?? [];

            return response()->json([
                'message' => 'Banner generated successfully.',
                'data' => [
                    'id' => $banner->id,
                    'generated_banner_url' => Storage::disk('public')->url($banner->file_path),
                    'generated_title' => $metadata['generated_title'] ?? '',
                    'generated_subtitle' => $metadata['generated_subtitle'] ?? '',
                    'generated_description' => $metadata['generated_description'] ?? '',
                    'festival_type' => $metadata['festival_type'] ?? $bannerService->getFestivalType($festivalCampaign->festival_name),
                    'file_path' => $banner->file_path,
                    'mime_type' => $banner->mime_type,
                    'source' => $banner->source,
                    'festival_name' => $festivalCampaign->festival_name,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Banner generation failed: ' . $e->getMessage()], 500);
        }
    }
}
