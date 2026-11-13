<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TicketPaymentSummaryService
{
    public function getPaymentSummary(int $companyId, string $range = 'overview'): array
    {
        $query = $this->baseQuery($companyId, $range);
        $amountColumn = $this->amountColumn();

        $row = (clone $query)
            ->selectRaw("
                COALESCE(SUM(COALESCE(ti.{$amountColumn}, 0)), 0) as total_invoice_amount,
                COALESCE(SUM(CASE WHEN ti.payment_status = 1 THEN COALESCE(ti.{$amountColumn}, 0) ELSE 0 END), 0) as total_paid_amount,
                COALESCE(SUM(CASE WHEN ti.payment_status = 0 THEN COALESCE(ti.{$amountColumn}, 0) ELSE 0 END), 0) as total_pending_amount,
                COALESCE(SUM(CASE WHEN ti.payment_status = 1 THEN 1 ELSE 0 END), 0) as total_paid_invoices,
                COALESCE(SUM(CASE WHEN ti.payment_status = 0 THEN 1 ELSE 0 END), 0) as total_unpaid_invoices,
                COALESCE(COUNT(*), 0) as total_invoices
            ")
            ->first();

        return [
            'range' => $range,
            'range_label' => $this->rangeLabel($range),
            'total_invoice_amount' => (float) ($row->total_invoice_amount ?? 0),
            'total_paid_amount' => (float) ($row->total_paid_amount ?? 0),
            'total_pending_amount' => (float) ($row->total_pending_amount ?? 0),
            'total_paid_invoices' => (int) ($row->total_paid_invoices ?? 0),
            'total_unpaid_invoices' => (int) ($row->total_unpaid_invoices ?? 0),
            'total_invoices' => (int) ($row->total_invoices ?? 0),
        ];
    }

    public function getStaffPaymentSummary(int $companyId, string $range = 'overview'): Collection
    {
        $query = $this->baseQuery($companyId, $range);
        $amountColumn = $this->amountColumn();

        $rows = (clone $query)
            ->selectRaw("
                COALESCE(ti.staff_id, 0) as staff_id,
                COALESCE(s.name, 'Unassigned') as staff_name,
                COALESCE(SUM(COALESCE(ti.{$amountColumn}, 0)), 0) as total_invoice_amount,
                COALESCE(SUM(CASE WHEN ti.payment_status = 1 THEN COALESCE(ti.{$amountColumn}, 0) ELSE 0 END), 0) as paid_amount,
                COALESCE(SUM(CASE WHEN ti.payment_status = 0 THEN COALESCE(ti.{$amountColumn}, 0) ELSE 0 END), 0) as pending_amount,
                COALESCE(SUM(CASE WHEN ti.payment_status = 1 THEN 1 ELSE 0 END), 0) as paid_invoice_count,
                COALESCE(SUM(CASE WHEN ti.payment_status = 0 THEN 1 ELSE 0 END), 0) as pending_invoice_count
            ")
            ->groupByRaw('COALESCE(ti.staff_id, 0), COALESCE(s.name, \'Unassigned\')')
            ->orderByDesc('total_invoice_amount')
            ->get()
            ->map(function ($row) {
                return [
                    'staff_id' => (int) ($row->staff_id ?? 0),
                    'staff_name' => (string) ($row->staff_name ?? 'Unassigned'),
                    'total_invoice_amount' => (float) ($row->total_invoice_amount ?? 0),
                    'paid_amount' => (float) ($row->paid_amount ?? 0),
                    'pending_amount' => (float) ($row->pending_amount ?? 0),
                    'paid_invoice_count' => (int) ($row->paid_invoice_count ?? 0),
                    'pending_invoice_count' => (int) ($row->pending_invoice_count ?? 0),
                ];
            });

        $rows->push([
            'staff_id' => null,
            'staff_name' => 'Grand Total',
            'total_invoice_amount' => (float) $rows->sum('total_invoice_amount'),
            'paid_amount' => (float) $rows->sum('paid_amount'),
            'pending_amount' => (float) $rows->sum('pending_amount'),
            'paid_invoice_count' => (int) $rows->sum('paid_invoice_count'),
            'pending_invoice_count' => (int) $rows->sum('pending_invoice_count'),
            'is_grand_total' => true,
        ]);

        return $rows;
    }

    public function getDoughnutChart(int $companyId, string $range = 'overview'): array
    {
        $summary = $this->getPaymentSummary($companyId, $range);

        return [
            'empty' => ($summary['total_invoices'] ?? 0) === 0,
            'type' => 'doughnut',
            'data' => [
                'labels' => ['Paid Amount', 'Pending Amount'],
                'datasets' => [[
                    'data' => [
                        (float) ($summary['total_paid_amount'] ?? 0),
                        (float) ($summary['total_pending_amount'] ?? 0),
                    ],
                    'backgroundColor' => ['#16a34a', '#f97316'],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'cutout' => '68%',
                'plugins' => [
                    'legend' => [
                        'position' => 'bottom',
                        'labels' => [
                            'usePointStyle' => true,
                            'pointStyle' => 'circle',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getBarChart(int $companyId, string $range = 'overview'): array
    {
        $rows = $this->getStaffPaymentSummary($companyId, $range)
            ->reject(fn (array $row) => ($row['is_grand_total'] ?? false))
            ->values();

        return [
            'empty' => $rows->isEmpty(),
            'type' => 'bar',
            'data' => [
                'labels' => $rows->pluck('staff_name')->all(),
                'datasets' => [[
                    'label' => 'Collected Amount',
                    'data' => $rows->pluck('paid_amount')->map(fn ($value) => (float) $value)->all(),
                    'backgroundColor' => '#0f172a',
                    'borderRadius' => 8,
                ]],
            ],
            'options' => [
                'responsive' => true,
                'maintainAspectRatio' => false,
                'plugins' => [
                    'legend' => [
                        'display' => false,
                    ],
                ],
                'scales' => [
                    'x' => [
                        'grid' => ['display' => false],
                    ],
                    'y' => [
                        'beginAtZero' => true,
                    ],
                ],
            ],
        ];
    }

    private function baseQuery(int $companyId, string $range)
    {
        $query = DB::table('ticket_invoices as ti')
            ->leftJoin('staff as s', 's.id', '=', 'ti.staff_id')
            ->where('ti.company_id', $companyId);

        return match ($range) {
            'today' => $query->whereDate('ti.created_at', Carbon::today()),
            'week' => $query->whereBetween('ti.created_at', [
                Carbon::now()->startOfWeek()->startOfDay(),
                Carbon::now()->endOfWeek()->endOfDay(),
            ]),
            'month' => $query->whereBetween('ti.created_at', [
                Carbon::now()->startOfMonth()->startOfDay(),
                Carbon::now()->endOfMonth()->endOfDay(),
            ]),
            default => $query,
        };
    }

    private function rangeLabel(string $range): string
    {
        return match ($range) {
            'today' => "Today's",
            'week' => 'This Week',
            'month' => 'This Month',
            default => 'Overview',
        };
    }

    private function amountColumn(): string
    {
        return Schema::hasColumn('ticket_invoices', 'total_amount')
            ? 'total_amount'
            : 'invoice_total';
    }
}
