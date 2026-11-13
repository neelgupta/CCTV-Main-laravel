<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Letterhead Preview - {{ $invoiceNumber }}</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #0f172a;
            background: #fff;
        }

        @page {
            size: A4 portrait;
            margin: {{ (float) ($margins['top_margin'] ?? 20) }}mm {{ (float) ($margins['right_margin'] ?? 20) }}mm {{ (float) ($margins['bottom_margin'] ?? 20) }}mm {{ (float) ($margins['left_margin'] ?? 20) }}mm;
        }

        .page {
            padding: 0;
        }

        .invoice {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
        }

        .topbar {
            background: #0f172a;
            color: #fff;
            padding: 22px 28px;
        }

        .topbar h1 {
            margin: 0;
            font-size: 26px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .topbar p {
            margin: 6px 0 0;
            font-size: 12px;
            opacity: 0.88;
        }

        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            padding: 22px 28px;
            border-bottom: 1px solid #e2e8f0;
        }

        .panel h2 {
            margin: 0 0 8px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #475569;
        }

        .panel p {
            margin: 0;
            font-size: 13px;
            line-height: 1.6;
            color: #334155;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .items {
            margin: 0;
        }

        .items thead th {
            background: #f8fafc;
            color: #334155;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            text-align: left;
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
        }

        .items tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
            vertical-align: top;
        }

        .items tbody tr:nth-child(even) td {
            background: #fcfcfd;
        }

        .summary {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            padding: 22px 28px 28px;
        }

        .summary-note {
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }

        .totals {
            min-width: 240px;
        }

        .totals td {
            padding: 6px 0;
            font-size: 13px;
        }

        .totals .label {
            color: #475569;
            padding-right: 24px;
        }

        .totals .value {
            text-align: right;
            white-space: nowrap;
        }

        .grand {
            font-weight: 700;
            font-size: 15px;
        }

        .footer {
            padding: 0 28px 24px;
            font-size: 11px;
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="page">
        <div class="invoice">
            <div class="topbar">
                <h1>{{ $companyName }}</h1>
                <p>{{ $companyAddress }} | {{ $companyPhone }} | {{ $companyEmail }}</p>
            </div>

            <div class="meta-grid">
                <div class="panel">
                    <h2>Bill To</h2>
                    <p>
                        <strong>{{ $clientName }}</strong><br>
                        {{ $clientAddress }}
                    </p>
                </div>
                <div class="panel">
                    <h2>Invoice Details</h2>
                    <p>
                        Invoice No: <strong>{{ $invoiceNumber }}</strong><br>
                        Date: <strong>{{ $invoiceDate }}</strong><br>
                        Top Margin: <strong>{{ $margins['top_margin'] }} mm</strong>
                    </p>
                </div>
            </div>

            <table class="items">
                <thead>
                    <tr>
                        <th style="width: 10%;">#</th>
                        <th style="width: 42%;">Item</th>
                        <th style="width: 20%;">Qty</th>
                        <th style="width: 14%;">Rate</th>
                        <th style="width: 14%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td>
                                <strong>{{ $item['name'] }}</strong><br>
                                <span style="color:#64748b;">{{ $item['description'] }}</span>
                            </td>
                            <td>{{ $item['qty'] }}</td>
                            <td>Rs. {{ number_format((float) $item['rate'], 2) }}</td>
                            <td>Rs. {{ number_format((float) $item['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="summary">
                <div class="summary-note">
                    This preview is generated from the currently selected letterhead and margins. Use it to check alignment, header spacing, and page breaks before saving.
                </div>
                <table class="totals">
                    <tr>
                        <td class="label">Subtotal</td>
                        <td class="value">Rs. {{ number_format((float) $subtotal, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label">GST (18%)</td>
                        <td class="value">Rs. {{ number_format((float) $gst, 2) }}</td>
                    </tr>
                    <tr>
                        <td class="label grand">Grand Total</td>
                        <td class="value grand">Rs. {{ number_format((float) $grandTotal, 2) }}</td>
                    </tr>
                </table>
            </div>

            <div class="footer">
                Right margin: {{ $margins['right_margin'] }} mm | Bottom margin: {{ $margins['bottom_margin'] }} mm
            </div>
        </div>
    </div>
</body>
</html>
