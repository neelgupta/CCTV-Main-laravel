<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Daily Staff Update Report - {{ optional($report->report_date)->format('d/m/Y') }}</title>
    <style>
        @page {
            margin: 12mm 14mm 14mm 14mm;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        td, th {
            vertical-align: top;
        }

        /* Header & Company Brand */
        .report-header {
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 12px;
            margin-bottom: 14px;
        }
        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .report-title {
            font-size: 13px;
            font-weight: bold;
            color: #4338ca;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-top: 2px;
        }
        .meta-text {
            color: #64748b;
            font-size: 9px;
            margin-top: 3px;
        }

        /* KPI Stat Badges */
        .stats-table {
            margin-bottom: 16px;
        }
        .stat-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 6px 10px;
            text-align: center;
        }
        .stat-label {
            font-size: 8px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .stat-val {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }
        .stat-card-rose {
            background: #fff1f2;
            border-color: #fecdd3;
        }
        .stat-card-rose .stat-label {
            color: #e11d48;
        }
        .stat-card-rose .stat-val {
            color: #be123c;
        }

        /* Staff Block */
        .staff-block {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-bottom: 14px;
            page-break-inside: avoid;
            background: #ffffff;
        }
        .staff-header {
            background: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px 12px;
        }
        .staff-name {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
        }
        .staff-emp {
            font-size: 9px;
            color: #64748b;
            font-weight: normal;
            margin-left: 6px;
        }
        .staff-badges {
            font-size: 8px;
            color: #475569;
            font-weight: bold;
            margin-top: 2px;
        }

        .section-box {
            padding: 8px 12px 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .section-box:last-child {
            border-bottom: none;
        }
        .section-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #475569;
            margin-bottom: 6px;
        }

        /* Mini Tables */
        .data-table {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
        }
        .data-table th {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 4px 6px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
        }
        .data-table td {
            padding: 4px 6px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 9px;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }

        /* Status Pills */
        .pill {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: capitalize;
        }
        .pill-done { background: #dcfce7; color: #15803d; }
        .pill-in-progress { background: #dbeafe; color: #1d4ed8; }
        .pill-pending { background: #fef3c7; color: #b45309; }
        .pill-rose { background: #ffe4e6; color: #be123c; }
        .pill-indigo { background: #e0e7ff; color: #4338ca; }
        .pill-purple { background: #f3e8ff; color: #7e22ce; }
        .pill-slate { background: #f1f5f9; color: #475569; }

        .empty-text {
            color: #94a3b8;
            font-style: italic;
            font-size: 9px;
            padding: 4px 0;
        }
    </style>
</head>
<body>
    @php
        $staffRows = collect($reportData['staff'] ?? []);
        $totalStaff = (int) ($reportData['staff_count'] ?? $staffRows->count());
        $totalProjects = (int) ($reportData['project_count'] ?? 0);
        $totalTickets = (int) ($reportData['ticket_count'] ?? 0);
        $totalActivities = (int) ($reportData['lead_activity_count'] ?? 0);
    @endphp

    <!-- Header -->
    <div class="report-header">
        <table style="width: 100%;">
            <tr>
                <td style="width: 65%;">
                    <div class="company-name">{{ $company->name }}</div>
                    <div class="report-title">Daily Staff Update Report</div>
                    <div class="meta-text">
                        Date: <strong>{{ optional($report->report_date)->format('d/m/Y') }}</strong>
                        @if(filled($reportData['generated_by']['name'] ?? null))
                            | Generated by {{ $reportData['generated_by']['name'] }}
                        @endif
                        | Generated at {{ optional($report->generated_at ?? $report->created_at)->format('d M Y, h:i A') }}
                    </div>
                </td>
                <td style="width: 35%; text-align: right;">
                    @if($company->company_code)
                        <div class="meta-text">Company Code: <strong>{{ $company->company_code }}</strong></div>
                    @endif
                    @if($company->mobile_number || $company->phone_number)
                        <div class="meta-text">Phone: {{ $company->mobile_number ?: $company->phone_number }}</div>
                    @endif
                    @if($company->email)
                        <div class="meta-text">Email: {{ $company->email }}</div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <!-- Summary KPI Cards -->
    <table class="stats-table">
        <tr>
            <td style="width: 25%; padding-right: 6px;">
                <div class="stat-card">
                    <div class="stat-label">Active Staff</div>
                    <div class="stat-val">{{ number_format($totalStaff) }}</div>
                </div>
            </td>
            <td style="width: 25%; padding-right: 6px;">
                <div class="stat-card">
                    <div class="stat-label">Projects</div>
                    <div class="stat-val">{{ number_format($totalProjects) }}</div>
                </div>
            </td>
            <td style="width: 25%; padding-right: 6px;">
                <div class="stat-card">
                    <div class="stat-label">Support Tickets</div>
                    <div class="stat-val">{{ number_format($totalTickets) }}</div>
                </div>
            </td>
            <td style="width: 25%;">
                <div class="stat-card stat-card-rose">
                    <div class="stat-label">Lead Activities</div>
                    <div class="stat-val">{{ number_format($totalActivities) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Staff Reports -->
    @forelse($staffRows as $staff)
        @php
            $projects = collect($staff['projects'] ?? []);
            $tickets = collect($staff['support_tickets'] ?? []);
            $activities = collect($staff['lead_activities'] ?? []);
            $totalTasks = $projects->sum(fn($p) => count($p['tasks'] ?? []));
        @endphp

        <div class="staff-block">
            <!-- Staff Header -->
            <div class="staff-header">
                <table style="width: 100%;">
                    <tr>
                        <td>
                            <span class="staff-name">{{ $staff['name'] ?? 'Staff Member' }}</span>
                            @if(filled($staff['employee_id'] ?? null))
                                <span class="staff-emp">({{ $staff['employee_id'] }})</span>
                            @endif
                        </td>
                        <td style="text-align: right;">
                            <span class="staff-badges">
                                {{ $projects->count() }} Projects | {{ $totalTasks }} Tasks | {{ $tickets->count() }} Tickets | {{ $activities->count() }} Lead Activities
                            </span>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Projects & Tasks -->
            <div class="section-box">
                <div class="section-title">Assigned Projects & Tasks</div>
                @if($projects->isEmpty())
                    <div class="empty-text">No assigned projects for this staff member.</div>
                @else
                    @foreach($projects as $project)
                        <div style="margin-bottom: 6px;">
                            <div style="font-weight: bold; font-size: 9px; color: #1e293b; margin-bottom: 2px;">
                                Project: {{ $project['name'] ?? 'Project' }}
                            </div>
                            @if(empty($project['tasks']))
                                <div class="empty-text" style="padding-left: 8px;">No tasks.</div>
                            @else
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Task Name</th>
                                            <th style="width: 25%;">Completed Date</th>
                                            <th style="width: 20%; text-align: center;">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($project['tasks'] as $task)
                                            @php
                                                $pillClass = match($task['status_color'] ?? '') {
                                                    'emerald' => 'pill-done',
                                                    'blue' => 'pill-in-progress',
                                                    'rose' => 'pill-rose',
                                                    default => 'pill-pending',
                                                };
                                            @endphp
                                            <tr>
                                                <td>{{ $task['name'] ?? 'Task' }}</td>
                                                <td>{{ $task['completed_date'] ?: '-' }}</td>
                                                <td style="text-align: center;">
                                                    <span class="pill {{ $pillClass }}">{{ $task['status_label'] ?? 'Pending' }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>

            <!-- Support Tickets -->
            <div class="section-box">
                <div class="section-title">Support Tickets</div>
                @if($tickets->isEmpty())
                    <div class="empty-text">No support tickets for this date.</div>
                @else
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ticket Subject</th>
                                <th style="width: 25%;">Ticket #</th>
                                <th style="width: 20%; text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tickets as $ticket)
                                @php
                                    $tPill = match($ticket['status_color'] ?? '') {
                                        'emerald' => 'pill-done',
                                        'blue' => 'pill-in-progress',
                                        'rose' => 'pill-rose',
                                        'amber' => 'pill-pending',
                                        default => 'pill-slate',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $ticket['name'] ?? 'Ticket' }}</td>
                                    <td>{{ $ticket['ticket_number'] ?: '-' }}</td>
                                    <td style="text-align: center;">
                                        <span class="pill {{ $tPill }}">{{ $ticket['status_label'] ?? 'Open' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <!-- Lead Activities -->
            <div class="section-box">
                <div class="section-title" style="color: #be123c;">Lead Activities & Follow-ups</div>
                @if($activities->isEmpty())
                    <div class="empty-text">No lead activities logged on this date.</div>
                @else
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Time</th>
                                <th style="width: 25%;">Lead / Client</th>
                                <th style="width: 20%; text-align: center;">Type</th>
                                <th>Activity Details</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($activities as $activity)
                                @php
                                    $actPill = match($activity['type_color'] ?? '') {
                                        'indigo' => 'pill-indigo',
                                        'blue' => 'pill-in-progress',
                                        'amber' => 'pill-pending',
                                        'emerald' => 'pill-done',
                                        'purple' => 'pill-purple',
                                        'rose' => 'pill-rose',
                                        default => 'pill-slate',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $activity['time'] ?: '-' }}</td>
                                    <td><strong>{{ $activity['lead_name'] }}</strong></td>
                                    <td style="text-align: center;">
                                        <span class="pill {{ $actPill }}">{{ $activity['type_label'] ?? 'Activity' }}</span>
                                    </td>
                                    <td>{{ $activity['description'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>
    @empty
        <div style="text-align: center; color: #64748b; padding: 20px;">
            No active staff updates recorded for this date.
        </div>
    @endforelse
</body>
</html>
