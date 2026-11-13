<?php

namespace App\Jobs;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Modules\Campaign\Services\CampaignEmailService;
use App\Modules\Campaign\Services\CampaignWhatsappService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCampaignBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly int $campaignId,
        private readonly array $recipientIds,
    ) {
    }

    public function retryUntil(): \DateTime
    {
        return now()->addHours(24);
    }

    public function handle(CampaignEmailService $emailService, CampaignWhatsappService $whatsAppService): void
    {
        $campaign = Campaign::withoutGlobalScopes()->with(['emailTemplate', 'generatedBanner'])->find($this->campaignId);

        if (! $campaign || $campaign->status !== Campaign::STATUS_RUNNING) {
            return;
        }

        $maxRetries = $campaign->retry_count ?: (int) config('campaigns.retry_count', 3);
        $sendEmail = (bool) data_get($campaign->settings, 'channels.email', true);
        $sendWhatsapp = (bool) data_get($campaign->settings, 'channels.whatsapp', true);

        $recipients = CampaignRecipient::withoutGlobalScopes()
            ->where('campaign_id', $campaign->id)
            ->whereIn('id', $this->recipientIds)
            ->get();

        foreach ($recipients as $recipient) {
            $emailStatus = $recipient->email_status;
            $whatsappStatus = $recipient->whatsapp_status;
            $failed = false;

            if ($sendEmail) {
                try {
                    $emailLog = $emailService->send($campaign, $recipient);
                    $emailStatus = $emailLog->status === 'bounced' ? 'failed' : $emailLog->status;
                } catch (\Throwable $e) {
                    $emailStatus = 'failed';
                    $failed = true;
                }

                if ($failed && $this->attempts() < $maxRetries) {
                    $this->release($this->backoff);
                    return;
                }
            }

            if ($sendWhatsapp) {
                try {
                    $waLog = $whatsAppService->send($campaign, $recipient);
                    $whatsappStatus = $waLog->status;
                } catch (\Throwable) {
                    $whatsappStatus = 'failed';
                }
            }

            $overall = in_array('failed', [$emailStatus, $whatsappStatus], true) ? 'failed' : 'processed';

            EmailLog::withoutGlobalScopes()
                ->where('campaign_recipient_id', $recipient->id)
                ->where('campaign_id', $campaign->id)
                ->update(['retry_attempts' => $this->attempts(), 'last_retry_at' => now()]);

            $recipient->update([
                'email_status' => $emailStatus,
                'whatsapp_status' => $whatsappStatus,
                'overall_status' => $overall,
                'processed_at' => now(),
            ]);
        }
    }
}
