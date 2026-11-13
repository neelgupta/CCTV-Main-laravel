<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMC Schedule - {{ $amc->contract_no }}</title>
    @php
        $company = $company ?? auth()->user()?->company;
        $showBrandHeader = !((bool) ($company?->enable_print_settings ?? false));
        $companyLogoUrl = $company?->print_logo_url ?? $company?->logo_url;
        $companyBrandName = $companyLogoUrl ? null : ($company?->name ?? 'Workshop Platform');

        $scheduleDate = now()->format('d M Y');
        $contractPeriod = trim(($amc->start_date?->format('d M Y') ?? '-') . ' to ' . ($amc->end_date?->format('d M Y') ?? '-'));
        $scheduleVisits = collect($amc->visits ?? [])->values();
        $totalVisits = (int) ($amc->total_visits ?? $scheduleVisits->count());
        $completedVisits = $scheduleVisits->filter(fn ($visit) => strtolower(trim((string) $visit->status)) === 'completed')->count();
        $pendingVisits = $scheduleVisits->filter(fn ($visit) => strtolower(trim((string) $visit->status)) === 'pending')->count();
        $scheduledVisits = $scheduleVisits->filter(fn ($visit) => strtolower(trim((string) $visit->status)) === 'scheduled')->count();
        $assignedTechnician = $amc->technician_name ?: $amc->assignedStaff?->name ?: 'Not assigned';
        $serviceMode = $amc->serviceModeLabel();
        $visitSummary = $amc->isOnCall() ? 'On Call Only' : $totalVisits;
        $partyCity = trim((string) ($amc->client?->city?->name ?? $amc->project?->lead?->city?->name ?? ''));
        $partyState = trim((string) ($amc->client?->state?->name ?? $amc->project?->lead?->state?->name ?? ''));

        $visitRows = $scheduleVisits->sortBy('visit_no')->map(function ($visit) use ($amc) {
            return [
                'visit_no' => $visit->visit_no,
                'visit_date' => $visit->visit_date?->format('d M Y') ?: '-',
                'status' => str((string) $visit->status)->headline()->toString(),
                'technician' => $visit->displayTechnicianName() ?: $amc->technician_name ?: 'Not assigned',
            ];
        })->values();
    @endphp
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 0; background: #eef1f4; color: #2f2f2f; font-family: Arial, Helvetica, sans-serif; }
        .actions {
            max-width: 980px;
            margin: 16px auto 0;
            padding: 0 12px;
            display: flex;
            gap: 8px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 36px;
            padding: 0 14px;
            border: 1px solid #c9c9c9;
            border-radius: 4px;
            background: #fff;
            color: #111827;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-print { background: var(--company-accent, #4f46e5); color: #fff; border-color: var(--company-accent, #4f46e5); }
        .sheet { max-width: 980px; margin: 12px auto 28px; padding: 0 12px; }
        .paper {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #b9b9b9;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            padding: 8mm 8mm 7mm;
        }
        .shell { border: 1px solid #4a4a4a; padding: 5px; }
        .topline {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            gap: 10px;
            font-size: 11px;
            margin-bottom: 4px;
            min-height: 18px;
        }
        .topline .gstin { white-space: nowrap; }
        .topline .right { justify-self: end; text-align: left; min-width: 170px; line-height: 1.45; }
        .brand-block { border-bottom: 1px solid #4a4a4a; padding: 2px 8px 6px; text-align: center; }
        .brand-logo { max-height: 28px; max-width: 140px; object-fit: contain; display: block; margin: 0 auto 2px; }
        .company-name { margin: 0; font-size: 24px; line-height: 1.05; letter-spacing: 0.02em; font-weight: 800; text-transform: uppercase; }
        .company-meta { margin: 2px 0 0; font-size: 10px; line-height: 1.35; }
        .panels {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            border-bottom: 1px solid #4a4a4a;
        }
        .panel {
            padding: 8px 10px 10px;
            min-height: 98px;
        }
        .panel + .panel { border-left: 1px solid #4a4a4a; }
        .panel-title {
            margin: 0 0 5px;
            font-size: 11px;
            font-weight: 700;
        }
        .party-name {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .party-line, .meta-line {
            margin: 0;
            font-size: 11px;
            line-height: 1.45;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }
        .meta-row {
            display: grid;
            grid-template-columns: 92px 10px 1fr;
            gap: 4px;
            align-items: start;
            font-size: 11px;
        }
        .terms-list {
            margin-top: 6px;
            border-top: 1px solid #d7d7d7;
            padding-top: 5px;
            font-size: 10px;
        }
        .terms-list div {
            display: grid;
            grid-template-columns: 120px 10px 1fr;
            gap: 4px;
            margin-top: 2px;
        }
        .divider { height: 2px; border: 0; margin: 24px 0 18px; background: linear-gradient(90deg, var(--company-accent, #4f46e5), #6d77ff); }
        .subject-block { margin-bottom: 18px; }
        .subject-label { margin: 0 0 6px; font-size: 10px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #94a3b8; }
        .subject-value { margin: 0; font-size: 15px; font-weight: 700; color: #1e40af; }
        .section-title {
            margin: 0 0 12px;
            padding-left: 10px;
            border-left: 3px solid var(--company-accent, #4f46e5);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--company-accent, #4f46e5);
        }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px 28px; margin-bottom: 20px; }
        .info-item { font-size: 12px; }
        .info-item .label {
            display: block;
            margin-bottom: 3px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .info-item .value { color: #334155; font-weight: 600; overflow-wrap: anywhere; }
        table { width: 100%; border-collapse: collapse; }
        .items-table { margin-bottom: 16px; }
        .items-table th,
        .items-table td { border: 1px solid #e5e7eb; padding: 8px 10px; font-size: 11px; vertical-align: top; }
        .items-table th {
            background: #fbfdff;
            color: var(--company-accent, #4f46e5);
            text-transform: uppercase;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .summary-wrap { display: flex; justify-content: flex-start; margin-top: 18px; }
        .summary-card { width: 280px; }
        .summary-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            font-size: 12px;
            color: #475569;
            padding: 3px 0;
        }
        .summary-line .amount { color: #0f172a; font-weight: 600; text-align: right; white-space: nowrap; }
        .summary-line.grand {
            margin-top: 2px;
            padding: 7px 10px;
            background: #4f46e5;
            color: #fff;
            font-weight: 700;
            border-radius: 2px;
        }
        .summary-line.grand .amount { color: #fff; }
        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            background: #ecfdf5;
            color: #047857;
            font-size: 11px;
            font-weight: 700;
        }
        .footer {
            margin-top: 26px;
            padding-top: 16px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 11px;
            color: #94a3b8;
        }
        .visit-note {
            margin-top: 4px;
            font-size: 10px;
            color: #64748b;
        }
        @media print {
            body { background: #fff; }
            .actions { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; width: 100%; }
            .paper { box-shadow: none; border: 0; padding: 0; max-width: none; width: 100%; margin: 0; }
            .shell { width: 100%; margin: 0; border: 1px solid #4a4a4a; }
        }
        @include('partials.standard-print-shell')
        @include('partials.company-print-settings', ['company' => $company])
    </style>
</head>
<body>
    <div class="actions no-print">
        <button class="btn btn-print" type="button" onclick="window.print()">Print Schedule</button>
        <a class="btn" href="{{ route('amcs.show', $amc) }}">Back to AMC</a>
    </div>

    <div class="sheet">
        <div class="paper" style="width:100%">
            <div class="shell">
                <div class="topline print-brand-header">
                    <div class="gstin"><strong>GSTIN :</strong> {{ filled($company?->gst_number) ? $company->gst_number : '-' }}</div>
                    <div></div>
                    <div class="right">
                        <div><strong>Schedule Date</strong> : {{ $scheduleDate }}</div>
                        <div><strong>Contract Period</strong> : {{ $contractPeriod }}</div>
                        <div><strong>Service Mode</strong> : {{ $serviceMode }}</div>
                    </div>
                </div>

                <div class="brand-block print-brand-header">
                  
                    <h1 class="company-name">{{ \Illuminate\Support\Str::upper($companyBrandName ?? ($company?->name ?? 'Workshop Platform')) }}</h1>
                    <p class="company-meta">
                        {{ $company?->address ?: '' }}<br>
                        @if($company?->mobile_number || $company?->phone_number)
                            Tel.: {{ trim((string) ($company?->mobile_number ?: $company?->phone_number)) }}
                        @endif
                    </p>
                </div>

                <div class="panels">
                    <div class="panel">
                        <p class="panel-title">Customer Details :</p>
                        <p class="party-name">{{ $amc->customer_name }}</p>
                        <p class="party-line">{{ $amc->address ?: '-' }}</p>
                        @if($partyCity !== '' || $partyState !== '')
                            <p class="party-line">{{ collect([$partyCity, $partyState])->filter()->join(', ') }}</p>
                        @endif
                        <p class="party-line">{{ $amc->mobile_number ?: '-' }}</p>
                        <p class="party-line">{{ $amc->email ?: '-' }}</p>
                        @if($amc?->gst_number)
                            <p class="party-line">GSTIN : {{ $amc->gst_number }}</p>
                        @endif
                        @if($amc?->client?->pan_number)
                            <p class="party-line">PAN : {{ $amc->client->pan_number }}</p>
                        @endif
                    </div>
                    <div class="panel">
                        <p class="panel-title">AMC Details :</p>
                        <div class="meta-row">
                            <strong>Contract</strong><span>:</span><span>{{ $amc->contract_no }}</span>
                        </div>
                        <div class="meta-row">
                            <strong>Project</strong><span>:</span><span>{{ $amc->project_name }}</span>
                        </div>
                        <div class="meta-row">
                            <strong>Period</strong><span>:</span><span>{{ $contractPeriod }}</span>
                        </div>
                        <div class="meta-row">
                            <strong>Status</strong><span>:</span><span>{{ $serviceMode }}</span>
                        </div>

                        @if($amc->isOnCall() || $amc->isHybrid())
                            <div class="terms-list">
                                <div>
                                    <strong>Technician</strong>
                                    <span>:</span>
                                    <span>{{ $assignedTechnician }}</span>
                                </div>
                                <div>
                                    <strong>Completed</strong>
                                    <span>:</span>
                                    <span>{{ $completedVisits }}</span>
                                </div>
                                <div>
                                    <strong>Pending</strong>
                                    <span>:</span>
                                    <span>{{ $pendingVisits }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                <hr class="divider">

                <div class="subject-block">
                    <p class="subject-label">Subject</p>
                    <p class="subject-value">AMC visit schedule - {{ $amc->project_name }}</p>
                </div>

                <div class="section-title">AMC Details</div>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="label">Project</span>
                        <span class="value">{{ $amc->project_name }}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Contract No</span>
                        <span class="value">{{ $amc->contract_no }}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Customer</span>
                        <span class="value">{{ $amc->customer_name }}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Technician</span>
                        <span class="value">{{ $assignedTechnician }}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Period</span>
                        <span class="value">{{ $contractPeriod }}</span>
                    </div>
                    <div class="info-item">
                        <span class="label">Total Visits</span>
                        <span class="value">{{ $visitSummary }}</span>
                    </div>
                </div>

                <div class="section-title">Schedule Summary</div>
                <div class="summary-wrap">
                    <div class="summary-card">
                        <div class="summary-line">
                            <span>Total Visits</span>
                            <span class="amount">{{ $totalVisits }}</span>
                        </div>
                        <div class="summary-line">
                            <span>Scheduled</span>
                            <span class="amount">{{ $scheduledVisits }}</span>
                        </div>
                        <div class="summary-line">
                            <span>Pending</span>
                            <span class="amount">{{ $pendingVisits }}</span>
                        </div>
                        <div class="summary-line">
                            <span>Completed</span>
                            <span class="amount">{{ $completedVisits }}</span>
                        </div>
                    </div>
                </div>

                @if($visitRows->isNotEmpty())
                    <div style="margin-top: 24px;">
                        <div class="section-title">Visit Schedule</div>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th style="width: 90px;" class="text-center">Visit No</th>
                                    <th style="width: 160px;">Visit Date</th>
                                    <th>Status</th>
                                    <th>Technician</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($visitRows as $visit)
                                    <tr>
                                        <td class="text-center">{{ $visit['visit_no'] }}</td>
                                        <td>{{ $visit['visit_date'] }}</td>
                                        <td>{{ $visit['status'] }}</td>
                                        <td>
                                            {{ $visit['technician'] }}
                                            @if($visit['technician'] === 'Not assigned')
                                                <div class="visit-note">Technician will appear here once assigned.</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @elseif($amc->isOnCall())
                    <div style="margin-top: 24px;">
                        <div class="section-title">Visit Schedule</div>
                        <div style="border: 1px dashed #bae6fd; background: #f0f9ff; color: #075985; padding: 12px 14px; font-size: 12px;">
                            This AMC is handled on customer call basis. No preventive visit dates are pre-scheduled.
                        </div>
                    </div>
                @elseif($amc->isHybrid())
                    <div style="margin-top: 24px;">
                        <div class="section-title">Support Coverage</div>
                        <div style="border: 1px dashed #bbf7d0; background: #f0fdf4; color: #166534; padding: 12px 14px; font-size: 12px;">
                            This AMC includes on-call support along with the scheduled preventive visits in the selected plan.
                        </div>
                    </div>
                @endif

                <div class="footer">
                    Generated via <strong>{{ $company?->name ?? 'Workshop Platform' }}</strong> on {{ now()->format('d M Y, h:i A') }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
