<?php

namespace App\Services;

use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\AmcVisitHistory;
use App\Models\Client;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketAssignmentHistoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AmcSupportWorkflowService
{
    public function seedVisitSlots(Amc $amc): void
    {
        if (! $amc->generatesScheduledVisits()) {
            $amc->forceFill([
                'scheduled_visits' => 0,
                'remaining_visits' => (int) $amc->total_visits,
            ])->saveQuietly();

            return;
        }

        $expectedCount = max(0, (int) $amc->total_visits);

        for ($visitNo = 1; $visitNo <= $expectedCount; $visitNo++) {
            $amc->visits()->firstOrCreate(
                ['visit_no' => $visitNo],
                [
                    'visit_date' => null,
                    'status' => 'pending',
                    'visit_type' => $amc->service_mode ?: 'scheduled',
                ]
            );
        }

        $this->refreshCounters($amc);
    }

    public function ensureCurrentSupportTicketForAmc(Amc $amc, ?User $user = null): Ticket
    {
        $currentTicket = $this->findCurrentSupportTicketForAmc($amc);

        if ($currentTicket) {
            if ($amc->support_ticket_id !== $currentTicket->id) {
                $amc->forceFill([
                    'support_ticket_id' => $currentTicket->id,
                ])->saveQuietly();
            }

            return $currentTicket;
        }

        return $this->generateSupportTicketForAmc($amc, null, $user);
    }

    public function refreshCounters(Amc $amc): void
    {
        $scheduledVisits = $amc->visits()
            ->whereNotNull('visit_date')
            ->count();

        $amc->forceFill([
            'scheduled_visits' => $scheduledVisits,
            'remaining_visits' => max(0, (int) $amc->total_visits - $scheduledVisits),
        ])->saveQuietly();
    }

    public function scheduleVisit(
        Amc $amc,
        AmcVisit $visit,
        array $payload,
        ?User $user = null
    ): AmcVisit {
        abort_unless((int) $visit->amc_id === (int) $amc->id, 404);

        $previousVisitDate = $visit->visit_date?->toDateString();
        $newVisitDate = isset($payload['visit_date']) && filled($payload['visit_date'])
            ? Carbon::parse($payload['visit_date'])->startOfDay()
            : null;
        $nextStatus = $payload['status'] ?? $visit->status ?? 'pending';

        if ($newVisitDate !== null) {
            $this->validateVisitDateForAmc($amc, $visit, $newVisitDate);

            if (! in_array($nextStatus, ['completed', 'cancelled'], true)) {
                $nextStatus = 'scheduled';
            }
        }

        DB::transaction(function () use ($amc, $visit, $payload, $user, $previousVisitDate, $newVisitDate): void {
            $visit->update([
                'visit_date' => $newVisitDate?->toDateString(),
                'status' => $payload['status'] ?? $visit->status ?? 'pending',
                'visit_type' => $payload['visit_type'] ?? $visit->visit_type,
                'technician' => array_key_exists('technician', $payload)
                    ? (filled($payload['technician']) ? Str::limit(trim((string) $payload['technician']), 191, '') : null)
                    : $visit->technician,
                'support_ticket_id' => $payload['support_ticket_id'] ?? $visit->support_ticket_id,
            ]);

            if ($previousVisitDate !== $newVisitDate?->toDateString()) {
                AmcVisitHistory::create([
                    'amc_visit_id' => $visit->id,
                    'changed_by' => $user?->id,
                    'previous_visit_date' => $previousVisitDate,
                    'new_visit_date' => $newVisitDate?->toDateString(),
                    'reason' => $payload['reason'] ?? null,
                    'notes' => $payload['notes'] ?? null,
                ]);
            }
        });

        $visit->refresh();
        if ($newVisitDate !== null && ! in_array(strtolower((string) $visit->status), ['completed', 'cancelled'], true)) {
            $visit->forceFill([
                'status' => $nextStatus,
            ])->saveQuietly();
        }
        $this->refreshCounters($amc->fresh(['visits']));

        return $visit;
    }

    public function supportTicketContextForAmc(Amc $amc, ?AmcVisit $visit = null, ?string $visitDate = null): array
    {
        $supportVisit = $this->resolveSupportVisitForCreation($amc, $visit);

        return [
            'visit' => $supportVisit,
            'visit_date' => $this->resolveSupportVisitDate($supportVisit, $amc, $visitDate),
            'assigned_to' => $this->resolveSupportTicketAssignee($amc, $supportVisit),
        ];
    }

    public function advanceSupportWorkflowForTicket(Ticket $ticket, ?User $user = null): ?Ticket
    {
        if (! $this->isAmcSupportTicket($ticket)) {
            return null;
        }

        if (! in_array($ticket->status, ['closed', 'resolved'], true)) {
            return null;
        }

        $amc = $ticket->generatedFromAmc ?: $ticket->amc?->fresh(['visits', 'assignedStaff', 'client']);

        if (! $amc) {
            return null;
        }

        $this->completeRelatedAmcVisitForTicket($ticket, $amc, $user);

        $currentVisitNo = (int) ($ticket->visit_number ?? 0);

        if ($currentVisitNo <= 0 && $ticket->source_type === 'amc_visit' && $ticket->source_id) {
            $currentVisitNo = (int) AmcVisit::query()->whereKey($ticket->source_id)->value('visit_no');
        }

        $nextVisit = $this->nextSupportVisitForAmc($amc, $currentVisitNo > 0 ? $currentVisitNo : null);

        if (! $nextVisit) {
            return null;
        }

        $nextVisitDate = $this->resolveAutoGeneratedSupportVisitDate($amc, $nextVisit, $ticket);
        $nextTicket = $this->generateSupportTicketForAmc($amc, $nextVisit, $user, $nextVisitDate);

        if ($amc->support_ticket_id !== $nextTicket->id) {
            $amc->forceFill([
                'support_ticket_id' => $nextTicket->id,
            ])->saveQuietly();
        }

        return $nextTicket;
    }

    private function validateVisitDateForAmc(Amc $amc, AmcVisit $visit, Carbon $visitDate): void
    {
        $candidateDate = $visitDate->copy()->startOfDay();
        $startDate = $amc->start_date?->copy()->startOfDay() ?? Carbon::parse($amc->start_date)->startOfDay();
        $endDate = $amc->end_date?->copy()->startOfDay() ?? Carbon::parse($amc->end_date)->startOfDay();

        if ($candidateDate->lt($startDate)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'visit_date' => 'Visit date cannot be before the AMC start date.',
            ]);
        }

        if ($candidateDate->gt($endDate)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'visit_date' => 'Visit date cannot be after the AMC end date.',
            ]);
        }

        if (! $visit->visit_date && (int) $amc->scheduled_visits >= (int) $amc->total_visits) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'visit_date' => 'All purchased visits are already scheduled for this AMC.',
            ]);
        }

        $conflictingVisit = AmcVisit::query()
            ->where('amc_id', $amc->id)
            ->where('id', '!=', $visit->id)
            ->whereDate('visit_date', $candidateDate->toDateString())
            ->first();

        if ($conflictingVisit) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'visit_date' => 'Another AMC visit is already scheduled for this date.',
            ]);
        }
    }

    public function generateSupportTicketForAmc(
        Amc $amc,
        ?AmcVisit $visit = null,
        ?User $user = null,
        ?string $visitDate = null,
        ?string $description = null
    ): Ticket
    {
        $existingTicket = $this->findExistingSupportTicketForAmc($amc, $visit);

        if ($existingTicket) {
            return $existingTicket;
        }

        $supportVisit = $this->resolveSupportVisitForCreation($amc, $visit);
        $context = $this->supportTicketContextForAmc($amc, $supportVisit, $visitDate);
        $context['description'] = filled($description)
            ? trim((string) $description)
            : null;

        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return DB::transaction(function () use ($amc, $supportVisit, $user, $context): Ticket {
                    $ticket = $this->createSupportTicketRecord($amc, $supportVisit, $user, $context);

                    if ($supportVisit) {
                        $this->syncSupportVisitForTicket($supportVisit, $ticket, $context);
                    } elseif (! $amc->support_ticket_id) {
                        $amc->forceFill([
                            'support_ticket_id' => $ticket->id,
                        ])->saveQuietly();
                    }

                    return $ticket;
                });
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 3) {
                    throw $exception;
                }

                usleep(50000);
            }
        }

        throw new \RuntimeException('Unable to generate a unique AMC support ticket number.');
    }

    public function syncSupportTicketForAmc(
        Amc $amc,
        ?AmcVisit $visit = null,
        ?User $user = null,
        ?string $visitDate = null,
        ?string $description = null
    ): ?Ticket
    {
        if ($visit) {
            $ticket = $this->generateSupportTicketForAmc($amc, $visit, $user, $visitDate, $description);
            $context = $this->supportTicketContextForAmc($amc, $visit, $visitDate);

            if ($amc->support_ticket_id !== $ticket->id) {
                $amc->forceFill([
                    'support_ticket_id' => $ticket->id,
                ])->saveQuietly();
            }
        } else {
            $ticket = $this->findCurrentSupportTicketForAmc($amc);

            if (! $ticket) {
                return null;
            }

            $context = $this->supportTicketContextForExistingTicket($amc, $ticket, $visitDate);
        }
        $updates = [];

        if (($ticket->visit_date?->toDateString() ?? null) !== $context['visit_date']) {
            $updates['visit_date'] = $context['visit_date'];
        }

        if ((int) ($ticket->assigned_to ?? 0) !== (int) ($context['assigned_to'] ?? 0)) {
            $updates['assigned_to'] = $context['assigned_to'];
        }

        if (filled($description)) {
            $updates['description'] = trim((string) $description);
        }

        if ($updates !== []) {
            $ticket->update($updates);
        }

        if ($context['visit'] instanceof AmcVisit) {
            $this->syncSupportVisitForTicket($context['visit'], $ticket, $context);
        }

        return $ticket->refresh();
    }

    public function findCurrentSupportTicketForAmc(Amc $amc): ?Ticket
    {
        $activeTicket = Ticket::query()
            ->where('company_id', $amc->company_id)
            ->where('generated_from_amc_id', $amc->id)
            ->whereNotIn('status', ['closed', 'resolved'])
            ->orderByDesc('id')
            ->first();

        if ($activeTicket) {
            return $activeTicket;
        }

        if ($amc->support_ticket_id) {
            $legacyTicket = Ticket::query()->find($amc->support_ticket_id);

            if ($legacyTicket && ! in_array($legacyTicket->status, ['closed', 'resolved'], true)) {
                return $legacyTicket;
            }
        }

        return null;
    }

    private function generateAmcSupportTicketNumber(): string
    {
        $prefix = 'TKT-' . now()->format('Ymd') . '-';

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $ticketNumber = $prefix . Str::upper(Str::random(6));

            if (! Ticket::query()->where('ticket_number', $ticketNumber)->exists()) {
                return $ticketNumber;
            }
        }

        throw new \RuntimeException('Unable to generate a unique AMC support ticket number.');
    }

    public function findExistingSupportTicketForAmc(Amc $amc, ?AmcVisit $visit = null): ?Ticket
    {
        if ($visit) {
            if ($visit->support_ticket_id && $visit->relationLoaded('supportTicket')) {
                return $visit->supportTicket;
            }

            if ($visit->support_ticket_id) {
                return Ticket::query()->find($visit->support_ticket_id);
            }
        }

        $currentTicket = Ticket::query()
            ->where('company_id', $amc->company_id)
            ->where('generated_from_amc_id', $amc->id)
            ->whereNotIn('status', ['closed', 'resolved'])
            ->latest('id')
            ->first();

        if ($currentTicket) {
            return $currentTicket;
        }

        if ($amc->support_ticket_id && $amc->relationLoaded('supportTicket')) {
            return in_array($amc->supportTicket->status, ['closed', 'resolved'], true)
                ? null
                : $amc->supportTicket;
        }

        if ($amc->support_ticket_id) {
            $legacyAmcTicket = Ticket::query()->find($amc->support_ticket_id);

            if ($legacyAmcTicket && ! in_array($legacyAmcTicket->status, ['closed', 'resolved'], true)) {
                return $legacyAmcTicket;
            }
        }

        return null;
    }

    private function createSupportTicketRecord(Amc $amc, ?AmcVisit $visit, ?User $user, array $context): Ticket
    {
        $supportVisit = $context['visit'];
        $sourceType = $supportVisit ? 'amc_visit' : 'amc';
        $subject = $supportVisit
            ? Str::limit('AMC Visit #' . $supportVisit->visit_no . ' - ' . $amc->project_name, 191, '')
            : Str::limit('AMC Support Request - ' . $amc->project_name, 191, '');

        $ticket = Ticket::create([
            'company_id' => $amc->company_id,
            'user_id' => $user?->id ?: $amc->created_by,
            'assigned_to' => $context['assigned_to'],
            'ticket_number' => $this->generateAmcSupportTicketNumber(),
            'customer_contact_number' => $amc->mobile_number,
            'customer_name' => $amc->customer_name,
            'customer_email' => $amc->email,
            'customer_address' => $amc->address,
            'type' => 'support',
            'subject' => $subject,
            'priority' => 'medium',
            'status' => 'open',
            'visit_date' => $context['visit_date'],
            'description' => filled($context['description'] ?? null)
                ? trim((string) $context['description'])
                : ($supportVisit
                    ? 'Auto-generated from AMC visit #' . $supportVisit->visit_no . '.'
                    : 'Auto-generated from AMC support workflow.'),
            'generated_from_amc_id' => $amc->id,
            'source_type' => $sourceType,
            'source_id' => null,
            'visit_number' => $supportVisit?->visit_no,
            'visit_type' => $supportVisit?->visit_type ?: ($amc->service_mode ?: 'scheduled'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($ticket->assigned_to) {
            app(TicketAssignmentHistoryService::class)->syncCurrentAssignment(
                $ticket,
                (int) $ticket->assigned_to,
                $user,
                'Initial'
            );
        }

        return $ticket;
    }

    private function resolveSupportVisitForCreation(Amc $amc, ?AmcVisit $visit = null): ?AmcVisit
    {
        if ($visit) {
            return $visit;
        }

        $visits = $amc->relationLoaded('visits')
            ? $amc->visits
            : $amc->visits()->orderBy('visit_no')->get(['id', 'amc_id', 'visit_no', 'visit_date', 'status', 'technician', 'support_ticket_id']);

        if ($visits->isEmpty()) {
            return null;
        }

        return $visits->first(fn (AmcVisit $visit) => blank($visit->support_ticket_id))
            ?: $visits->sortBy('visit_no')->first();
    }

    private function nextSupportVisitForAmc(Amc $amc, ?int $afterVisitNo = null): ?AmcVisit
    {
        $visits = $amc->relationLoaded('visits')
            ? $amc->visits
            : $amc->visits()->orderBy('visit_no')->get(['id', 'amc_id', 'visit_no', 'visit_date', 'status', 'technician', 'support_ticket_id']);

        if ($visits->isEmpty()) {
            return null;
        }

        $orderedVisits = $visits->sortBy('visit_no')->values();

        if ($afterVisitNo === null) {
            return $orderedVisits->first(fn (AmcVisit $visit) => blank($visit->support_ticket_id))
                ?: $orderedVisits->first();
        }

        return $orderedVisits->first(function (AmcVisit $visit) use ($afterVisitNo) {
            return (int) $visit->visit_no > $afterVisitNo;
        });
    }

    private function visitForTicket(Ticket $ticket, Amc $amc): ?AmcVisit
    {
        if ($ticket->visit_number) {
            $visit = $amc->relationLoaded('visits')
                ? $amc->visits->firstWhere('visit_no', (int) $ticket->visit_number)
                : $amc->visits()->where('visit_no', (int) $ticket->visit_number)->first();

            if ($visit) {
                return $visit;
            }
        }

        if ($ticket->source_type === 'amc_visit' && $ticket->source_id) {
            return $amc->relationLoaded('visits')
                ? $amc->visits->firstWhere('id', (int) $ticket->source_id)
                : $amc->visits()->whereKey((int) $ticket->source_id)->first();
        }

        return null;
    }

    private function completeRelatedAmcVisitForTicket(Ticket $ticket, Amc $amc, ?User $user = null): void
    {
        $visit = $this->visitForTicket($ticket, $amc);

        if (! $visit) {
            return;
        }

        if (strtolower((string) $visit->status) === 'completed') {
            return;
        }

        $visit->forceFill([
            'status' => 'completed',
        ])->saveQuietly();

        $this->refreshCounters($amc->fresh(['visits']));
    }

    private function syncSupportVisitForTicket(AmcVisit $visit, Ticket $ticket, array $context): void
    {
        $visitDate = $context['visit_date'] ?? $ticket->visit_date?->toDateString() ?? $visit->visit_date?->toDateString();
        $nextStatus = strtolower((string) ($visit->status ?? ''));

        if ($visitDate) {
            $nextStatus = in_array($nextStatus, ['completed', 'cancelled'], true)
                ? $nextStatus
                : 'scheduled';
        }

        $visit->forceFill([
            'support_ticket_id' => $ticket->id,
            'visit_date' => $visitDate,
            'status' => $nextStatus !== '' ? $nextStatus : ($visitDate ? 'scheduled' : ($visit->status ?: 'pending')),
            'visit_type' => $context['visit']->visit_type ?? $ticket->visit_type ?? $visit->visit_type,
        ])->saveQuietly();

        $this->refreshCounters($visit->amc()->first() ?? $visit->amc);
    }

    private function supportTicketContextForExistingTicket(Amc $amc, Ticket $ticket, ?string $visitDate = null): array
    {
        $supportVisit = $this->visitForTicket($ticket, $amc);

        return [
            'visit' => $supportVisit,
            'visit_date' => $this->resolveSupportVisitDate($supportVisit, $amc, $visitDate, $ticket->visit_date?->toDateString()),
            'assigned_to' => $this->resolveSupportTicketAssignee($amc, $supportVisit),
        ];
    }

    private function resolveSupportVisitDate(
        ?AmcVisit $visit,
        Amc $amc,
        ?string $visitDate = null,
        ?string $fallbackDate = null
    ): ?string {
        if (filled($visitDate)) {
            return Carbon::parse($visitDate)->toDateString();
        }

        if (filled($fallbackDate)) {
            return Carbon::parse($fallbackDate)->toDateString();
        }

        if ($visit?->visit_date) {
            return $visit->visit_date->toDateString();
        }

        if ($amc->isOnCall()) {
            return now()->toDateString();
        }

        return $amc->start_date?->toDateString() ?? now()->toDateString();
    }

    private function resolveAutoGeneratedSupportVisitDate(Amc $amc, ?AmcVisit $nextVisit, ?Ticket $currentTicket = null): ?string
    {
        if ($nextVisit?->visit_date) {
            return $nextVisit->visit_date->toDateString();
        }

        $anchorDate = $currentTicket?->visit_date
            ?? $this->latestSupportActivityDate($amc, $currentTicket)
            ?? $amc->start_date
            ?? now()->startOfDay();

        $anchorDate = $anchorDate instanceof Carbon
            ? $anchorDate->copy()->startOfDay()
            : Carbon::parse($anchorDate)->startOfDay();

        if ($amc->isOnCall()) {
            return $anchorDate->copy()->addDay()->toDateString();
        }

        return $anchorDate
            ->copy()
            ->addMonthsNoOverflow($this->supportVisitIntervalMonths($amc))
            ->toDateString();
    }

    private function latestSupportActivityDate(Amc $amc, ?Ticket $excludeTicket = null): ?Carbon
    {
        $visitDates = $amc->relationLoaded('visits')
            ? $amc->visits
            : $amc->visits()->get(['id', 'amc_id', 'visit_no', 'visit_date']);

        $latestVisitDate = $visitDates
            ->filter(fn (AmcVisit $visit) => $visit->visit_date)
            ->sortByDesc(fn (AmcVisit $visit) => $visit->visit_date?->timestamp ?? 0)
            ->first()?->visit_date;

        if ($latestVisitDate) {
            return $latestVisitDate;
        }

        if ($excludeTicket?->visit_date) {
            return $excludeTicket->visit_date;
        }

        $supportTicket = $amc->relationLoaded('supportTicket')
            ? $amc->supportTicket
            : $amc->supportTicket()->first(['id', 'visit_date', 'status']);

        if ($supportTicket?->visit_date) {
            return $supportTicket->visit_date;
        }

        return null;
    }

    private function supportVisitIntervalMonths(Amc $amc): int
    {
        if (in_array($amc->visit_frequency, array_keys(Amc::VISIT_FREQUENCY_OPTIONS), true)) {
            return max(1, $amc->visitIntervalMonths());
        }

        $durationMonths = (int) ($amc->plan_duration_months ?: $amc->amcPlan?->duration_months ?: 0);
        $visitCount = max(1, (int) ($amc->total_visits ?: $amc->amcPlan?->visit_count ?: 1));

        if ($durationMonths > 0) {
            return max(1, (int) ceil($durationMonths / $visitCount));
        }

        return 1;
    }

    private function isAmcSupportTicket(Ticket $ticket): bool
    {
        if ($ticket->generated_from_amc_id) {
            return true;
        }

        if (in_array($ticket->source_type, ['amc', 'amc_visit'], true)) {
            return true;
        }

        return (bool) $ticket->amc()->exists();
    }

    private function resolveSupportTicketAssignee(Amc $amc, ?AmcVisit $visit = null): ?int
    {
        $technicianName = trim((string) ($visit?->technician ?? ''));

        if ($technicianName !== '') {
            $matchedStaff = Staff::query()
                ->where('company_id', $amc->company_id)
                ->whereRaw('LOWER(TRIM(name)) = ?', [Str::lower(Str::squish($technicianName))])
                ->first();

            if ($matchedStaff?->user_id) {
                return (int) $matchedStaff->user_id;
            }
        }

        return $amc->assignedStaff?->user_id ? (int) $amc->assignedStaff->user_id : null;
    }

    public function detectRepeatIssue(
        int $companyId,
        string $customerName,
        ?string $customerEmail,
        ?string $customerContactNumber,
        string $subject
    ): ?array {
        $normalizedName = Str::squish($customerName);
        $normalizedEmail = filled($customerEmail) ? Str::lower(Str::squish($customerEmail)) : null;
        $normalizedContact = preg_replace('/\D+/', '', (string) $customerContactNumber) ?: null;
        $normalizedSubject = $this->normalizeIssueSignature($subject);

        if ($normalizedName === '' || $normalizedSubject === '') {
            return null;
        }

        $ticket = Ticket::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['closed', 'resolved'])
            ->where(function (Builder $query) use ($normalizedName, $normalizedEmail, $normalizedContact, $normalizedSubject): void {
                $query->where(function (Builder $identity) use ($normalizedName, $normalizedEmail, $normalizedContact): void {
                    $identity->whereRaw('LOWER(TRIM(customer_name)) = ?', [Str::lower($normalizedName)])
                        ->when($normalizedEmail, fn (Builder $builder) => $builder->orWhereRaw('LOWER(TRIM(customer_email)) = ?', [$normalizedEmail]))
                        ->when($normalizedContact, fn (Builder $builder) => $builder->orWhere('customer_contact_number', 'like', '%' . $normalizedContact . '%'));
                })->whereRaw('LOWER(REPLACE(REPLACE(REPLACE(subject, ".", ""), ",", ""), "-", "")) = ?', [$normalizedSubject]);
            })
            ->orderByDesc('updated_at')
            ->first();

        if (! $ticket) {
            return null;
        }

        $repeatedAfterDays = $ticket->updated_at
            ? max(0, (int) $ticket->updated_at->diffInDays(now()))
            : null;

        return [
            'parent_ticket' => $ticket,
            'repeat_count' => ((int) $ticket->repeat_count) + 1,
            'repeated_after_days' => $repeatedAfterDays,
            'assigned_previous_staff_id' => $ticket->assigned_to,
            'reason' => 'Matched by customer identity and issue subject.',
        ];
    }

    public function staffTicketCounts(int $companyId): array
    {
        return Ticket::query()
            ->where('company_id', $companyId)
            ->whereIn('status', ['open', 'pending', 'assigned', 'in_progress'])
            ->selectRaw('assigned_to, COUNT(*) as aggregate')
            ->groupBy('assigned_to')
            ->pluck('aggregate', 'assigned_to')
            ->mapWithKeys(fn ($count, $assignedTo) => [(int) $assignedTo => (int) $count])
            ->all();
    }

    public function staffPerformanceSummary(int $companyId, int $staffUserId): array
    {
        $assignedTickets = Ticket::query()
            ->where('company_id', $companyId)
            ->where('assigned_to', $staffUserId);

        $totalAssigned = (clone $assignedTickets)->count();
        $completed = (clone $assignedTickets)->whereIn('status', ['closed', 'resolved'])->count();
        $pending = (clone $assignedTickets)->whereIn('status', ['open', 'pending', 'assigned', 'in_progress'])->count();
        $repeatComplaints = (int) (clone $assignedTickets)->sum('repeat_count');
        $avgResolutionDays = (float) ((clone $assignedTickets)
            ->whereIn('status', ['closed', 'resolved'])
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, created_at, updated_at)) as aggregate')
            ->value('aggregate') ?? 0);

        return [
            'total_assigned' => $totalAssigned,
            'completed' => $completed,
            'pending' => $pending,
            'repeat_complaints' => $repeatComplaints,
            'average_resolution_days' => round($avgResolutionDays, 2),
            'completion_percentage' => $totalAssigned > 0 ? round(($completed / $totalAssigned) * 100, 2) : 0,
            'repeat_percentage' => $totalAssigned > 0 ? round(($repeatComplaints / $totalAssigned) * 100, 2) : 0,
        ];
    }

    public function clientTicketStatistics(int $companyId, string $customerName, ?string $customerEmail = null, ?string $customerContactNumber = null): array
    {
        $query = Ticket::query()
            ->where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(customer_name)) = ?', [Str::lower(Str::squish($customerName))]);

        if (filled($customerEmail)) {
            $query->orWhereRaw('LOWER(TRIM(customer_email)) = ?', [Str::lower(Str::squish($customerEmail))]);
        }

        if (filled($customerContactNumber)) {
            $query->orWhere('customer_contact_number', 'like', '%' . preg_replace('/\D+/', '', $customerContactNumber) . '%');
        }

        $tickets = $query->get(['id', 'status', 'created_at', 'updated_at', 'parent_ticket_id', 'repeat_count']);

        return [
            'total' => $tickets->count(),
            'open' => $tickets->whereIn('status', ['open', 'pending', 'assigned', 'in_progress'])->count(),
            'closed' => $tickets->whereIn('status', ['closed', 'resolved'])->count(),
            'repeat_count' => (int) $tickets->sum('repeat_count'),
            'average_resolution_days' => $tickets->whereIn('status', ['closed', 'resolved'])->count() > 0
                ? round(
                    $tickets->whereIn('status', ['closed', 'resolved'])
                        ->avg(fn ($ticket) => $ticket->created_at && $ticket->updated_at
                            ? $ticket->created_at->diffInDays($ticket->updated_at)
                            : 0
                        ),
                    2
                )
                : 0,
            'last_ticket' => $tickets->sortByDesc('created_at')->first(),
        ];
    }

    private function normalizeIssueSignature(string $subject): string
    {
        return Str::lower(
            preg_replace('/[^a-z0-9]+/i', '', Str::squish($subject)) ?: ''
        );
    }
}
