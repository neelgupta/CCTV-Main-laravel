<?php

declare(strict_types=1);

namespace App\Modules\Communication\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Communication\Actions\CreateIntegrationAccountAction;
use App\Modules\Communication\Actions\ProcessWebhookPayloadAction;
use App\Modules\Communication\Actions\VerifyWebhookAction;
use App\Modules\Communication\Contracts\CommunicationProviderInterface;
use App\Modules\Communication\Contracts\IntegrationAccountRepositoryInterface;
use App\Modules\Communication\DTOs\IntegrationAccountData;
use App\Modules\Communication\Enums\ConnectionStatusEnum;
use App\Modules\Communication\Enums\ProviderEnum;
use App\Modules\Communication\Exceptions\IntegrationAccountNotFoundException;
use App\Modules\Communication\Http\Requests\ConnectRequest;
use App\Modules\Communication\Http\Requests\VerifyWebhookRequest;
use App\Modules\Communication\Models\IntegrationAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use App\Modules\Communication\Support\Logger;
use Throwable;

class WhatsAppIntegrationController extends Controller
{
    public function __construct(
        protected IntegrationAccountRepositoryInterface $accountRepository,
        protected CommunicationProviderInterface $provider
    ) {}

    /**
     * Render the WhatsApp Settings page.
     */
    public function settings()
    {
        $companyId = Auth::user()->company_id;
        $account = $this->accountRepository->getAllForCompany((int) $companyId)->first();

        return view('communication::settings', compact('account'));
    }

    /**
     * Webhook Handshake Verification (GET)
     */
    public function verifyWebhook(string $uuid, VerifyWebhookRequest $request, VerifyWebhookAction $action): Response
    {
        try {
            $challenge = $action->execute($uuid, $request->validated());

            return response($challenge, 200)->header('Content-Type', 'text/plain');
        } catch (Throwable $e) {
            Logger::error("Webhook verification failed: {$e->getMessage()}");

            return response('Verification failed: '.$e->getMessage(), 400);
        }
    }

    /**
     * Handle Incoming Webhook Events (POST)
     */
    public function handleWebhook(string $uuid, Request $request, ProcessWebhookPayloadAction $action): Response
    {
        try {
            $payload = $request->json()->all();
            $headers = $request->headers->all();

            $action->execute(
                uuid: $uuid,
                payload: $payload,
                headers: $headers,
                requestIp: $request->ip(),
                userAgent: $request->userAgent()
            );

            // Respond 200 OK immediately as required by Meta guidelines
            return response('*ok*', 200)->header('Content-Type', 'text/plain');
        } catch (Throwable $e) {
            Logger::error("Failed to log webhook: {$e->getMessage()}", [
                'ip' => $request->ip(),
            ]);

            // Return 200 to prevent Meta from disabling the webhook endpoint during transient failures,
            // but return error explanation in body for internal logs.
            return response('Error handled, logged internally', 200);
        }
    }

    /**
     * Store Meta Credentials (POST)
     */
    public function connect(ConnectRequest $request, CreateIntegrationAccountAction $action): \Symfony\Component\HttpFoundation\Response
    {
        try {
            $validated = $request->validated();

            // Inject company and provider details
            $validated['company_id'] = Auth::user()->company_id;
            $validated['provider'] = ProviderEnum::META_WHATSAPP->value;

            $dto = IntegrationAccountData::fromArray($validated);
            $account = $action->execute($dto);

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Integration account saved successfully.',
                    'data' => [
                        'uuid' => $account->uuid,
                        'display_name' => $account->display_name,
                        'phone_number' => $account->phone_number,
                        'status' => $account->status->label(),
                    ],
                ], 201);
            }

            return redirect()->route('integrations.whatsapp.settings')
                ->with('success', 'Integration account saved successfully.');
        } catch (Throwable $e) {
            Logger::error("Error saving integration account: {$e->getMessage()}");

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to connect. '.$e->getMessage(),
                ], 500);
            }

            return redirect()->route('integrations.whatsapp.settings')
                ->with('error', 'Failed to connect. '.$e->getMessage());
        }
    }

    /**
     * Manual Connection Verification (POST)
     */
    public function verifyConnection(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $request->validate([
            'uuid' => ['required', 'string', 'uuid'],
        ]);

        try {
            $companyId = Auth::user()->company_id;
            $account = $this->accountRepository->findByCompanyAndUuid((int) $companyId, $request->input('uuid'));

            if (! $account) {
                throw new IntegrationAccountNotFoundException('Integration account not found.');
            }

            // Verify using provider
            $isConnected = $this->provider->verifyConnection($account);
            $newStatus = $isConnected ? ConnectionStatusEnum::CONNECTED : ConnectionStatusEnum::FAILED;

            $this->accountRepository->update($account, [
                'status' => $newStatus->value,
                'last_verified_at' => now(),
                'connected_at' => $isConnected ? now() : $account->connected_at,
            ]);

            $message = $isConnected
                ? 'Connection verified successfully.'
                : 'Connection verification failed. Please check your credentials.';

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'is_connected' => $isConnected,
                    'message' => $message,
                    'data' => [
                        'status' => $newStatus->label(),
                        'last_verified_at' => now()->toDateTimeString(),
                    ],
                ]);
            }

            return redirect()->route('integrations.whatsapp.settings')
                ->with($isConnected ? 'success' : 'error', $message);
        } catch (Throwable $e) {
            Logger::error("Connection verification exception: {$e->getMessage()}");

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 500);
            }

            return redirect()->route('integrations.whatsapp.settings')
                ->with('error', 'Connection verification failed: '.$e->getMessage());
        }
    }

    /**
     * Get Connection Statuses (GET)
     */
    public function getStatus(): JsonResponse
    {
        try {
            $companyId = Auth::user()->company_id;
            $accounts = $this->accountRepository->getAllForCompany((int) $companyId);

            $formatted = $accounts->map(fn (IntegrationAccount $acc) => [
                'uuid' => $acc->uuid,
                'display_name' => $acc->display_name,
                'phone_number' => $acc->phone_number,
                'phone_number_id' => $acc->phone_number_id,
                'business_account_id' => $acc->business_account_id,
                'status' => $acc->status->label(),
                'connected_at' => $acc->connected_at?->toDateTimeString(),
                'last_verified_at' => $acc->last_verified_at?->toDateTimeString(),
            ]);

            return response()->json([
                'success' => true,
                'data' => $formatted,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Could not fetch connection statuses.',
            ], 500);
        }
    }
}
