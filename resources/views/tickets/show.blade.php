<x-app-layout>
    @php
        $authUser = auth()->user();
        $canCreateTicket = $authUser && $authUser->hasPermission('support.create');
        $canManageTicket = $authUser && $authUser->hasAnyPermission('support.edit', 'tickets.manage', 'tickets.reply');
        $canDeleteTicket = $authUser && $authUser->hasAnyPermission('support.delete', 'tickets.manage');
        $canRepeatTicket = $canManageTicket && in_array($ticket->status, ['closed', 'resolved'], true);
        $canAutoAssignRepeat = filled($ticket->assignedUser?->id)
            && (bool) $ticket->assignedUser?->is_active
            && blank(data_get($ticket->assignedUser, 'deleted_at'));

        $originalAssignedStaff = $ticket->assignmentHistories->sortBy('assigned_at')->first()?->staff?->name
            ?? $ticket->repeatHistories->sortBy('repeated_at')->first()?->previousAssignee?->name
            ?? $ticket->previousStaff?->name
            ?? $ticket->assignedUser?->name
            ?? 'Unassigned';

        $lastRepeatHistory = $ticket->repeatHistories->first();
        $lastRepeatDate = $ticket->last_repeat_at ?? $lastRepeatHistory?->repeated_at;

        $statusStyle = match ($ticket->status) {
            'open'        => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
            'in_progress' => 'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
            'answered'    => 'bg-purple-100 text-purple-700 ring-1 ring-purple-200',
            'resolved'    => 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200',
            'hold'        => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
            'reschedule'  => 'bg-cyan-100 text-cyan-700 ring-1 ring-cyan-200',
            'closed'      => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
            default       => 'bg-gray-100 text-gray-600 ring-1 ring-gray-200',
        };

        $priorityStyle = match (strtolower((string) $ticket->priority)) {
            'urgent' => 'bg-rose-100 text-rose-700 ring-1 ring-rose-200',
            'high' => 'bg-orange-100 text-orange-700 ring-1 ring-orange-200',
            'medium' => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
            'low' => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
            default => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
        };

        $currentConditionPhotos = collect($ticket->current_condition_photos ?? [])
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->values()
            ->map(function (string $path, int $index) use ($ticket) {
                return [
                    'name' => basename($path),
                    'url' => \Illuminate\Support\Facades\URL::signedRoute('ticket-photos.open', [
                        'ticket' => $ticket->getRouteKey(),
                        'collection' => 'current-condition',
                        'index' => $index,
                    ]),
                ];
            })
            ->all();

        $afterRepairPhotos = collect($ticket->after_repair_photos ?? [])
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->values()
            ->map(function (string $path, int $index) use ($ticket) {
                return [
                    'name' => basename($path),
                    'url' => \Illuminate\Support\Facades\URL::signedRoute('ticket-photos.open', [
                        'ticket' => $ticket->getRouteKey(),
                        'collection' => 'after-repair',
                        'index' => $index,
                    ]),
                ];
            })
            ->all();

        $qrComplaintAttachments = collect($ticket->attachments ?? [])
            ->map(function ($attachment) {
                $url = \Illuminate\Support\Facades\Storage::disk('public')->url($attachment->file_path);

                return [
                    'name' => $attachment->original_name ?: basename($attachment->file_path),
                    'url' => $url,
                    'mime' => $attachment->mime_type,
                ];
            })
            ->all();

        $customerSignature = filled($ticket->customer_signature)
            ? [
                'name' => basename((string) $ticket->customer_signature),
                'url' => \Illuminate\Support\Facades\URL::signedRoute('ticket-signature.open', [
                    'ticket' => $ticket->getRouteKey(),
                ]),
            ]
            : null;

        $ticketTasks = collect($ticket->tasks ?? [])
            ->map(function ($task) {
                if (is_string($task) || is_numeric($task)) {
                    return trim((string) $task);
                }

                if (is_array($task)) {
                    foreach (['task', 'name', 'title', 'label', 'value', 'text'] as $key) {
                        if (filled($task[$key] ?? null)) {
                            return trim((string) $task[$key]);
                        }
                    }

                    return trim((string) ($task['description'] ?? ''));
                }

                if (is_object($task) && method_exists($task, '__toString')) {
                    return trim((string) $task);
                }

                return '';
            })
            ->filter(fn ($task) => $task !== '')
            ->values()
            ->all();

        $ticketTaskItems = \App\Models\Ticket::normalizeTaskItems($ticket->tasks ?? []);
        $hasServiceReport = filled($ticket->work_summary) || filled($ticket->service_notes) || $currentConditionPhotos !== [] || $afterRepairPhotos !== [];
        $followUpDate = $ticketVisitDate ?? null;
        $followUpLabel = $followUpDate ? ($followUpDate->isFuture() ? $followUpDate->diffForHumans(null, true) : 'Due now') : 'Not scheduled';

        $billingInvoices = collect($ticket->invoices ?? [])
            ->filter(function ($link) use ($authUser) {
                if (! $authUser) {
                    return false;
                }

                if ($authUser->isCompanyAdmin()) {
                    return true;
                }

                return (int) ($link->staff_id ?? 0) === (int) ($authUser->staff?->id ?? 0);
            })
            ->map(function ($link) {
                $invoice = $link->invoice;
                $total = (float) ($link->invoice_total ?? $invoice?->total ?? 0);
                $paidAmount = (int) ($link->payment_status ?? 0) === 1 ? $total : 0.0;
                $pendingAmount = max(0, $total - $paidAmount);

                return [
                    'link' => $link,
                    'invoice' => $invoice,
                    'number' => $link->invoice_number ?: $invoice?->document_number ?: ('INV-' . $link->id),
                    'date' => $invoice?->created_at ?? $link->created_at,
                    'due_date' => $invoice?->created_at?->copy()->addDays(7),
                    'total_amount' => $total,
                    'paid_amount' => $paidAmount,
                    'pending_amount' => $pendingAmount,
                    'status' => (int) ($link->payment_status ?? 0) === 1 ? 'Paid' : 'Unpaid',
                    'payment_method' => (int) ($link->payment_status ?? 0) === 1
                        ? ((int) ($link->payment_method ?? -1) === 0 ? 'Cash' : 'Online')
                        : '-',
                ];
            })->values();

        $billingQuotes = collect($ticket->quotes ?? [])->map(function ($link) {
            $quote = $link->quote;
            $total = (float) ($quote?->total ?? 0);

            return [
                'link' => $link,
                'quote' => $quote,
                'number' => $quote?->document_number ?: ('QTN-' . $link->id),
                'date' => $quote?->created_at ?? $link->created_at,
                'total_amount' => $total,
                'paid_amount' => 0.0,
                'pending_amount' => $total,
                'status' => 'Quotation',
                'payment_method' => '-',
            ];
        })->values();

        $billingTotal = (float) $billingInvoices->sum('total_amount');
        $billingPaid = (float) $billingInvoices->sum('paid_amount');
        $billingPending = (float) $billingInvoices->sum('pending_amount');
        $billingOutstanding = $billingPending;
        $billingCreditNotes = 0.0;
        $firstPaidInvoice = $billingInvoices->first(fn (array $item) => (float) ($item['paid_amount'] ?? 0) > 0);
        $billingMode = $firstPaidInvoice['payment_method'] ?? '-';
        $billingStatus = $billingPending > 0
            ? ($billingPaid > 0 ? 'Partial' : 'Unpaid')
            : ($billingTotal > 0 ? 'Paid' : 'N/A');
        $billingSourceLabel = $billingInvoices->isNotEmpty()
            ? 'Invoice #' . $billingInvoices->first()['number']
            : ($billingQuotes->isNotEmpty() ? 'Quotation #' . $billingQuotes->first()['number'] : null);
        $quotationTotal = (float) $billingQuotes->sum('total_amount');

        $summaryCards = [
            ['label' => 'Priority', 'value' => ucfirst((string) $ticket->priority)],
            ['label' => 'Source', 'value' => $ticket->source_type ? str((string) $ticket->source_type)->replace('_', ' ')->headline()->toString() : 'Manual'],
            ['label' => 'Created Date', 'value' => optional($ticket->created_at)->timezone(config('app.timezone'))->format('d M Y, g:i A') ?? '-'],
            ['label' => 'Updated Date', 'value' => optional($ticket->updated_at)->timezone(config('app.timezone'))->format('d M Y, g:i A') ?? '-'],
            ['label' => 'Visit Date', 'value' => $followUpDate?->format('d M Y') ?? '-'],

            ['label' => 'Repeat Count', 'value' => (string) ($ticket->repeat_count ?: 0)],
            ['label' => 'Generated From', 'value' => $ticket->generatedFromAmc
                ? 'AMC'
                : ($ticket->source_type === 'qr_complaint' ? 'Customer QR Portal' : 'Manual')],
        ];

        $assignedStaffMeta = $assignees->firstWhere('id', $ticket->assigned_to);
        $assignedStaffTicketCount = (int) ($assignedStaffMeta?->active_ticket_count ?? 0);
        $assignedRole = $ticket->assignedUser?->isCompanyAdmin() ? 'Company Admin' : ($ticket->assignedUser?->staff ? 'Staff' : 'User');
        $assignedStaffProfileUrl = $ticket->assignedUser?->staff ? route('teams.staff.show', $ticket->assignedUser->staff) : null;
        $customerProfileUrl = $ticket->project
            ? route('projects.show', $ticket->project)
            : ($ticket->generatedFromAmc ? route('amcs.show', $ticket->generatedFromAmc) : null);

        $assignedInitials = collect(preg_split('/\s+/', trim((string) ($ticket->assignedUser?->name ?? 'U'))) ?: [])
            ->filter()
            ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
            ->take(2)
            ->join('');
        $customerInitials = collect(preg_split('/\s+/', trim((string) ($ticket->customer_name ?: 'C'))) ?: [])
            ->filter()
            ->map(fn ($part) => strtoupper(substr((string) $part, 0, 1)))
            ->take(2)
            ->join('');

        $timelineEvents = collect()
            ->push([
                'date' => $ticket->created_at,
                'title' => 'Ticket Created',
                'description' => 'Created by ' . ($ticket->user?->name ?? 'System') . ' for ' . ($ticket->customer_name ?: 'the customer'),
                'tone' => 'emerald',
            ])
            ->merge(
                $ticket->assignmentHistories
                    ->sortBy('assigned_at')
                    ->map(function ($assignmentHistory) {
                        $title = $assignmentHistory->is_current
                            ? 'Assigned'
                            : ($assignmentHistory->released_at ? 'Reassigned' : 'Assignment Updated');

                        return [
                            'date' => $assignmentHistory->assigned_at,
                            'title' => $title,
                            'description' => trim(implode(' - ', array_filter([
                                'Staff: ' . ($assignmentHistory->staff?->name ?? 'Unassigned'),
                                'Assigned by: ' . ($assignmentHistory->assignedBy?->name ?? 'System'),
                                filled($assignmentHistory->note) ? 'Note: ' . $assignmentHistory->note : null,
                            ]))),
                            'tone' => $assignmentHistory->is_current ? 'blue' : 'slate',
                        ];
                    })
            )
            ->merge(
                $ticket->replies
                    ->sortBy('created_at')
                    ->map(function ($reply) {
                        return [
                            'date' => $reply->created_at,
                            'title' => 'Comment Added',
                            'description' => trim(implode(' - ', array_filter([
                                'By: ' . ($reply->user?->name ?? 'User'),
                                \Illuminate\Support\Str::limit(strip_tags((string) $reply->message), 160),
                            ]))),
                            'tone' => 'purple',
                        ];
                    })
            )
            ->merge(
                $ticket->repeatHistories
                    ->sortBy('repeated_at')
                    ->map(function ($repeatHistory) {
                        return [
                            'date' => $repeatHistory->repeated_at,
                            'title' => 'Repeat Complaint',
                            'description' => trim(implode(' - ', array_filter([
                                'Repeat #' . ($repeatHistory->repeat_count ?? 0),
                                'Previous: ' . ($repeatHistory->previousAssignee?->name ?? 'Unassigned'),
                                'Assigned: ' . ($repeatHistory->assignee?->name ?? 'Unassigned'),
                                filled($repeatHistory->note) ? 'Note: ' . $repeatHistory->note : null,
                            ]))),
                            'tone' => 'amber',
                        ];
                    })
            )
            ->filter(fn ($event) => filled($event['date'] ?? null))
            ->sortBy(fn ($event) => optional($event['date'])->timestamp ?? 0)
            ->values();

        $replyCount = isset($conversationReplies) ? $conversationReplies->total() : $ticket->replies->count();
        $taskCount = count($ticketTasks);
        $attachmentCount = count($currentConditionPhotos) + count($afterRepairPhotos) + count($qrComplaintAttachments) + ($customerSignature ? 1 : 0);
        $billingTabCount = $billingInvoices->count() + $billingQuotes->count();
        $allowedTabs = ['overview', 'conversation', 'tasks', 'attachments', 'billing', 'history', 'notes', 'invoices', 'quotations', 'payments', 'pending', 'paid', 'credit-notes'];
        $initialTab = in_array((string) request()->query('tab', 'overview'), $allowedTabs, true) ? (string) request()->query('tab', 'overview') : 'overview';
    @endphp

    <x-slot name="header">
        <div class="border border-slate-200 bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <a href="{{ route('tickets.index') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-slate-600 transition hover:text-slate-900">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        Back to Tickets
                    </a>

                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <span class="inline-flex items-center border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-emerald-700">
                            {{ $ticket->ticket_number }}
                        </span>
                        <span class="inline-flex items-center px-3 py-1 text-xs font-bold uppercase tracking-[0.18em]
                            {{ match ($ticket->status) {
                                'open'        => 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200',
                                'in_progress' => 'bg-blue-100 text-blue-700 ring-1 ring-blue-200',
                                'answered'    => 'bg-purple-100 text-purple-700 ring-1 ring-purple-200',
                                'resolved'    => 'bg-indigo-100 text-indigo-700 ring-1 ring-indigo-200',
                                'hold'        => 'bg-amber-100 text-amber-700 ring-1 ring-amber-200',
                                'reschedule'  => 'bg-cyan-100 text-cyan-700 ring-1 ring-cyan-200',
                                'closed'      => 'bg-slate-100 text-slate-600 ring-1 ring-slate-200',
                                default       => 'bg-gray-100 text-gray-600 ring-1 ring-gray-200',
                            } }}">
                            {{ $ticket->status_label }}
                        </span>
                    </div>

                    <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-950">
                        {{ $ticket->subject }}
                    </h1>


                </div>

                <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                    @if($canManageTicket)
                        <a href="{{ route('tickets.edit', $ticket) }}" class="inline-flex items-center border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50">
                            Edit Ticket
                        </a>
                    @endif

                    @if($canDeleteTicket)
                        <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" class="inline-flex" data-swal-confirm="Delete this ticket permanently?" data-swal-title="Delete Ticket" data-swal-confirm-button="Delete" data-swal-cancel-button="Cancel">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex items-center bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-rose-700">
                                Delete Ticket
                            </button>
                        </form>
                    @endif

                </div>
            </div>
        </div>
    </x-slot>

    <div class="w-full px-2 py-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-5 border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('warning'))
            <div class="mb-5 border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 shadow-sm">
                {{ session('warning') }}
            </div>
        @endif

        <div class="mb-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-8">
            @foreach($summaryCards as $card)
                <div class="border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">{{ $card['label'] }}</p>
                    <p class="mt-2 text-sm font-semibold text-slate-900">{{ $card['value'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-4 pt-4 sm:px-6">
                <div class="overflow-x-auto">
                    <nav class="-mb-px flex w-max min-w-full flex-nowrap gap-3" aria-label="Ticket detail tabs">
                        @php
                            $tabs = [
                                'overview' => ['label' => 'Overview', 'count' => null],
                                'conversation' => ['label' => 'Conversation', 'count' => null],
                                'tasks' => ['label' => 'Tasks', 'count' => null],
                                'attachments' => ['label' => 'Attachments', 'count' => null],
                                'invoices' => ['label' => 'Invoices', 'count' => $billingInvoices->count()],
                                'quotations' => ['label' => 'Quotations', 'count' => $billingQuotes->count()],
                                'pending' => ['label' => 'Pending Amount', 'count' => null],
                                'paid' => ['label' => 'Paid Amount', 'count' => null],
                            ];
                        @endphp

                        @foreach($tabs as $key => $tab)
                            <a
                                href="{{ route('tickets.show', [$ticket, 'tab' => $key]) }}"
                                data-ticket-tab="{{ $key }}"
                                class="inline-flex shrink-0 items-center gap-2 border-b-2 px-3 py-3 text-[13px] font-semibold transition sm:px-4 {{ $initialTab === $key ? 'border-indigo-600 bg-white text-indigo-700' : 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-700' }}"
                            >
                                <span>{{ $tab['label'] }}</span>
                                @if(! is_null($tab['count']))
                                    <span class="px-2.5 py-0.5 text-xs font-bold {{ $initialTab === $key ? 'bg-indigo-50 text-indigo-700' : 'bg-rose-500 text-white' }}">
                                        {{ $tab['count'] }}
                                    </span>
                                @endif
                            </a>
                        @endforeach
                    </nav>
                </div>
            </div>

            <div class="p-4 sm:p-6 lg:p-8">
                @if($initialTab === 'overview')
                <section data-ticket-section="overview" class="grid gap-5 xl:grid-cols-12">
                    <div class="space-y-5 xl:col-span-4">
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-lg font-bold text-slate-900">Ticket Details</h3>
                                <span class="bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">ID {{ $ticket->id }}</span>
                            </div>

                            <dl class="mt-5 divide-y divide-slate-100 text-sm">
                                @php
                                    $detailRows = [
                                        ['label' => 'Ticket ID', 'value' => $ticket->ticket_number ?: '-'],
                                        ['label' => 'Subject', 'value' => $ticket->subject ?: '-'],
                                        ['label' => 'Billing', 'value' => $billingSourceLabel ?: 'No linked billing record'],
                                        ['label' => 'Company', 'value' => $ticket->customer_name ?: '-'],
                                        ['label' => 'Contact Person', 'value' => $ticket->user?->name ?? '-'],
                                        ['label' => 'Requested By', 'value' => $ticket->referencedUser?->name ?? '-'],
                                        ['label' => 'Contact Number', 'value' => $ticket->customer_contact_number ?: '-'],
                                        ['label' => 'Address', 'value' => $ticket->customer_address ?: '-'],
                                        ['label' => 'Project', 'value' => $ticket->project ? trim(implode(' - ', array_filter([$ticket->project->reference_no, $ticket->project->name]))) : '-'],
                                        ['label' => 'Created By', 'value' => $ticket->user?->name ?? '-'],
                                        ['label' => 'Created At', 'value' => optional($ticket->created_at)->timezone(config('app.timezone'))->format('d M Y, g:i A') ?? '-'],
                                        ['label' => 'Updated At', 'value' => optional($ticket->updated_at)->timezone(config('app.timezone'))->format('d M Y, g:i A') ?? '-'],
                                        ['label' => 'Repeat Count', 'value' => (string) ($ticket->repeat_count ?: 0)],
                                        ['label' => 'Current Assigned Staff', 'value' => $ticket->assigned_user_names ?: ($ticket->assignedUser?->name ?? 'Unassigned')],
                                        ['label' => 'Original Assigned Staff', 'value' => $originalAssignedStaff],
                                        ['label' => 'Current Visit Status', 'value' => $ticket->status_label],
                                    ];
                                @endphp

                                @foreach($detailRows as $row)
                                    <div class="grid grid-cols-2 gap-4 py-3">
                                        <dt class="text-slate-500">{{ $row['label'] }}</dt>
                                        <dd class="text-right font-medium text-slate-900">{{ $row['value'] }}</dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>

                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-lg font-bold text-slate-900">Description</h3>
                                <span class="text-sm text-slate-500">Customer reported issue</span>
                            </div>
                            <div class="mt-4 whitespace-pre-line bg-slate-50 p-4 text-sm leading-7 text-slate-700">{{ $ticket->description }}</div>
                        </div>
                    </div>

                    <div class="space-y-5 xl:col-span-4">
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Customer</h3>
                                    <p class="text-sm text-slate-500">Company and contact profile</p>
                                </div>
                                <div class="flex h-12 w-12 items-center justify-center bg-slate-100 text-sm font-black text-slate-700">{{ $customerInitials ?: 'C' }}</div>
                            </div>
                            <div class="mt-4 space-y-2 text-sm text-slate-600">
                                <div>Company: <span class="font-semibold text-slate-900">{{ $ticket->customer_name ?: '-' }}</span></div>
                                <div>Contact Person: <span class="font-semibold text-slate-900">{{ $ticket->user?->name ?? $ticket->referencedUser?->name ?? '-' }}</span></div>
                                <div>Mobile: <span class="font-semibold text-slate-900">{{ $ticket->customer_contact_number ?: '-' }}</span></div>
                                <div>Email: <span class="font-semibold text-slate-900 break-all">{{ $ticket->customer_email ?: '-' }}</span></div>
                                <div>Address: <span class="font-semibold text-slate-900 whitespace-pre-line">{{ $ticket->customer_address ?: '-' }}</span></div>
                            </div>
                            <div class="mt-4">
                                @if($customerProfileUrl)
                                    <a href="{{ $customerProfileUrl }}" class="inline-flex items-center border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">
                                        View Profile
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Status</h3>
                                    <p class="text-sm text-slate-500">Current status and quick change</p>
                                </div>
                                <span class="inline-flex items-center px-3 py-1 text-xs font-semibold {{ $statusStyle }}">{{ $ticket->status_label }}</span>
                            </div>

                            <div class="mt-4 space-y-2 text-sm text-slate-600">
                                <div>Current Status: <span class="font-semibold text-slate-900">{{ $ticket->status_label }}</span></div>
                                <div>Current Visit Status: <span class="font-semibold text-slate-900">{{ $ticket->board_stage ? str($ticket->board_stage)->replace('_', ' ')->headline()->toString() : '-' }}</span></div>
                                <div>Follow Up: <span class="font-semibold text-slate-900">{{ $followUpDate?->format('d M Y') ?? 'Not scheduled' }}</span></div>
                                <div>Remaining Time: <span class="font-semibold text-slate-900">{{ $followUpLabel }}</span></div>
                            </div>

                            @if($canManageTicket)
                                <form method="POST" action="{{ route('tickets.status.update', $ticket) }}" class="mt-4 space-y-3">
                                    @csrf
                                    @method('PATCH')
                                    <x-searchable-select
                                        name="status"
                                        id="status"
                                        label="Change Status"
                                        :options="collect(\App\Models\Ticket::statusOptions())->map(fn ($label, $value) => ['id' => $value, 'label' => $label])"
                                        :selected="$ticket->status"
                                        placeholder="Search status..."
                                        empty-label="Select Status"
                                    />
                                    <div class="flex justify-end">
                                        <x-primary-button>Save Status</x-primary-button>
                                    </div>
                                </form>
                            @endif
                        </div>
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900">Service Report</h3>
                                    <p class="text-sm text-slate-500">Technician work summary and notes</p>
                                </div>
                                <span class="bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {{ $hasServiceReport ? 'Recorded' : 'Empty' }}
                                </span>
                            </div>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <div class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Work Summary</div>
                                    <div class="mt-2 whitespace-pre-line bg-slate-50 p-4 text-sm leading-7 text-slate-700">
                                        {{ filled($ticket->work_summary) ? $ticket->work_summary : 'No work summary added yet.' }}
                                    </div>
                                </div>

                                <div>
                                    <div class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Service Notes</div>
                                    <div class="mt-2 whitespace-pre-line bg-slate-50 p-4 text-sm leading-7 text-slate-700">
                                        {{ filled($ticket->service_notes) ? $ticket->service_notes : 'No service notes added yet.' }}
                                    </div>
                                </div>
                            </div>
                        </div>


                    </div>

                    <div class="space-y-5 xl:col-span-4">
                        <div class="border border-slate-200 bg-white p-5 " style="max-height: 30%; overflow: auto;">
                            <div class="flex items-center justify-between gap-3">
                                <h3 class="text-lg font-bold text-slate-900">Ticket Timeline</h3>
                                <span class="text-sm text-slate-500">Vertical activity cards</span>
                            </div>

                            <div class="mt-5 space-y-4">
                                @forelse($timelineEvents as $event)
                                    @php
                                        $eventToneClasses = match ($event['tone']) {
                                            'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                            'blue' => 'border-blue-200 bg-blue-50 text-blue-700',
                                            'purple' => 'border-purple-200 bg-purple-50 text-purple-700',
                                            'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
                                            default => 'border-slate-200 bg-slate-50 text-slate-700',
                                        };
                                    @endphp
                                    <div class="relative pl-10">
                                        <div class="absolute left-3 top-6 h-full w-px bg-slate-200"></div>
                                        <div class="absolute left-0 top-2 flex h-7 w-7 items-center justify-center border {{ $eventToneClasses }}">
                                            <span class="h-2.5 w-2.5 bg-current"></span>
                                        </div>
                                        <div class="border border-slate-200 bg-slate-50 p-4">
                                            <div class="flex flex-wrap items-center justify-between gap-2">
                                                <div class="text-sm font-semibold text-slate-900">{{ $event['title'] }}</div>
                                                <div class="text-xs text-slate-500">{{ optional($event['date'])->format('d M Y, g:i A') ?? '-' }}</div>
                                            </div>
                                            <div class="mt-2 text-sm leading-6 text-slate-700">{{ $event['description'] }}</div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                        No activity history available yet.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                        <div class="space-y-4 xl:col-span-4">
                            <div class="border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-lg font-bold text-slate-900">Repeat Complaint</h3>
                                    <span class="px-3 py-1 text-xs font-semibold {{ $ticket->repeat_status ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-600 ring-1 ring-slate-200' }}">
                                        {{ $ticket->repeat_status ? 'Repeat Ticket' : 'Not Repeated' }}
                                    </span>
                                </div>

                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    <div class="border border-slate-100 bg-slate-50 p-4">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Original Assigned Staff</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $originalAssignedStaff }}</p>
                                    </div>
                                    <div class="border border-slate-100 bg-slate-50 p-4">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Last Repeat Date</p>
                                        <p class="mt-2 text-sm font-semibold text-slate-900">{{ $lastRepeatDate?->format('d M Y, g:i A') ?? '-' }}</p>
                                    </div>
                                    <div class="border border-slate-100 bg-slate-50 p-4 sm:col-span-2">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Current Assigned Staff</p>
                                        @if(\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments') && $ticket->assignedUsers->isNotEmpty())
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach($ticket->assignedUsers as $assignedStaff)
                                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 border border-indigo-200 px-3 py-1 text-xs font-semibold text-indigo-700">
                                                        <span class="h-1.5 w-1.5 rounded-full bg-indigo-600"></span>
                                                        {{ $assignedStaff->name }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @else
                                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ $ticket->assigned_user_names ?: ($ticket->assignedUser?->name ?? 'Unassigned') }}</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-4 border border-slate-100 bg-slate-50 p-4">
                                    <div class="flex items-center justify-between">
                                        <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Assignment History</p>
                                        @if($ticket->assignmentHistories->count() > 0)
                                            <span class="text-[11px] font-medium text-slate-400">{{ $ticket->assignmentHistories->count() }} record{{ $ticket->assignmentHistories->count() > 1 ? 's' : '' }}</span>
                                        @endif
                                    </div>
                                    <div class="mt-3 space-y-3 max-h-80 overflow-y-auto pr-1">
                                        @forelse($ticket->assignmentHistories->sortBy('assigned_at') as $assignmentHistory)
                                            <div class="border border-slate-200 bg-white p-4 shadow-sm">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div class="text-sm font-semibold text-slate-900">{{ $assignmentHistory->staff?->name ?? 'Unassigned' }}</div>
                                                    <div class="text-xs text-slate-500">{{ $assignmentHistory->assigned_at?->format('d M Y, g:i A') ?? '-' }}</div>
                                                </div>
                                                <div class="mt-2 grid gap-2 text-sm text-slate-600">
                                                    <div>Assigned By: <span class="font-semibold text-slate-900">{{ $assignmentHistory->assignedBy?->name ?? 'System' }}</span></div>
                                                    <div>Repeat Complaints Handled: <span class="font-semibold text-slate-900">{{ $assignmentHistory->repeat_count_handled ?? 0 }}</span></div>
                                                    <div>Status: <span class="font-semibold text-slate-900">{{ $assignmentHistory->is_current ? 'Current Owner' : ($assignmentHistory->released_at ? 'Transferred' : 'Assigned') }}</span></div>
                                                </div>
                                                @if(filled($assignmentHistory->note))
                                                    <p class="mt-3 whitespace-pre-line bg-slate-50 p-3 text-sm leading-6 text-slate-700">{{ $assignmentHistory->note }}</p>
                                                @endif
                                            </div>
                                        @empty
                                            <div class="border border-dashed border-slate-200 bg-white p-5 text-sm text-slate-500">
                                                No assignment history available.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                @if($canRepeatTicket)
                                    <form method="POST" action="{{ route('tickets.repeat-complaint', $ticket) }}" class="mt-4 space-y-4 border border-amber-200 bg-amber-50/70 p-4">
                                        @csrf
                                        <div class="border border-amber-200 bg-white p-4 text-sm text-amber-900">
                                            @if($canAutoAssignRepeat)
                                                The previously assigned staff is available. This will reopen the same ticket and assign it back automatically to <span class="font-semibold">{{ $ticket->assignedUser?->name }}</span>.
                                            @else
                                                The previously assigned staff is unavailable. Please assign another staff member.
                                            @endif
                                        </div>

                                        <div>
                                            <div class="flex items-center justify-between">
                                                <x-input-label for="repeat_note" value="Repeat Note" />
                                                <x-speech-to-text target="repeat_note" />
                                            </div>
                                            <textarea id="repeat_note" name="note" rows="3" class="mt-1 block w-full border-slate-300 focus:border-amber-500 focus:ring-amber-500" placeholder="Optional note for repeat complaint...">{{ old('note') }}</textarea>
                                            <x-input-error :messages="$errors->get('note')" class="mt-2" />
                                        </div>


                                            <div>
                                                <x-input-label for="repeat_assigned_to" value="Assign To" />
                                                <select id="repeat_assigned_to" name="assigned_to" class="mt-1 block w-full border-slate-300 focus:border-amber-500 focus:ring-amber-500" required>
                                                    <option value="">Select staff member</option>
                                                    @foreach($assignees as $assignee)
                                                        <option value="{{ $assignee->id }}" @selected((string) old('assigned_to') === (string) $assignee->id)>{{ $assignee->name }}</option>
                                                    @endforeach
                                                </select>
                                                <x-input-error :messages="$errors->get('assigned_to')" class="mt-2" />
                                            </div>


                                        <div class="flex justify-end">
                                            <x-primary-button>Mark Repeat Complaint</x-primary-button>
                                        </div>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                @if($initialTab === 'conversation')
                <section data-ticket-section="conversation" class="space-y-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-lg font-bold text-slate-900">Conversation</h3>
                        <span class="text-sm text-slate-500">{{ $replyCount }} reply(s)</span>
                    </div>

                    <div class="space-y-4">
                        <div class="border border-slate-200 bg-slate-50 p-4">
                            <div class="mb-2 text-sm font-semibold text-slate-900">{{ $ticket->user?->name ?? 'User' }}</div>
                            <div class="whitespace-pre-line text-sm leading-7 text-slate-700">{{ $ticket->description }}</div>
                        </div>

                        @if($ticketTasks !== [])
                            <div class="border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="mb-3 text-sm font-semibold text-slate-900">Task List</div>
                                <ul class="space-y-2 text-sm text-slate-700">
                                    @foreach($ticketTasks as $task)
                                        <li class="flex items-start gap-3 bg-slate-50 px-3 py-2">
                                            <span class="mt-0.5 inline-flex h-6 w-6 flex-none items-center justify-center bg-slate-900 text-[11px] font-bold text-white">{{ $loop->iteration }}</span>
                                            <span>{{ $task }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @forelse($conversationReplies as $reply)
                            <div class="border border-slate-200 bg-white p-4 shadow-sm">
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <div class="text-sm font-semibold text-slate-900">{{ $reply->user?->name ?? 'User' }}</div>
                                    <div class="text-xs text-slate-500">{{ $reply->created_at->diffForHumans() }}</div>
                                </div>
                                <div class="whitespace-pre-line text-sm leading-7 text-slate-700">{{ $reply->message }}</div>
                            </div>
                        @empty
                            <div class="border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                No replies yet.
                            </div>
                        @endforelse

                        @if($conversationReplies->hasPages())
                            <div class="border border-slate-200 bg-white px-4 py-3 shadow-sm">
                                {{ $conversationReplies->links() }}
                            </div>
                        @endif
                    </div>

                    @if($canManageTicket)
                        <form method="POST" action="{{ route('tickets.reply', $ticket) }}" class="border border-slate-200 bg-white p-5 shadow-sm">
                            @csrf
                            <div>
                                <div class="flex items-center justify-between">
                                    <x-input-label for="message" value="Reply" />
                                    <x-speech-to-text target="message" />
                                </div>
                                <textarea id="message" name="message" rows="5" class="mt-1 block w-full border-slate-300 focus:border-indigo-500 focus:ring-indigo-500" required></textarea>
                                <x-input-error :messages="$errors->get('message')" class="mt-2" />
                            </div>
                            <div class="mt-4 flex justify-end">
                                <x-primary-button>Send Reply</x-primary-button>
                            </div>
                        </form>
                    @endif
                </section>
                @endif

                @if($initialTab === 'tasks')
                <section data-ticket-section="tasks" class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-900">Tasks</h3>
                        <span class="text-sm text-slate-500">{{ $taskCount }} task(s)</span>
                    </div>
                    <div class="grid gap-4 {{ $canManageTicket ? 'xl:grid-cols-12' : '' }}">
                        @if($canManageTicket)
                            <div class="border border-slate-200 bg-white p-5 shadow-sm xl:col-span-5">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="text-base font-bold text-slate-900">Add Task</h4>
                                        <p class="mt-1 text-sm text-slate-500">Choose a task from the master list or enter a custom one.</p>
                                    </div>
                                    <span class="bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                        {{ count($taskMasters) }} master(s)
                                    </span>
                                </div>

                                <form method="POST" action="{{ route('tickets.tasks.store', $ticket) }}" class="mt-4 space-y-4">
                                    @csrf
                                    <div>
                                        <x-searchable-select
                                            name="task_master_name"
                                            id="task_master_name"
                                            label="Task Master"
                                            :options="collect($taskMasters)->map(fn ($taskMaster) => ['id' => $taskMaster, 'label' => $taskMaster])"
                                            :selected="old('task_master_name')"
                                            placeholder="Search task master..."
                                            empty-label="Choose from task master"
                                            :disabled="$taskMasters === []"
                                        />
                                        @if($taskMasters === [])
                                            <p class="mt-2 text-xs text-amber-600">No task masters are configured for this company yet. You can still enter a custom task below.</p>
                                        @endif
                                        <x-input-error :messages="$errors->get('task_master_name')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="task_name" value="Custom Task" />
                                        <x-text-input id="task_name" name="task_name" type="text" class="mt-1 block w-full" placeholder="Type a task if you don't want to use the dropdown" :value="old('task_name')" />
                                        <p class="mt-1 text-xs text-slate-500">If both are filled, the custom task name will be used.</p>
                                        <x-input-error :messages="$errors->get('task_name')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-searchable-select
                                            name="task_status"
                                            id="task_status"
                                            label="Task Status"
                                            :options="collect($taskStatusOptions)->map(fn ($statusLabel, $statusValue) => ['id' => $statusValue, 'label' => $statusLabel])"
                                            :selected="old('task_status', 'pending')"
                                            placeholder="Search task status..."
                                            empty-label="Select Task Status"
                                        />
                                        <x-input-error :messages="$errors->get('task_status')" class="mt-2" />
                                    </div>

                                    <div class="flex justify-end">
                                        <x-primary-button>Add Task</x-primary-button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        <div class="border border-slate-200 bg-white p-5 shadow-sm {{ $canManageTicket ? 'xl:col-span-7' : '' }}">
                            <h4 class="text-base font-bold text-slate-900">Current Tasks</h4>
                            @if($ticketTaskItems !== [])
                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                    @foreach($ticketTaskItems as $index => $task)
                                        <div class="border border-slate-100 bg-slate-50 p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-sm font-semibold text-slate-900">Task {{ $index + 1 }}</div>
                                                    <div class="mt-1 text-sm leading-6 text-slate-700">{{ $task['name'] }}</div>
                                                </div>
                                                <span class="inline-flex shrink-0 items-center px-2.5 py-1 text-[11px] font-semibold {{ $task['status'] === 'done' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                                    {{ \Illuminate\Support\Str::headline($task['status']) }}
                                                </span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-4 border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                    No tasks have been added yet.
                                </div>
                            @endif
                        </div>
                    </div>
                </section>
                @endif

                @if($initialTab === 'attachments')
                <section data-ticket-section="attachments" class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-900">Attachments</h3>
                        <span class="text-sm text-slate-500">{{ $attachmentCount }} file(s)</span>
                    </div>
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-base font-bold text-slate-900">Current Condition</h4>
                                <span class="bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ count($currentConditionPhotos) }} photo(s)</span>
                            </div>
                            @if($currentConditionPhotos !== [])
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @foreach($currentConditionPhotos as $photo)
                                        <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="group overflow-hidden border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                            <div class="aspect-[4/3] overflow-hidden bg-slate-100">
                                                <img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                            </div>
                                            <div class="border-t border-slate-100 px-3 py-2">
                                                <p class="truncate text-xs font-medium text-slate-700">{{ $photo['name'] }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-4 border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                    No current condition photos uploaded yet.
                                </div>
                            @endif
                        </div>

                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-base font-bold text-slate-900">After Repair</h4>
                                <span class="bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ count($afterRepairPhotos) }} photo(s)</span>
                            </div>
                            @if($afterRepairPhotos !== [])
                                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                    @foreach($afterRepairPhotos as $photo)
                                        <a href="{{ $photo['url'] }}" target="_blank" rel="noopener" class="group overflow-hidden border border-slate-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                            <div class="aspect-[4/3] overflow-hidden bg-slate-100">
                                                <img src="{{ $photo['url'] }}" alt="{{ $photo['name'] }}" class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                                            </div>
                                            <div class="border-t border-slate-100 px-3 py-2">
                                                <p class="truncate text-xs font-medium text-slate-700">{{ $photo['name'] }}</p>
                                            </div>
                                        </a>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-4 border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                    No after repair photos uploaded yet.
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-base font-bold text-slate-900">QR Complaint Attachments</h4>
                            <span class="bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ count($qrComplaintAttachments) }} file(s)</span>
                        </div>
                        @if($qrComplaintAttachments !== [])
                            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                @foreach($qrComplaintAttachments as $attachment)
                                    <a href="{{ $attachment['url'] }}" target="_blank" rel="noopener" class="group overflow-hidden border border-slate-200 bg-slate-50 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                        <div class="flex h-40 items-center justify-center bg-slate-100 text-slate-400">
                                            <div class="text-center">
                                                <div class="text-sm font-semibold text-slate-700">View Attachment</div>
                                                <div class="mt-1 text-xs uppercase tracking-wider">{{ $attachment['mime'] ?: 'File' }}</div>
                                            </div>
                                        </div>
                                        <div class="border-t border-slate-100 px-3 py-2">
                                            <p class="truncate text-xs font-medium text-slate-700">{{ $attachment['name'] }}</p>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="mt-4 border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                No QR complaint attachments uploaded yet.
                            </div>
                        @endif
                    </div>

                    <div class="border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center justify-between gap-3">
                            <h4 class="text-base font-bold text-slate-900">Customer Signature</h4>
                            <span class="bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-600">{{ $customerSignature ? '1 file' : '0 file' }}</span>
                        </div>
                        @if($customerSignature)
                            <a href="{{ $customerSignature['url'] }}" target="_blank" rel="noopener" class="group mt-4 block overflow-hidden border border-slate-200 bg-slate-50 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
                                <div class="flex min-h-56 items-center justify-center bg-slate-100 p-4">
                                    <img src="{{ $customerSignature['url'] }}" alt="Customer signature" class="max-h-56 w-full object-contain transition duration-300 group-hover:scale-[1.01]">
                                </div>
                                <div class="border-t border-slate-100 px-3 py-2">
                                    <p class="truncate text-xs font-medium text-slate-700">{{ $customerSignature['name'] }}</p>
                                </div>
                            </a>
                        @else
                            <div class="mt-4 border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                No customer signature uploaded yet.
                            </div>
                        @endif
                    </div>
                </section>
                @endif

                @if($initialTab === 'billing')
                <section data-ticket-section="billing" class="space-y-4">
                    @php
                        $requestedBillingType = old('document_type', request()->query('document_type', 'quotation'));
                        $billingFormDocument = $requestedBillingType === 'invoice' ? $billingInvoiceDocument : $billingQuotationDocument;
                        $manualProductEntry = (bool) ($ticket?->company?->manual_product_entry ?? false);
                        $submittedNames = is_array(old('name')) ? array_values(old('name', [])) : [];
                        $submittedProductIds = is_array(old('product_id')) ? array_values(old('product_id', [])) : [];
                        $submittedQuantities = is_array(old('qty')) ? array_values(old('qty', [])) : [];
                        $submittedPrices = is_array(old('price')) ? array_values(old('price', [])) : [];
                        $submittedGsts = is_array(old('gst')) ? array_values(old('gst', [])) : [];
                        $submittedNotes = is_array(old('note')) ? array_values(old('note', [])) : [];
                        $submittedDescriptions = is_array(old('description')) ? array_values(old('description', [])) : [];
                        $submittedUnits = is_array(old('unit')) ? array_values(old('unit', [])) : [];

                        if ($manualProductEntry && $submittedNames !== []) {
                            $rowCount = max(count($submittedNames), count($submittedProductIds), count($submittedQuantities), count($submittedPrices), count($submittedGsts), count($submittedNotes), count($submittedDescriptions), count($submittedUnits));
                            $billingFormRows = collect(range(0, max(0, $rowCount - 1)))
                                ->map(function (int $index) use ($submittedNames, $submittedProductIds, $submittedQuantities, $submittedPrices, $submittedGsts, $submittedNotes, $submittedDescriptions, $submittedUnits) {
                                    return [
                                        'product_id' => (string) ($submittedProductIds[$index] ?? ''),
                                        'name' => (string) ($submittedNames[$index] ?? ''),
                                        'description' => (string) ($submittedDescriptions[$index] ?? ''),
                                        'qty' => (float) ($submittedQuantities[$index] ?? 1),
                                        'price' => (float) ($submittedPrices[$index] ?? 0),
                                        'gst' => (float) ($submittedGsts[$index] ?? 0),
                                        'note' => (string) ($submittedNotes[$index] ?? ''),
                                        'unit' => (string) ($submittedUnits[$index] ?? ''),
                                    ];
                                })
                                ->filter(fn ($row) => filled($row['name'] ?? null))
                                ->values()
                                ->all();
                        } else {
                            $billingFormRows = collect(old('product_id') ? array_values(old('product_id', [])) : ($billingFormDocument?->items ?? []))
                                ->map(function ($item, $index) use ($submittedDescriptions) {
                                    if (is_array($item)) {
                                        return [
                                            'product_id' => (string) ($item['product_id'] ?? $item['id'] ?? ''),
                                            'name' => (string) ($item['name'] ?? 'Saved product'),
                                            'description' => (string) ($item['description'] ?? ''),
                                            'qty' => (float) ($item['qty'] ?? 1),
                                            'price' => (float) ($item['price'] ?? 0),
                                            'gst' => (float) ($item['gst'] ?? 0),
                                            'note' => (string) ($item['note'] ?? ''),
                                            'unit' => (string) ($item['unit'] ?? ''),
                                        ];
                                    }

                                    return [
                                        'product_id' => (string) $item,
                                        'name' => '',
                                        'description' => (string) ($submittedDescriptions[$index] ?? ''),
                                        'qty' => (float) old('qty.' . $index, 1),
                                        'price' => (float) old('price.' . $index, 0),
                                        'gst' => (float) old('gst.' . $index, 0),
                                        'note' => (string) old('note.' . $index, ''),
                                        'unit' => '',
                                    ];
                                })
                                ->filter(fn ($row) => filled($row['product_id'] ?? null))
                                ->values()
                                ->all();
                        }

                        if ($billingFormRows === []) {
                            $billingFormRows = [['product_id' => '', 'name' => '', 'description' => '', 'qty' => 1, 'price' => 0, 'gst' => 0, 'note' => '', 'unit' => '']];
                        }
                        $billingUnitOptions = collect($billingUnits ?? [])
                            ->pluck('name')
                            ->merge(collect($billingFormRows)->pluck('unit'))
                            ->merge($billingProducts->pluck('unit'))
                            ->map(fn ($unit) => trim((string) $unit))
                            ->filter()
                            ->unique()
                            ->values();

                        $billingProductOptions = collect($billingProducts ?? [])
                            ->map(fn ($product) => [
                                'id' => (string) $product->id,
                                'name' => (string) $product->name,
                                'description' => (string) ($product->description ?? ''),
                                'brand' => (string) ($product->brand ?? ''),
                                'model' => (string) ($product->model ?? ''),
                                'unit' => (string) ($product->unit ?? ''),
                                'price' => (float) ($product->price ?? 0),
                                'gst' => (float) ($product->gst ?? 0),
                            ]);

                        $savedBillingProducts = collect($billingFormRows)
                            ->filter(fn ($row) => filled($row['product_id'] ?? null))
                            ->reject(fn ($row) => $billingProductOptions->contains('id', (string) ($row['product_id'] ?? '')))
                            ->map(fn ($row) => [
                                'id' => (string) ($row['product_id'] ?? ''),
                                'name' => (string) ($row['name'] ?: ('Saved product #' . ($row['product_id'] ?? ''))),
                                'brand' => '',
                                'model' => '',
                                'unit' => '',
                                'price' => (float) ($row['price'] ?? 0),
                                'gst' => (float) ($row['gst'] ?? 0),
                            ]);

                        $billingProductOptions = $billingProductOptions
                            ->merge($savedBillingProducts)
                            ->unique('id')
                            ->values();
                    @endphp
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-900">Billing</h3>
                        <span class="text-sm text-slate-500">Linked invoices and quotations</span>
                    </div>

                    @if($canManageTicket)
                        <form method="POST" action="{{ route('tickets.billing-document.save', $ticket) }}" class="border border-slate-200 bg-white p-5 shadow-sm" x-data="{
                            documentType: @js($requestedBillingType),
                            manualProductEntry: @js($manualProductEntry),
                            rows: @js($billingFormRows),
                            products: @js($billingProductOptions),
                            unitOptions: @js($billingUnitOptions),
                            gstRates: @js($billingGstRates ?? [0, 5, 12, 18, 28]),
                            discount: Number(@js((float) old('discount', $billingFormDocument?->discount ?? 0))),
                            productSearchOpen: {},
                            productSearchQuery: {},
                            ensureUnitOption(value = '') {
                                const unit = String(value || '').trim();
                                if (unit && !this.unitOptions.includes(unit)) {
                                    this.unitOptions.push(unit);
                                }
                                return unit;
                            },
                            ensureGstOption(value = 0) {
                                const rate = Number(value || 0);
                                if (!this.gstRates.some((r) => Math.abs(Number(r) - rate) < 0.001)) {
                                    this.gstRates.push(rate);
                                    this.gstRates.sort((a, b) => Number(a) - Number(b));
                                }
                                return rate;
                            },
                            unitOptionsFor(selectedUnit = '') {
                                const selected = String(selectedUnit || '').trim();
                                if (selected && !this.unitOptions.includes(selected)) {
                                    this.unitOptions.push(selected);
                                }
                                return this.unitOptions;
                            },
                            addRow() { this.rows.push({ product_id: '', name: '', description: '', qty: 1, price: 0, gst: 0, note: '', unit: '' }); },
                            removeRow(index) { if (this.rows.length > 1) this.rows.splice(index, 1); },
                            findProduct(row) {
                                return this.products.find((item) => String(item.id) === String(row.product_id));
                            },
                            productLabel(row) {
                                const product = this.findProduct(row);
                                return product ? product.name : (row.name || '');
                            },
                            productBrandModel(row) {
                                const product = this.findProduct(row);
                                if (!product) return '';
                                return [product.brand, product.model].filter(Boolean).join(' / ');
                            },
                            productUnit(row) {
                                const product = this.findProduct(row);
                                return product ? product.unit : (row.unit || '');
                            },
                            selectProduct(row, rowElement = null) {
                                const product = this.findProduct(row);
                                if (!product) return;
                                row.price = Number(product.price || 0);
                                const gst = Number(product.gst || 0);
                                this.ensureGstOption(gst);
                                row.gst = gst;
                                row.name = product.name || (row.name || '');
                                row.description = product.description || (row.description || '');
                                const unit = String(product.unit || '').trim();
                                if (unit) {
                                    this.ensureUnitOption(unit);
                                    row.unit = unit;
                                }
                                this.$nextTick(() => {
                                    if (unit) row.unit = unit;
                                    row.gst = gst;

                                    const container = rowElement || document;
                                    const selects = container.querySelectorAll('select');
                                    selects.forEach((select) => {
                                        if (typeof select._syncCustomSelect === 'function') {
                                            select._syncCustomSelect();
                                        } else {
                                            select.dispatchEvent(new Event('change', { bubbles: true }));
                                        }
                                    });
                                });
                            },
                            filteredProducts(query) {
                                const normalized = String(query || '').trim().toLowerCase();
                                if (!normalized) return this.products;
                                return this.products.filter((product) => String([product.name, product.brand, product.model, product.unit].filter(Boolean).join(' ')).toLowerCase().includes(normalized));
                            },
                            setProduct(row, value, index, rowElement = null) {
                                row.product_id = value;
                                this.selectProduct(row, rowElement);
                                this.productSearchQuery[index] = this.productLabel(row);
                                this.productSearchOpen[index] = false;
                            },
                            gstOptionsFor(selectedGst) {
                                const selected = Number(selectedGst || 0);
                                if (!this.gstRates.some((rate) => Math.abs(Number(rate) - selected) < 0.001)) {
                                    this.gstRates.push(selected);
                                    this.gstRates.sort((a, b) => Number(a) - Number(b));
                                }
                                return this.gstRates;
                            },
                            rowTotal(row) {
                                const gst = Number(row.gst || 0);
                                const base = Number(row.qty || 0) * Number(row.price || 0);
                                return (base + ((base * gst) / 100)).toFixed(2);
                            },
                            itemsSubtotal() {
                                return this.rows.reduce((sum, row) => sum + (Number(row.qty || 0) * Number(row.price || 0)), 0);
                            },
                            taxableAmount() {
                                const subtotal = this.itemsSubtotal();
                                const capped = Math.min(Math.max(0, Number(this.discount || 0)), subtotal);
                                return Math.max(0, subtotal - capped);
                            },
                            gstTotal() {
                                return this.rows.reduce((sum, row) => {
                                    const base = Number(row.qty || 0) * Number(row.price || 0);
                                    return sum + ((base * Number(row.gst || 0)) / 100);
                                }, 0);
                            },
                            grandTotal() {
                                return this.taxableAmount() + this.gstTotal();
                            }
                        }">
                            @csrf
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h4 class="text-base font-bold text-slate-900">Add / Edit Support Billing</h4>
                                    <p class="mt-1 text-sm text-slate-500">Saving again updates the existing support quotation or invoice.</p>
                                </div>
                                <x-searchable-select
                                    name="document_type"
                                    id="document_type"
                                    label="Document Type"
                                    :options="collect([
                                        ['id' => 'quotation', 'name' => 'Quotation'],
                                        ['id' => 'invoice', 'name' => 'Invoice'],
                                    ])"
                                    :selected="$requestedBillingType"
                                    empty-label="Select document type..."
                                    placeholder="Search document type..."
                                helper-text="Choose whether this billing record is a quotation or invoice."
                                />
                            </div>

                            @error('product_id')
                                <p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>
                            @enderror
                            @error('name')
                                <p class="mt-3 text-sm font-semibold text-red-600">{{ $message }}</p>
                            @enderror

                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                                <div class="flex items-center justify-between border-b border-slate-200 pb-3">
                                    <h4 class="text-base font-bold text-slate-900">Equipment &amp; Items</h4>
                                    <button type="button" @click="addRow()" class="text-sm font-semibold text-indigo-600 hover:text-indigo-800">+ Add Item</button>
                                </div>

                                <div class="mt-4 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-slate-200">
                                        <thead>
                                            <tr>
                                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-500 w-1/4">Item Name</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-500">Brand/Model</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-500 w-28">Unit</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-500 w-24">Qty</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-500 w-32">Unit Price</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-500 w-32">GST %</th>
                                                <th class="px-4 py-2 text-left text-xs font-bold uppercase tracking-wider text-500 w-32">Total</th>
                                                <th class="px-4 py-2 w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            <template x-for="(row, index) in rows" :key="index">
                                                <tr>
                                                    <td class="px-4 py-2">
                                                        <template x-if="manualProductEntry">
                                                            <input type="text" :name="`name[${index}]`" x-model="row.name" class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Enter product name" required>
                                                        </template>
                                                        <div class="relative" x-data="{ open: false, query: productSearchQuery[index] || productLabel(row), selectedId: row.product_id }" @click.outside="open = false" x-show="!manualProductEntry">
                                                            <button
                                                                type="button"
                                                                class="flex w-full items-center justify-between rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                                                @click="open = !open; query = productSearchQuery[index] || productLabel(row)"
                                                            >
                                                                <span class="min-w-0 truncate" x-text="query || 'Select Product'"></span>
                                                                <svg class="ml-3 h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                    <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 10.94l3.71-3.71a.75.75 0 1 1 1.06 1.06l-4.24 4.25a.75.75 0 0 1-1.06 0L5.21 8.29a.75.75 0 0 1 .02-1.08z" clip-rule="evenodd" />
                                                                </svg>
                                                            </button>
                                                            <div x-show="open" x-transition class="absolute z-50 mt-2 w-[26rem] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl" style="display:none;">
                                                                <div class="border-b border-slate-100 p-3">
                                                                    <input
                                                                        type="search"
                                                                        class="block w-full rounded-xl border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                                        placeholder="Search product by name, brand, model, or unit"
                                                                        x-model="query"
                                                                        @input="productSearchQuery[index] = query"
                                                                        autocomplete="off"
                                                                    >
                                                                </div>
                                                                <div class="max-h-[410px] overflow-y-auto overscroll-contain py-1">
                                                                    <template x-for="product in filteredProducts(query)" :key="product.id">
                                                                        <button
                                                                            type="button"
                                                                            class="flex w-full items-start justify-between gap-3 border-t border-slate-50 px-4 py-2.5 text-left text-sm hover:bg-slate-50"
                                                                            :class="String(selectedId) === String(product.id) ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700'"
                                                                            @click="selectedId = product.id; setProduct(row, product.id, index, $event.target.closest('tr'))"
                                                                        >
                                                                            <span class="min-w-0">
                                                                                <span class="block truncate font-medium" x-text="product.name"></span>
                                                                                <span class="block truncate text-xs text-slate-500" x-text="[product.brand, product.model, product.unit].filter(Boolean).join(' • ')"></span>
                                                                            </span>
                                                                            <span x-show="String(selectedId) === String(product.id)" class="text-xs font-semibold text-indigo-600">Selected</span>
                                                                        </button>
                                                                    </template>
                                                                    <div x-show="filteredProducts(query).length === 0" class="px-4 py-3 text-sm text-slate-500">No matching products found.</div>
                                                                </div>
                                                            </div>
                                                            <input type="hidden" :name="`product_id[${index}]`" x-model="row.product_id" :required="!manualProductEntry" :disabled="manualProductEntry">
                                                        </div>
                                                        <input type="hidden" :name="`name[${index}]`" x-model="row.name" :disabled="manualProductEntry">
                                                        <div class="mt-2">
                                                            <textarea
                                                                :name="`description[${index}]`"
                                                                x-model="row.description"
                                                                maxlength="2000"
                                                                rows="2"
                                                                placeholder="Description (optional)"
                                                                class="w-full resize-y rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            ></textarea>
                                                        </div>
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <input type="text" :value="productBrandModel(row)" readonly placeholder="Hikvision / DS-2CD..." class="w-full rounded-lg border-slate-200 bg-slate-50 text-sm text-slate-600 shadow-sm">
                                                    </td>
                                                    <td class="px-4 py-2">
                                        <select
                                            :name="`unit[${index}]`"
                                            x-model="row.unit"
                                            class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="">Select unit</option>
                                            @foreach($billingUnitOptions as $unitOption)
                                                <option value="{{ $unitOption }}">{{ $unitOption }}</option>
                                            @endforeach
                                            <template x-for="unitOption in unitOptionsFor(row.unit).filter(u => !@js($billingUnitOptions).includes(u))" :key="unitOption">
                                                <option :value="unitOption" x-text="unitOption"></option>
                                            </template>
                                        </select>
                                    </td>
                                                    <td class="px-4 py-2">
                                                        <input
                                                            type="number"
                                                            min="0.01"
                                                            step="any"
                                                            :name="`qty[${index}]`"
                                                            x-model.number="row.qty"
                                                            @input="row.qty = row.qty === '' ? '' : (Number(row.qty) <= 0 ? 0.01 : row.qty)"
                                                            class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                            required
                                                        >
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <input
                                                            type="number"
                                                            min="0"
                                                            step="0.01"
                                                            :name="`price[${index}]`"
                                                            x-model.number="row.price"
                                                            @input="row.price = Math.max(0, row.price || 0)"
                                                            class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        >
                                                    </td>
                                                    <td class="px-4 py-2">
                                                        <select
                                                            :name="`gst[${index}]`"
                                                            x-model.number="row.gst"
                                                            class="w-full min-w-24 rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                        >
                                                            @foreach(($billingGstRates ?? [0, 5, 12, 18, 28]) as $gstRate)
                                                                <option value="{{ (float) $gstRate }}">{{ (int) $gstRate }}%</option>
                                                            @endforeach
                                                            <template x-for="rate in gstOptionsFor(row.gst).filter(r => !@js($billingGstRates ?? [0, 5, 12, 18, 28]).map(Number).includes(Number(r)))" :key="rate">
                                                                <option :value="Number(rate)" x-text="Number(rate).toFixed(0) + '%'"></option>
                                                            </template>
                                                        </select>
                                                    </td>
                                                    <td class="px-4 py-2 text-sm font-semibold text-slate-900" x-text="'Rs. ' + rowTotal(row)"></td>
                                                    <td class="px-4 py-2 text-center">
                                                        <button type="button" @click="removeRow(index)" class="text-red-500 hover:text-red-700" title="Remove item">
                                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                                <path fill-rule="evenodd" d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22Z" clip-rule="evenodd" />
                                                            </svg>
                                                        </button>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-4">
                                <h4 class="border-b border-slate-200 pb-3 text-base font-bold text-slate-900">Pricing Summary</h4>
                                <div class="mt-4 grid gap-4 lg:grid-cols-12">
                                    <div class="lg:col-span-6 space-y-3 text-sm">
                                        <div class="flex justify-between">
                                            <span class="font-medium text-slate-500">Equipment Subtotal:</span>
                                            <span class="font-bold text-slate-900" x-text="'Rs. ' + itemsSubtotal().toFixed(2)"></span>
                                        </div>
                                        <div class="flex items-center justify-between">
                                            <span class="font-medium text-slate-500">Discount:</span>
                                            <div class="w-32">
                                                <input type="number" name="discount" min="0" step="0.01" :value="discount" @input="discount = Number($event.target.value || 0)" class="w-full rounded-lg border-slate-300 text-right text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            </div>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="font-medium text-slate-500">Taxable Amount:</span>
                                            <span class="font-bold text-slate-900" x-text="'Rs. ' + taxableAmount().toFixed(2)"></span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="font-medium text-slate-500">GST (item-wise):</span>
                                            <span class="font-bold text-slate-900" x-text="'Rs. ' + gstTotal().toFixed(2)"></span>
                                        </div>
                                        <div class="flex justify-between border-t pt-3">
                                            <span class="text-base font-bold text-slate-900">Grand Total:</span>
                                            <span class="text-xl font-black text-indigo-600" x-text="'Rs. ' + grandTotal().toFixed(2)"></span>
                                        </div>
                                    </div>
                                    <div class="lg:col-span-3" x-show="documentType === 'invoice'">
                                        <x-searchable-select
                                            name="payment_status"
                                            id="payment_status"
                                            label="Payment Status"
                                            :options="collect([
                                                ['id' => '0', 'name' => 'Unpaid'],
                                                ['id' => '1', 'name' => 'Paid'],
                                            ])"
                                            :selected="(string) old('payment_status', $billingInvoiceDocument?->payment_status ?? 0)"
                                            empty-label="Select status..."
                                            placeholder="Search payment status..."
                                        />
                                    </div>
                                    <div class="lg:col-span-3" x-show="documentType === 'invoice'">
                                        <x-searchable-select
                                            name="payment_method"
                                            id="payment_method"
                                            label="Payment Method"
                                            :options="collect([
                                                ['id' => '0', 'name' => 'Cash'],
                                                ['id' => '1', 'name' => 'Online'],
                                            ])"
                                            :selected="(string) old('payment_method', $billingInvoiceDocument?->payment_method ?? '')"
                                            empty-label="Select method..."
                                            placeholder="Search payment method..."
                                        />
                                    </div>
                                    <div class="flex items-end lg:col-span-3">
                                        <x-primary-button>Save Billing</x-primary-button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    @endif

                    <div class="grid gap-4 xl:grid-cols-12">
                        <div class="border border-slate-200 bg-white p-5 shadow-sm xl:col-span-5">
                            <h4 class="text-base font-bold text-slate-900">Billing Summary</h4>
                            <dl class="mt-4 space-y-3 text-sm">
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">Invoice</dt>
                                    <dd class="font-semibold text-slate-900">{{ $billingSourceLabel ?: 'No linked billing record' }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">Payment Mode</dt>
                                    <dd class="font-semibold text-slate-900">{{ $billingMode }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">Payment Status</dt>
                                    <dd><span class="inline-flex items-center bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-700">{{ $billingStatus }}</span></dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">Quotation Total</dt>
                                    <dd class="font-semibold text-slate-900">Rs. {{ number_format($quotationTotal, 2) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">Pending Amount</dt>
                                    <dd class="font-semibold text-slate-900">Rs. {{ number_format($billingPending, 2) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">Paid Amount</dt>
                                    <dd class="font-semibold text-slate-900">Rs. {{ number_format($billingPaid, 2) }}</dd>
                                </div>
                                <div class="flex items-center justify-between gap-4">
                                    <dt class="text-slate-500">Credit Notes</dt>
                                    <dd class="font-semibold text-slate-900">Rs. {{ number_format($billingCreditNotes, 2) }}</dd>
                                </div>
                            </dl>
                        </div>

                        <div class="border border-slate-200 bg-white p-5 shadow-sm xl:col-span-7">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Invoice #</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Invoice Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Due Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Total Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Paid Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Pending Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white text-sm">
                                        @if($billingInvoices->isNotEmpty())
                                            @foreach($billingInvoices as $invoiceRow)
                                                <tr>
                                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $invoiceRow['number'] }}</td>
                                                    <td class="px-4 py-3 text-slate-700">{{ optional($invoiceRow['date'])->format('d M Y') ?? '-' }}</td>
                                                    <td class="px-4 py-3 text-slate-700">{{ optional($invoiceRow['due_date'])->format('d M Y') ?? '-' }}</td>
                                                    <td class="px-4 py-3 text-slate-700">Rs. {{ number_format($invoiceRow['total_amount'], 2) }}</td>
                                                    <td class="px-4 py-3 text-slate-700">Rs. {{ number_format($invoiceRow['paid_amount'], 2) }}</td>
                                                    <td class="px-4 py-3 text-slate-700">Rs. {{ number_format($invoiceRow['pending_amount'], 2) }}</td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center {{ $invoiceRow['status'] === 'Paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-semibold">
                                                            {{ $invoiceRow['status'] }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr>
                                                <td colspan="7" class="px-4 py-6 text-center text-slate-500">No billing records are linked to this ticket.</td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Total Invoice</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($billingTotal, 2) }}</p>
                        </div>
                        <div class="border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Paid</p>
                            <p class="mt-2 text-2xl font-black text-emerald-600">Rs. {{ number_format($billingPaid, 2) }}</p>
                        </div>
                        <div class="border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Pending</p>
                            <p class="mt-2 text-2xl font-black text-rose-600">Rs. {{ number_format($billingPending, 2) }}</p>
                        </div>
                        <div class="border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Outstanding</p>
                            <p class="mt-2 text-2xl font-black text-orange-600">Rs. {{ number_format($billingOutstanding, 2) }}</p>
                        </div>
                    </div>
                </section>
                @endif

                @if($initialTab === 'history')
                <section data-ticket-section="history" class="space-y-4">
                    <div class="grid gap-4 xl:grid-cols-12">


                        <div class="xl:col-span-8">
                            <div class="border border-slate-200 bg-white p-5 shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <h3 class="text-lg font-bold text-slate-900">Activity Timeline</h3>
                                    <span class="text-sm text-slate-500">Vertical cards, no history removed</span>
                                </div>

                                <div class="mt-5 space-y-4">
                                    @forelse($timelineEvents as $event)
                                        @php
                                            $eventToneClasses = match ($event['tone']) {
                                                'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                                'blue' => 'border-blue-200 bg-blue-50 text-blue-700',
                                                'purple' => 'border-purple-200 bg-purple-50 text-purple-700',
                                                'amber' => 'border-amber-200 bg-amber-50 text-amber-700',
                                                default => 'border-slate-200 bg-slate-50 text-slate-700',
                                            };
                                        @endphp
                                        <div class="relative pl-10">
                                            <div class="absolute left-3 top-6 h-full w-px bg-slate-200"></div>
                                            <div class="absolute left-0 top-2 flex h-7 w-7 items-center justify-center border {{ $eventToneClasses }}">
                                                <span class="h-2.5 w-2.5 bg-current"></span>
                                            </div>
                                            <div class="border border-slate-200 bg-slate-50 p-4">
                                                <div class="flex flex-wrap items-center justify-between gap-2">
                                                    <div class="text-sm font-semibold text-slate-900">{{ $event['title'] }}</div>
                                                    <div class="text-xs text-slate-500">{{ optional($event['date'])->format('d M Y, g:i A') ?? '-' }}</div>
                                                </div>
                                                <div class="mt-2 text-sm leading-6 text-slate-700">{{ $event['description'] }}</div>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                            No activity history available yet.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                @if($initialTab === 'notes')
                <section data-ticket-section="notes" class="grid gap-5 xl:grid-cols-12">
                    <div class="border border-slate-200 bg-white p-5 shadow-sm xl:col-span-7">
                        <h3 class="text-lg font-bold text-slate-900">Service Notes</h3>
                        <div class="mt-4 whitespace-pre-line bg-slate-50 p-4 text-sm leading-7 text-slate-700">
                            {{ filled($ticket->service_notes) ? $ticket->service_notes : 'No service notes added yet.' }}
                        </div>
                    </div>

                    <div class="space-y-5 xl:col-span-5">
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <h4 class="text-base font-bold text-slate-900">Internal Notes Snapshot</h4>
                            <div class="mt-4 space-y-3">
                                @if($ticket->assignmentHistories->whereNotNull('note')->isNotEmpty() || $ticket->repeatHistories->whereNotNull('note')->isNotEmpty())
                                    @foreach($ticket->assignmentHistories->whereNotNull('note') as $assignmentHistory)
                                        <div class="border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                                            <div class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Assignment Note</div>
                                            <div class="mt-2 whitespace-pre-line">{{ $assignmentHistory->note }}</div>
                                        </div>
                                    @endforeach
                                    @foreach($ticket->repeatHistories->whereNotNull('note') as $repeatHistory)
                                        <div class="border border-slate-100 bg-slate-50 p-4 text-sm text-slate-700">
                                            <div class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400">Repeat Note</div>
                                            <div class="mt-2 whitespace-pre-line">{{ $repeatHistory->note }}</div>
                                        </div>
                                    @endforeach
                                @else
                                    <div class="border border-dashed border-slate-200 bg-slate-50 p-5 text-sm text-slate-500">No internal notes captured yet.</div>
                                @endif
                            </div>
                        </div>

                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <h4 class="text-base font-bold text-slate-900">Add Note</h4>
                            <p class="mt-2 text-sm text-slate-500">Use the existing reply workflow to add internal communication.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="{{ route('tickets.show', [$ticket, 'tab' => 'conversation']) }}" class="border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Add Reply</a>
                                <a href="{{ route('tickets.edit', $ticket) }}" class="border border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 transition hover:bg-slate-50">Edit Ticket</a>
                            </div>
                        </div>
                    </div>
                </section>
                @endif

                @if($initialTab === 'invoices')
                <section data-ticket-section="invoices" class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-900">Invoices</h3>
                        <span class="text-sm text-slate-500">Linked billing snapshot</span>
                    </div>
                    <div class="border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Invoice #</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Invoice Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Due Date</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Total Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Paid Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Pending Amount</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white text-sm">
                                    @if($billingInvoices->isNotEmpty())
                                        @foreach($billingInvoices as $invoiceRow)
                                            @php
                                                $invoiceLink = $invoiceRow['link'];
                                                $invoiceModel = $invoiceRow['invoice'];
                                                $invoiceDownloadUrl = ($ticket && $invoiceLink)
                                                    ? route('tickets.invoices.download', ['ticket' => $ticket, 'ticketInvoice' => $invoiceLink])
                                                    : null;
                                            @endphp
                                            <tr>
                                                <td class="px-4 py-3 font-medium text-slate-900">{{ $invoiceRow['number'] }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ optional($invoiceRow['date'])->format('d M Y') ?? '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ optional($invoiceRow['due_date'])->format('d M Y') ?? '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">Rs. {{ number_format($invoiceRow['total_amount'], 2) }}</td>
                                                <td class="px-4 py-3 text-slate-700">Rs. {{ number_format($invoiceRow['paid_amount'], 2) }}</td>
                                                <td class="px-4 py-3 text-slate-700">Rs. {{ number_format($invoiceRow['pending_amount'], 2) }}</td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center {{ $invoiceRow['status'] === 'Paid' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-semibold">
                                                        {{ $invoiceRow['status'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-wrap gap-2">
                                                        <a href="{{ route('tickets.invoices.view', [$ticket, $invoiceLink]) }}" class="inline-flex items-center  border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                                        <a href="{{ route('tickets.invoices.print', [$ticket, $invoiceLink]) }}" target="_blank" rel="noopener" class="inline-flex items-center  border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Print</a>
                                                        @if($invoiceDownloadUrl)
                                                            <a href="{{ $invoiceDownloadUrl }}" class="inline-flex items-center  border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Download PDF</a>
                                                        @else
                                                            <span class="inline-flex items-center  border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-400 cursor-not-allowed">Download PDF</span>
                                                        @endif
                                                        <a href="{{ route('tickets.show', [$ticket, 'tab' => 'billing', 'document_type' => 'invoice']) }}" class="inline-flex items-center  border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Edit</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    @else
                                        <tr>
                                            <td colspan="8" class="px-4 py-6 text-center text-slate-500">No invoice records are linked to this ticket yet.</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                @endif

                @if($initialTab === 'quotations')
                <section data-ticket-section="quotations" class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-900">Quotations</h3>
                        <span class="text-sm text-slate-500">Linked quotation records</span>
                    </div>
                    <div class="border border-slate-200 bg-white p-5 shadow-sm">
                        @if($billingQuotes->isNotEmpty())
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-slate-200">
                                    <thead class="bg-slate-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Quotation #</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Quotation Date</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Total Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Pending Amount</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Status</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-white text-slate-500">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 bg-white text-sm">
                                        @foreach($billingQuotes as $quoteRow)
                                            @php
                                                $quoteLink = $quoteRow['link'];
                                            @endphp
                                            <tr>
                                                <td class="px-4 py-3 font-medium text-slate-900">{{ $quoteRow['number'] }}</td>
                                                <td class="px-4 py-3 text-slate-700">{{ optional($quoteRow['date'])->format('d M Y') ?? '-' }}</td>
                                                <td class="px-4 py-3 text-slate-700">Rs. {{ number_format($quoteRow['total_amount'], 2) }}</td>
                                                <td class="px-4 py-3 text-slate-700">Rs. {{ number_format($quoteRow['pending_amount'], 2) }}</td>
                                                <td class="px-4 py-3">
                                                    <span class="inline-flex items-center bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">
                                                        {{ $quoteRow['status'] }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3">
                                                    <div class="flex flex-wrap gap-2">
                                                        <a href="{{ route('tickets.quotations.view', [$ticket, $quoteLink]) }}" class="inline-flex items-center  border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">View</a>
                                                        <a href="{{ route('tickets.quotations.print', [$ticket, $quoteLink]) }}" target="_blank" rel="noopener" class="inline-flex items-center border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Print</a>
                                                        <a href="{{ route('tickets.quotations.download', [$ticket, $quoteLink]) }}" class="inline-flex items-center border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Download PDF</a>
                                                        <a href="{{ route('tickets.show', [$ticket, 'tab' => 'billing', 'document_type' => 'quotation']) }}" class="inline-flex items-center border border-indigo-200 px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50">Edit</a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                                No quotation records are linked to this ticket yet.
                            </div>
                        @endif
                    </div>
                </section>
                @endif

                @if($initialTab === 'payments')
                <section data-ticket-section="payments" class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <h3 class="text-lg font-bold text-slate-900">Payments</h3>
                        <span class="text-sm text-slate-500">Current payment snapshot</span>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Payment Mode</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">{{ $billingMode }}</p>
                        </div>
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Payment Status</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">{{ $billingStatus }}</p>
                        </div>
                        <div class="border border-slate-200 bg-white p-5 shadow-sm">
                            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-slate-400">Paid Amount</p>
                            <p class="mt-2 text-2xl font-black text-slate-950">Rs. {{ number_format($billingPaid, 2) }}</p>
                        </div>
                    </div>
                </section>
                @endif

                @if($initialTab === 'pending')
                <section data-ticket-section="pending" class="space-y-4">
                    <div class="border border-amber-200 bg-amber-50 p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-amber-700">Pending Amount</p>
                        <p class="mt-3 text-4xl font-black text-amber-950">Rs. {{ number_format($billingPending, 2) }}</p>
                        <p class="mt-3 text-sm text-amber-800">Calculated from unpaid invoices and linked quotations.</p>
                    </div>
                </section>
                @endif

                @if($initialTab === 'paid')
                <section data-ticket-section="paid" class="space-y-4">
                    <div class="border border-emerald-200 bg-emerald-50 p-6 shadow-sm">
                        <p class="text-xs font-bold uppercase tracking-[0.24em] text-emerald-700">Paid Amount</p>
                        <p class="mt-3 text-4xl font-black text-emerald-950">Rs. {{ number_format($billingPaid, 2) }}</p>
                        <p class="mt-3 text-sm text-emerald-800">Based on linked invoices marked as paid.</p>
                    </div>
                </section>
                @endif

                @if($initialTab === 'credit-notes')
                <section data-ticket-section="credit-notes" class="space-y-4">
                    <div class="border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-500">
                        No credit note records are linked to this ticket yet.
                    </div>
                </section>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
