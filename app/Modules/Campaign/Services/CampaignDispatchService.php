<?php

namespace App\Modules\Campaign\Services;

use App\Events\CampaignStatusChanged;
use App\Jobs\FinalizeCampaignJob;
use App\Jobs\ProcessCampaignBatchJob;
use App\Jobs\StartCampaignJob;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Client;
use App\Models\FestivalCampaign;
use App\Models\User;
use App\Modules\Campaign\Contracts\CampaignRecipientRepositoryInterface;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CampaignDispatchService
{
    public function __construct(
        private readonly CampaignRepositoryInterface $campaigns,
        private readonly CampaignRecipientRepositoryInterface $recipients,
    ) {
    }

    public function createCampaign(User $user, array $payload): Campaign
    {
        return DB::transaction(function () use ($user, $payload) {
            $sendType = $payload['send_type'] ?? Campaign::SEND_TYPE_SCHEDULED;
            $isImmediate = $sendType === Campaign::SEND_TYPE_IMMEDIATE;

            $status = $isImmediate ? Campaign::STATUS_RUNNING : ($payload['status'] ?? Campaign::STATUS_DRAFT);
            $scheduledAt = $isImmediate ? now() : ($payload['scheduled_at'] ?? null);

            $companyId = (int) $user->company_id;
            $batchSize = $this->resolveBatchSetting($companyId, 'email_batch_size', (int) ($payload['batch_size'] ?? config('campaigns.batch_size')));
            $retryCount = $this->resolveBatchSetting($companyId, 'retry_count', (int) config('campaigns.retry_count', 3));

            $campaign = $this->campaigns->create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'type' => $payload['type'],
                'send_type' => $sendType,
                'name' => $payload['name'],
                'subject' => $payload['subject'] ?? null,
                'email_template_id' => $payload['email_template_id'] ?? null,
                'whatsapp_template_name' => $payload['whatsapp_template_name'] ?? null,
                'festival_campaign_id' => $payload['festival_campaign_id'] ?? null,
                'generated_banner_id' => $payload['generated_banner_id'] ?? null,
                'banner_image' => $payload['banner_image'] ?? null,
                'status' => $status,
                'scheduled_at' => $scheduledAt,
                'batch_size' => $batchSize,
                'batch_delay_seconds' => (int) ($payload['batch_delay_seconds'] ?? config('campaigns.batch_delay_seconds')),
                'retry_count' => $retryCount,
                'settings' => [
                    'email_body' => $payload['email_body'] ?? null,
                    'channels' => [
                        'email' => (bool) ($payload['send_email'] ?? true),
                        'whatsapp' => (bool) ($payload['send_whatsapp'] ?? true),
                    ],
                ],
            ]);

            $rows = $this->prepareRecipients($user, $campaign, $payload);
            $this->recipients->bulkCreate($campaign, $rows);

            $campaign->update(['total_recipients' => count($rows)]);

            if ($campaign->festival_campaign_id) {
                FestivalCampaign::withoutGlobalScopes()
                    ->where('id', $campaign->festival_campaign_id)
                    ->update(['campaign_id' => $campaign->id]);
            }

            event(new CampaignStatusChanged($campaign->fresh(), 'created'));

            if ($isImmediate) {
                event(new CampaignStatusChanged($campaign->fresh(), 'started'));
                StartCampaignJob::dispatch($campaign->id);
            } elseif ($campaign->status === Campaign::STATUS_SCHEDULED) {
                event(new CampaignStatusChanged($campaign->fresh(), 'scheduled'));
            }

            return $campaign->fresh(['recipients']);
        });
    }

    private function resolveBatchSetting(int $companyId, string $key, int $default): int
    {
        try {
            return (int) \App\Models\SystemSetting::getValue($key, $default, $companyId);
        } catch (\Throwable) {
            return $default;
        }
    }

    public function start(Campaign $campaign): void
    {
        $campaign->update([
            'status' => Campaign::STATUS_RUNNING,
            'started_at' => now(),
        ]);

        event(new CampaignStatusChanged($campaign->fresh(), 'started'));

        $this->dispatchBatches($campaign);
    }

    public function dispatchBatches(Campaign $campaign): void
    {
        $recipientIds = CampaignRecipient::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->pluck('id')
            ->chunk(max(1, (int) $campaign->batch_size))
            ->values();

        foreach ($recipientIds as $index => $batchIds) {
            ProcessCampaignBatchJob::dispatch($campaign->id, $batchIds->all())
                ->delay(now()->addSeconds($index * max(1, (int) $campaign->batch_delay_seconds)));
        }

        FinalizeCampaignJob::dispatch($campaign->id)
            ->delay(now()->addSeconds(max(60, $recipientIds->count() * max(1, (int) $campaign->batch_delay_seconds))));
    }

    public function markCompleted(Campaign $campaign): void
    {
        $campaign->refresh();

        $campaign->update([
            'status' => Campaign::STATUS_COMPLETED,
            'completed_at' => now(),
            'sent_count' => $campaign->emailLogs()->whereIn('status', ['sent', 'delivered', 'opened', 'clicked', 'bounced'])->count(),
            'delivered_count' => $campaign->emailLogs()->whereIn('status', ['delivered', 'opened', 'clicked'])->count(),
            'opened_count' => $campaign->emailLogs()->whereHas('opens')->count(),
            'clicked_count' => $campaign->emailLogs()->whereHas('clicks')->count(),
            'bounced_count' => $campaign->emailLogs()->where('status', 'bounced')->count(),
            'failed_count' => $campaign->whatsappLogs()->where('status', 'failed')->count() + $campaign->emailLogs()->where('status', 'failed')->count(),
        ]);

        event(new CampaignStatusChanged($campaign->fresh(), 'completed'));
    }

    private function prepareRecipients(User $user, Campaign $campaign, array $payload): array
    {
        $timestamp = now();
        $rows = [];

        $clientIds = collect($payload['client_ids'] ?? [])->filter()->map(fn ($id) => (int) $id)->values();

        if ($clientIds->isNotEmpty()) {
            $clients = Client::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->whereIn('id', $clientIds)
                ->get();

            foreach ($clients as $client) {
                $rows[] = [
                    'campaign_id' => $campaign->id,
                    'company_id' => (int) $user->company_id,
                    'client_id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'phone' => $client->mobile ?: $client->phone,
                    'scheduled_at' => $campaign->scheduled_at,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }

        foreach ($payload['recipients'] ?? [] as $recipient) {
            if (! is_array($recipient)) {
                continue;
            }

            $rows[] = [
                'campaign_id' => $campaign->id,
                'company_id' => (int) $user->company_id,
                'user_id' => $recipient['user_id'] ?? null,
                'name' => $recipient['name'] ?? null,
                'email' => $recipient['email'] ?? null,
                'phone' => $recipient['phone'] ?? null,
                'scheduled_at' => $campaign->scheduled_at,
                'metadata' => $recipient['metadata'] ?? null,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];
        }

        return $rows;
    }
}
