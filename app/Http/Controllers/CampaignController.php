<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampaignRequest;
use App\Http\Requests\UpdateCampaignRequest;
use App\Jobs\GenerateFestivalCampaignAssetsJob;
use App\Jobs\StartCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\EmailLog;
use App\Models\Client;
use App\Models\FestivalCampaign;
use App\Models\GeneratedBanner;
use App\Models\WhatsAppLog;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaign\Services\CampaignAnalyticsService;
use App\Modules\Campaign\Services\CampaignBannerService;
use App\Modules\Campaign\Services\CampaignDispatchService;
use App\Modules\Campaign\Services\CampaignFestivalService;
use App\Services\MediaUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CampaignController extends Controller
{
    public function __construct(
        private readonly CampaignBannerService $bannerService,
    ) {
    }

    public function index(Request $request, CampaignRepositoryInterface $campaigns, CampaignAnalyticsService $analytics): View
    {
        $companyId = (int) $request->user()->company_id;

        return view('marketing.campaigns.index', [
            'campaigns' => $campaigns->paginateForCompany($companyId, $request->only(['status', 'type'])),
            'analytics' => $analytics->dashboard($companyId),
        ]);
    }

    public function createNormal(Request $request, CampaignFestivalService $festivals): View
    {
        return view('marketing.campaigns.create-normal', [
            'clients' => Client::query()->latest()->limit(100)->get(['id', 'name', 'email', 'mobile', 'phone']),
        ]);
    }

    public function createFestival(Request $request, CampaignFestivalService $festivals): View
    {
        return view('marketing.campaigns.create-festival', [
            'upcomingFestivals' => $festivals->upcomingFestivals(),
            'clients' => Client::query()->latest()->limit(100)->get(['id', 'name', 'email', 'mobile', 'phone']),
        ]);
    }

    public function show(Request $request, CampaignRepositoryInterface $campaigns, Campaign $campaign): View
    {
        $record = $campaigns->findForCompany((int) $request->user()->company_id, $campaign->id);
        abort_if(! $record, 404);

        return view('marketing.campaigns.show', ['campaign' => $record]);
    }

    public function store(StoreCampaignRequest $request, CampaignDispatchService $dispatchService, CampaignFestivalService $festivalService): RedirectResponse
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

        $message = $campaign->send_type === Campaign::SEND_TYPE_IMMEDIATE
            ? 'Campaign created and queued for immediate sending.'
            : 'Campaign created successfully.';

        return redirect()->route('marketing.campaigns.index')->with('success', $message);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): RedirectResponse
    {
        abort_if((int) $campaign->company_id !== (int) $request->user()->company_id, 403);
        $campaign->update($request->validated());

        return back()->with('success', 'Campaign updated successfully.');
    }

    public function sendNow(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_if((int) $campaign->company_id !== (int) $request->user()->company_id, 403);

        $campaign->update([
            'send_type' => Campaign::SEND_TYPE_IMMEDIATE,
            'status' => Campaign::STATUS_RUNNING,
            'scheduled_at' => now(),
        ]);

        StartCampaignJob::dispatch($campaign->id);

        return back()->with('success', 'Campaign queued for immediate sending.');
    }

    public function uploadBanner(Request $request, Campaign $campaign, MediaUploadService $uploadService): RedirectResponse
    {
        abort_if((int) $campaign->company_id !== (int) $request->user()->company_id, 403);

        $request->validate(['banner' => ['required', 'image', 'max:4096']]);

        $path = $uploadService->store($request->file('banner'), 'campaign-banners/company_' . $request->user()->company_id);

        $banner = GeneratedBanner::create([
            'company_id' => (int) $request->user()->company_id,
            'campaign_id' => $campaign->id,
            'created_by' => $request->user()->id,
            'source' => 'manual',
            'file_path' => $path,
            'mime_type' => $request->file('banner')->getMimeType() ?: 'image/jpeg',
            'metadata' => ['manual_upload' => true],
        ]);

        $campaign->update([
            'generated_banner_id' => $banner->id,
            'banner_image' => $banner->file_path,
        ]);

        return back()->with('success', 'Banner uploaded successfully.');
    }

    public function generateBanner(Request $request): RedirectResponse
    {
        $request->validate([
            'festival_campaign_id' => ['required', 'exists:festival_campaigns,id'],
        ]);

        $festivalCampaign = FestivalCampaign::withoutGlobalScopes()
            ->where('company_id', (int) $request->user()->company_id)
            ->findOrFail((int) $request->input('festival_campaign_id'));

        try {
            $banner = $this->bannerService->generate($festivalCampaign, $request->user());

            if ($campaignId = $request->input('campaign_id')) {
                Campaign::withoutGlobalScopes()
                    ->where('company_id', (int) $request->user()->company_id)
                    ->where('id', (int) $campaignId)
                    ->update(['generated_banner_id' => $banner->id, 'banner_image' => $banner->file_path]);
            }

            return back()->with('success', 'Festival banner generated successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to generate banner: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, Campaign $campaign, CampaignRepositoryInterface $campaigns): RedirectResponse
    {
        abort_if((int) $campaign->company_id !== (int) $request->user()->company_id, 403);

        $campaign->delete();

        return redirect()->route('marketing.campaigns.index')->with('success', 'Campaign deleted successfully.');
    }

    public function duplicate(Request $request, Campaign $campaign, CampaignRepositoryInterface $campaigns): RedirectResponse
    {
        abort_if((int) $campaign->company_id !== (int) $request->user()->company_id, 403);

        $record = $campaigns->findForCompany((int) $request->user()->company_id, $campaign->id);
        abort_if(! $record, 404);

        $duplicate = DB::transaction(function () use ($record, $request) {
            $newCampaign = Campaign::create([
                'company_id' => $record->company_id,
                'user_id' => $request->user()->id,
                'type' => $record->type,
                'send_type' => $record->send_type,
                'name' => $record->name . ' (Copy)',
                'subject' => $record->subject,
                'email_template_id' => $record->email_template_id,
                'whatsapp_template_name' => $record->whatsapp_template_name,
                'status' => $record->status,
                'scheduled_at' => $record->scheduled_at,
                'started_at' => $record->started_at,
                'completed_at' => $record->completed_at,
                'batch_size' => $record->batch_size,
                'batch_delay_seconds' => $record->batch_delay_seconds,
                'total_recipients' => $record->total_recipients,
                'sent_count' => $record->sent_count,
                'delivered_count' => $record->delivered_count,
                'opened_count' => $record->opened_count,
                'clicked_count' => $record->clicked_count,
                'bounced_count' => $record->bounced_count,
                'failed_count' => $record->failed_count,
                'retry_count' => $record->retry_count,
                'settings' => $record->settings,
            ]);

            if ($record->festivalCampaign) {
                $festivalCopy = FestivalCampaign::create([
                    'company_id' => $record->festivalCampaign->company_id,
                    'campaign_id' => $newCampaign->id,
                    'selected_by_user_id' => $request->user()->id,
                    'festival_name' => $record->festivalCampaign->festival_name,
                    'festival_date' => $record->festivalCampaign->festival_date,
                    'description' => $record->festivalCampaign->description,
                    'greetings_text' => $record->festivalCampaign->greetings_text,
                    'brand_name' => $record->festivalCampaign->brand_name,
                    'brand_logo_path' => $record->festivalCampaign->brand_logo_path,
                    'ai_provider' => $record->festivalCampaign->ai_provider,
                    'prompt_payload' => $record->festivalCampaign->prompt_payload,
                ]);

                $newCampaign->festival_campaign_id = $festivalCopy->id;
            }

            if ($record->generatedBanner) {
                $newPath = $record->generatedBanner->file_path;

                if (filled($record->generatedBanner->file_path) && Storage::disk('public')->exists($record->generatedBanner->file_path)) {
                    $extension = pathinfo($record->generatedBanner->file_path, PATHINFO_EXTENSION) ?: 'png';
                    $newPath = 'campaign-banners/company_' . $request->user()->company_id . '/' . Str::uuid() . '.' . $extension;
                    Storage::disk('public')->copy($record->generatedBanner->file_path, $newPath);
                }

                $bannerCopy = GeneratedBanner::create([
                    'company_id' => $record->generatedBanner->company_id,
                    'campaign_id' => $newCampaign->id,
                    'festival_campaign_id' => $newCampaign->festival_campaign_id,
                    'created_by' => $request->user()->id,
                    'source' => $record->generatedBanner->source,
                    'prompt_text' => $record->generatedBanner->prompt_text,
                    'file_path' => $newPath,
                    'mime_type' => $record->generatedBanner->mime_type,
                    'width' => $record->generatedBanner->width,
                    'height' => $record->generatedBanner->height,
                    'metadata' => $record->generatedBanner->metadata,
                ]);

                $newCampaign->generated_banner_id = $bannerCopy->id;
                $newCampaign->banner_image = $bannerCopy->file_path;
            }

            $recipientIdMap = [];

            foreach ($record->recipients as $recipient) {
                $recipientCopy = CampaignRecipient::create([
                    'campaign_id' => $newCampaign->id,
                    'company_id' => $recipient->company_id,
                    'client_id' => $recipient->client_id,
                    'user_id' => $recipient->user_id,
                    'name' => $recipient->name,
                    'email' => $recipient->email,
                    'phone' => $recipient->phone,
                    'email_status' => $recipient->email_status,
                    'whatsapp_status' => $recipient->whatsapp_status,
                    'overall_status' => $recipient->overall_status,
                    'scheduled_at' => $recipient->scheduled_at,
                    'processed_at' => $recipient->processed_at,
                    'metadata' => $recipient->metadata,
                ]);

                $recipientIdMap[$recipient->id] = $recipientCopy->id;
            }

            foreach ($record->emailLogs as $emailLog) {
                EmailLog::create([
                    'campaign_id' => $newCampaign->id,
                    'campaign_recipient_id' => $recipientIdMap[$emailLog->campaign_recipient_id] ?? null,
                    'company_id' => $emailLog->company_id,
                    'email' => $emailLog->email,
                    'subject' => $emailLog->subject,
                    'provider' => $emailLog->provider,
                    'message_id' => $emailLog->message_id,
                    'tracking_token' => $emailLog->tracking_token,
                    'status' => $emailLog->status,
                    'delivery_status' => $emailLog->delivery_status,
                    'sent_at' => $emailLog->sent_at,
                    'delivered_at' => $emailLog->delivered_at,
                    'opened_at' => $emailLog->opened_at,
                    'clicked_at' => $emailLog->clicked_at,
                    'failed_at' => $emailLog->failed_at,
                    'error_message' => $emailLog->error_message,
                    'retry_attempts' => $emailLog->retry_attempts,
                    'last_retry_at' => $emailLog->last_retry_at,
                    'whatsapp_status' => $emailLog->whatsapp_status,
                    'metadata' => $emailLog->metadata,
                ]);
            }

            foreach ($record->whatsappLogs as $whatsAppLog) {
                WhatsAppLog::create([
                    'campaign_id' => $newCampaign->id,
                    'campaign_recipient_id' => $recipientIdMap[$whatsAppLog->campaign_recipient_id] ?? null,
                    'company_id' => $whatsAppLog->company_id,
                    'phone' => $whatsAppLog->phone,
                    'template_name' => $whatsAppLog->template_name,
                    'provider' => $whatsAppLog->provider,
                    'provider_message_id' => $whatsAppLog->provider_message_id,
                    'status' => $whatsAppLog->status,
                    'sent_at' => $whatsAppLog->sent_at,
                    'delivered_at' => $whatsAppLog->delivered_at,
                    'read_at' => $whatsAppLog->read_at,
                    'failed_at' => $whatsAppLog->failed_at,
                    'error_message' => $whatsAppLog->error_message,
                    'metadata' => $whatsAppLog->metadata,
                ]);
            }

            $newCampaign->save();

            return $newCampaign;
        });

        return redirect()->route('marketing.campaigns.index')
            ->with('success', 'Campaign duplicated successfully.');
    }

    public function toggleStatus(Request $request, Campaign $campaign): RedirectResponse
    {
        abort_if((int) $campaign->company_id !== (int) $request->user()->company_id, 403);

        $request->validate(['status' => ['required', 'string', Rule::in([Campaign::STATUS_DRAFT, Campaign::STATUS_SCHEDULED])]]);

        $campaign->update(['status' => $request->input('status')]);

        return back()->with('success', 'Campaign status updated to ' . $request->input('status') . '.');
    }
}
