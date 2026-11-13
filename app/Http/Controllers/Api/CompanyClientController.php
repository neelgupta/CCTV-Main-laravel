<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\ClientOutstandingAmountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyClientController extends Controller
{
    public function __construct(
        private readonly ClientOutstandingAmountService $clientOutstandingAmountService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        // abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can access client records.');

        $companyId = (int) $user->company_id;
        $search = trim((string) $request->query('search', ''));

        $query = Client::query()
            ->where('company_id', $companyId)
            ->with(['state:id,name', 'city:id,name,state_id'])
            ->with(['projects' => function ($projectsQuery) use ($companyId) {
                $projectsQuery->where('company_id', $companyId)->latest();
            }])
            ->withCount('amcs')
            ->latest();

        if ($search !== '') {
            $like = '%' . $search . '%';

            $query->where(function ($searchQuery) use ($like) {
                $searchQuery->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('mobile', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('gst_number', 'like', $like)
                    ->orWhere('pan_number', 'like', $like);
            });
        }

        $clients = $query->get()->map(function (Client $client): Client {
            $ledgerSummary = $this->clientOutstandingAmountService->ledgerSummary($client);

            $client->setAttribute('total_amount', $ledgerSummary['total_amount']);
            $client->setAttribute('total_received', $ledgerSummary['paid_amount']);
            $client->setAttribute('pending_amount', $ledgerSummary['pending_amount']);

            return $client;
        });

        return response()->json([
            'message' => 'Client records retrieved successfully.',
            'data' => $clients,
        ]);
    }
}
