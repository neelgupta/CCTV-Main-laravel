<?php

namespace App\Http\Controllers;

use App\Exports\TicketExport;
use App\Models\Amc;
use App\Models\BoqProduct;
use App\Models\Company;
use App\Models\CompanyGstRate;
use App\Models\CompanyUnit;
use App\Models\ServiceStage;
use App\Models\TicketDocument;
use App\Models\TicketInvoice;
use App\Models\TicketQuote;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\Client;
use App\Models\Staff;
use App\Models\Lead;
use App\Models\Project;
use App\Services\ClientSyncService;
use App\Services\AmcSupportWorkflowService;
use App\Services\AmcTechnicianAssignmentService;
use App\Services\LetterheadPdfService;
use App\Services\TicketAssignmentHistoryService;
use App\Services\TicketDocumentLinkService;
use App\Services\TicketRepeatWorkflowService;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\View\View;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        $viewMode = $request->query('view') === 'kanban' ? 'kanban' : 'list';
        $filters = $this->ticketFilters($request);
        $assignees = $this->ticketAssignees($user);
        $ticketCounts = $this->ticketQuickFilterCounts($user, $filters);
        $quickAssignTechnicians = $this->ticketQuickAssignTechnicians($user);
        $ticketsQuery = $this->applyTicketFilters(
            $this->visibleTicketsQuery($user)->withCount('replies'),
            $filters
        );
        $stages = ServiceStage::orderedForCompany((int) $user->company_id);
        $ticketsByStage = collect();

        if ($viewMode === 'kanban') {
            $tickets = $ticketsQuery->get();
            $ticketsByStage = $tickets->groupBy(fn (Ticket $ticket) => Ticket::stageForStatus(
                $ticket->status ?: 'open',
                $ticket->priority,
                $ticket->assigned_to ? (int) $ticket->assigned_to : null,
                (int) $ticket->replies_count,
                $ticket->board_stage,
                (int) $user->company_id
            ));
        } else {
            $tickets = $ticketsQuery
                ->paginate(10)
                ->withQueryString();
        }

        return view('tickets.index', [
            'tickets' => $tickets,
            'viewMode' => $viewMode,
            'stages' => $stages,
            'ticketsByStage' => $ticketsByStage,
            'filters' => $filters,
            'assignees' => $assignees,
            'ticketCounts' => $ticketCounts,
            'quickAssignTechnicians' => $quickAssignTechnicians,
            'priorityOptions' => [
                'low' => 'Low',
                'medium' => 'Medium',
                'high' => 'High',
                'urgent' => 'Urgent',
            ],
            'statusOptions' => Ticket::statusOptions(),
        ]);
    }

    public function export(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        $tickets = $this->applyTicketFilters(
            $this->visibleTicketsQuery($user),
            $this->ticketFilters($request)
        )
            ->get();

        return Excel::download(
            new TicketExport($tickets),
            'support-tickets-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($this->canCreateSupportTicket($user), 403);

        $assignees = $this->ticketAssignees($user);
        $prefill = [
            'source' => null,
            'source_label' => null,
            'customer_contact_number' => null,
            'customer_name' => null,
            'customer_email' => null,
            'customer_address' => null,
            'type' => null,
            'priority' => null,
            'assigned_to' => null,
            'visit_date' => null,
            'subject' => null,
            'description' => null,
            'amc_reference' => null,
        ];

        if (filled($request->query('amc'))) {
            $amc = $this->resolveAmcReference((string) $request->query('amc'), (int) $user->company_id);
            $workflowService = app(AmcSupportWorkflowService::class);

            abort_unless($amc->isOnCall() || $amc->isHybrid(), 404);

            $context = $workflowService->supportTicketContextForAmc($amc);
            $assignedTo = $context['assigned_to'];
            if (! $assignees->contains('id', $assignedTo)) {
                $assignedTo = null;
            }

            $amcTypeLabel = Amc::TYPE_OPTIONS[$amc->amc_type]
                ?? str($amc->amc_type)->replace('_', ' ')->title()->toString();

            $prefill = [
                'source' => 'amc',
                'source_label' => 'AMC ' . $amc->contract_no,
                'customer_contact_number' => $amc->mobile_number,
                'customer_name' => $amc->customer_name,
                'customer_email' => $amc->email,
                'customer_address' => $amc->address,
                'type' => 'support',
                'priority' => 'medium',
                'assigned_to' => $assignedTo,
                'visit_date' => $context['visit_date'] ?? now()->toDateString(),
                'subject' => Str::limit('AMC Support Request - ' . $amc->project_name, 191, ''),
                'description' => implode("\n", [
                    'AMC Contract: ' . $amc->contract_no,
                    'Customer: ' . ($amc->customer_name ?: '-'),
                    'Project: ' . ($amc->project_name ?: '-'),
                    'AMC Type: ' . $amcTypeLabel,
                    'Service Mode: ' . $amc->serviceModeLabel(),
                    '',
                    'Issue Details:',
                ]),
                'amc_reference' => $amc->getRouteKey(),
            ];
        }

        return view('tickets.create', [
            'assignees' => $assignees,
            'typeOptions' => Ticket::typeOptions(),
            'prefill' => $prefill,
        ]);
    }

    public function storeFromAmc(Request $request, Amc $amc, AmcSupportWorkflowService $workflowService)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless((int) $amc->company_id === (int) $user->company_id, 403);
        abort_unless($amc->isOnCall() || $amc->isHybrid() || $amc->generatesScheduledVisits(), 404);
        abort_unless($this->canCreateSupportTicket($user), 403);

        if ($amc->isServiceClosed()) {
            return redirect()
                ->route('amcs.show', $amc)
                ->with('error', $amc->serviceClosureMessage());
        }

        $validated = $request->validate([
            'visit_date' => ['required', 'date', 'after_or_equal:today'],
            'description' => ['required', 'string', 'max:5000'],
        ], [
            'visit_date.required' => 'Please select a visit date before generating the ticket.',
            'description.required' => 'Please enter a description before generating the ticket.',
        ]);

        $existingTicket = $workflowService->findCurrentSupportTicketForAmc($amc);
        if (! $existingTicket) {
            app(SubscriptionLimitService::class)->ensureWithinLimit((int) $user->company_id, 'support', 1, 'subject');
        }

        $ticket = $existingTicket ?: $workflowService->generateSupportTicketForAmc(
            $amc,
            null,
            $user,
            $validated['visit_date'],
            $validated['description']
        );
        $ticket = $workflowService->syncSupportTicketForAmc(
            $amc,
            null,
            $user,
            $validated['visit_date'],
            $validated['description']
        ) ?? $ticket;

        $message = $existingTicket
            ? 'AMC support ticket updated with the selected visit date and description.'
            : 'Support ticket generated from AMC successfully.';

        return redirect()
            ->route('tickets.show', $ticket)
            ->with('success', $message);
    }

    public function store(Request $request, ClientSyncService $sync, TicketAssignmentHistoryService $assignmentHistory)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);
        abort_unless($this->canCreateSupportTicket($user), 403);

        $assigneeIds = $this->ticketAssignees($user)->pluck('id')->all();

        $validated = $request->validate([
            'customer_contact_number' => 'required|string|max:30',
            'customer_name' => 'required|string|min:2|max:191',
            'customer_email' => 'nullable|email|max:191',
            'customer_address' => 'nullable|string|max:1000',
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id))],
            'type' => ['required', 'string', Rule::in(array_keys(Ticket::typeOptions()))],
            'subject' => 'required|string|min:5|max:191',
            'priority' => 'required|in:low,medium,high,urgent',
            'visit_date' => ['required', 'date', 'after_or_equal:today'],
            'description' => 'required|string|max:5000',
            'assigned_to' => ['nullable'],
            'assigned_to.*' => ['integer', Rule::in($assigneeIds)],
            'referenced_by' => ['nullable', 'integer', Rule::in($assigneeIds)],
            'amc_source' => 'nullable|string|max:191',
            'selected_customer_source_type' => ['nullable', 'string', Rule::in(['client', 'lead'])],
            'selected_customer_source_id' => 'nullable|integer|min:1',
        ], [
            'customer_contact_number.required' => 'Phone number is required.',
        ]);

        app(SubscriptionLimitService::class)->ensureWithinLimit((int) $user->company_id, 'support', 1, 'subject');

        $customerContactNumber = filled($validated['customer_contact_number'] ?? null)
            ? trim((string) $validated['customer_contact_number'])
            : null;
        $customerName = filled($validated['customer_name'] ?? null) ? trim((string) $validated['customer_name']) : null;
        $customerEmail = filled($validated['customer_email'] ?? null) ? str($validated['customer_email'])->trim()->lower()->toString() : null;
        $customerAddress = filled($validated['customer_address'] ?? null) ? trim((string) $validated['customer_address']) : null;
        $matchedStaff = null;
        $matchedClient = null;
        $matchedLead = null;
        $selectedSourceType = $validated['selected_customer_source_type'] ?? null;
        $selectedSourceId = isset($validated['selected_customer_source_id']) ? (int) $validated['selected_customer_source_id'] : null;

        $explicitlySelected = false;

        if ($selectedSourceType && $selectedSourceId) {
            $selectedCustomer = $this->resolveCustomerHistorySource((int) $user->company_id, $selectedSourceType, $selectedSourceId);

            if ($selectedCustomer instanceof Client) {
                $matchedClient = $selectedCustomer;
                $explicitlySelected = true;
            } elseif ($selectedCustomer instanceof Lead) {
                $matchedLead = $selectedCustomer;
                $explicitlySelected = true;
            }
        }

        if (! $matchedClient && ! $matchedLead && $customerContactNumber) {
            $matchedStaff = $this->findStaffByContactNumber((int) $user->company_id, $customerContactNumber);
            $matchedClient = $matchedStaff
                ? null
                : $this->findClientByContactNumber((int) $user->company_id, $customerContactNumber);
            $matchedLead = $matchedStaff || $matchedClient
                ? null
                : $this->findLeadByContactNumber((int) $user->company_id, $customerContactNumber);

            if (! $explicitlySelected && ($matchedClient || $matchedLead)) {
                throw ValidationException::withMessages([
                    'customer_contact_number' => 'This phone number already exists for another client.',
                ]);
            }
        }

        if (! $matchedStaff && ! $matchedClient && ! $matchedLead) {
            $matchedClient = $this->findClientByIdentity((int) $user->company_id, $customerName, $customerEmail, $customerAddress);
            $matchedLead = $matchedClient
                ? null
                : $this->findLeadByIdentity((int) $user->company_id, $customerName, $customerEmail, $customerAddress);

            if (! $explicitlySelected && $customerEmail && $matchedClient) {
                throw ValidationException::withMessages([
                    'customer_email' => 'This email already exists for another client.',
                ]);
            }
        }

        if ($matchedStaff) {
            $customerName = $matchedStaff->name ?: ($matchedStaff->user?->name ?: $customerName);
            $customerEmail = $matchedStaff->user?->email ?: $customerEmail;
        } elseif ($matchedClient) {
            $customerName = $matchedClient->name ?: $customerName;
            $customerEmail = $matchedClient->email ?: $customerEmail;
            $customerAddress = $matchedClient->address ?: $customerAddress;
        } elseif ($matchedLead) {
            $customerName = $matchedLead->client_name ?: $customerName;
            $customerEmail = $matchedLead->email ?: $customerEmail;
            $customerAddress = $matchedLead->address ?: $customerAddress;
        }

        $messages = [];

        if (! $customerName) {
            $messages['customer_name'] = 'Customer name is required.';
        }

        if (filled($validated['project_id'] ?? null)) {
            $selectedProjectId = (int) $validated['project_id'];
            $matchedCustomer = $matchedClient ?: $matchedLead;

            if (! $matchedCustomer) {
                $messages['project_id'] = 'Select an existing client/contact before choosing a project.';
            } elseif (! $this->customerCanUseProject(
                $selectedSourceType ?? ($matchedClient ? 'client' : 'lead'),
                $matchedCustomer,
                $selectedProjectId
            )) {
                $messages['project_id'] = 'Selected project does not belong to this customer.';
            }
        }

        if ($messages) {
            throw ValidationException::withMessages($messages);
        }

        if ($customerEmail && ! $matchedLead && ! $matchedClient) {
            $company = Company::query()->find($user->company_id);
            if ($company && $company->isInternalStaffOrAdminEmail($customerEmail)) {
                throw ValidationException::withMessages([
                    'customer_email' => 'This email belongs to a company administrator or staff member and cannot be assigned to a support ticket client.',
                ]);
            }

            $existingUser = \App\Models\User::query()->whereRaw('LOWER(email) = ?', [$customerEmail])->first();
            if ($existingUser && (int) $existingUser->company_id !== (int) $user->company_id) {
                throw ValidationException::withMessages([
                    'customer_email' => 'Customer email is already registered.',
                ]);
            }
        }

        if (! $matchedStaff && ! $matchedClient && ! $matchedLead) {
            $conflictMessages = [];

            if ($customerEmail) {
                $emailClient = Client::query()
                    ->where('company_id', (int) $user->company_id)
                    ->whereRaw('LOWER(email) = ?', [$customerEmail])
                    ->first();

                if ($emailClient) {
                    $conflictMessages['customer_email'] = 'This email already exists for another client.';
                }
            }

            if ($customerContactNumber) {
                $digits = preg_replace('/\D+/', '', $customerContactNumber) ?: $customerContactNumber;
                $phoneClient = Client::query()
                    ->where('company_id', (int) $user->company_id)
                    ->where(function ($query) use ($customerContactNumber, $digits): void {
                        $query->where('mobile', $customerContactNumber)
                            ->orWhere('phone', $customerContactNumber)
                            ->orWhere('mobile', $digits)
                            ->orWhere('phone', $digits);
                    })
                    ->first();

                if ($phoneClient) {
                    $conflictMessages['customer_contact_number'] = 'This phone number already exists for another client.';
                }
            }

            if ($conflictMessages) {
                throw ValidationException::withMessages($conflictMessages);
            }
        }

        $ticket = null;
        $generatedFromAmcId = null;
        $sourceType = null;
        $sourceId = null;
        $visitType = null;

        if (filled($validated['amc_source'] ?? null)) {
            $sourceAmc = $this->resolveAmcReference((string) $validated['amc_source'], (int) $user->company_id);
            if (! $sourceAmc->isOnCall() && ! $sourceAmc->isHybrid()) {
                throw ValidationException::withMessages([
                    'amc_source' => 'Selected AMC contract is not allowed for on-call support tickets.',
                ]);
            }

            $generatedFromAmcId = (int) $sourceAmc->id;
            $sourceType = 'amc';
            $sourceId = (int) $sourceAmc->id;
            $visitType = $sourceAmc->service_mode ?: 'scheduled';
        }

        $assignedInput = $request->input('assigned_to');
        $assignedUserIds = collect(is_array($assignedInput) ? $assignedInput : ($assignedInput ? [$assignedInput] : []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $assigneeIds, true))
            ->unique()
            ->values()
            ->all();
        $primaryAssignedTo = $assignedUserIds[0] ?? null;

        DB::transaction(function () use ($user, $validated, $customerContactNumber, $customerName, $customerEmail, $customerAddress, $sync, $generatedFromAmcId, $sourceType, $sourceId, $matchedStaff, $matchedClient, $matchedLead, $assignmentHistory, $assignedUserIds, $primaryAssignedTo, &$ticket) {
            if ($matchedClient || $matchedLead) {
                $this->syncSelectedCustomerRecord($matchedClient ?: $matchedLead, $customerName, $customerEmail, $customerContactNumber, $customerAddress);
            } elseif ($customerName && ! $matchedStaff) {
                $digits = $customerContactNumber ? (preg_replace('/\\D+/', '', $customerContactNumber) ?: '') : '';
                $phoneNumber = $customerContactNumber
                    ? ($digits !== '' ? $digits : $customerContactNumber)
                    : null;

                $sync->upsert([
                    'company_id' => (int) $user->company_id,
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $phoneNumber,
                    'mobile' => $phoneNumber,
                    'address' => $customerAddress,
                ]);
            }

            $ticket = Ticket::create([
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'assigned_to' => $primaryAssignedTo,
                'project_id' => filled($validated['project_id'] ?? null) ? (int) $validated['project_id'] : null,
                'ticket_number' => 'TKT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6)),
                'customer_contact_number' => $customerContactNumber,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_address' => $customerAddress,
                'type' => $validated['type'],
                'referenced_by' => filled($validated['referenced_by'] ?? null) ? $validated['referenced_by'] : null,
                'subject' => $validated['subject'],
                'priority' => $validated['priority'],
                'status' => 'open',
                'board_stage' => Ticket::stageForStatus('open', null, null, 0, 'new', (int) $user->company_id),
                'visit_date' => $validated['visit_date'] ?? null,
                'description' => $validated['description'],
                'generated_from_amc_id' => $generatedFromAmcId,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'issue_category' => trim((string) $validated['subject']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (Schema::hasTable('ticket_user_assignments')) {
                $ticket->assignedUsers()->sync($assignedUserIds);
            }

            if (! empty($assignedUserIds)) {
                $assignmentHistory->syncCurrentAssignments(
                    $ticket,
                    $assignedUserIds,
                    $user,
                    'Initial',
                    null
                );
            }
        });

        try {
            app(DashboardNotificationService::class)->notifyTicketAssigned($ticket, $assignedUserIds);
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket created successfully.');
    }

    public function show(Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if (! $user->isSuperAdmin() && ! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        $ticket->load([
            'company:id,task_master_list',
            'user',
            'assignedUser',
            'assignedUsers',
            'referencedUser',
            'project',
            'replies.user',
            'amc.visits',
            'parentTicket',
            'previousStaff',
            'generatedFromAmc',
            'attachments',
            'invoices.invoice',
            'quotes.quote',
            'assignmentHistories.staff',
            'assignmentHistories.assignedBy',
            'repeatHistories.reopenedBy',
            'repeatHistories.previousAssignee',
            'repeatHistories.assignee',
        ]);

        $ticketVisitDate = $ticket->visit_date ?? $ticket->amc?->visits->first()?->visit_date;
        $taskMasters = $ticket->company?->taskMasterNames() ?? [];
        $conversationReplies = $ticket->replies()
            ->with('user')
            ->latest()
            ->paginate(10, ['*'], 'conversation_page')
            ->appends(['tab' => 'conversation']);
        $billingInvoiceDocument = $ticket->invoices->first()?->invoice;
        $billingQuotationDocument = $ticket->quotes->first()?->quote;
        $billingDocumentProductIds = collect($billingInvoiceDocument?->items ?? [])
            ->merge($billingQuotationDocument?->items ?? [])
            ->map(fn ($item) => is_array($item) ? (int) ($item['product_id'] ?? $item['id'] ?? 0) : 0)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $billingProducts = BoqProduct::query()
            ->where('company_id', $ticket->company_id)
            ->where(function ($query) use ($billingDocumentProductIds): void {
                $query->where('is_active', true)
                    ->when($billingDocumentProductIds !== [], fn ($builder) => $builder->orWhereIn('id', $billingDocumentProductIds));
            })
            ->orderBy('name')
            ->get(['id', 'name', 'brand', 'model', 'unit', 'price', 'gst', 'description']);


        return view('tickets.show', [
            'ticket' => $ticket,
            'ticketVisitDate' => $ticketVisitDate,
            'assignees' => $this->ticketAssignees($user),
            'canRepeatTicket' => $user->hasAnyPermission('support.edit', 'tickets.manage', 'tickets.reply'),
            'conversationReplies' => $conversationReplies,
            'billingProducts' => $billingProducts,
            'billingUnits' => CompanyUnit::query()
                ->where('company_id', $ticket->company_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'billingGstRates' => CompanyGstRate::query()
                ->where('company_id', $ticket->company_id)
                ->orderBy('rate')
                ->pluck('rate')
                ->merge($billingProducts->pluck('gst'))
                ->merge([0, 5, 12, 18, 28])
                ->map(fn ($r) => (float) $r)
                ->unique()
                ->sort()
                ->values(),
            'billingInvoiceDocument' => $billingInvoiceDocument,
            'billingQuotationDocument' => $billingQuotationDocument,
            'taskMasters' => $ticket->company?->taskMasterNames() ?? [],
            'taskStatusOptions' => Ticket::taskStatusOptions(),
        ]);
    }

    public function viewInvoice(Ticket $ticket, TicketInvoice $ticketInvoice): View
    {
        return $this->renderDocumentView($ticket, $ticketInvoice, 'invoice', false);
    }

    public function printInvoice(Ticket $ticket, TicketInvoice $ticketInvoice): View
    {
        return $this->renderDocumentView($ticket, $ticketInvoice, 'invoice', true);
    }

    public function downloadInvoice(Request $request, Ticket $ticket, TicketInvoice $ticketInvoice, LetterheadPdfService $letterheadPdfService)
    {
        $user = auth()->user();
        abort_unless($user && $this->canAccessTicket($user, $ticket), 403);
        abort_unless($this->canAccessInvoice($user, $ticketInvoice), 403);

        $view = $this->renderDocumentView($ticket, $ticketInvoice, 'invoice', false);
        $data = $view->getData();
        $data['isPdf'] = true;
        $pdf = Pdf::loadView('tickets.document', $data)
            ->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 96,
            ]);

        $fileName = 'invoice-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string) ($ticketInvoice->invoice?->document_number ?? $ticketInvoice->invoice_number ?? $ticket->ticket_number ?? $ticket->id)) . '.pdf';

        return $letterheadPdfService->download($pdf, $fileName, $ticket->company ?? null, $request->boolean('inline'), true);
    }

    public function viewQuotation(Ticket $ticket, TicketQuote $ticketQuote): View
    {
        return $this->renderDocumentView($ticket, $ticketQuote, 'quotation', false);
    }

    public function printQuotation(Ticket $ticket, TicketQuote $ticketQuote): View
    {
        return $this->renderDocumentView($ticket, $ticketQuote, 'quotation', true);
    }

    public function downloadQuotation(Request $request, Ticket $ticket, TicketQuote $ticketQuote, LetterheadPdfService $letterheadPdfService)
    {
        $user = auth()->user();
        abort_unless($user && $this->canAccessTicket($user, $ticket), 403);

        $view = $this->renderDocumentView($ticket, $ticketQuote, 'quotation', false);
        $data = $view->getData();
        $data['isPdf'] = true;
        $pdf = Pdf::loadView('tickets.document', $data)
            ->setPaper('a4', 'portrait')
            ->setOption([
                'defaultFont' => 'DejaVu Sans',
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 96,
            ]);

        $fileName = 'quotation-' . preg_replace('/[^A-Za-z0-9._-]/', '-', (string) ($ticketQuote->quote?->document_number ?? $ticketQuote->quote_number ?? $ticket->quote_id ?? $ticket->id)) . '.pdf';

        return $letterheadPdfService->download($pdf, $fileName, $ticket->company ?? null, $request->boolean('inline'), true);
    }

    public function saveBillingDocument(Request $request, Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_unless($this->canAccessTicket($user, $ticket), 403);
        $company = Company::query()->find($ticket->company_id);
        abort_unless($company, 403);

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(['invoice', 'quotation'])],
            'product_id' => [($company?->usesManualProductEntry() ? 'nullable' : 'required'), 'array', 'min:1'],
            'product_id.*' => ['nullable', 'integer'],
            'name' => [($company?->usesManualProductEntry() ? 'required' : 'nullable'), 'array', 'min:1'],
            'name.*' => ['nullable', 'string'],
            'unit' => ['nullable', 'array'],
            'unit.*' => ['nullable', 'string'],
            'qty' => ['required', 'array'],
            'qty.*' => ['nullable', 'numeric', 'min:0.01'],
            'price' => ['nullable', 'array'],
            'price.*' => ['nullable', 'numeric', 'min:0'],
            'gst' => ['nullable', 'array'],
            'gst.*' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'array'],
            'note.*' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:2000'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'terms_conditions' => ['nullable', 'string'],
            'payment_status' => ['nullable', 'integer', Rule::in(['0', '1'])],
            'payment_method' => ['nullable', 'integer', Rule::in(['0', '1'])],
        ]);

        $documentType = (string) $validated['document_type'];
        $paymentStatus = (int) ($validated['payment_status'] ?? 0);
        $paymentMethod = array_key_exists('payment_method', $validated) ? (int) $validated['payment_method'] : null;

        if ($documentType === 'invoice' && $paymentStatus === 1 && $paymentMethod === null) {
            return back()->withErrors(['payment_method' => 'Payment method is required when invoice is paid.'])->withInput();
        }

        $itemBuilder = app(\App\Services\BillingDocumentItemBuilder::class);
        $items = $itemBuilder->buildItems($validated, (int) $ticket->company_id, $company?->usesManualProductEntry() ?? false);
        $summary = $itemBuilder->buildSummary($items, round((float) ($validated['discount'] ?? 0), 2));
        $document = DB::transaction(function () use ($ticket, $user, $documentType, $items, $summary, $paymentStatus, $paymentMethod): TicketDocument {
            $document = $this->resolveVisibleTicketDocument($ticket, $documentType);
            $payload = [
                'ticket_id' => $ticket->id,
                'company_id' => $ticket->company_id,
                'created_by' => $user->id,
                'document_type' => $documentType,
                'invoice_or_quote' => $documentType === 'invoice' ? 1 : 2,
                'items' => $items,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'gst_amount' => $summary['gst_amount'],
                'total' => $summary['total'],
                'payment_status' => $documentType === 'invoice' ? $paymentStatus : null,
                'payment_method' => $documentType === 'invoice' && $paymentStatus === 1 ? $paymentMethod : null,
                'terms_conditions' => trim((string) ($validated['terms_conditions'] ?? '')) ?: null,
            ];
            $changeMessages = [];

            if ($document) {
                $changeMessages = $this->buildTicketDocumentChangeMessages($document, $payload);
                $this->snapshotTicketDocument($document, $payload);
                $document->forceFill($payload)->save();
            } else {
                $prefix = $documentType === 'invoice' ? 'INV' : 'QTN';
                $company = Company::query()
                    ->whereKey($ticket->company_id)
                    ->lockForUpdate()
                    ->first();
                $allocatedDocument = $company?->allocateDocumentNumber($documentType);
                $documentNumber = $allocatedDocument['number'] ?? null;

                $document = TicketDocument::query()->create($payload + [
                    'document_number' => $prefix . '-TMP-' . Str::upper(Str::random(8)),
                ]);
                $document->forceFill([
                    'document_number' => $documentNumber ?? ($prefix . '-' . now()->format('Y') . '-' . str_pad((string) $document->id, 5, '0', STR_PAD_LEFT)),
                ])->save();

                if ($allocatedDocument !== null && $company) {
                    $company->syncDocumentNumberSequence($documentType, ((int) $allocatedDocument['sequence']) + 1);
                }
            }

            if ($documentType === 'invoice') {
                app(TicketDocumentLinkService::class)->linkInvoice(
                    $ticket,
                    $document->fresh(),
                    $user,
                    $paymentStatus,
                    $paymentStatus === 1 ? $paymentMethod : null,
                    $summary,
                    $changeMessages
                );
            } else {
                $ticket->update([
                    'quote_id' => $ticket->quote_id ?: ('QTE-' . now()->format('Ymd') . '-T' . $ticket->id . '-' . Str::upper(Str::random(5))),
                    'quote_items' => $items,
                    'quote_subtotal' => $summary['subtotal'],
                    'quote_discount' => $summary['discount'],
                    'quote_gst_amount' => $summary['gst_amount'],
                    'quote_total' => $summary['total'],
                ]);
                app(TicketDocumentLinkService::class)->linkQuotation($ticket, $document->fresh(), $user, $changeMessages);
            }

            return $document->fresh();
        });

        $label = $documentType === 'invoice' ? 'Invoice' : 'Quotation';

        return redirect()
            ->route('tickets.show', [$ticket, 'tab' => $documentType === 'invoice' ? 'invoices' : 'quotations'])
            ->with('success', $label . ' saved successfully.');
    }

    private function renderDocumentView(Ticket $ticket, TicketInvoice|TicketQuote $link, string $type, bool $autoPrint): View
    {
        $user = auth()->user();
        abort_unless($user, 403);
        abort_unless($this->canAccessTicket($user, $ticket), 403);

        if ($type === 'invoice') {
            abort_unless($this->canAccessInvoice($user, $link), 403);
        }

        $document = $type === 'invoice'
            ? $link->loadMissing('invoice')->invoice
            : $link->loadMissing('quote')->quote;

        abort_if(! $document, 404);

        $items = collect($document->items ?? [])->filter(fn ($item) => is_array($item))->values();

        $subtotal = (float) ($document->subtotal ?? $items->sum(fn ($item) => (float) ($item['base_total'] ?? 0)));
        $discount = (float) ($document->discount ?? 0);
        $gstAmount = (float) ($document->gst_amount ?? $items->sum(fn ($item) => (float) ($item['gst_amount'] ?? 0)));
        $total = (float) ($document->total ?? max(0, $subtotal - $discount + $gstAmount));

        $paymentStatus = null;
        $paymentMethod = null;
        if ($type === 'invoice') {
            $paymentStatus = (int) ($link->payment_status ?? 0) === 1 ? 'Paid' : 'Unpaid';
            $paymentMethod = (int) ($link->payment_status ?? 0) === 1
                ? (in_array((int) ($link->payment_method ?? -1), [0, 2], true) ? 'Cash' : 'Online')
                : '-';
        }

        $letterheadPdfService = app(LetterheadPdfService::class);
        $company = $ticket->company;
        $useLetterhead = $letterheadPdfService->hasActiveLetterhead($company);
        $pdfMargins = $useLetterhead
            ? $letterheadPdfService->getMargins($letterheadPdfService->activeLetterhead($company))
            : [
                'top_margin' => 15,
                'bottom_margin' => 15,
                'left_margin' => 15,
                'right_margin' => 15,
            ];

        return view('tickets.document', [
            'ticket' => $ticket->loadMissing(['company', 'user', 'assignedUser', 'referencedUser']),
            'document' => $document,
            'link' => $link,
            'type' => $type,
            'autoPrint' => $autoPrint,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'gstAmount' => $gstAmount,
            'total' => $total,
            'paymentStatus' => $paymentStatus,
            'paymentMethod' => $paymentMethod,
            'useLetterhead' => $useLetterhead,
            'pdfMargins' => $pdfMargins,
        ]);
    }

    public function updateServiceReport(Request $request, Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        if (! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        $validated = $request->validate([
            'service_notes' => ['nullable', 'string', 'max:5000'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.name' => ['nullable', 'string', 'max:191'],
            'tasks.*.status' => ['nullable', 'string', Rule::in(array_keys(Ticket::taskStatusOptions()))],
        ]);

        $taskItems = Ticket::normalizeTaskItems($validated['tasks'] ?? []);
        $taskItems = $this->syncManualTaskMasters((int) $user->company_id, $taskItems);

        $payload = [];

        if ($request->has('service_notes')) {
            $payload['service_notes'] = $validated['service_notes'] ?? null;
        }

        if ($request->has('tasks') || $request->boolean('tasks_present')) {
            $payload['tasks'] = $taskItems === [] ? null : $taskItems;
        }

        if ($payload !== []) {
            $ticket->update($payload);
        }

        $ticket->load(['company:id,task_master_list', 'user', 'assignedUser', 'referencedUser', 'project', 'replies.user', 'amc.visits']);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Service report updated successfully.',
                'ticket' => [
                    'id' => $ticket->id,
                    'service_notes' => $ticket->service_notes,
                    'tasks' => Ticket::normalizeTaskItems($ticket->tasks ?? []),
                ],
                'task_masters' => $ticket->company?->taskMasterNames() ?? [],
                'task_status_options' => Ticket::taskStatusOptions(),
            ]);
        }

        return back()->with('success', 'Service report updated successfully.');

    }

    public function storeTask(Request $request, Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        if (! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        $validated = $request->validate([
            'task_name' => ['nullable', 'string', 'max:191'],
            'task_master_name' => ['nullable', 'string', 'max:191'],
            'task_status' => ['nullable', 'string', Rule::in(array_keys(Ticket::taskStatusOptions()))],
        ]);

        $taskName = filled($validated['task_name'] ?? null)
            ? trim((string) $validated['task_name'])
            : (filled($validated['task_master_name'] ?? null) ? trim((string) $validated['task_master_name']) : '');

        if ($taskName === '') {
            return back()
                ->withErrors(['task_name' => 'Please enter a task or choose one from the task master list.'])
                ->withInput();
        }

        $taskItems = Ticket::normalizeTaskItems([
            ...($ticket->tasks ?? []),
            [
                'name' => $taskName,
                'status' => $validated['task_status'] ?? 'pending',
            ],
        ]);
        $taskItems = $this->syncManualTaskMasters((int) $user->company_id, $taskItems);

        $ticket->update([
            'tasks' => $taskItems === [] ? null : $taskItems,
        ]);

        return redirect()
            ->route('tickets.show', ['ticket' => $ticket, 'tab' => 'tasks'])
            ->with('success', 'Task added successfully.');
    }

    public function edit(Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        if (! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        abort_unless($this->canEditSupportTicket($user), 403);

        if (Schema::hasTable('ticket_user_assignments')) {
            $ticket->loadMissing('assignedUsers');
            $selectedAssignedUserIds = $ticket->assignedUsers->pluck('id')->all();
        } else {
            $selectedAssignedUserIds = [];
        }

        if (empty($selectedAssignedUserIds) && $ticket->assigned_to) {
            $selectedAssignedUserIds = [(int) $ticket->assigned_to];
        }

        return view('tickets.edit', [
            'ticket' => $ticket,
            'selectedAssignedUserIds' => $selectedAssignedUserIds,
            'assignees' => $this->ticketAssignees($user),
            'typeOptions' => Ticket::typeOptions(),
        ]);
    }

    public function update(Request $request, Ticket $ticket, ClientSyncService $sync, TicketAssignmentHistoryService $assignmentHistory)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        if (! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        abort_unless($this->canEditSupportTicket($user), 403);

        $assigneeIds = $this->ticketAssignees($user)->pluck('id')->all();

        $validated = $request->validate([
            'customer_contact_number' => 'nullable|string|max:30',
            'customer_name' => 'required|string|min:2|max:191',
            'customer_email' => 'nullable|email|max:191',
            'customer_address' => 'nullable|string|max:1000',
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where(fn ($query) => $query->where('company_id', $user->company_id))],
            'type' => ['required', 'string', Rule::in(array_keys(Ticket::typeOptions()))],
            'subject' => 'required|string|min:5|max:191',
            'priority' => 'required|in:low,medium,high,urgent',
            'visit_date' => ['nullable', 'date', 'after_or_equal:today'],
            'description' => 'required|string|max:5000',
            'assigned_to' => ['nullable'],
            'assigned_to.*' => ['integer', Rule::in($assigneeIds)],
            'referenced_by' => ['nullable', 'integer', Rule::in($assigneeIds)],
            'selected_customer_source_type' => ['nullable', 'string', Rule::in(['client', 'lead'])],
            'selected_customer_source_id' => 'nullable|integer|min:1',
        ]);

        $customerContactNumber = filled($validated['customer_contact_number'] ?? null)
            ? trim((string) $validated['customer_contact_number'])
            : null;
        $customerName = filled($validated['customer_name'] ?? null) ? trim((string) $validated['customer_name']) : null;
        $customerEmail = filled($validated['customer_email'] ?? null) ? str($validated['customer_email'])->trim()->lower()->toString() : null;
        $customerAddress = filled($validated['customer_address'] ?? null) ? trim((string) $validated['customer_address']) : null;
        $typedCustomerName = $customerName;

        $matchedStaff = null;
        $matchedClient = null;
        $matchedLead = null;
        $selectedSourceType = $validated['selected_customer_source_type'] ?? null;
        $selectedSourceId = isset($validated['selected_customer_source_id']) ? (int) $validated['selected_customer_source_id'] : null;
        $originalContactNumber = trim((string) $ticket->customer_contact_number);
        $contactNumberUnchanged = $customerContactNumber !== null
            && $originalContactNumber !== ''
            && $customerContactNumber === $originalContactNumber;
        $explicitlySelected = $contactNumberUnchanged;

        if ($selectedSourceType && $selectedSourceId) {
            $selectedCustomer = $this->resolveCustomerHistorySource((int) $user->company_id, $selectedSourceType, $selectedSourceId);

            if ($selectedCustomer instanceof Client) {
                $matchedClient = $selectedCustomer;
                $explicitlySelected = true;
            } elseif ($selectedCustomer instanceof Lead) {
                $matchedLead = $selectedCustomer;
                $explicitlySelected = true;
            }
        }

        if (! $matchedClient && ! $matchedLead && $customerContactNumber) {
            $matchedStaff = $this->findStaffByContactNumber((int) $user->company_id, $customerContactNumber);
            if ($matchedStaff) {
                $customerName = $matchedStaff->name ?: ($matchedStaff->user?->name ?: null);
                $customerEmail = $matchedStaff->user?->email ?: null;
            } else {
                if (! $explicitlySelected) {
                    $matchedClient = $this->findClientByContactNumber((int) $user->company_id, $customerContactNumber);
                    $matchedLead = $matchedClient
                        ? null
                        : $this->findLeadByContactNumber((int) $user->company_id, $customerContactNumber);

                    if ($matchedClient || $matchedLead) {
                        throw ValidationException::withMessages([
                            'customer_contact_number' => 'This phone number already exists for another client.',
                        ]);
                    }
                } else {
                    $matchedClient = $this->findClientByContactNumber((int) $user->company_id, $customerContactNumber);
                    if ($matchedClient) {
                        $customerName = $matchedClient->name ?: null;
                        $customerEmail = $matchedClient->email ?: null;
                        $customerAddress = $matchedClient->address ?: $customerAddress;
                    } else {
                        $matchedLead = $this->findLeadByContactNumber((int) $user->company_id, $customerContactNumber);
                        if ($matchedLead) {
                            $customerName = $matchedLead->client_name ?: $customerName;
                            $customerEmail = $matchedLead->email ?: $customerEmail;
                            $customerAddress = $matchedLead->address ?: $customerAddress;
                        }
                    }
                }
            }
        }

        $originalEmail = Str::lower(trim((string) $ticket->customer_email));
        $emailUnchanged = $customerEmail !== null && $originalEmail !== '' && $customerEmail === $originalEmail;

        if (! $matchedStaff && ! $matchedClient && ! $matchedLead) {
            $matchedClient = $this->findClientByIdentity((int) $user->company_id, $customerName, $customerEmail, $customerAddress);
            $matchedLead = $matchedClient
                ? null
                : $this->findLeadByIdentity((int) $user->company_id, $customerName, $customerEmail, $customerAddress);

            if (! $explicitlySelected && ! $emailUnchanged && $customerEmail && $matchedClient) {
                throw ValidationException::withMessages([
                    'customer_email' => 'This email already exists for another client.',
                ]);
            }
        }

        if ($matchedClient) {
            $customerName = $matchedClient->name ?: $customerName;
            $customerEmail = $matchedClient->email ?: $customerEmail;
            $customerAddress = $matchedClient->address ?: $customerAddress;
        } elseif ($matchedLead) {
            $customerName = $matchedLead->client_name ?: $customerName;
            $customerEmail = $matchedLead->email ?: $customerEmail;
            $customerAddress = $matchedLead->address ?: $customerAddress;
        }

        if ($customerEmail && ! $matchedStaff && ! $matchedClient && ! $matchedLead) {
            $company = Company::query()->find($user->company_id);
            if ($company && $company->isInternalStaffOrAdminEmail($customerEmail)) {
                throw ValidationException::withMessages([
                    'customer_email' => 'This email belongs to a company administrator or staff member and cannot be assigned to a support ticket client.',
                ]);
            }

            $existingUser = \App\Models\User::query()->where('email', $customerEmail)->first();
            if ($existingUser && (int) $existingUser->company_id !== (int) $user->company_id) {
                throw ValidationException::withMessages([
                    'customer_email' => 'Customer email is already registered.',
                ]);
            }
        }

        if (! $customerName) {
            $messages = [];

            if (! $customerName) {
                $messages['customer_name'] = 'Customer name is required.';
            }

            throw ValidationException::withMessages($messages);
        }

        $messages = [];

        if (filled($validated['project_id'] ?? null)) {
            $selectedProjectId = (int) $validated['project_id'];
            $matchedCustomer = $matchedClient ?: $matchedLead;

            if (! $matchedCustomer) {
                $messages['project_id'] = 'Select an existing client/contact before choosing a project.';
            } elseif (! $this->customerCanUseProject(
                $selectedSourceType ?? ($matchedClient ? 'client' : 'lead'),
                $matchedCustomer,
                $selectedProjectId
            )) {
                $messages['project_id'] = 'Selected project does not belong to this customer.';
            }
        }

        if ($messages) {
            throw ValidationException::withMessages($messages);
        }

        $assignedInput = $request->input('assigned_to');
        $assignedUserIds = collect(is_array($assignedInput) ? $assignedInput : ($assignedInput ? [$assignedInput] : []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $assigneeIds, true))
            ->unique()
            ->values()
            ->all();
        $primaryAssignedTo = $assignedUserIds[0] ?? null;

        DB::transaction(function () use ($ticket, $validated, $customerContactNumber, $customerName, $customerEmail, $customerAddress, $user, $sync, $matchedStaff, $matchedClient, $matchedLead, $assignedUserIds, $primaryAssignedTo) {
            if ($matchedClient || $matchedLead) {
                $this->syncSelectedCustomerRecord($matchedClient ?: $matchedLead, $customerName, $customerEmail, $customerContactNumber, $customerAddress);
            } elseif ($customerName && ! $matchedStaff) {
                $digits = $customerContactNumber ? (preg_replace('/\\D+/', '', $customerContactNumber) ?: '') : '';
                $phoneNumber = $customerContactNumber
                    ? ($digits !== '' ? $digits : $customerContactNumber)
                    : null;

                $sync->upsert([
                    'company_id' => (int) $user->company_id,
                    'name' => $customerName,
                    'email' => $customerEmail,
                    'phone' => $phoneNumber,
                    'mobile' => $phoneNumber,
                    'address' => $customerAddress,
                ]);
            }

            $ticket->update([
                'assigned_to' => $primaryAssignedTo,
                'project_id' => array_key_exists('project_id', $validated)
                    ? (filled($validated['project_id'] ?? null) ? (int) $validated['project_id'] : null)
                    : $ticket->project_id,
                'customer_contact_number' => $customerContactNumber,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_address' => $customerAddress,
                'type' => $validated['type'],
                'referenced_by' => filled($validated['referenced_by'] ?? null) ? $validated['referenced_by'] : null,
                'subject' => $validated['subject'],
                'priority' => $validated['priority'],
                'board_stage' => $this->resolveBoardStageForTicketUpdate(
                    $ticket,
                    $validated['priority'],
                    $primaryAssignedTo
                ),
                'visit_date' => $validated['visit_date'] ?? null,
                'description' => $validated['description'],
            ]);

            if (Schema::hasTable('ticket_user_assignments')) {
                $ticket->assignedUsers()->sync($assignedUserIds);
            }
        });

        $assignmentHistory->syncCurrentAssignments(
            $ticket->refresh(),
            $assignedUserIds,
            $user,
            'Manual'
        );

        try {
            app(DashboardNotificationService::class)->notifyTicketAssigned($ticket, $assignedUserIds);
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated successfully.');
    }

    public function lookupCustomer(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'contact_number' => 'nullable|string|max:30',
            'customer_name' => 'nullable|string|max:191',
            'customer_email' => 'nullable|string|max:191',
            'refine_term' => 'nullable|string|max:191',
            'search_clients' => 'nullable|boolean',
            'search_field' => ['nullable', 'string', Rule::in(['name', 'contact_number', 'email'])],
        ]);

        $contactNumber = filled($validated['contact_number'] ?? null)
            ? trim((string) $validated['contact_number'])
            : null;
        $customerName = filled($validated['customer_name'] ?? null)
            ? trim((string) $validated['customer_name'])
            : null;
        $customerEmail = filled($validated['customer_email'] ?? null)
            ? Str::lower(trim((string) $validated['customer_email']))
            : null;
        $refineTerm = filled($validated['refine_term'] ?? null)
            ? trim((string) $validated['refine_term'])
            : null;
        $searchClients = (bool) ($validated['search_clients'] ?? false);
        $searchField = $validated['search_field'] ?? null;

        if ($searchField === null) {
            $searchField = $customerEmail ? 'email' : ($contactNumber ? 'contact_number' : 'name');
        }

        if ($searchField === 'name' && ($customerName || $searchClients)) {
            return response()->json([
                'mode' => 'search',
                'results' => $this->searchCustomersByName((int) $user->company_id, $customerName ?? '', $refineTerm),
            ]);
        }

        if ($searchField === 'contact_number' || $searchField === 'email') {
            $searchTerm = $searchField === 'email' ? $customerEmail : $contactNumber;

            if (! $searchTerm) {
                $errorKey = $searchField === 'email' ? 'customer_email' : 'contact_number';
                $label = $searchField === 'email' ? 'Email' : 'Contact number';

                throw ValidationException::withMessages([
                    $errorKey => $label . ' is required.',
                ]);
            }

            return response()->json([
                'mode' => 'search',
                'results' => $this->searchCustomersByField((int) $user->company_id, $searchField, $searchTerm, $refineTerm),
            ]);
        }

        if (! $contactNumber) {
            throw ValidationException::withMessages([
                'contact_number' => 'Contact number is required.',
            ]);
        }

        $staff = $this->findStaffByContactNumber((int) $user->company_id, $contactNumber);
        if ($staff) {
            return response()->json([
                'found' => true,
                'locked' => true,
                'name' => $staff->name ?: ($staff->user?->name ?: null),
                'email' => $staff->user?->email ?: null,
                'phone' => $staff->contact_number,
                'phones' => $this->phoneOptions([$staff->contact_number]),
                'address' => null,
                'addresses' => [],
            ]);
        }

        $client = $this->findClientByContactNumber((int) $user->company_id, $contactNumber);

        if ($client) {
            $addresses = [];

            try {
                $addresses = $this->clientAddressOptions($client);
            } catch (\Throwable $exception) {
                Log::warning('Unable to load client ticket address options.', [
                    'client_id' => $client->id,
                    'company_id' => $client->company_id,
                    'contact_number' => $contactNumber,
                    'message' => $exception->getMessage(),
                ]);
            }

            return response()->json([
                'found' => true,
                'locked' => true,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $this->primaryPhoneValue([$client->mobile, $client->phone]),
                'phones' => $this->phoneOptions([$client->mobile, $client->phone]),
                'address' => $client->address,
                'addresses' => $addresses,
            ]);
        }

        $lead = $this->findLeadByContactNumber((int) $user->company_id, $contactNumber);
        if (! $lead) {
            return response()->json(['found' => false]);
        }

        try {
            $addresses = $this->leadAddressOptions($lead);
        } catch (\Throwable $exception) {
            Log::warning('Unable to load lead contact ticket address options.', [
                'lead_id' => $lead->id,
                'company_id' => $lead->company_id,
                'contact_number' => $contactNumber,
                'message' => $exception->getMessage(),
            ]);
            $addresses = [];
        }

        return response()->json([
            'found' => true,
            'locked' => true,
            'name' => $lead->client_name,
            'email' => $lead->email,
            'phone' => $lead->mobile,
            'phones' => $this->phoneOptions([$lead->mobile]),
            'address' => $lead->address,
            'addresses' => $addresses,
        ]);
    }

    public function customerHistory(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'source_type' => ['required', 'string', Rule::in(['client', 'lead'])],
            'source_id' => 'required|integer|min:1',
        ]);

        $customer = $this->resolveCustomerHistorySource(
            (int) $user->company_id,
            (string) $validated['source_type'],
            (int) $validated['source_id']
        );

        abort_unless($customer !== null, 404);

        return response()->json(
            $this->buildCustomerHistoryPayload($user, (string) $validated['source_type'], $customer)
        );
    }

    public function checkCustomerEmail(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'customer_email' => 'nullable|email|max:191',
            'selected_customer_source_type' => ['nullable', 'string', Rule::in(['client', 'lead'])],
            'selected_customer_source_id' => 'nullable|integer|min:1',
        ]);

        $email = filled($validated['customer_email'] ?? null)
            ? Str::lower(trim((string) $validated['customer_email']))
            : null;

        if (! $email) {
            return response()->json(true);
        }

        $selectedSourceType = $validated['selected_customer_source_type'] ?? null;
        $selectedSourceId = isset($validated['selected_customer_source_id']) ? (int) $validated['selected_customer_source_id'] : null;
        $selectedCustomer = ($selectedSourceType && $selectedSourceId)
            ? $this->resolveCustomerHistorySource((int) $user->company_id, $selectedSourceType, $selectedSourceId)
            : null;

        try {
            $this->assertCustomerEmailAvailable((int) $user->company_id, $email, $selectedCustomer);

            return response()->json(true);
        } catch (ValidationException $exception) {
            return response()->json($exception->errors()['customer_email'][0] ?? 'This email is already in use.');
        }
    }

    public function checkCustomerPhone(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'customer_contact_number' => 'nullable|string|max:30',
            'selected_customer_source_type' => ['nullable', 'string', Rule::in(['client', 'lead'])],
            'selected_customer_source_id' => 'nullable|integer|min:1',
        ]);

        $contactNumber = filled($validated['customer_contact_number'] ?? null)
            ? trim((string) $validated['customer_contact_number'])
            : null;

        if (! $contactNumber) {
            return response()->json(true);
        }

        $selectedSourceType = $validated['selected_customer_source_type'] ?? null;
        $selectedSourceId = isset($validated['selected_customer_source_id']) ? (int) $validated['selected_customer_source_id'] : null;
        $selectedCustomer = ($selectedSourceType && $selectedSourceId)
            ? $this->resolveCustomerHistorySource((int) $user->company_id, $selectedSourceType, $selectedSourceId)
            : null;

        try {
            $this->assertCustomerPhoneAvailable((int) $user->company_id, $contactNumber, $selectedCustomer);

            return response()->json(true);
        } catch (ValidationException $exception) {
            return response()->json($exception->errors()['customer_contact_number'][0] ?? 'This phone number is already in use.');
        }
    }

    public function visitDateWorkloads(Request $request, AmcTechnicianAssignmentService $assignmentService)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        $validated = $request->validate([
            'visit_date' => ['required', 'date'],
        ]);

        $visitDate = Carbon::parse($validated['visit_date'])->startOfDay();
        $assignees = $this->ticketAssignees($user);

        return response()->json([
            'visit_date' => $visitDate->toDateString(),
            'assignees' => $assignmentService->ticketFormWorkloads((int) $user->company_id, $visitDate, $assignees),
        ]);
    }

    public function reply(Request $request, Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if (! $user->isSuperAdmin() && ! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        abort_unless($this->canEditSupportTicket($user), 403);

        $validated = $request->validate([
            'message' => 'required|string',
        ]);

        TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $validated['message'],
        ]);

        if ($user->isSuperAdmin() && $ticket->status === 'open') {
            $ticket->update(['status' => 'answered']);
        }

        return back()->with('success', 'Reply posted successfully.');
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if (! $user->isSuperAdmin() && ! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        abort_unless($this->canEditSupportTicket($user), 403);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_keys(Ticket::statusOptions()))],
        ]);

        $ticket->loadCount('replies');

        $updates = [
            'status' => $validated['status'],
            'board_stage' => Ticket::stageForStatus(
                $validated['status'],
                $ticket->priority,
                $ticket->assigned_to ? (int) $ticket->assigned_to : null,
                (int) $ticket->replies_count,
                $validated['status'] === 'open' ? $this->preferredOpenBoardStage($ticket->board_stage, (int) $user->company_id) : null,
                (int) $user->company_id
            ),
        ];

        if (
            in_array($validated['status'], ['answered', 'resolved', 'closed', 'hold', 'reschedule'], true)
            && $ticket->start_time !== null
        ) {
            $updates['end_time'] = now();
        }

        $ticket->update([
            ...$updates,
        ]);

        return back()->with('success', 'Ticket status updated.');
    }

    public function repeatComplaint(Request $request, Ticket $ticket, TicketRepeatWorkflowService $repeatWorkflow)
    {
        $user = auth()->user();
        abort_unless($user, 403);

        if (! $user->isSuperAdmin() && ! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        abort_unless($user->hasAnyPermission('support.edit', 'tickets.manage', 'tickets.reply'), 403);

        $assigneeIds = $this->ticketAssignees($user)->pluck('id')->all();

        $validated = $request->validate([
            'assigned_to' => ['nullable', 'integer', Rule::in($assigneeIds)],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $repeatWorkflow->reopenAsRepeatComplaint(
            $ticket,
            $user,
            isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
            isset($validated['note']) ? trim((string) $validated['note']) : null
        );

        return back()->with('success', 'Ticket reopened as repeat complaint.');
    }

    public function updateBoardStage(Request $request, Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        if (! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        abort_unless($this->canEditSupportTicket($user), 403);

        $validated = $request->validate([
            'board_stage' => ['required', Rule::in(array_keys(Ticket::boardStageOptions((int) $user->company_id)))],
        ]);

        $ticket->update([
            'board_stage' => $validated['board_stage'],
            'status' => Ticket::statusForStage($validated['board_stage'], (int) $user->company_id),
        ]);

        return response()->json([
            'message' => 'Ticket stage updated successfully.',
            'ticket' => [
                'id' => $ticket->id,
                'status' => $ticket->status,
                'board_stage' => $ticket->board_stage,
                'status_label' => $ticket->status_label,
            ],
        ]);
    }

    private function syncManualTaskMasters(int $companyId, array $taskItems): array
    {
        $company = Company::query()->find($companyId);
        if (! $company) {
            return $taskItems;
        }

        $taskMasters = $company->normalizedTaskMasterList();
        $existingNames = collect($taskMasters)
            ->pluck('name')
            ->map(fn ($name) => Str::lower(Str::squish((string) $name)))
            ->values()
            ->all();

        $updated = false;

        foreach ($taskItems as $taskItem) {
            $name = Str::squish((string) ($taskItem['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            if (! in_array(Str::lower($name), $existingNames, true)) {
                $taskMasters[] = ['name' => $name];
                $existingNames[] = Str::lower($name);
                $updated = true;
            }
        }

        if ($updated) {
            $company->update([
                'task_master_list' => array_values($taskMasters),
            ]);
        }

        return Ticket::normalizeTaskItems($taskItems);
    }
    public function updateAssignedTechnician(Request $request, Ticket $ticket, TicketAssignmentHistoryService $assignmentHistory)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        if (! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        abort_unless($this->canEditSupportTicket($user), 403);

        $assigneeIds = $this->ticketAssignees($user)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $assignedInput = $request->input('assigned_to', $request->input('technician_ids'));

        if (is_array($assignedInput)) {
            $request->validate([
                'assigned_to' => ['nullable', 'array'],
                'assigned_to.*' => ['integer', Rule::in($assigneeIds)],
                'technician_ids' => ['nullable', 'array'],
                'technician_ids.*' => ['integer', Rule::in($assigneeIds)],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);
        } else {
            $request->validate([
                'assigned_to' => ['nullable', 'integer', Rule::in($assigneeIds)],
                'technician_ids' => ['nullable', 'integer', Rule::in($assigneeIds)],
                'reason' => ['nullable', 'string', 'max:500'],
            ]);
        }

        $assignedUserIds = collect(is_array($assignedInput) ? $assignedInput : ($assignedInput ? [$assignedInput] : []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $assigneeIds, true))
            ->unique()
            ->values()
            ->all();

        $primaryAssignedTo = $assignedUserIds[0] ?? null;
        $reason = filled($request->input('reason')) ? trim((string) $request->input('reason')) : null;

        DB::transaction(function () use ($ticket, $user, $primaryAssignedTo, $assignedUserIds, $assignmentHistory, $reason): void {
            $ticket->update([
                'assigned_to' => $primaryAssignedTo,
                'board_stage' => $this->resolveBoardStageForTicketUpdate($ticket, (string) $ticket->priority, $primaryAssignedTo),
            ]);

            if (Schema::hasTable('ticket_user_assignments')) {
                $ticket->assignedUsers()->sync($assignedUserIds);
            }

            $assignmentHistory->syncCurrentAssignments(
                $ticket->refresh(),
                $assignedUserIds,
                $user,
                'Manual',
                $reason
            );
        });

        try {
            app(\App\Services\DashboardNotificationService::class)->notifyTicketAssigned($ticket, $assignedUserIds);
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        $ticket->refresh()->loadMissing(['assignedUser', 'assignedUsers']);

        $assignedUsersCollection = Schema::hasTable('ticket_user_assignments') && $ticket->relationLoaded('assignedUsers') && $ticket->assignedUsers->isNotEmpty()
            ? $ticket->assignedUsers
            : ($ticket->assignedUser ? collect([$ticket->assignedUser]) : collect());

        $assignedUsersData = $assignedUsersCollection->map(function ($u) {
            $uName = (string) $u->name;
            return [
                'id' => (int) $u->id,
                'name' => $uName,
                'avatar_url' => $u->profile_photo_url,
                'initials' => $this->userInitials($uName),
            ];
        })->values()->all();

        return response()->json([
            'success' => true,
            'message' => 'Ticket assigned successfully.',
            'ticket' => [
                'id' => $ticket->id,
                'assigned_to' => $ticket->assigned_to ? (int) $ticket->assigned_to : null,
                'assigned_to_name' => $ticket->assignedUser?->name ?? 'Unassigned',
                'assigned_to_avatar_url' => $ticket->assignedUser?->profile_photo_url,
                'assigned_to_initials' => $this->userInitials($ticket->assignedUser?->name ?? 'Unassigned'),
                'assigned_to_label' => $ticket->assignedUser?->name ?? 'Unassigned',
                'assigned_users' => $assignedUsersData,
                'assigned_user_ids' => collect($assignedUsersData)->pluck('id')->all(),
                'assigned_user_names' => $ticket->assigned_user_names ?: ($ticket->assignedUser?->name ?? 'Unassigned'),
                'assigned_count' => count($assignedUsersData),
            ],
        ]);
    }

    public function destroy(Request $request, Ticket $ticket)
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        if (! $this->canAccessTicket($user, $ticket)) {
            abort(403);
        }

        abort_unless($this->canDeleteSupportTicket($user), 403);

        $ticket->delete();

        $previousUrl = (string) url()->previous();
        $redirectRoute = str_contains($previousUrl, '/services') ? 'services.index' : 'tickets.index';

        return redirect()->route($redirectRoute)->with('success', 'Ticket deleted successfully.');
    }

    private function ticketAssignees($user)
    {
        $query = \App\Models\User::query()
            ->where('company_id', $user->company_id)
            ->activeAssigneeOrAdmin()
            ->withCount([
                'assignedTickets as active_ticket_count' => function ($ticketQuery) {
                    $ticketQuery->whereIn('status', ['open', 'pending', 'assigned', 'in_progress']);
                },
            ])
            ->orderBy('name');

        return $query->get();
    }

    private function ticketQuickAssignTechnicians($user): Collection
    {
        $assignees = $this->ticketAssignees($user);
        $assigneeIds = $assignees->pluck('id')->filter()->values()->all();

        if ($assigneeIds === []) {
            return collect();
        }

        $today = now()->toDateString();

        if (Schema::hasTable('ticket_user_assignments')) {
            $aggregateRows = DB::table('tickets as t')
                ->leftJoin('ticket_user_assignments as tua', 'tua.ticket_id', '=', 't.id')
                ->where('t.company_id', $user->company_id)
                ->whereIn(DB::raw('COALESCE(tua.user_id, t.assigned_to)'), $assigneeIds)
                ->selectRaw(
                    'COALESCE(tua.user_id, t.assigned_to) as assigned_to,
                    COUNT(DISTINCT CASE WHEN DATE(t.visit_date) = ? THEN t.id ELSE NULL END) as today_tickets,
                    COUNT(DISTINCT CASE WHEN t.status = ? THEN t.id ELSE NULL END) as open_tickets,
                    COUNT(DISTINCT CASE WHEN t.status = ? THEN t.id ELSE NULL END) as in_progress_tickets,
                    COUNT(DISTINCT CASE WHEN t.repeat_status = 1 THEN t.id ELSE NULL END) as repeat_tickets,
                    COUNT(DISTINCT CASE WHEN t.status NOT IN (?, ?) THEN t.id ELSE NULL END) as total_active_tickets',
                    [$today, 'open', 'in_progress', 'resolved', 'closed']
                )
                ->groupBy(DB::raw('COALESCE(tua.user_id, t.assigned_to)'))
                ->get()
                ->keyBy('assigned_to');
        } else {
            $aggregateRows = Ticket::query()
                ->where('company_id', $user->company_id)
                ->whereIn('assigned_to', $assigneeIds)
                ->selectRaw(
                    'assigned_to,
                    SUM(CASE WHEN DATE(visit_date) = ? THEN 1 ELSE 0 END) as today_tickets,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_tickets,
                    SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_tickets,
                    SUM(CASE WHEN repeat_status = 1 THEN 1 ELSE 0 END) as repeat_tickets,
                    SUM(CASE WHEN status NOT IN (?, ?) THEN 1 ELSE 0 END) as total_active_tickets',
                    [$today, 'open', 'in_progress', 'resolved', 'closed']
                )
                ->groupBy('assigned_to')
                ->get()
                ->keyBy('assigned_to');
        }

        return $assignees->map(function ($assignee) use ($aggregateRows) {
            $stats = $aggregateRows->get($assignee->id);
            $name = (string) $assignee->name;

            return [
                'id' => $assignee->id,
                'name' => $name,
                'avatar_url' => $assignee->profile_photo_url,
                'initials' => $this->userInitials($name),
                'today_tickets' => (int) ($stats->today_tickets ?? 0),
                'open_tickets' => (int) ($stats->open_tickets ?? 0),
                'in_progress_tickets' => (int) ($stats->in_progress_tickets ?? 0),
                'repeat_tickets' => (int) ($stats->repeat_tickets ?? 0),
                'total_active_tickets' => (int) ($stats->total_active_tickets ?? 0),
                'active_ticket_count' => (int) ($assignee->active_ticket_count ?? 0),
                'is_company_admin' => (bool) $assignee->isCompanyAdmin(),
            ];
        })->sortBy(function (array $item): string {
            return sprintf(
                '%06d|%s|%010d',
                (int) $item['total_active_tickets'],
                Str::lower(Str::squish((string) $item['name'])),
                (int) $item['id']
            );
        })->values();
    }

    private function userInitials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($parts === []) {
            return 'NA';
        }

        return Str::upper(collect($parts)->take(2)->map(fn ($part) => Str::substr($part, 0, 1))->implode(''));
    }

    private function findStaffByContactNumber(int $companyId, string $contactNumber): ?Staff
    {
        $raw = trim((string) $contactNumber);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\\D+/', '', $raw) ?: '';
        if ($digits === '') {
            return null;
        }

        $last10 = strlen($digits) > 10 ? substr($digits, -10) : $digits;
        $last4 = strlen($last10) >= 4 ? substr($last10, -4) : $last10;

        $candidates = Staff::query()
            ->with(['user:id,name,email'])
            ->where('company_id', $companyId)
            ->whereNotNull('contact_number')
            ->when($last4 !== '', fn ($query) => $query->where('contact_number', 'like', '%' . $last4 . '%'))
            ->latest('id')
            ->get(['id', 'user_id', 'company_id', 'name', 'contact_number']);

        $exactMatch = $candidates->first(function (Staff $staff) use ($digits) {
            $stored = preg_replace('/\\D+/', '', (string) $staff->contact_number) ?: '';

            return $stored !== '' && $stored === $digits;
        });

        if ($exactMatch) {
            return $exactMatch;
        }

        return $candidates->first(function (Staff $staff) use ($last10) {
            $stored = preg_replace('/\\D+/', '', (string) $staff->contact_number) ?: '';
            if ($stored === '') {
                return false;
            }

            $storedLast10 = strlen($stored) > 10 ? substr($stored, -10) : $stored;

            return $storedLast10 !== '' && $storedLast10 === $last10;
        });
    }

    private function findClientByContactNumber(int $companyId, string $contactNumber): ?Client
    {
        $raw = trim((string) $contactNumber);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\\D+/', '', $raw) ?: '';

        return Client::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($raw, $digits) {
                $query->where('phone', $raw)
                    ->orWhere('mobile', $raw);

                if ($digits !== '' && $digits !== $raw) {
                    $query->orWhere('phone', $digits)
                        ->orWhere('mobile', $digits);
                }
            })
            ->latest('id')
            ->first();
    }

    private function findLeadByContactNumber(int $companyId, string $contactNumber): ?Lead
    {
        $raw = trim((string) $contactNumber);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        return Lead::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($raw, $digits) {
                $query->where('mobile', $raw);

                if ($digits !== '' && $digits !== $raw) {
                    $query->orWhere('mobile', $digits);
                }
            })
            ->latest('id')
            ->first();
    }

    private function findClientByIdentity(int $companyId, ?string $name, ?string $email, ?string $address = null): ?Client
    {
        $normalizedName = filled($name) ? trim((string) $name) : null;
        $normalizedEmail = filled($email) ? Str::lower(trim((string) $email)) : null;
        $normalizedAddress = $this->normalizeAddressForMatch($address);

        if (! $normalizedName && ! $normalizedEmail && ! $normalizedAddress) {
            return null;
        }

        $query = Client::query()->where('company_id', $companyId);

        if ($normalizedEmail) {
            return $query
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->latest('id')
                ->first();
        }

        if ($normalizedName && $normalizedAddress) {
            return $query
                ->where('name', $normalizedName)
                ->latest('id')
                ->get(['id', 'company_id', 'name', 'email', 'mobile', 'phone', 'address'])
                ->first(function (Client $client) use ($normalizedAddress) {
                    return $this->normalizeAddressForMatch($client->address) === $normalizedAddress;
                });
        }

        // A bare name match (no email/address to corroborate it) is not a reliable
        // identity signal — different customers frequently share the same name —
        // so we deliberately do not fall back to matching by name alone here.
        return null;
    }

    private function findLeadByIdentity(int $companyId, ?string $name, ?string $email, ?string $address = null): ?Lead
    {
        $normalizedName = filled($name) ? trim((string) $name) : null;
        $normalizedEmail = filled($email) ? Str::lower(trim((string) $email)) : null;
        $normalizedAddress = $this->normalizeAddressForMatch($address);

        if (! $normalizedName && ! $normalizedEmail && ! $normalizedAddress) {
            return null;
        }

        $query = Lead::query()->where('company_id', $companyId);

        if ($normalizedEmail) {
            return $query
                ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
                ->latest('id')
                ->first();
        }

        if ($normalizedName && $normalizedAddress) {
            return $query
                ->where('client_name', $normalizedName)
                ->latest('id')
                ->get(['id', 'company_id', 'client_name', 'email', 'mobile', 'address'])
                ->first(function (Lead $lead) use ($normalizedAddress) {
                    return $this->normalizeAddressForMatch($lead->address) === $normalizedAddress;
                });
        }

        // A bare name match (no email/address to corroborate it) is not a reliable
        // identity signal — different customers frequently share the same name —
        // so we deliberately do not fall back to matching by name alone here.
        return null;
    }

    private function searchCustomersByName(int $companyId, string $search, ?string $refineTerm = null): array
    {
        return $this->searchCustomersByField($companyId, 'name', $search, $refineTerm);
    }

    private function searchCustomersByField(int $companyId, string $searchField, string $search, ?string $refineTerm = null): array
    {
        $field = in_array($searchField, ['name', 'contact_number', 'email'], true) ? $searchField : 'name';
        $term = trim($search);
        $like = '%' . $term . '%';
        $refine = filled($refineTerm) ? trim((string) $refineTerm) : '';
        $refineLike = '%' . $refine . '%';
        $groups = [];

        $pushResult = function (
            string $sourceType,
            int $sourceId,
            ?string $name,
            ?string $email,
            array $phones,
            array $addresses
        ) use (&$groups): void {
            $resolvedName = trim((string) $name);
            if ($resolvedName === '') {
                return;
            }

            $resolvedEmail = filled($email) ? Str::lower(trim((string) $email)) : '';
            $variantKey = $sourceType . ':' . $sourceId;
            $variantPhones = $this->phoneOptions($phones);
            $variantAddresses = array_values(array_reduce(
                $addresses,
                function (array $carry, array $addressOption): array {
                    $value = trim((string) ($addressOption['value'] ?? ''));
                    if ($value === '') {
                        return $carry;
                    }

                    $carry[$this->normalizeAddressForMatch($value)] = [
                        'label' => $addressOption['label'] ?? $value,
                        'value' => $value,
                    ];

                    return $carry;
                },
                []
            ));
            $groupKey = $variantKey;

            if (! isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'key' => $groupKey,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'name' => $resolvedName,
                    'email' => null,
                    'phones' => [],
                    'addresses' => [],
                    'source_labels' => [],
                    'variants' => [],
                ];
            }

            $groups[$groupKey]['source_labels'][$sourceType] = $sourceType === 'lead'
                ? 'Contact'
                : 'Client';

            $groups[$groupKey]['phones'] = array_values(array_reduce(
                array_merge($groups[$groupKey]['phones'], $variantPhones),
                function (array $carry, array $phoneOption): array {
                    $carry[$phoneOption['value']] = $phoneOption;

                    return $carry;
                },
                []
            ));

            $groups[$groupKey]['addresses'] = array_values(array_reduce(
                array_merge($groups[$groupKey]['addresses'], $variantAddresses),
                function (array $carry, array $addressOption): array {
                    $value = trim((string) ($addressOption['value'] ?? ''));
                    if ($value === '') {
                        return $carry;
                    }

                    $carry[Str::lower((string) preg_replace('/\s+/', ' ', $value))] = [
                        'label' => $addressOption['label'] ?? $value,
                        'value' => $value,
                    ];

                    return $carry;
                },
                []
            ));

            if ($groups[$groupKey]['email'] === null && $resolvedEmail !== '') {
                $groups[$groupKey]['email'] = $resolvedEmail;
            }

            $groups[$groupKey]['variants'][$variantKey] = [
                'key' => $variantKey,
                'source_type' => $sourceType,
                'source_label' => $sourceType === 'lead' ? 'Contact' : 'Client',
                'source_id' => $sourceId,
                'name' => $resolvedName,
                'email' => $resolvedEmail ?: null,
                'phone' => $variantPhones[0]['value'] ?? null,
                'phones' => $variantPhones,
                'address' => $variantAddresses[0]['value'] ?? null,
                'addresses' => $variantAddresses,
            ];
        };

        $clientQuery = Client::query()->where('company_id', $companyId);
        $leadQuery = Lead::query()->where('company_id', $companyId);

        if ($field === 'contact_number') {
            $digits = preg_replace('/\D+/', '', $term) ?: '';
            $last4 = $digits !== '' ? substr($digits, -4) : '';

            $clientQuery->where(function ($query) use ($term, $digits, $last4) {
                $query->where('phone', 'like', '%' . $term . '%')
                    ->orWhere('mobile', 'like', '%' . $term . '%');

                if ($digits !== '' && $digits !== $term) {
                    $query->orWhere('phone', 'like', '%' . $digits . '%')
                        ->orWhere('mobile', 'like', '%' . $digits . '%');
                }

                if ($last4 !== '' && $last4 !== $digits) {
                    $query->orWhere('phone', 'like', '%' . $last4 . '%')
                        ->orWhere('mobile', 'like', '%' . $last4 . '%');
                }
            });

            $leadQuery->where(function ($query) use ($term, $digits, $last4) {
                $query->where('mobile', 'like', '%' . $term . '%');

                if ($digits !== '' && $digits !== $term) {
                    $query->orWhere('mobile', 'like', '%' . $digits . '%');
                }

                if ($last4 !== '' && $last4 !== $digits) {
                    $query->orWhere('mobile', 'like', '%' . $last4 . '%');
                }
            });
        } elseif ($field === 'email') {
            $clientQuery->whereRaw('LOWER(email) LIKE ?', ['%' . Str::lower($term) . '%']);
            $leadQuery->whereRaw('LOWER(email) LIKE ?', ['%' . Str::lower($term) . '%']);
        } else {
            $clientQuery->when($term !== '', function ($query) use ($like) {
                $query->where('name', 'like', $like);
            });

            $leadQuery->when($term !== '', function ($query) use ($like) {
                $query->where('client_name', 'like', $like);
            });
        }

        $clientQuery
            ->when($refine !== '', function ($query) use ($refineLike) {
                $query->where(function ($nestedQuery) use ($refineLike) {
                    $nestedQuery->where('email', 'like', $refineLike)
                        ->orWhere('mobile', 'like', $refineLike)
                        ->orWhere('phone', 'like', $refineLike)
                        ->orWhere('address', 'like', $refineLike);
                });
            })
            ->latest('id')
            ->limit($refine !== '' ? 50 : 25)
            ->get(['id', 'company_id', 'name', 'email', 'mobile', 'phone', 'address'])
            ->each(function (Client $client) use ($pushResult): void {
                $addresses = $this->clientAddressOptions($client);
                $pushResult(
                    'client',
                    (int) $client->id,
                    $client->name,
                    $client->email,
                    [$client->mobile, $client->phone],
                    $addresses
                );
            });

        $leadQuery
            ->when($refine !== '', function ($query) use ($refineLike) {
                $query->where(function ($nestedQuery) use ($refineLike) {
                    $nestedQuery->where('email', 'like', $refineLike)
                        ->orWhere('mobile', 'like', $refineLike)
                        ->orWhere('address', 'like', $refineLike);
                });
            })
            ->latest('id')
            ->limit($refine !== '' ? 50 : 25)
            ->get(['id', 'company_id', 'client_name', 'email', 'mobile', 'address'])
            ->each(function (Lead $lead) use ($pushResult): void {
                $addresses = $this->leadAddressOptions($lead);
                $pushResult(
                    'lead',
                    (int) $lead->id,
                    $lead->client_name,
                    $lead->email,
                    [$lead->mobile],
                    $addresses
                );
            });

        return collect(array_values($groups))
            ->map(function (array $group): array {
                $primaryPhone = $group['phones'][0]['value'] ?? null;
                $primaryAddress = $group['addresses'][0]['value'] ?? null;

                return [
                    'key' => $group['key'],
                    'source_type' => $group['source_type'],
                    'source_id' => $group['source_id'],
                    'name' => $group['name'],
                    'email' => $group['email'],
                    'phone' => $primaryPhone,
                    'phones' => $group['phones'],
                    'address' => $primaryAddress,
                    'addresses' => $group['addresses'],
                    'source_labels' => array_values($group['source_labels']),
                    'variants' => array_values($group['variants']),
                ];
            })
            ->sortBy([
                fn (array $customer) => Str::lower((string) $customer['name']),
                fn (array $customer) => Str::lower((string) ($customer['email'] ?? '')),
            ])
            ->values()
            ->all();
    }

    private function phoneOptions(array $phoneValues): array
    {
        return collect($phoneValues)
            ->filter(fn ($value) => filled($value))
            ->map(function ($value) {
                $raw = trim((string) $value);
                $digits = preg_replace('/\D+/', '', $raw) ?: $raw;

                return [
                    'label' => $raw,
                    'value' => $digits,
                ];
            })
            ->unique('value')
            ->values()
            ->all();
    }

    private function primaryPhoneValue(array $phoneValues): ?string
    {
        return collect($this->phoneOptions($phoneValues))->pluck('value')->first();
    }

    private function normalizeAddressForMatch(?string $address): ?string
    {
        $value = trim((string) $address);

        if ($value === '') {
            return null;
        }

        return Str::lower((string) preg_replace('/\s+/', ' ', $value));
    }

    private function resolveCustomerHistorySource(int $companyId, string $sourceType, int $sourceId): Client|Lead|null
    {
        if ($sourceType === 'client') {
            return Client::query()
                ->with(['user:id,name,email', 'state:id,name', 'city:id,name'])
                ->where('company_id', $companyId)
                ->find($sourceId);
        }

        return Lead::query()
            ->with(['state:id,name', 'city:id,name'])
            ->where('company_id', $companyId)
            ->find($sourceId);
    }

    private function syncSelectedCustomerRecord(Client|Lead $customer, ?string $name, ?string $email, ?string $contactNumber, ?string $address): void
    {
        $normalizedEmail = filled($email) ? Str::lower(trim((string) $email)) : null;
        $normalizedContact = filled($contactNumber) ? trim((string) $contactNumber) : null;
        $normalizedAddress = filled($address) ? trim((string) $address) : null;
        $normalizedName = filled($name) ? trim((string) $name) : null;

        if ($normalizedEmail) {
            $this->assertCustomerEmailAvailable((int) $customer->company_id, $normalizedEmail, $customer);
        }

        if ($customer instanceof Client) {
            if ($normalizedName) {
                $customer->name = $normalizedName;
            }
            if ($normalizedEmail !== null) {
                $customer->email = $normalizedEmail;
            }
            if ($normalizedContact !== null) {
                $customer->mobile = $normalizedContact;
                $customer->phone = $normalizedContact;
            }
            if ($normalizedAddress !== null) {
                $customer->address = $normalizedAddress;
            }
            $customer->save();

            if ($normalizedEmail !== null && $customer->user) {
                $customer->user->name = $customer->name;
                $customer->user->email = $normalizedEmail;
                $customer->user->save();
            }

            return;
        }

        if ($normalizedName) {
            $customer->client_name = $normalizedName;
        }
        if ($normalizedEmail !== null) {
            $customer->email = $normalizedEmail;
        }
        if ($normalizedContact !== null) {
            $customer->mobile = $normalizedContact;
        }
        if ($normalizedAddress !== null) {
            $customer->address = $normalizedAddress;
        }
        $customer->save();
    }

    private function assertCustomerEmailAvailable(int $companyId, string $email, Client|Lead|null $selectedCustomer = null): void
    {
        $normalizedEmail = Str::lower(trim($email));

        // The email already belongs to the customer we resolved for this ticket (client, or the
        // lead's own linked email) - that's not a conflict, it's the same person.
        if ($selectedCustomer && Str::lower(trim((string) $selectedCustomer->email)) === $normalizedEmail) {
            return;
        }

        $existingClient = Client::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if ($existingClient) {
            $selectedClientId = $selectedCustomer instanceof Client ? (int) $selectedCustomer->id : null;

            if ($selectedClientId === null || (int) $existingClient->id !== $selectedClientId) {
                throw ValidationException::withMessages([
                    'customer_email' => 'This email already exists for another customer.',
                ]);
            }
        }

        $existingUser = \App\Models\User::query()
            ->whereRaw('LOWER(email) = ?', [$normalizedEmail])
            ->first();

        if ($existingUser && (int) $existingUser->company_id !== $companyId) {
            $selectedUserId = $selectedCustomer instanceof Client ? (int) $selectedCustomer->user?->id : null;

            if ($selectedUserId === null || (int) $existingUser->id !== $selectedUserId) {
                throw ValidationException::withMessages([
                    'customer_email' => 'Customer email is already registered.',
                ]);
            }
        }
    }

    private function assertCustomerPhoneAvailable(int $companyId, string $contactNumber, Client|Lead|null $selectedCustomer = null): void
    {
        $raw = trim($contactNumber);
        if ($raw === '') {
            return;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '';

        // The phone number already belongs to the customer we resolved for this ticket -
        // that's not a conflict, it's the same person.
        if ($selectedCustomer) {
            $selectedNumbers = $selectedCustomer instanceof Client
                ? [$selectedCustomer->mobile, $selectedCustomer->phone]
                : [$selectedCustomer->mobile];

            foreach ($selectedNumbers as $selectedNumber) {
                $selectedNumber = trim((string) $selectedNumber);
                if ($selectedNumber === '') {
                    continue;
                }

                if ($selectedNumber === $raw || $selectedNumber === $digits) {
                    return;
                }
            }
        }

        $existingClient = Client::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($raw, $digits): void {
                $query->where('phone', $raw)
                    ->orWhere('mobile', $raw);

                if ($digits !== '' && $digits !== $raw) {
                    $query->orWhere('phone', $digits)
                        ->orWhere('mobile', $digits);
                }
            })
            ->first();

        if ($existingClient) {
            $selectedClientId = $selectedCustomer instanceof Client ? (int) $selectedCustomer->id : null;

            if ($selectedClientId === null || (int) $existingClient->id !== $selectedClientId) {
                throw ValidationException::withMessages([
                    'customer_contact_number' => 'This phone number already exists for another client.',
                ]);
            }
        }
    }

    private function buildCustomerHistoryPayload($user, string $sourceType, Client|Lead $customer): array
    {
        $identity = $this->customerIdentitySnapshot($sourceType, $customer);
        $historyTickets = $this->customerHistoryTickets($user, $identity);

        return [
            'customer' => [
                'source_label' => $sourceType === 'client' ? 'Client' : 'Contact',
                'name' => $identity['name'],
                'email' => $identity['email'],
                'phone' => $identity['primary_phone'],
                'phones' => $identity['phones'],
                'address' => $identity['primary_address'],
                'addresses' => $identity['addresses'],
                'state' => $identity['state'],
                'city' => $identity['city'],
                'show_url' => $sourceType === 'client'
                    ? route('settings.clients.show', $customer)
                    : route('leads.show', $customer),
            ],
            'projects' => $this->customerProjectOptions($sourceType, $customer),
            'history' => $historyTickets->map(function (Ticket $ticket): array {
                return [
                    'id' => $ticket->getRouteKey(),
                    'ticket_number' => $ticket->ticket_number,
                    'subject' => $ticket->subject,
                    'type' => $ticket->type,
                    'type_label' => $ticket->type_label,
                    'status' => $ticket->status,
                    'status_label' => $ticket->status_label,
                    'priority' => $ticket->priority,
                    'visit_date' => $ticket->visit_date?->format('d M Y'),
                    'created_at' => optional($ticket->created_at)->format('d M Y, g:i A'),
                    'requested_by' => $ticket->user?->name,
                    'assigned_to' => $ticket->assignedUser?->name,
                    'contact_number' => $ticket->customer_contact_number,
                    'email' => $ticket->customer_email,
                    'address' => $ticket->customer_address,
                    'show_url' => route('tickets.show', $ticket),
                ];
            })->values()->all(),
        ];
    }

    private function customerIdentitySnapshot(string $sourceType, Client|Lead $customer): array
    {
        $name = $sourceType === 'client'
            ? trim((string) $customer->name)
            : trim((string) $customer->client_name);
        $email = filled($customer->email ?? null)
            ? Str::lower(trim((string) $customer->email))
            : null;
        $phones = $sourceType === 'client'
            ? $this->phoneOptions([$customer->mobile, $customer->phone])
            : $this->phoneOptions([$customer->mobile]);
        $addresses = $sourceType === 'client'
            ? $this->clientAddressOptions($customer)
            : $this->leadAddressOptions($customer);

        return [
            'name' => $name,
            'email' => $email,
            'primary_phone' => collect($phones)->pluck('value')->first(),
            'phones' => collect($phones)->pluck('label')->values()->all(),
            'primary_address' => collect($addresses)->pluck('value')->first() ?: ($customer->address ?: null),
            'addresses' => collect($addresses)->pluck('value')->values()->all(),
            'state' => $customer->state?->name,
            'city' => $customer->city?->name,
        ];
    }

    private function customerProjectOptions(string $sourceType, Client|Lead $customer): array
    {
        $projects = Project::query()
            ->where('company_id', (int) $customer->company_id)
            ->when(
                $sourceType === 'client',
                fn ($query) => $query->where(function ($projectQuery) use ($customer) {
                    $matchedLeadIds = $this->matchedLeadIdsForClient($customer);
                    $isNameAmbiguous = $this->isClientNameAmbiguous($customer);
                    $customerName = trim((string) $customer->name);

                    $projectQuery->where(function ($scopedQuery) use ($customer, $matchedLeadIds, $isNameAmbiguous, $customerName) {
                        $scopedQuery->whereHas('amcs', function ($amcQuery) use ($customer) {
                            $amcQuery->where('client_id', (int) $customer->id);
                        });

                        if ($matchedLeadIds !== []) {
                            $scopedQuery->orWhereIn('lead_id', $matchedLeadIds);
                        }

                        if ($customerName !== '' && ! $isNameAmbiguous) {
                            $scopedQuery->orWhere(function ($nameQuery) use ($customer) {
                                $nameQuery->where('client_name', trim((string) $customer->name));
                            });
                        }
                    });
                }),
                fn ($query) => $query->where('lead_id', (int) $customer->id)
            )
            ->latest('id')
            ->get(['id', 'uuid', 'reference_no', 'name', 'client_name', 'current_stage']);

        return $projects->map(function (Project $project): array {
            $labelParts = array_filter([
                $project->reference_no ?: 'Project #' . $project->id,
                $project->name ?: null,
            ]);

            return [
                'id' => $project->id,
                'uuid' => $project->uuid,
                'label' => implode(' - ', $labelParts),
                'reference_no' => $project->reference_no,
                'name' => $project->name,
                'client_name' => $project->client_name,
                'stage_label' => $project->stage_label,
                'show_url' => route('projects.show', $project),
            ];
        })->values()->all();
    }

    private function customerCanUseProject(string $sourceType, Client|Lead $customer, int $projectId): bool
    {
        return collect($this->customerProjectOptions($sourceType, $customer))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->contains($projectId);
    }

    private function matchedLeadIdsForClient(Client $client): array
    {
        $name = trim((string) $client->name);
        $email = filled($client->email) ? Str::lower(trim((string) $client->email)) : null;
        $phones = collect([$client->mobile, $client->phone])
            ->map(fn ($value) => preg_replace('/\D+/', '', (string) $value) ?: null)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $address = $this->normalizeAddressForMatch($client->address);

        if ($name === '' || (! $email && $phones === [] && ! $address)) {
            return [];
        }

        return Lead::query()
            ->where('company_id', (int) $client->company_id)
            ->where('client_name', $name)
            ->where(function ($query) use ($email, $phones, $address) {
                $hasCondition = false;

                if ($email) {
                    $query->whereRaw('LOWER(email) = ?', [$email]);
                    $hasCondition = true;
                }

                foreach ($phones as $phone) {
                    if ($hasCondition) {
                        $query->orWhere(function ($phoneQuery) use ($phone) {
                            $phoneQuery->where('mobile', 'like', '%' . $phone . '%');
                        });
                    } else {
                        $query->where(function ($phoneQuery) use ($phone) {
                            $phoneQuery->where('mobile', 'like', '%' . $phone . '%');
                        });
                        $hasCondition = true;
                    }
                }

                if ($address) {
                    if ($hasCondition) {
                        $query->orWhereRaw('LOWER(TRIM(address)) = ?', [$address]);
                    } else {
                        $query->whereRaw('LOWER(TRIM(address)) = ?', [$address]);
                    }
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function customerHasStrongIdentifiers(Client $client): bool
    {
        return filled($client->email)
            || filled($client->mobile)
            || filled($client->phone)
            || filled($client->address);
    }

    private function isClientNameAmbiguous(Client $client): bool
    {
        $name = trim((string) $client->name);

        if ($name === '') {
            return false;
        }

        return Client::query()
            ->where('company_id', (int) $client->company_id)
            ->where('name', $name)
            ->count() > 1;
    }

    private function customerHistoryTickets($user, array $identity)
    {
        $name = trim((string) ($identity['name'] ?? ''));
        $email = filled($identity['email'] ?? null) ? trim((string) $identity['email']) : null;
        $phones = collect($identity['phones'] ?? [])
            ->map(fn ($value) => preg_replace('/\D+/', '', (string) $value) ?: null)
            ->filter()
            ->unique()
            ->values()
            ->all();
        $addresses = collect($identity['addresses'] ?? [])
            ->map(fn ($value) => $this->normalizeAddressForMatch((string) $value))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $hasStrongIdentifiers = $email !== null || $phones !== [] || $addresses !== [];

        return $this->visibleTicketsQuery($user)
            ->whereIn('type', ['complain', 'support'])
            ->where(function ($query) use ($name, $email, $phones, $addresses, $hasStrongIdentifiers) {
                $hasCondition = false;

                if ($hasStrongIdentifiers) {
                    $query->where(function ($strictQuery) use ($email, $phones, $addresses, &$hasCondition) {
                        if ($email) {
                            $strictQuery->whereRaw('LOWER(customer_email) = ?', [Str::lower($email)]);
                            $hasCondition = true;
                        }

                        foreach ($phones as $phone) {
                            if ($hasCondition) {
                                $strictQuery->orWhere('customer_contact_number', 'like', '%' . $phone . '%');
                            } else {
                                $strictQuery->where('customer_contact_number', 'like', '%' . $phone . '%');
                                $hasCondition = true;
                            }
                        }

                        foreach ($addresses as $address) {
                            if ($hasCondition) {
                                $strictQuery->orWhereRaw('LOWER(TRIM(customer_address)) = ?', [$address]);
                            } else {
                                $strictQuery->whereRaw('LOWER(TRIM(customer_address)) = ?', [$address]);
                                $hasCondition = true;
                            }
                        }
                    });

                    if ($name !== '') {
                        $query->where('customer_name', $name);
                    }
                } elseif ($name !== '') {
                    $query->where('customer_name', $name);
                    $hasCondition = true;
                }

                if (! $hasStrongIdentifiers && $email) {
                    if ($hasCondition) {
                        $query->orWhereRaw('LOWER(customer_email) = ?', [Str::lower($email)]);
                    } else {
                        $query->whereRaw('LOWER(customer_email) = ?', [Str::lower($email)]);
                        $hasCondition = true;
                    }
                }

                if (! $hasCondition) {
                    $query->whereRaw('1 = 0');
                }
            })
            ->latest()
            ->limit(12)
            ->get();
    }

    private function clientAddressOptions(Client $client): array
    {
        $companyId = (int) $client->company_id;
        $email = filled($client->email) ? Str::lower(trim((string) $client->email)) : null;
        $name = filled($client->name) ? trim((string) $client->name) : null;
        $contactNumbers = collect([$client->mobile, $client->phone])
            ->filter(fn ($value) => filled($value))
            ->map(function ($value) {
                $raw = trim((string) $value);
                $digits = preg_replace('/\D+/', '', $raw) ?: null;

                return [$raw, $digits];
            })
            ->flatten()
            ->filter(fn ($value) => filled($value))
            ->unique()
            ->values();

        $options = [];
        $seen = [];

        $pushAddress = function (?string $address, string $label) use (&$options, &$seen): void {
            $value = trim((string) $address);
            if ($value === '') {
                return;
            }

            $normalized = Str::lower((string) preg_replace('/\s+/', ' ', $value));
            if (isset($seen[$normalized])) {
                return;
            }

            $seen[$normalized] = true;
            $options[] = [
                'label' => $label,
                'value' => $value,
            ];
        };

        $pushAddress($client->address, 'Client address');

        $applyLeadMatch = function ($query) use ($email, $name, $contactNumbers): void {
            $query->where(function ($matchQuery) use ($email, $name, $contactNumbers) {
                $hasConditions = false;

                if ($email) {
                    $matchQuery->whereRaw('LOWER(email) = ?', [$email]);
                    $hasConditions = true;
                }

                if ($name) {
                    if ($hasConditions) {
                        $matchQuery->orWhere('client_name', $name);
                    } else {
                        $matchQuery->where('client_name', $name);
                        $hasConditions = true;
                    }
                }

                foreach ($contactNumbers as $contactNumber) {
                    if ($hasConditions) {
                        $matchQuery->orWhere('mobile', $contactNumber);
                    } else {
                        $matchQuery->where('mobile', $contactNumber);
                        $hasConditions = true;
                    }
                }

                if (! $hasConditions) {
                    $matchQuery->whereRaw('1 = 0');
                }
            });
        };

        Lead::query()
            ->where('company_id', $companyId)
            ->whereNotNull('address')
            ->where($applyLeadMatch)
            ->latest('id')
            ->get(['id', 'address'])
            ->each(function (Lead $lead) use ($pushAddress): void {
                $pushAddress($lead->address, 'Lead #' . $lead->id);
            });

        Project::query()
            ->with(['lead:id,address'])
            ->where('company_id', $companyId)
            ->whereHas('lead', $applyLeadMatch)
            ->latest('id')
            ->get(['id', 'reference_no', 'lead_id'])
            ->each(function (Project $project) use ($pushAddress): void {
                $label = 'Project ' . ($project->reference_no ?: '#' . $project->id);
                $pushAddress($project->lead?->address, $label);
            });

        Amc::query()
            ->where('company_id', $companyId)
            ->whereNotNull('address')
            ->where(function ($query) use ($client, $email, $name, $contactNumbers) {
                $query->where('client_id', $client->id);

                if ($email) {
                    $query->orWhereRaw('LOWER(email) = ?', [$email]);
                }

                if ($name) {
                    $query->orWhere('customer_name', $name);
                }

                foreach ($contactNumbers as $contactNumber) {
                    $query->orWhere('mobile_number', $contactNumber);
                }
            })
            ->latest('id')
            ->get(['id', 'contract_no', 'address'])
            ->each(function (Amc $amc) use ($pushAddress): void {
                $pushAddress($amc->address, 'AMC ' . ($amc->contract_no ?: '#' . $amc->id));
            });

        return $options;
    }

    private function leadAddressOptions(Lead $lead): array
    {
        $options = [];
        $seen = [];

        $pushAddress = function (?string $address, string $label) use (&$options, &$seen): void {
            $value = trim((string) $address);
            if ($value === '') {
                return;
            }

            $normalized = Str::lower((string) preg_replace('/\s+/', ' ', $value));
            if (isset($seen[$normalized])) {
                return;
            }

            $seen[$normalized] = true;
            $options[] = [
                'label' => $label,
                'value' => $value,
            ];
        };

        $pushAddress($lead->address, 'Lead #' . $lead->id);

        Project::query()
            ->with(['amcs:id,project_id,contract_no,address'])
            ->where('company_id', $lead->company_id)
            ->where('lead_id', $lead->id)
            ->latest('id')
            ->get(['id', 'reference_no', 'lead_id'])
            ->each(function (Project $project) use ($pushAddress): void {
                foreach ($project->amcs as $amc) {
                    $pushAddress($amc->address, 'AMC ' . ($amc->contract_no ?: '#' . $amc->id));
                }
            });

        return $options;
    }

    private function resolveVisibleTicketDocument(Ticket $ticket, string $type): ?TicketDocument
    {
        if ($type === 'invoice') {
            $link = TicketInvoice::query()
                ->where('ticket_id', $ticket->id)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            return $link?->invoice()
                ->where('document_type', 'invoice')
                ->lockForUpdate()
                ->first();
        }

        $link = TicketQuote::query()
            ->where('ticket_id', $ticket->id)
            ->latest('id')
            ->lockForUpdate()
            ->first();

        return $link?->quote()
            ->where('document_type', 'quotation')
            ->lockForUpdate()
            ->first();
    }


    private function snapshotTicketDocument(TicketDocument $document, array $payload): void
    {
        if (! $this->ticketDocumentPayloadChanged($document, $payload)) {
            return;
        }

        $snapshot = new TicketDocument();
        $snapshot->forceFill([
            'ticket_id' => $document->ticket_id,
            'company_id' => $document->company_id,
            'created_by' => $document->created_by,
            'document_type' => $document->document_type,
            'invoice_or_quote' => $document->invoice_or_quote,
            'document_number' => $document->document_number . '-OLD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(4)),
            'items' => $document->items,
            'subtotal' => $document->subtotal,
            'discount' => $document->discount,
            'gst_amount' => $document->gst_amount,
            'total' => $document->total,
            'payment_status' => $document->payment_status,
            'payment_method' => $document->payment_method,
            'billing_snapshot' => $document->billing_snapshot,
            'notes' => trim(implode("\n", array_filter([
                $document->notes,
                'Archived before support document update on ' . now()->toDateTimeString(),
            ]))),
            'created_at' => $document->created_at,
            'updated_at' => now(),
        ])->save();
    }

    private function ticketDocumentPayloadChanged(TicketDocument $document, array $payload): bool
    {
        foreach (['items', 'subtotal', 'discount', 'gst_amount', 'total', 'payment_status', 'payment_method'] as $field) {
            $current = $document->{$field};
            $incoming = $payload[$field] ?? null;

            if ($field === 'items') {
                if (json_encode($current ?? []) !== json_encode($incoming ?? [])) {
                    return true;
                }

                continue;
            }

            if (in_array($field, ['subtotal', 'discount', 'gst_amount', 'total'], true)) {
                if (round((float) $current, 2) !== round((float) $incoming, 2)) {
                    return true;
                }

                continue;
            }

            if ($current !== $incoming) {
                return true;
            }
        }

        return false;
    }

    private function buildTicketDocumentChangeMessages(TicketDocument $document, array $payload): array
    {
        $messages = [];
        $oldItems = $this->normalizeTicketDocumentItems($document->items ?? []);
        $newItems = $this->normalizeTicketDocumentItems($payload['items'] ?? []);

        foreach ($newItems as $key => $newItem) {
            if (! array_key_exists($key, $oldItems)) {
                $messages[] = 'Added service: ' . $this->formatTicketDocumentItemLabel($newItem);
                continue;
            }

            $changes = $this->buildTicketDocumentItemChangeParts($oldItems[$key], $newItem);
            if ($changes !== []) {
                $messages[] = 'Updated service ' . $this->ticketDocumentItemName($newItem) . ': ' . implode(', ', $changes);
            }
        }

        foreach ($oldItems as $key => $oldItem) {
            if (! array_key_exists($key, $newItems)) {
                $messages[] = 'Removed service: ' . $this->formatTicketDocumentItemLabel($oldItem);
            }
        }

        foreach (['subtotal' => 'Subtotal', 'discount' => 'Discount', 'gst_amount' => 'GST amount', 'total' => 'Total'] as $field => $label) {
            $oldValue = round((float) ($document->{$field} ?? 0), 2);
            $newValue = round((float) ($payload[$field] ?? 0), 2);

            if ($oldValue !== $newValue) {
                $messages[] = $label . ' changed from ' . $this->formatTicketDocumentMoney($oldValue) . ' to ' . $this->formatTicketDocumentMoney($newValue);
            }
        }

        if (array_key_exists('payment_status', $payload) && (int) ($document->payment_status ?? 0) !== (int) ($payload['payment_status'] ?? 0)) {
            $messages[] = 'Payment status changed from ' . $this->ticketInvoicePaymentStatusLabel($document->payment_status) . ' to ' . $this->ticketInvoicePaymentStatusLabel($payload['payment_status']);
        }

        if (array_key_exists('payment_method', $payload) && (int) ($document->payment_method ?? -1) !== (int) ($payload['payment_method'] ?? -1)) {
            $messages[] = 'Payment method changed from ' . $this->ticketInvoicePaymentMethodLabel($document->payment_method) . ' to ' . $this->ticketInvoicePaymentMethodLabel($payload['payment_method']);
        }

        return $messages;
    }

    private function normalizeTicketDocumentItems(array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $normalized[$productId > 0 ? 'product:' . $productId : 'row:' . $index . ':' . Str::lower((string) ($item['name'] ?? ''))] = $item;
        }

        return $normalized;
    }

    private function buildTicketDocumentItemChangeParts(array $oldItem, array $newItem): array
    {
        $changes = [];

        foreach (['qty' => 'qty', 'price' => 'price', 'gst' => 'GST', 'total' => 'total'] as $field => $label) {
            $oldValue = $field === 'qty' ? (int) ($oldItem[$field] ?? 0) : round((float) ($oldItem[$field] ?? 0), 2);
            $newValue = $field === 'qty' ? (int) ($newItem[$field] ?? 0) : round((float) ($newItem[$field] ?? 0), 2);

            if ($oldValue !== $newValue) {
                $changes[] = $label . ' ' . ($field === 'qty' ? (string) $oldValue : $this->formatTicketDocumentMoney($oldValue))
                    . ' to ' . ($field === 'qty' ? (string) $newValue : $this->formatTicketDocumentMoney($newValue));
            }
        }

        if (trim((string) ($oldItem['note'] ?? '')) !== trim((string) ($newItem['note'] ?? ''))) {
            $changes[] = 'note changed';
        }

        if (trim((string) ($oldItem['description'] ?? '')) !== trim((string) ($newItem['description'] ?? ''))) {
            $changes[] = 'description changed';
        }

        return $changes;
    }

    private function formatTicketDocumentItemLabel(array $item): string
    {
        return $this->ticketDocumentItemName($item) . ' x ' . (int) ($item['qty'] ?? 0) . ' @ ' . $this->formatTicketDocumentMoney((float) ($item['price'] ?? 0));
    }

    private function ticketDocumentItemName(array $item): string
    {
        return Str::squish((string) ($item['name'] ?? 'Service'));
    }

    private function formatTicketDocumentMoney(float $value): string
    {
        return 'Rs. ' . number_format($value, 2);
    }

    private function ticketInvoicePaymentStatusLabel(?int $paymentStatus): ?string
    {
        return match ((int) $paymentStatus) {
            0 => 'Unpaid',
            1 => 'Paid',
            default => null,
        };
    }

    private function ticketInvoicePaymentMethodLabel(?int $paymentMethod): ?string
    {
        return match ((int) $paymentMethod) {
            0 => 'Cash',
            1 => 'Online',
            default => null,
        };
    }

    private function canAccessTicket($user, Ticket $ticket): bool
    {
        if ((int) $ticket->company_id !== (int) $user->company_id) {
            return false;
        }

        if ($this->canManageCompanyTickets($user)) {
            return true;
        }

        if ((int) $ticket->user_id === (int) $user->id || (int) $ticket->assigned_to === (int) $user->id) {
            return true;
        }

        if (Schema::hasTable('ticket_user_assignments')) {
            if ($ticket->relationLoaded('assignedUsers')) {
                return $ticket->assignedUsers->contains('id', $user->id);
            }

            return $ticket->assignedUsers()->where('users.id', $user->id)->exists();
        }

        return false;
    }

    private function canManageCompanyTickets($user): bool
    {
        return $user->isCompanyAdmin() || $user->hasPermission('tickets.manage');
    }

    private function canAccessInvoice($user, TicketInvoice $ticketInvoice): bool
    {
        return $user->isCompanyAdmin()
            || ((int) ($user->staff?->id ?? 0) > 0 && (int) $ticketInvoice->staff_id === (int) $user->staff->id);
    }

    private function canCreateSupportTicket($user): bool
    {
        return $user->isCompanyAdmin() || $user->hasPermission('support.create');
    }

    private function canEditSupportTicket($user): bool
    {
        return $user->isCompanyAdmin() || $user->hasAnyPermission('support.edit', 'tickets.manage');
    }

    private function canDeleteSupportTicket($user): bool
    {
        return $user->isCompanyAdmin() || $user->hasAnyPermission('support.delete', 'tickets.manage');
    }

    private function preferredOpenBoardStage(?string $boardStage, ?int $companyId = null): ?string
    {
        if (! $boardStage) {
            return null;
        }

        $openStages = $companyId
            ? ServiceStage::orderedForCompany($companyId)
                ->where('mapped_status', 'open')
                ->pluck('key')
                ->all()
            : ['new', 'open', 'blocker'];

        return in_array($boardStage, $openStages, true) ? $boardStage : null;
    }

    private function resolveBoardStageForTicketUpdate(Ticket $ticket, string $priority, ?int $assignedTo): string
    {
        $ticket->loadCount('replies');

        return Ticket::stageForStatus(
            $ticket->status ?: 'open',
            $priority,
            $assignedTo,
            (int) $ticket->replies_count,
            $ticket->status === 'open' ? $this->preferredOpenBoardStage($ticket->board_stage, (int) $ticket->company_id) : null,
            (int) $ticket->company_id
        );
    }

    private function resolveAmcReference(string $amcReference, int $companyId): Amc
    {
        $normalizedReference = trim($amcReference);
        $decodedAmcReference = Amc::decodeRouteKeyValue($normalizedReference);
        $candidateAmcIds = array_values(array_unique(array_filter([
            is_numeric($decodedAmcReference) ? (int) $decodedAmcReference : null,
            ctype_digit($normalizedReference) ? (int) $normalizedReference : null,
        ], static fn ($value) => $value !== null)));

        return Amc::query()
            ->with('assignedStaff.user:id,name')
            ->where('company_id', $companyId)
            ->where(function ($query) use ($normalizedReference, $candidateAmcIds) {
                if ($candidateAmcIds !== []) {
                    $query->whereIn('id', $candidateAmcIds)
                        ->orWhere('contract_no', $normalizedReference);

                    return;
                }

                $query->where('contract_no', $normalizedReference);
            })
            ->firstOrFail();
    }

    private function visibleTicketsQuery($user)
    {
        $relations = ['user', 'assignedUser', 'referencedUser', 'replies.user', 'amc:id,support_ticket_id,contract_no', 'generatedFromAmc:id,contract_no'];
        if (Schema::hasTable('ticket_user_assignments')) {
            $relations[] = 'assignedUsers';
        }

        $ticketsQuery = Ticket::query()
            ->where('company_id', $user->company_id)
            ->with($relations)
            ->latest();

        if (! $this->canManageCompanyTickets($user)) {
            $ticketsQuery->where(function ($query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('assigned_to', $user->id);

                if (Schema::hasTable('ticket_user_assignments')) {
                    $query->orWhereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id));
                }
            });
        }

        return $ticketsQuery;
    }

    private function ticketFilters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:191'],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'integer'],
            'type' => ['nullable', 'string', Rule::in(array_keys(Ticket::typeOptions()))],
            'task_category' => ['nullable', 'string', Rule::in(['all', 'amc', 'complain', 'support'])],
            'task_state' => ['nullable', 'string', Rule::in(['all', 'remaining'])],
            'status' => ['nullable', 'string', Rule::in(array_keys(Ticket::statusOptions()))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'quick_filter' => ['nullable', 'string', Rule::in(array_keys($this->ticketQuickFilterOptions()))],
        ]);

        return [
            'search' => isset($validated['search']) ? trim((string) $validated['search']) : null,
            'priority' => $validated['priority'] ?? null,
            'assigned_to' => isset($validated['assigned_to']) ? (int) $validated['assigned_to'] : null,
            'type' => $validated['type'] ?? null,
            'task_category' => isset($validated['task_category']) && $validated['task_category'] !== 'all' ? $validated['task_category'] : null,
            'task_state' => isset($validated['task_state']) && $validated['task_state'] !== 'all' ? $validated['task_state'] : null,
            'status' => $validated['status'] ?? null,
            'start_date' => isset($validated['start_date']) ? Carbon::parse($validated['start_date'])->toDateString() : null,
            'end_date' => isset($validated['end_date']) ? Carbon::parse($validated['end_date'])->toDateString() : null,
            'quick_filter' => $validated['quick_filter'] ?? null,
        ];
    }

    private function applyTicketFilters($ticketsQuery, array $filters)
    {
        $search = $filters['search'] ?? null;
        $priority = $filters['priority'] ?? null;
        $assignedTo = $filters['assigned_to'] ?? null;
        $type = $filters['type'] ?? null;
        $taskCategory = $filters['task_category'] ?? null;
        $taskState = $filters['task_state'] ?? null;
        $status = $filters['status'] ?? null;
        $startDate = $filters['start_date'] ?? null;
        $endDate = $filters['end_date'] ?? null;
        $quickFilter = $filters['quick_filter'] ?? null;

        $ticketsQuery = $ticketsQuery
            ->when($priority, fn ($query) => $query->where('priority', $priority))
            ->when($assignedTo, function ($query) use ($assignedTo) {
                $query->where(function ($q) use ($assignedTo) {
                    $q->where('assigned_to', $assignedTo);
                    if (Schema::hasTable('ticket_user_assignments')) {
                        $q->orWhereHas('assignedUsers', fn ($sub) => $sub->where('users.id', $assignedTo));
                    }
                });
            })
            ->when($type, fn ($query) => $query->where('type', $type))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($startDate || $endDate, function ($query) use ($startDate, $endDate) {
                if ($startDate) {
                    $query->whereDate('visit_date', '>=', $startDate);
                }

                if ($endDate) {
                    $query->whereDate('visit_date', '<=', $endDate);
                }
            })
            ->when($search, function ($query) use ($search) {
                $like = '%' . $search . '%';

                $query->where(function ($builder) use ($like, $search) {
                    $builder->where('ticket_number', 'like', $like)
                        ->orWhere('subject', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('customer_name', 'like', $like)
                        ->orWhere('customer_email', 'like', $like)
                        ->orWhere('customer_contact_number', 'like', $like)
                        ->orWhere('customer_address', 'like', $like)
                        ->orWhere('priority', 'like', $like)
                        ->orWhere('status', 'like', $like)
                        ->orWhere('type', 'like', $like)
                        ->orWhereDate('visit_date', $search)
                        ->orWhereHas('assignedUser', fn ($assignedUserQuery) => $assignedUserQuery->where('name', 'like', $like))
                        ->when(Schema::hasTable('ticket_user_assignments'), fn ($q) => $q->orWhereHas('assignedUsers', fn ($assignedUserQuery) => $assignedUserQuery->where('name', 'like', $like)))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', $like))
                        ->orWhereHas('referencedUser', fn ($referencedUserQuery) => $referencedUserQuery->where('name', 'like', $like))
                        ->orWhereHas('amc', fn ($amcQuery) => $amcQuery->where('contract_no', 'like', $like))
                        ->orWhereHas('generatedFromAmc', fn ($amcQuery) => $amcQuery->where('contract_no', 'like', $like));
                });
            });

        $ticketsQuery = $this->applyDashboardTaskFilters($ticketsQuery, $taskCategory, $taskState);

        return $this->applyTicketQuickFilter($ticketsQuery, $quickFilter);
    }

    private function applyDashboardTaskFilters($ticketsQuery, ?string $taskCategory, ?string $taskState)
    {
        return $ticketsQuery
            ->when($taskCategory, function ($query) use ($taskCategory) {
                match ($taskCategory) {
                    'amc' => $query->where(function ($categoryQuery) {
                        $categoryQuery
                            ->whereNotNull('generated_from_amc_id')
                            ->orWhereRaw("LOWER(COALESCE(source_type, '')) IN ('amc', 'amc_visit')");
                    }),
                    'complain' => $query->where(function ($categoryQuery) {
                        $categoryQuery
                            ->whereRaw("LOWER(COALESCE(type, '')) = 'complain'")
                            ->whereNull('generated_from_amc_id')
                            ->whereRaw("LOWER(COALESCE(source_type, '')) NOT IN ('amc', 'amc_visit')");
                    }),
                    'support' => $query->where(function ($categoryQuery) {
                        $categoryQuery
                            ->whereRaw("LOWER(COALESCE(type, '')) = 'support'")
                            ->whereNull('generated_from_amc_id')
                            ->whereRaw("LOWER(COALESCE(source_type, '')) NOT IN ('amc', 'amc_visit')");
                    }),
                    default => null,
                };
            })
            ->when($taskState === 'remaining', function ($query) {
                $query->whereRaw("LOWER(COALESCE(status, '')) NOT IN ('resolved', 'closed')");
            });
    }

    private function ticketQuickFilterOptions(): array
    {
        return [
            'all' => 'All',
            'today' => 'Today',
            'week' => 'This Week',
            'month' => 'This Month',
            'next_month' => 'Next Month',
            'repeat_yes' => 'Repeat YES',
            'repeat_no' => 'Repeat NO',
            'open' => 'Open',
            'closed' => 'Closed',
            'in_progress' => 'In Progress',
            'pending' => 'Pending',
            'resolved' => 'Resolved',
            'overdue' => 'Overdue',
            'unassigned' => 'Unassigned',
            'amc_generated' => 'AMC Generated',
            'manual_ticket' => 'Manual Ticket',
        ];
    }

    private function applyTicketQuickFilter($ticketsQuery, ?string $quickFilter)
    {
        return $ticketsQuery->when($quickFilter && $quickFilter !== 'all', function ($query) use ($quickFilter) {
            $today = now()->startOfDay();
            $weekStart = now()->startOfWeek()->startOfDay();
            $weekEnd = now()->endOfWeek()->endOfDay();
            $monthStart = now()->startOfMonth()->startOfDay();
            $monthEnd = now()->endOfMonth()->endOfDay();
            $nextMonthStart = now()->addMonthNoOverflow()->startOfMonth()->startOfDay();
            $nextMonthEnd = now()->addMonthNoOverflow()->endOfMonth()->endOfDay();

            match ($quickFilter) {
                'today' => $query->whereDate('visit_date', $today->toDateString()),
                'week' => $query->whereBetween('visit_date', [$weekStart, $weekEnd]),
                'month' => $query->whereBetween('visit_date', [$monthStart, $monthEnd]),
                'next_month' => $query->whereBetween('visit_date', [$nextMonthStart, $nextMonthEnd]),
                'repeat_yes' => $query->where('repeat_status', true),
                'repeat_no' => $query->where(fn ($repeatQuery) => $repeatQuery->whereNull('repeat_status')->orWhere('repeat_status', false)),
                'open' => $query->where('status', 'open'),
                'closed' => $query->where('status', 'closed'),
                'in_progress' => $query->where('status', 'in_progress'),
                'pending' => $query->where('status', 'hold'),
                'resolved' => $query->where('status', 'resolved'),
                'overdue' => $query->whereDate('visit_date', '<', $today->toDateString())->whereIn('status', ['open', 'in_progress', 'answered', 'hold', 'reschedule']),
                'unassigned' => $query->whereNull('assigned_to')->when(Schema::hasTable('ticket_user_assignments'), fn ($q) => $q->whereDoesntHave('assignedUsers')),
                'amc_generated' => $query->whereNotNull('generated_from_amc_id'),
                'manual_ticket' => $query->whereNull('generated_from_amc_id'),
                default => $query,
            };
        });
    }

    private function ticketQuickFilterCounts($user, array $filters): array
    {
        $baseQuery = $this->applyTicketFilters($this->visibleTicketsQuery($user), array_merge($filters, ['quick_filter' => null]));
        $today = now()->startOfDay();
        $weekStart = now()->startOfWeek()->startOfDay();
        $weekEnd = now()->endOfWeek()->endOfDay();
        $monthStart = now()->startOfMonth()->startOfDay();
        $monthEnd = now()->endOfMonth()->endOfDay();
        $nextMonthStart = now()->addMonthNoOverflow()->startOfMonth()->startOfDay();
        $nextMonthEnd = now()->addMonthNoOverflow()->endOfMonth()->endOfDay();

        $row = (clone $baseQuery)
            ->selectRaw(
                'COUNT(*) as total_count,' .
                'SUM(CASE WHEN DATE(visit_date) = ? THEN 1 ELSE 0 END) as today_count,' .
                'SUM(CASE WHEN visit_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as week_count,' .
                'SUM(CASE WHEN visit_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as month_count,' .
                'SUM(CASE WHEN visit_date BETWEEN ? AND ? THEN 1 ELSE 0 END) as next_month_count,' .
                'SUM(CASE WHEN repeat_status = 1 THEN 1 ELSE 0 END) as repeat_yes_count,' .
                'SUM(CASE WHEN COALESCE(repeat_status, 0) = 0 THEN 1 ELSE 0 END) as repeat_no_count,' .
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as open_count,' .
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as closed_count,' .
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as in_progress_count,' .
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending_count,' .
                'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as resolved_count,' .
                'SUM(CASE WHEN visit_date < ? AND status IN (?, ?, ?, ?, ?) THEN 1 ELSE 0 END) as overdue_count,' .
                'SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned_count,' .
                'SUM(CASE WHEN generated_from_amc_id IS NOT NULL THEN 1 ELSE 0 END) as amc_generated_count,' .
                'SUM(CASE WHEN generated_from_amc_id IS NULL THEN 1 ELSE 0 END) as manual_ticket_count',
                [
                    $today->toDateString(),
                    $weekStart->toDateTimeString(),
                    $weekEnd->toDateTimeString(),
                    $monthStart->toDateTimeString(),
                    $monthEnd->toDateTimeString(),
                    $nextMonthStart->toDateTimeString(),
                    $nextMonthEnd->toDateTimeString(),
                    'open',
                    'closed',
                    'in_progress',
                    'hold',
                    'resolved',
                    $today->toDateString(),
                    'open',
                    'in_progress',
                    'answered',
                    'hold',
                    'reschedule',
                ]
            )
            ->first();

        return [
            'all' => (int) ($row->total_count ?? 0),
            'today' => (int) ($row->today_count ?? 0),
            'week' => (int) ($row->week_count ?? 0),
            'month' => (int) ($row->month_count ?? 0),
            'next_month' => (int) ($row->next_month_count ?? 0),
            'repeat_yes' => (int) ($row->repeat_yes_count ?? 0),
            'repeat_no' => (int) ($row->repeat_no_count ?? 0),
            'open' => (int) ($row->open_count ?? 0),
            'closed' => (int) ($row->closed_count ?? 0),
            'in_progress' => (int) ($row->in_progress_count ?? 0),
            'pending' => (int) ($row->pending_count ?? 0),
            'resolved' => (int) ($row->resolved_count ?? 0),
            'overdue' => (int) ($row->overdue_count ?? 0),
            'unassigned' => (int) ($row->unassigned_count ?? 0),
            'amc_generated' => (int) ($row->amc_generated_count ?? 0),
            'manual_ticket' => (int) ($row->manual_ticket_count ?? 0),
        ];
    }
}
