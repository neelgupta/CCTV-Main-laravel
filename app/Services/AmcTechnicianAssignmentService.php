<?php

namespace App\Services;

use App\Mail\AmcVisitAssignedMail;
use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\AmcVisitAssignmentHistory;
use App\Models\DashboardNotification;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\Ticket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AmcTechnicianAssignmentService
{
    public function assignTechnicianIfRequired(AmcVisit $visit, ?Carbon $today = null, string $source = 'auto'): bool
    {
        $visit->loadMissing(['amc.client', 'amc.assignedStaff', 'amc.supportTicket']);
        $visit->refresh();

        $amc = $visit->amc;
        if (! $amc || ! $this->isVisitEligibleForAutomaticAssignment($visit, $today)) {
            return false;
        }

        $technicians = $this->assignableTechnicians((int) $amc->company_id);
        if ($technicians->isEmpty()) {
            return false;
        }

        $targetDate = $visit->visit_date?->copy()->startOfDay();
        if (! $targetDate) {
            return false;
        }

        $loads = $this->buildLoadMap((int) $amc->company_id, $targetDate, $technicians);
        $preferred = $this->preferredTechnicianForVisit($visit, $technicians);
        $technician = $this->pickTechnician($technicians, $loads, $visit, $preferred, $targetDate);

        if (! $technician) {
            return false;
        }

        $updatedVisit = $this->updateVisitTechnician($visit, $technician, null, 'Auto-assigned when visit became due', $source);
        $this->dispatchNotifications($updatedVisit->fresh(['amc']), $technician, $today ?: now(), $source);

        return true;
    }

    public function assignDueVisits(?Carbon $assignmentDate = null): array
    {
        $assignmentDate = ($assignmentDate ?: now())->copy()->startOfDay();
        $targetDate = $assignmentDate->copy()->addDays(2)->startOfDay();

        $visits = AmcVisit::query()
            ->with([
                'amc:id,company_id,client_id,customer_name,project_name,contract_no,mobile_number,email,address,start_date,end_date,assigned_staff_id',
            ])
            ->whereBetween('visit_date', [$assignmentDate->toDateString(), $targetDate->toDateString()])
            ->whereIn('status', ['pending', 'scheduled'])
            ->where(function ($query): void {
                $query->whereNull('technician')->orWhere('technician', '');
            })
            ->orderBy('amc_id')
            ->orderBy('visit_no')
            ->get();

        $grouped = $visits->groupBy(fn (AmcVisit $visit) => (int) $visit->amc?->company_id);
        $summary = [
            'target_date' => $targetDate->toDateString(),
            'assigned' => 0,
            'skipped' => 0,
            'companies' => [],
        ];

        foreach ($grouped as $companyId => $companyVisits) {
            if (! $companyId) {
                $summary['skipped'] += $companyVisits->count();
                continue;
            }

            $result = $this->assignCompanyDueVisits((int) $companyId, $assignmentDate, $companyVisits);
            $summary['assigned'] += $result['assigned'];
            $summary['skipped'] += $result['skipped'];
            $summary['companies'][] = $result;
        }

        return $summary;
    }

    public function updateVisitTechnician(AmcVisit $visit, ?Staff $staff, ?User $changedBy = null, ?string $reason = null, string $source = 'manual'): AmcVisit
    {
        return DB::transaction(function () use ($visit, $staff, $changedBy, $reason, $source): AmcVisit {
            $visit->refresh();
            $oldTechnician = $visit->technician;
            $newTechnician = $staff?->name;

            if ($oldTechnician === $newTechnician) {
                return $visit;
            }

            $visit->update([
                'technician' => $newTechnician,
            ]);

            AmcVisitAssignmentHistory::create([
                'amc_visit_id' => $visit->id,
                'old_technician_name' => $oldTechnician,
                'new_technician_name' => $newTechnician,
                'changed_by' => $changedBy?->id,
                'assignment_source' => $source,
                'reason' => $reason,
            ]);

            return $visit->refresh();
        });
    }

    public function workloadDashboard(int $companyId, ?Carbon $date = null): array
    {
        $date = ($date ?: now())->copy()->startOfDay();
        $upcomingWindowEnd = $date->copy()->addDays(14);

        $staff = $this->assignableTechnicians($companyId);
        $openVisits = AmcVisit::query()
            ->with(['amc:id,company_id,client_id,customer_name,contract_no'])
            ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
            ->whereIn('status', ['pending', 'scheduled'])
            ->whereNotNull('technician')
            ->get();

        $openTickets = Ticket::query()
            ->with(['assignedUser:id,name'])
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->get();

        return $staff->map(function (Staff $technician) use ($date, $upcomingWindowEnd, $openVisits, $openTickets): array {
            $todayVisits = $openVisits->filter(fn (AmcVisit $visit) => $visit->visit_date?->isSameDay($date) && $this->visitTechnicianMatchesStaff($visit, $technician))->count();
            $upcomingVisits = $openVisits->filter(fn (AmcVisit $visit) => $visit->visit_date && $visit->visit_date->betweenIncluded($date, $upcomingWindowEnd) && $this->visitTechnicianMatchesStaff($visit, $technician))->count();
            $openSupportTickets = $openTickets->filter(fn (Ticket $ticket) => (int) $ticket->assigned_to === (int) $technician->user_id)->count();
            $pendingAmc = $openVisits->filter(fn (AmcVisit $visit) => $this->visitTechnicianMatchesStaff($visit, $technician))->count();

            $totalLoad = $todayVisits + $upcomingVisits + $openSupportTickets + $pendingAmc;
            $tone = match (true) {
                $totalLoad >= max(1, $technician->amcDailyVisitLimit()) => 'red',
                $totalLoad >= max(1, (int) ceil($technician->amcDailyVisitLimit() * 0.7)) => 'orange',
                default => 'green',
            };

            return [
                'staff_id' => $technician->id,
                'staff_name' => $technician->name,
                'today_visits' => $todayVisits,
                'upcoming_visits' => $upcomingVisits,
                'open_support_tickets' => $openSupportTickets,
                'pending_amc' => $pendingAmc,
                'completed' => 0,
                'total_load' => $totalLoad,
                'tone' => $tone,
            'limit' => $technician->amcDailyVisitLimit(),
        ];
        })->values()->all();
    }

    public function ticketFormWorkloads(int $companyId, Carbon $visitDate, Collection $assignees): Collection
    {
        $visitDate = $visitDate->copy()->startOfDay();
        $visitDateString = $visitDate->toDateString();

        $activeTicketStatuses = ['open', 'pending', 'assigned', 'in_progress'];
        $activeVisitStatuses = ['pending', 'scheduled', 'assigned', 'in_progress', 'open'];

        $ticketLoads = collect();
        if (Schema::hasTable('ticket_user_assignments')) {
            $ticketLoads = DB::table('tickets as t')
                ->leftJoin('ticket_user_assignments as tua', 'tua.ticket_id', '=', 't.id')
                ->where('t.company_id', $companyId)
                ->whereDate('t.visit_date', $visitDateString)
                ->whereIn('t.status', $activeTicketStatuses)
                ->where(function ($query) {
                    $query->whereNotNull('tua.user_id')
                        ->orWhereNotNull('t.assigned_to');
                })
                ->selectRaw('COALESCE(tua.user_id, t.assigned_to) as user_id, COUNT(DISTINCT t.id) as aggregate')
                ->groupBy(DB::raw('COALESCE(tua.user_id, t.assigned_to)'))
                ->pluck('aggregate', 'user_id');
        } else {
            $ticketLoads = Ticket::query()
                ->where('company_id', $companyId)
                ->whereDate('visit_date', $visitDateString)
                ->whereIn('status', $activeTicketStatuses)
                ->whereNotNull('assigned_to')
                ->selectRaw('assigned_to, COUNT(*) as aggregate')
                ->groupBy('assigned_to')
                ->pluck('aggregate', 'assigned_to');
        }

        $visitLoads = AmcVisit::query()
            ->with('amc:id,company_id')
            ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
            ->whereDate('visit_date', $visitDateString)
            ->whereIn('status', $activeVisitStatuses)
            ->whereNotNull('technician')
            ->selectRaw('LOWER(TRIM(technician)) as technician_name, COUNT(*) as aggregate')
            ->groupBy(DB::raw('LOWER(TRIM(technician))'))
            ->pluck('aggregate', 'technician_name');

        return $assignees
            ->map(function (User $assignee) use ($ticketLoads, $visitLoads): array {
                $displayName = trim((string) ($assignee->staff?->name ?: $assignee->name));
                $normalisedName = Str::lower(Str::squish($displayName));

                $workload = (int) ($ticketLoads[$assignee->id] ?? 0);
                if ($normalisedName !== '') {
                    $workload += (int) ($visitLoads[$normalisedName] ?? 0);
                }

                return [
                    'id' => $assignee->id,
                    'name' => $assignee->name,
                    'label' => $assignee->name . ' (' . $workload . ' Jobs)',
                    'workload' => $workload,
                    'is_company_admin' => (bool) $assignee->isCompanyAdmin(),
                    'active_ticket_count' => (int) ($assignee->active_ticket_count ?? 0),
                ];
            })
            ->sortBy(function (array $item): string {
                return sprintf(
                    '%06d|%s|%010d',
                    (int) $item['workload'],
                    Str::lower(Str::squish((string) $item['name'])),
                    (int) $item['id']
                );
            })
            ->values();
    }

    private function assignCompanyDueVisits(int $companyId, Carbon $assignmentDate, Collection $companyVisits): array
    {
        return DB::transaction(function () use ($companyId, $assignmentDate, $companyVisits): array {
            $technicians = $this->assignableTechnicians($companyId);

            if ($technicians->isEmpty()) {
                return [
                    'company_id' => $companyId,
                    'assigned' => 0,
                    'skipped' => $companyVisits->count(),
                    'reason' => 'No available technicians.',
                ];
            }

            $assigned = 0;
            $skipped = 0;

            foreach ($companyVisits as $visit) {
                if (! $this->assignTechnicianForVisit($visit->refresh(), $technicians, $assignmentDate, 'scheduler')) {
                    $skipped++;
                    continue;
                }

                $assigned++;
            }

            return [
                'company_id' => $companyId,
                'assigned' => $assigned,
                'skipped' => $skipped,
                'technicians' => $technicians->count(),
            ];
        });
    }

    private function assignTechnicianForVisit(
        AmcVisit $visit,
        Collection $technicians,
        Carbon $assignmentDate,
        string $source
    ): bool {
        $visit->loadMissing(['amc.client', 'amc.assignedStaff', 'amc.supportTicket']);

        if (! $this->isVisitEligibleForAutomaticAssignment($visit, $assignmentDate)) {
            return false;
        }

        $targetDate = $visit->visit_date?->copy()->startOfDay();
        if (! $targetDate) {
            return false;
        }

        $loads = $this->buildLoadMap((int) $visit->amc->company_id, $targetDate, $technicians);
        $preferred = $this->preferredTechnicianForVisit($visit, $technicians);
        $technician = $this->pickTechnician($technicians, $loads, $visit, $preferred, $targetDate);

        if (! $technician) {
            return false;
        }

        $updatedVisit = $this->updateVisitTechnician($visit, $technician, null, 'Auto-assigned by scheduler', 'auto');
        $this->dispatchNotifications($updatedVisit->fresh(['amc']), $technician, $assignmentDate, $source);

        return true;
    }

    private function dispatchNotifications(AmcVisit $visit, Staff $technician, Carbon $assignmentDate, string $source): void
    {
        $user = $technician->user;

        if (! $user) {
            return;
        }

        $amc = $visit->amc;
        $title = 'AMC Visit Assigned';
        $message = trim(($amc?->contract_no ?: 'AMC') . ' | ' . ($amc?->customer_name ?: 'Customer'));

        DashboardNotification::updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_key' => 'amc-visit-assignment-' . $visit->id,
            ],
            [
                'company_id' => $user->company_id,
                'category' => 'amc_assignment',
                'source_type' => 'amc_visit',
                'source_id' => (string) $visit->id,
                'title' => $title,
                'message' => $message,
                'url' => $amc ? route('amcs.show', $amc) : route('amcs.index'),
                'notification_date' => $assignmentDate->toDateString(),
                'status' => 'unread',
                'payload' => [
                    'type' => 'amc_visit_assignment',
                    'visit_id' => $visit->id,
                    'amc_id' => $visit->amc_id,
                    'source' => $source,
                ],
            ]
        );

        try {
            if (filled($user->email)) {
                Mail::to($user->email)->send(new AmcVisitAssignedMail($visit->fresh(['amc']), $technician));
            }
        } catch (\Throwable $throwable) {
            report($throwable);
        }

        try {
            app(FirebasePushService::class)->sendToUser(
                $user,
                $title,
                $message,
                [
                    'type' => 'amc_visit_assignment',
                    'visit_id' => (string) $visit->id,
                    'amc_id' => (string) $visit->amc_id,
                ],
                $visit->amc ? route('amcs.show', $visit->amc) : route('amcs.index'),
                'amc-visit-' . $visit->id
            );
        } catch (\Throwable $throwable) {
            report($throwable);
        }
    }

    private function pickTechnician(Collection $technicians, array $loads, AmcVisit $visit, ?Staff $preferred, Carbon $targetDate): ?Staff
    {
        $eligible = $technicians->filter(fn (Staff $staff) => $this->isEligibleForDate($staff, $loads, $targetDate))->values();

        if ($eligible->isEmpty()) {
            return null;
        }

        if ($preferred) {
            $preferredMatch = $eligible->first(fn (Staff $staff) => $staff->id === $preferred->id);
            if ($preferredMatch) {
                return $preferredMatch;
            }
        }

        $ordered = $eligible->sortBy(function (Staff $technician) use ($loads): string {
            $load = $loads[$technician->id] ?? ['score' => 0, 'day_count' => 0];

            return sprintf(
                '%06d|%06d|%010d',
                (int) $load['score'],
                (int) $load['day_count'],
                (int) $technician->id
            );
        })->values();

        $lowestScore = (int) ($loads[$ordered->first()->id]['score'] ?? 0);
        $lowestDayCount = (int) ($loads[$ordered->first()->id]['day_count'] ?? 0);
        $tied = $ordered->filter(function (Staff $technician) use ($loads, $lowestScore, $lowestDayCount): bool {
            $load = $loads[$technician->id] ?? ['score' => 0, 'day_count' => 0];

            return (int) $load['score'] === $lowestScore && (int) $load['day_count'] === $lowestDayCount;
        })->values();

        if ($tied->count() === 1) {
            return $tied->first();
        }

        $seed = (int) ($visit->id + ($visit->visit_no ?? 0) + (int) $targetDate->day);
        $index = $seed % $tied->count();

        return $tied->get($index);
    }

    private function isEligibleForDate(Staff $staff, array $loads, Carbon $targetDate): bool
    {
        $load = $loads[$staff->id] ?? ['day_count' => 0];

        if ($load['day_count'] >= $staff->amcDailyVisitLimit()) {
            return false;
        }

        if (! $staff->user || ! $staff->user->is_active) {
            return false;
        }

        if ($staff->trashed()) {
            return false;
        }

        return ! StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->whereDate('date', $targetDate->toDateString())
            ->where('status', 'Leave')
            ->exists();
    }

    private function isVisitEligibleForAutomaticAssignment(AmcVisit $visit, ?Carbon $today = null): bool
    {
        $today = ($today ?: now())->copy()->startOfDay();
        $visitDate = $visit->visit_date?->copy()->startOfDay();

        if (! $visitDate) {
            return false;
        }

        if (filled($visit->technician)) {
            return false;
        }

        if (! in_array($visit->status, ['pending', 'scheduled'], true)) {
            return false;
        }

        $amc = $visit->relationLoaded('amc') ? $visit->amc : $visit->amc()->first();
        if (! $amc || $amc->end_date?->lt($today) || $amc->start_date?->gt($visitDate)) {
            return false;
        }

        if ($visitDate->lt($today)) {
            return false;
        }

        return (int) $today->diffInDays($visitDate, false) <= 2;
    }

    private function preferredTechnicianForVisit(AmcVisit $visit, Collection $technicians): ?Staff
    {
        $amc = $visit->amc;

        if (! $amc) {
            return null;
        }

        $preferredStaffIds = collect();

        if ($amc->assigned_staff_id) {
            $preferredStaffIds->push((int) $amc->assigned_staff_id);
        }

        if ($amc->client_id) {
            $preferredStaffIds = $preferredStaffIds->merge(
                Amc::query()
                    ->where('company_id', $amc->company_id)
                    ->where('client_id', $amc->client_id)
                    ->where('id', '!=', $amc->id)
                    ->whereNotNull('assigned_staff_id')
                    ->pluck('assigned_staff_id')
            );
        } elseif (filled($amc->customer_name)) {
            $preferredStaffIds = $preferredStaffIds->merge(
                Amc::query()
                    ->where('company_id', $amc->company_id)
                    ->where('customer_name', $amc->customer_name)
                    ->where('id', '!=', $amc->id)
                    ->whereNotNull('assigned_staff_id')
                    ->pluck('assigned_staff_id')
            );
        }

        if (filled($amc->mobile_number) || filled($amc->email)) {
            $ticketQuery = Ticket::query()
                ->where('company_id', $amc->company_id)
                ->whereNotNull('assigned_to')
                ->whereNotIn('status', ['resolved', 'closed']);

            $ticketQuery->where(function ($query) use ($amc): void {
                if (filled($amc->mobile_number)) {
                    $query->where('customer_contact_number', 'like', '%' . preg_replace('/\D+/', '', (string) $amc->mobile_number) . '%');
                }

                if (filled($amc->email)) {
                    $query->orWhereRaw('LOWER(TRIM(customer_email)) = ?', [Str::lower(Str::squish((string) $amc->email))]);
                }

                if (filled($amc->customer_name)) {
                    $query->orWhereRaw('LOWER(TRIM(customer_name)) = ?', [Str::lower(Str::squish((string) $amc->customer_name))]);
                }
            });

            $preferredStaffIds = $preferredStaffIds->merge(
                $ticketQuery->pluck('assigned_to')
            );
        }

        $preferredStaffIds = $preferredStaffIds->filter()->unique()->values();
        if ($preferredStaffIds->isEmpty()) {
            return null;
        }

        return $technicians->first(fn (Staff $staff) => $preferredStaffIds->contains((int) $staff->id));
    }

    private function buildLoadMap(int $companyId, Carbon $targetDate, Collection $technicians): array
    {
        $loadMap = [];

        foreach ($technicians as $technician) {
            $loadMap[$technician->id] = [
                'score' => 0,
                'day_count' => 0,
            ];
        }

        $visits = AmcVisit::query()
            ->with('amc:id,company_id')
            ->whereHas('amc', fn ($query) => $query->where('company_id', $companyId))
            ->whereIn('status', ['pending', 'scheduled'])
            ->whereNotNull('technician')
            ->get();

        foreach ($visits as $visit) {
            foreach ($technicians as $technician) {
                if (! $this->visitTechnicianMatchesStaff($visit, $technician)) {
                    continue;
                }

                $loadMap[$technician->id]['score']++;

                if ($visit->visit_date?->isSameDay($targetDate)) {
                    $loadMap[$technician->id]['day_count']++;
                }
            }
        }

        $tickets = Ticket::query()
            ->where('company_id', $companyId)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->get(['assigned_to']);

        foreach ($technicians as $technician) {
            $loadMap[$technician->id]['score'] += $tickets->where('assigned_to', $technician->user_id)->count();
        }

        return $loadMap;
    }

    private function visitTechnicianMatchesStaff(AmcVisit $visit, Staff $staff): bool
    {
        $visitTechnician = strtolower(trim((string) $visit->technician));
        $staffName = strtolower(trim((string) $staff->name));

        return $visitTechnician !== '' && $staffName !== '' && $visitTechnician === $staffName;
    }

    private function assignableTechnicians(int $companyId): EloquentCollection
    {
        return Staff::query()
            ->with(['user:id,name,email,is_active,role,role_id', 'role:id,name,slug'])
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereHas('user', fn ($query) => $query->where('is_active', true))
            ->get()
            ->filter(fn (Staff $staff) => $staff->isAmcTechnicianCandidate())
            ->values();
    }
}
