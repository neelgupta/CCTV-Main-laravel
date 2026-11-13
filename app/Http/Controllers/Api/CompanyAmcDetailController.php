<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amc;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyAmcDetailController extends Controller
{
    public function show(Request $request, int $amcId): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can access AMC details.');

        $amc = Amc::query()
            ->where('company_id', (int) $user->company_id)
            ->with([
                'project:id,name,uuid,company_id',
                'client:id,name,email,mobile,phone,address,company_id',
                'amcPlan:id,name,duration_months,price,company_id',
                'assignedStaff:id,name,contact_number,company_id',
                'assignedStaff.user:id,name,email',
                'visits.histories',
                'visits.ticketInvoice.invoice',
                'supportTicket:id,ticket_number,subject,status,company_id,generated_from_amc_id,assigned_to,visit_date',
                'supportTicket.assignedUser:id,name,email',
                'supportTicket.generatedFromAmc:id,contract_no,project_name,customer_name,client_id,project_id',
            ])
            ->findOrFail($amcId);

        $supportTickets = $this->supportTicketsForAmc($amc);

        return response()->json([
            'message' => 'AMC details retrieved successfully.',
            'data' => [
                'amc' => $amc,
                // 'client' => $amc->client,
                'support_ticket' => $supportTickets,
            ],
        ]);
    }

    private function supportTicketsForAmc(Amc $amc)
    {
        $ticketIds = collect();

        if ($amc->support_ticket_id) {
            $ticketIds->push((int) $amc->support_ticket_id);
        }

        $ticketIds = $ticketIds
            ->merge(
                Ticket::query()
                    ->where('company_id', (int) $amc->company_id)
                    ->where('generated_from_amc_id', $amc->id)
                    ->pluck('id')
            )
            ->merge(
                $amc->visits()
                    ->whereNotNull('support_ticket_id')
                    ->pluck('support_ticket_id')
            )
            ->filter()
            ->unique()
            ->values();

        if ($ticketIds->isEmpty()) {
            return collect();
        }

        return Ticket::query()
            ->where('company_id', (int) $amc->company_id)
            ->whereIn('id', $ticketIds->all())
            ->with([
                'assignedUser:id,name,email',
                'generatedFromAmc:id,contract_no,project_name,customer_name,client_id,project_id',
                'amc:id,contract_no,project_name,customer_name,client_id,project_id,support_ticket_id',
                'replies.user:id,name,email',
            ])
            ->latest()
            ->get();
    }
}
