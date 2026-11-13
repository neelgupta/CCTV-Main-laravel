<?php

namespace App\Http\Controllers;

use App\Models\EmailBounce;
use App\Models\EmailLog;
use App\Models\EmailOpen;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailTrackingWebhookController extends Controller
{
    public function open(Request $request): JsonResponse
    {
        $token = $request->query('token');
        $emailLog = EmailLog::query()->where('tracking_token', $token)->first();

        if (! $emailLog) {
            return response()->json(['error' => 'Invalid token'], 404);
        }

        $open = EmailOpen::where('email_log_id', $emailLog->id)->first();
        if (! $open) {
            EmailOpen::create([
                'email_log_id' => $emailLog->id,
                'campaign_id' => $emailLog->campaign_id,
                'campaign_recipient_id' => $emailLog->campaign_recipient_id,
                'opened_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'device_info' => $request->header('User-Agent'),
            ]);
        }

        $emailLog->update([
            'status' => 'opened',
            'delivery_status' => 'opened',
            'opened_at' => now(),
        ]);

        return response()->json(['message' => 'Open tracked']);
    }

    public function click(Request $request): \Illuminate\Http\RedirectResponse
    {
        $token = $request->query('token');
        $url = $request->query('url');

        $emailLog = EmailLog::query()->where('tracking_token', $token)->first();

        if ($emailLog && $url) {
            $click = $emailLog->clicks()->where('clicked_url', $url)->first();
            if (! $click) {
                $emailLog->clicks()->create([
                    'campaign_id' => $emailLog->campaign_id,
                    'campaign_recipient_id' => $emailLog->campaign_recipient_id,
                    'clicked_url' => $url,
                    'clicked_at' => now(),
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                ]);
            }

            $emailLog->update([
                'status' => 'clicked',
                'delivery_status' => 'clicked',
                'clicked_at' => now(),
            ]);
        }

        return redirect()->away($url ?: url('/'));
    }

    public function deliveryWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $messageId = $payload['message_id'] ?? $payload['MessageId'] ?? data_get($payload, 'event-data.message.headers.message-id');

        if (! $messageId) {
            Log::warning('Email delivery webhook received without message_id', ['payload' => $payload]);

            return response()->json(['error' => 'Missing message_id'], 400);
        }

        $emailLog = EmailLog::query()->where('message_id', $messageId)->first();

        if (! $emailLog) {
            return response()->json(['error' => 'Log not found'], 404);
        }

        $event = $payload['event'] ?? $payload['event-type'] ?? data_get($payload, 'event-data.event');

        $updates = match ($event) {
            'delivered', 'delivery' => [
                'status' => 'delivered',
                'delivery_status' => 'delivered',
                'delivered_at' => now(),
            ],
            'open', 'opened' => [
                'status' => 'opened',
                'delivery_status' => 'opened',
                'opened_at' => now(),
            ],
            'click', 'clicked' => [
                'status' => 'clicked',
                'delivery_status' => 'clicked',
                'clicked_at' => now(),
            ],
            'bounce', 'bounced', 'failed', 'complaint' => [
                'status' => 'bounced',
                'delivery_status' => 'bounced',
                'failed_at' => now(),
                'error_message' => $payload['reason'] ?? $payload['description'] ?? data_get($payload, 'event-data.reason'),
            ],
            default => null,
        };

        if ($updates) {
            $emailLog->update($updates);

            if (in_array($event, ['bounce', 'bounced', 'failed', 'complaint'])) {
                EmailBounce::create([
                    'email_log_id' => $emailLog->id,
                    'campaign_id' => $emailLog->campaign_id,
                    'campaign_recipient_id' => $emailLog->campaign_recipient_id,
                    'bounce_type' => $payload['bounce_type'] ?? ($event === 'complaint' ? 'complaint' : 'hard'),
                    'reason' => $updates['error_message'] ?? 'Webhook bounce',
                    'bounced_at' => now(),
                    'provider_payload' => $payload,
                ]);
            }
        }

        return response()->json(['message' => 'Webhook processed']);
    }

    public function whatsAppWebhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $messageId = $payload['message_id'] ?? data_get($payload, 'entry.0.changes.0.value.messages.0.id');

        if (! $messageId) {
            return response()->json(['error' => 'Missing message_id'], 400);
        }

        $log = \App\Models\WhatsAppLog::query()->where('provider_message_id', $messageId)->first();

        if (! $log) {
            return response()->json(['error' => 'Log not found'], 404);
        }

        $status = $payload['status'] ?? data_get($payload, 'entry.0.changes.0.value.statuses.0.status');

        $updates = match ($status) {
            'sent' => ['status' => 'sent', 'sent_at' => now()],
            'delivered' => ['status' => 'delivered', 'delivered_at' => now()],
            'read' => ['status' => 'read', 'read_at' => now()],
            'failed' => [
                'status' => 'failed',
                'failed_at' => now(),
                'error_message' => $payload['error'] ?? data_get($payload, 'entry.0.changes.0.value.statuses.0.errors.0.title', 'Delivery failed'),
            ],
            default => null,
        };

        if ($updates) {
            $log->update($updates);
        }

        return response()->json(['message' => 'WhatsApp webhook processed']);
    }
}
