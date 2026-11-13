<?php

declare(strict_types=1);

namespace App\Modules\Communication\Jobs;

use App\Modules\Communication\Actions\PersistWhatsAppMessageAction;
use App\Modules\Communication\Actions\ResolveWhatsAppContactAction;
use App\Modules\Communication\Actions\UpdateWhatsAppMessageStatusAction;
use App\Modules\Communication\Contracts\CommunicationProviderInterface;
use App\Modules\Communication\Contracts\WebhookLogRepositoryInterface;
use App\Modules\Communication\Enums\MessageDirectionEnum;
use App\Modules\Communication\Enums\ProcessedStatusEnum;
use App\Modules\Communication\Models\CommunicationWebhookLog;
use App\Modules\Communication\Models\IntegrationAccount;
use App\Modules\Communication\Services\WhatsAppMediaService;
use App\Modules\Communication\Support\Logger;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class StoreWebhookEventJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected int $webhookLogId,
        protected string $fingerprint
    ) {
        $this->onQueue(config('communication.queue.queue_name', 'communication-webhooks'));
        $this->onConnection(config('communication.queue.connection', 'database'));
    }

    /**
     * Execute the job.
     */
    public function handle(
        WebhookLogRepositoryInterface $logRepository,
        ResolveWhatsAppContactAction $resolveContactAction,
        PersistWhatsAppMessageAction $persistMessageAction,
        UpdateWhatsAppMessageStatusAction $updateStatusAction
    ): void {
        Logger::info("Processing webhook event job ID: {$this->webhookLogId}");

        // Find the log
        $log = CommunicationWebhookLog::withoutGlobalScopes()->find($this->webhookLogId);

        if (! $log) {
            Logger::error("Webhook log record not found for job processing: ID {$this->webhookLogId}");

            return;
        }

        try {
            // Check for idempotency / duplicates
            $isDuplicate = CommunicationWebhookLog::withoutGlobalScopes()
                ->where('integration_account_id', $log->integration_account_id)
                ->where('payload->fingerprint', $this->fingerprint)
                ->where('processed', ProcessedStatusEnum::SUCCESS->value)
                ->where('id', '<', $log->id)
                ->exists();

            if ($isDuplicate) {
                Logger::warning("Duplicate event identified. Skipping processing for Log ID: {$log->id}, Fingerprint: {$this->fingerprint}");
                $logRepository->updateStatus($log, ProcessedStatusEnum::DUPLICATE->value);

                return;
            }

            // Process payload: persist contacts, chat messages, and status updates
            $this->processPayload($log, $resolveContactAction, $persistMessageAction, $updateStatusAction);

            $logRepository->updateStatus($log, ProcessedStatusEnum::SUCCESS->value);
            Logger::info("Webhook event processed successfully for Log ID: {$log->id}");
        } catch (Throwable $e) {
            Logger::error("Exception processing webhook event Log ID: {$log->id}. Message: {$e->getMessage()}");
            $logRepository->updateStatus($log, ProcessedStatusEnum::FAILED->value, $e->getMessage());
        }
    }

    /**
     * Parse and persist contact profiles, inbound messages, and message status updates.
     */
    protected function processPayload(
        CommunicationWebhookLog $log,
        ResolveWhatsAppContactAction $resolveContactAction,
        PersistWhatsAppMessageAction $persistMessageAction,
        UpdateWhatsAppMessageStatusAction $updateStatusAction
    ): void {
        $payload = $log->payload;
        $value = $payload['entry'][0]['changes'][0]['value'] ?? null;

        if (! $value) {
            Logger::warning("Webhook payload has empty value object. Log ID: {$log->id}");

            return;
        }

        // 1. Process Status Updates (delivered, read, failed, sent)
        $statuses = $value['statuses'] ?? [];
        if (! empty($statuses)) {
            foreach ($statuses as $statusItem) {
                $msgId = $statusItem['id'] ?? null;
                $statusValue = $statusItem['status'] ?? null;

                if ($msgId && $statusValue) {
                    $updateStatusAction->execute($msgId, $statusValue);
                }
            }
        }

        // 2. Process Incoming Messages
        $messages = $value['messages'] ?? [];
        if (empty($messages) && empty($statuses)) {
            Logger::info('No message or status content found in webhook payload.');

            return;
        }

        $contacts = $value['contacts'] ?? [];

        $account = null;
        if (! empty($messages)) {
            $account = IntegrationAccount::withoutGlobalScopes()->find($log->integration_account_id);
        }

        foreach ($messages as $message) {
            $sender = $message['from'] ?? 'Unknown';
            $messageId = $message['id'] ?? 'Unknown';
            $type = $message['type'] ?? 'text';
            $bodyText = '';

            // Extract content body based on message type
            switch ($type) {
                case 'text':
                    $bodyText = $message['text']['body'] ?? '';
                    break;
                case 'image':
                    $bodyText = $message['image']['caption'] ?? '(Image)';
                    break;
                case 'document':
                    $bodyText = $message['document']['filename'] ?? ($message['document']['caption'] ?? '(Document)');
                    break;
                case 'video':
                    $bodyText = $message['video']['caption'] ?? '(Video)';
                    break;
                case 'audio':
                    $bodyText = '(Audio)';
                    break;
                case 'location':
                    $lat = $message['location']['latitude'] ?? '';
                    $long = $message['location']['longitude'] ?? '';
                    $name = $message['location']['name'] ?? '';
                    $bodyText = trim("Location: {$name} ({$lat}, {$long})");
                    break;
                case 'interactive':
                    $bodyText = $message['interactive']['button_reply']['title'] ??
                        ($message['interactive']['list_reply']['title'] ?? '(Interactive Reply)');
                    break;
                case 'button':
                    $bodyText = $message['button']['text'] ?? '(Button Click)';
                    break;
                default:
                    $bodyText = '(' . ucfirst($type) . ' Message)';
                    break;
            }

            // Extract media ID if applicable
            $mediaId = $message[$type]['id'] ?? null;
            $mediaUrl = null;

            if ($mediaId && $account) {
                $mediaUrl = app(WhatsAppMediaService::class)
                    ->downloadAndStore($mediaId, $account);
            }

            // Retrieve or match profile name
            $profileName = null;
            foreach ($contacts as $contact) {
                if (($contact['wa_id'] ?? null) === $sender) {
                    $profileName = $contact['profile']['name'] ?? null;
                    break;
                }
            }
            if (! $profileName && ! empty($contacts)) {
                $profileName = $contacts[0]['profile']['name'] ?? null;
            }

            Logger::info('Processing incoming WhatsApp message:', [
                'log_id' => $log->id,
                'message_id' => $messageId,
                'sender' => $sender,
                'profile_name' => $profileName,
                'type' => $type,
                'body' => $bodyText,
            ]);

            // Resolve/Create the contact
            $contactRecord = $resolveContactAction->execute(
                companyId: (int) $log->company_id,
                integrationAccountId: (int) $log->integration_account_id,
                phoneNumber: $sender,
                profileName: $profileName
            );

            // Parse sent_at timestamp
            $sentAt = isset($message['timestamp'])
                ? Carbon::createFromTimestamp((int) $message['timestamp'])
                : null;

            // Persist the message
            $persistMessageAction->execute(
                companyId: (int) $log->company_id,
                integrationAccountId: (int) $log->integration_account_id,
                whatsappContactId: (int) $contactRecord->id,
                messageId: $messageId,
                direction: MessageDirectionEnum::INBOUND,
                type: $type,
                body: $bodyText,
                mediaId: $mediaId,
                mediaUrl: $mediaUrl,
                status: 'received',
                rawPayload: $message,
                sentAt: $sentAt
            );

            // Mark the message as read via the provider API to update status for the sender (blue ticks)
            try {
                if ($account) {
                    $provider = app(CommunicationProviderInterface::class);
                    $provider->markAsRead($account, $messageId);
                    Logger::info("Marked inbound message {$messageId} as read.");
                }
            } catch (Throwable $e) {
                Logger::error("Failed to mark inbound message {$messageId} as read: {$e->getMessage()}");
            }

            // Optional: Auto-reply "Hello!" for test messages
            if (strtolower(trim($bodyText)) === 'hello') {
                try {
                    if ($account) {
                        $provider = app(CommunicationProviderInterface::class);

                        Logger::info("Sending automated test reply to {$sender}...");

                        $replyText = 'Hello! We received your message: "' . $bodyText . '"';
                        $response = $provider->sendMessage(
                            account: $account,
                            to: $sender,
                            text: $replyText
                        );

                        Logger::info('Automated test reply sent successfully.', [
                            'response' => $response,
                        ]);

                        // Persist the outbound auto-reply in our database so the chat log is complete
                        $outboundMessageId = $response['messages'][0]['id'] ?? null;

                        $persistMessageAction->execute(
                            companyId: (int) $log->company_id,
                            integrationAccountId: (int) $log->integration_account_id,
                            whatsappContactId: (int) $contactRecord->id,
                            messageId: $outboundMessageId,
                            direction: MessageDirectionEnum::OUTBOUND,
                            type: 'text',
                            body: $replyText,
                            status: 'sent',
                            rawPayload: $response,
                            sentAt: now()
                        );
                    }
                } catch (Throwable $e) {
                    Logger::error('Failed to send automated test reply: ' . $e->getMessage());
                }
            }
        }
    }
}
