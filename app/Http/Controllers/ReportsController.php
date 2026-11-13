<?php

namespace App\Http\Controllers;

use App\Models\Amc;
use App\Models\AmcVisit;
use App\Models\Client;
use App\Models\Company;
use App\Models\Lead;
use App\Models\LeadCategory;
use App\Models\LeadStatus;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\ProjectStage;
use App\Models\Staff;
use App\Models\Ticket;
use App\Models\TicketInvoice;
use App\Exports\LeadsReportExport;
use App\Models\User;
use App\Services\ServiceCallListingService;
use App\Services\StaffInvoiceService;
use App\Services\TicketAssignmentHistoryService;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Pagination\LengthAwarePaginator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ReportsController extends Controller
{
    private const INACTIVE_PROJECT_DAYS = 15;
    private const RENEWAL_WARNING_DAYS = 30;

    public function leads(Request $request)
    {
        return $this->renderReport(
            $request,
            'sales-funnel',
            'Leads Report',
            'Lead pipeline health, source performance, and conversion metrics.',
            route('reports.leads'),
            route('reports.leads.export', $request->query()),
        );
    }

    public function exportLeads(Request $request): BinaryFileResponse
    {
        $companyId = $this->resolveCompanyId();
        $this->ensureReportDefaults($companyId);

        $filters = $this->reportFilters($request, $companyId, 'sales-funnel');
        $salesReport = $this->buildSalesReport($companyId, $filters);

        $fileName = 'leads-report-' . now()->format('Y-m-d-His') . '.xlsx';

        return Excel::download(new LeadsReportExport($salesReport, $filters), $fileName);
    }

    public function projects(Request $request)
    {
        return $this->renderReport($request, 'project-pipeline', 'Project Report', 'Project stage distribution, timelines, and inactive project tracking.', route('reports.projects'));
    }

    public function amc(Request $request)
    {
        return $this->renderReport($request, 'amc', 'AMC Report', 'AMC coverage, renewals, payment status, and revenue overview.', route('reports.amc'));
    }

    public function users(Request $request)
    {
        return $this->renderReport($request, 'users', 'Users Report', 'Company user roster, role distribution, and account activity.', route('reports.users'));
    }

    public function services(Request $request)
    {
        return $this->renderReport($request, 'service-operations', 'Service Report', 'Service visits, technician workload, and overdue task tracking.', route('reports.services'));
    }

    public function payments(Request $request)
    {
        return $this->renderReport($request, 'payments', 'Payment Report', 'Support invoices, payment collection status, and staff/admin receipt tracking.', route('reports.payments'));
    }

    public function togglePaymentReceived(Request $request, TicketInvoice $ticketInvoice)
    {
        abort_unless(auth()->user()?->isCompanyAdmin(), 403, 'Only company admin can update invoice receipt status.');

        abort_unless((int) $ticketInvoice->company_id === (int) $this->resolveCompanyId(), 404);

        $validated = $request->validate([
            'received_by_admin' => ['required', 'boolean'],
        ]);

        if ((bool) $validated['received_by_admin'] && (int) $ticketInvoice->payment_status !== 1) {
            return back()->withErrors([
                'received_by_admin' => 'Invoice not paid yet.',
            ]);
        }

        $ticketInvoice->loadMissing('ticket');

        if ($ticketInvoice->ticket) {
            app(StaffInvoiceService::class)->updateReceivedByAdmin(
                $ticketInvoice,
                auth()->user(),
                (bool) $validated['received_by_admin']
            );
        } else {
            $ticketInvoice->forceFill([
                'received_by_admin' => (bool) $validated['received_by_admin'],
                'received_by_admin_at' => (bool) $validated['received_by_admin'] ? now() : null,
                'received_by_admin_by' => (bool) $validated['received_by_admin'] ? auth()->id() : null,
            ])->save();
        }

        return back()->with('success', 'Invoice receipt status updated successfully.');
    }

    private function renderReport(Request $request, string $activeTab, string $reportTitle, string $reportDescription, string $formAction, ?string $exportUrl = null)
    {
        $companyId = $this->resolveCompanyId();
        $this->ensureReportDefaults($companyId);

        $filters = $this->reportFilters($request, $companyId, $activeTab);
        $company = Company::query()->find($companyId);
        $statusFilterOptions = $this->statusOptionsForReport($activeTab);
        $leadGraphableCustomFields = collect($company?->normalizedLeadCustomFields() ?? [])
            ->filter(fn (array $field) => $this->isGraphableLeadCustomField($field))
            ->values()
            ->all();
        $selectedLeadCustomFieldGraphKeys = $this->selectedLeadCustomFieldGraphKeys($request, $leadGraphableCustomFields);

        $salesReport = $this->buildSalesReport(
            $companyId,
            $filters,
            $leadGraphableCustomFields,
            $selectedLeadCustomFieldGraphKeys
        );
        $projectReport = $this->buildProjectPipelineReport($companyId, $filters);
        $amcCoverageReport = $this->buildAmcCoverageReport($companyId, $filters);
        $renewalReport = $this->buildRenewalReport($companyId, $filters);
        $serviceReport = $this->buildServiceOperationsReport($companyId, $filters);
        $usersReport = $this->buildUsersReport($companyId, $filters);
        $paymentReport = $this->buildPaymentReport($companyId, $filters);
        $paymentStaffOptions = Staff::query()
            ->where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        $summaryCards = match ($activeTab) {
            'sales-funnel' => [
                [
                    'label' => 'Total Leads',
                    'value' => $salesReport['summary']['total_leads'],
                    'meta' => $salesReport['summary']['quoted_leads'] . ' quoted',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Conversion Rate',
                    'value' => $salesReport['summary']['conversion_rate'] . '%',
                    'meta' => $salesReport['summary']['converted_leads'] . ' projects created',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Won Leads',
                    'value' => $salesReport['summary']['won_leads'],
                    'meta' => $salesReport['summary']['quoted_leads'] . ' with quotations',
                    'tone' => 'violet',
                ],
                [
                    'label' => 'Best Source',
                    'value' => $salesReport['summary']['best_source']['label'] ?? 'N/A',
                    'meta' => isset($salesReport['summary']['best_source']['conversion_rate'])
                        ? $salesReport['summary']['best_source']['conversion_rate'] . '% conversion'
                        : 'No source data',
                    'tone' => 'amber',
                ],
            ],
            'project-pipeline' => [
                [
                    'label' => 'Total Projects',
                    'value' => $projectReport['summary']['total_projects'],
                    'meta' => $projectReport['summary']['on_track'] . ' on track',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Completed',
                    'value' => $projectReport['summary']['completed'],
                    'meta' => $projectReport['summary']['due_today'] . ' due today',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Delayed',
                    'value' => $projectReport['summary']['delayed'],
                    'meta' => $projectReport['summary']['inactive_projects'] . ' inactive',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'At Risk',
                    'value' => $projectReport['status_rows'][1]['count'] ?? 0,
                    'meta' => 'Projects needing attention',
                    'tone' => 'amber',
                ],
            ],
            'amc' => [
                [
                    'label' => 'Projects With AMC',
                    'value' => $amcCoverageReport['summary']['projects_with_amc'],
                    'meta' => $amcCoverageReport['summary']['projects_without_amc'] . ' without AMC',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Paid AMC',
                    'value' => $amcCoverageReport['summary']['paid_amcs'],
                    'meta' => $amcCoverageReport['summary']['pending_amcs'] . ' pending',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Renewals Due',
                    'value' => $renewalReport['summary']['expiring_in_30_days'],
                    'meta' => $renewalReport['summary']['expired_amcs'] . ' already expired',
                    'tone' => 'amber',
                ],
                [
                    'label' => 'Billed Revenue',
                    'value' => 'Rs. ' . number_format($amcCoverageReport['summary']['billed_revenue'], 0),
                    'meta' => 'Rs. ' . number_format($amcCoverageReport['summary']['paid_revenue'], 0) . ' collected',
                    'tone' => 'violet',
                ],
            ],
            'users' => [
                [
                    'label' => 'Total Users',
                    'value' => $usersReport['summary']['total_users'],
                    'meta' => $usersReport['summary']['active_users'] . ' active',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Active Users',
                    'value' => $usersReport['summary']['active_users'],
                    'meta' => $usersReport['summary']['inactive_users'] . ' inactive',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Roles In Use',
                    'value' => $usersReport['role_rows']->count(),
                    'meta' => 'Distinct role groups',
                    'tone' => 'violet',
                ],
                [
                    'label' => 'Top Role',
                    'value' => $usersReport['role_rows']->first()['label'] ?? 'N/A',
                    'meta' => isset($usersReport['role_rows']->first()['count'])
                        ? $usersReport['role_rows']->first()['count'] . ' users'
                        : 'No users yet',
                    'tone' => 'amber',
                ],
            ],
            'service-operations' => [
                [
                    'label' => 'Upcoming Visits',
                    'value' => $serviceReport['summary']['upcoming_visits'],
                    'meta' => $serviceReport['summary']['pending_visits'] . ' pending',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Completed',
                    'value' => $serviceReport['summary']['completed_visits'],
                    'meta' => $serviceReport['summary']['open_amc_tickets'] . ' open tickets',
                    'tone' => 'emerald',
                ],
                [
                    'label' => 'Overdue Tasks',
                    'value' => $serviceReport['summary']['overdue_tasks'],
                    'meta' => 'Needs immediate follow-up',
                    'tone' => 'rose',
                ],
                [
                    'label' => 'Open AMC Tickets',
                    'value' => $serviceReport['summary']['open_amc_tickets'],
                    'meta' => 'Linked service tickets',
                    'tone' => 'amber',
                ],
            ],
            'payments' => [
                [
                    'label' => 'Total Invoices',
                    'value' => $paymentReport['summary']['total_invoices'],
                    'meta' => 'Rs. ' . number_format($paymentReport['summary']['total_invoice_amount'], 0) . ' billed',
                    'tone' => 'sky',
                ],
                [
                    'label' => 'Paid Invoices',
                    'value' => $paymentReport['summary']['paid_invoices'],
                    'meta' => 'Rs. ' . number_format($paymentReport['summary']['paid_amount'], 0) . ' collected',
                    'tone' => 'emerald',
                    'payment_status' => 'paid',
                ],
                [
                    'label' => 'Pending',
                    'value' => $paymentReport['summary']['pending_invoices'],
                    'meta' => 'Rs. ' . number_format($paymentReport['summary']['pending_amount'], 0) . ' awaiting',
                    'tone' => 'amber',
                    'payment_status' => 'unpaid',
                ],
                [
                    'label' => 'Received By Admin',
                    'value' => $paymentReport['summary']['received_invoices'],
                    'meta' => 'Rs. ' . number_format($paymentReport['summary']['received_amount'], 0) . ' confirmed',
                    'tone' => 'violet',
                    'received_only' => true,
                ],
            ],
            default => [],
        };

        return view('reports.index', [
            'activeTab' => $activeTab,
            'reportTitle' => $reportTitle,
            'reportDescription' => $reportDescription,
            'formAction' => $formAction,
            'inactiveProjectDays' => self::INACTIVE_PROJECT_DAYS,
            'renewalWarningDays' => self::RENEWAL_WARNING_DAYS,
            'filters' => $filters,
            'statusFilterOptions' => $statusFilterOptions,
            'statusFilterUsesToggle' => $this->statusFilterUsesToggle($activeTab),
            'projectTypes' => LeadCategory::query()
                ->where(function (Builder $query) use ($companyId) {
                    $query->whereNull('company_id')->orWhere('company_id', $companyId);
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'clients' => Client::query()
                ->where('company_id', $companyId)
                ->orderBy('name')
                ->get(['id', 'name']),
            'company' => $company,
            'summaryCards' => $summaryCards,
            'salesReport' => $salesReport,
            'projectReport' => $projectReport,
            'amcCoverageReport' => $amcCoverageReport,
            'renewalReport' => $renewalReport,
            'serviceReport' => $serviceReport,
            'usersReport' => $usersReport,
            'paymentReport' => $paymentReport,
            'paymentStaffOptions' => $paymentStaffOptions,
            'leadGraphableCustomFields' => $leadGraphableCustomFields,
            'selectedLeadCustomFieldGraphKeys' => $selectedLeadCustomFieldGraphKeys,
            'chartConfigs' => $this->buildChartConfigs($activeTab, $salesReport, $projectReport, $amcCoverageReport, $paymentReport),
            'exportUrl' => $exportUrl,
        ]);
    }

    private function resolveCompanyId(): int
    {
        $user = auth()->user();
        abort_unless($user && ! $user->isSuperAdmin(), 403);

        $companyId = (int) ($user->company_id ?: $user->roleRecord?->company_id);
        abort_unless($companyId > 0, 403, 'Company context required.');

        return $companyId;
    }

    private function ensureReportDefaults(int $companyId): void
    {
        LeadStatus::ensureNewLead($companyId);
        LeadStatus::ensureProposalSent($companyId);
        LeadStatus::ensureWon($companyId);
        ProjectStage::ensureDefaults($companyId);
    }

    private function buildSalesReport(
        int $companyId,
        array $filters,
        array $graphableCustomFields = [],
        array $selectedCustomFieldKeys = []
    ): array
    {
        $leads = $this->filteredLeadsQuery($companyId, $filters)
            ->with(['status:id,name,color', 'project:id,lead_id'])
            ->withCount('quotations')
            ->get();

        $totalLeads = $leads->count();
        $newLeads = $leads->filter(fn (Lead $lead) => $this->isLeadStatus($lead->status?->name, ['new lead', 'fresh lead']));
        $lostLeads = $leads->filter(fn (Lead $lead) => $this->isLeadStatus($lead->status?->name, ['lost']));
        $wonLeads = $leads->filter(fn (Lead $lead) => $this->isLeadStatus($lead->status?->name, ['won']));
        $convertedLeads = $leads->filter(fn (Lead $lead) => $lead->project !== null);
        $quotedLeads = $leads->filter(fn (Lead $lead) => (int) $lead->quotations_count > 0);
        $qualifiedLeads = $leads->filter(function (Lead $lead) {
            $status = $this->normalizeText($lead->status?->name);

            return $lead->project === null
                && ! in_array($status, ['new lead', 'fresh lead', 'won', 'lost'], true);
        });
        $statusRows = $this->buildLeadStatusRows($companyId, $leads);

        $conversionRate = $totalLeads > 0
            ? round(($convertedLeads->count() / $totalLeads) * 100, 1)
            : 0.0;

        $sourceRows = $leads
            ->groupBy(fn (Lead $lead) => $this->fallbackLabel($lead->source, 'Unknown'))
            ->map(function (Collection $group, string $label) {
                $converted = $group->filter(fn (Lead $lead) => $lead->project !== null)->count();
                $total = $group->count();

                return [
                    'label' => $label,
                    'total' => $total,
                    'converted' => $converted,
                    'conversion_rate' => $total > 0 ? round(($converted / $total) * 100, 1) : 0.0,
                ];
            })
            ->sortByDesc(fn (array $row) => [$row['converted'], $row['conversion_rate'], $row['total']])
            ->values();

        return [
            'summary' => [
                'total_leads' => $totalLeads,
                'quoted_leads' => $quotedLeads->count(),
                'won_leads' => $wonLeads->count(),
                'converted_leads' => $convertedLeads->count(),
                'conversion_rate' => $conversionRate,
                'best_source' => $sourceRows->first(),
            ],
            'status_rows' => $statusRows,
            'source_rows' => $sourceRows,
            'source_chart' => $this->buildSourcePerformanceChart($sourceRows),
            'monthly_trend' => $this->buildMonthlyLeadTrend($leads, $filters),
            'custom_field_graphs' => $this->buildLeadCustomFieldGraphs($leads, $graphableCustomFields, $selectedCustomFieldKeys),
        ];
    }

    private function buildSourcePerformanceChart(Collection $sourceRows): array
    {
        $palette = ['#0ea5e9', '#14b8a6', '#6366f1', '#f59e0b', '#ef4444'];
        $rows = $sourceRows
            ->take(5)
            ->values()
            ->map(function (array $row, int $index) use ($palette) {
                return [
                    'label' => $row['label'],
                    'count' => (int) $row['total'],
                    'conversion_rate' => $row['conversion_rate'],
                    'color' => $palette[$index] ?? '#94a3b8',
                ];
            });

        $labels = $rows->pluck('label')->all();
        $data = $rows->pluck('count')->all();
        $colors = $rows->pluck('color')->all();
        $total = array_sum($data);

        $chart = $this->doughnutChartConfig($labels, $data, $colors);
        $chart['options']['plugins']['legend'] = ['display' => false];

        return [
            'rows' => $rows,
            'total' => $total,
            'empty' => $total === 0,
            'chart' => $chart,
        ];
    }

    private function buildProjectPipelineReport(int $companyId, array $filters): array
    {
        $projects = $this->filteredProjectsQuery($companyId, $filters)->get();
        $today = Carbon::today();
        $inactiveCutoff = $today->copy()->subDays(self::INACTIVE_PROJECT_DAYS);

        $orderedStages = ProjectStage::query()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->get()
            ->map(function (ProjectStage $stage) {
                $normalizedKey = $this->normalizeProjectStageKey($stage->key);

                return [
                    'key' => $normalizedKey,
                    'label' => $normalizedKey === 'new-project' ? 'New Project' : $stage->name,
                    'color' => $stage->color ?: '#94a3b8',
                ];
            })
            ->unique('key')
            ->values();

        $stageCounts = $projects
            ->groupBy(fn (Project $project) => $this->normalizeProjectStageKey($project->current_stage))
            ->map(fn (Collection $group) => $group->count());

        $stageRows = $orderedStages->map(function (array $stage) use ($stageCounts) {
            return [
                'key' => $stage['key'],
                'label' => $stage['label'],
                'color' => $stage['color'],
                'count' => (int) ($stageCounts->get($stage['key']) ?? 0),
            ];
        });

        $extraStages = $stageCounts
            ->keys()
            ->filter(fn ($key) => ! $stageRows->pluck('key')->contains($key))
            ->map(fn ($key) => [
                'key' => $key,
                'label' => str($key)->replace('-', ' ')->replace('_', ' ')->title()->toString(),
                'color' => '#94a3b8',
                'count' => (int) ($stageCounts->get($key) ?? 0),
            ]);

        $stageChart = $this->buildStageChart(
            $stageRows->concat($extraStages)->values()->all()
        );

        $inactiveProjects = $projects
            ->filter(function (Project $project) use ($inactiveCutoff) {
                if (! $project->last_activity_at) {
                    return true;
                }

                return Carbon::parse($project->last_activity_at)->lt($inactiveCutoff);
            })
            ->sortBy(fn (Project $project) => $project->last_activity_at ?: $project->created_at)
            ->values();

        $timelineProjects = $projects
            ->filter(fn (Project $project) => $project->start_date && $project->end_date)
            ->sortBy('start_date')
            ->take(10)
            ->values();

        $timelineRows = collect();
        $timelineBounds = null;

        if ($timelineProjects->isNotEmpty()) {
            $timelineStart = $timelineProjects->min('start_date')->copy()->startOfDay();
            $timelineEnd = $timelineProjects->max('end_date')->copy()->endOfDay();
            $timelineDays = max(1, $timelineStart->diffInDays($timelineEnd) + 1);
            $stageColorMap = $stageChart['rows']->pluck('color', 'key');

            $timelineRows = $timelineProjects->map(function (Project $project) use ($timelineStart, $timelineDays, $stageColorMap) {
                $projectStart = $project->start_date->copy()->startOfDay();
                $projectEnd = $project->end_date->copy()->startOfDay();
                $offset = round(($timelineStart->diffInDays($projectStart) / $timelineDays) * 100, 2);
                $width = round((($projectStart->diffInDays($projectEnd) + 1) / $timelineDays) * 100, 2);
                $normalizedStage = $this->normalizeProjectStageKey($project->current_stage);

                return [
                    'name' => $project->name,
                    'reference_no' => $project->reference_no,
                    'stage_label' => $project->stage_label,
                    'status_label' => $this->projectStatusLabel($project->status),
                    'completion_percentage' => (int) $project->completion_percentage,
                    'start_label' => $projectStart->format('d M Y'),
                    'end_label' => $projectEnd->format('d M Y'),
                    'offset_percent' => $offset,
                    'width_percent' => max(8, $width),
                    'color' => $stageColorMap[$normalizedStage] ?? '#4f46e5',
                    'show_url' => route('projects.show', $project),
                ];
            });

            $timelineBounds = [
                'start' => $timelineStart->format('d M Y'),
                'end' => $timelineEnd->format('d M Y'),
            ];
        }

        return [
            'summary' => [
                'total_projects' => $projects->count(),
                'on_track' => $projects->where('status', 'on_track')->count(),
                'delayed' => $projects->where('status', 'delayed')->count(),
                'completed' => $projects->where('status', 'completed')->count(),
                'due_today' => $projects->filter(fn (Project $project) => $project->end_date?->isSameDay($today))->count(),
                'inactive_projects' => $inactiveProjects->count(),
            ],
            'stage_chart' => $stageChart,
            'status_rows' => [
                ['label' => 'On Track', 'count' => $projects->where('status', 'on_track')->count(), 'tone' => 'emerald'],
                ['label' => 'At Risk', 'count' => $projects->where('status', 'at_risk')->count(), 'tone' => 'amber'],
                ['label' => 'Delayed', 'count' => $projects->where('status', 'delayed')->count(), 'tone' => 'rose'],
                ['label' => 'Completed', 'count' => $projects->where('status', 'completed')->count(), 'tone' => 'sky'],
            ],
            'timeline_rows' => $timelineRows,
            'timeline_bounds' => $timelineBounds,
            'inactive_rows' => $inactiveProjects->take(8)->map(function (Project $project) use ($today) {
                $lastTouched = $project->last_activity_at
                    ? Carbon::parse($project->last_activity_at)
                    : $project->created_at;
                $daysIdle = (int) $lastTouched->diffInDays($today, false);

                return [
                    'name' => $project->name,
                    'reference_no' => $project->reference_no,
                    'status_label' => $this->projectStatusLabel($project->status),
                    'stage_label' => $project->stage_label,
                    'last_activity' => $lastTouched->format('d M Y'),
                    'days_idle' => max(0, $daysIdle),
                    'show_url' => route('projects.show', $project),
                ];
            }),
        ];
    }

    private function buildAmcCoverageReport(int $companyId, array $filters): array
    {
        $today = Carbon::today();
        $warningDate = $today->copy()->addDays(self::RENEWAL_WARNING_DAYS);

        $projects = $this->filteredProjectsQuery($companyId, $filters)
            ->with(['amcs:id,project_id,end_date'])
            ->get();

        $projectsWithActiveAmc = $projects->filter(function (Project $project) use ($today) {
            return $project->amcs->contains(fn (Amc $amc) => $amc->end_date && $amc->end_date->gte($today));
        })->count();

        $projectsWithExpiredAmc = $projects->filter(function (Project $project) use ($today) {
            return $project->amcs->isNotEmpty()
                && ! $project->amcs->contains(fn (Amc $amc) => $amc->end_date && $amc->end_date->gte($today));
        })->count();

        $projectsWithoutAmc = max(0, $projects->count() - $projectsWithActiveAmc - $projectsWithExpiredAmc);

        $amcs = $this->filteredCoverageAmcsQuery($companyId, $filters)
            ->get();

        $contractStatusChart = $this->buildDonutChart([
            [
                'label' => 'Active',
                'count' => $amcs->filter(fn (Amc $amc) => $amc->end_date && $amc->end_date->gt($warningDate))->count(),
                'color' => '#14b8a6',
            ],
            [
                'label' => 'Expiring Soon',
                'count' => $amcs->filter(fn (Amc $amc) => $amc->end_date && $amc->end_date->gte($today) && $amc->end_date->lte($warningDate))->count(),
                'color' => '#f59e0b',
            ],
            [
                'label' => 'Expired',
                'count' => $amcs->filter(fn (Amc $amc) => $amc->end_date && $amc->end_date->lt($today))->count(),
                'color' => '#ef4444',
            ],
        ]);

        $coverageChart = $this->buildDonutChart([
            ['label' => 'Active Coverage', 'count' => $projectsWithActiveAmc, 'color' => '#14b8a6'],
            ['label' => 'No AMC', 'count' => $projectsWithoutAmc, 'color' => '#94a3b8'],
            ['label' => 'Expired Coverage', 'count' => $projectsWithExpiredAmc, 'color' => '#f59e0b'],
        ]);

        $planRows = $amcs
            ->groupBy(fn (Amc $amc) => $this->fallbackLabel($amc->plan_name, 'Legacy AMC'))
            ->map(function (Collection $group, string $label) {
                $total = $group->count();
                $paid = $group->where('payment_status', 'paid')->count();
                $pending = $group->where('payment_status', 'pending')->count();

                return [
                    'label' => $label,
                    'count' => $total,
                    'paid' => $paid,
                    'pending' => $pending,
                    'paid_percent' => $total > 0 ? round(($paid / $total) * 100, 1) : 0.0,
                    'pending_percent' => $total > 0 ? round(($pending / $total) * 100, 1) : 0.0,
                    'amount' => round((float) $group->sum('total_amount'), 2),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $billedRevenue = (float) $amcs->sum('total_amount');
        $paidRevenue = (float) $amcs->where('payment_status', 'paid')->sum('total_amount');
        $pendingRevenue = (float) $amcs->where('payment_status', 'pending')->sum('total_amount');

        return [
            'summary' => [
                'projects_with_amc' => $projects->count() - $projectsWithoutAmc,
                'projects_without_amc' => $projectsWithoutAmc,
                'total_amcs' => $amcs->count(),
                'paid_amcs' => $amcs->where('payment_status', 'paid')->count(),
                'pending_amcs' => $amcs->where('payment_status', 'pending')->count(),
                'billed_revenue' => round($billedRevenue, 2),
                'paid_revenue' => round($paidRevenue, 2),
                'pending_revenue' => round($pendingRevenue, 2),
            ],
            'coverage_chart' => $coverageChart,
            'contract_status_chart' => $contractStatusChart,
            'plan_rows' => $planRows,
            'payment_rows' => [
                ['label' => 'Paid', 'count' => $amcs->where('payment_status', 'paid')->count(), 'tone' => 'emerald'],
                ['label' => 'Pending', 'count' => $amcs->where('payment_status', 'pending')->count(), 'tone' => 'amber'],
            ],
        ];
    }

    private function buildRenewalReport(int $companyId, array $filters): array
    {
        $today = Carbon::today();
        $amcs = $this->filteredRenewalAmcsQuery($companyId, $filters)
            ->orderBy('end_date')
            ->get();

        $followUpRows = $amcs
            ->map(function (Amc $amc) use ($today) {
                $daysLeft = $amc->end_date
                    ? (int) $today->diffInDays($amc->end_date, false)
                    : null;

                [$statusLabel, $tone] = match (true) {
                    $daysLeft === null => ['No Expiry', 'slate'],
                    $daysLeft < 0 => ['Expired', 'rose'],
                    $daysLeft <= 7 => ['Urgent', 'rose'],
                    $daysLeft <= 15 => ['Soon', 'amber'],
                    $daysLeft <= 30 => ['Upcoming', 'sky'],
                    default => ['Active', 'emerald'],
                };

                return [
                    'amc' => $amc,
                    'contract_no' => $amc->contract_no,
                    'customer_name' => $amc->customer_name,
                    'project_name' => $amc->project_name,
                    'expiry_date' => $amc->end_date?->format('d M Y') ?: '-',
                    'days_left' => $daysLeft,
                    'status_label' => $statusLabel,
                    'tone' => $tone,
                    'amount' => (float) $amc->total_amount,
                    'action_label' => $daysLeft !== null && $daysLeft <= 30 ? 'Renew' : 'Follow-up',
                    'show_url' => route('amcs.show', $amc),
                    'edit_url' => auth()->user()?->hasPermission('amc.edit') ? route('amcs.edit', $amc) : route('amcs.show', $amc),
                ];
            })
            ->sortBy(function (array $row) {
                return $row['days_left'] ?? 99999;
            })
            ->values();

        return [
            'summary' => [
                'expiring_in_7_days' => $followUpRows->filter(fn (array $row) => $row['days_left'] !== null && $row['days_left'] >= 0 && $row['days_left'] <= 7)->count(),
                'expiring_in_15_days' => $followUpRows->filter(fn (array $row) => $row['days_left'] !== null && $row['days_left'] >= 0 && $row['days_left'] <= 15)->count(),
                'expiring_in_30_days' => $followUpRows->filter(fn (array $row) => $row['days_left'] !== null && $row['days_left'] >= 0 && $row['days_left'] <= 30)->count(),
                'expired_amcs' => $followUpRows->filter(fn (array $row) => $row['days_left'] !== null && $row['days_left'] < 0)->count(),
            ],
            'follow_up_rows' => $followUpRows,
            'priority_rows' => $followUpRows
                ->filter(fn (array $row) => $row['days_left'] !== null && $row['days_left'] <= 30)
                ->sortByDesc('amount')
                ->take(5)
                ->values(),
        ];
    }

    private function buildServiceOperationsReport(int $companyId, array $filters): array
    {
        $today = Carbon::today();

        $visits = $this->filteredServiceVisitsQuery($companyId, $filters)
            ->orderBy('visit_date')
            ->get();

        $tickets = $this->filteredServiceTicketsQuery($companyId, $filters)
            ->get();

        $upcomingVisits = $visits
            ->filter(fn (AmcVisit $visit) => in_array($visit->status, ['pending', 'scheduled'], true) && $visit->visit_date && $visit->visit_date->gte($today))
            ->sortBy('visit_date')
            ->values();

        $pendingVisits = $visits->filter(fn (AmcVisit $visit) => in_array($visit->status, ['pending', 'scheduled'], true) && $visit->visit_date && $visit->visit_date->gte($today));
        $completedVisits = $visits->where('status', 'completed');
        $cancelledVisits = $visits->where('status', 'cancelled');
        $overdueVisits = $visits->filter(fn (AmcVisit $visit) => in_array($visit->status, ['pending', 'scheduled'], true) && $visit->visit_date && $visit->visit_date->lt($today));
        $openTickets = $tickets->filter(fn (Ticket $ticket) => ! in_array($ticket->status, ['resolved', 'closed'], true));
        $repeatComplaints = (int) $tickets->sum('repeat_count');
        $repeatableTickets = $tickets->filter(fn (Ticket $ticket) => (int) $ticket->repeat_count > 0);
        $repeatPercent = $tickets->count() > 0 ? round(($repeatComplaints / $tickets->count()) * 100, 2) : 0;
        $assignmentInsights = app(TicketAssignmentHistoryService::class)->companySummary($companyId);

        $workload = [];

        foreach ($pendingVisits as $visit) {
            $label = $this->fallbackLabel($visit->technician ?: $visit->amc?->technician_name, 'Unassigned');
            $workload[$label] = ($workload[$label] ?? 0) + 1;
        }

        foreach ($openTickets as $ticket) {
            $label = $this->fallbackLabel($ticket->assignedUser?->name, 'Unassigned');
            $workload[$label] = ($workload[$label] ?? 0) + 1;
        }

        arsort($workload);

        $workloadRows = collect($workload)
            ->map(fn (int $count, string $label) => ['label' => $label, 'count' => $count])
            ->take(8)
            ->values();

        $maxWorkload = max(1, (int) $workloadRows->max('count'));
        $workloadRows = $workloadRows->map(function (array $row) use ($maxWorkload) {
            $row['percent'] = round(($row['count'] / $maxWorkload) * 100, 1);

            return $row;
        });

        $staffRepeatRows = $tickets
            ->filter(fn (Ticket $ticket) => (int) $ticket->repeat_count > 0)
            ->groupBy(fn (Ticket $ticket) => $this->fallbackLabel($ticket->assignedUser?->name, 'Unassigned'))
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'count' => (int) $group->sum('repeat_count'),
            ])
            ->sortByDesc('count')
            ->values();

        $clientRepeatRows = $tickets
            ->filter(fn (Ticket $ticket) => (int) $ticket->repeat_count > 0)
            ->groupBy(fn (Ticket $ticket) => $this->fallbackLabel($ticket->customer_name, 'Unknown Client'))
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'count' => (int) $group->sum('repeat_count'),
            ])
            ->sortByDesc('count')
            ->values();

        $issueRepeatRows = $tickets
            ->filter(fn (Ticket $ticket) => (int) $ticket->repeat_count > 0)
            ->groupBy(function (Ticket $ticket): string {
                $issue = trim((string) ($ticket->issue_category ?: $ticket->subject));

                return $issue !== '' ? $issue : 'Unspecified Issue';
            })
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'count' => (int) $group->sum('repeat_count'),
            ])
            ->sortByDesc('count')
            ->take(8)
            ->values();

        return [
            'summary' => [
                'upcoming_visits' => $upcomingVisits->count(),
                'completed_visits' => $completedVisits->count(),
                'pending_visits' => $pendingVisits->count(),
                'overdue_tasks' => $overdueVisits->count(),
                'open_amc_tickets' => $openTickets->count(),
                'repeat_complaints' => $repeatComplaints,
                'repeat_percentage' => $repeatPercent,
                'repeatable_tickets' => $repeatableTickets->count(),
                'total_reassignments' => $assignmentInsights['total_reassignments'],
                'average_repeat_count_before_reassignment' => $assignmentInsights['average_repeat_count_before_reassignment'],
            ],
            'status_rows' => [
                ['label' => 'Pending', 'count' => $visits->where('status', 'pending')->count(), 'tone' => 'amber'],
                ['label' => 'Scheduled', 'count' => $visits->where('status', 'scheduled')->count(), 'tone' => 'sky'],
                ['label' => 'Completed', 'count' => $completedVisits->count(), 'tone' => 'emerald'],
                ['label' => 'Cancelled', 'count' => $cancelledVisits->count(), 'tone' => 'slate'],
                ['label' => 'Overdue', 'count' => $overdueVisits->count(), 'tone' => 'rose'],
            ],
            'workload_rows' => $workloadRows,
            'repeat_rows' => $staffRepeatRows,
            'client_repeat_rows' => $clientRepeatRows,
            'issue_repeat_rows' => $issueRepeatRows,
            'assignment_insights' => $assignmentInsights,
            'timeline_rows' => $upcomingVisits->take(8)->map(function (AmcVisit $visit) {
                $amc = $visit->amc;

                return [
                    'title' => ($amc?->project_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no,
                    'contract_no' => $amc?->contract_no ?: 'AMC',
                    'customer_name' => $amc?->customer_name ?: 'Customer',
                    'date' => $visit->visit_date?->format('d M Y') ?: '-',
                    'technician' => $this->fallbackLabel($visit->technician ?: $amc?->technician_name, 'Unassigned'),
                    'show_url' => $amc ? route('amcs.show', $amc) : route('amcs.index'),
                ];
            }),
            'overdue_rows' => $overdueVisits->sortBy('visit_date')->take(8)->map(function (AmcVisit $visit) use ($today) {
                $amc = $visit->amc;
                $daysOverdue = $visit->visit_date ? $visit->visit_date->diffInDays($today) : 0;

                return [
                    'title' => ($amc?->project_name ?: 'AMC Service') . ' - Visit ' . $visit->visit_no,
                    'customer_name' => $amc?->customer_name ?: 'Customer',
                    'technician' => $this->fallbackLabel($visit->technician ?: $amc?->technician_name, 'Unassigned'),
                    'visit_date' => $visit->visit_date?->format('d M Y') ?: '-',
                    'days_overdue' => $daysOverdue,
                    'show_url' => $amc ? route('amcs.show', $amc) : route('amcs.index'),
                ];
            }),
        ];
    }

    private function buildPaymentReport(int $companyId, array $filters): array
    {
        $query = app(ServiceCallListingService::class)->invoiceQuery($companyId, $filters, auth()->user());
        if (! empty($filters['received_only'])) {
            $query->where('received_by_admin', true);
        }
        $invoices = $query->get();
        $today = Carbon::today();
        $perPage = 15;
        $page = LengthAwarePaginator::resolveCurrentPage() ?: 1;

        $invoiceRows = $invoices->map(function (TicketInvoice $invoice) use ($today) {
            $amount = (float) ($invoice->total_amount ?? $invoice->invoice_total ?? 0);
            $isPaid = (int) ($invoice->payment_status ?? 0) === 1;
            $isReceived = (bool) $invoice->received_by_admin;
            $status = match (true) {
                $isPaid && $isReceived => 'Paid & Received',
                $isPaid => 'Paid',
                ((int) ($invoice->payment_status ?? 0) === 2) => 'Partially Paid',
                ((int) ($invoice->payment_status ?? 0) === 3) => 'Cancelled',
                optional($invoice->created_at)->lt($today->copy()->subDays(30)) => 'Overdue',
                default => 'Unpaid',
            };

            return [
                'invoice' => $invoice,
                'number' => $invoice->invoice?->document_number ?? $invoice->invoice_number ?? ('INV-' . $invoice->id),
                'ticket_number' => $invoice->ticket?->ticket_number ?? '-',
                'customer_name' => $invoice->ticket?->customer_name ?? $invoice->client?->name ?? '-',
                'company_name' => $invoice->ticket?->project?->client_name ?? ($invoice->ticket?->project?->name ?? '-'),
                'staff_name' => $invoice->staff?->name ?? '-',
                'creator_name' => $invoice->creator?->name ?? '-',
                'amount' => $amount,
                'payment_status' => $status,
                'payment_method' => $isPaid ? (($invoice->payment_method === 0 || $invoice->payment_method === 2) ? 'Cash' : 'Online') : '-',
                'received_status' => $isReceived ? 'Received' : 'Pending',
                'received_tone' => $isReceived ? 'emerald' : 'amber',
                'payment_tone' => match ($status) {
                    'Paid', 'Paid & Received' => 'emerald',
                    'Partially Paid' => 'amber',
                    'Cancelled' => 'slate',
                    'Overdue' => 'rose',
                    default => 'sky',
                },
                'paid_at' => $invoice->received_by_admin_at?->format('d M Y') ?? $invoice->created_at?->format('d M Y') ?? '-',
                'created_at' => $invoice->created_at?->format('d M Y') ?? '-',
                'show_url' => $invoice->ticket ? route('tickets.show', [$invoice->ticket, 'tab' => 'billing']) : null,
            ];
        });

        $statusRows = $invoiceRows
            ->groupBy('payment_status')
            ->map(fn (Collection $group, string $label) => [
                'label' => $label,
                'count' => $group->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $staffRows = $invoiceRows
            ->groupBy('staff_name')
            ->map(function (Collection $group, string $label) {
                return [
                    'label' => $label,
                    'count' => $group->count(),
                    'amount' => round((float) $group->sum('amount'), 2),
                    'received' => $group->where('received_status', 'Received')->count(),
                ];
            })
            ->sortByDesc('amount')
            ->take(8)
            ->values();

        $creatorRows = $invoiceRows
            ->groupBy('creator_name')
            ->map(function (Collection $group, string $label) {
                return [
                    'label' => $label,
                    'count' => $group->count(),
                    'amount' => round((float) $group->sum('amount'), 2),
                ];
            })
            ->sortByDesc('count')
            ->take(8)
            ->values();

        $sortedInvoiceRows = $invoiceRows
            ->sortByDesc(fn (array $row) => $row['invoice']->created_at?->timestamp ?? 0)
            ->values();

        $paginatedInvoiceRows = new LengthAwarePaginator(
            $sortedInvoiceRows->forPage($page, $perPage)->values(),
            $sortedInvoiceRows->count(),
            $perPage,
            $page,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );

        $paymentChart = $this->buildDonutChart([
            ['label' => 'Paid', 'count' => $invoiceRows->whereIn('payment_status', ['Paid', 'Paid & Received'])->count(), 'color' => '#10b981'],
            ['label' => 'Pending', 'count' => $invoiceRows->where('payment_status', 'Unpaid')->count(), 'color' => '#f59e0b'],
            ['label' => 'Overdue', 'count' => $invoiceRows->where('payment_status', 'Overdue')->count(), 'color' => '#ef4444'],
            ['label' => 'Cancelled', 'count' => $invoiceRows->where('payment_status', 'Cancelled')->count(), 'color' => '#64748b'],
        ]);

        return [
            'summary' => [
                'total_invoices' => $invoiceRows->count(),
                'total_invoice_amount' => round((float) $invoiceRows->sum('amount'), 2),
                'paid_invoices' => $invoiceRows->whereIn('payment_status', ['Paid', 'Paid & Received'])->count(),
                'pending_invoices' => $invoiceRows->where('payment_status', 'Unpaid')->count(),
                'received_invoices' => $invoiceRows->where('received_status', 'Received')->count(),
                'paid_amount' => round((float) $invoiceRows->whereIn('payment_status', ['Paid', 'Paid & Received'])->sum('amount'), 2),
                'pending_amount' => round((float) $invoiceRows->where('payment_status', 'Unpaid')->sum('amount'), 2),
                'received_amount' => round((float) $invoiceRows->where('received_status', 'Received')->sum('amount'), 2),
            ],
            'payment_chart' => $paymentChart,
            'status_rows' => $statusRows,
            'staff_rows' => $staffRows,
            'creator_rows' => $creatorRows,
            'invoice_rows' => $paginatedInvoiceRows,
        ];
    }

    private function filteredLeadsQuery(int $companyId, array $filters): Builder
    {
        $query = Lead::query()->where('company_id', $companyId);

        if ($filters['date_from']) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if ($filters['project_type']) {
            $query->where('lead_category_id', $filters['project_type']);
        }

        if ($filters['client']) {
            $query->where('client_name', $filters['client']->name);
        }

        return $this->applyLeadStatusFilter($query, $filters['status']);
    }

    private function filteredProjectsQuery(int $companyId, array $filters): Builder
    {
        $query = Project::query()
            ->where('company_id', $companyId)
            ->with(['lead:id,lead_category_id'])
            ->withCount('amcs')
            ->addSelect([
                'last_activity_at' => ProjectActivity::query()
                    ->selectRaw('MAX(COALESCE(occurred_at, created_at))')
                    ->whereColumn('project_id', 'projects.id'),
            ]);

        if ($filters['date_from']) {
            $query->where(function (Builder $builder) use ($filters) {
                $builder->whereDate('start_date', '>=', $filters['date_from'])
                    ->orWhere(function (Builder $fallbackQuery) use ($filters) {
                        $fallbackQuery->whereNull('start_date')
                            ->whereDate('created_at', '>=', $filters['date_from']);
                    });
            });
        }

        if ($filters['date_to']) {
            $query->where(function (Builder $builder) use ($filters) {
                $builder->whereDate('end_date', '<=', $filters['date_to'])
                    ->orWhere(function (Builder $fallbackQuery) use ($filters) {
                        $fallbackQuery->whereNull('end_date')
                            ->whereDate('created_at', '<=', $filters['date_to']);
                    });
            });
        }

        if ($filters['project_type']) {
            $query->whereHas('lead', fn (Builder $leadQuery) => $leadQuery->where('lead_category_id', $filters['project_type']));
        }

        if ($filters['client']) {
            $query->where('client_name', $filters['client']->name);
        }

        return $this->applyProjectStatusFilter($query, $filters['status']);
    }

    private function filteredCoverageAmcsQuery(int $companyId, array $filters): Builder
    {
        $query = Amc::query()
            ->where('company_id', $companyId)
            ->with(['client:id,name', 'project:id,name,lead_id', 'project.lead:id,lead_category_id']);

        if ($filters['date_from']) {
            $query->whereDate('start_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('start_date', '<=', $filters['date_to']);
        }

        if ($filters['project_type']) {
            $query->whereHas('project.lead', fn (Builder $leadQuery) => $leadQuery->where('lead_category_id', $filters['project_type']));
        }

        if ($filters['client']) {
            $query->where(function (Builder $clientQuery) use ($filters) {
                $clientQuery->where('client_id', $filters['client']->id)
                    ->orWhere('customer_name', $filters['client']->name);
            });
        }

        return $this->applyAmcStatusFilter($query, $filters['status']);
    }

    private function filteredRenewalAmcsQuery(int $companyId, array $filters): Builder
    {
        $query = Amc::query()
            ->where('company_id', $companyId)
            ->whereNotNull('end_date')
            ->with(['client:id,name', 'project:id,name,lead_id', 'project.lead:id,lead_category_id']);

        if ($filters['date_from']) {
            $query->whereDate('end_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('end_date', '<=', $filters['date_to']);
        }

        if ($filters['project_type']) {
            $query->whereHas('project.lead', fn (Builder $leadQuery) => $leadQuery->where('lead_category_id', $filters['project_type']));
        }

        if ($filters['client']) {
            $query->where(function (Builder $clientQuery) use ($filters) {
                $clientQuery->where('client_id', $filters['client']->id)
                    ->orWhere('customer_name', $filters['client']->name);
            });
        }

        return $this->applyRenewalStatusFilter($query, $filters['status']);
    }

    private function filteredServiceVisitsQuery(int $companyId, array $filters): Builder
    {
        $query = AmcVisit::query()
            ->with(['amc:id,company_id,contract_no,project_name,customer_name,technician_name,client_id,project_id'])
            ->whereHas('amc', function (Builder $amcQuery) use ($companyId, $filters) {
                $amcQuery->where('company_id', $companyId);

                if ($filters['project_type']) {
                    $amcQuery->whereHas('project.lead', fn (Builder $leadQuery) => $leadQuery->where('lead_category_id', $filters['project_type']));
                }

                if ($filters['client']) {
                    $amcQuery->where(function (Builder $clientQuery) use ($filters) {
                        $clientQuery->where('client_id', $filters['client']->id)
                            ->orWhere('customer_name', $filters['client']->name);
                    });
                }
            });

        if ($filters['date_from']) {
            $query->whereDate('visit_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('visit_date', '<=', $filters['date_to']);
        }

        if (in_array($filters['status'], ['pending', 'scheduled', 'completed', 'cancelled'], true)) {
            $query->where('status', $filters['status']);
        }

        if ($filters['status'] === 'overdue') {
            $query->whereIn('status', ['pending', 'scheduled'])
                ->whereDate('visit_date', '<', Carbon::today());
        }

        return $query;
    }

    private function filteredServiceTicketsQuery(int $companyId, array $filters): Builder
    {
        $query = Ticket::query()
            ->where('company_id', $companyId)
            ->where(function (Builder $ticketQuery) {
                $ticketQuery->whereNotNull('generated_from_amc_id')
                    ->orWhereHas('amc');
            })
            ->with([
                'assignedUser:id,name',
                'generatedFromAmc:id,contract_no,project_name,customer_name,client_id,project_id',
                'amc:id,support_ticket_id,contract_no,project_name,customer_name,client_id,project_id',
            ]);

        if ($filters['date_from']) {
            $query->whereDate('visit_date', '>=', $filters['date_from']);
        }

        if ($filters['date_to']) {
            $query->whereDate('visit_date', '<=', $filters['date_to']);
        }

        if ($filters['client']) {
            $query->where(function (Builder $clientQuery) use ($filters) {
                $clientQuery->where('customer_name', $filters['client']->name)
                    ->orWhereHas('generatedFromAmc', fn (Builder $amcQuery) => $amcQuery->where('client_id', $filters['client']->id))
                    ->orWhereHas('amc', fn (Builder $amcQuery) => $amcQuery->where('client_id', $filters['client']->id));
            });
        }

        if ($filters['project_type']) {
            $query->where(function (Builder $ticketQuery) use ($filters) {
                $ticketQuery
                    ->whereHas('generatedFromAmc.project.lead', fn (Builder $leadQuery) => $leadQuery->where('lead_category_id', $filters['project_type']))
                    ->orWhereHas('amc.project.lead', fn (Builder $leadQuery) => $leadQuery->where('lead_category_id', $filters['project_type']));
            });
        }

        return $query;
    }

    private function applyLeadStatusFilter(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'new' => $query->whereHas('status', fn (Builder $statusQuery) => $statusQuery->whereRaw('LOWER(name) IN (?, ?)', ['new lead', 'fresh lead'])),
            'qualified' => $query->whereHas('status', fn (Builder $statusQuery) => $statusQuery->whereRaw('LOWER(name) NOT IN (?, ?, ?, ?)', ['new lead', 'fresh lead', 'won', 'lost']))
                ->whereDoesntHave('project'),
            'won' => $query->whereHas('status', fn (Builder $statusQuery) => $statusQuery->whereRaw('LOWER(name) = ?', ['won'])),
            'converted' => $query->whereHas('project'),
            'lost' => $query->whereHas('status', fn (Builder $statusQuery) => $statusQuery->whereRaw('LOWER(name) = ?', ['lost'])),
            'proposal_sent' => $query->whereHas('status', fn (Builder $statusQuery) => $statusQuery->whereRaw('LOWER(name) = ?', ['proposal sent'])),
            default => $query,
        };
    }

    private function applyProjectStatusFilter(Builder $query, ?string $status): Builder
    {
        return match ($status) {
            'on_track', 'at_risk', 'delayed', 'completed' => $query->where('status', $status),
            default => $query,
        };
    }

    private function applyAmcStatusFilter(Builder $query, ?string $status): Builder
    {
        $today = Carbon::today();
        $warningDate = $today->copy()->addDays(self::RENEWAL_WARNING_DAYS);

        return match ($status) {
            'paid', 'pending' => $query->where('payment_status', $status),
            'active' => $query->whereDate('end_date', '>', $warningDate),
            'expiring_soon' => $query->whereBetween('end_date', [$today->toDateString(), $warningDate->toDateString()]),
            'expired' => $query->whereDate('end_date', '<', $today),
            default => $query,
        };
    }

    private function applyRenewalStatusFilter(Builder $query, ?string $status): Builder
    {
        $today = Carbon::today();
        $warningDate = $today->copy()->addDays(self::RENEWAL_WARNING_DAYS);

        return match ($status) {
            'active' => $query->whereDate('end_date', '>', $warningDate),
            'expiring_soon' => $query->whereBetween('end_date', [$today->toDateString(), $warningDate->toDateString()]),
            'expired' => $query->whereDate('end_date', '<', $today),
            default => $query,
        };
    }

    private function buildUsersReport(int $companyId, array $filters): array
    {
        $users = User::query()
            ->where('company_id', $companyId)
            ->when($filters['status'] === 'active', fn (Builder $query) => $query->where('is_active', true))
            ->when($filters['status'] === 'deactivated', fn (Builder $query) => $query->where('is_active', false))
            ->with('roleRecord:id,name')
            ->orderBy('name')
            ->get();

        $roleRows = $users
            ->groupBy(fn (User $user) => $this->fallbackLabel($user->roleRecord?->name ?: str($user->role ?: 'unknown')->replace('_', ' ')->title()->toString(), 'Unknown'))
            ->map(function (Collection $group, string $label) {
                return [
                    'label' => $label,
                    'count' => $group->count(),
                    'active' => $group->where('is_active', true)->count(),
                    'inactive' => $group->where('is_active', false)->count(),
                ];
            })
            ->sortByDesc('count')
            ->values();

        $maxRoleCount = max(1, (int) $roleRows->max('count'));
        $roleChartRows = $roleRows->map(function (array $row) use ($maxRoleCount) {
            $row['bar_percent'] = max(10, round(($row['count'] / $maxRoleCount) * 100, 1));

            return $row;
        });

        return [
            'summary' => [
                'total_users' => $users->count(),
                'active_users' => $users->where('is_active', true)->count(),
                'inactive_users' => $users->where('is_active', false)->count(),
            ],
            'role_rows' => $roleChartRows,
            'status_rows' => [
                ['label' => 'Active', 'count' => $users->where('is_active', true)->count(), 'tone' => 'emerald'],
                ['label' => 'Inactive', 'count' => $users->where('is_active', false)->count(), 'tone' => 'rose'],
            ],
            'user_rows' => $users->map(function (User $user) {
                return [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $this->fallbackLabel($user->roleRecord?->name ?: str($user->role ?: 'unknown')->replace('_', ' ')->title()->toString(), 'Unknown'),
                    'is_active' => (bool) $user->is_active,
                    'status_label' => $user->is_active ? 'Active' : 'Inactive',
                    'tone' => $user->is_active ? 'emerald' : 'rose',
                    'joined_at' => $user->created_at?->format('d M Y') ?: '-',
                ];
            }),
        ];
    }

    private function reportFilters(Request $request, int $companyId, string $activeTab): array
    {
        $allowedStatuses = array_keys($this->statusOptionsForReport($activeTab));
        $status = $request->input('status');

        if ($status !== null && $status !== '' && ! in_array($status, $allowedStatuses, true)) {
            $request->merge(['status' => null]);
        }

        $rules = [
            'project_type' => [
                'nullable',
                'integer',
                Rule::exists('lead_categories', 'id')->where(function ($query) use ($companyId) {
                    $query->whereNull('company_id')->orWhere('company_id', $companyId);
                }),
            ],
            'status' => ['nullable', Rule::in($allowedStatuses)],
            'client_id' => [
                'nullable',
                'integer',
                Rule::exists('clients', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
            'staff_id' => [
                'nullable',
                'integer',
                Rule::exists('staff', 'id')->where(fn ($query) => $query->where('company_id', $companyId)),
            ],
        ];

        if ($activeTab === 'payments') {
            $rules['invoice_date'] = ['nullable', 'date'];
            $rules['received_only'] = ['nullable', 'boolean'];
        } else {
            $rules['date_from'] = ['nullable', 'date'];
            $rules['date_to'] = ['nullable', 'date', 'after_or_equal:date_from'];
        }

        $validated = $request->validate($rules);

        $client = isset($validated['client_id'])
            ? Client::query()->where('company_id', $companyId)->find($validated['client_id'])
            : null;
        $staff = isset($validated['staff_id'])
            ? Staff::query()->where('company_id', $companyId)->find($validated['staff_id'])
            : null;

        return [
            'date_from' => $activeTab === 'payments'
                ? (isset($validated['invoice_date']) ? Carbon::parse($validated['invoice_date'])->startOfDay() : null)
                : (isset($validated['date_from']) ? Carbon::parse($validated['date_from'])->startOfDay() : null),
            'date_to' => $activeTab === 'payments'
                ? (isset($validated['invoice_date']) ? Carbon::parse($validated['invoice_date'])->endOfDay() : null)
                : (isset($validated['date_to']) ? Carbon::parse($validated['date_to'])->endOfDay() : null),
            'project_type' => isset($validated['project_type']) ? (int) $validated['project_type'] : null,
            'status' => isset($validated['status']) && $validated['status'] !== '' ? (string) $validated['status'] : null,
            'client_id' => $client?->id,
            'client' => $client,
            'staff_id' => $staff?->id,
            'staff' => $staff,
            'received_only' => $activeTab === 'payments' ? $request->boolean('received_only') : false,
        ];
    }

    private function buildFunnel(array $rows): array
    {
        $max = max(1, ...array_map(fn (array $row) => (int) $row['count'], $rows));

        return array_map(function (array $row) use ($max) {
            $count = (int) $row['count'];

            return array_merge($row, [
                'width_percent' => $count > 0 ? max(28, round(($count / $max) * 100, 1)) : 18,
            ]);
        }, $rows);
    }

    private function buildStageChart(array $rows): array
    {
        $collection = collect($rows);
        $max = max(1, (int) $collection->max('count'));

        return [
            'max' => $max,
            'rows' => $collection->map(function (array $row) use ($max) {
                $count = (int) $row['count'];

                return array_merge($row, [
                    'bar_percent' => $count > 0 ? max(14, round(($count / $max) * 100, 1)) : 8,
                ]);
            })->values(),
        ];
    }

    private function buildDonutChart(array $segments): array
    {
        $total = array_sum(array_map(fn (array $segment) => (int) ($segment['count'] ?? 0), $segments));

        if ($total <= 0) {
            return [
                'total' => 0,
                'segments' => collect($segments)->map(function (array $segment) {
                    $segment['percentage'] = 0;

                    return $segment;
                })->values(),
                'gradient' => 'conic-gradient(#e2e8f0 0deg 360deg)',
            ];
        }

        $cursor = 0.0;
        $gradientSegments = [];

        $rows = collect($segments)->map(function (array $segment) use ($total, &$cursor, &$gradientSegments) {
            $count = (int) ($segment['count'] ?? 0);
            $degrees = ($count / $total) * 360;
            $start = $cursor;
            $cursor += $degrees;

            $gradientSegments[] = ($segment['color'] ?? '#94a3b8') . ' ' . round($start, 2) . 'deg ' . round($cursor, 2) . 'deg';
            $segment['percentage'] = round(($count / $total) * 100, 1);

            return $segment;
        })->values();

        if ($cursor < 360) {
            $gradientSegments[] = '#e2e8f0 ' . round($cursor, 2) . 'deg 360deg';
        }

        return [
            'total' => $total,
            'segments' => $rows,
            'gradient' => 'conic-gradient(' . implode(', ', $gradientSegments) . ')',
        ];
    }

    private function buildMonthlyLeadTrend(Collection $leads, array $filters): array
    {
        $end = $filters['date_to'] ? Carbon::parse($filters['date_to'])->endOfMonth() : Carbon::today()->endOfMonth();
        $start = $filters['date_from']
            ? Carbon::parse($filters['date_from'])->startOfMonth()
            : $end->copy()->subMonths(11)->startOfMonth();

        if ($start->gt($end)) {
            $start = $end->copy()->startOfMonth();
        }

        $months = [];
        $cursor = $start->copy();

        while ($cursor->lte($end)) {
            $months[$cursor->format('Y-m')] = 0;
            $cursor->addMonth();
        }

        foreach ($leads as $lead) {
            if (! $lead->created_at) {
                continue;
            }

            $key = $lead->created_at->format('Y-m');

            if (array_key_exists($key, $months)) {
                $months[$key]++;
            }
        }

        return [
            'labels' => collect($months)
                ->keys()
                ->map(fn (string $month) => Carbon::createFromFormat('Y-m', $month)->format('M Y'))
                ->values()
                ->all(),
            'counts' => array_values($months),
        ];
    }

    private function buildChartConfigs(string $activeTab, array $salesReport, array $projectReport, array $amcCoverageReport, array $paymentReport = []): array
    {
        return match ($activeTab) {
            'sales-funnel' => [
                'leads_by_status' => $this->doughnutChartConfig(
                    collect($salesReport['status_rows'])->pluck('label')->all(),
                    collect($salesReport['status_rows'])->pluck('count')->map(fn ($count) => (int) $count)->all(),
                    collect($salesReport['status_rows'])->pluck('color')->all(),
                ),
                'conversion_rate' => $this->doughnutChartConfig(
                    ['Converted', 'In Pipeline'],
                    [
                        (int) $salesReport['summary']['converted_leads'],
                        max(0, (int) $salesReport['summary']['total_leads'] - (int) $salesReport['summary']['converted_leads']),
                    ],
                    ['#10b981', '#e2e8f0'],
                ),
                'monthly_trends' => $this->lineChartConfig(
                    $salesReport['monthly_trend']['labels'],
                    array_map('intval', $salesReport['monthly_trend']['counts']),
                    '#6366f1',
                ),
            ],
            'project-pipeline' => [
                'project_stages' => $this->verticalBarChartConfig(
                    collect($projectReport['stage_chart']['rows'])->pluck('label')->all(),
                    collect($projectReport['stage_chart']['rows'])->pluck('count')->map(fn ($count) => (int) $count)->all(),
                    collect($projectReport['stage_chart']['rows'])->pluck('color')->all(),
                ),
            ],
            'amc' => [
                'amc_status' => $this->doughnutChartConfig(
                    collect($amcCoverageReport['contract_status_chart']['segments'])->pluck('label')->all(),
                    collect($amcCoverageReport['contract_status_chart']['segments'])->pluck('count')->map(fn ($count) => (int) $count)->all(),
                    collect($amcCoverageReport['contract_status_chart']['segments'])->pluck('color')->all(),
                ),
                'coverage' => $this->doughnutChartConfig(
                    collect($amcCoverageReport['coverage_chart']['segments'])->pluck('label')->all(),
                    collect($amcCoverageReport['coverage_chart']['segments'])->pluck('count')->map(fn ($count) => (int) $count)->all(),
                    collect($amcCoverageReport['coverage_chart']['segments'])->pluck('color')->all(),
                ),
            ],
            'payments' => [
                'payment_status' => $this->doughnutChartConfig(
                    collect($paymentReport['payment_chart']['segments'])->pluck('label')->all(),
                    collect($paymentReport['payment_chart']['segments'])->pluck('count')->map(fn ($count) => (int) $count)->all(),
                    collect($paymentReport['payment_chart']['segments'])->pluck('color')->all(),
                ),
                'payment_staff' => $this->horizontalBarChartConfig(
                    collect($paymentReport['staff_rows'])->pluck('label')->all(),
                    collect($paymentReport['staff_rows'])->pluck('count')->map(fn ($count) => (int) $count)->all(),
                    collect($paymentReport['staff_rows'])->map(fn (array $row, int $index) => $this->chartFallbackColor($index))->all(),
                    'Invoices',
                ),
                'payment_creator' => $this->horizontalBarChartConfig(
                    collect($paymentReport['creator_rows'])->pluck('label')->all(),
                    collect($paymentReport['creator_rows'])->pluck('amount')->map(fn ($amount) => (float) $amount)->all(),
                    collect($paymentReport['creator_rows'])->map(fn (array $row, int $index) => $this->toneColor($index % 2 === 0 ? 'sky' : 'violet'))->all(),
                    'Amount',
                ),
            ],
            default => [],
        };
    }

    private function isGraphableLeadCustomField(array $field): bool
    {
        return ($field['type'] ?? null) === 'dropdown'
            && ! empty($field['options'] ?? []);
    }

    private function selectedLeadCustomFieldGraphKeys(Request $request, array $graphableFields): array
    {
        $allowedKeys = collect($graphableFields)
            ->pluck('key')
            ->map(fn ($key) => $this->normalizeText((string) $key))
            ->filter()
            ->values()
            ->all();

        $rawSelection = $request->input('custom_graph_fields', []);
        if (! is_array($rawSelection)) {
            $rawSelection = [$rawSelection];
        }

        $selectedKeys = collect($rawSelection)
            ->map(fn ($key) => $this->normalizeText((string) $key))
            ->filter()
            ->unique()
            ->values();

        return collect($allowedKeys)
            ->filter(fn (string $key) => $selectedKeys->contains($key))
            ->values()
            ->all();
    }

    private function buildLeadCustomFieldGraphs(Collection $leads, array $graphableFields, array $selectedFieldKeys): array
    {
        if ($selectedFieldKeys === [] || $graphableFields === []) {
            return [];
        }

        $fieldLookup = collect($graphableFields)
            ->keyBy(fn (array $field) => $this->normalizeText((string) ($field['key'] ?? '')));

        return collect($selectedFieldKeys)
            ->map(function (string $fieldKey) use ($fieldLookup, $leads) {
                $field = $fieldLookup->get($this->normalizeText($fieldKey));

                if (! $field) {
                    return null;
                }

                return $this->buildLeadCustomFieldGraph($leads, $field);
            })
            ->filter()
            ->values()
            ->all();
    }

    private function buildLeadCustomFieldGraph(Collection $leads, array $field): ?array
    {
        if (! $this->isGraphableLeadCustomField($field)) {
            return null;
        }

        $options = collect($field['options'])
            ->map(fn ($option) => trim((string) $option))
            ->filter()
            ->values()
            ->all();

        if ($options === []) {
            return null;
        }

        $counts = array_fill_keys($options, 0);
        $otherCount = 0;
        $missingCount = 0;

        foreach ($leads as $lead) {
            $value = $this->normalizeLeadCustomFieldGraphValue(data_get($lead->custom_fields ?? [], $field['key'] ?? ''));

            if ($value === null) {
                $missingCount++;
                continue;
            }

            if (array_key_exists($value, $counts)) {
                $counts[$value]++;
                continue;
            }

            $otherCount++;
        }

        $labels = array_keys($counts);
        $data = array_values($counts);
        $palette = $this->customFieldGraphPalette();
        $colors = collect($labels)
            ->map(function (string $label, int $index) use ($palette) {
                return $palette[$index % count($palette)];
            })
            ->all();

        if ($otherCount > 0) {
            $labels[] = 'Other';
            $data[] = $otherCount;
            $colors[] = '#64748b';
        }

        if ($missingCount > 0) {
            $labels[] = 'Not set';
            $data[] = $missingCount;
            $colors[] = '#cbd5e1';
        }

        $chartType = count($options) <= 4 ? 'doughnut' : 'bar';

        if ($chartType === 'doughnut') {
            $chart = $this->doughnutChartConfig($labels, $data, $colors);
        } else {
            $chart = $this->horizontalBarChartConfig($labels, $data, $colors, 'Responses');
            $chart['options']['plugins']['legend'] = ['display' => false];
        }

        return [
            'key' => (string) ($field['key'] ?? ''),
            'label' => (string) ($field['label'] ?? 'Custom Field'),
            'chart_type' => $chartType,
            'total' => array_sum($data),
            'empty' => array_sum($data) === 0,
            'chart' => $chart,
        ];
    }

    private function normalizeLeadCustomFieldGraphValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            $value = collect($value)
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->first();
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : null;
    }

    private function doughnutChartConfig(array $labels, array $data, array $colors): array
    {
        return [
            'type' => 'doughnut',
            'empty' => array_sum($data) === 0,
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderWidth' => 2,
                    'borderColor' => '#ffffff',
                    'hoverOffset' => 8,
                ]],
            ],
            'options' => $this->baseChartOptions('doughnut'),
        ];
    }

    private function horizontalBarChartConfig(array $labels, array $data, string|array $color, string $datasetLabel = 'Leads'): array
    {
        return [
            'type' => 'bar',
            'empty' => array_sum($data) === 0,
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => $datasetLabel,
                    'data' => $data,
                    'backgroundColor' => $color,
                    'borderRadius' => 8,
                    'maxBarThickness' => 28,
                ]],
            ],
            'options' => $this->baseChartOptions('bar', true),
        ];
    }

    private function customFieldGraphPalette(): array
    {
        return [
            '#0ea5e9',
            '#14b8a6',
            '#8b5cf6',
            '#f59e0b',
            '#ef4444',
            '#22c55e',
            '#ec4899',
            '#6366f1',
            '#06b6d4',
            '#84cc16',
        ];
    }

    private function verticalBarChartConfig(array $labels, array $data, array $colors): array
    {
        return [
            'type' => 'bar',
            'empty' => array_sum($data) === 0,
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'Projects',
                    'data' => $data,
                    'backgroundColor' => $colors,
                    'borderRadius' => 10,
                    'maxBarThickness' => 48,
                ]],
            ],
            'options' => $this->baseChartOptions('bar'),
        ];
    }

    private function lineChartConfig(array $labels, array $data, string $color): array
    {
        return [
            'type' => 'line',
            'empty' => array_sum($data) === 0,
            'data' => [
                'labels' => $labels,
                'datasets' => [[
                    'label' => 'New Leads',
                    'data' => $data,
                    'borderColor' => $color,
                    'backgroundColor' => 'rgba(99, 102, 241, 0.12)',
                    'borderWidth' => 2,
                    'pointRadius' => 4,
                    'pointHoverRadius' => 6,
                    'pointBackgroundColor' => '#ffffff',
                    'pointBorderColor' => $color,
                    'pointBorderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.35,
                ]],
            ],
            'options' => $this->baseChartOptions('line'),
        ];
    }

    private function baseChartOptions(string $type, bool $horizontal = false): array
    {
        $options = [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'boxWidth' => 12,
                        'boxHeight' => 12,
                        'padding' => 16,
                        'color' => '#475569',
                        'font' => ['size' => 12, 'weight' => '500'],
                    ],
                ],
                'tooltip' => [
                    'backgroundColor' => '#0f172a',
                    'titleColor' => '#f8fafc',
                    'bodyColor' => '#e2e8f0',
                    'padding' => 12,
                    'cornerRadius' => 12,
                ],
            ],
        ];

        if ($type === 'bar') {
            $options['indexAxis'] = $horizontal ? 'y' : 'x';
            $options['scales'] = [
                'x' => [
                    'grid' => ['display' => ! $horizontal, 'color' => '#f1f5f9'],
                    'ticks' => ['color' => '#64748b', 'font' => ['size' => 11]],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['display' => $horizontal, 'color' => '#f1f5f9'],
                    'ticks' => [
                        'color' => '#64748b',
                        'font' => ['size' => 11],
                        'precision' => 0,
                    ],
                ],
            ];
        }

        if ($type === 'line') {
            $options['scales'] = [
                'x' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#64748b', 'font' => ['size' => 11]],
                ],
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#f1f5f9'],
                    'ticks' => [
                        'color' => '#64748b',
                        'font' => ['size' => 11],
                        'precision' => 0,
                    ],
                ],
            ];
        }

        if ($type === 'doughnut') {
            $options['cutout'] = '68%';
        }

        return $options;
    }

    private function toneColor(string $tone): string
    {
        return match ($tone) {
            'sky' => '#0ea5e9',
            'emerald' => '#10b981',
            'amber' => '#f59e0b',
            'rose' => '#ef4444',
            'violet' => '#8b5cf6',
            default => '#94a3b8',
        };
    }

    private function chartFallbackColor(int $index): string
    {
        $palette = [
            '#0ea5e9',
            '#8b5cf6',
            '#10b981',
            '#f59e0b',
            '#ef4444',
            '#14b8a6',
            '#6366f1',
            '#ec4899',
            '#64748b',
            '#22c55e',
        ];

        return $palette[$index % count($palette)] ?? '#94a3b8';
    }

    private function buildLeadStatusRows(int $companyId, Collection $leads): array
    {
        $statusCounts = $leads
            ->groupBy(fn (Lead $lead) => $lead->lead_status_id ? (int) $lead->lead_status_id : 0)
            ->map(fn (Collection $group) => $group->count());

        $rows = LeadStatus::query()
            ->where('company_id', $companyId)
            ->orderBy('order')
            ->orderBy('id')
            ->get()
            ->values()
            ->map(function (LeadStatus $status, int $index) use ($statusCounts) {
                $color = trim((string) $status->color);

                return [
                    'label' => $this->fallbackLabel($status->name, 'Stage ' . ($index + 1)),
                    'count' => (int) ($statusCounts->get((int) $status->id) ?? 0),
                    'color' => $color !== '' ? $color : $this->chartFallbackColor($index),
                ];
            });

        $unassignedCount = (int) ($statusCounts->get(0) ?? 0);

        if ($unassignedCount > 0) {
            $rows->push([
                'label' => 'Unassigned',
                'count' => $unassignedCount,
                'color' => '#94a3b8',
            ]);
        }

        return $rows->all();
    }

    private function statusOptionsForReport(string $reportType): array
    {
        return match ($reportType) {
            'sales-funnel' => [
                'new' => 'New Leads',
                'qualified' => 'Qualified Leads',
                'proposal_sent' => 'Proposal Sent',
                'won' => 'Won Leads',
                'converted' => 'Converted To Projects',
                'lost' => 'Lost Leads',
            ],
            'project-pipeline' => [
                'on_track' => 'On Track',
                'at_risk' => 'At Risk',
                'delayed' => 'Delayed',
                'completed' => 'Completed',
            ],
            'amc' => [
                'paid' => 'Paid',
                'pending' => 'Pending',
                'active' => 'Active',
                'expiring_soon' => 'Expiring Soon',
                'expired' => 'Expired',
            ],
            'service-operations' => [
                'pending' => 'Pending',
                'scheduled' => 'Scheduled',
                'completed' => 'Completed',
                'cancelled' => 'Cancelled',
                'overdue' => 'Overdue',
            ],
            'users' => [
                'active' => 'Active Users',
                'deactivated' => 'Deactivated Users',
            ],
            'payments' => [
                'paid' => 'Paid',
                'unpaid' => 'Unpaid',
                'partially_paid' => 'Partially Paid',
                'cancelled' => 'Cancelled',
                'overdue' => 'Overdue',
            ],
            default => [],
        };
    }

    private function statusFilterUsesToggle(string $reportType): bool
    {
        return $reportType === 'users';
    }

    private function dataRequirements(): array
    {
        return [
            'Leads' => ['client_name', 'source', 'lead_status_id', 'lead_category_id', 'created_at', 'estimated_value', 'next_follow_up'],
            'Projects' => ['name', 'reference_no', 'lead_id', 'client_name', 'current_stage', 'status', 'start_date', 'end_date', 'completion_percentage'],
            'AMC Contracts' => ['project_id', 'client_id', 'contract_no', 'plan_name', 'payment_status', 'start_date', 'end_date', 'total_amount', 'assigned_staff_id'],
            'Service Tasks / Visits' => ['amc_id', 'visit_no', 'visit_date', 'status', 'technician', 'ticket_number', 'assigned_to'],
            'Payments (Optional)' => ['payment_status', 'payment_mode', 'total_amount', 'amount_paid', 'paid_at'],
        ];
    }

    private function normalizeProjectStageKey(?string $stage): string
    {
        $normalized = $this->normalizeText($stage);

        if (in_array($normalized, ['drawing', 'new-project'], true)) {
            return 'new-project';
        }

        if (in_array($normalized, ['in-progress', 'material', 'production', 'ready_to_dispatch', 'delivery', 'installation'], true)) {
            return 'in-progress';
        }

        return $normalized;
    }

    private function projectStatusLabel(?string $status): string
    {
        return match ($this->normalizeText($status)) {
            'on_track', 'pending', 'in_progress' => 'On Track',
            'at_risk' => 'At Risk',
            'delayed' => 'Delayed',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => str($status ?: 'Unknown')->replace('_', ' ')->title()->toString(),
        };
    }

    private function isLeadStatus(?string $status, array $candidates): bool
    {
        return in_array($this->normalizeText($status), $candidates, true);
    }

    private function fallbackLabel(?string $value, string $fallback): string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : $fallback;
    }

    private function normalizeText(?string $value): string
    {
        return strtolower(trim((string) $value));
    }
}
