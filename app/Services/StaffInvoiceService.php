<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketInvoice;
use App\Models\TicketReply;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class StaffInvoiceService
{
    public function getInvoices(int $companyId, int $staffId): Collection
    {
        return TicketInvoice::query()
            ->where('company_id', $companyId)
            ->where('staff_id', $staffId)
            ->with([
                'ticket:id,ticket_number,customer_name,visit_date,assigned_to,company_id',
                'invoice:id,document_number,created_at',
                'receivedByAdminBy:id,name',
            ])
            ->latest('created_at')
            ->get();
    }

    public function getSummary(int $companyId, int $staffId): array
    {
        $row = DB::table('ticket_invoices as ti')
            ->where('ti.company_id', $companyId)
            ->where('ti.staff_id', $staffId)
            ->selectRaw('COUNT(*) as total_invoices')
            ->selectRaw('COALESCE(SUM(COALESCE(ti.total_amount, ti.invoice_total, 0)), 0) as total_invoice_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ti.payment_status = 1 THEN COALESCE(ti.total_amount, ti.invoice_total, 0) ELSE 0 END), 0) as paid_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ti.payment_status = 0 THEN COALESCE(ti.total_amount, ti.invoice_total, 0) ELSE 0 END), 0) as pending_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ti.received_by_admin = 1 THEN COALESCE(ti.total_amount, ti.invoice_total, 0) ELSE 0 END), 0) as received_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN ti.payment_status = 1 THEN 1 ELSE 0 END), 0) as paid_invoice_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN ti.payment_status = 0 THEN 1 ELSE 0 END), 0) as pending_invoice_count')
            ->first();

        $paidAmount = (float) ($row->paid_amount ?? 0);
        $receivedAmount = (float) ($row->received_amount ?? 0);

        return [
            'total_invoices' => (int) ($row->total_invoices ?? 0),
            'total_invoice_amount' => (float) ($row->total_invoice_amount ?? 0),
            'paid_amount' => $paidAmount,
            'pending_amount' => (float) ($row->pending_amount ?? 0),
            'received_amount' => $receivedAmount,
            'remaining_amount' => max(0, round($paidAmount - $receivedAmount, 2)),
            'paid_invoice_count' => (int) ($row->paid_invoice_count ?? 0),
            'pending_invoice_count' => (int) ($row->pending_invoice_count ?? 0),
        ];
    }

    public function updateReceivedByAdmin(TicketInvoice $ticketInvoice, User $actor, bool $received): TicketInvoice
    {
        $ticketInvoice->loadMissing('ticket');

        abort_unless($ticketInvoice->ticket, 404);

        return DB::transaction(function () use ($ticketInvoice, $actor, $received): TicketInvoice {
            $ticketInvoice->forceFill([
                'received_by_admin' => $received,
                'received_by_admin_at' => $received ? Carbon::now() : null,
                'received_by_admin_by' => $received ? $actor->id : null,
            ])->save();

            $this->writeTimelineEntries(
                $ticketInvoice->ticket,
                $actor,
                [
                    $received ? 'Amount Received By Admin' : 'Amount Marked Pending By Admin',
                    $received
                        ? 'Payment Received (' . $this->paymentMethodLabel($ticketInvoice->payment_method) . ')'
                        : 'Invoice Pending With Staff',
                ]
            );

            return $ticketInvoice->refresh()->loadMissing([
                'ticket:id,ticket_number,customer_name,visit_date,assigned_to,company_id',
                'invoice:id,document_number,created_at',
                'receivedByAdminBy:id,name',
            ]);
        });
    }

    /**
     * @param array<int, string> $messages
     */
    private function writeTimelineEntries(Ticket $ticket, User $actor, array $messages): void
    {
        foreach ($messages as $message) {
            TicketReply::query()->create([
                'ticket_id' => $ticket->id,
                'user_id' => $actor->id,
                'message' => $message,
            ]);
        }
    }

    private function paymentMethodLabel(?int $paymentMethod): string
    {
        return match ((int) $paymentMethod) {
            0 => 'Cash',
            1 => 'Online',
            2 => 'Cash',
            default => 'Unknown',
        };
    }
}
