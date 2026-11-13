<?php

namespace App\Jobs;

use App\Mail\DailyStaffUpdateReportMail;
use App\Models\Company;
use App\Models\DailyStaffUpdateReport;
use App\Services\CompanyMailService;
use App\Services\DailyStaffUpdateReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class GenerateCompanyDailyStaffUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(
        private readonly int $companyId,
        private readonly string $reportDate,
    ) {
    }

    public function handle(DailyStaffUpdateReportService $reportService, CompanyMailService $companyMailService): void
    {
        $company = Company::query()
            ->with(['mailSetting', 'users:id,company_id,name,email,role,is_active'])
            ->find($this->companyId);

        if (! $company) {
            return;
        }

        $timezone = $company->timezone ?: config('app.timezone');
        $reportDate = Carbon::createFromFormat('Y-m-d', $this->reportDate, $timezone)->startOfDay();

        $result = $reportService->generateForCompany($company, $reportDate, null, 'automatic', false);
        $report = $result['report'] ?? null;

        if (! $report instanceof DailyStaffUpdateReport) {
            return;
        }

        if ($report->emailed_at) {
            return;
        }

        $adminEmails = $company->users
            ->filter(fn ($user) => (bool) $user->is_active && in_array(strtolower(trim((string) $user->role)), ['company_admin', 'companyadmin'], true))
            ->pluck('email')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($adminEmails === []) {
            return;
        }

        try {
            $companyMailService->configure($company);
        } catch (RuntimeException $exception) {
            logger()->warning('Daily staff update email skipped because company mail settings are missing or inactive.', [
                'company_id' => $company->id,
                'report_id' => $report->id,
            ]);

            return;
        }

        $primaryRecipient = array_shift($adminEmails);

        try {
            $message = Mail::to($primaryRecipient);

            if ($adminEmails !== []) {
                $message->bcc($adminEmails);
            }

            $message->send(new DailyStaffUpdateReportMail($company, $report->fresh(['generatedBy:id,name'])));

            $report->forceFill(['emailed_at' => now()])->saveQuietly();
        } catch (Throwable $exception) {
            throw $exception;
        }
    }
}
