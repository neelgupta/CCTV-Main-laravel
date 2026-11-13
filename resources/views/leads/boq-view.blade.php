<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BOQ - {{ $lead->client_name }}</title>
    @php
        use App\Support\QuotationTaxCalculator;
        use Illuminate\Support\Str;

        $isPdf = (bool) ($isPdf ?? false);
        $useLetterhead = (bool) ($useLetterhead ?? false);
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
        $company = $lead->company ?? auth()->user()?->company;
        $displayVersion = $quotation->version ?? $company?->nextAvailableQuotationVersion();
        $showBrandHeader = $company?->shouldShowPrintLogo() ?? true;
        $companyLogoUrl = $showBrandHeader ? ($company?->print_logo_url ?? $company?->logo_url) : null;
        $companyName = $company?->name ?? 'Workshop Platform';
        $companyAddress = trim((string) ($company?->address ?? ''));
        $companyContact = trim(implode(', ', array_filter([
            trim((string) ($company?->phone_number ?? '')),
            trim((string) ($company?->email ?? '')),
        ])));
        $showMsmeNumber = (bool) ($company?->show_msme_number_in_quotation ?? false);
        $companyMsmeNumber = trim((string) ($company?->msme_number ?? ''));

        $formData = is_array($quotation->form_data ?? null) ? $quotation->form_data : [];
        $get = function (string $path, $default = '') use ($formData) {
            return data_get($formData, $path, $default);
        };

        $companyStateName = QuotationTaxCalculator::resolveCompanyStateName($company);
        $customerStateName = QuotationTaxCalculator::resolveCustomerStateName($formData, $lead, $client ?? null);
        $isIntraState = QuotationTaxCalculator::isIntraState($companyStateName, $customerStateName);

        $customFieldEntries = collect($company?->leadCustomFieldsForQuotation() ?? [])
            ->map(function ($field) use ($formData, $lead) {
                $rawValue = data_get($formData, "custom_fields.{$field['key']}");

                return [
                    'raw' => $rawValue,
                    'label' => $field['label'],
                    'value' => $lead->formatCustomFieldValue($field, $rawValue),
                ];
            })
            ->filter(fn ($entry) => $entry['raw'] !== null && $entry['raw'] !== '' && $entry['raw'] !== [])
            ->values();

        $sourceItems = collect($formData['items'] ?? []);
        $categoryLookup = \App\Models\BoqCategory::query()
            ->where('company_id', $company?->id)
            ->pluck('name', 'id');
        $productLookup = \App\Models\BoqProduct::query()
            ->where('company_id', $company?->id)
            ->with('category:id,name')
            ->get()
            ->keyBy('id');

        $normalizedItems = $sourceItems->map(function ($item, $index) use ($categoryLookup, $productLookup) {
            $product = null;
            if (!empty($item['product_id'])) {
                $product = $productLookup[(int) $item['product_id']] ?? null;
            }

            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? $item['rate'] ?? $item['unit_price'] ?? $product?->price ?? 0);
            $gst = (float) ($item['gst'] ?? $product?->gst ?? 0);
            $baseTotal = (float) ($item['base_total'] ?? ($qty * $price));
            $gstAmount = (float) ($item['gst_amount'] ?? (($baseTotal * $gst) / 100));
            $lineTotal = (float) ($item['total'] ?? $item['amount'] ?? ($baseTotal + $gstAmount));

            $productName = trim((string) (
                $item['product_name']
                ?? $item['name']
                ?? $item['title']
                ?? $item['item_name']
                ?? $product?->name
                ?? $item['description']
                ?? ''
            ));
            $description = trim((string) ($item['description'] ?? $product?->description ?? ''));
            $brandModel = trim((string) ($item['brand_model'] ?? $item['spec'] ?? $item['brand'] ?? $item['model'] ?? ''));
            $hsnSac = trim((string) (
                $item['hsn_sac_code']
                ?? $item['hsn_code']
                ?? $item['hsn']
                ?? $item['sac_code']
                ?? $product?->hsn_sac_code
                ?? ''
            ));
            $unit = trim((string) ($item['unit'] ?? $product?->unit ?? ''));
            $categoryName = $categoryLookup[(string) ($item['category_id'] ?? '')] ?? ($item['category_name'] ?? $product?->category?->name ?? '');

            return [
                'sr_no' => $index + 1,
                'category_name' => $categoryName !== '' ? $categoryName : 'Items',
                'product_name' => $productName !== '' ? $productName : '-',
                'description' => $description !== '' ? $description : '-',
                'brand_model' => $brandModel,
                'hsn_sac_code' => $hsnSac !== '' ? $hsnSac : '-',
                'unit' => $unit !== '' ? $unit : '-',
                'qty' => $qty,
                'price' => $price,
                'gst' => $gst,
                'base_total' => $baseTotal,
                'gst_amount' => $gstAmount,
                'total' => $lineTotal,
            ];
        })->values();

        if ($normalizedItems->isEmpty()) {
            $normalizedItems = collect([[
                'sr_no' => 1,
                'category_name' => 'Items',
                'product_name' => '-',
                'description' => '-',
                'brand_model' => '',
                'hsn_sac_code' => '-',
                'unit' => '-',
                'qty' => 0,
                'price' => 0,
                'gst' => 0,
                'base_total' => 0,
                'gst_amount' => 0,
                'total' => 0,
            ]]);
        }

        $discount = (float) ($formData['pricing']['discount'] ?? $formData['discount'] ?? 0);
        $taxSummary = QuotationTaxCalculator::calculate($normalizedItems->all(), $discount, $isIntraState);
        $subtotal = $taxSummary['subtotal'];
        $effectiveDiscount = $taxSummary['discount'];
        $taxableAmount = $taxSummary['taxable_amount'];
        $gstAmount = $taxSummary['gst_amount'];
        $grandTotal = $taxSummary['grand_total'];

        $quoteNo = '#BOQ-' . $lead->id . '-' . $displayVersion;
        $quoteDate = optional($quotation->created_at ?? now())->format('d-m-Y');

        $partyName = trim((string) ($lead->client_name ?? ''));
        $partyAddress = trim((string) ($get('client.site_address', $lead->address) ?: $lead->address ?: ''));
        $partyCity = trim((string) ($client?->city?->name ?? $lead->city?->name ?? ''));
        $partyState = trim((string) ($client?->state?->name ?? $lead->state?->name ?? ''));
        $partyMobile = trim((string) ($lead->mobile ?? ''));
        $partyEmail = trim((string) ($lead->email ?? ''));
        $partyGstin = trim((string) ($lead->gst_number ?? ''));
        $partyPan = trim((string) ($lead->pan_number ?? ''));

        $subject = trim((string) ($get('subject') ?: $get('project.subject') ?: ''));

        $legacyTermsText = collect([
            ['label' => 'TERM CONDITION1', 'value' => $get('terms.warranty')],
            ['label' => 'TERM CONDITION2', 'value' => $get('terms.payment')],
            ['label' => 'TERM CONDITION3', 'value' => $get('terms.delivery')],
            ['label' => 'TERM CONDITION4', 'value' => $get('terms.validity')],
            ['label' => 'TERM CONDITION5', 'value' => $get('terms.amc')],
        ])
            ->filter(fn ($row) => filled($row['value'] ?? null))
            ->map(fn ($row) => $row['label'] . ': ' . $row['value'])
            ->implode("\n");
        $termSource = trim((string) (data_get($formData, 'terms.conditions') ?: $legacyTermsText ?: ($company?->defaultTermsConditionsText() ?? '')));
        $termHtml = $termSource !== ''
            ? (\Illuminate\Support\Str::contains($termSource, '<') ? $termSource : nl2br(e($termSource)))
            : ($company?->defaultTermsConditionsHtml() ?? '');

        $effectiveTaxRate = $taxableAmount > 0 ? ($gstAmount / $taxableAmount) * 100 : 0;
        $splitTaxRate = $isIntraState ? $effectiveTaxRate / 2 : $effectiveTaxRate;

        $numberToWords = function (float $amount): string {
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

            $convertBelowHundred = function (int $number) use (&$convertBelowHundred, $ones, $tens): string {
                if ($number < 20) {
                    return $ones[$number];
                }

                $ten = intdiv($number, 10);
                $rest = $number % 10;

                return $tens[$ten] . ($rest > 0 ? ' ' . $ones[$rest] : '');
            };

            $convertIndian = function (int $number) use (&$convertIndian, $convertBelowHundred): string {
                if ($number < 100) {
                    return $convertBelowHundred($number);
                }

                if ($number < 1000) {
                    $hundreds = intdiv($number, 100);
                    $rest = $number % 100;

                    return $convertBelowHundred($hundreds) . ' Hundred' . ($rest > 0 ? ' ' . $convertIndian($rest) : '');
                }

                $units = [
                    10000000 => 'Crore',
                    100000 => 'Lakh',
                    1000 => 'Thousand',
                ];

                foreach ($units as $divider => $label) {
                    if ($number >= $divider) {
                        $head = intdiv($number, $divider);
                        $rest = $number % $divider;

                        return $convertIndian($head) . ' ' . $label . ($rest > 0 ? ' ' . $convertIndian($rest) : '');
                    }
                }

                return (string) $number;
            };

            $rupeeWords = $convertIndian($rupees);

            if ($paise > 0) {
                return 'Rupees ' . $rupeeWords . ' and ' . $convertBelowHundred($paise) . ' Paise Only';
            }

            return 'Rupees ' . $rupeeWords . ' Only';
        };
    @endphp
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        :root {
            --page-bg: #eef1f4;
            --paper: #ffffff;
            --ink: #2f2f2f;
            --muted: #666666;
            --line: #b9b9b9;
            --soft-line: #d7d7d7;
            --heavy: #4a4a4a;
            --brand: var(--company-accent, #222222);
        }
        html, body {
            margin: 0;
            padding: 0;
            background: var(--page-bg);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }
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
        .btn-print {
            background: #111827;
            color: #fff;
            border-color: #111827;
        }
        .btn-download {
            background: #fff;
            color: #ef4444;
            border-color: #ef4444;
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
            border: 1px solid var(--line);
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
            padding: 8mm 8mm 7mm;
        }
        .doc-shell {
            border: 1px solid var(--heavy);
            padding: 5px;
        }
        .topline {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: start;
            gap: 10px;
            font-size: 11px;
            margin-bottom: 4px;
            min-height: 18px;
        }
        .topline .gstin {
            text-align: left;
            white-space: nowrap;
            padding-top: 1px;
        }
        .topline .empty {
            min-height: 1px;
        }
        .topline .quote-mini {
            justify-self: end;
            text-align: left;
            min-width: 160px;
            line-height: 1.45;
        }
        .brand-block {
            border-bottom: 1px solid var(--heavy);
            padding: 2px 8px 6px;
            text-align: center;
        }
        .brand-logo {
            max-height: 28px;
            max-width: 140px;
            display: block;
            margin: 0 auto 2px;
            object-fit: contain;
        }
        .company-name {
            margin: 0;
            font-size: 24px;
            line-height: 1.05;
            letter-spacing: 0.02em;
            font-weight: 800;
            text-transform: uppercase;
        }
        .company-meta {
            margin: 2px 0 0;
            font-size: 10px;
            line-height: 1.35;
        }
        .info-panels {
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 0;
            border-bottom: 1px solid var(--heavy);
        }
        .panel {
            padding: 8px 10px 10px;
            min-height: 98px;
        }
        .panel + .panel {
            border-left: 1px solid var(--heavy);
        }
        .panel-title {
            margin: 0 0 5px;
            font-size: 11px;
            line-height: 1;
            font-weight: 700;
        }
        .party-name {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .party-line, .quote-line {
            margin: 0;
            font-size: 11px;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .quote-meta {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2px;
            font-size: 11px;
        }
        .quote-meta-row {
            display: grid;
            grid-template-columns: 92px 10px 1fr;
            gap: 4px;
            align-items: start;
        }
        .quote-meta-row strong {
            font-weight: 700;
        }
        .terms-list {
            margin-top: 6px;
            border-top: 1px solid var(--soft-line);
            padding-top: 5px;
            font-size: 10px;
        }
        .terms-list p,
        .terms-list ul,
        .terms-list ol {
            margin: 0 0 4px;
            padding: 0;
        }
        .terms-list ul,
        .terms-list ol {
            padding-left: 16px;
        }
        .table-wrap {
            border-bottom: 1px solid var(--heavy);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        .items-table th,
        .items-table td {
            border-right: 1px solid var(--line);
            border-bottom: 1px solid var(--line);
            padding: 5px 6px;
            font-size: 11px;
            vertical-align: top;
        }
        .items-table th:last-child,
        .items-table td:last-child {
            border-right: 0;
        }
        .items-table thead th {
            font-size: 11px;
            font-weight: 700;
            text-align: center;
            background: #f7f7f7;
            line-height: 1.2;
        }
        .items-table .sn { width: 6%; text-align: center; }
        .items-table .desc { width: 52%; text-align: left; }
        .items-table .qty { width: 8%; text-align: center; }
        .items-table .unit { width: 8%; text-align: center; }
        .items-table .price { width: 13%; text-align: right; }
        .items-table .amount { width: 13%; text-align: right; }
        .item-main {
            font-size: 11px;
            line-height: 1.3;
            font-weight: 400;
            text-transform: uppercase;
        }
        .item-sub {
            margin-top: 1px;
            font-size: 9px;
            line-height: 1.25;
            color: #505050;
            font-style: italic;
        }
        .qty-text, .unit-text, .hsn-text, .num-text {
            white-space: nowrap;
        }
        .summary-block {
            display: grid;
            grid-template-columns: 1fr 250px;
            gap: 0;
            border-bottom: 1px solid var(--heavy);
        }
        .tax-box,
        .total-box {
            padding: 8px 10px;
            min-height: 92px;
        }
        .total-box {
            border-left: 1px solid var(--heavy);
        }
        .tax-table td {
            border: 1px solid var(--line);
            padding: 5px 6px;
            font-size: 10px;
            line-height: 1.25;
        }
        .tax-table .head td {
            font-weight: 700;
            background: #f7f7f7;
            text-align: center;
        }
        .tax-table .center {
            text-align: center;
        }
        .tax-table .right {
            text-align: right;
        }
        .totals {
            width: 100%;
            border-collapse: collapse;
        }
        .totals td {
            padding: 2px 0;
            font-size: 11px;
            line-height: 1.35;
            vertical-align: top;
        }
        .totals .label {
            width: 70%;
            text-align: left;
        }
        .totals .value {
            width: 30%;
            text-align: right;
            white-space: nowrap;
        }
        .totals .grand td {
            font-size: 13px;
            font-weight: 700;
            padding-top: 6px;
        }
        .amount-words {
            padding: 7px 10px 0;
            font-size: 11px;
            font-weight: 700;
            border-bottom: 1px solid var(--heavy);
            min-height: 30px;
        }
        .signature-row {
            display: grid;
            grid-template-columns: 1fr 210px;
            gap: 0;
        }
        .signature-note {
            padding: 10px;
            border-right: 1px solid var(--heavy);
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
        .muted {
            color: var(--muted);
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        @media print {
            body { background: #fff; }
            .actions { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; width: 100%; }
            .paper {
                box-shadow: none;
                border: 0;
                padding: 0;
                max-width: none;
                width: 100%;
                margin: 0;
            }
            .doc-shell {
                width: 100%;
                margin: 0;
                border: 1px solid var(--heavy);
            }
        }
        @if($isPdf)
        .sheet {
            margin: 0;
            padding: 0;
            max-width: none;
            width: 100%;
        }
        .paper {
            box-shadow: none;
            border: 0;
            padding: 0;
            max-width: none;
            width: 100%;
            margin: 0;
        }
        .doc-shell {
            width: 100%;
            margin: 0;
            border: 1px solid var(--heavy);
        }
        @page {
            size: A4 portrait;
            margin: {{ $pdfPageMargin }};
        }
        @if($useLetterhead)
        html, body {
            background: transparent;
        }
        .sheet {
            background: transparent;
        }
        .paper {
            background: transparent;
        }
        .doc-shell {
            background: transparent;
            border: 0;
        }
        @endif
        @else
        @page {
            margin: 6mm;
        }
        @endif
        @unless($isPdf)
        @include('partials.company-print-settings', ['company' => $company])
        @endunless
    </style>
</head>
<body id="product-boq-view-page">
    <div class="actions no-print">
        <button class="btn btn-print" type="button" onclick="window.print()">Print BOQ</button>
        <a class="btn btn-download" href="{{ route('leads.boq.download', [$lead, $quotation]) }}">PDF Download</a>
        <a class="btn" href="{{ route('leads.show', $lead) }}">Back to Lead</a>
    </div>

    <div class="sheet">
        <div class="paper" style="width:100%">
            <div class="doc-shell">
                <div class="topline print-brand-header">
                    <div class="gstin">
                        <strong>GSTIN :</strong> {{ filled($company?->gst_number) ? $company->gst_number : '-' }}
                    </div>
                    <div class="empty"></div>
                    <div class="quote-mini">
                        <div><strong>BOQ No</strong> : {{ $quoteNo }}</div>
                        <div><strong>Dated</strong> : {{ $quoteDate }}</div>
                    </div>
                </div>

                <div class="brand-block print-brand-header">
                  
                    <h1 class="company-name">{{ Str::upper($companyName) }}</h1>
                    <p class="company-meta">
                        {{ $companyAddress !== '' ? $companyAddress : ' ' }}<br>
                        @if($companyContact !== '')
                            Tel.: {{ $companyContact }}
                        @endif
                    </p>
                </div>

                <div class="info-panels">
                    <div class="panel">
                        <p class="panel-title">Party Details :</p>
                        <p class="party-name">{{ $partyName !== '' ? $partyName : '-' }}</p>
                        <p class="party-line">{{ $partyAddress !== '' ? $partyAddress : '-' }}</p>
                        @if($partyCity !== '' || $partyState !== '')
                            <p class="party-line">{{ collect([$partyCity, $partyState])->filter()->join(', ') }}</p>
                        @endif
                        @if($partyMobile !== '')
                            <p class="party-line">{{ $partyMobile }}</p>
                        @endif
                        @if($partyEmail !== '')
                            <p class="party-line">Email : {{ $partyEmail }}</p>
                        @endif
                        @if($partyGstin !== '')
                            <p class="party-line">GSTIN : {{ $partyGstin }}</p>
                        @endif
                        @if($partyPan !== '')
                            <p class="party-line">PAN : {{ $partyPan }}</p>
                        @endif
                    </div>
                    <div class="panel">
                        <p class="panel-title">BOQ Details :</p>
                        <div class="quote-meta">
                            <div class="quote-meta-row">
                                <strong>BOQ No</strong>
                                <span>:</span>
                                <span>{{ $quoteNo }}</span>
                            </div>
                            <div class="quote-meta-row">
                                <strong>Dated</strong>
                                <span>:</span>
                                <span>{{ $quoteDate }}</span>
                            </div>
                            @if($showMsmeNumber && $companyMsmeNumber !== '')
                                <div class="quote-meta-row">
                                    <strong>MSME No</strong>
                                    <span>:</span>
                                    <span>{{ $companyMsmeNumber }}</span>
                                </div>
                            @endif
                        </div>

                        @if($subject !== '')
                            <div style="margin-top:6px; font-size:10.5px; line-height:1.35;"><strong>Subject :</strong> {{ $subject }}</div>
                        @endif

                 @if(filled($termSource))
                            <div class="terms-list">{!! $termHtml !!}</div>
                        @endif
                    </div>
                </div>

                @if($customFieldEntries->isNotEmpty())
                    <div class="panel" style="margin-top: 10px;">
                        <p class="panel-title">Additional Details :</p>
                        <div class="quote-meta">
                            @foreach($customFieldEntries as $entry)
                                <div class="quote-meta-row">
                                    <strong>{{ $entry['label'] }}</strong>
                                    <span>:</span>
                                    <span>{{ $entry['value'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="table-wrap">
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th class="sn">S.N</th>
                                <th class="desc">Description of Goods</th>
                                <th class="qty">Qty.</th>
                                <th class="unit">Unit</th>
                                <th class="price">Price</th>
                                <th class="amount">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($normalizedItems as $item)
                                <tr>
                                    <td class="sn">{{ $item['sr_no'] }}</td>
                                    <td class="desc">
                                        <div class="item-main">{{ $item['product_name'] }}</div>
                                        @if($item['brand_model'] !== '')
                                            <div class="item-sub">{{ $item['brand_model'] }}</div>
                                        @endif
                                        @if($item['description'] !== '-' && $item['description'] !== $item['product_name'])
                                            <div class="item-sub">{{ $item['description'] }}</div>
                                        @endif
                                    </td>
                                    <td class="qty text-center qty-text">{{ number_format($item['qty'], 2) }}</td>
                                    <td class="unit text-center unit-text">{{ $item['unit'] }}</td>
                                    <td class="price text-right num-text">{{ number_format($item['price'], 2) }}</td>
                                    <td class="amount text-right num-text">{{ number_format($item['total'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="summary-block">
                    <div class="tax-box">
                        <table class="tax-table">
                            <tbody>
                                <tr class="head">
                                    <td>Tax Rate</td>
                                    <td>Taxable Amt.</td>
                                    @if($isIntraState)
                                        <td>CGST Amt.</td>
                                        <td>SGST Amt.</td>
                                    @else
                                        <td>IGST Amt.</td>
                                    @endif
                                    <td>Total Tax</td>
                                </tr>
                                <tr>
                                    <td class="center">{{ number_format($effectiveTaxRate, 2) }}%</td>
                                    <td class="right">&#8377;{{ number_format($taxableAmount, 2) }}</td>
                                    @if($isIntraState)
                                        <td class="right">&#8377;{{ number_format($taxSummary['cgst_amount'], 2) }}</td>
                                        <td class="right">&#8377;{{ number_format($taxSummary['sgst_amount'], 2) }}</td>
                                    @else
                                        <td class="right">&#8377;{{ number_format($taxSummary['igst_amount'], 2) }}</td>
                                    @endif
                                    <td class="right">&#8377;{{ number_format($gstAmount, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="total-box">
                        <table class="totals">
                            @if($isIntraState)
                                <tr>
                                    <td class="label">Add : SGST @ {{ number_format($splitTaxRate, 2) }}%</td>
                                    <td class="value">&#8377;{{ number_format($taxSummary['sgst_amount'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="label">Add : CGST @ {{ number_format($splitTaxRate, 2) }}%</td>
                                    <td class="value">&#8377;{{ number_format($taxSummary['cgst_amount'], 2) }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td class="label">Add : IGST @ {{ number_format($effectiveTaxRate, 2) }}%</td>
                                    <td class="value">&#8377;{{ number_format($taxSummary['igst_amount'], 2) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td class="label">Subtotal</td>
                                <td class="value">&#8377;{{ number_format($subtotal, 2) }}</td>
                            </tr>
                            @if($effectiveDiscount > 0)
                                <tr>
                                    <td class="label">Less : Discount</td>
                                    <td class="value">-&#8377;{{ number_format($effectiveDiscount, 2) }}</td>
                                </tr>
                            @endif
                            <tr class="grand">
                                <td class="label">Grand Total</td>
                                <td class="value">&#8377;{{ number_format($grandTotal, 2) }}</td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="amount-words">
                    {{ $numberToWords($grandTotal) }}
                </div>

                <div class="signature-row">
                    <div class="signature-note">
                        <div><strong>Tax Rate</strong> : {{ number_format($effectiveTaxRate, 2) }}%</div>
                        <div><strong>Taxable Amt.</strong> : &#8377;{{ number_format($taxableAmount, 2) }}</div>
                        @if($isIntraState)
                            <div><strong>CGST</strong> : &#8377;{{ number_format($taxSummary['cgst_amount'], 2) }}</div>
                            <div><strong>SGST</strong> : &#8377;{{ number_format($taxSummary['sgst_amount'], 2) }}</div>
                        @else
                            <div><strong>IGST</strong> : &#8377;{{ number_format($taxSummary['igst_amount'], 2) }}</div>
                        @endif
                        <div><strong>Total Tax</strong> : &#8377;{{ number_format($gstAmount, 2) }}</div>
                    </div>
                    <div class="signature-block">
                        <div class="muted">For {{ Str::upper($companyName) }}</div>
                        <div >
                            <strong>Authorised Signatory</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
