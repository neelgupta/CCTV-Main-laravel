<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\LeadActivity;
use App\Models\LeadAppointment;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    /**
     * Ensure current user belongs to a company with the appointment module enabled.
     */
    private function checkCompanyModuleAccess(): void
    {
        $user = auth()->user();
        if (! $user) {
            abort(401);
        }

        if ($user->isSuperAdmin()) {
            return;
        }

        $company = $user->company;
        if (! $company || ! (bool) $company->allow_appointment_module) {
            abort(403, 'Appointment module is not enabled for your company. Please contact superadmin.');
        }
    }

    /**
     * Display the appointment module slots view.
     */
    public function index(Request $request)
    {
        $this->checkCompanyModuleAccess();

        $user = auth()->user();
        $companyId = $user->company_id;

        $selectedDate = $request->query('date', now()->toDateString());
        try {
            $parsedDate = Carbon::parse($selectedDate)->startOfDay();
        } catch (\Throwable $e) {
            $parsedDate = now()->startOfDay();
            $selectedDate = $parsedDate->toDateString();
        }

        // Available defined slots (9 AM to 8 PM, excluding lunch 2 PM - 3 PM)
        $definedSlots = LeadAppointment::definedSlots();
        $now = now();
        $today = now()->startOfDay();
        $isPastDate = $parsedDate->lt($today);

        // Fetch appointments on this date for the company
        $appointments = LeadAppointment::query()
            ->where('company_id', $companyId)
            ->whereDate('appointment_date', $selectedDate)
            ->with(['lead.status', 'assignee', 'creator'])
            ->get();

        $slotsData = [];
        $totalAppointmentsToday = 0;

        foreach ($definedSlots as $slot) {
            $slotStartDateTime = Carbon::parse($selectedDate . ' ' . $slot['start_time']);
            $isPastSlot = $isPastDate || ($parsedDate->isSameDay($today) && $now->gte($slotStartDateTime));

            // Matching appointments in this slot
            $slotAppointments = $appointments->filter(function ($apt) use ($slot) {
                return $apt->slot_start_time === $slot['start_time'] ||
                       substr((string) $apt->slot_start_time, 0, 5) === $slot['start_h_i'];
            })->values();

            $leadsCount = $slotAppointments->count();
            $totalAppointmentsToday += $leadsCount;
            $maxLeads = LeadAppointment::MAX_LEADS_PER_SLOT;
            $remainingSeats = max(0, $maxLeads - $leadsCount);
            $isFull = $leadsCount >= $maxLeads;

            $slotsData[] = [
                'slot' => $slot,
                'is_past' => $isPastSlot,
                'is_full' => $isFull,
                'max_leads' => $maxLeads,
                'leads_count' => $leadsCount,
                'remaining_seats' => $remainingSeats,
                'appointments' => $slotAppointments,
            ];
        }

        // Exclude Won and Lost/Loss stages leads from appointment booking list
        $excludedStatusIds = LeadStatus::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(TRIM(name)) in (?, ?, ?)', ['won', 'lost', 'loss'])
                    ->orWhereRaw('LOWER(name) like ?', ['%won%'])
                    ->orWhereRaw('LOWER(name) like ?', ['%lost%'])
                    ->orWhereRaw('LOWER(name) like ?', ['%loss%']);
            })
            ->pluck('id');

        $availableLeads = Lead::query()
            ->where('company_id', $companyId)
            ->where(function ($query) use ($excludedStatusIds) {
                $query->whereNull('lead_status_id')
                    ->orWhereNotIn('lead_status_id', $excludedStatusIds);
            })
            ->accessibleTo($user)
            ->orderBy('client_name')
            ->get(['id', 'client_name', 'mobile', 'email', 'created_at']);

        // Company staff for assignment
        $staffUsers = User::query()
            ->where('company_id', $companyId)
            ->activeAssigneeOrAdmin()
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        // Available lead stages/statuses in logged in company
        $leadStatuses = LeadStatus::query()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->get(['id', 'name', 'color']);

        return view('appointments.index', [
            'selectedDate' => $selectedDate,
            'parsedDate' => $parsedDate,
            'isPastDate' => $isPastDate,
            'slotsData' => $slotsData,
            'totalAppointmentsToday' => $totalAppointmentsToday,
            'availableLeads' => $availableLeads,
            'staffUsers' => $staffUsers,
            'leadStatuses' => $leadStatuses,
            'definedSlots' => $definedSlots,
        ]);
    }

    /**
     * API/AJAX endpoint to get slot availability for a given date.
     */
    public function getSlotsAvailability(Request $request)
    {
        $this->checkCompanyModuleAccess();

        $companyId = auth()->user()->company_id;
        $dateStr = $request->query('date', now()->toDateString());

        try {
            $date = Carbon::parse($dateStr)->startOfDay();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid date format'], 422);
        }

        $now = now();
        $today = now()->startOfDay();
        $isPastDate = $date->lt($today);

        $counts = LeadAppointment::query()
            ->where('company_id', $companyId)
            ->whereDate('appointment_date', $date->toDateString())
            ->where('status', '!=', 'cancelled')
            ->selectRaw('slot_start_time, COUNT(*) as lead_count')
            ->groupBy('slot_start_time')
            ->pluck('lead_count', 'slot_start_time')
            ->all();

        $slots = [];
        foreach (LeadAppointment::definedSlots() as $slotDef) {
            $slotStartDateTime = Carbon::parse($date->toDateString() . ' ' . $slotDef['start_time']);
            $isPastSlot = $isPastDate || ($date->isSameDay($today) && $now->gte($slotStartDateTime));

            // Check count
            $count = $counts[$slotDef['start_time']]
                ?? $counts[substr($slotDef['start_time'], 0, 5)]
                ?? 0;

            $max = LeadAppointment::MAX_LEADS_PER_SLOT;
            $isFull = $count >= $max;
            $isSelectable = (! $isPastSlot) && (! $isFull);

            $slots[] = [
                'start_time' => $slotDef['start_time'],
                'end_time' => $slotDef['end_time'],
                'label' => $slotDef['label'],
                'short_label' => $slotDef['short_label'],
                'agenda' => $slotDef['agenda'] ?? '',
                'leads_count' => $count,
                'max_leads' => $max,
                'remaining' => max(0, $max - $count),
                'is_past' => $isPastSlot,
                'is_full' => $isFull,
                'is_selectable' => $isSelectable,
            ];
        }

        return response()->json([
            'date' => $date->toDateString(),
            'is_past_date' => $isPastDate,
            'slots' => $slots,
        ]);
    }

    /**
     * Book a new slot-wise appointment for a lead.
     */
    public function store(Request $request)
    {
        $this->checkCompanyModuleAccess();

        $user = auth()->user();
        $companyId = $user->company_id;

        // Exclude Won and Lost/Loss stages leads from appointment booking
        $excludedStatusIds = LeadStatus::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereRaw('LOWER(TRIM(name)) in (?, ?, ?)', ['won', 'lost', 'loss'])
                    ->orWhereRaw('LOWER(name) like ?', ['%won%'])
                    ->orWhereRaw('LOWER(name) like ?', ['%lost%'])
                    ->orWhereRaw('LOWER(name) like ?', ['%loss%']);
            })
            ->pluck('id');

        $validated = $request->validate([
            'lead_id' => [
                'required',
                'integer',
                Rule::exists('leads', 'id')->where(function ($query) use ($companyId, $excludedStatusIds) {
                    $query->where('company_id', $companyId)
                        ->where(function ($q) use ($excludedStatusIds) {
                            $q->whereNull('lead_status_id')
                                ->orWhereNotIn('lead_status_id', $excludedStatusIds);
                        });
                }),
            ],
            'appointment_date' => [
                'required',
                'date',
                'date_format:Y-m-d',
                'after_or_equal:today',
            ],
            'slot_start_time' => [
                'required',
                'string',
            ],
            'assigned_to' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('company_id', $companyId),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ], [
            'appointment_date.after_or_equal' => 'Cannot book appointments in past dates.',
            'lead_id.required' => 'Please select a lead.',
            'slot_start_time.required' => 'Please select a slot.',
        ]);

        $appDate = Carbon::parse($validated['appointment_date'])->startOfDay();
        $today = now()->startOfDay();

        if ($appDate->lt($today)) {
            return back()->withErrors(['appointment_date' => 'Cannot select past date.'])->withInput();
        }

        // Validate that slot_start_time matches one of the defined slots
        $matchingSlot = null;
        $slotInput = trim($validated['slot_start_time']);
        if (strlen($slotInput) === 5) {
            $slotInput .= ':00';
        }

        foreach (LeadAppointment::definedSlots() as $slotDef) {
            if ($slotDef['start_time'] === $slotInput || $slotDef['start_h_i'] === substr($slotInput, 0, 5)) {
                $matchingSlot = $slotDef;
                break;
            }
        }

        if (! $matchingSlot) {
            return back()->withErrors(['slot_start_time' => 'Invalid slot selected or slot is lunch break (2 PM - 3 PM).'])->withInput();
        }

        // Validate past time on current date
        $slotStartDateTime = Carbon::parse($validated['appointment_date'] . ' ' . $matchingSlot['start_time']);
        if ($appDate->isSameDay($today) && now()->gte($slotStartDateTime)) {
            return back()->withErrors(['slot_start_time' => 'Cannot select past time slot. Please choose an upcoming slot.'])->withInput();
        }

        // Atomically check max 10 leads limit per slot
        $appointment = DB::transaction(function () use ($companyId, $validated, $matchingSlot, $user) {
            $existingCount = LeadAppointment::query()
                ->where('company_id', $companyId)
                ->whereDate('appointment_date', $validated['appointment_date'])
                ->where(function ($q) use ($matchingSlot) {
                    $q->where('slot_start_time', $matchingSlot['start_time'])
                      ->orWhere('slot_start_time', $matchingSlot['start_h_i']);
                })
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->count();

            if ($existingCount >= LeadAppointment::MAX_LEADS_PER_SLOT) {
                throw new \RuntimeException('This slot is already full (max 10 leads reached). Please pick another slot.');
            }

            // Create appointment record
            $createdAppointment = LeadAppointment::create([
                'company_id' => $companyId,
                'lead_id' => $validated['lead_id'],
                'appointment_date' => $validated['appointment_date'],
                'slot_label' => $matchingSlot['short_label'],
                'slot_start_time' => $matchingSlot['start_time'],
                'slot_end_time' => $matchingSlot['end_time'],
                'assigned_to' => $validated['assigned_to'] ?? $user->id,
                'created_by' => $user->id,
                'status' => 'scheduled',
                'notes' => $validated['notes'] ?? null,
            ]);

            try {
                $aptDateFormatted = Carbon::parse($validated['appointment_date'])->format('d/m/Y');
                $slotDesc = $matchingSlot['label'] ?? $matchingSlot['short_label'] ?? $matchingSlot['start_time'];
                $activityDesc = "Booked appointment for {$aptDateFormatted} in slot {$slotDesc}";
                if (! empty($validated['notes'])) {
                    $activityDesc .= " (Notes: {$validated['notes']})";
                }

                LeadActivity::create([
                    'lead_id' => $validated['lead_id'],
                    'user_id' => $user->id,
                    'type' => 'appointment',
                    'description' => $activityDesc,
                    'occurred_at' => now(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }

            return $createdAppointment;
        });

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Appointment booked successfully.',
                'appointment' => $appointment,
            ]);
        }

        return redirect()
            ->route('appointments.index', ['date' => $validated['appointment_date']])
            ->with('success', "Appointment scheduled successfully in slot {$matchingSlot['label']}.");
    }

    /**
     * Update appointment status or cancel.
     */
    public function updateStatus(Request $request, LeadAppointment $appointment)
    {
        $this->checkCompanyModuleAccess();

        if ((int) $appointment->company_id !== (int) auth()->user()->company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['scheduled', 'completed', 'cancelled', 'rescheduled'])],
        ]);

        $appointment->update([
            'status' => $validated['status'],
        ]);

        try {
            $statusLabel = ucfirst($validated['status']);
            $aptDate = optional($appointment->appointment_date)->format('d/m/Y');
            LeadActivity::create([
                'lead_id' => $appointment->lead_id,
                'user_id' => auth()->id(),
                'type' => 'appointment',
                'description' => "Appointment for {$aptDate} status updated to {$statusLabel}",
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => 'Appointment status updated.']);
        }

        return back()->with('success', 'Appointment status updated successfully.');
    }

    /**
     * Reschedule appointment to a new slot.
     */
    public function reschedule(Request $request, LeadAppointment $appointment)
    {
        $this->checkCompanyModuleAccess();

        $companyId = auth()->user()->company_id;
        if ((int) $appointment->company_id !== (int) $companyId) {
            abort(403);
        }

        $validated = $request->validate([
            'appointment_date' => ['required', 'date', 'date_format:Y-m-d', 'after_or_equal:today'],
            'slot_start_time' => ['required', 'string'],
        ], [
            'appointment_date.after_or_equal' => 'Cannot reschedule to a past date.',
        ]);

        $appDate = Carbon::parse($validated['appointment_date'])->startOfDay();
        $today = now()->startOfDay();

        $matchingSlot = null;
        $slotInput = trim($validated['slot_start_time']);
        if (strlen($slotInput) === 5) {
            $slotInput .= ':00';
        }

        foreach (LeadAppointment::definedSlots() as $slotDef) {
            if ($slotDef['start_time'] === $slotInput || $slotDef['start_h_i'] === substr($slotInput, 0, 5)) {
                $matchingSlot = $slotDef;
                break;
            }
        }

        if (! $matchingSlot) {
            return back()->withErrors(['slot_start_time' => 'Invalid slot selected or slot is lunch break.'])->withInput();
        }

        $slotStartDateTime = Carbon::parse($validated['appointment_date'] . ' ' . $matchingSlot['start_time']);
        if ($appDate->isSameDay($today) && now()->gte($slotStartDateTime)) {
            return back()->withErrors(['slot_start_time' => 'Cannot select past time slot.'])->withInput();
        }

        DB::transaction(function () use ($companyId, $appointment, $validated, $matchingSlot) {
            $existingCount = LeadAppointment::query()
                ->where('company_id', $companyId)
                ->where('id', '!=', $appointment->id)
                ->whereDate('appointment_date', $validated['appointment_date'])
                ->where(function ($q) use ($matchingSlot) {
                    $q->where('slot_start_time', $matchingSlot['start_time'])
                      ->orWhere('slot_start_time', $matchingSlot['start_h_i']);
                })
                ->where('status', '!=', 'cancelled')
                ->lockForUpdate()
                ->count();

            if ($existingCount >= LeadAppointment::MAX_LEADS_PER_SLOT) {
                throw new \RuntimeException('Target slot is already full (max 10 leads reached). Please choose another slot.');
            }

            $appointment->update([
                'appointment_date' => $validated['appointment_date'],
                'slot_label' => $matchingSlot['short_label'],
                'slot_start_time' => $matchingSlot['start_time'],
                'slot_end_time' => $matchingSlot['end_time'],
                'status' => 'rescheduled',
            ]);

            try {
                $newDateStr = Carbon::parse($validated['appointment_date'])->format('d/m/Y');
                LeadActivity::create([
                    'lead_id' => $appointment->lead_id,
                    'user_id' => auth()->id(),
                    'type' => 'appointment',
                    'description' => "Appointment rescheduled to {$newDateStr} ({$matchingSlot['short_label']})",
                    'occurred_at' => now(),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        });

        return redirect()
            ->route('appointments.index', ['date' => $validated['appointment_date']])
            ->with('success', "Appointment rescheduled successfully to {$matchingSlot['label']}.");
    }

    /**
     * Update the lead's status/stage directly from the appointment interface.
     */
    public function updateLeadStatus(Request $request, LeadAppointment $appointment)
    {
        $this->checkCompanyModuleAccess();

        $companyId = auth()->user()->company_id;
        if ((int) $appointment->company_id !== (int) $companyId) {
            abort(403);
        }

        $validated = $request->validate([
            'lead_status_id' => [
                'required',
                'integer',
                Rule::exists('lead_statuses', 'id')->where('company_id', $companyId),
            ],
            'project_name' => [
                'nullable',
                'string',
                'max:191',
            ],
        ]);

        $lead = $appointment->lead;
        if (! $lead || (int) $lead->company_id !== (int) $companyId) {
            abort(404, 'Lead not found or does not belong to your company.');
        }

        $targetStatus = LeadStatus::query()
            ->where('company_id', $companyId)
            ->findOrFail($validated['lead_status_id']);

        $isWon = strtolower(trim((string) $targetStatus->name)) === 'won';

        DB::transaction(function () use ($lead, $targetStatus, $isWon, $validated) {
            $oldStatusName = $lead->status?->name ?? 'Unknown';

            $lead->update([
                'lead_status_id' => $targetStatus->id,
            ]);

            LeadActivity::create([
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'type' => 'status_change',
                'description' => "Status changed from {$oldStatusName} to {$targetStatus->name} via Appointment Manager",
            ]);

            // If updated to won and not yet converted to project, create associated project if name given
            if ($isWon && ! $lead->project && ! empty($validated['project_name'])) {
                $lead->project()->create([
                    'company_id' => $lead->company_id,
                    'created_by' => auth()->id(),
                    'name' => $validated['project_name'],
                    'status' => 'pending',
                    'start_date' => now()->toDateString(),
                ]);
            }
        });

        $lead->refresh()->load('status');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Lead stage updated to {$targetStatus->name}.",
                'status' => [
                    'id' => $targetStatus->id,
                    'name' => $targetStatus->name,
                    'color' => $targetStatus->color,
                ],
            ]);
        }

        return back()->with('success', "Lead stage updated to {$targetStatus->name}.");
    }

    /**
     * Feed calendar events for FullCalendar view.
     */
    public function calendarEvents(Request $request)
    {
        $this->checkCompanyModuleAccess();

        $companyId = auth()->user()->company_id;
        $startStr = $request->query('start');
        $endStr = $request->query('end');

        try {
            $startDate = $startStr ? Carbon::parse($startStr)->startOfDay() : now()->startOfMonth();
            $endDate = $endStr ? Carbon::parse($endStr)->endOfDay() : now()->endOfMonth();
        } catch (\Throwable $e) {
            $startDate = now()->startOfMonth();
            $endDate = now()->endOfMonth();
        }

        // Fetch all appointments in this date range
        $appointments = LeadAppointment::query()
            ->where('company_id', $companyId)
            ->whereBetween('appointment_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->with(['lead.status', 'assignee'])
            ->get();

        // Group appointments by date and slot_start_time
        $grouped = $appointments->groupBy(function ($apt) {
            return $apt->appointment_date->toDateString() . '_' . substr((string) $apt->slot_start_time, 0, 5);
        });

        $events = [];
        $definedSlots = LeadAppointment::definedSlots();
        $definedSlotsMap = [];
        foreach ($definedSlots as $s) {
            $definedSlotsMap[$s['start_h_i']] = $s;
        }

        // Generate events for each group that has appointments
        foreach ($grouped as $key => $slotAppointments) {
            [$dateStr, $startHi] = explode('_', $key);
            $slotDef = $definedSlotsMap[$startHi] ?? null;
            $slotLabel = $slotDef['short_label'] ?? $startHi;
            $agenda = $slotDef['agenda'] ?? 'Lead Consultation';
            $count = $slotAppointments->count();
            $max = LeadAppointment::MAX_LEADS_PER_SLOT;

            $color = '#4f46e5'; // default indigo
            if ($count >= $max) {
                $color = '#e11d48'; // rose when full
            } elseif ($count >= 7) {
                $color = '#d97706'; // amber when near full
            }

            $startIso = $dateStr . 'T' . ($slotDef ? $slotDef['start_time'] : ($startHi . ':00'));
            $endIso = $dateStr . 'T' . ($slotDef ? $slotDef['end_time'] : (Carbon::parse($startHi)->addHour()->format('H:i:s')));

            $leadsSummary = $slotAppointments->map(function ($apt) {
                return [
                    'id' => $apt->id,
                    'lead_id' => $apt->lead_id,
                    'lead_uuid' => $apt->lead?->uuid,
                    'show_url' => $apt->lead ? route('leads.show', $apt->lead) : '#',
                    'client_name' => $apt->lead?->client_name ?? 'Lead #' . $apt->lead_id,
                    'mobile' => $apt->lead?->mobile,
                    'status_name' => $apt->lead?->status?->name ?? 'New Lead',
                    'status_color' => $apt->lead?->status?->color ?? '#3498db',
                    'appointment_status' => $apt->status,
                    'assignee_name' => $apt->assignee?->name ?? 'Unassigned',
                ];
            })->values();

            $events[] = [
                'id' => 'slot_' . $key,
                'title' => "[$slotLabel] $agenda ($count/$max)",
                'start' => $startIso,
                'end' => $endIso,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'date' => $dateStr,
                    'slot_label' => $slotDef['label'] ?? $slotLabel,
                    'short_label' => $slotLabel,
                    'agenda' => $agenda,
                    'leads_count' => $count,
                    'max_leads' => $max,
                    'leads' => $leadsSummary,
                ],
            ];
        }

        return response()->json($events);
    }

    /**
     * Update slot agenda(s) for the current company.
     */
    public function updateAgendas(Request $request)
    {
        $this->checkCompanyModuleAccess();

        $user = auth()->user();
        $company = $user->company;
        if (! $company) {
            abort(403);
        }

        // Support either single slot update or multiple slot updates
        $validated = $request->validate([
            'slot_start_time' => 'nullable|string',
            'agenda'          => 'nullable|string|max:255',
            'agendas'         => 'nullable|array',
            'agendas.*'       => 'nullable|string|max:255',
        ]);

        $currentAgendas = is_array($company->appointment_slot_agendas) ? $company->appointment_slot_agendas : [];

        if (! empty($validated['slot_start_time'])) {
            $slotKey = trim($validated['slot_start_time']);
            if (strlen($slotKey) === 5) {
                $slotKey .= ':00';
            }
            $newAgenda = trim((string) ($validated['agenda'] ?? ''));
            if ($newAgenda === '') {
                unset($currentAgendas[$slotKey], $currentAgendas[substr($slotKey, 0, 5)]);
            } else {
                $currentAgendas[$slotKey] = $newAgenda;
            }
        } elseif (isset($validated['agendas']) && is_array($validated['agendas'])) {
            foreach ($validated['agendas'] as $slotKey => $agendaText) {
                $slotKey = trim((string) $slotKey);
                if (strlen($slotKey) === 5) {
                    $slotKey .= ':00';
                }
                $agendaText = trim((string) $agendaText);
                if ($agendaText === '') {
                    unset($currentAgendas[$slotKey], $currentAgendas[substr($slotKey, 0, 5)]);
                } else {
                    $currentAgendas[$slotKey] = $agendaText;
                }
            }
        }

        $company->update([
            'appointment_slot_agendas' => $currentAgendas,
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Slot agenda updated successfully.',
                'agendas' => $currentAgendas,
            ]);
        }

        return back()->with('success', 'Slot agenda updated successfully.');
    }

    /**
     * Delete appointment.
     */
    public function destroy(LeadAppointment $appointment)
    {
        $this->checkCompanyModuleAccess();

        if ((int) $appointment->company_id !== (int) auth()->user()->company_id) {
            abort(403);
        }

        $leadId = $appointment->lead_id;
        $appointmentDate = optional($appointment->appointment_date)->format('d/m/Y');
        $appointment->delete();

        try {
            LeadActivity::create([
                'lead_id' => $leadId,
                'user_id' => auth()->id(),
                'type' => 'appointment',
                'description' => "Deleted appointment scheduled for {$appointmentDate}",
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Appointment deleted successfully.');
    }
}

