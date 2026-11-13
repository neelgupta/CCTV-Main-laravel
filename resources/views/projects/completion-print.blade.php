<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Summary - {{ $project->reference_no }}</title>
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.5;
            color: #0f172a;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        .page-container {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            padding: 44px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .no-print {
            max-width: 900px;
            margin: 20px auto;
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-weight: 700;
        }
        .btn-print { background: var(--company-accent, #4f46e5); color: white; }
        .btn-back { background: #64748b; color: white; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 16px;
            margin-bottom: 18px;
        }
        .company-logo {
            max-height: 72px;
            max-width: 220px;
            width: auto;
            object-fit: contain;
            display: block;
        }
        .title {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 900;
        }
        .subtle { color: #475569; font-size: 0.9rem; }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-top: 14px;
        }
        .card {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-left: 4px solid var(--company-accent, #4f46e5);
            border-radius: 8px;
            padding: 14px 16px;
        }
        .card h3 {
            margin: 0 0 8px 0;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #64748b;
        }
        .row { display: flex; justify-content: space-between; gap: 12px; }
        .row div { font-size: 0.92rem; color: #334155; }
        .row strong { color: #0f172a; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #0f172a;
            color: white;
            font-weight: 700;
            text-align: left;
            padding: 10px 12px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.95rem;
            vertical-align: top;
        }
        tr:nth-child(even) { background-color: #f8fafc; }
        .section-title {
            margin: 22px 0 8px 0;
            font-size: 1.05rem;
            font-weight: 900;
            color: #0f172a;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            background: #e2e8f0;
            color: #334155;
        }
        @media print {
            body { background: white; }
            .no-print { display: none; }
            .page-container {
                margin: 0;
                box-shadow: none;
                width: 100%;
                max-width: 100%;
                padding: 18px;
                border-radius: 0;
            }
        }
        @include('partials.company-print-settings', ['company' => $company ?? null])
    </style>
</head>
<body>
@php
    $company = $project->company ?? auth()->user()?->company;
    $companyLogoUrl = $company?->print_logo_url ?? $company?->logo_url;
@endphp

<div class="no-print">
    <button onclick="window.print()" class="btn btn-print">Print / Save as PDF</button>
    <a href="{{ route('projects.show', [$project, 'tab' => 'completed']) }}" class="btn btn-back" style="text-decoration: none; display: inline-flex; align-items: center;">Back</a>
</div>

<div class="page-container">
    <div class="header print-header-trim">
        <div class="print-brand-header">
            @if($companyLogoUrl)
                <img src="{{ $companyLogoUrl }}" alt="{{ $company?->name ?? 'Company' }} Logo" class="company-logo">
            @else
                <div style="font-weight: 900; font-size: 1.05rem; color: #4f46e5; text-transform: uppercase; letter-spacing: 1px;">
                    {{ $company?->name ?? 'Workshop Platform' }}
                </div>
            @endif
            <p class="subtle" style="margin: 8px 0 0 0;">Project Summary</p>
        </div>
        <div style="text-align:right;">
            <p class="title" style="margin:0;">{{ $project->reference_no }}</p>
            <p class="subtle" style="margin:6px 0 0 0;">Generated: <strong>{{ now()->format('d M Y, h:i A') }}</strong></p>
            <p class="subtle" style="margin:6px 0 0 0;">Stage: <strong>{{ $project->stage_label }}</strong></p>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>Project</h3>
            <div class="row"><div>Name</div><div><strong>{{ $project->name }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Client</div><div><strong>{{ $project->client_name ?: 'N/A' }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Manager</div><div><strong>{{ $project->manager?->name ?? 'Unassigned' }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Status</div><div><strong>{{ str($project->status)->headline() }}</strong></div></div>
        </div>
        <div class="card">
            <h3>Dates</h3>
            <div class="row"><div>Start</div><div><strong>{{ $project->start_date?->format('d M Y') ?? 'N/A' }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Deadline</div><div><strong>{{ $project->end_date?->format('d M Y') ?? 'N/A' }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Completion</div><div><strong>{{ $project->completion_percentage }}%</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Installed</div><div><strong>{{ $project->installation_completed_at?->format('d M Y, h:i A') ?? '-' }}</strong></div></div>
        </div>
    </div>

    <div class="section-title">Delivery / Challan</div>
    @if(!$latestChallan)
        <p class="subtle">No challan found.</p>
    @else
        <div class="card" style="border-left-color:#16a34a;">
            <h3>Challan</h3>
            <div class="row"><div>No</div><div><strong>{{ $latestChallan->challan_no }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Status</div><div><span class="badge">{{ str($latestChallan->status ?? 'created')->headline() }}</span></div></div>
            <div class="row" style="margin-top:6px;"><div>Date</div><div><strong>{{ $latestChallan->dispatch_date?->format('d M Y') ?? '-' }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Transport</div><div><strong>{{ $latestChallan->transport_name ?: '-' }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Vehicle</div><div><strong>{{ $latestChallan->vehicle_no ?: '-' }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>LR No</div><div><strong>{{ $latestChallan->lr_no ?: '-' }}</strong></div></div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width:60px;">Sr</th>
                    <th>Material</th>
                    <th>Note</th>
                </tr>
            </thead>
            <tbody>
                @forelse($challanMaterials as $index => $material)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td style="font-weight: 800;">{{ $material->material_name }}</td>
                        <td>{{ $material->note ?: '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" style="text-align:center; color:#64748b;">No material items on challan.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    <div class="section-title">Installation Checklist</div>
    <table>
        <thead>
            <tr>
                <th>Step</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>Site Preparation</td><td>{{ str($project->site_preparation_status ?: 'not_started')->replace('_', ' ')->headline() }}</td></tr>
            <tr><td>Fixing & Welding</td><td>{{ str($project->fixing_welding_status ?: 'not_started')->replace('_', ' ')->headline() }}</td></tr>
            <tr><td>Testing & Commissioning</td><td>{{ str($project->testing_commissioning_status ?: 'not_started')->replace('_', ' ')->headline() }}</td></tr>
            <tr><td>Final Inspection</td><td>{{ str($project->final_inspection_status ?: 'ready')->replace('_', ' ')->headline() }}</td></tr>
            <tr><td>Client Handover</td><td>{{ str($project->client_handover_status ?: 'pending')->replace('_', ' ')->headline() }}</td></tr>
        </tbody>
    </table>

    <div class="section-title">Materials</div>
    <table>
        <thead>
            <tr>
                <th>Material</th>
                <th style="width:90px;">Qty</th>
                <th style="width:120px;">Size</th>
                <th>Note</th>
            </tr>
        </thead>
        <tbody>
            @forelse($materials as $material)
                <tr>
                    <td style="font-weight:800;">{{ $material->material_name }}</td>
                    <td>{{ $material->qty }}</td>
                    <td>{{ $material->size ?: '-' }}</td>
                    <td>{{ $material->note ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" style="text-align:center; color:#64748b;">No materials.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Accepted Drawing</div>
    @if(!$acceptedDrawing)
        <p class="subtle">No accepted drawing.</p>
    @else
        <div class="card">
            <h3>Drawing</h3>
            <div class="row"><div>File</div><div><strong>{{ $acceptedDrawing->file_name }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Version</div><div><strong>{{ $acceptedDrawing->version }}</strong></div></div>
            <div class="row" style="margin-top:6px;"><div>Accepted</div><div><strong>{{ $acceptedDrawing->accepted_at?->format('d M Y, h:i A') ?? '-' }}</strong></div></div>
        </div>
    @endif
</div>
</body>
</html>
