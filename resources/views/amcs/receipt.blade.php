<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMC Receipt - {{ $amc->contract_no }}</title>
    @php
        $company = $company ?? auth()->user()?->company;
        $showBrandHeader = !((bool) ($company?->enable_print_settings ?? false));
        $companyLogoUrl = $company?->print_logo_url ?? $company?->logo_url;
        $companyBrandName = $companyLogoUrl ? null : ($company?->name ?? 'Workshop Platform');

        $receiptNo = '#RCPT-' . str_replace(['/', ' '], '-', (string) $amc->contract_no);
        $receiptDate = now()->format('d M Y');
        $contractPeriod = trim(($amc->start_date?->format('d M Y') ?? '-') . ' to ' . ($amc->end_date?->format('d M Y') ?? '-'));
        $cameraTypes = collect($amc->camera_types ?? [])
            ->map(fn ($type) => $amcCameraTypeOptions[$type] ?? str($type)->headline()->toString())
            ->join(', ');
        $serviceScope = collect($amc->service_scope ?? [])
            ->map(fn ($scope) => $amcServiceScopeOptions[$scope] ?? str($scope)->headline()->toString())
            ->join(', ');
        $amcType = $amcTypeOptions[$amc->amc_type] ?? str($amc->amc_type)->headline()->toString();
        $paymentMode = $amcPaymentModeOptions[$amc->payment_mode] ?? str($amc->payment_mode)->headline()->toString();
        $paymentStatus = $amcPaymentStatusOptions[$amc->payment_status] ?? str($amc->payment_status)->headline()->toString();
        $visitSummary = $amc->isOnCall() ? 'On Call Only' : $amc->total_visits;
        $partyCity = trim((string) ($amc->client?->city?->name ?? $amc->project?->lead?->city?->name ?? ''));
        $partyState = trim((string) ($amc->client?->state?->name ?? $amc->project?->lead?->state?->name ?? ''));

        $subtotal = (float) $amc->amc_amount;
        $discount = min(max(0, (float) $amc->discount), max(0, $subtotal));
        $taxableAmount = max(0, $subtotal - $discount);
        $gstAmount = max(0, (float) $amc->total_amount - $taxableAmount);
        $grandTotal = (float) $amc->total_amount;

        $detailRows = collect([
            ['label' => 'Project', 'value' => $amc->project_name],
            ['label' => 'AMC Contract No', 'value' => $amc->contract_no],
            ['label' => 'AMC Type', 'value' => $amcType],
            ['label' => 'AMC Plan', 'value' => $amc->plan_name ?: 'Legacy AMC'],
            ['label' => 'Plan Mode', 'value' => $amc->serviceModeLabel()],
            ['label' => 'Plan Duration', 'value' => \App\Models\Amc::formatDurationMonths($amc->plan_duration_months)],
            ['label' => 'Contract Period', 'value' => $contractPeriod],
            ['label' => $amc->isOnCall() ? 'Service Mode' : 'Total Visits', 'value' => $visitSummary],
            ['label' => 'On-Call Support', 'value' => $amc->hasOnCallSupport() ? 'Included' : 'Not Included'],
            ['label' => 'Technician', 'value' => $amc->technician_name ?: 'Not assigned'],
        ])->filter(fn ($row) => filled($row['value'] ?? null))->values();

        $systemRows = collect([
            ['label' => 'Total Cameras', 'value' => $amc->total_cameras],
            ['label' => 'Camera Types', 'value' => $cameraTypes ?: '-'],
            ['label' => 'DVR/NVR Model', 'value' => $amc->dvr_nvr_model ?: '-'],
            ['label' => 'Hard Disk Size', 'value' => $amc->hard_disk_size ?: '-'],
            ['label' => 'Installation Location', 'value' => $amc->installation_location ?: '-'],
            ['label' => 'Service Scope', 'value' => $serviceScope ?: '-'],
        ])->values();

        $termRows = collect([
            ['label' => 'Service Timing', 'value' => $amc->service_timing],
            ['label' => 'Parts Replacement Policy', 'value' => $amc->parts_replacement_policy],
            ['label' => 'Emergency Support Terms', 'value' => $amc->emergency_support_terms],
        ])->filter(fn ($row) => filled($row['value'] ?? null))->values();
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
        .sheet {
            max-width: 980px;
            margin: 12px auto 28px;
            padding: 0 12px;
        }
        .paper {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #b9b9b9;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            padding: 8mm 8mm 7mm;
        }
        .shell {
            border: 1px solid #4a4a4a;
            padding: 5px;
        }
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
        .brand-block {
            border-bottom: 1px solid #4a4a4a;
            padding: 2px 8px 6px;
            text-align: center;
        }
        .brand-logo {
            max-height: 28px;
            max-width: 140px;
            object-fit: contain;
            display: block;
            margin: 0 auto 2px;
        }
        .company-name {
            margin: 0;
            font-size: 24px;
            line-height: 1.05;
            letter-spacing: 0.02em;
            font-weight: 800;
            text-transform: uppercase;
        }
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
        .divider {
            height: 2px;
            border: 0;
            margin: 24px 0 18px;
            background: linear-gradient(90deg, var(--company-accent, #4f46e5), #6d77ff);
        }
        .subject-block { margin-bottom: 18px; }
        .subject-label {
            margin: 0 0 6px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: #94a3b8;
        }
        .subject-value {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
            color: #1e40af;
        }
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
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px 28px;
            margin-bottom: 20px;
        }
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
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table { margin-bottom: 16px; }
        .items-table th,
        .items-table td {
            border: 1px solid #e5e7eb;
            padding: 8px 10px;
            font-size: 11px;
            vertical-align: top;
        }
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
        .summary-wrap { display: flex; justify-content: flex-end; margin-top: 18px; }
        .summary-card { width: 240px; }
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
        .signature-row {
            display: grid;
            grid-template-columns: 1fr 210px;
            gap: 0;
            border-top: 1px solid #4a4a4a;
            margin-top: 8px;
        }
        .signature-note {
            padding: 10px;
            border-right: 1px solid #4a4a4a;
            font-size: 10px;
            color: #444;
        }
        .signature-block {
            padding: 10px 10px 8px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            text-align: center;
        }
        .signature-line {
            /* width: 100%;
            border-top: 1px solid #444;
            margin-top: 10px;
            padding-top: 5px;
            font-size: 10px;
            line-height: 1.25; */
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
        <button class="btn btn-print" type="button" onclick="window.print()">Print Receipt</button>
        <a class="btn" href="{{ route('amcs.show', $amc) }}">Back to AMC</a>
    </div>

    <div class="sheet">
        <div class="paper" style="width:100%">
            <div class="shell">
                <div class="topline print-brand-header">
                    <div class="gstin"><strong>GSTIN :</strong> {{ filled($company?->gst_number) ? $company->gst_number : '-' }}</div>
                    <div></div>
                    <div class="right">
                        <div><strong>Receipt No</strong> : {{ $receiptNo }}</div>
                        <div><strong>Receipt Date</strong> : {{ $receiptDate }}</div>
                        <div><strong>Payment Status</strong> : {{ $paymentStatus }}</div>
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
                        @if($company?->enable_print_settings)
                            <div class="meta-row">
                                <strong>Receipt No</strong><span>:</span><span>{{ $receiptNo }}</span>
                            </div>
                            <div class="meta-row">
                                <strong>Receipt Date</strong><span>:</span><span>{{ $receiptDate }}</span>
                            </div>
                        @endif
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
                            <strong>Status</strong><span>:</span><span>{{ $paymentStatus }}</span>
                        </div>

                        @if($termRows->isNotEmpty())
                            <div class="terms-list">
                                @foreach($termRows as $row)
                                    <div>
                                        <strong>{{ $row['label'] }}</strong>
                                        <span>:</span>
                                        <span>{{ $row['value'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <hr class="divider">

                <div class="subject-block">
                    <p class="subject-label">Subject</p>
                    <p class="subject-value">Receipt for AMC service contract - {{ $amc->project_name }}</p>
                </div>

                <div class="section-title">AMC Details</div>
                <div class="info-grid">
                    @foreach($detailRows as $row)
                        <div class="info-item">
                            <span class="label">{{ $row['label'] }}</span>
                            <span class="value">{{ $row['value'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="section-title">Payment Details</div>
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 64px;" class="text-center">Sr. No</th>
                            <th>Description</th>
                            <th style="width: 120px;" class="text-center">Mode</th>
                            <th style="width: 110px;" class="text-center">GST</th>
                            <th style="width: 125px;" class="text-right">Amount (&#8377;)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-center">1</td>
                            <td>
                                {{ $amc->plan_name ?: 'AMC Service Contract' }}
                                <div style="margin-top: 3px; color: #94a3b8;">{{ $amcType }} | {{ $contractPeriod }}</div>
                            </td>
                            <td class="text-center">{{ $paymentMode }}</td>
                            <td class="text-center">{{ rtrim(rtrim(number_format((float) $amc->tax_percent, 2, '.', ''), '0'), '.') }}%</td>
                            <td class="text-right">{{ number_format($subtotal, 2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <div class="summary-wrap">
                    <div class="summary-card">
                        <div class="summary-line">
                            <span>Subtotal</span>
                            <span class="amount">&#8377;{{ number_format($subtotal, 2) }}</span>
                        </div>
                        @if($discount > 0)
                            <div class="summary-line">
                                <span>Discount</span>
                                <span class="amount">-&#8377;{{ number_format($discount, 2) }}</span>
                            </div>
                        @endif
                        @if($gstAmount > 0)
                            <div class="summary-line">
                                <span>GST</span>
                                <span class="amount">&#8377;{{ number_format($gstAmount, 2) }}</span>
                            </div>
                        @endif
                        <div class="summary-line">
                            <span>Status</span>
                            <span class="status-pill">{{ $paymentStatus }}</span>
                        </div>
                        <div class="summary-line grand">
                            <span>Total Received</span>
                            <span class="amount">&#8377;{{ number_format($grandTotal, 2) }}</span>
                        </div>
                    </div>
                </div>



                @if($termRows->isNotEmpty())
                    <div style="margin-top: 22px;">
                        <div class="section-title">Terms & Conditions</div>
                        <div class="info-grid">
                            @foreach($termRows as $row)
                                <div class="info-item">
                                    <span class="label">{{ $row['label'] }}</span>
                                    <span class="value">{{ $row['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="signature-row">
                    <div class="signature-note">
                        <div><strong>Taxable Amt.</strong> : &#8377;{{ number_format($taxableAmount, 2) }}</div>
                        <div><strong>GST</strong> : &#8377;{{ number_format($gstAmount, 2) }}</div>
                        <div><strong>Total</strong> : &#8377;{{ number_format($grandTotal, 2) }}</div>
                    </div>
                    <div class="signature-block">
                        <div style="color:#64748b;">For {{ \Illuminate\Support\Str::upper($companyBrandName ?? ($company?->name ?? 'Workshop Platform')) }}</div>
                        <div ><strong>Authorised Signatory</strong></div>
                    </div>
                </div>

                <div class="footer">
                    Generated via <strong>{{ $company?->name ?? 'Workshop Platform' }}</strong> on {{ now()->format('d M Y, h:i A') }}
                </div>
            </div>
        </div>
    </div>
</body>
</html>
