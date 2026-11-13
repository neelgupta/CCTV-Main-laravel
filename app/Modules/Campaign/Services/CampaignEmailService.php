<?php

namespace App\Modules\Campaign\Services;

use App\Events\EmailBounceDetected;
use App\Mail\CampaignEmailMailable;
use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CompanyIntegration;
use App\Models\EmailBounce;
use App\Models\EmailClick;
use App\Models\EmailLog;
use App\Models\EmailOpen;
use App\Modules\Campaign\Contracts\EmailLogRepositoryInterface;
use App\Services\MarketingEmailTemplateRenderer;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Email;

class CampaignEmailService
{
    public function __construct(
        private readonly EmailLogRepositoryInterface $emailLogs,
        private readonly MarketingEmailTemplateRenderer $renderer,
        private readonly MailManager $mailManager,
    ) {
    }

    public function send(Campaign $campaign, CampaignRecipient $recipient): EmailLog
    {
        $emailLog = $this->emailLogs->createForRecipient($campaign, $recipient);

        if (! filled($recipient->email)) {
            return $this->bounce($emailLog, $campaign, $recipient, 'hard', 'Recipient email is missing.');
        }

        $html = $this->buildHtml($campaign, $recipient, $emailLog);

        try {
            $mailer = $this->resolveMailer($campaign->company_id);

            $mailer->to($recipient->email, $recipient->name ?: null)
                ->send(new CampaignEmailMailable($campaign->subject ?: 'Campaign', $html));

            $this->emailLogs->update($emailLog, [
                'status' => 'delivered',
                'sent_at' => now(),
                'delivered_at' => now(),
                'metadata' => array_merge($emailLog->metadata ?? [], [
                    'recipient_name' => $recipient->name,
                ]),
            ]);

            return $emailLog->fresh();
        } catch (\Throwable $throwable) {
            Log::error('Campaign email send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $throwable->getMessage(),
            ]);

            return $this->bounce($emailLog, $campaign, $recipient, 'soft', $throwable->getMessage());
        }
    }

    public function trackOpen(EmailLog $emailLog, array $context): void
    {
        EmailOpen::create([
            'email_log_id' => $emailLog->id,
            'campaign_id' => $emailLog->campaign_id,
            'campaign_recipient_id' => $emailLog->campaign_recipient_id,
            'opened_at' => now(),
            'ip_address' => $context['ip_address'] ?? null,
            'device_info' => $context['device_info'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
            'metadata' => $context,
        ]);

        $emailLog->update(['status' => 'opened']);
    }

    public function trackClick(EmailLog $emailLog, string $url, array $context): void
    {
        EmailClick::create([
            'email_log_id' => $emailLog->id,
            'campaign_id' => $emailLog->campaign_id,
            'campaign_recipient_id' => $emailLog->campaign_recipient_id,
            'clicked_url' => $url,
            'clicked_at' => now(),
            'ip_address' => $context['ip_address'] ?? null,
            'device_info' => $context['device_info'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ]);

        if ($emailLog->status !== 'bounced') {
            $emailLog->update(['status' => 'clicked']);
        }
    }

    private function buildHtml(Campaign $campaign, CampaignRecipient $recipient, EmailLog $emailLog): string
    {
        $template = $campaign->emailTemplate;
        $html = $template
            ? $this->renderer->render($template, [
                'first_name' => $recipient->name ?: 'Customer',
                'conference_name' => $campaign->name,
                'salutation' => 'Mr./Ms.',
            ])
            : nl2br(e((string) data_get($campaign->settings, 'email_body', '')));

        $baseUrl = rtrim((string) config('campaigns.tracking_base_url'), '/');
        $openUrl = $baseUrl . '/api/email-tracking/open?token=' . urlencode($emailLog->tracking_token);

        return preg_replace_callback(
            '/href=["\']([^"\']+)["\']/i',
            fn (array $matches) => 'href="' . $baseUrl . '/api/email-tracking/click?token=' . urlencode($emailLog->tracking_token) . '&url=' . urlencode($matches[1]) . '"',
            $html
        ) . '<img src="' . e($openUrl) . '" alt="" width="1" height="1" style="display:block;width:1px;height:1px;border:0;opacity:0;" />';
    }

    private function bounce(EmailLog $emailLog, Campaign $campaign, CampaignRecipient $recipient, string $type, string $reason): EmailLog
    {
        EmailBounce::create([
            'email_log_id' => $emailLog->id,
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'bounce_type' => $type,
            'reason' => $reason,
            'bounced_at' => now(),
            'provider_payload' => ['reason' => $reason],
        ]);

        $this->emailLogs->update($emailLog, [
            'status' => 'bounced',
            'failed_at' => now(),
            'error_message' => Str::limit($reason, 65000),
        ]);

        event(new EmailBounceDetected($campaign, $recipient, $emailLog->fresh()));

        return $emailLog->fresh();
    }

    private function resolveMailer(int $companyId)
    {
        $integration = CompanyIntegration::query()
            ->where('company_id', $companyId)
            ->where('type', CompanyIntegration::TYPE_EMAIL_SMTP)
            ->where('is_enabled', true)
            ->first();

        if (! $integration || empty($integration->credentials)) {
            return Mail::mailer();
        }

        $credentials = $integration->credentials;
        $transport = [
            'transport' => 'smtp',
            'host' => $credentials['host'] ?? '127.0.0.1',
            'port' => (int) ($credentials['port'] ?? 587),
            'encryption' => ($credentials['encryption'] ?? 'tls') === 'none' ? null : ($credentials['encryption'] ?? 'tls'),
            'username' => $credentials['username'] ?? null,
            'password' => $credentials['password'] ?? null,
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN'),
        ];

        config(['mail.mailers.campaign_dynamic_' . $companyId => $transport]);

        return $this->mailManager->mailer('campaign_dynamic_' . $companyId);
    }
}
