<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan - {{ $challan->challan_no }}</title>
    @php
        $company = auth()->user()?->company ?? $project->company;
        $pdfLetterhead = app(\App\Services\LetterheadPdfService::class)->getActiveLetterhead($company);
        $hasPdfLetterhead = $pdfLetterhead && filled($pdfLetterhead->file_path);
        $hasCustomPrintLetterhead = $company?->enable_print_settings && (bool) ($company->effective_print_settings['use_letterhead'] ?? true) && $company->letterhead_url;
        $useLetterhead = $hasPdfLetterhead || $hasCustomPrintLetterhead;
        $letterheadUrl = $hasPdfLetterhead
            ? route('settings.letterhead.file', ['v' => $pdfLetterhead->updated_at?->timestamp ?? time()])
            : ($hasCustomPrintLetterhead ? $company->letterhead_url : null);
        $margins = $hasPdfLetterhead
            ? [
                'top' => (float) ($pdfLetterhead->top_margin ?? 20),
                'right' => (float) ($pdfLetterhead->right_margin ?? 20),
                'bottom' => (float) ($pdfLetterhead->bottom_margin ?? 20),
                'left' => (float) ($pdfLetterhead->left_margin ?? 20),
            ]
            : [
                'top' => (float) ($company?->effective_print_settings['page_margin_top'] ?? 20),
                'right' => (float) ($company?->effective_print_settings['page_margin_right'] ?? 20),
                'bottom' => (float) ($company?->effective_print_settings['page_margin_bottom'] ?? 20),
                'left' => (float) ($company?->effective_print_settings['page_margin_left'] ?? 20),
            ];
    @endphp
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
        }

        .page-container {
            width:820px;
            padding:28px;
            margin: 40px auto;
            background: #fff;
            border: 1px solid #d6dce5;
            box-shadow: 0 2px 10px rgba(0, 0, 0, .08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            border: 1px solid #d8dee8;
            padding: 7px 9px;
            font-size: 12px;
        }

        th {
            background: #f8fafc;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 11px;
        }

        .heading {
            font-size: 26px;
            font-weight: 800;
            color: #14213d;
            letter-spacing: 1px;
            margin-bottom: 6px;
        }

        .company-address {
            font-size: 11px;
            color: #666;
            line-height: 18px;
        }

        .section-title {
            font-weight: bold;
            padding: 5px;
            background: #efefef;
        }

        .signature {
            height: 40px;
        }

        .no-border td {
            border: none;
        }

        .header-table td {
            border: 0;
            padding: 0;
            vertical-align: top;
        }

        .header-line {
            margin-top: 12px;
            border-top: 2px solid #cfd6df;
        }
        .no-print{
            width:876px;
            margin:20px auto;
        }

        .btn{
            padding:10px 18px;
            border-radius:4px;
            font-size:14px;
            cursor:pointer;
            border:1px solid #d4d4d4;
        }

        .btn-print{
            background:#111827;
            color:white;
        }

        .btn-back{
            background:white;
        }

        .challan-copy {
            margin-bottom: 30px;
            position: relative;
        }

        .copy-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 2px 8px;
            border-radius: 3px;
            margin-bottom: 6px;
        }

        .challan-copy:not(:last-child) {
            page-break-after: always;
            break-after: page;
        }

        @media print {

            body {
                background: #fff;
            }

            .no-print {
                display: none;
            }

            .page-container {
                width: 100% !important;
                border: none !important;
                margin: 0 !important;
                box-shadow: none !important;
                padding: 0 !important;
                background: transparent !important;
            }

            .challan-copy {
                margin-bottom: 0;
                padding-bottom: 0;
            }

            .challan-copy:not(:last-child) {
                page-break-after: always;
                break-after: page;
            }
        }
        @include('partials.company-print-settings', ['company' => $company])

        @if($useLetterhead && $letterheadUrl)
            .print-brand-header,
            .print-brand-header * {
                display: none !important;
            }
            .page-container {
                background-image: none !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }
            .challan-copy {
                box-sizing: border-box !important;
                width: 100% !important;
                padding: {{ $margins['top'] }}mm {{ $margins['right'] }}mm {{ $margins['bottom'] }}mm {{ $margins['left'] }}mm !important;
                background-image: url('{{ $letterheadUrl }}') !important;
                background-size: 100% 100% !important;
                background-repeat: no-repeat !important;
                background-position: top left !important;
                min-height: 297mm;
            }
            @media print {
                @page {
                    margin: 0;
                    size: A4 portrait;
                }
                body {
                    background: transparent !important;
                }
                .page-container {
                    padding: 0 !important;
                    background: transparent !important;
                }
                .challan-copy {
                    min-height: 100vh;
                    page-break-inside: avoid;
                }
            }
        @endif
    </style>
</head>

<body>
    @php
        $company = auth()->user()?->company ?? $project->company;
        $companyLogoUrl = $company?->print_logo_url ?? $company?->logo_url;
        $dispatchDate = $challan->dispatch_date?->format('d M Y') ?? now()->format('d M Y');
        $clientMobile = $project->lead?->mobile ?: $project->lead?->phone;
        $deliveryAddress = $challan->delivery_address ?: $project->lead?->address;
        $senderMobile = $company?->mobile_number ?: $company?->phone_number;
        $backFallbackUrl = route('projects.show', [$project, 'tab' => 'delivery']);
        $copies = [
            'Original (For Recipient)',
            'Duplicate (For Sender)',
        ];
    @endphp

    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print">Print / Save as PDF</button>
        <button
            onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = @js($backFallbackUrl); }"
            class="btn btn-back">
            Back
        </button>
    </div>

    <div class="page-container">
        @foreach($copies as $copyIndex => $copyLabel)
            <div class="challan-copy">
                <table class="header-table">
                    <tr>
                        <td width="25%" class="print-brand-header">
                            GSTIN : {{ $company->gst_no }}
                        </td>
                        <td align="center" class="print-brand-header">
                            <div class="heading">
                                {{ strtoupper($company->name) }}
                            </div>
                            <div>
                                {{ $company->address }}
                            </div>
                            <div>
                                {{ $company->phone_number }}
                            </div>
                            <div>
                                {{ $company->email }}
                            </div>
                        </td>
                        <td width="25%" align="right" class="print-header-fill">
                            <span class="copy-badge">{{ $copyLabel }}</span><br>
                            <b>Delivery Challan</b><br>
                            Challan No :
                            {{ $challan->challan_no }}
                            <br>
                            Date :
                            {{ $dispatchDate }}
                        </td>
                    </tr>
                </table>
                <table>
                    <tr>
                        <td width="50%">
                            <b>Client Details</b>
                            <b>{{ $project->client_name }}</b>
                            <br>
                            @if($deliveryAddress)
                                <span>{{ $deliveryAddress }}</span>
                                <br>
                            @endif
                            {{ $clientMobile }}
                            @if($challan->name_of_person)
                                <div style="margin-top: 4px;">
                                    <b>Person Name:</b> {{ $challan->name_of_person }}
                                </div>
                            @endif
                        </td>
                        <td width="50%">
                            <b>Challan Details</b>
                            <br>
                            Project : {{ $project->reference_no }}
                            @if($challan->needed_for)
                                <br>
                                <b>Needed For :</b> {{ $challan->needed_for }}
                            @endif
                            @if($challan->transport_name)
                                <br>
                                <b>Transport :</b> {{ $challan->transport_name }}
                            @endif
                            @if($challan->vehicle_no)
                                <br>
                                <b>Vehicle No :</b> {{ $challan->vehicle_no }}
                            @endif
                        </td>
                    </tr>
                </table>
                <div>
                    <h3 style="margin:10px 0 10px 0; font-size: 1.05rem; color:#0f172a;">Items</h3>
                    <table>
                        <thead>
                            <tr>
                                <th>S.N</th>
                                <th>Description of Goods</th>
                                <th>Qty</th>
                                <th>Unit</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($materials as $index => $material)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td style="font-weight: 700;">{{ $material->material_name }}</td>
                                    <td>{{ rtrim(rtrim(number_format((float) $material->qty, 2, '.', ''), '0'), '.') }}</td>
                                    <td>{{ $material->unit ?: '-' }}</td>
                                    <td>{{ $material->note ?: '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 18px; color:#64748b;">No items found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($challan->remarks)
                    <table>
                        <tr>
                            <td>
                                <b>Remarks :</b>
                                <br>
                                {{ $challan->remarks ?? '-' }}
                            </td>
                        </tr>
                    </table>
                @endif
                <table>
                    <tr>
                        <td class="signature" width="50%">
                            Receiver Signature
                            @if($challan->name_of_person)
                                <br><span style="font-size: 11px; color: #64748b;">({{ $challan->name_of_person }})</span>
                            @endif
                        </td>
                        <td class="signature" align="center">
                            For
                            <b>
                                {{ $company->name }}
                            </b>
                            <br><br><br>
                            Authorised Signatory
                        </td>
                    </tr>
                </table>
                <table class="no-border">
                    <tr>
                        <td>
                            <b>Note :</b>
                            Goods once delivered will not be taken back.
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    </div>
</body>
</html>
