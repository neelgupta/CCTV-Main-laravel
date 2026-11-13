<?php

namespace App\Services;

use App\Models\LeadQuotation;
use App\Models\Staff;
use App\Models\TicketInvoice;
use App\Models\TicketQuote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ServiceCallListingService
{
    /**
     * @return Collection<int, Staff>
     */
    public function staffOptions(int $companyId): Collection
    {
        return Staff::query()
            ->where('company_id', $companyId)
            ->whereHas('user')
            ->orderBy('name')
            ->get(['id', 'name', 'user_id']);
    }

    public function invoiceQuery(int $companyId, array $filters, ?User $user = null): Builder
    {
        $query = TicketInvoice::query()
            ->where('company_id', $companyId)
            ->with([
                'ticket:id,company_id,user_id,assigned_to,project_id,ticket_number,customer_contact_number,customer_name,customer_email,subject,created_at',
                'ticket.project:id,lead_id,client_name,name',
                'ticket.assignedUser:id,name',
                'invoice:id,document_number,created_at',
                'client:id,name,email,mobile,phone',
                'staff:id,name,user_id',
                'creator:id,name',
            ]);

        if ($user && ! $user->isCompanyAdmin()) {
            $staffId = $user->staff?->id;
            $query->where('staff_id', $staffId ?? -1);
        }

        $this->applyInvoiceFilters($query, $filters);

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function quotationRecords(int $companyId, array $filters): Collection
    {
        $leadRecords = $this->leadQuotationQuery($companyId, $filters)
            ->get()
            ->map(fn (LeadQuotation $quotation) => $this->normalizeLeadQuotationRecord($quotation))
            ->toBase();

        $ticketRecords = $this->ticketQuotationQuery($companyId, $filters)
            ->get()
            ->map(fn (TicketQuote $ticketQuote) => $this->normalizeTicketQuotationRecord($ticketQuote))
            ->toBase();

        $clientRecords = $this->clientQuotationQuery($companyId, $filters)
            ->get()
            ->map(fn (TicketQuote $ticketQuote) => $this->normalizeClientQuotationRecord($ticketQuote))
            ->toBase();

        return $leadRecords
            ->merge($ticketRecords)
            ->merge($clientRecords)
            ->sortByDesc(fn (array $record) => $record['sort_key'])
            ->values();
    }

    public function invoiceStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'unpaid' => 'Unpaid',
            'partially_paid' => 'Partially Paid',
            'paid' => 'Paid',
            'cancelled' => 'Cancelled',
            'overdue' => 'Overdue',
        ];
    }

    public function quotationStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'sent' => 'Sent',
            'accepted' => 'Accepted',
            'declined' => 'Declined',
            'expired' => 'Expired',
            'quotation' => 'Quotation',
        ];
    }

    public function invoiceStatusLabel(TicketInvoice $invoice): string
    {
        $paymentStatus = (int) ($invoice->payment_status ?? -1);
        $ageInDays = optional($invoice->created_at)->diffInDays(now()) ?? 0;

        return match (true) {
            $paymentStatus === 1 => 'Paid',
            $paymentStatus === 2 => 'Partially Paid',
            $paymentStatus === 3 => 'Cancelled',
            $paymentStatus === 0 && $ageInDays > 30 => 'Overdue',
            $paymentStatus === 0 => 'Unpaid',
            default => 'Draft',
        };
    }

    public function quotationStatusLabel(LeadQuotation $quotation): string
    {
        $status = strtolower(trim((string) $quotation->status));

        return match ($status) {
            'sent' => 'Sent',
            'accepted', 'approved' => 'Accepted',
            'declined', 'rejected' => 'Declined',
            'expired' => 'Expired',
            default => 'Draft',
        };
    }

    public function ticketQuotationStatusLabel(): string
    {
        return 'Quotation';
    }

    public function invoiceStatusTone(string $status): string
    {
        return match ($status) {
            'Paid' => 'emerald',
            'Partially Paid' => 'amber',
            'Overdue' => 'rose',
            'Cancelled' => 'slate',
            'Draft' => 'sky',
            default => 'zinc',
        };
    }

    public function quotationStatusTone(string $status): string
    {
        return match ($status) {
            'Accepted' => 'emerald',
            'Sent' => 'sky',
            'Declined' => 'rose',
            'Expired' => 'slate',
            'Quotation' => 'indigo',
            'Draft' => 'zinc',
            default => 'slate',
        };
    }

    private function applyInvoiceFilters(Builder $query, array $filters): void
    {
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $staffId = $this->normalizeFilterId($filters['staff_id'] ?? null);
        $search = trim((string) ($filters['search'] ?? ''));
        $startDate = $this->normalizeDateFilter($filters['date_from'] ?? ($filters['start_date'] ?? null));
        $endDate = $this->normalizeDateFilter($filters['date_to'] ?? ($filters['end_date'] ?? null));

        $query
            ->when($startDate, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $endDate))
            ->when($staffId, fn (Builder $builder) => $builder->where('staff_id', $staffId))
            ->when($status !== '', function (Builder $builder) use ($status): void {
                match ($status) {
                    'draft' => $builder->whereNull('payment_status'),
                    'unpaid' => $builder->where('payment_status', 0),
                    'partially_paid' => $builder->where('payment_status', 2),
                    'paid' => $builder->where('payment_status', 1),
                    'cancelled' => $builder->where('payment_status', 3),
                    'overdue' => $builder->where(function (Builder $statusQuery): void {
                        $statusQuery->where('payment_status', 0)
                            ->whereDate('created_at', '<', Carbon::now()->subDays(30)->toDateString());
                    }),
                    default => null,
                };
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $like = '%' . $search . '%';

                    $searchQuery
                        ->where('invoice_number', 'like', $like)
                        ->orWhereHas('ticket', function (Builder $ticketQuery) use ($like): void {
                            $ticketQuery->where('ticket_number', 'like', $like)
                                ->orWhere('customer_name', 'like', $like)
                                ->orWhere('customer_contact_number', 'like', $like)
                                ->orWhere('customer_email', 'like', $like)
                                ->orWhereHas('project', function (Builder $projectQuery) use ($like): void {
                                    $projectQuery->where('client_name', 'like', $like)
                                        ->orWhere('name', 'like', $like);
                                });
                        })
                        ->orWhereHas('client', function (Builder $clientQuery) use ($like): void {
                            $clientQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('mobile', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        })
                        ->orWhereHas('staff', function (Builder $staffQuery) use ($like): void {
                            $staffQuery->where('name', 'like', $like);
                        });
                });
            });
    }

    private function leadQuotationQuery(int $companyId, array $filters): Builder
    {
        $query = LeadQuotation::query()
            ->whereHas('lead', fn (Builder $leadQuery) => $leadQuery->where('company_id', $companyId))
            ->with([
                'lead:id,uuid,company_id,assigned_to,client_name,email,mobile,source,created_at',
                'lead.project:id,lead_id,client_name,name',
                'lead.assignedTo.staff:id,user_id,name',
                'lead.assignedTo:id,name',
            ]);

        $this->applyLeadQuotationFilters($query, $filters);

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function ticketQuotationQuery(int $companyId, array $filters): Builder
    {
        $query = TicketQuote::query()
            ->whereHas('ticket', fn (Builder $ticketQuery) => $ticketQuery->where('company_id', $companyId))
            ->with([
                'ticket:id,company_id,user_id,assigned_to,project_id,ticket_number,customer_name,customer_contact_number,customer_email,created_at',
                'ticket.project:id,lead_id,client_name,name',
                'ticket.assignedUser.staff:id,user_id,name',
                'ticket.assignedUser:id,name',
                'quote:id,document_number,created_at,total',
                'creator:id,name',
            ]);

        $this->applyTicketQuotationFilters($query, $filters);

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function clientQuotationQuery(int $companyId, array $filters): Builder
    {
        $query = TicketQuote::query()
            ->whereNull('ticket_id')
            ->whereHas('client', fn (Builder $clientQuery) => $clientQuery->where('company_id', $companyId))
            ->with([
                'client:id,company_id,name,email,mobile,phone',
                'quote:id,document_number,created_at,total',
                'creator:id,name',
            ]);

        $this->applyClientQuotationFilters($query, $filters);

        return $query->orderByDesc('created_at')->orderByDesc('id');
    }

    private function applyClientQuotationFilters(Builder $query, array $filters): void
    {
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $search = trim((string) ($filters['search'] ?? ''));
        $startDate = $this->normalizeDateFilter($filters['start_date'] ?? null);
        $endDate = $this->normalizeDateFilter($filters['end_date'] ?? null);

        $query
            ->when($startDate, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $endDate))
            ->when($status !== '' && $status !== 'quotation', fn (Builder $builder) => $builder->whereRaw('1 = 0'))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $like = '%' . $search . '%';

                    $searchQuery
                        ->whereHas('quote', function (Builder $quoteQuery) use ($like): void {
                            $quoteQuery->where('document_number', 'like', $like);
                        })
                        ->orWhereHas('client', function (Builder $clientQuery) use ($like): void {
                            $clientQuery->where('name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('mobile', 'like', $like)
                                ->orWhere('phone', 'like', $like);
                        });
                });
            });
    }

    private function applyLeadQuotationFilters(Builder $query, array $filters): void
    {
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $staffId = $this->normalizeFilterId($filters['staff_id'] ?? null);
        $search = trim((string) ($filters['search'] ?? ''));
        $startDate = $this->normalizeDateFilter($filters['start_date'] ?? null);
        $endDate = $this->normalizeDateFilter($filters['end_date'] ?? null);

        $query
            ->when($startDate, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $endDate))
            ->when($staffId, function (Builder $builder) use ($staffId): void {
                $builder->whereHas('lead.assignedTo.staff', fn (Builder $staffQuery) => $staffQuery->whereKey($staffId));
            })
            ->when($status !== '', function (Builder $builder) use ($status): void {
                match ($status) {
                    'quotation' => $builder->whereRaw('1 = 0'),
                    'draft' => $builder->where(function (Builder $statusQuery): void {
                        $statusQuery->whereNull('status')->orWhereRaw('LOWER(status) = ?', ['draft']);
                    }),
                    'sent' => $builder->whereRaw('LOWER(status) = ?', ['sent']),
                    'accepted' => $builder->whereRaw("LOWER(status) IN ('accepted', 'approved')"),
                    'declined' => $builder->whereRaw("LOWER(status) IN ('declined', 'rejected')"),
                    'expired' => $builder->whereRaw('LOWER(status) = ?', ['expired']),
                    default => null,
                };
            })
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $like = '%' . $search . '%';

                    $searchQuery
                        ->where('version', 'like', $like)
                        ->orWhere('amount', 'like', $like)
                        ->orWhereHas('lead', function (Builder $leadQuery) use ($like): void {
                            $leadQuery->where('client_name', 'like', $like)
                                ->orWhere('email', 'like', $like)
                                ->orWhere('mobile', 'like', $like)
                                ->orWhereHas('project', function (Builder $projectQuery) use ($like): void {
                                    $projectQuery->where('client_name', 'like', $like)
                                        ->orWhere('name', 'like', $like);
                                });
                        })
                        ->orWhereHas('lead.assignedTo.staff', function (Builder $staffQuery) use ($like): void {
                            $staffQuery->where('name', 'like', $like);
                        });
                });
            });
    }

    private function applyTicketQuotationFilters(Builder $query, array $filters): void
    {
        $status = strtolower(trim((string) ($filters['status'] ?? '')));
        $staffId = $this->normalizeFilterId($filters['staff_id'] ?? null);
        $search = trim((string) ($filters['search'] ?? ''));
        $startDate = $this->normalizeDateFilter($filters['start_date'] ?? null);
        $endDate = $this->normalizeDateFilter($filters['end_date'] ?? null);

        $query
            ->when($startDate, fn (Builder $builder) => $builder->whereDate('created_at', '>=', $startDate))
            ->when($endDate, fn (Builder $builder) => $builder->whereDate('created_at', '<=', $endDate))
            ->when($staffId, function (Builder $builder) use ($staffId): void {
                $builder->whereHas('ticket.assignedUser.staff', fn (Builder $staffQuery) => $staffQuery->whereKey($staffId));
            })
            ->when($status !== '' && $status !== 'quotation', fn (Builder $builder) => $builder->whereRaw('1 = 0'))
            ->when($search !== '', function (Builder $builder) use ($search): void {
                $builder->where(function (Builder $searchQuery) use ($search): void {
                    $like = '%' . $search . '%';

                    $searchQuery
                        ->whereHas('quote', function (Builder $quoteQuery) use ($like): void {
                            $quoteQuery->where('document_number', 'like', $like);
                        })
                        ->orWhereHas('ticket', function (Builder $ticketQuery) use ($like): void {
                            $ticketQuery->where('ticket_number', 'like', $like)
                                ->orWhere('customer_name', 'like', $like)
                                ->orWhere('customer_contact_number', 'like', $like)
                                ->orWhere('customer_email', 'like', $like)
                                ->orWhereHas('project', function (Builder $projectQuery) use ($like): void {
                                    $projectQuery->where('client_name', 'like', $like)
                                        ->orWhere('name', 'like', $like);
                                });
                        })
                        ->orWhereHas('ticket.assignedUser.staff', function (Builder $staffQuery) use ($like): void {
                            $staffQuery->where('name', 'like', $like);
                        });
                });
            });
    }

    private function normalizeLeadQuotationRecord(LeadQuotation $quotation): array
    {
        $lead = $quotation->lead;
        $staffName = $lead?->assignedTo?->staff?->name ?? $lead?->assignedTo?->name ?? '-';
        $statusLabel = $this->quotationStatusLabel($quotation);
        $actionUrl = $lead && filled($lead->getRouteKey()) ? route('leads.show', $lead) : null;
        $viewUrl = null;

        if ($lead && filled($lead->getRouteKey())) {
            $viewUrl = strtolower((string) $quotation->source) === 'boq'
                ? route('leads.boq.view', [$lead, $quotation])
                : route('leads.quotation.view-dynamic', [$lead, $quotation]);
        }

        return [
            'source' => 'lead',
            'view_url' => $viewUrl,
            'number' => $quotation->version,
            'client_name' => $lead?->client_name ?? '-',
            'company_name' => $lead?->project?->client_name ?? '-',
            'mobile' => $lead?->mobile ?? '-',
            'email' => $lead?->email ?? '-',
            'staff_name' => $staffName,
            'status_label' => $statusLabel,
            'amount' => (float) ($quotation->amount ?? 0),
            'date' => $quotation->created_at,
            'sort_key' => optional($quotation->created_at)?->timestamp ?? 0,
            'action_label' => 'View Lead',
            'action_url' => $actionUrl,
        ];
    }

    private function normalizeTicketQuotationRecord(TicketQuote $ticketQuote): array
    {
        $ticket = $ticketQuote->ticket;
        $quote = $ticketQuote->quote;
        $staffName = $ticket?->assignedUser?->staff?->name ?? $ticket?->assignedUser?->name ?? '-';
        $createdAt = $quote?->created_at ?? $ticketQuote->created_at;
        $viewUrl = $ticket && $quote ? route('tickets.quotations.view', [$ticket, $ticketQuote]) : null;

        return [
            'source' => 'ticket',
            'view_url' => $viewUrl,
            'edit_url' => $ticket ? route('tickets.show', [$ticket, 'tab' => 'billing', 'document_type' => 'quotation']) : null,
            'number' => $quote?->document_number ?? ('QTN-' . $ticketQuote->id),
            'client_name' => $ticket?->customer_name ?? '-',
            'company_name' => $ticket?->project?->client_name ?? ($ticket?->project?->name ?? '-'),
            'mobile' => $ticket?->customer_contact_number ?? '-',
            'email' => $ticket?->customer_email ?? '-',
            'staff_name' => $staffName,
            'status_label' => $this->ticketQuotationStatusLabel(),
            'amount' => (float) ($quote?->total ?? 0),
            'date' => $createdAt,
            'sort_key' => optional($createdAt)?->timestamp ?? 0,
            'action_label' => 'View Ticket',
            'action_url' => $ticket && $quote ? route('tickets.quotations.view', [$ticket, $ticketQuote]) : null,
        ];
    }

    private function normalizeClientQuotationRecord(TicketQuote $ticketQuote): array
    {
        $client = $ticketQuote->client;
        $quote = $ticketQuote->quote;
        $createdAt = $quote?->created_at ?? $ticketQuote->created_at;
        $viewUrl = $quote ? route('service-call.billing-document.show', $quote) : null;

        return [
            'source' => 'client',
            'view_url' => $viewUrl,
            'edit_url' => $quote ? route('service-call.billing-document.edit', $quote) : null,
            'number' => $quote?->document_number ?? ('QTN-' . $ticketQuote->id),
            'client_name' => $client?->name ?? '-',
            'company_name' => '-',
            'mobile' => $client?->mobile ?? $client?->phone ?? '-',
            'email' => $client?->email ?? '-',
            'staff_name' => $ticketQuote->creator?->name ?? '-',
            'status_label' => $this->ticketQuotationStatusLabel(),
            'amount' => (float) ($quote?->total ?? 0),
            'date' => $createdAt,
            'sort_key' => optional($createdAt)?->timestamp ?? 0,
            'action_label' => 'View Invoice',
            'action_url' => $viewUrl,
        ];
    }

    private function normalizeFilterId(mixed $value): ?int
    {
        $filtered = filter_var($value, FILTER_VALIDATE_INT);

        return $filtered === false ? null : (int) $filtered;
    }

    private function normalizeDateFilter(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
