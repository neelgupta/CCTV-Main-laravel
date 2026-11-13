<?php

namespace App\Services;

use App\Exceptions\RazorpayAuthenticationException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class RazorpayService
{
    private const BASE_URL = 'https://api.razorpay.com/v1';

    public function keyId(): string
    {
        return (string) config('services.razorpay.key_id');
    }

    public function keySecret(): string
    {
        return (string) config('services.razorpay.key_secret');
    }

    public function webhookSecret(): string
    {
        return (string) config('services.razorpay.webhook_secret');
    }

    public function mode(): string
    {
        return config('services.razorpay.mode') === 'test' ? 'test' : 'live';
    }

    public function currency(): string
    {
        return (string) config('services.razorpay.currency', 'INR');
    }

    public function enabled(): bool
    {
        return $this->keyId() !== '' && $this->keySecret() !== '';
    }

    public function createOrder(int $amountPaise, string $receipt, array $notes = []): array
    {
        $response = $this->client()
            ->asForm()
            ->post(self::BASE_URL . '/orders', [
                'amount' => $amountPaise,
                'currency' => $this->currency(),
                'receipt' => $receipt,
                'payment_capture' => 1,
                'notes' => $notes,
            ]);

        return $this->decode($response, 'Unable to create Razorpay order.');
    }

    /**
     * @param  string  $period  monthly|yearly
     */
    public function createPlan(string $period, int $amountPaise, string $name, array $notes = []): array
    {
        $response = $this->client()
            ->asJson()
            ->post(self::BASE_URL . '/plans', [
                'period' => $period,
                'interval' => 1,
                'item' => [
                    'name' => $name,
                    'amount' => $amountPaise,
                    'currency' => $this->currency(),
                ],
                'notes' => (object) $notes,
            ]);

        return $this->decode($response, 'Unable to create Razorpay plan.');
    }

    public function fetchPlan(string $planId): array
    {
        $response = $this->client()->get(self::BASE_URL . '/plans/' . urlencode($planId));

        return $this->decode($response, 'Unable to fetch Razorpay plan.');
    }

    /**
     * @param  int|null  $startAt  Unix timestamp of the first charge; null charges on authorisation.
     */
    public function createSubscription(string $planId, int $totalCount, int $quantity = 1, ?int $startAt = null, array $notes = []): array
    {
        $payload = [
            'plan_id' => $planId,
            'total_count' => $totalCount,
            'quantity' => max(1, $quantity),
            'customer_notify' => 1,
            'notes' => (object) $notes,
        ];

        if ($startAt !== null) {
            $payload['start_at'] = $startAt;
        }

        $response = $this->client()
            ->asJson()
            ->post(self::BASE_URL . '/subscriptions', $payload);

        return $this->decode($response, 'Unable to create Razorpay subscription.');
    }

    public function fetchSubscription(string $subscriptionId): array
    {
        $response = $this->client()->get(self::BASE_URL . '/subscriptions/' . urlencode($subscriptionId));

        return $this->decode($response, 'Unable to fetch Razorpay subscription.');
    }

    public function fetchPayment(string $paymentId): array
    {
        $response = $this->client()->get(self::BASE_URL . '/payments/' . urlencode($paymentId));

        return $this->decode($response, 'Unable to fetch Razorpay payment.');
    }

    public function cancelSubscription(string $subscriptionId, bool $atCycleEnd = true): array
    {
        $response = $this->client()
            ->asJson()
            ->post(self::BASE_URL . '/subscriptions/' . urlencode($subscriptionId) . '/cancel', [
                'cancel_at_cycle_end' => $atCycleEnd ? 1 : 0,
            ]);

        return $this->decode($response, 'Unable to cancel Razorpay subscription.');
    }

    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        if ($this->keySecret() === '' || $orderId === '' || $paymentId === '' || $signature === '') {
            return false;
        }

        $payload = $orderId . '|' . $paymentId;
        $expectedSignature = hash_hmac('sha256', $payload, $this->keySecret());

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Checkout signature for a subscription payment (note the order: payment id first).
     */
    public function verifySubscriptionSignature(string $paymentId, string $subscriptionId, string $signature): bool
    {
        if ($this->keySecret() === '' || $signature === '') {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', $paymentId . '|' . $subscriptionId, $this->keySecret());

        return hash_equals($expectedSignature, $signature);
    }

    public function verifyWebhookSignature(string $body, string $signature): bool
    {
        if ($this->webhookSecret() === '' || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $body, $this->webhookSecret()), $signature);
    }

    private function client(): PendingRequest
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Razorpay credentials are not configured.');
        }

        $client = Http::withBasicAuth($this->keyId(), $this->keySecret())->timeout(30);

        if (! config('services.razorpay.verify_ssl', true)) {
            $client = $client->withoutVerifying();
        }

        return $client;
    }

    private function decode(Response $response, string $fallbackMessage): array
    {
        if ($response->successful()) {
            return (array) $response->json();
        }

        if ($response->status() === 401) {
            throw new RazorpayAuthenticationException(sprintf(
                'Razorpay rejected the %s Key ID / Key Secret. Make sure both come from the same key pair in Razorpay %s mode, then save them in Global Settings.',
                $this->mode(),
                $this->mode()
            ));
        }

        $description = $response->json('error.description')
            ?? $response->json('error.message')
            ?? ($response->body() ?: $fallbackMessage);

        throw new RuntimeException($description);
    }
}
