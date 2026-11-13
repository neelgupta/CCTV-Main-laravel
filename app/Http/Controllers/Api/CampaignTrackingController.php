<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Campaign\Contracts\EmailLogRepositoryInterface;
use App\Modules\Campaign\Services\CampaignEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CampaignTrackingController extends Controller
{
    public function __construct(
        private readonly EmailLogRepositoryInterface $emailLogs,
        private readonly CampaignEmailService $emailService,
    ) {
    }

    public function open(Request $request): Response
    {
        $token = (string) $request->query('token', '');
        $emailLog = $this->emailLogs->findByTrackingToken($token);

        if ($emailLog) {
            $this->emailService->trackOpen($emailLog, [
                'ip_address' => $request->ip(),
                'device_info' => $request->header('Sec-CH-UA-Platform'),
                'user_agent' => $request->userAgent(),
            ]);
        }

        $pixel = base64_decode('R0lGODlhAQABAPAAAAAAAAAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw==');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function click(Request $request): RedirectResponse
    {
        $token = (string) $request->query('token', '');
        $url = (string) $request->query('url', url('/'));
        $emailLog = $this->emailLogs->findByTrackingToken($token);

        if ($emailLog) {
            $this->emailService->trackClick($emailLog, $url, [
                'ip_address' => $request->ip(),
                'device_info' => $request->header('Sec-CH-UA-Platform'),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return redirect()->away($url);
    }
}
