<?php

namespace App\Http\Controllers;

use App\Models\StaffAdvance;
use App\Models\Company;
use App\Models\CompanyHoliday;
use App\Models\Staff;
use App\Models\StaffAttendance;
use App\Models\StaffSalaryPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffPayrollController extends Controller
{
    public function index()
    {
        $this->ensureCompanyAdmin('teams.staff.salary.view');

        $companyId = $this->companyId();
        $financialYear = $this->resolveFinancialYear(request()->string('fy')->toString());
        $staffCount = Staff::query()
            ->where('company_id', $companyId)
            ->whereHas('user')
            ->count();

        $startMonth = Carbon::create($financialYear['start_year'], 4, 1)->startOfMonth();
        $fiscalEndMonth = Carbon::create($financialYear['end_year'], 3, 1)->endOfMonth();
        $currentMonth = now()->startOfMonth();
        $endMonth = $currentMonth->lessThan($fiscalEndMonth) ? $currentMonth->copy()->endOfMonth() : $fiscalEndMonth;

        if ($endMonth->lessThan($startMonth)) {
            $endMonth = $startMonth->copy()->endOfMonth();
        }

        $records = StaffSalaryPayment::query()
            ->where('company_id', $companyId)
            ->whereBetween('salary_month', [$startMonth->toDateString(), $endMonth->copy()->endOfMonth()->toDateString()])
            ->get()
            ->groupBy(fn (StaffSalaryPayment $payment) => $payment->salary_month->format('Y-m'));

        $months = collect();
        $cursor = $startMonth->copy();

        $monthCount = (int) $startMonth->diffInMonths($endMonth) + 1;

        for ($i = 0; $i < $monthCount; $i++) {
            $monthKey = $cursor->format('Y-m');
            $monthRecords = $records->get($monthKey, collect());
            $recordCount = $monthRecords->count();
            $paidCount = $monthRecords->where('is_paid', true)->count();

            if ($recordCount === 0) {
                $status = 'Pending';
            } elseif ($paidCount === 0) {
                $status = 'Draft';
            } elseif ($paidCount >= $staffCount && $staffCount > 0) {
                $status = 'Paid';
            } else {
                $status = 'Partial';
            }

            $months->push([
                'month_key' => $monthKey,
                'month_label' => $cursor->format('F Y'),
                'status' => $status,
                'staff_count' => $staffCount,
                'record_count' => $recordCount,
                'paid_count' => $paidCount,
                'total_advance' => (float) $monthRecords->sum('advance_amount'),
                'route' => route('teams.payroll.show', ['month' => $monthKey, 'fy' => $financialYear['key']]),
            ]);

            $cursor->addMonthNoOverflow();
        }

        return view('teams.staff.salary', [
            'months' => $months,
            'financialYear' => $financialYear,
            'financialYears' => $this->financialYearOptions(),
        ]);
    }

    public function show(string $month)
    {
        $this->ensureCompanyAdmin('teams.staff.salary.view');

        $monthDate = $this->normalizeMonth($month);
        $companyId = $this->companyId();
        $financialYear = $this->resolveFinancialYear(request()->string('fy')->toString());
        $company = Company::query()->find($companyId);
        $workingDays = collect($company?->working_days ?? ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'])->values()->all();
        $holidayDates = CompanyHoliday::query()
            ->where('company_id', $companyId)
            ->whereYear('holiday_date', (int) $monthDate->format('Y'))
            ->whereMonth('holiday_date', (int) $monthDate->format('m'))
            ->get()
            ->mapWithKeys(fn ($holiday) => [$holiday->holiday_date->toDateString() => $holiday]);
        $workingDaysCount = $this->countWorkingDaysInMonth($monthDate, $workingDays, $holidayDates);

        $staffs = Staff::query()
            ->where('company_id', $companyId)
            ->whereHas('user')
            ->with(['user:id,name,email,role', 'salaryConfiguration'])
            ->orderBy('name')
            ->get();

        $advances = StaffAdvance::query()
            ->where('company_id', $companyId)
            ->with('staff:id,name')
            ->orderByDesc('advance_date')
            ->orderByDesc('id')
            ->get()
            ->groupBy('staff_id');

        $payments = StaffSalaryPayment::query()
            ->where('company_id', $companyId)
            ->whereDate('salary_month', $monthDate->toDateString())
            ->get()
            ->keyBy('staff_id');

        $monthRedirectTo = route('teams.payroll.show', [
            'month' => $monthDate->format('Y-m'),
            'fy' => $financialYear['key'],
        ]);

        $rows = $staffs->map(function (Staff $staff) use ($payments, $advances, $monthDate, $workingDays, $holidayDates, $monthRedirectTo) {
            $salary = $staff->salaryConfiguration?->total_salary;
            $payment = $payments->get($staff->id);
            $staffAdvances = $advances->get($staff->id, collect())
                ->filter(fn (StaffAdvance $advance) => $advance->advance_date->isSameMonth($monthDate))
                ->values();
            $advance = (float) $staffAdvances->sum('amount');
            $attendanceRows = StaffAttendance::query()
                ->where('staff_id', $staff->id)
                ->whereYear('date', (int) $monthDate->format('Y'))
                ->whereMonth('date', (int) $monthDate->format('m'))
                ->get();
            $presentEquivalent = (float) $attendanceRows->where('status', 'Present')->count()
                + ((float) $attendanceRows->where('status', 'Half Day')->count() * 0.5);
            $workingDaysCount = max(1, $this->countWorkingDaysInMonth($monthDate, $workingDays, $holidayDates));
            $perDayRate = round(((float) ($salary ?? 0)) / $workingDaysCount, 2);
            $grossPayable = round($perDayRate * $presentEquivalent, 2);
            $netPayable = max(0, $grossPayable - $advance);

            return [
                'staff' => $staff,
                'salary' => (float) ($salary ?? 0),
                'working_days_count' => $workingDaysCount,
                'per_day_rate' => $perDayRate,
                'present_equivalent' => $presentEquivalent,
                'gross_payable' => $grossPayable,
                'advance' => $advance,
                'net_payable' => $netPayable,
                'advance_count' => $staffAdvances->count(),
                'advance_history' => $staffAdvances->map(function (StaffAdvance $record) use ($monthRedirectTo) {
                    return [
                        'id' => $record->id,
                        'date' => optional($record->advance_date)->format('d M Y'),
                        'amount' => (float) $record->amount,
                        'notes' => $record->notes,
                        'edit_url' => route('teams.payroll.advance.edit', [
                            'advance' => $record->id,
                            'redirect_to' => $monthRedirectTo,
                        ]),
                        'delete_url' => route('teams.payroll.advance.destroy', ['advance' => $record->id]),
                    ];
                })->values(),
                'is_paid' => (bool) ($payment?->is_paid ?? false),
                'payment_id' => $payment?->id,
                'paid_at' => $payment?->paid_at,
            ];
        });

        $summary = [
            'staff_count' => $rows->count(),
            'working_days' => $workingDaysCount,
            'total_salary' => (float) $rows->sum('salary'),
            'total_advance' => (float) $rows->sum('advance'),
            'total_net_payable' => (float) $rows->sum('net_payable'),
            'paid_count' => (int) $rows->where('is_paid', true)->count(),
        ];

        return view('teams.staff.salary-month', [
            'monthDate' => $monthDate,
            'monthKey' => $monthDate->format('Y-m'),
            'financialYear' => $financialYear,
            'workingDaysCount' => $workingDaysCount,
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }

    public function advanceIndex()
    {
        $this->ensureCompanyAdmin('teams.staff.salary.advance.view');

        $companyId = $this->companyId();
        $canManageAdvance = Auth::user()->isCompanyAdmin() || Auth::user()->hasPermission('teams.staff.salary.advance.manage');
        $editingAdvance = null;

        $editAdvanceId = request()->integer('edit');
        if ($editAdvanceId) {
            $editingAdvance = StaffAdvance::query()
                ->where('company_id', $companyId)
                ->with('staff:id,name,employee_id')
                ->find($editAdvanceId);

            abort_unless($editingAdvance, 404);
        }

        $staffs = Staff::query()
            ->where('company_id', $companyId)
            ->whereHas('user')
            ->with(['user:id,name,email,role', 'salaryConfiguration'])
            ->orderBy('name')
            ->get();

        $advanceQuery = StaffAdvance::query()
            ->where('company_id', $companyId)
            ->with('staff:id,name')
            ->latest('advance_date')
            ->latest('id');

        $advances = (clone $advanceQuery)
            ->paginate(10)
            ->appends(request()->except(['page', 'edit', 'redirect_to']));

        $totalAdvance = (float) (clone $advanceQuery)->sum('amount');

        return view('teams.staff.advance', [
            'staffs' => $staffs,
            'advances' => $advances,
            'totalAdvance' => $totalAdvance,
            'canManageAdvance' => $canManageAdvance,
            'editingAdvance' => $editingAdvance,
            'redirectTo' => request()->string('redirect_to')->toString(),
        ]);
    }

    public function advanceStore(Request $request)
    {
        $this->ensureCompanyAdmin('teams.staff.salary.advance.manage');
        $companyId = $this->companyId();

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $staff = Staff::query()
            ->where('company_id', $companyId)
            ->where('id', $validated['staff_id'])
            ->firstOrFail();

        StaffAdvance::create([
            'company_id' => $companyId,
            'staff_id' => $staff->id,
            'advance_date' => Carbon::parse($validated['advance_date'])->toDateString(),
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ]);

        StaffAdvance::syncMonthlyPayrollAdvanceAmount($companyId, $staff->id, $validated['advance_date']);

        return back()->with('success', 'Advance saved successfully.');
    }

    public function advanceEdit(Request $request, StaffAdvance $advance)
    {
        $this->ensureCompanyAdmin('teams.staff.salary.advance.manage');

        $companyId = $this->companyId();
        abort_unless((int) $advance->company_id === $companyId, 404);

        $redirectParams = [
            'edit' => $advance->id,
            'redirect_to' => (string) $request->input('redirect_to', route('teams.payroll.advance.index')),
        ];

        if ($request->filled('page')) {
            $redirectParams['page'] = $request->integer('page');
        }

        return redirect()->route('teams.payroll.advance.index', [
            ...$redirectParams,
        ]);
    }

    public function advanceUpdate(Request $request, StaffAdvance $advance)
    {
        $this->ensureCompanyAdmin('teams.staff.salary.advance.manage');

        $companyId = $this->companyId();
        abort_unless((int) $advance->company_id === $companyId, 404);

        $validated = $request->validate([
            'staff_id' => ['required', 'integer', 'exists:staff,id'],
            'advance_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $staff = Staff::query()
            ->where('company_id', $companyId)
            ->where('id', $validated['staff_id'])
            ->firstOrFail();

        $originalStaffId = (int) $advance->staff_id;
        $originalAdvanceDate = $advance->advance_date?->toDateString();

        $advance->update([
            'staff_id' => $staff->id,
            'advance_date' => Carbon::parse($validated['advance_date'])->toDateString(),
            'amount' => $validated['amount'],
            'notes' => $validated['notes'] ?? null,
        ]);

        if ($originalAdvanceDate !== null) {
            StaffAdvance::syncMonthlyPayrollAdvanceAmount($companyId, $originalStaffId, $originalAdvanceDate);
        }

        StaffAdvance::syncMonthlyPayrollAdvanceAmount($companyId, $staff->id, $validated['advance_date']);

        $redirectTo = (string) $request->input('redirect_to', route('teams.payroll.advance.index'));
        if ($redirectTo === '' || !str_starts_with($redirectTo, url('/'))) {
            $redirectTo = route('teams.payroll.advance.index');
        }

        return redirect()->to($redirectTo)->with('success', 'Advance updated successfully.');
    }

    public function advanceDestroy(Request $request, StaffAdvance $advance)
    {
        $this->ensureCompanyAdmin('teams.staff.salary.advance.manage');

        $companyId = $this->companyId();
        abort_unless((int) $advance->company_id === $companyId, 404);

        $staffId = (int) $advance->staff_id;
        $advanceDate = $advance->advance_date?->toDateString();

        $advance->delete();

        if ($advanceDate !== null) {
            StaffAdvance::syncMonthlyPayrollAdvanceAmount($companyId, $staffId, $advanceDate);
        }

        $redirectTo = (string) $request->input('redirect_to', '');
        $defaultRedirect = route('teams.payroll.advance.index');

        if ($redirectTo === '' || !str_starts_with($redirectTo, url('/'))) {
            $redirectTo = $defaultRedirect;
        }

        return redirect()->to($redirectTo)->with('success', 'Advance deleted successfully.');
    }

    public function store(Request $request, string $month)
    {
        $this->ensureCompanyAdmin('teams.staff.salary.manage');

        $monthDate = $this->normalizeMonth($month);
        $companyId = $this->companyId();

        $validated = $request->validate([
            'payroll' => ['required', 'array'],
            'payroll.*.is_paid' => ['nullable', 'in:0,1'],
        ]);

        $allowedStaffIds = Staff::query()
            ->where('company_id', $companyId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $monthAdvances = StaffAdvance::query()
            ->where('company_id', $companyId)
            ->whereBetween('advance_date', [$monthDate->copy()->startOfMonth()->toDateString(), $monthDate->copy()->endOfMonth()->toDateString()])
            ->get()
            ->groupBy('staff_id');

        foreach ($validated['payroll'] as $staffId => $row) {
            if (!in_array((int) $staffId, $allowedStaffIds, true)) {
                continue;
            }

            $isPaid = isset($row['is_paid']) && (string) $row['is_paid'] === '1';
            $advance = (float) ($monthAdvances->get((int) $staffId, collect())->sum('amount'));

            StaffSalaryPayment::updateOrCreate(
                [
                    'company_id' => $companyId,
                    'staff_id' => (int) $staffId,
                    'salary_month' => $monthDate->toDateString(),
                ],
                [
                    'advance_amount' => $advance,
                    'is_paid' => $isPaid,
                    'paid_at' => $isPaid ? now() : null,
                ]
            );
        }

        return redirect()
            ->route('teams.payroll.show', [
                'month' => $monthDate->format('Y-m'),
                'fy' => request()->string('fy')->toString() ?: $this->currentFinancialYearKey(),
            ])
            ->with('success', 'Payroll updated successfully.');
    }

    private function resolveFinancialYear(?string $fyKey = null): array
    {
        $options = $this->financialYearOptions();
        $currentKey = $this->currentFinancialYearKey();

        if ($fyKey && isset($options[$fyKey])) {
            return $options[$fyKey];
        }

        return $options[$currentKey] ?? array_values($options)[0];
    }

    private function currentFinancialYearKey(): string
    {
        $year = (int) now()->year;

        if ((int) now()->month >= 4) {
            return $year . '-' . ($year + 1);
        }

        return ($year - 1) . '-' . $year;
    }

    private function financialYearOptions(): array
    {
        $currentYear = (int) now()->year;
        $options = [];

        for ($offset = -4; $offset <= 0; $offset++) {
            $startYear = $currentYear + $offset;
            $endYear = $startYear + 1;
            $key = $startYear . '-' . $endYear;
            $options[$key] = [
                'key' => $key,
                'label' => 'FY ' . $startYear . '-' . substr((string) $endYear, -2),
                'start_year' => $startYear,
                'end_year' => $endYear,
            ];
        }

        return $options;
    }

    private function normalizeMonth(string $month): Carbon
    {
        try {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        } catch (\Throwable $e) {
            abort(404, 'Invalid month selected.');
        }
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

    private function ensureCompanyAdmin(string $permission = 'teams.staff.salary.manage'): void
    {
        $user = Auth::user();
        if (!$user) {
            abort(401);
        }

        if ($user->isCompanyAdmin()) {
            return;
        }

        if ($user->hasPermission($permission)) {
            return;
        }

        abort(403, 'Insufficient permissions to manage payroll.');
    }

    private function companyId(): int
    {
        return (int) Auth::user()->company_id;
    }
}
