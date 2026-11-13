<?php

namespace App\Http\Controllers;

use App\Services\RazorpayService;
use App\Services\RazorpayWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, RazorpayService $razorpay, RazorpayWebhookService $webhooks): JsonResponse
    {
        $body = $request->getContent();

        if (! $razorpay->verifyWebhookSignature($body, (string) $request->header('X-Razorpay-Signature'))) {
            Log::warning('Razorpay webhook rejected: invalid signature.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Invalid signature.'], 400);
        }

        $payload = json_decode($body, true);

        if (! is_array($payload) || ! filled($payload['event'] ?? null)) {
            return response()->json(['message' => 'Invalid payload.'], 400);
        }

        // Razorpay sends the same event id on retries; fall back to the body hash.
        $eventId = (string) ($request->header('X-Razorpay-Event-Id') ?: hash('sha256', $body));

        try {
            $result = $webhooks->process($eventId, $payload);
        } catch (Throwable $exception) {
            report($exception);

            // A non-2xx response makes Razorpay retry the delivery.
            return response()->json(['message' => 'Processing failed.'], 500);
        }

        return response()->json(['status' => $result]);
    }
}
