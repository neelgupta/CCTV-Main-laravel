<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\StaffActivity;
use App\Models\StaffAttendance;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class StaffAttendanceClockService
{
    public function today(Staff $staff): array
    {
        $today = now()->toDateString();
        $attendance = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->where('date', $today)
            ->first();

        return $this->formatAttendance($attendance, $today);
    }

    public function clockIn(Staff $staff, array $context = []): StaffAttendance
    {
        $today = now()->toDateString();
        $attendance = StaffAttendance::firstOrCreate(
            ['staff_id' => $staff->id, 'date' => $today],
            ['status' => 'Present']
        );

        if ($attendance->check_in_time !== null) {
            throw ValidationException::withMessages([
                'clock_in' => 'Check-in already exists for today.',
            ]);
        }

        $attendance->check_in_time = now()->format('H:i:s');
        $attendance->status = 'Present';
        $attendance->save();

        $this->logActivity($staff, 'Check In', 'Check-in recorded', $context);

        return $attendance->fresh();
    }

    public function clockOut(Staff $staff, array $context = []): StaffAttendance
    {
        $today = now()->toDateString();
        $attendance = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->where('date', $today)
            ->first();

        if (! $attendance || $attendance->check_in_time === null) {
            throw ValidationException::withMessages([
                'clock_out' => 'Please check in first.',
            ]);
        }

        if ($attendance->check_out_time !== null) {
            throw ValidationException::withMessages([
                'clock_out' => 'Check-out already exists for today.',
            ]);
        }

        $attendance->check_out_time = now()->format('H:i:s');
        $attendance->save();

        $this->logActivity($staff, 'Check Out', 'Check-out recorded', $context);

        return $attendance->fresh();
    }

    public function monthly(Staff $staff, int $month, int $year): array
    {
        $this->normalizeMonthYear($month, $year);

        $attendances = StaffAttendance::query()
            ->where('staff_id', $staff->id)
            ->whereMonth('date', $month)
            ->whereYear('date', $year)
            ->orderByDesc('date')
            ->get([
                'date',
                'status',
                'check_in_time',
                'check_out_time',
                'overtime_hours',
                // 'leave_reason',
                // 'correction_reason',
                'is_corrected',
            ]);

        return [
            // 'records' => $attendances->map(fn (StaffAttendance $attendance) => $this->formatAttendanceHistory($attendance))->values()->all(),
            'summary' => $this->buildSummary($attendances),
        ];
    }

    public function formatAttendance(?StaffAttendance $attendance, ?string $fallbackDate = null): array
    {
        return [
            'date' => $attendance?->date?->toDateString() ?? $fallbackDate,
            'status' => $attendance?->status,
            'check_in_time' => $attendance?->check_in_time,
            'check_out_time' => $attendance?->check_out_time,
        ];
    }

    // Extend formatAttendance with operational/correction fields.
    // Swap formatAttendance → formatAttendanceDetailed in the controller or monthly()
    // whenever these fields are needed again.
    public function formatAttendanceHistory(StaffAttendance $attendance): array
    {
        return array_merge($this->formatAttendance($attendance), [
            'overtime_hours' => (float) ($attendance->overtime_hours ?? 0),
        ]);
    }

    public function formatAttendanceDetailed(?StaffAttendance $attendance, ?string $fallbackDate = null): array
    {
        return array_merge($this->formatAttendance($attendance, $fallbackDate), [
            'overtime_hours' => (float) ($attendance?->overtime_hours ?? 0),
            'leave_reason' => $attendance?->leave_reason,
            'correction_reason' => $attendance?->correction_reason,
            'is_corrected' => (bool) ($attendance?->is_corrected ?? false),
            'is_checked_in' => $attendance?->check_in_time !== null,
            'is_checked_out' => $attendance?->check_out_time !== null,
            'can_clock_in' => $attendance?->check_in_time === null,
            'can_clock_out' => $attendance?->check_in_time !== null && $attendance?->check_out_time === null,
        ]);
    }

    private function buildSummary(Collection $attendances): array
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

    private function logActivity(Staff $staff, string $type, string $description, array $context = []): void
    {
        StaffActivity::create([
            'staff_id' => $staff->id,
            'type' => $type,
            'description' => $description,
            'occurred_at' => now(),
            'ip_address' => $context['ip_address'] ?? null,
            'device' => $context['device'] ?? null,
        ]);
    }

    private function normalizeMonthYear(int $month, int $year): void
    {
        if ($month < 1 || $month > 12) {
            throw ValidationException::withMessages([
                'month' => 'Invalid month.',
            ]);
        }

        if ($year < 2000 || $year > 2100) {
            throw ValidationException::withMessages([
                'year' => 'Invalid year.',
            ]);
        }
    }
}
