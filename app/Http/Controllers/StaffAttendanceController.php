<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\StaffAttendance;
use App\Models\StaffActivity;
use App\Models\Staff;
use App\Services\StaffAttendanceClockService;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly StaffAttendanceClockService $clockService
    ) {
    }

    public function store(Request $request)
    {
        $this->ensureCompanyAdmin();

        $request->validate([
            'attendance' => 'required|array',
            'attendance.*.status' => 'required|in:Present,Absent,Half Day,Leave,Holiday',
            'attendance.*.check_in_time' => 'nullable|date_format:H:i',
            'attendance.*.check_out_time' => 'nullable|date_format:H:i',
            'attendance.*.overtime_hours' => 'nullable|numeric|min:0',
            'date' => 'nullable|date',
        ]);

        $date = Carbon::parse($request->input('date', now()->toDateString()))->toDateString();

        foreach($request->attendance as $staffId => $data){
            $staff = $this->resolveStaffOrFail($staffId);
            $this->validateTimeRange($data['check_in_time'] ?? null, $data['check_out_time'] ?? null);

            StaffAttendance::updateOrCreate(
                ['staff_id' => $staff->id, 'date' => $date],
                [
                    'status' => $data['status'],
                    'check_in_time' => $data['check_in_time'] ?? null,
                    'check_out_time' => $data['check_out_time'] ?? null,
                    'overtime_hours' => $data['overtime_hours'] ?? 0,
                ]
            );
            StaffActivity::create([
                'staff_id' => $staff->id,
                'type' => 'Staff Attendance',
                'description' => 'Staff Attendance Marked',
                'occurred_at' => now(),
            ]);
        }

        return $this->successResponse($request, 'Attendance recorded.');
    }

    public function markIndividual(Request $request)
    {
        $this->ensureCompanyAdmin();

        $request->validate([
            'staff_id' => 'required|exists:staff,id',
            'date' => 'required|date',
            'status' => 'required|in:Present,Absent,Half Day,Leave,Holiday',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
            'overtime_hours' => 'nullable|numeric|min:0',
            'leave_reason' => 'nullable|string|max:255',
            'correction_reason' => 'nullable|string|max:255',
        ]);

        $staff = $this->resolveStaffOrFail($request->staff_id);
        $this->validateTimeRange($request->check_in_time, $request->check_out_time);
        $attendanceDate = Carbon::parse($request->date)->toDateString();
        $today = now()->toDateString();
        $isPastDate = $attendanceDate < $today;

        if ($request->status === 'Leave' && trim((string) $request->leave_reason) === '') {
            throw ValidationException::withMessages([
                'leave_reason' => 'Leave reason is required when status is Leave.',
            ]);
        }

        if ($isPastDate && trim((string) $request->correction_reason) === '') {
            throw ValidationException::withMessages([
                'correction_reason' => 'Correction reason is required for past date attendance updates.',
            ]);
        }

        StaffAttendance::updateOrCreate(
            ['staff_id' => $staff->id, 'date' => $attendanceDate],
            [
                'status' => $request->status,
                'check_in_time' => $request->check_in_time,
                'check_out_time' => $request->check_out_time,
                'overtime_hours' => in_array($request->status, ['Present', 'Half Day'], true) ? ($request->overtime_hours ?? 0) : 0,
                'leave_reason' => $request->status === 'Leave' ? $request->leave_reason : null,
                'correction_reason' => $isPastDate ? $request->correction_reason : null,
                'is_corrected' => $isPastDate,
            ]
        );

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Individual Attendance',
            'description' => 'Attendance marked for ' . $request->date,
            'occurred_at' => now(),
        ]);

        return $this->successResponse($request, 'Attendance marked successfully.');
    }

    public function markBulk(Request $request)
    {
        $this->ensureCompanyAdmin();
        $this->ensureBulkAttendancePermission();

        $request->validate([
            'attendance' => 'required|array',
            'attendance.*' => 'array',
            'attendance.*.*.status' => 'required|in:Present,Absent,Half Day,Leave,Holiday',
            'attendance.*.*.overtime_hours' => 'nullable|numeric|min:0',
            'attendance.*.*.leave_reason' => 'nullable|string|max:255',
            'attendance.*.*.correction_reason' => 'nullable|string|max:255',
            'attendance.*.*.touched' => 'nullable|boolean',
        ]);

        $today = now()->toDateString();
        $touchedCount = 0;

        foreach ($request->attendance as $staffId => $dateRows) {
            $staff = $this->resolveStaffOrFail($staffId);

            foreach ($dateRows as $date => $data) {
                if ((int) ($data['touched'] ?? 0) !== 1) {
                    continue;
                }

                $touchedCount++;
                $rowDate = Carbon::parse($date)->toDateString();
                if ($rowDate > $today) {
                    throw ValidationException::withMessages([
                        'attendance' => 'Future date attendance updates are not allowed.',
                    ]);
                }

                $isPastDate = $rowDate < $today;
                $status = (string) ($data['status'] ?? 'Absent');
                $leaveReason = trim((string) ($data['leave_reason'] ?? ''));
                $correctionReason = trim((string) ($data['correction_reason'] ?? ''));
                $overtime = (float) ($data['overtime_hours'] ?? 0);

                if (!in_array($status, ['Present', 'Half Day'], true)) {
                    $overtime = 0;
                }

                if ($status === 'Leave' && $leaveReason === '') {
                    throw ValidationException::withMessages([
                        'attendance' => 'Leave reason is required when status is Leave.',
                    ]);
                }

                if ($isPastDate && $correctionReason === '') {
                    throw ValidationException::withMessages([
                        'attendance' => 'Correction reason is required for past-date attendance update.',
                    ]);
                }

                StaffAttendance::updateOrCreate(
                    ['staff_id' => $staff->id, 'date' => $rowDate],
                    [
                        'status' => $status,
                        'check_in_time' => null,
                        'check_out_time' => null,
                        'overtime_hours' => $overtime,
                        'leave_reason' => $status === 'Leave' ? $leaveReason : null,
                        'correction_reason' => $isPastDate ? $correctionReason : null,
                        'is_corrected' => $isPastDate,
                    ]
                );

                StaffActivity::create([
                    'staff_id' => $staff->id,
                    'type' => 'Bulk Attendance',
                    'description' => ($isPastDate ? 'Attendance corrected for ' : 'Attendance marked for ') . $rowDate,
                    'occurred_at' => now(),
                ]);
            }
        }

        if ($touchedCount === 0) {
            throw ValidationException::withMessages([
                'attendance' => 'No attendance changes detected. Please mark at least one date.',
            ]);
        }

        return $this->successResponse($request, 'Bulk attendance marked.');
    }

    public function checkInOut(Request $request, string $staffId)
    {
        $staff = $this->resolveStaffOrFail($staffId);
        $this->ensureCanAccessStaff($staff);

        $request->validate([
            'type' => 'required|in:check_in,check_out,overtime',
            'overtime_hours' => 'nullable|numeric|min:0',
        ]);

        $context = [
            'ip_address' => $request->ip(),
            'device' => $request->userAgent(),
        ];

        if ($request->type === 'check_in') {
            $attendance = $this->clockService->clockIn($staff, $context);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Check-in recorded.',
                    'attendance' => $this->clockService->formatAttendance($attendance),
                ]);
            }

            return $this->successResponse($request, 'Check-in recorded.');
        }

        if ($request->type === 'check_out') {
            $attendance = $this->clockService->clockOut($staff, $context);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Check-out recorded.',
                    'attendance' => $this->clockService->formatAttendance($attendance),
                ]);
            }

            return $this->successResponse($request, 'Check-out recorded.');
        }

        $today = now()->toDateString();
        $attendance = StaffAttendance::firstOrCreate(
            ['staff_id' => $staff->id, 'date' => $today],
            ['status' => 'Present']
        );

        $attendance->overtime_hours = $request->overtime_hours;
        $attendance->save();

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Check Overtime',
            'description' => 'Overtime recorded',
            'occurred_at' => now(),
        ]);

        return $this->successResponse($request, 'Overtime recorded.');
    }

    public function correctAttendance(Request $request, $staffId, $date)
    {
        $this->ensureCompanyAdmin();

        $request->validate([
            'status' => 'required|in:Present,Absent,Half Day,Leave,Holiday',
            'check_in_time' => 'nullable|date_format:H:i',
            'check_out_time' => 'nullable|date_format:H:i',
            'overtime_hours' => 'nullable|numeric|min:0',
            'leave_reason' => 'nullable|string|max:255',
            'correction_reason' => 'required|string|max:255',
        ]);

        $staff = $this->resolveStaffOrFail($staffId);
        $this->validateTimeRange($request->check_in_time, $request->check_out_time);
        $attendance = StaffAttendance::where('staff_id', $staff->id)->where('date', $date)->firstOrFail();

        $attendance->update([
            'status' => $request->status,
            'check_in_time' => $request->check_in_time,
            'check_out_time' => $request->check_out_time,
            'overtime_hours' => $request->overtime_hours ?? 0,
            'leave_reason' => $request->leave_reason,
            'correction_reason' => $request->correction_reason,
            'is_corrected' => true,
        ]);

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Attendance Corrected',
            'description' => 'Attendance corrected for ' . $date . ' Reason: ' . $request->correction_reason,
            'occurred_at' => now(),
        ]);

        return $this->successResponse($request, 'Attendance corrected.');
    }

    public function monthlyCalendar($staffId, $month, $year)
    {
        [$month, $year] = $this->normalizeMonthYear($month, $year);
        $staff = $this->resolveStaffOrFail($staffId);
        $this->ensureCanAccessStaff($staff);

        $attendances = StaffAttendance::where('staff_id', $staff->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get(['date','status','check_in_time','check_out_time','overtime_hours', 'leave_reason', 'is_corrected']);

        $summary = $this->buildSummary($attendances);

        return response()->json([
            'calendar' => $attendances,
            'summary' => $summary,
        ]);
    }

    public function exportCsv(Request $request, $staffId, $month, $year)
    {
        [$month, $year] = $this->normalizeMonthYear($month, $year);
        $staff = $this->resolveStaffOrFail($staffId);
        $this->ensureCanAccessStaff($staff);

        $attendances = StaffAttendance::where('staff_id', $staff->id)
            ->whereMonth('date',$month)
            ->whereYear('date',$year)
            ->orderBy('date')
            ->get();

        $fileName = 'attendance-' . $staff->id . '-' . str_pad((string) $month, 2, '0', STR_PAD_LEFT) . '-' . $year . '.csv';

        return response()->streamDownload(function () use ($attendances) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Status', 'Check In', 'Check Out', 'Overtime Hours', 'Leave Reason', 'Correction Reason', 'Corrected']);

            foreach ($attendances as $attendance) {
                fputcsv($handle, [
                    $attendance->date,
                    $attendance->status,
                    $attendance->check_in_time,
                    $attendance->check_out_time,
                    $attendance->overtime_hours,
                    $attendance->leave_reason,
                    $attendance->correction_reason,
                    $attendance->is_corrected ? 'Yes' : 'No',
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportBulkExcel(Request $request)
    {
        $this->ensureCompanyAdmin();
        $this->ensureBulkAttendancePermission();

        $monthKey = (string) $request->input('month', now()->format('Y-m'));
        try {
            $monthDate = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'month' => 'Invalid month format.',
            ]);
        }
        $year = (int) $monthDate->format('Y');
        $month = (int) $monthDate->format('m');
        $daysInMonth = (int) $monthDate->daysInMonth;

        $staffs = Staff::query()
            ->where('company_id', (int) Auth::user()->company_id)
            ->orderBy('name')
            ->get(['id', 'employee_id', 'name']);

        $attendance = StaffAttendance::query()
            ->whereIn('staff_id', $staffs->pluck('id'))
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->get()
            ->groupBy('staff_id');

        $fileName = 'bulk-attendance-' . $monthDate->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($staffs, $attendance, $daysInMonth, $monthDate) {
            $handle = fopen('php://output', 'w');

            $headers = ['Emp ID', 'Emp Name'];
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $headers[] = $day . '-' . $monthDate->format('M');
            }
            $headers[] = 'Present';
            $headers[] = 'Total Days';
            $headers[] = 'Attendance %';
            fputcsv($handle, $headers);

            foreach ($staffs as $staff) {
                $staffRows = $attendance->get($staff->id, collect())->keyBy(function ($row) {
                    return (int) Carbon::parse($row->date)->format('d');
                });

                $presentEquivalent = 0.0;
                $line = [$staff->employee_id, $staff->name];
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    $status = $staffRows->get($day)?->status ?? 'Absent';
                    $line[] = match ($status) {
                        'Present' => 'P',
                        'Half Day' => 'HD',
                        'Leave' => 'L',
                        'Holiday' => 'H',
                        default => 'A',
                    };

                    if ($status === 'Present') {
                        $presentEquivalent += 1;
                    } elseif ($status === 'Half Day') {
                        $presentEquivalent += 0.5;
                    }
                }

                $line[] = $presentEquivalent;
                $line[] = $daysInMonth;
                $line[] = $daysInMonth > 0 ? round(($presentEquivalent / $daysInMonth) * 100, 2) . '%' : '0%';
                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function report($staffId, $month, $year) {
        [$month, $year] = $this->normalizeMonthYear($month, $year);
        $staff = $this->resolveStaffOrFail($staffId);
        $this->ensureCanAccessStaff($staff);

        $attendance = StaffAttendance::where('staff_id', $staff->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderBy('date')
            ->get();

        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => 'Staff Report',
            'description' => 'Staff Report Generated',
            'occurred_at' => now(),
        ]);

        return response()->json([
            'staff_id' => $staff->id,
            'month' => $month,
            'year' => $year,
            'summary' => $this->buildSummary($attendance),
            'records' => $attendance,
        ]);
    }

    private function resolveStaffOrFail(string|int $staffId): Staff
    {
        $staffId = Staff::decodeRouteKeyValue($staffId);

        return Staff::query()
            ->where('company_id', (int) Auth::user()->company_id)
            ->findOrFail($staffId);
    }

    private function ensureCompanyAdmin(): void
    {
        if (!Auth::user()->isCompanyAdmin()) {
            abort(403, 'Only company admin can manage team attendance.');
        }
    }

    private function ensureCanAccessStaff(Staff $staff): void
    {
        $user = Auth::user();
        if ($user->isCompanyAdmin()) {
            return;
        }

        if (!$user->hasPermission('teams.staff.self.manage') || (int) $staff->user_id !== (int) $user->id) {
            abort(403, 'You can manage attendance only for your own profile.');
        }
    }

    private function ensureBulkAttendancePermission(): void
    {
        $user = Auth::user();
        if (!$user->hasPermission('teams.staff.attendance.bulk.manage')) {
            abort(403, 'You do not have permission to manage bulk attendance.');
        }
    }

    private function buildSummary($attendances): array
    {
        return [
            'present' => $attendances->where('status', 'Present')->count(),
            'absent' => $attendances->where('status', 'Absent')->count(),
            'half_day' => $attendances->where('status', 'Half Day')->count(),
            'leave' => $attendances->where('status', 'Leave')->count(),
            'holiday' => $attendances->where('status', 'Holiday')->count(),
            'corrected' => $attendances->where('is_corrected', true)->count(),
            'overtime_hours' => (float) $attendances->sum('overtime_hours'),
        ];
    }

    private function normalizeMonthYear($month, $year): array
    {
        $month = (int) $month;
        $year = (int) $year;

        if ($month < 1 || $month > 12) {
            abort(422, 'Invalid month.');
        }

        if ($year < 2000 || $year > 2100) {
            abort(422, 'Invalid year.');
        }

        return [$month, $year];
    }

    private function validateTimeRange(?string $checkIn, ?string $checkOut): void
    {
        if ($checkIn && $checkOut) {
            $in = Carbon::createFromFormat('H:i', substr($checkIn, 0, 5));
            $out = Carbon::createFromFormat('H:i', substr($checkOut, 0, 5));
            if ($out->lessThanOrEqualTo($in)) {
                throw ValidationException::withMessages([
                    'check_out_time' => 'Check-out time must be after check-in time.',
                ]);
            }
        }
    }

    private function successResponse(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_OK);
        }

        return back()->with('success', $message);
    }
}
