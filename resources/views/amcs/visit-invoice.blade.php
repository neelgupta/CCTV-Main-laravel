<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice - {{ $document->document_number }}</title>
    @php
        $pdfMargins = $pdfMargins ?? [
            'top_margin' => 20,
            'bottom_margin' => 20,
            'left_margin' => 20,
            'right_margin' => 20,
        ];
        $pdfPageMargin = $pdfPageMargin ?? sprintf(
            '%gmm %gmm %gmm %gmm',
            (float) ($pdfMargins['top_margin'] ?? 20),
            (float) ($pdfMargins['right_margin'] ?? 20),
            (float) ($pdfMargins['bottom_margin'] ?? 20),
            (float) ($pdfMargins['left_margin'] ?? 20),
        );
        $company = $company ?? auth()->user()?->company;
        // Seller and party details frozen when the invoice was issued; older invoices fall back to live data.
        $billingSnapshot = is_array($document?->billing_snapshot ?? null) ? $document->billing_snapshot : null;
        $sellerSnapshot = $billingSnapshot['seller'] ?? null;
        $amcPartySnapshot = $billingSnapshot['amc_party'] ?? null;
        $sellerGstNumber = $sellerSnapshot ? ($sellerSnapshot['gst_number'] ?? '') : ($company?->gst_number ?? '');
        $companyName = ($sellerSnapshot ? ($sellerSnapshot['name'] ?? null) : $company?->name) ?? 'WE4WORK';
        $companyAddress = trim((string) ($sellerSnapshot ? ($sellerSnapshot['address'] ?? '') : ($company?->address ?? '')));
        $companyContact = trim(implode(', ', array_filter([
            trim((string) ($sellerSnapshot ? ($sellerSnapshot['phone_number'] ?? '') : ($company?->phone_number ?? ''))),
            trim((string) ($sellerSnapshot ? ($sellerSnapshot['email'] ?? '') : ($company?->email ?? ''))),
        ])));
        $companyGstin = filled($sellerGstNumber) ? (string) $sellerGstNumber : '-';

        $amc = $amc ?? $visit?->amc;
        $visitNo = (int) ($visit->visit_no ?? 0);
        $isPaid = (string) ($paymentStatus ?? 'Unpaid') === 'Paid';

        $invoiceNumber = (string) ($document->document_number ?? '-');
        $invoiceDate = optional($document->created_at)->format('d-m-Y') ?? now()->format('d-m-Y');
        $invoiceSubject = 'AMC Visit #' . ($visitNo > 0 ? $visitNo : '-') . ' - ' . (filled($amc?->project_name) ? $amc->project_name : (filled($amc?->contract_no) ? $amc->contract_no : 'Service Visit'));

        if ($amcPartySnapshot) {
            $partyName = trim((string) ($amcPartySnapshot['name'] ?? ''));
            $partyAddress = trim((string) ($amcPartySnapshot['address'] ?? ''));
            $partyContact = trim((string) ($amcPartySnapshot['contact'] ?? ''));
            $partyEmail = trim((string) ($amcPartySnapshot['email'] ?? ''));
        } else {
            $partyName = trim((string) (filled($amc?->customer_name) ? $amc->customer_name : ($ticket->customer_name ?? '')));
            $partyAddress = trim((string) (filled($amc?->address) ? $amc->address : ($ticket->customer_address ?? '')));
            $partyContact = trim((string) (filled($amc?->mobile_number) ? $amc->mobile_number : ($ticket->customer_contact_number ?? '')));
            $partyEmail = trim((string) (filled($amc?->email) ? $amc->email : ($ticket->customer_email ?? '')));
        }
        $ticketRef = trim((string) ($ticket->ticket_number ?? $ticket->ticket_code ?? $ticket->id ?? ''));

        $items = collect($items ?? [])->filter(fn ($item) => is_array($item))->values();
        $lineItems = $items->map(function (array $item, int $index) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $amount = (float) ($item['total'] ?? $item['amount'] ?? ($qty * $price));

            return [
                'sr_no' => $index + 1,
                'description' => trim((string) ($item['description'] ?? $item['name'] ?? '-')),
                'note' => trim((string) ($item['note'] ?? '')),
                'qty' => $qty,
                'unit' => trim((string) ($item['unit'] ?? '-')) ?: '-',
                'price' => $price,
                'amount' => $amount,
            ];
        });

        if ($lineItems->isEmpty()) {
            $lineItems = collect([[
                'sr_no' => 1,
                'description' => $invoiceSubject,
                'note' => '',
                'qty' => 1,
                'unit' => 'Visit',
                'price' => (float) $total,
                'amount' => (float) $total,
            ]]);
        }

        $subtotal = (float) ($subtotal ?? 0);
        $discount = (float) ($discount ?? 0);
        $gstAmount = (float) ($gstAmount ?? 0);
        $grandTotal = (float) ($total ?? max(0, $subtotal - $discount + $gstAmount));
        $paidAmount = $isPaid ? $grandTotal : 0.0;
        $pendingAmount = max(0, $grandTotal - $paidAmount);
        $paymentMethodLabel = $isPaid ? (string) ($paymentMethod ?? '-') : '-';
        $statusLabel = $isPaid ? 'PAID' : 'UNPAID';

        $amountInWords = function (float $amount): string {
            $amount = round($amount, 2);
            $rupees = (int) floor($amount);
            $paise = (int) round(($amount - $rupees) * 100);

            $ones = [
                0 => 'Zero', 1 => 'One', 2 => 'Two', 3 => 'Three', 4 => 'Four',
                5 => 'Five', 6 => 'Six', 7 => 'Seven', 8 => 'Eight', 9 => 'Nine',
                10 => 'Ten', 11 => 'Eleven', 12 => 'Twelve', 13 => 'Thirteen', 14 => 'Fourteen',
                15 => 'Fifteen', 16 => 'Sixteen', 17 => 'Seventeen', 18 => 'Eighteen', 19 => 'Nineteen',
            ];
            $tens = [
                2 => 'Twenty', 3 => 'Thirty', 4 => 'Forty', 5 => 'Fifty',
                6 => 'Sixty', 7 => 'Seventy', 8 => 'Eighty', 9 => 'Ninety',
            ];

            $belowHundred = function (int $number) use (&$belowHundred, $ones, $tens): string {
                if ($number < 20) {
                    return $ones[$number];
                }

                $ten = intdiv($number, 10);
                $rest = $number % 10;

                return $tens[$ten] . ($rest > 0 ? ' ' . $ones[$rest] : '');
            };

            $indian = function (int $number) use (&$indian, $belowHundred): string {
                if ($number < 100) {
                    return $belowHundred($number);
                }

                if ($number < 1000) {
                    $hundreds = intdiv($number, 100);
                    $rest = $number % 100;

                    return $belowHundred($hundreds) . ' Hundred' . ($rest > 0 ? ' ' . $indian($rest) : '');
                }

                foreach ([10000000 => 'Crore', 100000 => 'Lakh', 1000 => 'Thousand'] as $divider => $label) {
                    if ($number >= $divider) {
                        $head = intdiv($number, $divider);
                        $rest = $number % $divider;

                        return $indian($head) . ' ' . $label . ($rest > 0 ? ' ' . $indian($rest) : '');
                    }
                }

                return (string) $number;
            };

            $words = $indian($rupees);

            return $paise > 0
                ? 'Rupees ' . $words . ' and ' . $belowHundred($paise) . ' Paise Only'
                : 'Rupees ' . $words . ' Only';
        };
    @endphp
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        :root {
            --bg: #eef2f6;
            --paper: #ffffff;
            --border: #94a3b8;
            --light-border: #cbd5e1;
            --muted: #64748b;
            --ink: #0f172a;
            --soft: #f8fafc;
            --accent: #0f172a;
            --success: #d1fae5;
            --success-text: #166534;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: var(--bg);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }
        .sheet {
            max-width: 980px;
            margin: 12px auto 28px;
            padding: 0 12px;
        }
        .paper {
            width: 100%;
            max-width: 190mm;
            margin: 0 auto;
            background: var(--paper);
            border: 1px solid #d1d5db;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            padding: 8mm;
        }
        .doc {
            border: 1px solid var(--border);
            padding: 10px;
        }
        .topline {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 12px;
            font-size: 11px;
            line-height: 1.45;
            margin-bottom: 6px;
        }
        .brand-block {
            text-align: center;
            border-bottom: 1px solid var(--border);
            padding: 8px 10px 10px;
        }
        .company-name {
            margin: 0;
            font-size: 30px;
            line-height: 1;
            letter-spacing: 0.02em;
            font-weight: 800;
            text-transform: uppercase;
        }
        .company-meta {
            margin: 5px 0 0;
            font-size: 10px;
            line-height: 1.35;
        }
        .panels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            border-bottom: 1px solid var(--border);
        }
        .panel {
            padding: 10px 12px 12px;
            min-height: 112px;
        }
        .panel + .panel {
            border-left: 1px solid var(--border);
        }
        .panel-title {
            margin: 0 0 6px;
            font-size: 12px;
            font-weight: 800;
        }
        .party-name {
            margin: 0 0 4px;
            font-size: 14px;
            font-weight: 800;
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
            grid-template-columns: 108px 10px 1fr;
            gap: 4px;
            align-items: start;
            font-size: 11px;
            line-height: 1.45;
            margin-bottom: 2px;
        }
        .meta-row .label {
            font-weight: 400;
        }
        .meta-row .value {
            overflow-wrap: anywhere;
        }
        .subject-block {
            border-bottom: 1px solid var(--border);
            background: var(--soft);
            padding: 8px 12px 9px;
        }
        .subject-label {
            margin: 0 0 4px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--muted);
        }
        .subject-value {
            margin: 0;
            font-size: 16px;
            font-weight: 800;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .items {
            border-bottom: 1px solid var(--border);
        }
        .items th,
        .items td {
            border: 1px solid var(--light-border);
            padding: 7px 8px;
            font-size: 11px;
            vertical-align: top;
        }
        .items th {
            background: #f8fafc;
            text-transform: uppercase;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.04em;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 280px;
            gap: 0;
            border-bottom: 1px solid var(--border);
        }
        .tax-box,
        .total-box {
            min-height: 118px;
            padding: 8px 12px 10px;
        }
        .tax-box + .total-box {
            border-left: 1px solid var(--border);
        }
        .summary-title {
            margin: 0 0 8px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            color: #374151;
        }
        .summary-line {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            margin: 0 0 4px;
            font-size: 11px;
            line-height: 1.45;
        }
        .summary-line strong {
            font-size: 12px;
        }
        .grand {
            font-size: 15px;
            font-weight: 800;
        }
        .bill-box {
            margin-top: 8px;
            padding: 8px 10px;
            border: 1px solid var(--light-border);
            background: #f8fafc;
            font-size: 11px;
            line-height: 1.5;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 10px;
            border-radius: 999px;
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .badge-paid {
            background: var(--success);
            color: var(--success-text);
        }
        .badge-unpaid {
            background: #fee2e2;
            color: #991b1b;
        }
        .words {
            padding: 8px 12px;
            border-bottom: 1px solid var(--border);
            font-size: 11px;
            font-weight: 700;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 220px;
        }
        .footer-left,
        .footer-right {
            padding: 10px 12px;
        }
        .footer-right {
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
        .signature-line {
            /* width: 100%;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid var(--border);
            font-size: 11px;
            font-weight: 800; */
        }
        .terms {
            margin-top: 4px;
            font-size: 10px;
            line-height: 1.45;
        }
        @page {
            size: A4 portrait;
            margin: {{ $pdfPageMargin }};
        }
        body {
            background: #fff;
        }
        .sheet {
            margin: 0;
            padding: 0;
            max-width: none;
        }
        .paper {
            max-width: none;
            border: none;
            box-shadow: none;
            padding: 0;
        }
        @media print {
            body { background: #fff; }
            .sheet { margin: 0; padding: 0; max-width: none; }
            .paper {
                max-width: none;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .doc {
                border: 1px solid #4b5563;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="paper" style="width:100%">
            <div class="doc">
                <div class="topline">
                    <div class="gstin"><strong>GSTIN :</strong> {{ $companyGstin }}</div>
                    <div class="right">
                        <div><strong>Invoice No :</strong> {{ $invoiceNumber }}</div>
                        <div><strong>Dated :</strong> {{ $invoiceDate }}</div>
                    </div>
                </div>

                <div class="brand-block">
                    <h1 class="company-name">{{ strtoupper($companyName) }}</h1>
                    <p class="company-meta">
                        {{ $companyAddress !== '' ? $companyAddress : ' ' }}
                        @if($companyContact !== '')
                            <br>{{ $companyContact }}
                        @endif
                    </p>
                </div>

                <div class="panels">
                    <div class="panel">
                        <p class="panel-title">Party Details :</p>
                        <p class="party-name">{{ $partyName !== '' ? $partyName : '-' }}</p>
                        <p class="party-line">{{ $partyAddress !== '' ? $partyAddress : '-' }}</p>
                        <p class="party-line">{{ $partyContact !== '' ? $partyContact : '-' }}</p>
                        @if($partyEmail !== '')
                            <p class="party-line">{{ $partyEmail }}</p>
                        @endif
                        <p class="party-line">Ticket # : {{ $ticketRef !== '' ? $ticketRef : '-' }}</p>
                    </div>
                    <div class="panel">
                        <p class="panel-title">Invoice Details :</p>
                        <div class="meta-row">
                            <div class="label">Invoice No</div>
                            <div>:</div>
                            <div class="value">{{ $invoiceNumber }}</div>
                        </div>
                        <div class="meta-row">
                            <div class="label">Date</div>
                            <div>:</div>
                            <div class="value">{{ $invoiceDate }}</div>
                        </div>
                        <div class="meta-row">
                            <div class="label">Subject</div>
                            <div>:</div>
                            <div class="value">{{ $invoiceSubject }}</div>
                        </div>
                    </div>
                </div>

                <div class="subject-block">
                    <p class="subject-label">Invoice Subject</p>
                    <p class="subject-value">{{ $invoiceSubject }}</p>
                </div>

                <table class="items">
                    <thead>
                        <tr>
                            <th style="width: 50px;">S.N</th>
                            <th>Description of Goods</th>
                            <th style="width: 80px;" class="center">Qty.</th>
                            <th style="width: 80px;" class="center">Unit</th>
                            <th style="width: 100px;" class="right">Price</th>
                            <th style="width: 100px;" class="right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($lineItems as $row)
                            <tr>
                                <td class="center">{{ $row['sr_no'] }}</td>
                                <td>
                                    <div>{{ $row['description'] }}</div>
                                    @if($row['note'] !== '')
                                        <div style="margin-top: 3px; color: #6b7280; font-size: 10px;">{{ $row['note'] }}</div>
                                    @endif
                                </td>
                                <td class="center">{{ number_format((float) $row['qty'], 2) }}</td>
                                <td class="center">{{ $row['unit'] }}</td>
                                <td class="right">{{ number_format((float) $row['price'], 2) }}</td>
                                <td class="right">{{ number_format((float) $row['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <div class="summary-grid">
                    <div class="tax-box">
                        <p class="summary-title">Tax Summary</p>
                        <div class="summary-line">
                            <span>Subtotal</span>
                            <span>{{ number_format($subtotal, 2) }}</span>
                        </div>
                        <div class="summary-line">
                            <span>Discount</span>
                            <span>{{ number_format($discount, 2) }}</span>
                        </div>
                        <div class="summary-line">
                            <span>GST Amount</span>
                            <span>{{ number_format($gstAmount, 2) }}</span>
                        </div>
                        <div class="summary-line">
                            <span>Paid Amount</span>
                            <span>{{ number_format($paidAmount, 2) }}</span>
                        </div>
                        <div class="summary-line">
                            <span>Pending Amount</span>
                            <span>{{ number_format($pendingAmount, 2) }}</span>
                        </div>
                    </div>
                    <div class="total-box">
                        <p class="summary-title">Billing Total</p>
                        <div class="summary-line">
                            <span>Grand Total</span>
                            <span class="grand">&#8377;{{ number_format($grandTotal, 2) }}</span>
                        </div>
                        <!-- <div class="bill-box">
                            <div><strong>Payment Status :</strong> {{ $statusLabel }}</div>
                            <div><strong>Payment Method :</strong> {{ $paymentMethodLabel }}</div>
                        </div> -->
                    </div>
                </div>

                <div class="words">
                    {{ $amountInWords($grandTotal) }}
                </div>

                <div class="footer-grid">
                    <div class="footer-left">
                        <div class="terms">
                            <div><strong>Note :</strong> {{ filled($document->notes ?? null) ? $document->notes : 'Thank you for your business.' }}</div>
                        </div>
                    </div>
                    <div class="footer-right">
                        <div style="font-size: 20px; color: #6b7280; margin-bottom: 14px;">For {{ $companyName }}</div>
                        <div >Authorised Signatory</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
