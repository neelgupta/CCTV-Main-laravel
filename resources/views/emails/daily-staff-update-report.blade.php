@php
    $reportDateHuman = optional($report->report_date)->format('d M Y') ?: 'Today';
@endphp
<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.7;">
    <p style="margin: 0 0 12px;">Hello Admin,</p>

    <p style="margin: 0 0 12px;">
        The daily staff update report for {{ $reportDateHuman }} is ready.
    </p>

    <ul style="margin: 0 0 16px; padding-left: 18px;">
        <li><strong>Staff:</strong> {{ number_format((int) ($report->report_payload['staff_count'] ?? 0)) }}</li>
        <li><strong>Projects:</strong> {{ number_format((int) ($report->report_payload['project_count'] ?? 0)) }}</li>
        <li><strong>Tickets:</strong> {{ number_format((int) ($report->report_payload['ticket_count'] ?? 0)) }}</li>
        <li><strong>Lead Activities:</strong> {{ number_format((int) ($report->report_payload['lead_activity_count'] ?? 0)) }}</li>
    </ul>

    <p style="margin: 0 0 12px;">
        Full report:
    </p>

    <pre style="white-space: pre-wrap; word-break: break-word; font-family: Consolas, monospace; font-size: 13px; line-height: 1.6; color: #111827; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 16px; margin: 0;">{{ $report->report_text }}</pre>
</div>
