<?php

namespace App\Modules\Campaign\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\CompanyIntegration;
use App\Models\WhatsAppLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CampaignWhatsappService
{
    public function send(Campaign $campaign, CampaignRecipient $recipient): WhatsAppLog
    {
        $log = WhatsAppLog::create([
            'campaign_id' => $campaign->id,
            'campaign_recipient_id' => $recipient->id,
            'company_id' => $campaign->company_id,
            'phone' => (string) $recipient->phone,
            'template_name' => $campaign->whatsapp_template_name,
            'provider' => strtolower((string) data_get($campaign->settings, 'whatsapp.provider', 'wati')),
            'status' => 'queued',
        ]);

        if (! filled($recipient->phone)) {
            $log->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => 'Recipient phone is missing.',
            ]);

            return $log->fresh();
        }

        try {
            $response = $this->dispatchProviderRequest($campaign, $recipient);

            $log->update([
                'status' => $response['status'] ?? 'sent',
                'provider_message_id' => $response['provider_message_id'] ?? null,
                'sent_at' => now(),
                'metadata' => $response,
            ]);

            return $log->fresh();
        } catch (\Throwable $throwable) {
            Log::error('Campaign WhatsApp send failed', [
                'campaign_id' => $campaign->id,
                'recipient_id' => $recipient->id,
                'error' => $throwable->getMessage(),
            ]);

            $log->update([
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $throwable->getMessage(),
            ]);

            return $log->fresh();
        }
    }

    private function dispatchProviderRequest(Campaign $campaign, CampaignRecipient $recipient): array
    {
        $integration = CompanyIntegration::query()
            ->where('company_id', $campaign->company_id)
            ->where('type', CompanyIntegration::TYPE_WHATSAPP_WATI)
            ->first();

        $credentials = $integration?->credentials ?? [];
        $provider = (string) ($credentials['provider'] ?? 'WATI');
        $banner = $campaign->generatedBanner?->file_path
            ? asset('storage/' . ltrim((string) $campaign->generatedBanner->file_path, '/'))
            : null;

        return match ($provider) {
            'Twilio' => $this->sendTwilio($credentials, $recipient, $campaign, $banner),
            'Gupshup' => $this->sendGupshup($credentials, $recipient, $campaign, $banner),
            'Interakt' => $this->sendInterakt($credentials, $recipient, $campaign, $banner),
            'WebGlowPro' => $this->sendWebhook($credentials, $recipient, $campaign, $banner, $credentials['base_url'] ?? null),
            'Other (Generic Webhook)' => $this->sendWebhook($credentials, $recipient, $campaign, $banner, $credentials['webhook_url'] ?? null),
            default => $this->sendWati($credentials, $recipient, $campaign, $banner),
        };
    }

    private function sendWati(array $credentials, CampaignRecipient $recipient, Campaign $campaign, ?string $banner): array
    {
        if (! filled($credentials['api_url'] ?? null) || ! filled($credentials['access_token'] ?? null)) {
            return ['status' => 'sent', 'provider_message_id' => null, 'mock' => true];
        }

        $response = Http::withToken($credentials['access_token'])
            ->post(rtrim((string) $credentials['api_url'], '/') . '/api/v1/sendTemplateMessage', [
                'whatsappNumber' => $recipient->phone,
                'template_name' => $campaign->whatsapp_template_name,
                'bodyParams' => [$recipient->name ?: 'Customer', $campaign->name],
                'mediaUrl' => $banner,
            ])
            ->json();

        return [
            'status' => 'sent',
            'provider_message_id' => data_get($response, 'messageId'),
            'response' => $response,
        ];
    }

    private function sendTwilio(array $credentials, CampaignRecipient $recipient, Campaign $campaign, ?string $banner): array
    {
        if (! filled($credentials['account_sid'] ?? null) || ! filled($credentials['auth_token'] ?? null) || ! filled($credentials['from_number'] ?? null)) {
            return ['status' => 'sent', 'provider_message_id' => null, 'mock' => true];
        }

        $endpoint = 'https://api.twilio.com/2010-04-01/Accounts/' . $credentials['account_sid'] . '/Messages.json';
        $payload = [
            'From' => $credentials['from_number'],
            'To' => str_starts_with((string) $recipient->phone, 'whatsapp:') ? $recipient->phone : 'whatsapp:' . $recipient->phone,
            'Body' => $campaign->name,
        ];

        if ($banner) {
            $payload['MediaUrl'] = $banner;
        }

        $response = Http::asForm()
            ->withBasicAuth($credentials['account_sid'], $credentials['auth_token'])
            ->post($endpoint, $payload)
            ->json();

        return [
            'status' => 'sent',
            'provider_message_id' => data_get($response, 'sid'),
            'response' => $response,
        ];
    }

    private function sendGupshup(array $credentials, CampaignRecipient $recipient, Campaign $campaign, ?string $banner): array
    {
        if (! filled($credentials['api_key'] ?? null)) {
            return ['status' => 'sent', 'provider_message_id' => null, 'mock' => true];
        }

        $response = Http::withHeaders(['apikey' => $credentials['api_key']])
            ->post('https://api.gupshup.io/wa/api/v1/msg', [
                'channel' => 'whatsapp',
                'source' => $credentials['source_number'] ?? null,
                'destination' => $recipient->phone,
                'src.name' => $credentials['app_name'] ?? null,
                'message' => json_encode([
                    'type' => $banner ? 'image' : 'text',
                    'text' => $campaign->name,
                    'originalUrl' => $banner,
                    'previewUrl' => $banner,
                ]),
            ])
            ->json();

        return [
            'status' => 'sent',
            'provider_message_id' => data_get($response, 'messageId'),
            'response' => $response,
        ];
    }

    private function sendInterakt(array $credentials, CampaignRecipient $recipient, Campaign $campaign, ?string $banner): array
    {
        if (! filled($credentials['interakt_api_key'] ?? null)) {
            return ['status' => 'sent', 'provider_message_id' => null, 'mock' => true];
        }

        $response = Http::withHeaders([
            'Authorization' => 'Basic ' . $credentials['interakt_api_key'],
            'Content-Type' => 'application/json',
        ])->post('https://api.interakt.ai/v1/public/message/', [
            'countryCode' => '+91',
            'phoneNumber' => preg_replace('/\D+/', '', (string) $recipient->phone),
            'callbackData' => 'campaign:' . $campaign->id,
            'type' => $banner ? 'Image' : 'Text',
            'template' => [
                'name' => $campaign->whatsapp_template_name ?: 'campaign_notification',
                'bodyValues' => [$recipient->name ?: 'Customer', $campaign->name],
            ],
            'image' => $banner ? ['link' => $banner] : null,
        ])->json();

        return [
            'status' => 'sent',
            'provider_message_id' => data_get($response, 'result.messageId'),
            'response' => $response,
        ];
    }

    private function sendWebhook(array $credentials, CampaignRecipient $recipient, Campaign $campaign, ?string $banner, ?string $url): array
    {
        if (! filled($url) || ! filled($credentials['generic_auth_token'] ?? $credentials['jwt_token'] ?? null)) {
            return ['status' => 'sent', 'provider_message_id' => null, 'mock' => true];
        }

        $token = $credentials['generic_auth_token'] ?? $credentials['jwt_token'];
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'API_KEY' => $token,
        ])->post($url, [
            'campaignId' => $campaign->id,
            'campaignName' => $campaign->name,
            'phone' => $recipient->phone,
            'templateName' => $campaign->whatsapp_template_name,
            'bodyParams' => [$recipient->name ?: 'Customer', $campaign->name],
            'mediaUrl' => $banner,
            'phoneNumberId' => $credentials['phone_number_id'] ?? null,
            'fromNumber' => $credentials['from_number'] ?? null,
            'wabaId' => $credentials['waba_id'] ?? null,
        ])->json();

        return [
            'status' => 'sent',
            'provider_message_id' => data_get($response, 'message_id', data_get($response, 'id')),
            'response' => $response,
        ];
    }
}
