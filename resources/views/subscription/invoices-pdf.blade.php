<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subscription Invoices</title>
    <style>
        @page { margin: 14mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0f172a; margin: 0; }
        .invoice { border: 1px solid #cbd5e1; page-break-after: always; }
        .invoice:last-child { page-break-after: auto; }
        table { width: 100%; border-collapse: collapse; }
        td, th { vertical-align: top; }
        .section { border-top: 1px solid #cbd5e1; }
        .header { padding: 14px 12px; text-align: center; }
        .business-name { font-size: 24px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; }
        .cell { padding: 8px 12px; }
        .divider { border-right: 1px solid #cbd5e1; }
        .label { font-weight: bold; margin-bottom: 6px; }
        .party-name { font-size: 14px; font-weight: bold; text-transform: uppercase; }
        .muted { color: #64748b; font-size: 9px; }
        .status { background: #d1fae5; color: #047857; font-weight: bold; font-size: 9px; padding: 2px 8px; border-radius: 8px; }
        .items th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; }
        .items th, .items td { border: 1px solid #cbd5e1; padding: 5px 6px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .summary td { padding: 3px 0; }
        .grand-total { font-size: 22px; font-weight: bold; text-align: right; margin-top: 6px; }
        .signatory { border-top: 1px solid #cbd5e1; margin-top: 24px; padding-top: 4px; font-weight: bold; }
    </style>
</head>
<body>
    @foreach($invoices as $invoice)
        <div class="invoice">
            <div class="header">
                <div class="business-name">{{ $invoice['businessName'] ?: config('app.name') }}</div>
                @if($invoice['businessAddress'])
                    <div>{{ $invoice['businessAddress'] }}</div>
                @endif
                <div>{{ $invoice['businessMobile'] ?: '-' }}@if($invoice['businessEmail']) | {{ $invoice['businessEmail'] }}@endif</div>
                <div>GSTIN : {{ $invoice['businessGst'] ?: '-' }}</div>
            </div>

            <table class="section">
                <tr>
                    <td class="cell divider" style="width: 50%;">
                        <div class="label">Party Details :</div>
                        <div class="party-name">{{ $invoice['partyCompanyName'] }}</div>
                        @if($invoice['partyCompanyAddress'])
                            <div>{{ $invoice['partyCompanyAddress'] }}</div>
                        @endif
                        <div>{{ $invoice['partyCompanyMobile'] ?: '-' }}</div>
                        <div>{{ $invoice['partyCompanyEmail'] ?: '-' }}</div>
                        @if($invoice['partyCompanyGst'])
                            <div>GSTIN : {{ $invoice['partyCompanyGst'] }}</div>
                        @endif
                    </td>
                    <td class="cell" style="width: 50%;">
                        <div class="label">Invoice Details :</div>
                        <table class="summary">
                            <tr><td style="width: 35%;">Invoice No</td><td style="width: 5%;">:</td><td>{{ $invoice['invoiceNumber'] }}</td></tr>
                            <tr><td>Date</td><td>:</td><td>{{ $invoice['invoiceDate'] }}</td></tr>
                            <tr><td>Status</td><td>:</td><td><span class="status">{{ $invoice['status'] }}</span></td></tr>
                        </table>
                    </td>
                </tr>
            </table>

            <table class="section items">
                <thead>
                    <tr>
                        <th style="width: 6%;">S.N</th>
                        <th>Description of Goods</th>
                        <th style="width: 9%;">Qty.</th>
                        <th style="width: 9%;">Unit</th>
                        <th style="width: 15%;">Price</th>
                        <th style="width: 15%;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="center">1</td>
                        <td>
                            {{ $invoice['planName'] }}
                            <div class="muted">Subscription Renewal</div>
                        </td>
                        <td class="center">1.00</td>
                        <td class="center">Plan</td>
                        <td class="right">₹{{ $invoice['subtotalFormatted'] }}</td>
                        <td class="right">₹{{ $invoice['subtotalFormatted'] }}</td>
                    </tr>
                </tbody>
            </table>

            <table class="section">
                <tr>
                    <td class="cell divider" style="width: 50%;">
                        <div class="label">TAX SUMMARY</div>
                        <table class="summary">
                            <tr><td>Subtotal</td><td class="right">₹{{ $invoice['subtotalFormatted'] }}</td></tr>
                            <tr><td>Discount</td><td class="right">0.00</td></tr>
                            <tr><td>GST Amount ({{ rtrim(rtrim(number_format($invoice['gstRate'], 2), '0'), '.') }}%)</td><td class="right">₹{{ $invoice['gstAmountFormatted'] }}</td></tr>
                            <tr><td>Paid Amount</td><td class="right">₹{{ $invoice['grandTotal'] }}</td></tr>
                            <tr><td>Pending Amount</td><td class="right">0.00</td></tr>
                        </table>
                    </td>
                    <td class="cell" style="width: 50%;">
                        <div class="label">BILLING TOTAL</div>
                        <div>Grand Total</div>
                        <div class="grand-total">₹{{ $invoice['grandTotal'] }}</div>
                    </td>
                </tr>
            </table>

            <div class="section cell" style="font-weight: bold;">
                Rupees {{ $invoice['amountWords'] ? ucwords($invoice['amountWords']) : 'Zero' }} Only
            </div>

            <table class="section">
                <tr>
                    <td class="cell divider" style="width: 50%;">
                        <div><strong>Note :</strong> Thank you for your business.</div>
                        <div style="margin-top: 6px;"><strong>Payment Method :</strong> {{ $invoice['paymentMethod'] }}</div>
                    </td>
                    <td class="cell center" style="width: 50%;">
                        <div style="font-size: 13px; color: #475569;">For {{ $invoice['businessName'] ?: config('app.name') }}</div>
                        <div class="signatory">Authorised Signatory</div>
                    </td>
                </tr>
            </table>
        </div>
    @endforeach
</body>
</html>
