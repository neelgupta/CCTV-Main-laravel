<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientQrController extends Controller
{
    public function generate(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClient($client);

        if (blank($client->qr_token)) {
            $client->ensureQrToken();
        }

        return back()->with('success', 'Customer QR code is ready.');
    }

    public function regenerate(Request $request, Client $client): RedirectResponse
    {
        $this->authorizeClient($client);

        $client->regenerateQrToken();

        return back()->with('success', 'Customer QR code regenerated successfully. Old QR code is now invalid.');
    }

    public function print(Client $client): View
    {
        $this->authorizeClient($client);

        $client->ensureQrToken();
        $client->loadMissing(['company:id,name,logo,print_logo']);

        $publicLink = route('customer.support.qr.show', ['token' => $client->qr_token]);

        return view('clients.qr-print', [
            'client' => $client,
            'publicLink' => $publicLink,
            'qrImageUrl' => $this->qrImageUrl($publicLink),
            'company' => $client->company,
        ]);
    }

    private function authorizeClient(Client $client): void
    {
        $user = auth()->user();

        abort_unless($user && (int) $client->company_id === (int) $user->company_id, 403);
        abort_unless($user->isCompanyAdmin() || $user->hasPermission('settings.users.manage'), 403);
    }

    private function qrImageUrl(string $publicLink): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=360x360&margin=8&data=' . urlencode($publicLink);
    }
}
