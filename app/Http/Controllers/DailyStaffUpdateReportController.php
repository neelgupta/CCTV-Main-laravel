<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\DailyStaffUpdateReport;
use App\Services\DailyStaffUpdateReportService;
use App\Services\LetterheadPdfService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DailyStaffUpdateReportController extends Controller
{
    private function resolveCompanyForCurrentUser(): ?Company
    {
        $user = auth()->user();

        if (! $user) {
            return null;
        }

        if ($user->company_id) {
            return Company::query()->find($user->company_id);
        }

        return null;
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        abort_unless($user && $user->canManageCompanySettings(), 403);

        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 404);

        return redirect()->route('settings.company.index', array_merge(
            $request->query(),
            ['tab' => 'daily-update-history']
        ));
    }

    public function store(Request $request, DailyStaffUpdateReportService $service)
    {
        $user = auth()->user();
        abort_unless($user && $user->canManageCompanySettings(), 403);

        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 404);
        $timezone = $company->timezone ?: config('app.timezone');

        $request->validate([
            'force_generate' => ['nullable', 'boolean'],
            'report_date' => ['nullable', 'date'],
        ]);

        $reportDate = $request->filled('report_date')
            ? Carbon::parse($request->input('report_date'), $timezone)->startOfDay()
            : now($timezone)->startOfDay();

        $result = $service->generateForCompany(
            $company,
            $reportDate,
            $user,
            'manual',
            $request->boolean('force_generate')
        );

        $redirectUrl = route('settings.daily-staff-update.calendar', [
            'date' => $reportDate->toDateString(),
        ]);

        if (! empty($result['duplicate'])) {
            return redirect($redirectUrl)
                ->with('info', $result['message'] ?? 'Report for this date already exists.');
        }

        return redirect($redirectUrl)
            ->with('success', $result['message'] ?? 'Daily staff update report generated successfully.');
    }

    public function show(DailyStaffUpdateReport $report)
    {
        $user = auth()->user();
        abort_unless($user && $user->canManageCompanySettings(), 403);

        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company && (int) $report->company_id === (int) $company->id, 403);

        $report->loadMissing(['generatedBy:id,name', 'company:id,name']);

        return $this->renderDateView($company, $report->report_date->copy(), $report);
    }

    public function calendar(string $date)
    {
        $user = auth()->user();
        abort_unless($user && $user->canManageCompanySettings(), 403);

        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company, 404);

        $selectedDate = $this->resolveCalendarDate($date);

        return $this->renderDateView($company, $selectedDate);
    }

    public function download(DailyStaffUpdateReport $report, LetterheadPdfService $letterheadPdfService)
    {
        $user = auth()->user();
        abort_unless($user && $user->canManageCompanySettings(), 403);

        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company && (int) $report->company_id === (int) $company->id, 403);

        $report->loadMissing(['generatedBy:id,name', 'company:id,name']);

        $pdf = Pdf::loadView('daily-staff-update.pdf', [
            'company' => $company,
            'report' => $report,
            'reportData' => $report->report_payload ?? [],
        ])->setPaper('a4', 'portrait');

        $fileName = 'daily-staff-update-' . Carbon::parse($report->report_date)->format('Y-m-d') . '.pdf';

        return $letterheadPdfService->download($pdf, $fileName, $company);
    }

    public function destroy(DailyStaffUpdateReport $report)
    {
        $user = auth()->user();
        abort_unless($user && $user->canManageCompanySettings(), 403);

        $company = $this->resolveCompanyForCurrentUser();
        abort_unless($company && (int) $report->company_id === (int) $company->id, 403);

        $report->delete();

        return redirect()
            ->route('settings.company.index', ['tab' => 'daily-update-history'])
            ->with('success', 'Report deleted successfully.');
    }

    private function renderDateView(Company $company, Carbon $selectedDate, ?DailyStaffUpdateReport $report = null)
    {
        $selectedDate = $selectedDate->copy()->startOfDay();
        $timezone = $company->timezone ?: config('app.timezone');
        $monthStart = $selectedDate->copy()->startOfMonth();
        $monthEnd = $selectedDate->copy()->endOfMonth();
        $companyTodayDateKey = now($timezone)->toDateString();

        $monthReports = DailyStaffUpdateReport::query()
            ->where('company_id', $company->id)
            ->whereBetween('report_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->with(['generatedBy:id,name'])
            ->orderBy('report_date')
            ->get()
            ->keyBy(fn (DailyStaffUpdateReport $monthReport) => $monthReport->report_date->toDateString());

        if ($report) {
            $report->loadMissing(['generatedBy:id,name', 'company:id,name']);
        } else {
            $report = $monthReports->get($selectedDate->toDateString());
        }

        return view('daily-staff-update.show', [
            'company' => $company,
            'report' => $report,
            'selectedDate' => $selectedDate,
            'selectedDateHuman' => $selectedDate->format('d/m/Y'),
            'monthLabel' => $selectedDate->format('F Y'),
            'companyTodayDateKey' => $companyTodayDateKey,
            'previousMonthUrl' => route('settings.daily-staff-update.calendar', [
                'date' => $selectedDate->copy()->subMonthNoOverflow()->toDateString(),
            ]),
            'nextMonthUrl' => route('settings.daily-staff-update.calendar', [
                'date' => $selectedDate->copy()->addMonthNoOverflow()->toDateString(),
            ]),
            'monthDates' => collect(range(1, $monthStart->daysInMonth))
                ->map(function (int $day) use ($monthStart): Carbon {
                    return $monthStart->copy()->setDay($day)->startOfDay();
                })
                ->values(),
            'monthReports' => $monthReports,
            'reportData' => $report?->report_payload ?? [],
        ]);
    }

    private function resolveCalendarDate(string $date): Carbon
    {
        try {
            $parsedDate = Carbon::createFromFormat('Y-m-d', $date);

            if ($parsedDate->format('Y-m-d') !== $date) {
                abort(404);
            }

            return $parsedDate->startOfDay();
        } catch (\Throwable $exception) {
            abort(404);
        }
    }
}
