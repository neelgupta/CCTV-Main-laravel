<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Amc;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Models\Ticket;
use App\Services\AmcSupportWorkflowService;
use App\Services\TicketAssignmentHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class CompanySupportTicketController extends Controller
{
    public function update(Request $request, Ticket $ticket, AmcSupportWorkflowService $amcSupportWorkflowService, TicketAssignmentHistoryService $assignmentHistory): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasPermission('support.edit') || $user->isCompanyAdmin()), 403, 'You are not allowed to update support tickets.');
        abort_unless((int) $ticket->company_id === (int) $user->company_id, 403);
        // abort_unless($ticket->type === 'support' , 403, 'Only support tickets can be updated from this API.');

        $companyId = (int) $user->company_id;
        $projectIds = \App\Models\Project::query()->where('company_id', $companyId)->pluck('id')->all();
        $assigneeIds = $this->ticketAssigneeUserIds($companyId);
        $inputData = $this->requestInputData($request);

        $validated = $this->validateJson($inputData, [
            'customer_contact_number' => ['sometimes', 'nullable', 'string', 'max:30'],
            'customer_name' => ['sometimes', 'string', 'min:2', 'max:191'],
            'customer_email' => ['sometimes', 'nullable', 'email:rfc,dns', 'max:191'],
            'customer_address' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'project_id' => ['sometimes', 'nullable', 'integer', Rule::in($projectIds)],
            'subject' => ['sometimes', 'string', 'min:5', 'max:191'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'visit_date' => ['sometimes', 'nullable', 'date'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'assigned_to' => ['sometimes', 'nullable'],
            'assigned_to.*' => ['integer', Rule::in($assigneeIds)],
            'amc_source' => ['sometimes', 'nullable', 'string', 'max:191'],
        ], [
            'customer_name.min' => 'Customer name must be at least 2 characters.',
            'customer_name.max' => 'Customer name may not be greater than 191 characters.',
            'customer_email.email' => 'Please enter a valid customer email address.',
            'customer_email.max' => 'Customer email may not be greater than 191 characters.',
            'customer_address.max' => 'Customer address may not be greater than 1000 characters.',
            'project_id.in' => 'Selected project is not available for this company.',
            'subject.min' => 'Subject must be at least 5 characters.',
            'subject.max' => 'Subject may not be greater than 191 characters.',
            'priority.in' => 'Priority must be low, medium, high, or urgent.',
            'visit_date.date' => 'Please enter a valid visit date.',
            'description.max' => 'Description may not be greater than 5000 characters.',
            'assigned_to.in' => 'Selected assignee is not available for this company.',
            'amc_source.max' => 'AMC reference may not be greater than 191 characters.',
        ]);

        if ($validated === []) {
            throw ValidationException::withMessages([
                'ticket' => 'Please provide at least one field to update.',
            ]);
        }

        $assignedUserIds = null;
        if (array_key_exists('assigned_to', $validated)) {
            $assignedInput = $validated['assigned_to'];
            $assignedUserIds = collect(is_array($assignedInput) ? $assignedInput : ($assignedInput ? [$assignedInput] : []))
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => in_array($id, $assigneeIds, true))
                ->unique()
                ->values()
                ->all();
        }

        $primaryAssignedTo = $assignedUserIds !== null
            ? ($assignedUserIds[0] ?? null)
            : $ticket->assigned_to;

        $payload = [
            'customer_contact_number' => array_key_exists('customer_contact_number', $validated)
                ? (filled($validated['customer_contact_number'] ?? null) ? trim((string) $validated['customer_contact_number']) : null)
                : $ticket->customer_contact_number,
            'customer_name' => array_key_exists('customer_name', $validated)
                ? trim((string) $validated['customer_name'])
                : $ticket->customer_name,
            'customer_email' => array_key_exists('customer_email', $validated)
                ? (filled($validated['customer_email'] ?? null) ? Str::lower(trim((string) $validated['customer_email'])) : null)
                : $ticket->customer_email,
            'customer_address' => array_key_exists('customer_address', $validated)
                ? (filled($validated['customer_address'] ?? null) ? trim((string) $validated['customer_address']) : null)
                : $ticket->customer_address,
            'project_id' => array_key_exists('project_id', $validated)
                ? (filled($validated['project_id'] ?? null) ? (int) $validated['project_id'] : null)
                : $ticket->project_id,
            'subject' => array_key_exists('subject', $validated)
                ? trim((string) $validated['subject'])
                : $ticket->subject,
            'priority' => array_key_exists('priority', $validated)
                ? $validated['priority']
                : $ticket->priority,
            'visit_date' => array_key_exists('visit_date', $validated)
                ? ($validated['visit_date'] ?? null)
                : ($ticket->visit_date?->toDateString()),
            'description' => array_key_exists('description', $validated)
                ? trim((string) $validated['description'])
                : $ticket->description,
            'assigned_to' => $primaryAssignedTo,
            'amc_source' => array_key_exists('amc_source', $validated)
                ? (filled($validated['amc_source'] ?? null) ? trim((string) $validated['amc_source']) : null)
                : null,
        ];

        $selectedAmc = null;
        if (array_key_exists('amc_source', $validated) && filled($payload['amc_source'] ?? null)) {
            $selectedAmc = $this->resolveAmcReference($payload['amc_source'], $companyId);

            if (! $this->canGenerateSupportTicketFromAmc($selectedAmc)) {
                throw ValidationException::withMessages([
                    'amc_source' => 'Selected AMC contract is not allowed for support ticket generation.',
                ]);
            }
        }

        DB::transaction(function () use ($ticket, $payload, $selectedAmc, $amcSupportWorkflowService, $user, $assignmentHistory, $assignedUserIds): void {
            $previousAssignedTo = $ticket->assigned_to;

            $ticket->update([
                'customer_contact_number' => $payload['customer_contact_number'],
                'customer_name' => Str::limit($payload['customer_name'], 191, ''),
                'customer_email' => $payload['customer_email'],
                'customer_address' => $payload['customer_address'],
                'project_id' => $payload['project_id'],
                'subject' => $payload['subject'],
                'priority' => $payload['priority'],
                'visit_date' => $payload['visit_date'],
                'description' => $payload['description'],
                'assigned_to' => $payload['assigned_to'],
            ]);

            if ($assignedUserIds !== null && Schema::hasTable('ticket_user_assignments')) {
                $ticket->assignedUsers()->sync($assignedUserIds);
            }

            if ($assignedUserIds !== null) {
                $assignmentHistory->syncCurrentAssignments(
                    $ticket->fresh(),
                    $assignedUserIds,
                    $user,
                    'Manual'
                );
            } elseif ($previousAssignedTo !== $ticket->assigned_to) {
                $assignmentHistory->syncCurrentAssignment(
                    $ticket->fresh(),
                    $ticket->assigned_to ? (int) $ticket->assigned_to : null,
                    $user,
                    'Manual'
                );
            }

            if ($selectedAmc) {
                $selectedAmc->forceFill([
                    'support_ticket_id' => $ticket->id,
                ])->saveQuietly();
            }

            if ($ticket->generated_from_amc_id || $ticket->amc?->exists()) {
                $amcSupportWorkflowService->advanceSupportWorkflowForTicket($ticket->fresh(['generatedFromAmc', 'amc']), $user);
            }
        });

        return response()->json([
            'message' => 'Support ticket updated successfully.',
            'data' => [
                'ticket' => $ticket->fresh(['assignedUser', 'generatedFromAmc', 'amc', 'replies.user']),
            ],
        ]);
    }

    public function store(Request $request, AmcSupportWorkflowService $amcSupportWorkflowService, TicketAssignmentHistoryService $assignmentHistory): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasPermission('support.create') || $user->isCompanyAdmin()), 403, 'You are not allowed to create support tickets.');

        $companyId = (int) $user->company_id;
        $amcIds = Amc::query()->where('company_id', $companyId)->pluck('id')->all();
        $projectIds = \App\Models\Project::query()->where('company_id', $companyId)->pluck('id')->all();
        $assigneeIds = $this->ticketAssigneeUserIds($companyId);
        $clientIds = Client::query()->where('company_id', $companyId)->pluck('id')->all();

        $validated = $this->validateJson($this->requestInputData($request), [
            'client_id' => ['required', 'integer', Rule::in($clientIds)],
            'project_id' => ['nullable', 'integer', Rule::in($projectIds)],
            'type' => ['required', 'string', Rule::in(['support', 'complain'])],
            'subject' => ['required', 'string', 'min:5', 'max:191'],
            'priority' => ['required', Rule::in(['low', 'medium', 'high', 'urgent'])],
            'visit_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:5000'],
            'assigned_to' => ['nullable'],
            'assigned_to.*' => ['integer', Rule::in($assigneeIds)],
            'amc_source' => ['nullable', 'string', 'max:191'],
        ], [
            'client_id.required' => 'Client is required.',
            'client_id.in' => 'Selected client is not available for this company.',
            'project_id.in' => 'Selected project is not available for this company.',
            'type.required' => 'Ticket type is required.',
            'type.in' => 'Type must be support or complain.',
            'subject.required' => 'Subject is required.',
            'subject.min' => 'Subject must be at least 5 characters.',
            'subject.max' => 'Subject may not be greater than 191 characters.',
            'priority.required' => 'Priority is required.',
            'priority.in' => 'Priority must be low, medium, high, or urgent.',
            'visit_date.required' => 'Visit date is required.',
            'visit_date.date' => 'Please enter a valid visit date.',
            'description.required' => 'Description is required.',
            'description.max' => 'Description may not be greater than 5000 characters.',
            'assigned_to.in' => 'Selected assignee is not available for this company.',
            'amc_source.max' => 'AMC reference may not be greater than 191 characters.',
        ]);

        $selectedClient = Client::query()->where('company_id', $companyId)->find($validated['client_id']);
        if (! $selectedClient) {
            throw ValidationException::withMessages([
                'client_id' => 'Selected client is not available for this company.',
            ]);
        }

        $customerContactNumber = filled($selectedClient->phone ?: $selectedClient->mobile)
            ? trim((string) ($selectedClient->phone ?: $selectedClient->mobile))
            : null;
        $customerName = trim((string) $selectedClient->name);
        $customerEmail = filled($selectedClient->email)
            ? Str::lower(trim((string) $selectedClient->email))
            : null;
        $customerAddress = filled($selectedClient->address)
            ? trim((string) $selectedClient->address)
            : null;
        $subject = trim((string) $validated['subject']);
        $description = trim((string) $validated['description']);
        $ticketType = (string) $validated['type'];
        $amcReference = filled($validated['amc_source'] ?? null)
            ? trim((string) $validated['amc_source'])
            : null;

        $selectedAmc = null;
        if ($amcReference !== null) {
            $selectedAmc = $this->resolveAmcReference($amcReference, $companyId);

            if (! $this->canGenerateSupportTicketFromAmc($selectedAmc)) {
                throw ValidationException::withMessages([
                    'amc_source' => 'Selected AMC contract is not allowed for support ticket generation.',
                ]);
            }
        }

        if (! $selectedClient && filled($customerContactNumber)) {
            $selectedClient = $this->findClientByPhoneNumber($companyId, $customerContactNumber);
        }

        $assignedInput = $request->input('assigned_to');
        $assignedUserIds = collect(is_array($assignedInput) ? $assignedInput : ($assignedInput ? [$assignedInput] : []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => in_array($id, $assigneeIds, true))
            ->unique()
            ->values()
            ->all();
        $primaryAssignedTo = $assignedUserIds[0] ?? null;

        $ticket = DB::transaction(function () use (
            $request,
            $user,
            $validated,
            $companyId,
            $customerContactNumber,
            $customerName,
            $customerEmail,
            $customerAddress,
            $selectedClient,
            $subject,
            $description,
            $ticketType,
            $selectedAmc,
            $amcSupportWorkflowService,
            $assignmentHistory,
            $assignedUserIds,
            $primaryAssignedTo
        ): Ticket {
            if ($selectedAmc) {
                $ticket = $amcSupportWorkflowService->generateSupportTicketForAmc(
                    $selectedAmc,
                    null,
                    $user,
                    $request->input('visit_date'),
                    $description
                );

                $updatePayload = [
                    'subject' => $subject,
                    'priority' => $validated['priority'],
                    'visit_date' => $validated['visit_date'],
                    'description' => $description,
                ];

                $updatePayload['assigned_to'] = $primaryAssignedTo;
                $updatePayload['customer_contact_number'] = $customerContactNumber;
                $updatePayload['customer_name'] = $customerName;
                $updatePayload['customer_email'] = $customerEmail;
                $updatePayload['customer_address'] = $customerAddress;

                $ticket->update($updatePayload);

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

                $selectedAmc->forceFill([
                    'support_ticket_id' => $ticket->id,
                ])->saveQuietly();

                return $ticket->fresh(['generatedFromAmc', 'amc', 'assignedUser', 'replies.user']);
            }

            $ticketNumber = 'TKT-' . now()->format('Ymd') . '-' . Str::upper(Str::random(6));

            $ticket = Ticket::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
                'project_id'=>$validated['project_id']??null,
                'assigned_to' => $primaryAssignedTo,
                'client_id' => $selectedClient->id,
                'ticket_number' => $ticketNumber,
                'customer_contact_number' => $customerContactNumber,
                'customer_name' => $customerName,
                'customer_email' => $customerEmail,
                'customer_address' => $customerAddress,
                'type' => $ticketType,
                'subject' => $subject,
                'priority' => $validated['priority'],
                'status' => 'open',
                'visit_date' => $validated['visit_date'],
                'description' => $description,
                'generated_from_amc_id' => null,
                'source_type' => null,
                'source_id' => null,
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

            return $ticket->fresh(['assignedUser', 'generatedFromAmc', 'amc', 'replies.user']);
        });

        return response()->json([
            'message' => 'Support ticket created successfully.',
            'data' => [
                'ticket' => $ticket,
                'amc' => $selectedAmc ? $selectedAmc->fresh(['client', 'project', 'supportTicket']) : null,
            ],
        ], 201);
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

    private function ticketAssigneeUserIds(int $companyId): array
    {
        return User::query()
            ->where('company_id', $companyId)
            ->where(function ($builder) {
                $builder->whereHas('staff')
                    ->orWhere(function ($companyAdminQuery) {
                        $companyAdminQuery
                            ->whereHas('roleRecord', function ($roleQuery) {
                                $roleQuery->whereIn('slug', ['company_admin', 'companyadmin']);
                            })
                            ->orWhereIn('role', ['company_admin', 'companyadmin']);
                    });
            })
            ->pluck('id')
            ->all();
    }

    private function canGenerateSupportTicketFromAmc(Amc $amc): bool
    {
        if ($amc->isOnCall() || $amc->isHybrid()) {
            return true;
        }

        if ($amc->generatesScheduledVisits()) {
            return (int) $amc->remaining_visits > 0 || (int) $amc->total_visits > 0;
        }

        return false;
    }

    private function findClientByPhoneNumber(int $companyId, string $phoneNumber): ?Client
    {
        $normalizedPhone = preg_replace('/\D+/', '', $phoneNumber);

        if ($normalizedPhone === '') {
            return null;
        }

        return Client::query()
            ->where('company_id', $companyId)
            ->get(['id', 'mobile', 'phone'])
            ->first(function (Client $client) use ($normalizedPhone) {
                $clientPhones = array_filter([
                    preg_replace('/\D+/', '', (string) $client->mobile),
                    preg_replace('/\D+/', '', (string) $client->phone),
                ]);

                return in_array($normalizedPhone, $clientPhones, true);
            });
    }

    private function requestInputData(Request $request): array
    {
        $data = $request->all();

        if ($data !== []) {
            return $data;
        }

        $content = trim((string) $request->getContent());

        if ($content === '') {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function validateJson(array $data, array $rules, array $messages = [], array $attributes = []): array
    {
        $validator = Validator::make($data, $rules, $messages, $attributes);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => collect($validator->errors())->flatten()->first(),
                'errors' => $validator->errors(),
            ], 422));
        }

        return $validator->validated();
    }
}
