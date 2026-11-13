<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amc;
use App\Models\Client;
use App\Models\Lead;
use App\Models\Ticket;
use App\Services\ClientOutstandingAmountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CompanyClientDetailController extends Controller
{
    public function __construct(
        private readonly ClientOutstandingAmountService $clientOutstandingAmountService
    ) {
    }

    public function show(Request $request, int $clientId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403, 'Unauthorized access to client details.');

        $client = Client::query()
            ->where('company_id', (int) $user->company_id)
            ->with(['user:id,name,email', 'state:id,name', 'city:id,name,state_id', 'company:id,name,logo,print_logo'])
            ->findOrFail($clientId);

        $projects = $this->projects($client);
        $leads = $this->leads($client);
        $amcs = $this->amcs($client);
        $supportTickets = $this->supportTickets($client);

        return response()->json([
            'message' => 'Client details retrieved successfully.',
            'data' => [
                'client' => $client,
                'pending_amount' => $this->clientOutstandingAmountService->getTotal($client),
                'projects' => $projects,
                'leads' => $leads,
                'amcs' => $amcs,
                'support_tickets' => $supportTickets,
            ],
        ]);
    }

    private function projects(Client $client): Collection
    {
        return \App\Models\Project::query()
            ->where('company_id', $client->company_id)
            ->where('client_name', $client->name)
            ->latest()
            ->get();
    }

    private function leads(Client $client): Collection
    {
        return Lead::query()
            ->where('company_id', $client->company_id)
            ->where(function ($query) use ($client) {
                $query->where('client_name', $client->name);
                if ($client->email) {
                    $query->orWhere('email', $client->email);
                }
                if ($client->mobile) {
                    $query->orWhere('mobile', $client->mobile);
                }
            })
            ->latest()
            ->get();
    }

    private function amcs(Client $client): Collection
    {
        return Amc::query()
            ->where('company_id', $client->company_id)
            ->where(function ($query) use ($client) {
                $query->where('client_id', $client->id)
                    ->orWhere('customer_name', $client->name);

                if ($client->email) {
                    $query->orWhere('email', $client->email);
                }

                if ($client->mobile) {
                    $query->orWhere('mobile_number', $client->mobile);
                }

                if ($client->phone) {
                    $query->orWhere('mobile_number', $client->phone);
                }
            })
            ->with([
                'project:id,name,uuid,company_id',
                'client:id,name,email,mobile,phone,company_id',
                'amcPlan:id,name,duration_months,price,company_id',
                'assignedStaff:id,name,contact_number,company_id',
                'supportTicket:id,ticket_number,subject,status,company_id,generated_from_amc_id,assigned_to,visit_date',
                'supportTicket.generatedFromAmc:id,contract_no,project_name,customer_name,client_id,project_id',
            ])
            ->latest()
            ->get();
    }

    private function supportTickets(Client $client): Collection
    {
        $name = trim((string) $client->name);
        $email = filled($client->email) ? Str::lower(trim((string) $client->email)) : null;
        $phones = collect([$client->mobile, $client->phone])
            ->map(fn ($value) => preg_replace('/\D+/', '', (string) $value) ?: null)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $address = $this->normalizeClientAddressForMatch($client->address);

        return Ticket::query()
            ->where('company_id', (int) $client->company_id)
            ->whereIn('type', ['complain', 'support'])
            ->where(function ($query) use ($client, $name, $email, $phones, $address) {
                $hasCondition = false;

                if ($email) {
                    $query->whereRaw('LOWER(customer_email) = ?', [$email]);
                    $hasCondition = true;
                }

                foreach ($phones as $phone) {
                    if ($hasCondition) {
                        $query->orWhere('customer_contact_number', 'like', '%' . $phone . '%');
                    } else {
                        $query->where('customer_contact_number', 'like', '%' . $phone . '%');
                        $hasCondition = true;
                    }
                }

                if ($address) {
                    if ($hasCondition) {
                        $query->orWhereRaw('LOWER(TRIM(customer_address)) = ?', [$address]);
                    } else {
                        $query->whereRaw('LOWER(TRIM(customer_address)) = ?', [$address]);
                        $hasCondition = true;
                    }
                }

                if ($name !== '') {
                    if ($hasCondition) {
                        $query->where('customer_name', $name);
                    } else {
                        $query->where('customer_name', $name);
                        $hasCondition = true;
                    }
                }

                $query->orWhereHas('generatedFromAmc', function ($amcQuery) use ($client, $name) {
                    $amcQuery->where('client_id', (int) $client->id);

                    if ($name !== '') {
                        $amcQuery->orWhere('customer_name', $name);
                    }
                });

                $query->orWhereHas('amc', function ($amcQuery) use ($client, $name) {
                    $amcQuery->where('client_id', (int) $client->id);

                    if ($name !== '') {
                        $amcQuery->orWhere('customer_name', $name);
                    }
                });

                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->with([
                'assignedUser:id,name',
                'generatedFromAmc:id,contract_no,project_name,customer_name,client_id,project_id',
                'amc:id,contract_no,project_name,customer_name,client_id,project_id,support_ticket_id',
            ])
            ->latest()
            ->get();
    }

    private function normalizeClientAddressForMatch(?string $address): ?string
    {
        $value = trim((string) $address);

        if ($value === '') {
            return null;
        }

        return Str::lower((string) preg_replace('/\s+/', ' ', $value));
    }
}
