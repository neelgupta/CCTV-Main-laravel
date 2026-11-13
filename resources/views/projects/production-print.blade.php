<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Production Summary - {{ $project->name }}</title>
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #334155;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        .page-container {
            max-width: 900px;
            margin: 30px auto;
            background: white;
            padding: 50px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        .platform-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
            gap: 16px;
        }
        .company-logo {
            max-height: 72px;
            max-width: 220px;
            width: auto;
            object-fit: contain;
            display: block;
        }
        .header-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        .card {
            background: #f1f5f9;
            padding: 18px;
            border-radius: 6px;
            border-left: 4px solid var(--company-accent, #4f46e5);
        }
        .card h2 {
            margin: 0 0 10px 0;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }
        .meta {
            font-size: 0.9rem;
            color: #475569;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background-color: #1e293b;
            color: white;
            font-weight: 500;
            text-align: left;
            padding: 12px 15px;
            font-size: 0.9rem;
        }
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.95rem;
            vertical-align: top;
        }
        tr:nth-child(even) { background-color: #f8fafc; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .badge-done { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
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
            font-weight: 600;
            transition: opacity 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-print { background: var(--company-accent, #4f46e5); color: white; }
        .btn-back { background: #64748b; color: white; }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            text-align: center;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        @media print {
            body { background: white; }
            .no-print { display: none; }
            .page-container {
                margin: 0;
                box-shadow: none;
                width: 100%;
                max-width: 100%;
                padding: 20px;
            }
        }
        @include('partials.company-print-settings', ['company' => $company ?? null])
    </style>
</head>
<body>
    @php
        $company = $project->company ?? auth()->user()?->company;
        $companyLogoUrl = $company?->print_logo_url ?? $company?->logo_url;
        $productions = $project->productions->sortByDesc('created_at');
        $qaDoneCount = $productions->where('qa_done', true)->count();
        $qaPendingCount = $productions->where('qa_done', false)->count();
    @endphp

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print" type="button">Print Summary</button>
        <a href="{{ route('projects.show', [$project, 'tab' => 'production']) }}" class="btn btn-back">Back to Project</a>
    </div>

    <div class="page-container">
        <div class="platform-header print-brand-header">
            <div>
                @if($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $company?->name ?? 'Company' }} Logo" class="company-logo">
                @else
                    <div style="font-weight: 800; font-size: 1.1rem; color: #4f46e5; text-transform: uppercase; letter-spacing: 1px;">
                        {{ $company?->name ?? 'Workshop Platform' }}
                    </div>
                @endif
            </div>
            <div style="font-size: 0.85rem; color: #64748b; text-align: right;">
                Ref: {{ $project->reference_no ?? ('PRJ-' . $project->id . '-' . date('Y')) }}<br>
                Generated: {{ date('F d, Y \\a\\t H:i') }}
            </div>
        </div>

        <div class="header-grid">
            <div class="card">
                <h2>Project</h2>
                <div style="font-weight: 800; font-size: 1.1rem; color: #0f172a;">{{ $project->name }}</div>
                <div class="meta" style="margin-top: 8px;">
                    Client: {{ $project->client_name ?: 'N/A' }}<br>
                    Stage: {{ $project->stage_label }}<br>
                    Status: {{ str($project->status)->headline() }}<br>
                    Manager: {{ $project->manager?->name ?? 'Unassigned' }}
                </div>
            </div>

            <div class="card">
                <h2>Production Overview</h2>
                <div class="meta">
                    Total Updates: <strong>{{ $productions->count() }}</strong><br>
                    QA Done: <strong>{{ $qaDoneCount }}</strong><br>
                    QA Pending: <strong>{{ $qaPendingCount }}</strong><br>
                    Timeline: {{ $project->start_date?->format('M d, Y') ?? 'N/A' }} to {{ $project->end_date?->format('M d, Y') ?? 'N/A' }}
                </div>
            </div>
        </div>

        <div>
            <h3 style="font-size: 1.25rem; margin: 0 0 12px 0; color: #1e293b; border-left: 4px solid var(--company-accent, #4f46e5); padding-left: 10px;">
                Production Updates
            </h3>
            <table>
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Note</th>
                        <th>QA</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($productions as $production)
                        <tr>
                            <td style="font-weight: 700;">{{ $production->title }}</td>
                            <td>{{ $production->note ?: '-' }}</td>
                            <td>
                                @if($production->qa_done)
                                    <span class="badge badge-done">Done</span>
                                @else
                                    <span class="badge badge-pending">Pending</span>
                                @endif
                            </td>
                            <td style="white-space: nowrap;">{{ $production->created_at->format('M d, Y') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" style="text-align: center; color: #64748b; padding: 20px;">
                                No production updates yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>Generated via <strong>Workshop Platform</strong></p>
        </div>
    </div>
</body>
</html>
