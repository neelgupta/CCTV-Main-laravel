<?php

namespace App\Modules\Accounting\Services;

use Carbon\Carbon;
use Illuminate\Http\Request;

class AccountingReportService
{
    public function periodFromRequest(Request $request): array
    {
        $period = (string) $request->string('period', 'month');
        $today = Carbon::today();

        return match ($period) {
            'today' => [$today->copy(), $today->copy(), 'Today'],
            'week' => [$today->copy()->startOfWeek(), $today->copy()->endOfWeek(), 'This Week'],
            'month' => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), 'This Month'],
            'year' => [$today->copy()->startOfYear(), $today->copy()->endOfYear(), 'Current Year'],
            'custom' => [
                Carbon::parse($request->input('date_from', $today->toDateString())),
                Carbon::parse($request->input('date_to', $today->toDateString())),
                'Custom Date',
            ],
            default => [$today->copy()->startOfMonth(), $today->copy()->endOfMonth(), 'This Month'],
        };
    }
}
