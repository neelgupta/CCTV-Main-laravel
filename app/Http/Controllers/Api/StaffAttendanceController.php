<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Staff;
use App\Models\User;
use App\Services\StaffAttendanceClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffAttendanceController extends Controller
{
    public function __construct(
        private readonly StaffAttendanceClockService $clockService
    ) {
    }

    public function today(Request $request): JsonResponse
    {
        $staff = $this->resolveStaff($request);

        return response()->json([
            'attendance' => $this->clockService->today($staff),
        ]);
    }

    public function clockIn(Request $request): JsonResponse
    {
        $staff = $this->resolveStaff($request);
        $attendance = $this->clockService->clockIn($staff, $this->context($request));

        return response()->json([
            'message' => 'Clock-in recorded.',
            'attendance' => $this->clockService->formatAttendance($attendance),
        ]);
    }

    public function clockOut(Request $request): JsonResponse
    {
        $staff = $this->resolveStaff($request);
        $attendance = $this->clockService->clockOut($staff, $this->context($request));

        return response()->json([
            'message' => 'Clock-out recorded.',
            'attendance' => $this->clockService->formatAttendance($attendance),
        ]);
    }

    public function monthly(Request $request, int $month, int $year): JsonResponse
    {
        $staff = $this->resolveStaff($request);

        return response()->json($this->clockService->monthly($staff, $month, $year));
    }

    private function resolveStaff(Request $request): Staff
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user && $user->staff, 403, 'No linked staff profile found.');

        return $user->staff;
    }

    private function context(Request $request): array
    {
        return [
            'ip_address' => $request->ip(),
            'device' => $request->userAgent(),
        ];
    }
}
