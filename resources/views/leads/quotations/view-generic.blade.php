<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $titleCompany = $lead->company ?? auth()->user()?->company;
        $titleDisplayVersion = $quotation->version ?? $titleCompany?->nextAvailableQuotationVersion();
        $quoteNo = $titleDisplayVersion;
    @endphp
    <title>Quotation {{ $quoteNo }}</title>
    @php
        use App\Support\QuotationTaxCalculator;
        use Illuminate\Support\Str;

        $company = $lead->company ?? auth()->user()?->company;
        $isPdf = (bool) ($isPdf ?? false);
        $useLetterhead = (bool) ($useLetterhead ?? false);
        $pdfPreviewUrl = $pdfPreviewUrl ?? route('leads.quotation.download-pdf', ['lead' => $lead, 'quotation' => $quotation, 'inline' => 1]);
        $pdfMargins = $pdfMargins ?? [
            'top_margin' => 15,
            'bottom_margin' => 15,
            'left_margin' => 15,
            'right_margin' => 15,
        ];
        $pdfPageMargin = $pdfPageMargin ?? sprintf(
            '%gmm %gmm %gmm %gmm',
            (float) ($pdfMargins['top_margin'] ?? 15),
            (float) ($pdfMargins['right_margin'] ?? 15),
            (float) ($pdfMargins['bottom_margin'] ?? 15),
            (float) ($pdfMargins['left_margin'] ?? 15),
        );
        $pdfPrintableWidthMm = $pdfPrintableWidthMm ?? max(
            0.0,
            210.0 - (float) ($pdfMargins['left_margin'] ?? 15) - (float) ($pdfMargins['right_margin'] ?? 15)
        );
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

            $name = trim((string) ($item['name'] ?? $item['title'] ?? $item['item_name'] ?? $product?->name ?? ''));
            $desc = trim((string) ($item['description'] ?? $product?->description ?? ''));
            $primaryName = $name !== '' ? $name : ($desc !== '' ? $desc : '-');
            $secondaryDescription = ($name !== '' && $desc !== '' && $desc !== $name) ? $desc : ($name === '' && $desc !== '' ? '' : $desc);

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
                'name' => $primaryName,
                'description' => $secondaryDescription,
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
                'name' => '-',
                'description' => '',
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

        $quoteNo = $displayVersion;
        $quoteDate = $get('date') ? \Illuminate\Support\Carbon::parse($get('date'))->format('d-m-Y') : optional($quotation->created_at ?? now())->format('d-m-Y');

        $partyName = trim((string) ($lead->client_name ?? ''));
        $partyAddress = trim((string) ($get('client.site_address', $lead->address) ?: $lead->address ?: ''));
        $partyCity = trim((string) ($client?->city?->name ?? $lead->city?->name ?? ''));
        $partyState = trim((string) ($client?->state?->name ?? $lead->state?->name ?? ''));
        $partyMobile = trim((string) ($lead->mobile ?? ''));
        $partyEmail = trim((string) ($lead->email ?? ''));
        $partyGstin = trim((string) ($lead->gst_number ?? ''));
        $partyPan = trim((string) ($lead->pan_number ?? ''));

        $subject = trim((string) ($get('project.subject') ?: $get('subject') ?: ''));

        $legacyTermsText = collect([
            ['label' => 'Warranty', 'value' => $get('terms.warranty')],
            ['label' => 'Payment Terms', 'value' => $get('terms.payment')],
            ['label' => 'Delivery Timeline', 'value' => $get('terms.delivery')],
        ])
            ->filter(fn ($row) => filled($row['value'] ?? null))
            ->map(fn ($row) => $row['label'] . ': ' . $row['value'])
            ->implode("\n");
        $termSource = trim((string) ($get('terms.conditions') ?: $legacyTermsText ?: ($company?->defaultTermsConditionsText() ?? '')));
        $termHtml = $termSource !== ''
            ? (\Illuminate\Support\Str::contains($termSource, '<') ? $termSource : nl2br(e($termSource)))
            : ($company?->defaultTermsConditionsHtml() ?? '');
        $defaultTermsSource = $termSource;
        $defaultTermsHtml = $termHtml;

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
            --pdf-printable-width: {{ number_format($pdfPrintableWidthMm, 2, '.', '') }}mm;
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
            max-width: 100%;
            margin: 12px auto 28px;
            padding: 0 12px;
        }
        .paper {
            width: var(--pdf-printable-width);
            max-width: var(--pdf-printable-width);
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
            line-height: 1.25;
        }
        .terms-list p,
        .terms-list div,
        .terms-list ul,
        .terms-list ol,
        .terms-list li {
            margin: 0 0 2px;
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
            padding: 3px 4px;
            font-size: 10px;
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
        .grand-box {
            border-top: 1px solid var(--line);
            margin-top: 6px;
            padding-top: 6px;
        }
        .grand-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 12px;
            font-weight: 700;
        }
        .grand-total {
            min-width: 92px;
            text-align: right;
            border: 1px solid var(--heavy);
            padding: 4px 8px;
            font-size: 13px;
            font-weight: 700;
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
            padding: 6px 10px 8px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            text-align: center;
        }
        .signature-line {
            width: 100%;
            border-top: 1px solid #444;
            margin-top: 16px;
            padding-top: 5px;
            font-size: 10px;
            line-height: 1.25;
        }
        .muted {
            color: var(--muted);
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        @if($isPdf)
        html, body {
            background: #fff;
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
        }
        .actions {
            display: none !important;
        }
        .sheet {
            margin: 0;
            padding: 0;
            max-width: none;
            width: 100%;
        }
        @page {
            @unless($useLetterhead)
            size: A4 portrait;
            @endunless
            margin: {{ $pdfPageMargin }};
        }
        .paper {
            width: var(--pdf-printable-width);
            max-width: var(--pdf-printable-width);
            margin: 0 auto;
            box-shadow: none;
            border: none;
            padding: 0;
        }
        .doc-shell {
            border: 1px solid var(--heavy);
            padding: 0;
            width: 100%;
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
        .pdf-top,
        .pdf-panels,
        .pdf-items,
        .pdf-summary,
        .amount-words,
        .pdf-footer {
            background: transparent;
        }
        .doc-shell {
            font-size: 11px;
            line-height: 1.35;
        }
        .doc-shell table,
        .doc-shell td,
        .doc-shell th,
        .doc-shell p,
        .doc-shell div,
        .doc-shell span {
            font-size: 15px !important;
            line-height: 1.35;
        }
        .doc-shell .company-name {
            font-size: 26px !important;
            line-height: 1.05;
        }
        .doc-shell .topline {
            font-size: 12px !important;
        }
        .doc-shell .panel-title {
            font-size: 13px !important;
        }
        .doc-shell .party-name {
            font-size: 14px !important;
        }
        .doc-shell .items-table thead th {
            font-size: 11px !important;
        }
        .doc-shell .items-table td {
            font-size: 11px !important;
        }
        .doc-shell .tax-table td {
            font-size: 11px !important;
        }
        .doc-shell .totals td {
            font-size: 12px !important;
        }
        .doc-shell .amount-words {
            font-size: 12px !important;
        }
        .doc-shell .signature-note {
            font-size: 11px !important;
        }
        .doc-shell .signature-line {
            font-size: 15px !important;
        }
        @endif
        .pdf-top {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .pdf-top td {
            padding: 3px 6px 8px 4px;
            font-size: 10.5px;
            line-height: 1.35;
            vertical-align: top;
        }
        .pdf-top .right {
            text-align: right;
        }
        .brand-block {
            padding: 7px 8px 8px;
        }
        .brand-logo {
            max-height: 34px;
            max-width: 150px;
            margin: 0 auto 3px;
        }
        .company-name {
            font-size: 25px;
            line-height: 1.05;
        }
        .company-meta {
            font-size: 9.5px;
            line-height: 1.3;
        }
        .pdf-panels {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-bottom: 1px solid var(--heavy);
        }
        .pdf-panels td {
            width: 50%;
            padding: 8px 10px 9px;
            vertical-align: top;
        }
        .pdf-panels td + td {
            border-left: 1px solid var(--heavy);
        }
        .pdf-meta {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .pdf-meta td {
            padding: 0 0 2px;
            font-size: 10.5px;
            line-height: 1.3;
            vertical-align: top;
        }
        .pdf-meta .label {
            width: 110px;
            white-space: nowrap;
        }
        .pdf-meta .value {
            padding-left: 4px;
        }
        .pdf-items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-bottom: 1px solid var(--heavy);
        }
        .pdf-items th,
        .pdf-items td {
            border: 1px solid var(--line);
            padding: 4px 4px;
            font-size: 10px;
            line-height: 1.25;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .pdf-items th {
            background: #f7f7f7;
            font-size: 9.5px;
            font-weight: 700;
            text-align: center;
        }
        .pdf-summary {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-bottom: 1px solid var(--heavy);
        }
        .pdf-summary td {
            vertical-align: top;
            padding: 7px 9px;
        }
        .pdf-summary td + td {
            border-left: 1px solid var(--heavy);
            width: 250px;
        }
        .pdf-summary-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .pdf-summary-table td {
            padding: 0 0 2px;
            font-size: 10.5px;
            line-height: 1.25;
        }
        .pdf-summary-table .right {
            text-align: right;
            white-space: nowrap;
        }
        .pdf-grand {
            font-size: 14px;
            font-weight: 700;
        }
        .bill-box {
            margin-top: 6px;
            padding: 6px 8px;
            border: 1px solid var(--soft-line);
            background: #f7f7f7;
            font-size: 10.5px;
            line-height: 1.3;
        }
        .amount-words {
            padding: 6px 9px;
            min-height: 28px;
            font-size: 10.5px;
            line-height: 1.3;
        }
        .pdf-footer {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .pdf-footer td {
            vertical-align: top;
            padding: 7px 9px;
        }
        .pdf-footer .note {
            border-right: 1px solid var(--heavy);
            font-size: 10px;
            line-height: 1.35;
        }
        .pdf-footer .sign {
            width: 210px;
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
        .terms-list {
            margin-top: 5px;
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
        .invoice-wrapper {
            page-break-inside: avoid;
        }
        .pdf-items tr {
            page-break-inside: avoid;
        }
        @endif
        @media print {
            @if($useLetterhead)
            body { background: transparent !important; }
            @else
            body { background: #fff; }
            @endif
            .actions { display: none; }
            .sheet { margin: 0; padding: 0; max-width: none; width: 100%; }
            .paper {
                background: transparent;
                box-shadow: none;
                border: 0;
                padding: 0;
                max-width: none;
                width: 100%;
                margin: 0;
            }
            .doc-shell {
                background: transparent;
                width: 100%;
                margin: 0;
                border: 0;
            }
        }
        @page {
            margin: {{ $pdfPageMargin }};
        }
    </style>
</head>
<body>
    @if($isPdf)
    <div class="sheet invoice-wrapper">
        <div class="paper" style="width:100%">
            <div class="doc-shell" style="width: 100% !important;">
                @unless($useLetterhead)
                    <table class="pdf-top print-brand-header">
                        <tr>
                            <td style="width: 50%;"><strong>GSTIN :</strong> {{ filled($company?->gst_number) ? $company->gst_number : '-' }}</td>
                            <td style="width: 50%; text-align: right;">
                                <div><strong>Quotation No</strong> : {{ $quoteNo }}</div>
                                <div><strong>Dated</strong> : {{ $quoteDate }}</div>
                            </td>
                        </tr>
                    </table>

                    <div class="brand-block print-brand-header">
                        @if($companyLogoUrl)
                            <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }} Logo" class="brand-logo">
                        @endif
                        <h1 class="company-name">{{ Str::upper($companyName) }}</h1>
                        <p class="company-meta">
                            {{ $companyAddress !== '' ? $companyAddress : ' ' }}<br>
                            @if($companyContact !== '')
                                Tel.: {{ $companyContact }}
                            @endif
                        </p>
                    </div>
                @endunless

                <table class="pdf-panels">
                    <tr>
                        <td>
                            <p class="panel-title">Party Details :</p>
                            @if($partyName !== '')
                                <p class="party-name">{{ $partyName }}</p>
                            @endif
                            @if($partyAddress !== '')
                                <p class="party-line">{{ $partyAddress }}</p>
                            @endif
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
                        </td>
                        <td>
                            <p class="panel-title">Quotation Details :</p>
                            <table class="pdf-meta">
                                <tr>
                                    <td class="label"><strong>Quotation No</strong></td>
                                    <td class="value">{{ $quoteNo }}</td>
                                </tr>
                                <tr>
                                    <td class="label"><strong>Date</strong></td>
                                    <td class="value">{{ $quoteDate }}</td>
                                </tr>
                                @if($showMsmeNumber && $companyMsmeNumber !== '')
                                    <tr>
                                        <td class="label"><strong>MSME No</strong></td>
                                        <td class="value">{{ $companyMsmeNumber }}</td>
                                    </tr>
                                @endif
                            </table>
                            @if($subject !== '')
                                <div style="margin-top:6px; font-size:10.5px; line-height:1.35;"><strong>Subject :</strong> {{ $subject }}</div>
                            @endif
                        </td>
                    </tr>
                </table>

                @if($customFieldEntries->isNotEmpty())
                    <table class="pdf-panels" style="margin-top: 2%;">
                        <tr>
                            <td colspan="2">
                                @foreach($customFieldEntries as $entry)
                                    <div style="font-size:10.5px; line-height:1.35;"><strong>{{ $entry['label'] }} :</strong> {{ $entry['value'] }}</div>
                                @endforeach
                            </td>
                        </tr>
                    </table>
                @endif

                <table class="pdf-items" style="margin-top: 3%;">
                    <thead>
                        <tr>
                            <th style="width: 6%;">S.N</th>
                            <th style="width: 42%;">Description of Goods</th>
                            <th style="width: 10%;">Qty.</th>
                            <th style="width: 10%;">Unit</th>
                            <th style="width: 15%;">Price</th>
                            <th style="width: 17%;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($normalizedItems as $item)
                            <tr>
                                <td style="text-align:center;">{{ $item['sr_no'] }}</td>
                                <td>
                                    <div class="item-main">{{ $item['name'] }}</div>
                                    @if($item['description'] !== '')
                                        <div class="item-sub">{{ $item['description'] }}</div>
                                    @endif
                                    @if($item['brand_model'] !== '')
                                        <div class="item-sub">{{ $item['brand_model'] }}</div>
                                    @endif
                                </td>
                                <td style="text-align:center;">{{ number_format($item['qty'], 2) }}</td>
                                <td style="text-align:center;">{{ $item['unit'] }}</td>
                                <td style="text-align:right;">&#8377;{{ number_format($item['price'], 2) }}</td>
                                <td style="text-align:right;">&#8377;{{ number_format($item['total'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

                <table class="pdf-summary" style="margin-top: 10px;">
                    <tr>
                        <td>
                            <p class="panel-title">Tax Summary</p>
                            <table class="pdf-summary-table">
                                <tr>
                                    <td>Items Subtotal</td>
                                    <td class="right">&#8377;{{ number_format($taxSummary['subtotal'], 2) }}</td>
                                </tr>
                                @if($taxSummary['discount'] > 0)
                                    <tr>
                                        <td>Discount</td>
                                        <td class="right">-&#8377;{{ number_format($taxSummary['discount'], 2) }}</td>
                                    </tr>
                                @endif
                                @if($isIntraState)
                                    <tr>
                                        <td>SGST</td>
                                        <td class="right">&#8377;{{ number_format($taxSummary['sgst_amount'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>CGST</td>
                                        <td class="right">&#8377;{{ number_format($taxSummary['cgst_amount'], 2) }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>IGST</td>
                                        <td class="right">&#8377;{{ number_format($taxSummary['igst_amount'], 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>Total Tax</td>
                                    <td class="right">&#8377;{{ number_format($gstAmount, 2) }}</td>
                                </tr>
                            </table>
                        </td>
                        <td>
                            <p class="panel-title">Billing Total</p>
                            <table class="pdf-summary-table">
                                @if($isIntraState)
                                    <tr>
                                        <td>Add : SGST @ {{ number_format($splitTaxRate, 2) }}%</td>
                                        <td class="right">&#8377;{{ number_format($taxSummary['sgst_amount'], 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Add : CGST @ {{ number_format($splitTaxRate, 2) }}%</td>
                                        <td class="right">&#8377;{{ number_format($taxSummary['cgst_amount'], 2) }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td>Add : IGST @ {{ number_format($effectiveTaxRate, 2) }}%</td>
                                        <td class="right">&#8377;{{ number_format($taxSummary['igst_amount'], 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td>Subtotal</td>
                                    <td class="right">&#8377;{{ number_format($subtotal, 2) }}</td>
                                </tr>
                                @if($effectiveDiscount > 0)
                                    <tr>
                                        <td>Less : Discount</td>
                                        <td class="right">-&#8377;{{ number_format($effectiveDiscount, 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="pdf-grand">Grand Total</td>
                                    <td class="right pdf-grand">&#8377;{{ number_format($grandTotal, 2) }}</td>
                                </tr>
                            </table>
                            
                        </td>
                    </tr>
                </table>
              
                <div class="amount-words">
                    {{ $numberToWords($grandTotal) }}
                </div>
                @if(filled($defaultTermsSource))
                    <div style="margin-top: 6px;"><strong>Terms & Conditions :</strong>
                        <div class="terms-list">{!! $defaultTermsHtml !!}</div>
                    </div>
                @endif

                <table class="pdf-footer" style="margin-top: 90px; margin-left: 60%;">
                    <tr>

                       <td >
                            <div class="muted">For {{ Str::upper($companyName) }}</div>
                            <div ><strong>Authorised Signatory</strong></div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    @else
    <div class="actions no-print">
        @if(filled($pdfPreviewUrl))
            <a class="btn btn-print" href="{{ $pdfPreviewUrl }}" target="_blank" rel="noopener">Print Quotation</a>
        @else
            <button class="btn btn-print" type="button" onclick="window.print()">Print Quotation</button>
        @endif
        <a class="btn btn-download" href="{{ route('leads.quotation.download-pdf', [$lead, $quotation]) }}">PDF Download</a>
        @if($lead && filled($lead->getRouteKey()))
            <button type="button" class="btn" style="background:#4f46e5;color:#fff;border-color:#4f46e5;" onclick="openSaveTemplateModal()">Save as Template</button>
            <a class="btn" href="{{ route('leads.show', $lead) }}">Back to Lead</a>
        @else
            <a class="btn" href="{{ url()->previous() }}">Back</a>
        @endif
    </div>

    <!-- Save Template Modal for View Generic -->
    <div id="saveTemplateModal" class="no-print" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:16px;max-width:480px;width:90%;padding:24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);text-align:left;font-family:inherit;">
            <h3 style="margin-top:0;margin-bottom:8px;font-size:18px;font-weight:bold;color:#111827;">Save Quotation as Template</h3>
            <p style="margin-top:0;margin-bottom:16px;font-size:13px;color:#6b7280;line-height:1.4;">Only materials, quantities, rates, and GST details will be saved into the template. Client details are excluded.</p>
            <form id="saveTemplateForm" method="POST" action="{{ route('leads.quotation.save-template', [$lead, $quotation]) }}">
                @csrf
                <div style="margin-bottom:14px;">
                    <label for="template_name" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">Template Name *</label>
                    <input type="text" id="template_name" name="name" required value="Quotation Template {{ $quotation->version }}" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;">
                </div>
                <div style="margin-bottom:18px;">
                    <label for="template_desc" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">Description (Optional)</label>
                    <textarea id="template_desc" name="description" rows="3" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;resize:vertical;" placeholder="Brief note about this materials package..."></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;gap:10px;">
                    <button type="button" onclick="closeSaveTemplateModal()" style="background:#fff;border:1px solid #d1d5db;padding:8px 16px;border-radius:8px;font-size:13px;font-weight:600;color:#374151;cursor:pointer;">Cancel</button>
                    <button type="submit" style="background:#4f46e5;border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;color:#fff;cursor:pointer;">Save Template</button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function openSaveTemplateModal() {
            var m = document.getElementById('saveTemplateModal');
            if (m) m.style.display = 'flex';
        }
        function closeSaveTemplateModal() {
            var m = document.getElementById('saveTemplateModal');
            if (m) m.style.display = 'none';
        }
    </script>

    @if(filled($pdfPreviewUrl))
    <div class="sheet">
        <div class="paper" style="max-width: 100%; width:100%; padding: 0; border: 0; box-shadow: none;">
            <div class="doc-shell" style="padding: 0; overflow: hidden;">
                <iframe
                    src="{{ $pdfPreviewUrl }}#zoom=100"
                    title="Quotation Preview"
                    class="h-[1200px] w-full border-0 bg-white"
                    style="width: 100%; min-height: 1200px; border: 0;"
                ></iframe>
            </div>
        </div>
    </div>
    @else
    <div class="sheet">
        <div class="paper" style="width:100%">
            <div class="doc-shell">
                <div class="topline print-brand-header">
                    <div class="gstin">
                        <strong>GSTIN :</strong> {{ filled($company?->gst_number) ? $company->gst_number : '-' }}
                    </div>
                    <div class="empty"></div>
                    <div class="quote-mini">
                        <div><strong>Quotation No</strong> : {{ $quoteNo }}</div>
                        <div><strong>Dated</strong> : {{ $quoteDate }}</div>
                    </div>
                </div>

                <div class="brand-block print-brand-header">
                    @if($companyLogoUrl)
                        <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }} Logo" class="brand-logo">
                    @endif
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
                        @if($partyName !== '')
                            <p class="party-name">{{ $partyName }}</p>
                        @endif
                        @if($partyAddress !== '')
                            <p class="party-line">{{ $partyAddress }}</p>
                        @endif
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
                        <p class="panel-title">Quotation Details :</p>
                        <div class="quote-meta">
                            <div class="quote-meta-row">
                                <strong>Quotation No</strong>
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
                    <table class="items-table" >
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
                                        <div class="item-main">{{ $item['name'] }}</div>
                                        @if($item['description'] !== '')
                                            <div class="item-sub">{{ $item['description'] }}</div>
                                        @endif
                                        @if($item['brand_model'] !== '')
                                            <div class="item-sub">{{ $item['brand_model'] }}</div>
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
                @if(filled($termSource))
                    <div class="terms-list">{!! $termHtml !!}</div>
                @endif
            </div>
            
        </div>
        
    </div>
    @endif
    
    @endif
</body>
</html>
