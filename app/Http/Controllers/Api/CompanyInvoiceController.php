<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyInvoiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user?->isCompanyAdmin(), 403, 'Only company admins can view invoices.');

        $companyId = (int) $user->company_id;
        $perPage = max(1, min(100, (int) $request->integer('per_page', 15)));

        $invoices = TicketInvoice::query()
            ->with(['ticket:id,ticket_number,company_id', 'client:id,name', 'staff:id,name', 'creator:id,name', 'income:id,invoice_id,amount,payment_method,received_date'])
            ->where('company_id', $companyId)
            ->when(filled($request->query('search')), function ($builder) use ($request): void {
                $search = trim((string) $request->query('search'));
                $builder->where(function ($inner) use ($search): void {
                    $inner->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('ticket', fn ($ticketQuery) => $ticketQuery->where('ticket_number', 'like', "%{$search}%"))
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('staff', fn ($staffQuery) => $staffQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->when(filled($request->query('ticket_id')), fn ($builder) => $builder->where('ticket_id', (int) $request->query('ticket_id')))
            ->when(filled($request->query('client_id')), fn ($builder) => $builder->where('client_id', (int) $request->query('client_id')))
            ->when(filled($request->query('staff_id')), fn ($builder) => $builder->where('staff_id', (int) $request->query('staff_id')))
            ->when(filled($request->query('payment_status')), fn ($builder) => $builder->where('payment_status', (int) $request->query('payment_status')))
            ->when(filled($request->query('payment_method')), fn ($builder) => $builder->where('payment_method', (int) $request->query('payment_method')))
            ->orderByDesc('id');

        return response()->json([
            'message' => 'Invoice list fetched successfully.',
            'data' => [
                'invoices' => $invoices->paginate($perPage)->withQueryString(),
            ],
        ]);
    }
}
