<?php

namespace App\Http\Controllers;

use App\Exports\StaffExport;
use App\Exports\StaffImportSampleExport;
use App\Imports\StaffImport;
use Illuminate\Http\Request;
use App\Models\Staff;
use App\Models\Role;
use App\Models\User;
use App\Models\StaffDocument;
use App\Models\StaffActivity;
use App\Models\StaffAttendance;
use App\Models\StaffAdvance;
use App\Models\StaffSalaryStatus;
use App\Models\SubscriptionPlan;
use App\Models\TicketInvoice;
use App\Services\MediaUploadService;
use App\Services\StaffInvoiceService;
use App\Support\StaffMobileTokenStore;
use App\Models\Company;
use App\Models\CompanyHoliday;
use App\Models\Ticket;
use App\Services\SubscriptionLimitService;
use App\Services\AmcSupportWorkflowService;
use App\Services\TicketAssignmentHistoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class StaffController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $companyId = $this->companyId();

        if ($companyId) {
            app(\App\Services\CompanyAdminSeatService::class)->ensureAdminStaffProfile($companyId, $user->isCompanyAdmin() ? $user : null);
        }

        $canCreateStaff = $user->isCompanyAdmin() || $user->hasAnyPermission('teams.staff.create', 'settings.users.manage');
        $roles = collect();
        $availableSubscriptionOptions = collect();
        $selectedPlanOption = null;
        $subscriptionLimitService = app(SubscriptionLimitService::class);

        if ($canCreateStaff) {
            $availableSubscriptionOptions = collect($subscriptionLimitService->availableSubscriptionOptions($companyId));
            $selectedPlanOption = $availableSubscriptionOptions->first();
            $roles = Role::query()
                ->where('company_id', $companyId)
                ->where('slug', '!=', 'client')
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);
        }

        $selectedMonthKey = (string) request()->input('month', now()->format('Y-m'));
        $salaryMonthKey = (string) request()->input('salary_month', now()->format('Y-m'));
        try {
            $selectedMonthDate = Carbon::createFromFormat('Y-m', $selectedMonthKey)->startOfMonth();
        } catch (\Throwable $e) {
            $selectedMonthDate = now()->startOfMonth();
            $selectedMonthKey = $selectedMonthDate->format('Y-m');
        }
        $attendanceMap = [];
        $company = $companyId ? Company::query()->find($companyId) : null;
        $workingDays = collect($company?->working_days ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'])->values()->all();
        $canTrackStaffRoutes = $user->isCompanyAdmin() && $subscriptionLimitService->activeSubscriptions($companyId)
            ->contains(fn ($subscription) => ! ($subscription->plan?->shouldDisableStaffTracking() ?? true));
        $holidayDates = CompanyHoliday::query()
            ->where('company_id', $companyId)
            ->whereYear('holiday_date', (int) $selectedMonthDate->format('Y'))
            ->whereMonth('holiday_date', (int) $selectedMonthDate->format('m'))
            ->get()
            ->mapWithKeys(fn ($holiday) => [$holiday->holiday_date->toDateString() => $holiday]);
        $attendanceDaysCount = 0;
        for ($day = 1; $day <= $selectedMonthDate->daysInMonth; $day++) {
            $date = $selectedMonthDate->copy()->day($day);
            $dayKey = strtolower($date->format('D'));
            if (in_array($dayKey, $workingDays, true) && !isset($holidayDates[$date->toDateString()])) {
                $attendanceDaysCount++;
            }
        }

        if ($user->isCompanyAdmin() || $user->hasPermission('teams.staff.view')) {
            $staffs = Staff::query()
                ->where('company_id', $companyId)
                ->whereHas('user')
                ->with([
                    'user:id,name,email,role,role_id',
                    'user.roleRecord:id,name,slug',
                    'companySubscription.plan:id,name,disable_staff_tracking',
                ])
                ->with('salaryConfiguration')
                ->latest('id')
                ->get();

            $advances = StaffAdvance::query()
                ->whereHas('staff', fn ($q) => $q->where('company_id', $companyId))
                ->with('staff:id,name,employee_id')
                ->latest()
                ->get();

            $attendanceRows = StaffAttendance::query()
                ->whereIn('staff_id', $staffs->pluck('id'))
                ->whereYear('date', (int) $selectedMonthDate->format('Y'))
                ->whereMonth('date', (int) $selectedMonthDate->format('m'))
                ->get()
                ->groupBy('staff_id');

            foreach ($attendanceRows as $staffId => $rows) {
                foreach ($rows as $row) {
                    $dateKey = Carbon::parse($row->date)->toDateString();
                    $attendanceMap[$staffId][$dateKey] = [
                        'status' => (string) $row->status,
                        'leave_reason' => (string) ($row->leave_reason ?? ''),
                        'overtime_hours' => (float) ($row->overtime_hours ?? 0),
                        'correction_reason' => (string) ($row->correction_reason ?? ''),
                        'is_corrected' => (bool) $row->is_corrected,
                    ];
                }
            }
        } else {
            $this->ensureSelfManagePermission();
            $staffs = Staff::query()
                ->where('company_id', $companyId)
                ->whereHas('user')
                ->where('user_id', $user->id)
                ->with([
                    'user:id,name,email,role,role_id',
                    'user.roleRecord:id,name,slug',
                    'companySubscription.plan:id,name,disable_staff_tracking',
                ])
                ->with('salaryConfiguration')
                ->latest('id')
                ->get();

            $advances = collect();
        }

        $salarySummaries = collect();
        $salaryMonths = collect();

        if ($user->isCompanyAdmin() || $user->hasPermission('teams.staff.view')) {
            $currentMonthKey = now()->format('Y-m');

            try {
                $salaryMonthDate = Carbon::createFromFormat('Y-m', $salaryMonthKey)->startOfMonth();
            } catch (\Throwable $e) {
                $salaryMonthDate = now()->startOfMonth();
                $salaryMonthKey = $salaryMonthDate->format('Y-m');
            }
            // prevent future months; clamp to current month
            if ($salaryMonthDate->greaterThan(now()->endOfMonth())) {
                $salaryMonthDate = now()->startOfMonth();
                $salaryMonthKey = $salaryMonthDate->format('Y-m');
            }

            $salaryDaysInMonth = $salaryMonthDate->daysInMonth;
            $salaryHolidayDates = CompanyHoliday::query()
                ->where('company_id', $companyId)
                ->whereYear('holiday_date', (int) $salaryMonthDate->format('Y'))
                ->whereMonth('holiday_date', (int) $salaryMonthDate->format('m'))
                ->get()
                ->mapWithKeys(fn ($holiday) => [$holiday->holiday_date->toDateString() => $holiday]);
            $salaryWorkingDaysCount = $this->countWorkingDaysInMonth($salaryMonthDate, $workingDays, $salaryHolidayDates);
            $salaryAttendance = StaffAttendance::query()
                ->whereIn('staff_id', $staffs->pluck('id'))
                ->whereYear('date', (int) $salaryMonthDate->format('Y'))
                ->whereMonth('date', (int) $salaryMonthDate->format('m'))
                ->get()
                ->groupBy('staff_id');

            $salaryAdvances = StaffAdvance::query()
                ->whereIn('staff_id', $staffs->pluck('id'))
                ->whereYear('advance_date', (int) $salaryMonthDate->format('Y'))
                ->whereMonth('advance_date', (int) $salaryMonthDate->format('m'))
                ->orderBy('advance_date')
                ->orderBy('id')
                ->get()
                ->groupBy('staff_id');

            $salaryStatuses = StaffSalaryStatus::query()
                ->whereIn('staff_id', $staffs->pluck('id'))
                ->get();

            // build month list from attendance, advances, statuses and current month
            $salaryMonths = collect([$currentMonthKey])
                ->merge(
                    StaffAttendance::query()
                        ->whereIn('staff_id', $staffs->pluck('id'))
                        ->selectRaw("DATE_FORMAT(date, '%Y-%m') as month_key")
                        ->distinct()
                        ->pluck('month_key')
                )
                ->merge(
                    StaffAdvance::query()
                        ->whereIn('staff_id', $staffs->pluck('id'))
                        ->selectRaw("DATE_FORMAT(advance_date, '%Y-%m') as month_key")
                        ->distinct()
                        ->pluck('month_key')
                )
                ->merge(
                    $salaryStatuses
                        ->pluck('month_key')
                        ->unique()
                )
                ->unique()
                ->filter()
                ->sortDesc()
                ->values();

            $salaryStatuses = $salaryStatuses->where('month_key', $salaryMonthKey)->keyBy('staff_id');

            $salarySummaries = $staffs->map(function ($staff) use ($salaryMonthKey, $salaryMonthDate, $salaryWorkingDaysCount, $salaryAttendance, $salaryAdvances, $salaryStatuses) {
                $attendanceRows = $salaryAttendance[$staff->id] ?? collect();
                $present = $attendanceRows->where('status', 'Present')->count();
                $halfDay = $attendanceRows->where('status', 'Half Day')->count();
                $presentEquivalent = $present + ($halfDay * 0.5);

                $totalSalary = (float) ($staff->salaryConfiguration?->total_salary ?? 0);
                $workingDays = max(1, $salaryWorkingDaysCount);
                $perDay = $totalSalary / $workingDays;
                $payable = round($perDay * $presentEquivalent, 2);

                $staffAdvances = ($salaryAdvances[$staff->id] ?? collect())->sortBy('advance_date');
                $advanceTotal = (float) $staffAdvances->sum('amount');
                $advanceBreakup = $staffAdvances->map(function ($adv) {
                    return [
                        'id' => $adv->id,
                        'date' => Carbon::parse($adv->advance_date)->format('d-m-Y'),
                        'amount' => (float) $adv->amount,
                        'notes' => $adv->notes,
                        'edit_url' => route('teams.payroll.advance.edit', ['advance' => $adv->id]),
                        'delete_url' => route('teams.payroll.advance.destroy', ['advance' => $adv->id]),
                    ];
                })->values()->toArray();

                $netPayable = $payable - $advanceTotal;
                $status = (string) ($salaryStatuses[$staff->id]->status ?? 'Pending');

                return [
                    'staff_id' => $staff->id,
                    'name' => $staff->name,
                    'employee_id' => $staff->employee_id,
                    'month_key' => $salaryMonthKey,
                    'month_label' => $salaryMonthDate->format('M Y'),
                    'days_in_month' => $salaryWorkingDaysCount,
                    'present_days' => $present,
                    'present_equivalent' => $presentEquivalent,
                    'total_salary' => round($totalSalary, 2),
                    'payable' => $payable,
                    'advance_total' => $advanceTotal,
                    'net_payable' => $netPayable,
                    'status' => $status,
                    'advances' => $advanceBreakup,
                ];
            })->values();
        }

        return view('teams.staff.index', compact(
            'staffs',
            'attendanceMap',
            'selectedMonthKey',
            'advances',
            'salarySummaries',
            'salaryMonthKey',
            'salaryMonths',
            'workingDays',
            'holidayDates',
            'company',
            'attendanceDaysCount',
            'selectedMonthDate',
            'roles',
            'availableSubscriptionOptions',
            'selectedPlanOption',
            'canCreateStaff',
            'subscriptionLimitService',
            'canTrackStaffRoutes'
        ));
    }

    private function countWorkingDaysInMonth(Carbon $monthDate, array $workingDays, $holidayDates): int
    {
        $count = 0;

        for ($day = 1; $day <= $monthDate->daysInMonth; $day++) {
            $date = $monthDate->copy()->day($day);
            $dayKey = strtolower($date->format('D'));
            if (in_array($dayKey, $workingDays, true) && !isset($holidayDates[$date->toDateString()])) {
                $count++;
            }
        }

        return $count;
    }

    public function updateSalaryStatus(Request $request)
    {
        $this->ensureCompanyAdmin();

        $data = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'month_key' => ['required', 'regex:/^\\d{4}-\\d{2}$/'],
            'status' => ['required', 'in:Pending,Done'],
        ]);

        $status = StaffSalaryStatus::query()->updateOrCreate(
            [
                'staff_id' => $data['staff_id'],
                'month_key' => $data['month_key'],
            ],
            [
                'status' => $data['status'],
            ]
        );

        return response()->json([
            'success' => true,
            'status' => $status->status,
        ]);
    }

    public function create()
    {
        $this->ensureCompanyAdmin();
        $companyId = $this->companyId();
        $subscriptionLimitService = app(SubscriptionLimitService::class);
        $subscriptionUsage = $subscriptionLimitService->currentUsageSummary($companyId);
        $activeSubscriptions = $subscriptionLimitService->activeSubscriptions($companyId);
        $availableSubscriptionOptions = $subscriptionLimitService->availableSubscriptionOptions($companyId);

        $usersWithoutStaff = User::query()
            ->where('company_id', $companyId)
            ->whereDoesntHave('staff')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return view('teams.staff.create', compact('usersWithoutStaff', 'activeSubscriptions', 'availableSubscriptionOptions', 'subscriptionUsage'));
    }

    public function export()
    {
        $user = Auth::user();

        abort_unless($user->isCompanyAdmin() || $user->hasPermission('teams.staff.view'), 403);

        $staffs = Staff::query()
            ->where('company_id', $this->companyId())
            ->whereHas('user')
            ->with(['user:id,email,role,role_id', 'user.roleRecord:id,name,slug'])
            ->latest('id')
            ->get();

        return Excel::download(
            new StaffExport($staffs),
            'staff-' . now()->format('Y-m-d-His') . '.xlsx'
        );
    }

    public function import(Request $request)
    {
        $this->ensureCompanyAdmin();

        $request->validate([
            'staff_import_file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ], [
            'staff_import_file.required' => 'Please choose a staff import file.',
            'staff_import_file.mimes' => 'Staff import file must be CSV, XLS, or XLSX.',
        ]);

        Excel::import(
            new StaffImport(Auth::user()),
            $request->file('staff_import_file')
        );

        return redirect()
            ->route('teams.staff.index')
            ->with('success', 'Staff imported successfully.');
    }

    public function downloadImportSample()
    {
        $this->ensureCompanyAdmin();

        return Excel::download(
            new StaffImportSampleExport(),
            'staff-import-sample.xlsx'
        );
    }

    public function store(Request $request)
    {
        $this->ensureCompanyAdmin();
        $companyId = $this->companyId();
        $subscriptionLimitService = app(SubscriptionLimitService::class);
        $availableSubscriptionOptions = collect($subscriptionLimitService->availableSubscriptionOptions($companyId));
        $availableSubscriptionIds = $availableSubscriptionOptions->pluck('company_subscription_id')->values()->all();

        if ($availableSubscriptionIds === []) {
            return back()
                ->withInput()
                ->withErrors([
                    'company_subscription_id' => 'No active plan seats are available. Please purchase a subscription before creating staff.',
                ]);
        }

        $validated = $request->validate(
            array_merge(
                $this->staffRules($companyId, null, true, false),
                [
                    'company_subscription_id' => [
                        'required',
                        Rule::in($availableSubscriptionIds),
                    ],
                ]
            ),
            $this->staffValidationMessages()
        );

        $selectedSubscriptionId = (int) $validated['company_subscription_id'];

        $validated['company_id'] = $companyId;
        $validated['employee_id'] = Staff::generateEmployeeId($companyId);
        $validated['name'] = Str::limit(trim((string) $validated['name']), 191, '');
        $validated['contact_number'] = trim((string) $validated['contact_number']);
        $validated['department'] = filled($validated['department'] ?? null)
            ? Str::limit(trim((string) $validated['department']), 191, '')
            : null;

        $staff = null;

        DB::transaction(function () use ($request, $companyId, $subscriptionLimitService, $selectedSubscriptionId, &$validated, &$staff) {
            $selectedLicenseKey = $subscriptionLimitService->nextAvailableLicenseKey($companyId, $selectedSubscriptionId);

            if (! $selectedLicenseKey) {
                throw ValidationException::withMessages([
                    'company_subscription_id' => 'The selected plan has no remaining seats.',
                ]);
            }

            $validated['company_subscription_id'] = $selectedSubscriptionId;

            if ($request->hasFile('profile_photo')) {
                $validated['profile_photo'] = app(MediaUploadService::class)->store($request->file('profile_photo'), 'company_'.$companyId.'/staff_profiles', 'public');
            }

            $validated['license_key'] = $selectedLicenseKey;
            $staff = Staff::create($validated);
        });

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Staff Created',
            'description' => 'New Staff Added',
            'occurred_at' => now(),
        ]);

        return redirect()->route('teams.staff.index')->with('success', 'Staff member added successfully.');
    }

    public function show(string $id)
    {
        $staff = $this->resolveStaffOrFail($id);
        $this->ensureCanAccessStaff($staff);

        $staff->load([
            'user:id,name,email,role',
            'salaryConfiguration',
            'bankDetails',
            'documents',
            'activities' => function ($q) {
                $q->latest('occurred_at');
            },
        ]);

        $workflowService = app(AmcSupportWorkflowService::class);
        $assignmentHistoryService = app(TicketAssignmentHistoryService::class);
        $assignedTickets = Ticket::query()
            ->where('company_id', $staff->company_id)
            ->where(function ($query) use ($staff) {
                $query->where('assigned_to', $staff->user_id);
                if (\Illuminate\Support\Facades\Schema::hasTable('ticket_user_assignments')) {
                    $query->orWhereHas('assignedUsers', fn ($q) => $q->where('users.id', $staff->user_id));
                }
            })
            ->with([
                'generatedFromAmc:id,contract_no,service_mode',
                'parentTicket:id,ticket_number',
                'previousStaff:id,name',
                'user:id,name',
            ])
            ->latest('updated_at')
            ->limit(25)
            ->get();

        $staffAssignmentSummary = $assignmentHistoryService->staffSummary((int) $staff->company_id, (int) $staff->user_id);
        $historicalTicketRows = \App\Models\TicketAssignmentHistory::query()
            ->whereHas('ticket', fn ($query) => $query->where('company_id', $staff->company_id))
            ->where('staff_id', $staff->user_id)
            ->with(['ticket:id,ticket_number,subject,status,assigned_to,repeat_count,last_repeat_at', 'assignedBy:id,name'])
            ->latest('assigned_at')
            ->limit(50)
            ->get();

        $staffPerformance = $workflowService->staffPerformanceSummary((int) $staff->company_id, (int) $staff->user_id);
        $staffInvoiceService = app(StaffInvoiceService::class);
        $staffInvoices = $staffInvoiceService->getInvoices((int) $staff->company_id, (int) $staff->id);
        $staffInvoiceSummary = $staffInvoiceService->getSummary((int) $staff->company_id, (int) $staff->id);
        $topClient = $assignedTickets
            ->groupBy(fn (Ticket $ticket) => trim((string) $ticket->customer_name))
            ->map(fn ($tickets, $name) => [
                'customer_name' => $name,
                'total' => $tickets->count(),
                'open' => $tickets->whereIn('status', ['open', 'pending', 'assigned', 'in_progress'])->count(),
                'closed' => $tickets->whereIn('status', ['closed', 'resolved'])->count(),
                'repeat_count' => (int) $tickets->sum('repeat_count'),
                'average_resolution_days' => $tickets->whereIn('status', ['closed', 'resolved'])->count() > 0
                    ? round($tickets->whereIn('status', ['closed', 'resolved'])->avg(fn (Ticket $ticket) => $ticket->created_at && $ticket->updated_at ? $ticket->created_at->diffInDays($ticket->updated_at) : 0), 2)
                    : 0,
                'last_ticket' => $tickets->sortByDesc('created_at')->first(),
            ])
            ->sortByDesc('total')
            ->first();

        $attendanceMonth = (int) now()->month;
        $attendanceYear = (int) now()->year;
        $monthlyAttendance = $staff->attendance()
            ->whereMonth('date', $attendanceMonth)
            ->whereYear('date', $attendanceYear)
            ->orderBy('date', 'desc')
            ->get();
        $attendanceSummary = [
            'present' => $monthlyAttendance->where('status', 'Present')->count(),
            'absent' => $monthlyAttendance->where('status', 'Absent')->count(),
            'half_day' => $monthlyAttendance->where('status', 'Half Day')->count(),
            'leave' => $monthlyAttendance->where('status', 'Leave')->count(),
            'holiday' => $monthlyAttendance->where('status', 'Holiday')->count(),
            'overtime_hours' => (float) $monthlyAttendance->sum('overtime_hours'),
        ];
        $companyStaff = Auth::user()->isCompanyAdmin()
            ? Staff::query()
                ->where('company_id', $this->companyId())
                ->orderBy('name')
                ->get(['id', 'name', 'employee_id'])
            : collect();
        $staffApiAccessToken = null;

        if (
            (int) $staff->user_id === (int) Auth::id()
            && Auth::user()->normalizedRole() === 'employee'
            && Auth::user()->hasPermission('teams.staff.self.manage')
        ) {
            $tokenStore = app(StaffMobileTokenStore::class);
            $tokenStore->forgetNamedTokens(Auth::user(), 'web-staff-panel');
            $staffApiAccessToken = $tokenStore->issue(Auth::user(), 'web-staff-panel', now()->addHours(2))['plain_text_token'];
        }

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Staff Details',
            'description' => 'Staff Details Check',
            'occurred_at' => now(),
        ]);

        return view('teams.staff.show', compact(
            'staff',
            'monthlyAttendance',
            'attendanceSummary',
            'attendanceMonth',
            'attendanceYear',
            'companyStaff',
            'staffApiAccessToken',
            'assignedTickets',
            'staffPerformance',
            'staffInvoices',
            'staffInvoiceSummary',
            'topClient',
            'staffAssignmentSummary',
            'historicalTicketRows'
        ));
    }

    public function updateReceivedByAdmin(Request $request, string $id, TicketInvoice $ticketInvoice)
    {
        $staff = $this->resolveStaffOrFail($id);
        $this->ensureCanAccessStaff($staff);
        abort_unless(Auth::user()->isCompanyAdmin(), 403, 'Only company admin can update invoice receipt status.');

        abort_unless((int) $ticketInvoice->company_id === (int) $staff->company_id, 404);
        abort_unless((int) $ticketInvoice->staff_id === (int) $staff->id, 404);

        $validated = $request->validate([
            'received_by_admin' => ['required', 'boolean'],
        ]);

        if ((bool) $validated['received_by_admin'] && (int) $ticketInvoice->payment_status !== 1) {
            throw ValidationException::withMessages([
                'received_by_admin' => ['Invoice not paid yet.'],
            ]);
        }

        app(StaffInvoiceService::class)->updateReceivedByAdmin(
            $ticketInvoice,
            Auth::user(),
            (bool) $validated['received_by_admin']
        );

        return back()->with('success', 'Invoice receipt status updated successfully.');
    }

    public function edit(string $id)
    {
        $staff = $this->resolveStaffOrFail($id);
        $this->ensureCanAccessStaff($staff);

        $staff->load('user:id,name,email,is_active,role,role_id');
        $roles = Auth::user()->isCompanyAdmin()
            ? Role::query()
                ->where('company_id', $this->companyId())
                ->where('slug', '!=', 'client')
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
            : collect();

        return view('teams.staff.edit', compact('staff', 'roles'));
    }

    public function update(Request $request, string $id)
    {
        $staff = $this->resolveStaffOrFail($id);
        $this->ensureCanAccessStaff($staff);
        $companyId = $this->companyId();
        $validated = $request->validate(
            $this->staffRules($companyId, $staff, false, false),
            $this->staffValidationMessages()
        );

        $validated['name'] = Str::limit(trim((string) $validated['name']), 191, '');
        $validated['contact_number'] = trim((string) $validated['contact_number']);
        $validated['department'] = filled($validated['department'] ?? null)
            ? Str::limit(trim((string) $validated['department']), 191, '')
            : null;

        if (!Auth::user()->isCompanyAdmin()) {
            unset($validated['employee_id']);
            unset($validated['role_id']);
        }

        if (isset($validated['role_id'])) {
            $this->ensureCompanyAdminIsNotRemoved($staff, (int) $validated['role_id'], $companyId);
        }

        if ($request->hasFile('profile_photo')) {
            $oldProfilePhoto = $staff->profile_photo;
            $validated['profile_photo'] = app(MediaUploadService::class)->store(
                $request->file('profile_photo'),
                'company_'.$companyId.'/staff_profiles',
                'public'
            );

            if ($oldProfilePhoto && $oldProfilePhoto !== $validated['profile_photo']) {
                app(MediaUploadService::class)->delete($oldProfilePhoto, 'public', $companyId);
            }
        }

        DB::transaction(function () use ($staff, $validated, $companyId) {
            $role = null;

            if (isset($validated['role_id'])) {
                $role = Role::query()
                    ->where('company_id', $companyId)
                    ->findOrFail((int) $validated['role_id']);
            }

            $staff->update($validated);

            if ($role) {
                $staff->role_id = $role->id;
                $staff->save();
            }

            if ($staff->user) {
                $userPayload = [];

                if ($staff->user->name !== $validated['name']) {
                    $userPayload['name'] = $validated['name'];
                }

                if ($role) {
                    $userPayload['role_id'] = $role->id;
                    $userPayload['role'] = $role->slug;
                }

                if ($userPayload !== []) {
                    $staff->user->update($userPayload);
                }
            }
        });

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Staff Updated',
            'description' => 'Staff Details Updated',
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('teams.staff.show', $staff)
            ->with('success', 'Staff details updated successfully.');
    }

    public function reactivateSubscription(Request $request, string $id)
    {
        $this->ensureCompanyAdmin();

        $staff = $this->resolveStaffOrFail($id);
        $companyId = $this->companyId();
        $subscriptionLimitService = app(SubscriptionLimitService::class);
        $availableSubscriptionOptions = collect($subscriptionLimitService->availableSubscriptionOptions($companyId));
        $availableSubscriptionIds = $availableSubscriptionOptions->pluck('company_subscription_id')->values()->all();

        if ($availableSubscriptionIds === []) {
            return back()
                ->withInput([
                    'reactivate_staff_id' => $staff->getRouteKey(),
                    'reactivate_staff_name' => $staff->name,
                    'reactivate_current_plan' => $staff->companySubscription?->plan?->name ?? 'Expired Plan',
                ])
                ->withErrors([
                    'reactivate_company_subscription_id' => 'No active plan seats are available. Please purchase or renew a subscription first.',
                ]);
        }

        $validated = $request->validate([
            'company_subscription_id' => ['required', Rule::in($availableSubscriptionIds)],
        ], [
            'company_subscription_id.required' => 'Please select an active plan.',
            'company_subscription_id.in' => 'The selected active plan is invalid for this company.',
        ]);

        $selectedSubscriptionId = (int) $validated['company_subscription_id'];

        DB::transaction(function () use ($staff, $companyId, $subscriptionLimitService, $selectedSubscriptionId) {
            $selectedLicenseKey = $subscriptionLimitService->nextAvailableLicenseKey($companyId, $selectedSubscriptionId);

            if (! $selectedLicenseKey) {
                throw ValidationException::withMessages([
                    'reactivate_company_subscription_id' => 'The selected active plan has no remaining seats.',
                ]);
            }

            $staff->forceFill([
                'company_subscription_id' => $selectedSubscriptionId,
                'license_key' => $selectedLicenseKey,
            ])->save();

            if ($staff->user && ! $staff->user->is_active) {
                $staff->user->forceFill([
                    'is_active' => true,
                ])->save();
            }
        });

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Staff Reactivated',
            'description' => 'Staff was moved to an active subscription plan.',
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('teams.staff.index')
            ->with('success', 'Staff member activated on the selected subscription plan.');
    }

    public function destroy(string $id)
    {
        $this->ensureCompanyAdmin();
        $staff = $this->resolveStaffOrFail($id);
        $user = $staff->user;
        $companyId = $this->companyId();

        if ($user && (int) $user->id === (int) Auth::id()) {
            return back()->with('error', 'You cannot delete your own staff user while logged in.');
        }

        if ($user && $user->is_active && $user->isCompanyAdmin() && $this->activeCompanyAdminCount() <= 1) {
            return back()->with('error', 'You cannot delete the last active company admin.');
        }

        $pathsToDelete = collect([$staff->profile_photo, $user?->profile_photo])
            ->merge($staff->documents()->pluck('document_path'))
            ->filter()
            ->unique()
            ->values();

        DB::transaction(function () use ($staff, $user) {
            $staff->forceDelete();

            if ($user) {
                $user->delete();
            }
        });

        $mediaUploadService = app(MediaUploadService::class);
        foreach ($pathsToDelete as $path) {
            $mediaUploadService->delete($path, 'public', $companyId);
        }

        return redirect()->route('teams.staff.index')->with('success', 'Staff and linked user deleted successfully.');
    }

    public function uploadDocument(Request $request, string $staffId)
    {
        $staff = $this->resolveStaffOrFail($staffId);
        $this->ensureCanAccessStaff($staff);

        $validated = $request->validate([
            'document_type' => 'required|string|min:2|max:191',
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,webp,doc,docx|max:10240',
        ]);

        $validated['document_type'] = trim((string) $validated['document_type']);

        $companyId = $this->companyId();
        $path = app(MediaUploadService::class)->store($request->file('document'), 'company_'.$companyId.'/staff_docs', 'public');

        StaffDocument::create([
            'staff_id' => $staff->id,
            'document_type' => $validated['document_type'],
            'document_path' => $path,
        ]);

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Staff Document',
            'description' => 'Uploaded document: ' . $validated['document_type'],
            'occurred_at' => now(),
        ]);

        return back()->with('success', 'Document uploaded successfully!');
    }

    public function downloadDocument(string $staffId, StaffDocument $document)
    {
        $staff = $this->resolveStaffOrFail($staffId);
        $this->ensureCanAccessStaff($staff);

        abort_unless((int) $document->staff_id === (int) $staff->id, 404);

        $rawPath = str_replace('\\', '/', (string) $document->document_path);
        $path = ltrim($rawPath, '/');
        $disk = env('AWS_ACCESS_KEY_ID') ? 's3' : 'public';

        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        abort_unless($path !== '' && Storage::disk($disk)->exists($path), 404, 'Document not found.');

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $safeType = preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $document->document_type) ?: 'document';
        $downloadName = $safeType . ($extension ? '.' . $extension : '');

        return Storage::disk($disk)->download($path, $downloadName);
    }

    public function storeAdvance(Request $request)
    {
        $this->ensureCompanyAdmin();
        $companyId = $this->companyId();

        $validated = $request->validate([
            'staff_id' => [
                'required',
                Rule::exists('staff', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string|max:500',
        ]);

        $advance = StaffAdvance::create([
            'company_id' => $companyId,
            'staff_id' => $validated['staff_id'],
            'advance_date' => now()->toDateString(),
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ]);

        StaffAdvance::syncMonthlyPayrollAdvanceAmount($companyId, (int) $advance->staff_id, $advance->advance_date);

        StaffActivity::create([
            'staff_id' => $advance->staff_id,
            'type' => 'Staff Advance',
            'description' => 'Advance recorded: INR ' . number_format($advance->amount, 2),
            'occurred_at' => now(),
        ]);

        return redirect()
            ->route('teams.staff.index', ['tab' => 'advance'])
            ->with('success', 'Advance saved successfully.');
    }

    public function createAdvance()
    {
        $this->ensureCompanyAdmin();

        $staffs = Staff::query()
            ->where('company_id', $this->companyId())
            ->whereHas('user')
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'name', 'employee_id']);

        return view('teams.staff.advance-create', compact('staffs'));
    }

    private function resolveStaffOrFail(string|int $staffId): Staff
    {
        $staffId = Staff::decodeRouteKeyValue($staffId);

        return Staff::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($staffId);
    }

    private function ensureCompanyAdminIsNotRemoved(Staff $staff, int $roleId, int $companyId): void
    {
        $staffUser = $staff->user;

        if (! $staffUser || ! $staffUser->isCompanyAdmin()) {
            return;
        }

        $newRoleSlug = strtolower((string) Role::query()
            ->where('company_id', $companyId)
            ->whereKey($roleId)
            ->value('slug'));

        if (in_array($newRoleSlug, ['company_admin', 'companyadmin'], true)) {
            return;
        }

        if ((int) $staffUser->id === (int) Auth::id()) {
            throw ValidationException::withMessages([
                'role_id' => ['You cannot remove your own company admin role.'],
            ]);
        }

        $hasOtherCompanyAdmin = User::query()
            ->where('company_id', $companyId)
            ->whereKeyNot($staffUser->id)
            ->where(function ($query) {
                $query->whereIn('role', ['company_admin', 'companyadmin'])
                    ->orWhereHas('roleRecord', function ($roleQuery) {
                        $roleQuery->whereIn('slug', ['company_admin', 'companyadmin']);
                    });
            })
            ->exists();

        if (! $hasOtherCompanyAdmin) {
            throw ValidationException::withMessages([
                'role_id' => ['This is the only company admin. Assign another company admin before changing this role.'],
            ]);
        }
    }

    private function ensureCanAccessStaff(Staff $staff): void
    {
        $user = Auth::user();
        if ($user->isCompanyAdmin()) {
            return;
        }

        $this->ensureSelfManagePermission();

        if ((int) $staff->user_id !== (int) $user->id) {
            abort(403, 'You can manage only your own staff details.');
        }
    }

    private function ensureCompanyAdmin(): void
    {
        if (!Auth::user()->isCompanyAdmin()) {
            abort(403, 'Only company admin can manage team members.');
        }
    }

    private function ensureSelfManagePermission(): void
    {
        if (!Auth::user()->hasPermission('teams.staff.self.manage')) {
            abort(403, 'You do not have permission to manage your own staff details.');
        }
    }

    private function staffRules(int $companyId, ?Staff $staff = null, bool $requireLinkedUser = false, bool $requireEmployeeId = false): array
    {
        $rules = [
            'name' => ['required', 'string', 'min:2', 'max:191'],
            'contact_number' => ['required', 'digits:10'],
            'department' => ['nullable', 'string', 'min:2', 'max:191'],
            'join_date' => ['required', 'date', 'before_or_equal:today'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];

        if ($requireLinkedUser) {
            $rules['user_id'] = [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
                Rule::unique('staff', 'user_id'),
            ];
        }

        if ($requireLinkedUser || Auth::user()->isCompanyAdmin()) {
            $rules['role_id'] = [
                'required',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ];
        }

        if ($requireEmployeeId) {
            $rules['employee_id'] = [
                'required',
                'string',
                'min:3',
                'max:191',
                Rule::unique('staff', 'employee_id')
                    ->where(fn ($query) => $query->where('company_id', $companyId))
                    ->ignore($staff?->id),
            ];
        }

        return $rules;
    }

    private function staffValidationMessages(): array
    {
        return [
            'company_subscription_id.required' => 'Please select a plan.',
            'company_subscription_id.in' => 'The selected plan is invalid for this company.',
            'user_id.required' => 'Please select a linked user.',
            'user_id.exists' => 'The selected user is invalid for this company.',
            'user_id.unique' => 'The selected user already has a staff profile.',
            'name.required' => 'Staff name is required.',
            'name.min' => 'Staff name must be at least 2 characters.',
            'name.max' => 'Staff name may not be greater than 191 characters.',
            'employee_id.required' => 'Employee ID is required.',
            'employee_id.min' => 'Employee ID must be at least 3 characters.',
            'employee_id.max' => 'Employee ID may not be greater than 191 characters.',
            'employee_id.unique' => 'This employee ID is already in use for your company.',
            'role_id.required' => 'Please select a role.',
            'role_id.exists' => 'The selected role is invalid for this company.',
            'contact_number.required' => 'Contact number is required.',
            'contact_number.digits' => 'Contact number must be exactly 10 digits.',
            'department.min' => 'Department must be at least 2 characters.',
            'department.max' => 'Department may not be greater than 191 characters.',
            'join_date.required' => 'Joining date is required.',
            'join_date.date' => 'Please enter a valid joining date.',
            'join_date.before_or_equal' => 'Joining date cannot be in the future.',
            'profile_photo.image' => 'Profile photo must be an image.',
            'profile_photo.mimes' => 'Profile photo must be a JPG, JPEG, PNG, or WEBP file.',
            'profile_photo.max' => 'Profile photo size must not exceed 10 MB.',
        ];
    }

    private function activeCompanyAdminCount(): int
    {
        return User::query()
            ->where('company_id', $this->companyId())
            ->where('is_active', true)
            ->where(function ($query) {
                $query
                    ->whereRaw('LOWER(role) = ?', ['company_admin'])
                    ->orWhereRaw('LOWER(role) = ?', ['companyadmin']);
            })
            ->count();
    }

    private function companyId(): int
    {
        return (int) Auth::user()->company_id;
    }
}
