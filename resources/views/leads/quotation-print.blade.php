<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Summary - {{ $lead->client_name }}</title>
    @php
        $company = $lead->company ?? auth()->user()?->company;
    @endphp
    @php
        $showBrandHeader = $company?->shouldShowPrintLogo() ?? true;
    @endphp
    <style>
        /* Modern Reset & Base Styles */
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

        /* Top Platform Bar */
        .platform-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 15px;
        }
        .platform-name {
            font-weight: 800;
            font-size: 1.2rem;
            color: var(--company-accent, #4f46e5);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Branding Section */
        .header-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            margin-bottom: 40px;
        }
        .company-branding h1 {
            margin: 0;
            font-size: 2rem;
            color: #1e293b;
            line-height: 1.2;
        }
        .company-logo-wrap {
            margin-bottom: 14px;
        }
        .company-logo {
            max-height: 72px;
            max-width: 220px;
            width: auto;
            object-fit: contain;
            display: block;
        }
        .client-card {
            background: #f1f5f9;
            padding: 20px;
            border-radius: 6px;
            border-left: 4px solid var(--company-accent, #4f46e5);
        }
        .client-card h2 {
            margin: 0 0 10px 0;
            font-size: 0.85rem;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
        }

        /* Table Design */
        .details h3 {
            font-size: 1.25rem;
            margin-bottom: 20px;
            color: #1e293b;
            border-left: 4px solid #4f46e5;
            padding-left: 10px;
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
        }
        tr:nth-child(even) { background-color: #f8fafc; }

        /* Summary Row */
        .total-row {
            background-color: #e0e7ff !important;
            font-weight: bold;
            color: #3730a3;
        }
        .total-label { text-align: right; }

        /* Buttons & Footer */
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
            margin-top: 50px;
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
                padding: 0;
                border-radius: 0;
            }
        }
        @include('partials.company-print-settings', ['company' => $company])
    </style>
</head>
<body>
    @php
        $companyLogoUrl = $showBrandHeader ? ($company?->print_logo_url ?? $company?->logo_url) : null;
        $companyBrandName = $companyLogoUrl ? null : ($company?->name ?? 'Workshop Platform');
        $acceptedQuotation = $lead->quotations
            ->first(function ($quote) {
                return strtolower((string) $quote->status) === 'accepted';
            });
        $displayEstimateValue = $acceptedQuotation?->amount ?? $lead->estimated_value;
    @endphp

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print" type="button">Print Quotation</button>
        @if($lead && filled($lead->getRouteKey()))
            <a href="{{ route('leads.show', $lead) }}" class="btn btn-back">Back to Lead</a>
        @else
            <a href="{{ url()->previous() }}" class="btn btn-back">Back</a>
        @endif
    </div>

    <div class="page-container">
        <div class="platform-header print-brand-header">
            @if($showBrandHeader && $companyLogoUrl)
                    <div class="company-logo-wrap">
                        <img src="{{ $companyLogoUrl }}" alt="{{ $company?->name ?? 'Company' }} Logo" class="company-logo">
                    </div>
                @endif
            <h3>{{ $company?->name ?? 'Workshop Platform' }}</h3>
            <div style="font-size: 0.8rem; color: #64748b;">Ref: #QT-{{ $lead->id }}-{{ date('Y') }}</div>
        </div>

        <div class="header-grid print-header-grid">
            <div class="company-branding print-brand-header">
                <p style="margin-top: 10px; font-size: 0.9rem; color: #64748b;">
                    {{ $company?->email }}<br>
                    {{ $company?->phone_number }}<br>
                    {{ $company?->address }}
                </p>
            </div>

            <div class="client-card">
                <h2>Quotation For</h2>
                @if($company?->enable_print_settings)
                    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 6px;">Ref: #QT-{{ $lead->id }}-{{ date('Y') }}</div>
                @endif
                <div style="font-weight: 700; font-size: 1.1rem; color: #1e293b;">{{ $lead->client_name }}</div>
                <div style="font-size: 0.9rem; margin-top: 5px;">
                    {{ $lead->email }}<br>
                    {{ $lead->mobile }}<br>
                    <span style="color: #64748b; font-style: italic;">{{ $lead->address }}</span>
                </div>
            </div>
        </div>

        <div class="details">
            <h4>Quotation History</h4>
            <table>
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th style="text-align: right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lead->quotations as $quote)
                        <tr>
                            <td style="font-weight: 600;">v{{ $quote->version }}</td>
                            <td>
                                <span style="text-transform: capitalize; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; background: #f1f5f9;">
                                    {{ $quote->status }}
                                </span>
                            </td>
                            <td>{{ $quote->created_at->format('M d, Y') }}</td>
                            <td style="text-align: right; font-family: monospace; font-weight: 600;">₹{{ number_format($quote->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="total-row">
                        <td colspan="3" class="total-label">Final Estimated Value</td>
                        <td style="text-align: right; font-size: 1.0 rem;">₹{{ number_format($displayEstimateValue, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <div class="footer">
            <p>Generated via <strong>{{ $companyBrandName ?? 'Workshop Platform' }}</strong> on {{ date('F d, Y \a\t H:i') }}</p>
        </div>
    </div>
</body>
</html>
