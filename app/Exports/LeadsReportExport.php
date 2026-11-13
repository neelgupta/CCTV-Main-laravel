<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;

class LeadsReportExport implements FromCollection, ShouldAutoSize, WithTitle
{
    public function __construct(
        private readonly array $salesReport,
        private readonly array $filters,
    ) {
    }

    public function title(): string
    {
        return 'Leads Report';
    }

    public function collection(): Collection
    {
        $rows = collect();
        $summary = $this->salesReport['summary'];

        $rows->push(['Leads Report']);
        $rows->push(['Generated At', now()->format('Y-m-d H:i:s')]);
        $rows->push(['Date From', $this->filters['date_from']?->format('Y-m-d') ?: 'All']);
        $rows->push(['Date To', $this->filters['date_to']?->format('Y-m-d') ?: 'All']);
        $rows->push(['Client', $this->filters['client']?->name ?: 'All']);
        $rows->push([]);

        $rows->push(['Summary']);
        $rows->push(['Metric', 'Value']);
        $rows->push(['Total Leads', $summary['total_leads']]);
        $rows->push(['Quoted Leads', $summary['quoted_leads']]);
        $rows->push(['Won Leads', $summary['won_leads']]);
        $rows->push(['Converted Leads', $summary['converted_leads']]);
        $rows->push(['Conversion Rate (%)', $summary['conversion_rate']]);
        $rows->push(['Best Source', $summary['best_source']['label'] ?? 'N/A']);
        $rows->push([]);

        $rows->push(['Leads by Status']);
        $rows->push(['Status', 'Count']);
        foreach ($this->salesReport['status_rows'] as $row) {
            $rows->push([$row['label'], $row['count']]);
        }
        $rows->push([]);

        $rows->push(['Lead Conversion Rate']);
        $rows->push(['Segment', 'Count']);
        $rows->push(['Converted', $summary['converted_leads']]);
        $rows->push(['In Pipeline', max(0, $summary['total_leads'] - $summary['converted_leads'])]);
        $rows->push(['Conversion Rate (%)', $summary['conversion_rate']]);
        $rows->push([]);

        $rows->push(['Leads by Source']);
        $rows->push(['Source', 'Total Leads', 'Converted', 'Conversion Rate (%)']);
        foreach ($this->salesReport['source_rows'] as $row) {
            $rows->push([
                $row['label'],
                $row['total'],
                $row['converted'],
                $row['conversion_rate'],
            ]);
        }
        $rows->push([]);

        $rows->push(['Monthly Lead Trends']);
        $rows->push(['Month', 'New Leads']);
        foreach ($this->salesReport['monthly_trend']['labels'] as $index => $label) {
            $rows->push([
                $label,
                $this->salesReport['monthly_trend']['counts'][$index] ?? 0,
            ]);
        }

        return $rows;
    }
}
