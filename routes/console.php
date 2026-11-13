<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\AmcTechnicianAssignmentService;
use App\Models\Company;
use App\Jobs\GenerateCompanyDailyStaffUpdateJob;
use Illuminate\Support\Carbon;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('campaigns:run-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('campaigns:process-bulk')->everyMinute()->withoutOverlapping();
Artisan::command('daily-staff-update:run {--force : Regenerate reports even when one already exists for the day}', function () {
    $now = now();
    $matched = 0;
    $generated = 0;

    Company::query()
        ->where('enable_daily_staff_update', true)
        ->whereNotNull('daily_staff_update_time')
        ->orderBy('id')
        ->chunkById(100, function ($companies) use (&$matched, &$generated): void {
            foreach ($companies as $company) {
                $timezone = $company->timezone ?: config('app.timezone');
                $currentCompanyTime = now($timezone);
                $scheduledCompanyTime = Carbon::parse($company->daily_staff_update_time, $timezone);

                if ($currentCompanyTime->lt($scheduledCompanyTime)) {
                    continue;
                }

                $matched++;
                GenerateCompanyDailyStaffUpdateJob::dispatchSync(
                    $company->id,
                    now($timezone)->toDateString()
                );
                $generated++;
            }
        });

    $this->info('Daily staff update scheduler checked companies at ' . $now->format('H:i') . '.');
    $this->info('Matched: ' . $matched . ', Generated jobs: ' . $generated);
})->purpose('Generate daily staff update reports for companies with matching schedule');

Schedule::command('daily-staff-update:run')->everyMinute()->withoutOverlapping();
Artisan::command('amc:assign-technicians {date?}', function (?string $date = null) {
    $assignmentDate = $date ? Carbon::parse($date)->startOfDay() : now()->startOfDay();
    $summary = app(AmcTechnicianAssignmentService::class)->assignDueVisits($assignmentDate);

    $this->info('AMC technician assignment completed for ' . $summary['target_date']);
    $this->info('Assigned: ' . $summary['assigned'] . ', Skipped: ' . $summary['skipped']);
})->purpose('Auto assign AMC visits to technicians');

Schedule::command('amc:assign-technicians')->dailyAt('00:00');
Schedule::command('database:backup')->hourly()->withoutOverlapping();
// Examples for the Google Drive backup scheduler:
// Schedule::command('backup:google-drive')->hourly()->withoutOverlapping();
// Schedule::command('backup:google-drive')->weeklyOn(1, '02:00')->withoutOverlapping();
// Schedule::command('backup:google-drive')->monthlyOn(1, '02:00')->withoutOverlapping();
Schedule::command('backup:google-drive')->dailyAt('02:00')->withoutOverlapping();
Schedule::command('tasks:send-reminders')->dailyAt('11:00')->withoutOverlapping();
Schedule::command('tasks:send-reminders')->everyMinute()->withoutOverlapping();

Artisan::command('subscriptions:prune-pending', function () {
    $removed = app(\App\Services\RecurringSubscriptionService::class)->prunePending();

    $this->info('Removed ' . $removed . ' abandoned auto-renew checkout(s).');
})->purpose('Remove auto-renew checkouts that were started but never authorised');

Schedule::command('subscriptions:prune-pending')->dailyAt('03:00')->withoutOverlapping();
