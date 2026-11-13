<?php

declare(strict_types=1);

namespace App\Modules\Communication\Services\Providers;

use App\Modules\Communication\Contracts\CommunicationProviderInterface;
use App\Modules\Communication\DTOs\WebhookPayloadData;
use App\Modules\Communication\Enums\ProviderEnum;
use App\Modules\Communication\Enums\WebhookEventTypeEnum;
use App\Modules\Communication\Exceptions\ProviderAuthenticationException;
use App\Modules\Communication\Exceptions\ProviderConnectionException;
use App\Modules\Communication\Models\IntegrationAccount;
use Illuminate\Support\Facades\Http;
use App\Modules\Communication\Support\Logger;
use Throwable;

class MetaWhatsAppProvider implements CommunicationProviderInterface
{
    protected string $apiVersion;

    protected string $baseUrl;

    protected int $timeout;

    public function __construct()
    {
        $this->apiVersion = config('communication.providers.meta_whatsapp.api_version', 'v20.0');
        $this->baseUrl = config('communication.providers.meta_whatsapp.base_url', 'https://graph.facebook.com');
        $this->timeout = (int) config('communication.providers.meta_whatsapp.timeout', 15);
    }

    public function verifyConnection(IntegrationAccount $account): bool
    {
        try {
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$account->phone_number_id}";

            $response = Http::withToken($account->access_token)
                ->timeout($this->timeout)
                ->get($url);

            if ($response->successful()) {
                Logger::info("Meta WhatsApp connection verified successfully for phone number ID: {$account->phone_number_id}");

                return true;
            }

            Logger::error("Meta WhatsApp verification failed for phone number ID: {$account->phone_number_id}. Status: {$response->status()}, Response: {$response->body()}");

            return false;
        } catch (Throwable $e) {
            Logger::error("Exception during Meta WhatsApp connection verification for ID: {$account->phone_number_id}. Error: {$e->getMessage()}");

            return false;
        }
    }

    public function verifyWebhook(array $params, IntegrationAccount $account): bool
    {
        $mode = $params['hub_mode'] ?? null;
        $token = $params['hub_verify_token'] ?? null;

        if ($mode === 'subscribe' && $token === $account->verify_token) {
            Logger::info("Webhook handshake successful for phone number ID: {$account->phone_number_id}");

            return true;
        }

        Logger::warning("Webhook handshake failed for phone number ID: {$account->phone_number_id}. Expected token: {$account->verify_token}, Received: {$token}");

        return false;
    }

    public function handleWebhook(array $payload, array $headers): WebhookPayloadData
    {
        $phoneNumberId = null;
        $messageId = null;
        $eventType = WebhookEventTypeEnum::OTHER;
        $fingerprint = '';

        try {
            // Meta webhooks contain data structured in nested arrays: entry -> changes -> value
            $entry = $payload['entry'][0] ?? null;
            $change = $entry['changes'][0] ?? null;
            $value = $change['value'] ?? null;

            if ($value) {
                $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;

                if (isset($value['messages'][0])) {
                    $eventType = WebhookEventTypeEnum::MESSAGE;
                    $messageId = $value['messages'][0]['id'] ?? null;
                } elseif (isset($value['statuses'][0])) {
                    $status = $value['statuses'][0]['status'] ?? '';
                    $messageId = $value['statuses'][0]['id'] ?? null;

                    $eventType = match ($status) {
                        'read' => WebhookEventTypeEnum::READ,
                        'delivered', 'sent', 'failed' => WebhookEventTypeEnum::DELIVERY,
                        default => WebhookEventTypeEnum::STATUS_UPDATE,
                    };
                }
            }

            // Generate fingerprint to ensure idempotency.
            // Meta passes a unique ID for messages/status changes.
            if ($messageId) {
                if ($eventType !== WebhookEventTypeEnum::MESSAGE) {
                    $status = $value['statuses'][0]['status'] ?? 'status_update';
                    $fingerprint = "meta_msg_{$messageId}_{$status}";
                } else {
                    $fingerprint = "meta_msg_{$messageId}_message";
                }
            } else {
                // Fallback to hashing the payload object/entry details
                $fingerprint = 'meta_raw_'.md5(serialize($payload));
            }

        } catch (Throwable $e) {
            Logger::error("Error parsing Meta webhook payload: {$e->getMessage()}", [
                'payload' => $payload,
            ]);
        }

        return new WebhookPayloadData(
            provider: ProviderEnum::META_WHATSAPP,
            eventType: $eventType,
            fingerprint: $fingerprint,
            payload: $payload,
            headers: $headers,
            phoneNumberId: $phoneNumberId,
            messageId: $messageId
        );
    }

    public function sendRequest(string $method, string $endpoint, array $options = []): array
    {
        // Concrete method to call Meta Graph API
        try {
            $url = "{$this->baseUrl}/{$this->apiVersion}/{$endpoint}";

            $response = Http::timeout($this->timeout);

            if (isset($options['token'])) {
                $response = $response->withToken($options['token']);
                unset($options['token']);
            }

            $response = match (strtoupper($method)) {
                'POST' => $response->post($url, $options),
                'PUT' => $response->put($url, $options),
                'DELETE' => $response->delete($url, $options),
                default => $response->get($url, $options),
            };

            if ($response->failed()) {
                if ($response->status() === 401) {
                    throw ProviderAuthenticationException::failed('MetaWhatsApp', $response->body());
                }
                throw ProviderConnectionException::unavailable('MetaWhatsApp', "API returned status {$response->status()}: {$response->body()}");
            }

            return $response->json() ?? [];
        } catch (Throwable $e) {
            if ($e instanceof ProviderAuthenticationException || $e instanceof ProviderConnectionException) {
                throw $e;
            }
            throw new ProviderConnectionException("Request failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function sendMessage(IntegrationAccount $account, string $to, string $text): array
    {
        return $this->sendRequest(
            method: 'POST',
            endpoint: "{$account->phone_number_id}/messages",
            options: [
                'token' => $account->access_token,
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $to,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $text,
                ],
            ]
        );
    }

    public function markAsRead(IntegrationAccount $account, string $messageId): array
    {
        return $this->sendRequest(
            method: 'POST',
            endpoint: "{$account->phone_number_id}/messages",
            options: [
                'token' => $account->access_token,
                'messaging_product' => 'whatsapp',
                'status' => 'read',
                'message_id' => $messageId,
            ]
        );
    }

    public function healthCheck(IntegrationAccount $account): bool
    {
        return $this->verifyConnection($account);
    }
}
