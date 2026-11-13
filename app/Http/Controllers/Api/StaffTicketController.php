<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\Company;
use App\Models\BoqProduct;
use App\Models\TicketDocument;
use App\Models\TicketInvoice;
use App\Models\TicketQuote;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use App\Support\QuotationTaxCalculator;
use App\Services\MediaUploadService;
use App\Services\TicketDocumentLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class StaffTicketController extends Controller
{
    public function taskMasters(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');

        return response()->json([
            'task_masters' => $this->formatTaskMasterOptions($user),
            'task_status_options' => Ticket::taskStatusOptions(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user, 403, 'Unauthenticated.');

        $validated = $this->validateJson($request, [
            'period' => ['nullable', 'string', Rule::in(['today', 'week', 'month'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'visit_date' => ['nullable', 'date'],
            'status' => ['nullable'],
            'priority' => ['nullable'],
            'type' => ['nullable'],
            'assigned_to_me' => ['nullable', 'boolean'],
            'created_by_me' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:191'],
            'sort' => ['nullable', 'string', Rule::in(['latest', 'oldest'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $statuses = $this->normalizeFilterValues($request->input('status'));
        $priorities = $this->normalizeFilterValues($request->input('priority'));
        $types = $this->normalizeFilterValues($request->input('type'));
        $period = $validated['period'] ?? null;
        $startDate = isset($validated['start_date']) ? Carbon::parse($validated['start_date'])->toDateString() : null;
        $endDate = isset($validated['end_date']) ? Carbon::parse($validated['end_date'])->toDateString() : null;
        $visitDate = isset($validated['visit_date']) ? Carbon::parse($validated['visit_date'])->toDateString() : null;
        $search = isset($validated['search']) ? trim((string) $validated['search']) : null;
        $assignedToMe = $request->boolean('assigned_to_me');
        $createdByMe = $request->boolean('created_by_me');
        $sort = $validated['sort'] ?? 'latest';
        $perPage = (int) ($validated['per_page'] ?? 10);
        $isCompanyAdmin = $user->isCompanyAdmin();

        $baseQuery = Ticket::query()
            ->where('company_id', $user->company_id)
            ->when(! $isCompanyAdmin, function ($query) use ($user, $assignedToMe, $createdByMe) {
                $query->where('status', '!=', 'closed')
                    ->where(function ($builder) use ($user) {
                        $builder->where('user_id', $user->id)
                            ->orWhere('assigned_to', $user->id);

                        if (\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments')) {
                            $builder->orWhereHas('assignedUsers', fn ($q) => $q->where('users.id', $user->id));
                        }
                    })
                    ->when($assignedToMe, function ($builder) use ($user) {
                        $builder->where(function ($q) use ($user) {
                            $q->where('assigned_to', $user->id);
                            if (\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments')) {
                                $q->orWhereHas('assignedUsers', fn ($sub) => $sub->where('users.id', $user->id));
                            }
                        });
                    })
                    ->when($createdByMe, fn ($builder) => $builder->where('user_id', $user->id));
            })
            ->when($isCompanyAdmin, function ($query) use ($assignedToMe, $createdByMe, $user) {
                $query
                    ->when($assignedToMe, function ($builder) use ($user) {
                        $builder->where(function ($q) use ($user) {
                            $q->where('assigned_to', $user->id);
                            if (\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments')) {
                                $q->orWhereHas('assignedUsers', fn ($sub) => $sub->where('users.id', $user->id));
                            }
                        });
                    })
                    ->when($createdByMe, fn ($builder) => $builder->where('user_id', $user->id));
            })
            ->when($priorities !== [], fn ($query) => $query->whereIn('priority', $priorities))
            ->when($types !== [], fn ($query) => $query->whereIn('type', $types))
            ->when($visitDate, fn ($query) => $query->whereDate('visit_date', $visitDate))
            ->when($startDate || $endDate, function ($query) use ($startDate, $endDate) {
                if ($startDate) {
                    $query->whereDate('visit_date', '>=', $startDate);
                }

                if ($endDate) {
                    $query->whereDate('visit_date', '<=', $endDate);
                }
            }, function ($query) use ($period) {
                $now = now();

                if ($period === 'today') {
                    $query->whereDate('created_at', $now->toDateString());

                    return;
                }

                if ($period === 'week') {
                    $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]);

                    return;
                }

                if ($period === 'month') {
                    $query->whereBetween('created_at', [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()]);
                }
            })
            ->when($search, function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('ticket_number', 'like', '%' . $search . '%')
                        ->orWhere('subject', 'like', '%' . $search . '%')
                        ->orWhere('description', 'like', '%' . $search . '%')
                        ->orWhere('customer_name', 'like', '%' . $search . '%')
                        ->orWhere('customer_email', 'like', '%' . $search . '%')
                        ->orWhere('customer_contact_number', 'like', '%' . $search . '%');
                });
            });

        $statusCounts = $this->buildStatusCounts(clone $baseQuery);

        $tickets = (clone $baseQuery)
            ->when($statuses !== [], fn ($query) => $query->whereIn('status', $statuses))
            ->with([
                'user:id,name',
                'assignedUser:id,name',
                'referencedUser:id,name',
                'project:id,name,reference_no,company_id',
                'amc:id,support_ticket_id,contract_no',
                'generatedFromAmc:id,contract_no',
                'parentTicket:id,ticket_number',
                'previousStaff:id,name',
                'invoices' => function ($query) {
                    $query->select(['id', 'ticket_id', 'payment_status', 'payment_method', 'created_at'])
                        ->orderByDesc('id');
                },
            ])
            ->withCount('replies')
            ->when($sort === 'oldest', fn ($query) => $query->oldest(), fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        return response()->json([
            'tickets' => collect($tickets->items())->map(fn (Ticket $ticket) => $this->formatTicket($ticket))->values(),
            'meta' => [
                'total' => $tickets->total(),
                'status_counts' => $statusCounts,
                'pagination' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'from' => $tickets->firstItem(),
                    'to' => $tickets->lastItem(),
                    'has_more_pages' => $tickets->hasMorePages(),
                ],
                'filters' => [
                    'period' => $period,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'visit_date' => $visitDate,
                    'status' => $statuses,
                    'priority' => $priorities,
                    'type' => $types,
                    'assigned_to_me' => $assignedToMe,
                    'created_by_me' => $createdByMe,
                    'search' => $search,
                    'sort' => $sort,
                    'page' => $tickets->currentPage(),
                    'per_page' => $tickets->perPage(),
                ],
            ],
        ]);
    }

    public function acceptJob(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to update this ticket.');

        $validated = $this->validateJson($request, [
            'accept_job' => ['required', Rule::in([0, 1, '0', '1'])],
        ], [
            'accept_job.required' => 'Please choose whether to accept or reject the job.',
            'accept_job.in' => 'Accept job must be either 1 or 0.',
        ], [
            'accept_job' => 'accept job',
        ]);

        $ticket->update([
            'accept_job' => (int) $validated['accept_job'] === 1,
        ]);

        $ticket = $ticket->fresh([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'project:id,name,reference_no,company_id',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'parentTicket:id,ticket_number',
            'previousStaff:id,name',
            'replies.user:id,name',
            'repeatHistories.reopenedBy:id,name',
            'repeatHistories.previousAssignee:id,name',
            'repeatHistories.assignee:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'success' => true,
            'message' => 'Job acceptance updated successfully.',
            'ticket_id' => $ticket->id,
            'accept_job' => (int) $ticket->accept_job,
            'ticket' => $this->formatTicketDetail($ticket, $request->getSchemeAndHttpHost()),
        ]);
    }

    public function show(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to access this ticket.');

        $ticket = $ticket->fresh();
        $ticket->load([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'project:id,name,reference_no,company_id',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'parentTicket:id,ticket_number',
            'previousStaff:id,name',
            'invoices' => function ($query) {
                $query->select([
                    'id',
                    'ticket_id',
                    'company_id',
                    'staff_id',
                    'invoice_id',
                    'invoice_number',
                    'invoice_subtotal',
                    'invoice_discount',
                    'invoice_gst_amount',
                    'invoice_total',
                    'total_amount',
                    'payment_status',
                    'payment_method',
                    'received_by_admin',
                    'received_by_admin_at',
                    'received_by_admin_by',
                    'created_by',
                    'created_at',
                ])
                    ->with([
                        'invoice:id,ticket_id,company_id,document_type,invoice_or_quote,document_number,items,subtotal,discount,gst_amount,total,payment_status,payment_method,created_at',
                    ])
                    ->orderByDesc('id');
            },
            'replies.user:id,name',
            'repeatHistories.reopenedBy:id,name',
            'repeatHistories.previousAssignee:id,name',
            'repeatHistories.assignee:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'ticket' => $this->formatTicketDetail($ticket, $request->getSchemeAndHttpHost()),
            'meta' => [
                'status_options' => $this->formatStatusOptions(),
                'task_master_options' => $this->formatTaskMasterOptions($user),
                'task_status_options' => Ticket::taskStatusOptions(),
            ],
        ]);
    }

    public function storeTask(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to update this ticket.');

        $validated = $this->validateJson($request, [
            'task' => ['required', 'string', 'max:191'],
            'status' => ['nullable', 'string', Rule::in(array_keys(Ticket::taskStatusOptions()))],
        ], [
            'task.required' => 'Please enter a task name.',
            'task.string' => 'Task name must be a valid text value.',
            'task.max' => 'Task name must not exceed 191 characters.',
            'status.string' => 'Task status must be a valid text value.',
            'status.in' => 'Task status must be either pending or done.',
        ], [
            'task' => 'task name',
            'status' => 'task status',
        ]);

        $taskItems = $this->normalizeTaskValues([
            ...Ticket::normalizeTaskItems($ticket->tasks ?? []),
            [
                'name' => $validated['task'],
                'status' => $validated['status'] ?? 'pending',
            ],
        ]);
        $taskItems = $this->syncManualTaskMasters((int) $user->company_id, $taskItems);

        $ticket->update([
            'tasks' => $taskItems === [] ? null : $taskItems,
        ]);

        $ticket->load([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'parentTicket:id,ticket_number',
            'previousStaff:id,name',
            'replies.user:id,name',
            'repeatHistories.reopenedBy:id,name',
            'repeatHistories.previousAssignee:id,name',
            'repeatHistories.assignee:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'message' => 'Task added successfully.',
            'tasks' => $taskItems,
            'ticket' => $this->formatTicketDetail($ticket, $request->getSchemeAndHttpHost()),
            'task_masters' => $this->formatTaskMasterOptions($user),
            'task_status_options' => Ticket::taskStatusOptions(),
        ], 201);
    }

    public function updateServiceReport(Request $request, Ticket $ticket, MediaUploadService $mediaUploadService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to update this ticket.');

        $validated = $this->validateJson($request, [
            'service_notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', 'string', Rule::in($this->allowedStatusInputs())],
            'visit_date' => ['nullable', 'date'],
            'tasks' => ['nullable', 'array'],
            'tasks.*.name' => ['required', 'string', 'max:191'],
            'tasks.*.status' => ['nullable', 'string', Rule::in(array_keys(Ticket::taskStatusOptions()))],
            'current_condition_photos' => ['nullable', 'array', 'max:3'],
            'current_condition_photos.*' => ['file', 'image', 'max:5120'],
            'after_repair_photos' => ['nullable', 'array', 'max:3'],
            'after_repair_photos.*' => ['file', 'image', 'max:5120'],
        ], [
            'service_notes.string' => 'Service notes must be plain text.',
            'service_notes.max' => 'Service notes must not exceed 5000 characters.',
            'status.string' => 'Status must be a valid text value.',
            'status.in' => 'Service status is invalid.',
            'visit_date.date' => 'Please provide a valid visit date.',
            'tasks.array' => 'Tasks must be sent as a list.',
            'tasks.*.name.required' => 'Please enter a task name for each task row.',
            'tasks.*.name.string' => 'Each task name must be plain text.',
            'tasks.*.name.max' => 'Each task name must not exceed 191 characters.',
            'tasks.*.status.string' => 'Each task status must be plain text.',
            'tasks.*.status.in' => 'Each task status must be pending or done.',
            'current_condition_photos.array' => 'Current condition photos must be sent as a list.',
            'current_condition_photos.max' => 'You can upload up to 3 current condition photos.',
            'current_condition_photos.*.file' => 'Each current condition photo must be a valid file.',
            'current_condition_photos.*.image' => 'Each current condition photo must be an image.',
            'current_condition_photos.*.max' => 'Each current condition photo must be smaller than 5 MB.',
            'after_repair_photos.array' => 'After repair photos must be sent as a list.',
            'after_repair_photos.max' => 'You can upload up to 3 after repair photos.',
            'after_repair_photos.*.file' => 'Each after repair photo must be a valid file.',
            'after_repair_photos.*.image' => 'Each after repair photo must be an image.',
            'after_repair_photos.*.max' => 'Each after repair photo must be smaller than 5 MB.',
        ], [
            'service_notes' => 'service notes',
            'status' => 'status',
            'visit_date' => 'visit date',
            'tasks' => 'tasks',
            'tasks.*.name' => 'task name',
            'tasks.*.status' => 'task status',
            'current_condition_photos' => 'current condition photos',
            'after_repair_photos' => 'after repair photos',
        ]);

        $currentConditionPhotos = $this->storeTicketPhotos(
            $ticket,
            $request->file('current_condition_photos', []),
            'current-condition',
            $mediaUploadService
        );
        $afterRepairPhotos = $this->storeTicketPhotos(
            $ticket,
            $request->file('after_repair_photos', []),
            'after-repair',
            $mediaUploadService
        );

        $updatePayload = [];

        if ($request->has('service_notes')) {
            $updatePayload['service_notes'] = $validated['service_notes'] ?? null;
        }

        if ($request->has('status') && filled($validated['status'] ?? null)) {
            $normalizedStatus = $this->normalizeIncomingStatus((string) $validated['status']);
            $updatePayload['status'] = $normalizedStatus;

            if (
                in_array($normalizedStatus, ['answered', 'resolved', 'closed', 'hold', 'reschedule'], true)
                && $ticket->start_time !== null
            ) {
                $updatePayload['end_time'] = now();
            }
        }

        if ($request->has('visit_date')) {
            $updatePayload['visit_date'] = $validated['visit_date'] ?? null;
        }

        if ($request->has('tasks')) {
            $tasks = $this->normalizeTaskValues($validated['tasks'] ?? []);
            $tasks = $this->syncManualTaskMasters((int) $user->company_id, $tasks);
            $updatePayload['tasks'] = $tasks === [] ? null : $tasks;
        }

        if ($currentConditionPhotos !== null) {
            $updatePayload['current_condition_photos'] = $currentConditionPhotos;
        }

        if ($afterRepairPhotos !== null) {
            $updatePayload['after_repair_photos'] = $afterRepairPhotos;
        }

        if ($updatePayload !== []) {
            $ticket->update($updatePayload);
        }

        $ticket->load([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'parentTicket:id,ticket_number',
            'previousStaff:id,name',
            'replies.user:id,name',
            'repeatHistories.reopenedBy:id,name',
            'repeatHistories.previousAssignee:id,name',
            'repeatHistories.assignee:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'message' => 'Ticket service report updated successfully.',
            'ticket' => $this->formatTicketDetail($ticket, $request->getSchemeAndHttpHost()),
            'meta' => [
                'status_options' => $this->formatStatusOptions(),
                'task_status_options' => Ticket::taskStatusOptions(),
            ],
            'task_masters' => $this->formatTaskMasterOptions($user),
        ]);
    }

    public function updateTaskReport(Request $request, Ticket $ticket, MediaUploadService $mediaUploadService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to update this ticket.');

        $validated = $this->validateJson($request, [
            'work_summary' => ['nullable', 'string', 'max:5000'],
            'complate_task_list' => ['nullable'],
            'complete_task_list' => ['nullable'],
            'customer_signature' => ['nullable', 'file', 'image', 'max:5120'],
        ], [
            'work_summary.string' => 'Work summary must be plain text.',
            'work_summary.max' => 'Work summary must not exceed 5000 characters.',
            'customer_signature.file' => 'Customer signature must be a valid file.',
            'customer_signature.image' => 'Customer signature must be an image.',
            'customer_signature.max' => 'Customer signature must be smaller than 5 MB.',
        ], [
            'work_summary' => 'work summary',
            'complate_task_list' => 'completed task list',
            'complete_task_list' => 'completed task list',
            'customer_signature' => 'customer signature',
        ]);

        $taskListInput = $request->input('complate_task_list', $request->input('complete_task_list'));
        $taskList = $this->normalizeTaskListValues($taskListInput);
        $companyTaskMasters = $this->formatTaskMasterOptions($user);
        $allowedTaskMasters = collect($companyTaskMasters)
            ->pluck('name')
            ->map(fn ($name) => Str::lower(Str::squish((string) $name)))
            ->values()
            ->all();

        if ($companyTaskMasters === []) {
            throw new HttpResponseException(response()->json([
                'message' => 'No task masters are configured for this company.',
                'errors' => [
                    'complate_task_list' => ['No task masters are configured for this company.'],
                ],
            ], 422));
        }

        $invalidTasks = collect($taskList)
            ->filter(fn (string $task) => ! in_array(Str::lower(Str::squish($task)), $allowedTaskMasters, true))
            ->values()
            ->all();

        if ($invalidTasks !== []) {
            $invalidTasksMessage = 'These tasks are not configured for this company: ' . implode(', ', $invalidTasks) . '.';

            throw new HttpResponseException(response()->json([
                'message' => $invalidTasksMessage,
                'errors' => [
                    'complate_task_list' => [$invalidTasksMessage],
                ],
            ], 422));
        }

        $updatedTasks = $this->syncTaskStatusesFromCompletedList(
            $ticket->company_id,
            $ticket->tasks ?? [],
            $taskList
        );

        $signaturePath = null;

        if ($request->hasFile('customer_signature')) {
            if (filled($ticket->customer_signature)) {
                $mediaUploadService->delete((string) $ticket->customer_signature, 'public', (int) $ticket->company_id);
            }

            $signaturePath = $mediaUploadService->store(
                $request->file('customer_signature'),
                'company_' . $ticket->company_id . '/tickets/signatures',
                'public'
            );
        }

        $updatePayload = [];

        if ($request->has('work_summary')) {
            $updatePayload['work_summary'] = $validated['work_summary'] ?? null;
        }

        if ($request->has('complate_task_list') || $request->has('complete_task_list')) {
            $updatePayload['complate_task_list'] = $taskList === [] ? null : $taskList;
            $updatePayload['tasks'] = $updatedTasks === [] ? null : $updatedTasks;
        }

        if ($signaturePath !== null) {
            $updatePayload['customer_signature'] = $signaturePath;
        }

        if ($updatePayload !== []) {
            $ticket->update($updatePayload);
        }

        $ticket->load([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'replies.user:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'message' => 'Ticket task report updated successfully.',
            'ticket' => $this->formatTicketDetail($ticket, $request->getSchemeAndHttpHost()),
            'task_masters' => $companyTaskMasters,
            'company_task_statuses' => $this->formatCompanyTaskStatuses($user->company_id, $taskList),
            'task_status_options' => Ticket::taskStatusOptions(),
        ]);
    }

    public function quoteAddContext(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to access this ticket.');
        abort_unless($user->company?->enable_boq ?? true, 403, 'BOQ feature is disabled for this company.');

        $ticket->load(['company:id,name,enable_boq']);

        return response()->json([
            'quote_id' => $ticket->quote_id,
            'quote_items' => $this->formatQuoteItems($ticket->quote_items ?? [], $user->company_id),
            'quote_summary' => $this->buildQuoteSummary($ticket->quote_items ?? [], (float) ($ticket->quote_discount ?? 0)),
        ]);
    }

    public function storeQuoteAdd(Request $request, Ticket $ticket): JsonResponse
    {

        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to update this ticket.');
        abort_unless($user->company?->enable_boq ?? true, 403, 'BOQ feature is disabled for this company.');

        try {
            $validated = $this->validateJson($request, [
                'product_id' => ['nullable'],
                'qty' => ['nullable'],
                'price' => ['nullable'],
                'note' => ['nullable'],
                'discount' => ['nullable', 'numeric', 'min:0'],
                'invoice_or_quote' => ['required', Rule::in([1, 2])],
                'payment_status' => ['nullable', Rule::in([0, 1, 2, 3])],
                'payment_method' => ['nullable', Rule::in([0, 1, 2])],
            ], [
                'invoice_or_quote.required' => 'Please select invoice or quotation.',
                'invoice_or_quote.in' => 'Invoice or quote must be either 1 or 2.',
                'payment_status.in' => 'Payment status must be either 0, 1, 2, or 3.',
                'payment_method.in' => 'Payment method must be either 0, 1, or 2.',
            ], [
                'product_id' => 'service',
                'qty' => 'quantity',
                'price' => 'price',
                'note' => 'note',
                'discount' => 'discount',
                'invoice_or_quote' => 'invoice or quote',
                'payment_status' => 'payment status',
                'payment_method' => 'payment method',
            ]);

        $invoiceOrQuote = (int) ($validated['invoice_or_quote'] ?? 2);
        $rawPaymentStatus = array_key_exists('payment_status', $validated)
            ? (int) $validated['payment_status']
            : null;
        $isAmcVisitInvoice = $rawPaymentStatus === 3;
        if ($isAmcVisitInvoice) {
            $invoiceOrQuote = 1;
        }

        $paymentStatus = $rawPaymentStatus !== null
            ? ($rawPaymentStatus === 2 || $rawPaymentStatus === 3 ? 0 : $rawPaymentStatus)
            : null;
        $paymentMethod = array_key_exists('payment_method', $validated)
            ? ((int) $validated['payment_method'] === 2 ? 0 : (int) $validated['payment_method'])
            : null;

        if ($invoiceOrQuote === 1 && $rawPaymentStatus === null) {
            throw $this->validationErrorResponse([
                'payment_status' => ['Payment status is required when creating an invoice.'],
            ]);
        }

        if ($invoiceOrQuote === 1 && $paymentStatus === 1 && $paymentMethod === null) {
            throw $this->validationErrorResponse([
                'payment_method' => ['Payment method is required when invoice payment status is paid.'],
            ]);
        }

        $discount = round((float) ($validated['discount'] ?? 0), 2);
        $quoteId = filled($ticket->quote_id)
            ? (string) $ticket->quote_id
            : $this->generateQuoteId($ticket);

        $productIdsInput = $this->normalizeListInput($request->input('product_id'));
        $quantitiesInput = $this->normalizeListInput($request->input('qty'));
        $pricesInput = $this->normalizeListInput($request->input('price'));
        $notesInput = $this->normalizeListInput($request->input('note'));

        $productIds = $productIdsInput
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->values()
            ->all();
        $quantities = $quantitiesInput
            ->map(fn ($value) => max(1, (int) $value))
            ->values()
            ->all();
        $prices = $pricesInput
            ->map(fn ($value) => filled($value) ? round((float) $value, 2) : null)
            ->values()
            ->all();
        $notes = $notesInput
            ->map(fn ($value) => filled($value) ? Str::squish((string) $value) : null)
            ->values()
            ->all();

        $isAmcSupportTicket = filled($ticket->generated_from_amc_id) || $ticket->source_type === 'amc';
        $hasNoLineItems = $productIds === [] && $quantities === [];

        $isZeroAmountInvoice = $rawPaymentStatus === 3
            && $invoiceOrQuote === 1
            && $hasNoLineItems;

        if ($rawPaymentStatus === 3 && ! $isAmcSupportTicket) {
            throw new HttpResponseException(response()->json([
                'message' => 'Zero amount invoices are allowed only for AMC support tickets.',
            ], 422));
        }

        $isAmcVisitInvoice = $rawPaymentStatus === 3 && ! $isZeroAmountInvoice;
        if ($isAmcVisitInvoice || $isZeroAmountInvoice) {
            $invoiceOrQuote = 1;
        }

        $amcVisit = null;
        $quoteItems = [];
        $summary = ['subtotal' => 0, 'discount' => 0, 'gst_amount' => 0, 'total' => 0];

        if ($isZeroAmountInvoice) {
            $summary = $this->buildQuoteSummary([], 0);
        } elseif ($isAmcVisitInvoice) {
            $amcVisit = $this->resolveAmcVisitForTicket($ticket);

            if (! $amcVisit) {
                throw $this->validationErrorResponse([
                    'payment_status' => ['Unable to resolve the AMC visit for this invoice.'],
                ]);
            }

            $amc = $amcVisit->amc()->with(['visits.ticketInvoice'])->first();

            if (! $amc) {
                throw $this->validationErrorResponse([
                    'payment_status' => ['Unable to resolve the AMC contract for this invoice.'],
                ]);
            }

            $quoteItems = $this->buildAmcVisitInvoiceItems($amc, $amcVisit);
            $summary = $this->buildQuoteSummary($quoteItems, 0);
        } else {
            if ($productIds === []) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Please select at least one service.',
                    'errors' => [
                        'product_id' => ['Please select at least one service.'],
                    ],
                ], 422));
            }

            if (count($productIds) !== count($quantities)) {
                throw new HttpResponseException(response()->json([
                    'message' => 'Service and quantity counts do not match.',
                    'errors' => [
                        'qty' => ['Service and quantity counts must match.'],
                    ],
                ], 422));
            }

            $uniqueProductIds = collect($productIds)->unique()->values()->all();

            $services = BoqProduct::query()
                ->where('company_id', $user->company_id)
                ->where('is_active', true)
                ->whereIn('id', $uniqueProductIds)
                ->get(['id', 'company_id', 'category_id', 'name', 'unit', 'price', 'gst', 'description']);

            if ($services->count() !== count($uniqueProductIds)) {
                $missingIds = collect($uniqueProductIds)
                    ->diff($services->pluck('id')->map(fn ($id) => (int) $id)->all())
                    ->values()
                    ->all();

                throw new HttpResponseException(response()->json([
                    'message' => 'Some selected services are not available for this company.',
                    'errors' => [
                        'product_id' => ['Invalid service ids: ' . implode(', ', $missingIds) . '.'],
                    ],
                ], 422));
            }

            $serviceById = $services->keyBy(fn (BoqProduct $service) => (int) $service->id);

            $quoteItems = collect($productIds)
                ->values()
                ->map(function (int $productId, int $index) use ($serviceById, $quantities, $prices, $notes) {
                    /** @var BoqProduct $service */
                    $service = $serviceById->get($productId);
                    $qty = max(1, (int) ($quantities[$index] ?? 1));
                    $basePrice = round((float) ($service->price ?? 0), 2);
                    $price = array_key_exists($index, $prices) && $prices[$index] !== null
                        ? round((float) $prices[$index], 2)
                        : $basePrice;
                    $baseTotal = round($qty * $price, 2);
                    $gstRate = round((float) ($service->gst ?? 0), 2);
                    $gstAmount = round(($baseTotal * $gstRate) / 100, 2);

                    return [
                        'product_id' => (int) $service->id,
                        'name' => $service->name,
                        'qty' => $qty,
                        'price' => $price,
                        'base_total' => $baseTotal,
                        'unit' => $service->unit,
                        'gst' => $gstRate,
                        'gst_amount' => $gstAmount,
                        'total' => round($baseTotal + $gstAmount, 2),
                        'note' => $notes[$index] ?? null,
                        'description' => $service->description,
                    ];
                })
                ->values()
                ->all();

            $summary = $this->buildQuoteSummary($quoteItems, $discount);
        }

        if ($invoiceOrQuote === 1) {
            $invoice = DB::transaction(function () use ($ticket, $user, $quoteItems, $summary, $paymentStatus, $paymentMethod, $isAmcVisitInvoice, $amcVisit): TicketDocument {
                $document = $this->resolveVisibleTicketDocument($ticket, 'invoice');
                $payload = [
                    'ticket_id' => $ticket->id,
                    'company_id' => $ticket->company_id,
                    'created_by' => $user->id,
                    'document_type' => 'invoice',
                    'invoice_or_quote' => 1,
                    'items' => $quoteItems,
                    'subtotal' => $summary['subtotal'],
                    'discount' => $summary['discount'],
                    'gst_amount' => $summary['gst_amount'],
                    'total' => $summary['total'],
                    'payment_status' => $paymentStatus,
                    'payment_method' => $paymentStatus === 1 ? $paymentMethod : null,
                ];

                $changeMessages = [];

                if ($document) {
                    $changeMessages = $this->buildTicketDocumentChangeMessages($document, $payload);
                    $this->snapshotTicketDocument($document, $payload);
                    $document->forceFill($payload)->save();
                } else {
                    $company = Company::query()
                        ->whereKey($ticket->company_id)
                        ->lockForUpdate()
                        ->first();
                    $allocatedDocument = $company?->allocateDocumentNumber('invoice');
                    $documentNumber = $allocatedDocument['number'] ?? null;

                    $document = TicketDocument::query()->create($payload + [
                        'document_number' => 'INV-TMP-' . Str::upper(Str::random(8)),
                    ]);

                    $document->forceFill([
                        'document_number' => $documentNumber ?? ('INV-' . now()->format('Y') . '-' . str_pad((string) $document->id, 5, '0', STR_PAD_LEFT)),
                    ])->save();

                    if ($allocatedDocument !== null && $company) {
                        $company->syncDocumentNumberSequence('invoice', ((int) $allocatedDocument['sequence']) + 1);
                    }
                }

                $invoiceLink = app(TicketDocumentLinkService::class)->linkInvoice(
                    $ticket,
                    $document->fresh(),
                    $user,
                    $paymentStatus,
                    $paymentStatus === 1 ? $paymentMethod : null,
                    $summary,
                    $changeMessages
                );

                if ($isAmcVisitInvoice) {
                    $amcVisit->forceFill([
                        'ticket_invoice_id' => $invoiceLink->id,
                    ])->saveQuietly();
                }

                return $document->fresh();
            });

            $ticket->load([
                'user:id,name',
                'assignedUser:id,name',
                'referencedUser:id,name',
                'amc:id,support_ticket_id,contract_no',
                'generatedFromAmc:id,contract_no',
                'replies.user:id,name',
            ]);
            $ticket->loadCount('replies');

            return response()->json([
                'success' => true,
                'message' => 'Invoice saved successfully.',
                'ticket_id' => $ticket->id,
                'invoice_id' => $invoice->id,
                'quote_id' => null,
                'quote_items' => $quoteItems,
                'quote_summary' => $summary,
                'document_type' => 'invoice',
                'invoice_or_quote' => 1,
                'payment_status' => $this->invoicePaymentStatusLabel($invoice->payment_status),
                'payment_method' => $this->invoicePaymentMethodLabel($invoice->payment_method),
                'document_id' => $invoice->id,
                'document_number' => $invoice->document_number,
                'amc_visit_invoice' => $isAmcVisitInvoice,
                'amc_visit_id' => $isAmcVisitInvoice ? $amcVisit->id : null,
            ]);
        }

        $document = DB::transaction(function () use ($ticket, $user, $quoteId, $quoteItems, $summary): TicketDocument {
            $ticket->update([
                'quote_id' => $quoteId,
                'quote_items' => $quoteItems,
                'quote_subtotal' => $summary['subtotal'],
                'quote_discount' => $summary['discount'],
                'quote_gst_amount' => $summary['gst_amount'],
                'quote_total' => $summary['total'],
            ]);

            $record = $this->resolveVisibleTicketDocument($ticket, 'quotation');
            $payload = [
                'ticket_id' => $ticket->id,
                'company_id' => $ticket->company_id,
                'created_by' => $user->id,
                'document_type' => 'quotation',
                'invoice_or_quote' => 2,
                'items' => $quoteItems,
                'subtotal' => $summary['subtotal'],
                'discount' => $summary['discount'],
                'gst_amount' => $summary['gst_amount'],
                'total' => $summary['total'],
            ];

            $changeMessages = [];

            if ($record) {
                $changeMessages = $this->buildTicketDocumentChangeMessages($record, $payload);
                $this->snapshotTicketDocument($record, $payload);
                $record->forceFill($payload)->save();
            } else {
                $company = Company::query()
                    ->whereKey($ticket->company_id)
                    ->lockForUpdate()
                    ->first();
                $allocatedDocument = $company?->allocateDocumentNumber('quotation');
                $documentNumber = $allocatedDocument['number'] ?? null;

                $record = TicketDocument::query()->create($payload + [
                    'document_number' => 'QTN-TMP-' . Str::upper(Str::random(8)),
                ]);

                $record->forceFill([
                    'document_number' => $documentNumber ?? ('QTN-' . now()->format('Y') . '-' . str_pad((string) $record->id, 5, '0', STR_PAD_LEFT)),
                ])->save();

                if ($allocatedDocument !== null && $company) {
                    $company->syncDocumentNumberSequence('quotation', ((int) $allocatedDocument['sequence']) + 1);
                }
            }

            app(TicketDocumentLinkService::class)->linkQuotation($ticket, $record->fresh(), $user, $changeMessages);

            return $record->fresh();
        });

        $ticket->load([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'replies.user:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'success' => true,
            'message' => 'Quotation saved successfully.',
            'ticket_id' => $ticket->id,
            'quotation_id' => $document->id,
            'quote_id' => $quoteId,
            'quote_items' => $quoteItems,
            'quote_summary' => $summary,
            'document_type' => 'quotation',
            'invoice_or_quote' => 2,
            'payment_status' => null,
            'payment_method' => null,
            'document_id' => $document->id,
            'document_number' => $document->document_number,
        ]);
        } catch (HttpResponseException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'message' => 'Request failed.',
                'errors' => [
                    'general' => [config('app.debug') ? $exception->getMessage() : 'Something went wrong while processing the request.'],
                ],
            ], 500);
        }
    }

    public function deleteQuoteItem(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to update this ticket.');
        abort_unless($user->company?->enable_boq ?? true, 403, 'BOQ feature is disabled for this company.');

        $validated = Validator::make([
            'product_id' => $request->query('product_id'),
        ], [
            'product_id' => ['required', 'integer', 'min:1'],
        ], [
            'product_id.required' => 'Please provide a service id.',
            'product_id.integer' => 'Service id must be a valid number.',
            'product_id.min' => 'Service id must be greater than zero.',
        ], [
            'product_id' => 'service',
        ])->validate();

        $productId = (int) $validated['product_id'];
        $quoteItems = collect(is_array($ticket->quote_items ?? null) ? $ticket->quote_items : []);

        $matchingItems = $quoteItems->filter(function ($item) use ($productId) {
            return is_array($item) && (int) ($item['product_id'] ?? 0) === $productId;
        });

        if ($matchingItems->isEmpty()) {
            throw $this->validationErrorResponse([
                'product_id' => ['Selected service is not available in this ticket.'],
            ]);
        }

        $remainingItems = $quoteItems
            ->reject(function ($item) use ($productId) {
                return is_array($item) && (int) ($item['product_id'] ?? 0) === $productId;
            })
            ->values()
            ->all();

        $summary = $this->buildQuoteSummary($remainingItems, (float) ($ticket->quote_discount ?? 0));

        $ticket->update([
            'quote_items' => $remainingItems === [] ? null : $remainingItems,
            'quote_subtotal' => $summary['subtotal'],
            'quote_discount' => $summary['discount'],
            'quote_gst_amount' => $summary['gst_amount'],
            'quote_total' => $summary['total'],
        ]);

        $ticket->refresh();
        $ticket->load([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'replies.user:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'success' => true,
            'message' => 'Service removed from ticket successfully.',
            'ticket_id' => $ticket->id,
            'product_id' => $productId,
            'removed_count' => $matchingItems->count(),
            'quote_id' => $ticket->quote_id,
            'quote_items' => $this->formatQuoteItems($ticket->quote_items ?? [], $ticket->company_id),
            'quote_summary' => $this->buildQuoteSummary($ticket->quote_items ?? [], (float) ($ticket->quote_discount ?? 0)),
            'ticket' => $this->formatTicketDetail($ticket, $request->getSchemeAndHttpHost()),
        ]);
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
            'document_number' => $document->document_number
                . '-OLD-' . now()->format('YmdHis')
                . '-' . Str::upper(Str::random(4)),
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

    /**
     * @return array<int, string>
     */
    private function buildTicketDocumentChangeMessages(TicketDocument $document, array $payload): array
    {
        $messages = [];
        $oldItems = $this->normalizeDocumentItems($document->items ?? []);
        $newItems = $this->normalizeDocumentItems($payload['items'] ?? []);

        foreach ($newItems as $key => $newItem) {
            if (! array_key_exists($key, $oldItems)) {
                $messages[] = 'Added service: ' . $this->formatDocumentItemLabel($newItem);
                continue;
            }

            $itemChanges = $this->buildDocumentItemChangeParts($oldItems[$key], $newItem);
            if ($itemChanges !== []) {
                $messages[] = 'Updated service ' . $this->documentItemName($newItem) . ': ' . implode(', ', $itemChanges);
            }
        }

        foreach ($oldItems as $key => $oldItem) {
            if (! array_key_exists($key, $newItems)) {
                $messages[] = 'Removed service: ' . $this->formatDocumentItemLabel($oldItem);
            }
        }

        foreach ([
            'subtotal' => 'Subtotal',
            'discount' => 'Discount',
            'gst_amount' => 'GST amount',
            'total' => 'Total',
        ] as $field => $label) {
            $oldValue = round((float) ($document->{$field} ?? 0), 2);
            $newValue = round((float) ($payload[$field] ?? 0), 2);

            if ($oldValue !== $newValue) {
                $messages[] = $label . ' changed from ' . $this->formatMoneyValue($oldValue) . ' to ' . $this->formatMoneyValue($newValue);
            }
        }

        if (array_key_exists('payment_status', $payload) && (int) ($document->payment_status ?? 0) !== (int) ($payload['payment_status'] ?? 0)) {
            $messages[] = 'Payment status changed from '
                . $this->invoicePaymentStatusLabel($document->payment_status)
                . ' to '
                . $this->invoicePaymentStatusLabel($payload['payment_status']);
        }

        if (array_key_exists('payment_method', $payload) && (int) ($document->payment_method ?? -1) !== (int) ($payload['payment_method'] ?? -1)) {
            $messages[] = 'Payment method changed from '
                . $this->invoicePaymentMethodLabel($document->payment_method)
                . ' to '
                . $this->invoicePaymentMethodLabel($payload['payment_method']);
        }

        return $messages;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, array<string, mixed>>
     */
    private function normalizeDocumentItems(array $items): array
    {
        $normalized = [];

        foreach (array_values($items) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $productId = (int) ($item['product_id'] ?? 0);
            $key = $productId > 0
                ? 'product:' . $productId
                : 'row:' . $index . ':' . Str::lower((string) ($item['name'] ?? ''));

            $normalized[$key] = $item;
        }

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function buildDocumentItemChangeParts(array $oldItem, array $newItem): array
    {
        $changes = [];

        foreach ([
            'qty' => 'qty',
            'price' => 'price',
            'gst' => 'GST',
            'total' => 'total',
        ] as $field => $label) {
            $oldValue = $field === 'qty'
                ? (int) ($oldItem[$field] ?? 0)
                : round((float) ($oldItem[$field] ?? 0), 2);
            $newValue = $field === 'qty'
                ? (int) ($newItem[$field] ?? 0)
                : round((float) ($newItem[$field] ?? 0), 2);

            if ($oldValue === $newValue) {
                continue;
            }

            $changes[] = $label . ' ' . $this->formatDocumentChangeValue($field, $oldValue)
                . ' to ' . $this->formatDocumentChangeValue($field, $newValue);
        }

        $oldNote = trim((string) ($oldItem['note'] ?? ''));
        $newNote = trim((string) ($newItem['note'] ?? ''));

        if ($oldNote !== $newNote) {
            $changes[] = 'note changed';
        }

        return $changes;
    }

    private function formatDocumentItemLabel(array $item): string
    {
        return $this->documentItemName($item)
            . ' x ' . (int) ($item['qty'] ?? 0)
            . ' @ ' . $this->formatMoneyValue((float) ($item['price'] ?? 0));
    }

    private function documentItemName(array $item): string
    {
        return Str::squish((string) ($item['name'] ?? 'Service'));
    }

    private function formatDocumentChangeValue(string $field, int|float $value): string
    {
        if ($field === 'qty') {
            return (string) (int) $value;
        }

        return $this->formatMoneyValue((float) $value);
    }

    private function formatMoneyValue(float $value): string
    {
        return 'Rs. ' . number_format($value, 2);
    }

    public function startTime(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to update this ticket.');

        $validated = $this->validateJson($request, [
            'recorded_at' => ['nullable', 'date'],
        ]);

        if ($ticket->start_time !== null) {
            throw $this->validationErrorResponse([
                'ticket' => ['Ticket start time already exists.'],
            ]);
        }

        $capturedAt = isset($validated['recorded_at'])
            ? Carbon::parse($validated['recorded_at'])
            : now();

        $ticket->update([
            'start_time' => $capturedAt,
            'status' => 'in_progress',
        ]);

        $ticket->refresh();
        $ticket->load([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'replies.user:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'message' => 'Ticket start time recorded successfully.',
            'ticket_id' => $ticket->id,
            'captured_at' => $capturedAt->toIso8601String(),
            'recorded_by' => [
                'user_id' => $user->id,
                'staff_id' => $user->staff?->id,
                'name' => $user->name,
            ],
            'ticket' => $this->formatTicketDetail($ticket, $request->getSchemeAndHttpHost()),
        ], 201);
    }

    public function endTime(Request $request, Ticket $ticket): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');
        abort_unless($this->canAccessTicket($user, $ticket), 403, 'You are not allowed to update this ticket.');

        $validated = $this->validateJson($request, [
            'recorded_at' => ['nullable', 'date'],
        ]);

        if ($ticket->start_time === null) {
            throw $this->validationErrorResponse([
                'ticket' => ['Ticket start time must be recorded before the end time.'],
            ]);
        }

        if ($ticket->end_time !== null) {
            throw $this->validationErrorResponse([
                'ticket' => ['Ticket end time already exists.'],
            ]);
        }

        $capturedAt = isset($validated['recorded_at'])
            ? Carbon::parse($validated['recorded_at'])
            : now();

        if ($ticket->start_time && $capturedAt->lessThan($ticket->start_time)) {
            throw $this->validationErrorResponse([
                'ticket' => ['Ticket end time cannot be earlier than the start time.'],
            ]);
        }

        $ticket->update([
            'end_time' => $capturedAt,
        ]);

        $ticket->refresh();
        $ticket->load([
            'user:id,name',
            'assignedUser:id,name',
            'referencedUser:id,name',
            'amc:id,support_ticket_id,contract_no',
            'generatedFromAmc:id,contract_no',
            'replies.user:id,name',
        ]);
        $ticket->loadCount('replies');

        return response()->json([
            'message' => 'Ticket end time recorded successfully.',
            'ticket_id' => $ticket->id,
            'captured_at' => $capturedAt->toIso8601String(),
            'recorded_by' => [
                'user_id' => $user->id,
                'staff_id' => $user->staff?->id,
                'name' => $user->name,
            ],
            'ticket' => $this->formatTicketDetail($ticket, $request->getSchemeAndHttpHost()),
        ]);
    }

    public function openPhoto(Request $request, Ticket $ticket, string $collection, int $index): Response
    {
        abort_unless(
            $request->hasValidSignature() || $request->hasValidSignature(absolute: false),
            403
        );

        $paths = match ($collection) {
            'current-condition' => $ticket->current_condition_photos ?? [],
            'after-repair' => $ticket->after_repair_photos ?? [],
            default => abort(404),
        };

        $path = collect($paths)->values()->get($index);
        abort_unless(is_string($path) && trim($path) !== '', 404);

        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        abort_unless(Storage::disk('public')->exists($normalizedPath), 404);

        return Storage::disk('public')->response($normalizedPath, basename($normalizedPath));
    }

    public function openSignature(Request $request, Ticket $ticket): Response
    {
        abort_unless(
            $request->hasValidSignature() || $request->hasValidSignature(absolute: false),
            403
        );

        $path = $ticket->customer_signature;
        abort_unless(is_string($path) && trim($path) !== '', 404);

        $normalizedPath = ltrim(str_replace('\\', '/', $path), '/');
        abort_unless(Storage::disk('public')->exists($normalizedPath), 404);

        return Storage::disk('public')->response($normalizedPath, basename($normalizedPath));
    }

    private function normalizeFilterValues(mixed $values): array
    {
        if (is_string($values)) {
            $values = explode(',', $values);
        }

        if (! is_array($values)) {
            return [];
        }

        return collect($values)
            ->map(fn ($value) => strtolower(trim((string) $value)))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function formatTicket(Ticket $ticket): array
    {
        $paymentFields = $this->resolveTicketPaymentFields($ticket);

        return [
            'id' => $ticket->id,
            // 'public_id' => $ticket->getRouteKey(),
            'ticket_number' => $ticket->ticket_number,
            'project_id' => $ticket->project_id,
            'subject' => $ticket->subject,
            'description' => $ticket->description,
            'type' => $ticket->type,
            'type_label' => $ticket->type_label,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'status_label' => $ticket->status_label,
            'accept_job' => $ticket->accept_job === null ? null : (int) (bool) $ticket->accept_job,
            'accept_job_label' => $ticket->accept_job === null ? 'Pending' : ((bool) $ticket->accept_job ? 'Accepted' : 'Rejected'),
            'start_time' => $ticket->start_time?->toIso8601String(),
            'end_time' => $ticket->end_time?->toIso8601String(),
            'visit_date' => $ticket->visit_date?->toDateString(),
            'customer_contact_number' => $ticket->customer_contact_number,
            'customer_name' => $ticket->customer_name,
            'customer_address'=>$ticket->customer_address,
            // 'customer_email' => $ticket->customer_email,
            // 'created_by' => [
            //     'id' => $ticket->user?->id,
            //     'name' => $ticket->user?->name,
            // ],
            // 'assigned_to' => [
            //     'id' => $ticket->assignedUser?->id,
            //     'name' => $ticket->assignedUser?->name,
            // ],
            // 'referenced_by' => [
            //     'id' => $ticket->referencedUser?->id,
            //     'name' => $ticket->referencedUser?->name,
            // ],
            // 'reply_count' => $ticket->replies_count,
            'amc' => ($ticket->generatedFromAmc || $ticket->amc) ? [
                'id' => $ticket->generatedFromAmc?->id ?? $ticket->amc?->id,
                'contract_no' => $ticket->generatedFromAmc?->contract_no ?? $ticket->amc?->contract_no,
            ] : null,
            'source_type' => $ticket->source_type,
            'source_id' => $ticket->source_id,
            'payment_status' => $paymentFields['payment_status'],
            'payment_method' => $paymentFields['payment_method'],
            'visit_number' => $ticket->visit_number,
            'visit_type' => $ticket->visit_type,
            'parent_ticket' => $ticket->parentTicket ? [
                'id' => $ticket->parentTicket->id,
                'ticket_number' => $ticket->parentTicket->ticket_number,
            ] : null,
            'repeat_count' => $ticket->repeat_count,
            'repeat_status' => (bool) $ticket->repeat_status,
            'repeat_status_label' => $ticket->repeat_status ? 'Yes' : 'No',
            'repeated_after_days' => $ticket->repeated_after_days,
            'last_repeat_at' => $ticket->last_repeat_at?->toISOString(),
            'assigned_previous_staff' => $ticket->previousStaff ? [
                'id' => $ticket->previousStaff->id,
                'name' => $ticket->previousStaff->name,
            ] : null,
            // 'created_at' => $ticket->created_at?->toISOString(),
            // 'updated_at' => $ticket->updated_at?->toISOString(),
        ];
    }

    private function formatTicketDetail(Ticket $ticket, string $baseUrl): array
    {
        $latestInvoice = $ticket->relationLoaded('invoices')
            ? $ticket->invoices->first()
            : null;
        $latestInvoiceDocument = $latestInvoice?->invoice;

        return array_merge($this->formatTicket($ticket), [
            'service_notes' => $ticket->service_notes,
            'work_summary' => $ticket->work_summary,
            'complate_task_list' => $this->normalizeTaskListValues($ticket->complate_task_list ?? []),
            'company_task_statuses' => $this->formatCompanyTaskStatuses($ticket->company_id, $ticket->complate_task_list ?? []),
            'quote_items' => $this->formatQuoteItems($ticket->quote_items ?? [], $ticket->company_id),
            'quote_summary' => $this->buildQuoteSummary($ticket->quote_items ?? [], (float) ($ticket->quote_discount ?? 0)),
            'invoice_items' => $latestInvoiceDocument
                ? $this->formatQuoteItems($latestInvoiceDocument->items ?? [], $ticket->company_id)
                : [],
            'invoice_summary' => $latestInvoiceDocument
                ? $this->buildQuoteSummary($latestInvoiceDocument->items ?? [], (float) ($latestInvoiceDocument->discount ?? 0))
                : [
                    'item_count' => 0,
                    'total_qty' => 0,
                    'subtotal' => 0,
                    'discount' => 0,
                    'gst_amount' => 0,
                    'total' => 0,
                ],
            'invoice_number' => $latestInvoice?->invoice_number,
            'invoice_payment_status' => $this->invoicePaymentStatusLabel(
                filled($latestInvoice?->payment_status) || $latestInvoice?->payment_status === 0
                    ? (int) $latestInvoice?->payment_status
                    : null
            ),
            'invoice_payment_method' => $this->invoicePaymentMethodLabel(
                filled($latestInvoice?->payment_method) || $latestInvoice?->payment_method === 0
                    ? (int) $latestInvoice?->payment_method
                    : null
            ),
            'customer_signature' => $ticket->customer_signature,
            'customer_signature_url' => $this->formatStoredFileUrl($ticket, $baseUrl),
            'tasks' => $this->normalizeTaskValues($ticket->tasks ?? []),
            'current_condition_photos' => $this->formatPhotoCollection($ticket, 'current-condition', $ticket->current_condition_photos, $baseUrl),
            'after_repair_photos' => $this->formatPhotoCollection($ticket, 'after-repair', $ticket->after_repair_photos, $baseUrl),
            'replies' => $ticket->replies->map(fn ($reply) => [
                'id' => $reply->id,
                'message' => $reply->message,
                'user' => [
                    'id' => $reply->user?->id,
                    'name' => $reply->user?->name,
                ],
                'created_at' => $reply->created_at?->toISOString(),
                'updated_at' => $reply->updated_at?->toISOString(),
            ])->values()->all(),
            'repeat_history' => $ticket->repeatHistories->map(fn ($history) => [
                'id' => $history->id,
                'repeat_count' => $history->repeat_count,
                'previous_status' => $history->previous_status,
                'reopened_status' => $history->reopened_status,
                'repeated_after_days' => $history->repeated_after_days,
                'note' => $history->note,
                'repeated_at' => $history->repeated_at?->toISOString(),
                'reopened_by' => [
                    'id' => $history->reopenedBy?->id,
                    'name' => $history->reopenedBy?->name,
                ],
                'previous_assigned_to' => [
                    'id' => $history->previousAssignee?->id,
                    'name' => $history->previousAssignee?->name,
                ],
                'assigned_to' => [
                    'id' => $history->assignee?->id,
                    'name' => $history->assignee?->name,
                ],
            ])->values()->all(),
        ]);
    }

    private function formatPhotoCollection(Ticket $ticket, string $collection, ?array $paths, string $baseUrl): array
    {
        return collect($paths ?? [])
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->values()
            ->map(fn (string $path, int $index) => [
                'path' => $path,
                'url' => rtrim($baseUrl, '/') . URL::signedRoute('ticket-photos.open', [
                    'ticket' => $ticket->getRouteKey(),
                    'collection' => $collection,
                    'index' => $index,
                ], absolute: false),
                'name' => basename($path),
            ])
            ->all();
    }

    private function storeTicketPhotos(
        Ticket $ticket,
        array $files,
        string $folder,
        MediaUploadService $mediaUploadService
        ): ?array {
        if ($files === []) {
            return null;
        }

        $directory = 'company_' . $ticket->company_id . '/tickets/' . $folder;
        $storedPaths = collect($files)
            ->map(fn ($file) => $mediaUploadService->store($file, $directory, 'public'))
            ->values();

        $existingPaths = $folder === 'current-condition'
            ? collect($ticket->current_condition_photos ?? [])
            : collect($ticket->after_repair_photos ?? []);

        return $existingPaths
            ->merge($storedPaths)
            ->filter(fn ($path) => is_string($path) && trim($path) !== '')
            ->unique()
            ->take(3)
            ->values()
            ->all();
    }

    private function normalizeTaskValues(array $tasks): array
    {
        return Ticket::normalizeTaskItems($tasks);
    }

    private function syncTaskStatusesFromCompletedList(int|string|null $companyId, mixed $existingTasks, mixed $completedTasks): array
    {
        $completedLookup = collect($this->normalizeTaskListValues($completedTasks))
            ->map(fn (string $task) => Str::lower(Str::squish($task)))
            ->values()
            ->all();

        $taskItems = Ticket::normalizeTaskItems($existingTasks);

        if ($taskItems === []) {
            $taskItems = collect(Company::query()->find($companyId)?->taskMasterNames() ?? [])
                ->map(fn (string $name) => [
                    'name' => $name,
                    'status' => 'pending',
                ])
                ->values()
                ->all();
        }

        return collect($taskItems)
            ->map(function (array $task) use ($completedLookup) {
                $name = Str::squish((string) ($task['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                $normalized = Str::lower($name);

                return [
                    'name' => $name,
                    'status' => in_array($normalized, $completedLookup, true) ? 'done' : 'pending',
                ];
            })
            ->filter()
            ->unique(fn (array $task) => Str::lower($task['name']))
            ->values()
            ->all();
    }

    private function normalizeTaskListValues(mixed $values): array
    {
        return $this->normalizeListInput($values)
            ->map(function ($value) {
                if (is_array($value)) {
                    $value = $value['name'] ?? $value['task'] ?? '';
                }

                return Str::squish((string) $value);
            })
            ->filter(fn (string $value) => $value !== '')
            ->unique(fn (string $value) => Str::lower($value))
            ->values()
            ->all();
    }

    private function normalizeListInput(mixed $values): \Illuminate\Support\Collection
    {
        if (is_string($values)) {
            $trimmed = trim($values);

            if ($trimmed === '') {
                return collect();
            }

            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $values = $decoded;
            } else {
                $trimmed = trim($trimmed, "[]");

                if ($trimmed === '') {
                    return collect();
                }

                $values = preg_split('/\s*,\s*/', $trimmed) ?: [];
            }
        }

        if (! is_array($values)) {
            if (filled($values)) {
                $values = [$values];
            } else {
                return collect();
            }
        }

        return collect($values)->values();
    }

    private function formatStoredFileUrl(Ticket $ticket, string $baseUrl): ?string
    {
        $path = $ticket->customer_signature;

        if (! filled($path)) {
            return null;
        }

        return rtrim($baseUrl, '/') . URL::signedRoute('ticket-signature.open', [
            'ticket' => $ticket->getRouteKey(),
        ], absolute: false);
    }

    private function formatTaskMasterOptions(User $user): array
    {
        $user->loadMissing('company');

        return collect($user->company?->taskMasterNames() ?? [])
            ->map(fn (string $name) => [
                'name' => $name,
            ])
            ->values()
            ->all();
    }

    private function formatCompanyServices(int|string|null $companyId): array
    {
        return BoqProduct::query()
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->with('category:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (BoqProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'unit' => $product->unit,
                'price' => (float) $product->price,
                'gst' => (float) $product->gst,
                'description' => $product->description,
                'category' => $product->category ? [
                    'id' => $product->category->id,
                    'name' => $product->category->name,
                ] : null,
            ])
            ->values()
            ->all();
    }

    private function formatQuoteItems(mixed $items, int|string|null $companyId = null): array
    {
        $products = BoqProduct::query()
            ->where('company_id', $companyId)
            ->with('category:id,name')
            ->get()
            ->keyBy(fn (BoqProduct $product) => (int) $product->id);

        return collect(is_array($items) ? $items : [])
            ->map(function ($item) use ($products) {
                $productId = (int) ($item['product_id'] ?? 0);
                $qty = max(1, (int) ($item['qty'] ?? 1));
                $price = round((float) ($item['price'] ?? 0), 2);
                $total = round((float) ($item['total'] ?? ($qty * $price)), 2);
                $product = $productId > 0 ? $products->get($productId) : null;

                return [
                    'product_id' => $productId > 0 ? $productId : null,
                    'name' => trim((string) ($item['name'] ?? $product?->name ?? '')),
                    'qty' => $qty,
                    'price' => $price > 0 ? $price : (float) ($product?->price ?? 0),
                    'total' => $total,
                    'unit' => trim((string) ($item['unit'] ?? $product?->unit ?? '')),
                    'gst' => (float) ($item['gst'] ?? $product?->gst ?? 0),
                    'note' => filled($item['note'] ?? null) ? Str::squish((string) $item['note']) : null,
                    'description' => trim((string) ($item['description'] ?? $product?->description ?? '')),
                    'category' => $product?->category ? [
                        'id' => $product->category->id,
                        'name' => $product->category->name,
                    ] : null,
                ];
            })
            ->filter(fn (array $item) => $item['name'] !== '')
            ->values()
            ->all();
    }

    private function buildQuoteSummary(mixed $items, float $discount = 0.0): array
    {
        $normalizedItems = collect($this->formatQuoteItems($items));
        $calculation = QuotationTaxCalculator::calculate($normalizedItems->all(), $discount, false);

        return [
            'item_count' => $normalizedItems->count(),
            'total_qty' => (int) $normalizedItems->sum(fn (array $item) => (int) $item['qty']),
            'subtotal' => (float) $calculation['subtotal'],
            'discount' => (float) $calculation['discount'],
            'gst_amount' => (float) $calculation['gst_amount'],
            'total' => (float) $calculation['grand_total'],
        ];
    }

    private function generateQuoteId(Ticket $ticket): string
    {
        do {
            $quoteId = 'QTE-' . now()->format('Ymd') . '-T' . $ticket->id . '-' . Str::upper(Str::random(5));
        } while (Ticket::query()->where('quote_id', $quoteId)->exists());

        return $quoteId;
    }

    /**
     * Build a one-line invoice for a single AMC visit.
     */
    private function buildAmcVisitInvoiceItems(Amc $amc, AmcVisit $visit): array
    {
        $totalVisits = max(1, (int) $amc->total_visits);
        $grandTotal = round((float) $amc->total_amount, 2);
        $baseShare = round($grandTotal / $totalVisits, 2);
        $visitNo = max(1, (int) $visit->visit_no);

        $amount = $visitNo >= $totalVisits
            ? round(max(0, $grandTotal - ($baseShare * max(0, $totalVisits - 1))), 2)
            : $baseShare;

        return [[
            'product_id' => null,
            'name' => 'AMC Visit #' . $visitNo . ' - ' . ($amc->project_name ?: $amc->contract_no),
            'qty' => 1,
            'price' => $amount,
            'base_total' => $amount,
            'unit' => 'visit',
            'gst' => 0,
            'gst_amount' => 0,
            'total' => $amount,
            'note' => 'Generated from AMC visit #' . $visitNo . '.',
            'description' => 'AMC visit invoice for contract ' . $amc->contract_no . '.',
        ]];
    }

    private function resolveAmcVisitForTicket(Ticket $ticket): ?AmcVisit
    {
        $ticket->loadMissing(['generatedFromAmc', 'amc']);

        $amcId = $ticket->generated_from_amc_id
            ?: $ticket->generatedFromAmc?->id
            ?: $ticket->amc?->id;

        if (! $amcId) {
            return null;
        }

        if ($ticket->source_type === 'amc_visit' && $ticket->source_id) {
            $visit = AmcVisit::query()
                ->where('amc_id', $amcId)
                ->whereKey($ticket->source_id)
                ->first();

            if ($visit) {
                return $visit;
            }
        }

        if ($ticket->visit_number) {
            $visit = AmcVisit::query()
                ->where('amc_id', $amcId)
                ->where('visit_no', (int) $ticket->visit_number)
                ->first();

            if ($visit) {
                return $visit;
            }
        }

        return AmcVisit::query()
            ->where('amc_id', $amcId)
            ->where(function ($query) use ($ticket) {
                $query->where('support_ticket_id', $ticket->id)
                    ->orWhereHas('supportTicket', function ($supportTicketQuery) use ($ticket) {
                        $supportTicketQuery->whereKey($ticket->id);
                    });
            })
            ->first();
    }

    private function invoicePaymentStatusLabel(?int $paymentStatus): ?string
    {
        return match ($paymentStatus) {
            1 => 'Paid',
            0 => 'Unpaid',
            default => null,
        };
    }

    private function invoicePaymentMethodLabel(?int $paymentMethod): ?string
    {
        return match ($paymentMethod) {
            0 => 'Cash',
            1 => 'Online',
            default => null,
        };
    }

    private function resolveTicketPaymentFields(Ticket $ticket): array
    {
        $latestInvoice = $ticket->relationLoaded('invoices')
            ? $ticket->invoices->first()
            : null;

        $paymentStatus = $latestInvoice?->payment_status ?? $ticket->payment_status;
        $paymentMethod = $latestInvoice?->payment_method ?? $ticket->payment_method;

        return [
            'payment_status' => $this->invoicePaymentStatusLabel(
                filled($paymentStatus) || $paymentStatus === 0 ? (int) $paymentStatus : null
            ),
            'payment_method' => $this->invoicePaymentMethodLabel(
                filled($paymentMethod) || $paymentMethod === 0 ? (int) $paymentMethod : null
            ),
        ];
    }

    private function formatCompanyTaskStatuses(int|string|null $companyId, mixed $completedTasks = []): array
    {
        $company = Company::query()->find($companyId);

        if (! $company) {
            return [];
        }

        $completedLookup = collect($this->normalizeTaskListValues($completedTasks))
            ->map(fn (string $task) => Str::lower(Str::squish($task)))
            ->values()
            ->all();

        return collect($company->taskMasterNames())
            ->map(function (string $name) use ($completedLookup) {
                $normalized = Str::lower(Str::squish($name));

                return [
                    'name' => $name,
                    'status' => in_array($normalized, $completedLookup, true) ? 'completed' : 'pending',
                    'status_label' => in_array($normalized, $completedLookup, true) ? 'Completed' : 'Pending',
                ];
            })
            ->values()
            ->all();
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

            $normalized = Str::lower($name);

            if (! in_array($normalized, $existingNames, true)) {
                $taskMasters[] = ['name' => $name];
                $existingNames[] = $normalized;
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

    private function validateJson(Request $request, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($request->all(), $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => collect($validator->errors())->flatten()->first(),
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }

    private function validationErrorResponse(array $errors): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => collect($errors)->flatten()->first() ?? 'The given data was invalid.',
            'errors' => $errors,
        ], 422));
    }

    private function formatStatusOptions(): array
    {
        return collect(Ticket::statusOptions())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    private function allowedStatusInputs(): array
    {
        return array_values(array_unique([
            ...array_keys(Ticket::statusOptions()),
            'pending',
            'rescheduled',
        ]));
    }

    private function normalizeIncomingStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => 'open',
            'rescheduled' => 'reschedule',
            default => strtolower(trim($status)),
        };
    }

    private function buildStatusCounts($query): array
    {
        $statusBreakdown = $query
            ->selectRaw('LOWER(status) as status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($statusBreakdown->get('open') ?? 0),
            'in_progress' => (int) ($statusBreakdown->get('in_progress') ?? 0),
            'closed' => (int) ($statusBreakdown->get('closed') ?? 0),
        ];
    }

    private function canAccessTicket(User $user, Ticket $ticket): bool
    {
        if ((int) $ticket->company_id !== (int) $user->company_id) {
            return false;
        }

        if ((int) $ticket->user_id === (int) $user->id || (int) $ticket->assigned_to === (int) $user->id) {
            return true;
        }

        if (\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments')) {
            if ($ticket->relationLoaded('assignedUsers')) {
                return $ticket->assignedUsers->contains('id', $user->id);
            }

            return $ticket->assignedUsers()->where('users.id', $user->id)->exists();
        }

        return false;
    }
}
