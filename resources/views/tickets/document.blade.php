<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ ucfirst($type) }} - {{ $document->document_number }}</title>
    @php
        $ticket = $ticket ?? null;
        $client = $client ?? null;
        $isPdf = (bool) ($isPdf ?? false);
        $useLetterhead = (bool) ($useLetterhead ?? false);
        $hideDefaultHeader = (bool) ($hideDefaultHeader ?? false);
        $pdfPreviewUrl = $pdfPreviewUrl ?? null;
        $pdfMargins = $pdfMargins ?? [
            'top_margin' => 15,
            'bottom_margin' => 15,
            'left_margin' => 15,
            'right_margin' => 15,
        ];
        $pdfLayoutMargins = $pdfMargins;
        $pdfPageMargin = $pdfPageMargin ?? sprintf(
            '%gmm %gmm %gmm %gmm',
            (float) ($pdfLayoutMargins['top_margin'] ?? 15),
            (float) ($pdfLayoutMargins['right_margin'] ?? 15),
            (float) ($pdfLayoutMargins['bottom_margin'] ?? 15),
            (float) ($pdfLayoutMargins['left_margin'] ?? 15),
        );
        $pdfPrintableWidthMm = $pdfPrintableWidthMm ?? max(
            0.0,
            210.0 - (float) ($pdfLayoutMargins['left_margin'] ?? 15) - (float) ($pdfLayoutMargins['right_margin'] ?? 15)
        );
        $company = $ticket?->company ?? $document?->company ?? $client?->company ?? auth()->user()?->company;
        $showBrandHeader = $company?->shouldShowPrintLogo() ?? true;
        $companyLogoUrl = $showBrandHeader ? ($company?->print_logo_url ?? $company?->logo_url) : null;
        // Seller and party details frozen when the document was issued; older documents fall back to live data.
        $billingSnapshot = is_array($document?->billing_snapshot ?? null) ? $document->billing_snapshot : null;
        $sellerSnapshot = $billingSnapshot['seller'] ?? null;
        $partySnapshot = $billingSnapshot['party'] ?? null;
        $sellerGstNumber = $sellerSnapshot ? ($sellerSnapshot['gst_number'] ?? '') : ($company?->gst_number ?? '');
        $companyName = ($sellerSnapshot ? ($sellerSnapshot['name'] ?? null) : $company?->name) ?? 'WE4WORK';
        $companyAddress = trim((string) ($sellerSnapshot ? ($sellerSnapshot['address'] ?? '') : ($company?->address ?? '')));
        $companyContact = trim(implode(', ', array_filter([
            trim((string) ($sellerSnapshot ? ($sellerSnapshot['phone_number'] ?? '') : ($company?->phone_number ?? ''))),
            trim((string) ($sellerSnapshot ? ($sellerSnapshot['email'] ?? '') : ($company?->email ?? ''))),
        ])));
        $companyGstin = filled($sellerGstNumber) ? (string) $sellerGstNumber : '-';
        $companyMsmeNumber = trim((string) ($sellerSnapshot ? ($sellerSnapshot['msme_number'] ?? '') : ($company?->msme_number ?? '')));

        $isInvoice = $type === 'invoice';
        $showMsmeNumber = $isInvoice
            ? (bool) ($company?->show_msme_number_in_invoice ?? false)
            : (bool) ($company?->show_msme_number_in_quotation ?? false);
        $documentLabel = $isInvoice ? 'Invoice' : 'Quotation';
        $documentNumber = (string) ($document->document_number ?? '-');
        $documentDate = optional($document->created_at)->format('d-m-Y') ?? now()->format('d-m-Y');
        $statusLabel = $isInvoice ? (string) ($paymentStatus ?? 'Unpaid') : 'Quotation';
        $isPaid = $isInvoice && strcasecmp($statusLabel, 'Paid') === 0;

        if ($partySnapshot) {
            $partyName = trim((string) ($partySnapshot['name'] ?? ''));
            $partyAddress = trim((string) ($partySnapshot['address'] ?? ''));
            $partyContact = trim((string) ($partySnapshot['contact'] ?? ''));
            $partyEmail = trim((string) ($partySnapshot['email'] ?? ''));
            $partyGstin = trim((string) ($partySnapshot['gst_number'] ?? ''));
        } else {
            $partyName = trim((string) ($ticket?->customer_name ?? $ticket?->user?->name ?? $client?->name ?? ''));
            $partyAddress = trim((string) ($ticket?->customer_address ?? $client?->address ?? ''));
            $partyContact = trim((string) ($ticket?->customer_contact_number ?? $client?->mobile ?? $client?->phone ?? ''));
            $partyEmail = trim((string) ($ticket?->customer_email ?? $client?->email ?? ''));
            $partyGstin = trim((string) ($client?->gst_number ?? ''));
        }
        $ticketSubject = trim((string) ($ticket?->subject ?? ''));
        $documentSubject = trim((string) ($document?->subject ?? ''));
        $displaySubject = $documentSubject !== '' ? $documentSubject : $ticketSubject;
        $ticketRef = $ticket ? trim((string) ($ticket->ticket_number ?? $ticket->ticket_code ?? $ticket->id)) : '';
        $invoiceHasAdditionalNotes = $isInvoice && (bool) ($document?->has_additional_notes ?? false);
        $invoiceAdditionalNotes = $isInvoice ? trim((string) ($document?->additional_notes ?? '')) : '';

        $items = collect($items ?? [])->filter(fn ($item) => is_array($item))->values();

        $lineItems = $items->map(function (array $item, int $index) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? 0);
            $amount = (float) ($item['total'] ?? $item['amount'] ?? ($qty * $price));

            $name = trim((string) ($item['name'] ?? ''));
            $desc = trim((string) ($item['description'] ?? ''));
            $primaryTitle = $name !== '' ? $name : ($desc !== '' ? $desc : '-');
            $secondaryDesc = ($name !== '' && $desc !== '' && $desc !== $name) ? $desc : '';

            return [
                'sr_no' => $index + 1,
                'name' => $primaryTitle,
                'description' => $secondaryDesc,
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
                'name' => '-',
                'description' => '',
                'note' => '',
                'qty' => 0,
                'unit' => '-',
                'price' => 0,
                'amount' => 0,
            ]]);
        }

        $subtotal = (float) ($subtotal ?? 0);
        $discount = (float) ($discount ?? 0);
        $gstAmount = (float) ($gstAmount ?? 0);
        $grandTotal = (float) ($total ?? max(0, $subtotal - $discount + $gstAmount));
        $paidAmount = $isPaid ? $grandTotal : 0.0;
        $pendingAmount = max(0, $grandTotal - $paidAmount);
        $paymentMethodLabel = $isInvoice ? (string) ($paymentMethod ?? '-') : null;
        $documentTerms = trim((string) ($document?->terms_conditions ?? ''));
        $termsPrefix = $isInvoice ? 'invoice' : 'quotation';
        $companyTermsText = $sellerSnapshot
            ? (string) ($sellerSnapshot[$termsPrefix . '_terms_text'] ?? '')
            : ($isInvoice ? ($company?->invoiceTermsConditionsText() ?? '') : ($company?->quotationTermsConditionsText() ?? ''));
        $companyTermsHtml = $sellerSnapshot
            ? (string) ($sellerSnapshot[$termsPrefix . '_terms_html'] ?? '')
            : ($isInvoice ? ($company?->invoiceTermsConditionsHtml() ?? '') : ($company?->quotationTermsConditionsHtml() ?? ''));
        $defaultTermsSource = trim((string) ($documentTerms !== '' ? $documentTerms : $companyTermsText));
        $defaultTermsHtml = $documentTerms !== '' ? $documentTerms : $companyTermsHtml;

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
            --pdf-printable-width: {{ number_format($pdfPrintableWidthMm, 2, '.', '') }}mm;
            --border: #4a4a4a;
            --light-border: #cbd5e1;
            --heavy: #4a4a4a;
            --line: #b9b9b9;
            --soft-line: #d7d7d7;
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
            background: var(--page-bg);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
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
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background: #fff;
            color: #111827;
            text-decoration: none;
            font-size: 15px;
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
            font-size: 15px;
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
            font-size: 28px;
            line-height: 1.1;
            letter-spacing: 0.02em;
            font-weight: 800;
            text-transform: uppercase;
        }
        .company-meta {
            margin: 2px 0 0;
            font-size: 12px;
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
            font-size: 15px;
            line-height: 1;
            font-weight: 700;
        }
        .party-name {
            margin: 0 0 4px;
            font-size: 15px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .party-line, .quote-line {
            margin: 0;
            font-size: 12px;
            line-height: 1.45;
            white-space: pre-wrap;
        }
        .quote-meta {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2px;
            font-size: 12px;
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
            font-size: 15px;
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
            padding: 5px 6px;
            font-size: 11px;
            vertical-align: top;
        }
        @if($isPdf && $useLetterhead)
        .items-table th,
        .items-table td { font-size: 12px; }
        .item-main { font-size: 12px; }
        .party-line, .quote-line, .quote-meta,
        .summary-line, .subject-value, .words { font-size: 13px; }
        @endif
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
            font-size: 15px;
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
            font-size: 15px;
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
            font-size: 15px;
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
            font-size: 15px;
            font-weight: 700;
        }
        .amount-words {
            padding: 7px 10px 0;
            font-size: 15px;
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
            width: 100%;
            border-top: 1px solid #444;
            margin-top: 10px;
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
        .summary-title {
            margin: 0 0 5px;
            font-size: 15px;
            line-height: 1;
            font-weight: 700;
        }
        .words {
            padding: 7px 10px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid var(--heavy);
            border-top: 0;
            min-height: 30px;
        }
        .document-section {
            padding: 7px 10px 8px;
            border: 1px solid var(--heavy);
            border-top: 0;
        }
        .document-section-title {
            margin: 0;
            font-size: 13px;
            line-height: 1.2;
            font-weight: 700;
        }
        .document-section-line {
            margin: 4px 0 6px;
            border-top: 1px solid var(--line);
        }
        .document-section-body {
            white-space: pre-line;
            font-size: 12px;
            line-height: 1.35;
            color: var(--ink);
        }
        .panels {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border-bottom: 1px solid var(--heavy);
        }
        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border-bottom: 1px solid var(--heavy);
        }
        .tax-box,
        .summary-grid .total-box {
            padding: 8px 10px;
        }
        .summary-grid .total-box {
            border-left: 1px solid var(--heavy);
        }
        .summary-line {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            line-height: 1.4;
        }
        .summary-line .grand {
            font-size: 16px;
            font-weight: 800;
        }
        .subject-block {
            padding: 6px 10px;
            border-bottom: 1px solid var(--soft-line);
        }
        .subject-label {
            margin: 0;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
        }
        .subject-value {
            margin: 2px 0 0;
            font-size: 12px;
        }
        .badge {
            display: inline-block;
            padding: 1px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 700;
        }
        .badge-paid {
            background: var(--success);
            color: var(--success-text);
        }
        .badge-unpaid {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-neutral {
            background: #e2e8f0;
            color: #334155;
        }
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
            size: A4 portrait;
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
            font-size: 15px !important;
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
            font-size: 15px;
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
            font-size: 15px;
            line-height: 1.25;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: break-word;
        }
        .pdf-items th {
            background: #f7f7f7;
            font-size: 15px;
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
            font-size: 15px;
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
            font-size: 15px;
            line-height: 1.3;
        }
        .pdf-footer {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border-top: 1px solid var(--heavy);
        }
        .pdf-footer td {
            vertical-align: top;
            padding: 8px 10px;
        }
        .pdf-footer .note {
            border-right: 1px solid var(--heavy);
            font-size: 11px;
            line-height: 1.35;
        }
        .pdf-footer .sign {
            width: 210px;
            text-align: center;
        }
        .signature-line {
            /* width: 100%;
            border-top: 1px solid #444;
            margin-top: 16px;
            padding-top: 5px;
            font-size: 10px;
            line-height: 1.25; */
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1fr 210px;
            gap: 0;
            align-items: start;
            margin-top: 0;
            border-top: 1px solid var(--heavy);
        }
        .footer-note {
            padding: 8px 10px;
            font-size: 11px;
            line-height: 1.35;
            border-right: 1px solid var(--heavy);
        }
        .footer-right {
            padding: 6px 10px 8px;
            display: flex;
            flex-direction: column;
            justify-content: flex-start;
            align-items: center;
            text-align: center;
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
    @unless($isPdf)
        <div class="actions">
        @if(filled($pdfPreviewUrl))
            <a href="{{ $pdfPreviewUrl }}" class="btn btn-print" target="_blank" rel="noopener">
                Print {{ $documentLabel }}
            </a>
            @else
                <button type="button" class="btn btn-print" onclick="window.print()">
                    Print {{ $documentLabel }}
                </button>
            @endif
            <a href="{{ $backUrl ?? ($ticket ? route('tickets.show', $ticket) : url('/')) }}" class="btn">{{ $backLabel ?? 'Back to Ticket' }}</a>
            @php
                $downloadUrl = null;
                if ($ticket && $link) {
                    $downloadUrl = $type === 'invoice'
                        ? route('tickets.invoices.download', ['ticket' => $ticket, 'ticketInvoice' => $link])
                        : route('tickets.quotations.download', ['ticket' => $ticket, 'ticketQuote' => $link]);
                } elseif (! $ticket && $document) {
                    $downloadUrl = route('service-call.billing-document.download', $document);
                }
            @endphp
            @if($downloadUrl)
                <a href="{{ $downloadUrl }}" class="btn btn-download">
                    PDF Download
                </a>
            @else
                <span class="btn btn-download" aria-disabled="true" style="opacity:.6;cursor:not-allowed;">
                    PDF Download
                </span>
            @endif
            @if($type === 'quotation' && $document)
                <button type="button" class="btn" style="background:#4f46e5;color:#fff;border-color:#4f46e5;" onclick="openSaveDocTemplateModal()">
                    Save as Template
                </button>
            @endif
        </div>

        @if($type === 'quotation' && $document)
            <div id="saveDocTemplateModal" class="no-print" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99999;align-items:center;justify-content:center;">
                <div style="background:#fff;border-radius:16px;max-width:480px;width:90%;padding:24px;box-shadow:0 20px 25px -5px rgba(0,0,0,0.1),0 10px 10px -5px rgba(0,0,0,0.04);text-align:left;font-family:inherit;">
                    <h3 style="margin-top:0;margin-bottom:8px;font-size:18px;font-weight:bold;color:#111827;">Save Quotation as Template</h3>
                    <p style="margin-top:0;margin-bottom:16px;font-size:13px;color:#6b7280;line-height:1.4;">Only materials, quantities, rates, and GST details will be saved into the template. Client details are excluded.</p>
                    <form id="saveDocTemplateForm" method="POST" action="{{ route('service-call.billing-document.save-template', $document) }}">
                        @csrf
                        <div style="margin-bottom:14px;">
                            <label for="doc_template_name" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">Template Name *</label>
                            <input type="text" id="doc_template_name" name="name" required value="Quotation Template {{ $document->document_number }}" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;">
                        </div>
                        <div style="margin-bottom:18px;">
                            <label for="doc_template_desc" style="display:block;font-size:13px;font-weight:600;color:#374151;margin-bottom:4px;">Description (Optional)</label>
                            <textarea id="doc_template_desc" name="description" rows="3" style="width:100%;box-sizing:border-box;border:1px solid #d1d5db;border-radius:8px;padding:8px 12px;font-size:14px;resize:vertical;" placeholder="Brief note about this materials package..."></textarea>
                        </div>
                        <div style="display:flex;justify-content:flex-end;gap:10px;">
                            <button type="button" onclick="closeSaveDocTemplateModal()" style="border:1px solid #d1d5db;background:#fff;padding:8px 16px;border-radius:8px;font-size:14px;font-weight:600;color:#374151;cursor:pointer;">Cancel</button>
                            <button type="submit" style="border:none;background:#4f46e5;color:#fff;padding:8px 18px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">Save Template</button>
                        </div>
                    </form>
                </div>
            </div>
            <script>
                function openSaveDocTemplateModal() {
                    var m = document.getElementById('saveDocTemplateModal');
                    if (m) m.style.display = 'flex';
                }
                function closeSaveDocTemplateModal() {
                    var m = document.getElementById('saveDocTemplateModal');
                    if (m) m.style.display = 'none';
                }
            </script>
        @endif
    @endunless

    @if(! $isPdf && filled($pdfPreviewUrl))
        <div class="sheet">
            <div class="paper" style="max-width: 100%; width:100%; padding: 0; border: 0; box-shadow: none;">
                <div class="doc-shell" style="padding: 0; overflow: hidden;">
                    <iframe
                        id="billing-document-preview-frame"
                        src="about:blank"
                        data-pdf-source="{{ $pdfPreviewUrl }}"
                        title="{{ $documentLabel }} Preview"
                        class="h-[1200px] w-full border-0 bg-white"
                        style="width: 100%; min-height: 1200px; border: 0;"
                    ></iframe>
                </div>
            </div>
        </div>
        <script>
            (function () {
                const frame = document.getElementById('billing-document-preview-frame');
                if (!frame) return;

                const sourceUrl = frame.dataset.pdfSource;
                if (!sourceUrl) return;

                fetch(sourceUrl, {
                    credentials: 'same-origin',
                    cache: 'no-store',
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Failed to load preview PDF: ' + response.status);
                        }

                        const contentType = response.headers.get('content-type') || '';
                        if (!contentType.includes('application/pdf')) {
                            throw new Error('Preview response is not a PDF.');
                        }

                        return response.blob();
                    })
                    .then((blob) => {
                        const blobUrl = URL.createObjectURL(blob);
                        frame.src = blobUrl + '#zoom=100';
                        frame.addEventListener('load', () => {
                            setTimeout(() => URL.revokeObjectURL(blobUrl), 30000);
                        }, { once: true });
                    })
                    .catch(() => {
                        frame.src = sourceUrl + '#zoom=100';
                    });
            })();
        </script>
    @else
    <div class="sheet invoice-wrapper">
        <div class="paper" style="width:100%">
            <div class="doc-shell">
                @if($isPdf)
                    @unless($hideDefaultHeader)
                        <table class="print-brand-header" style="width:100%; table-layout:fixed;">
                            <tr>
                                <td style="width:50%; font-size:15px; line-height:1.4; padding:6px 8px 4px 8px; vertical-align:top;">
                                    <strong>GSTIN :</strong> {{ $companyGstin }}
                                </td>
                                <td style="width:50%; line-height:1.4; padding:6px 8px 4px 8px; vertical-align:top; text-align:right;">
                                    <div><strong>{{ $documentLabel }} No :</strong> {{ $documentNumber }}</div>
                                    <div><strong>Dated :</strong> {{ $documentDate }}</div>
                                </td>
                            </tr>
                        </table>

                        <div class="brand-block print-brand-header">
                            @if($companyLogoUrl)
                                <img src="{{ $companyLogoUrl }}" alt="{{ $companyName }} Logo" class="brand-logo">
                            @endif
                            <h1 class="company-name">{{ strtoupper($companyName) }}</h1>
                            <p class="company-meta">
                                {{ $companyAddress !== '' ? $companyAddress : ' ' }}
                                @if($companyContact !== '')
                                    <br>{{ $companyContact }}
                                @endif
                            </p>
                        </div>
                    @endunless

                    <table style="width:100%; border-collapse:collapse; table-layout:fixed; border-bottom:1px solid var(--border);">
                        <tr>
                            <td data-lh="client_name" style="width:50%; border-right:1px solid var(--border); padding:9px 11px 10px; vertical-align:top;">
                                <p class="panel-title">Party Details :</p>
                                <p class="party-name">{{ $partyName !== '' ? $partyName : '-' }}</p>
                                <p class="party-line">{{ $partyAddress !== '' ? $partyAddress : '-' }}</p>
                                <p class="party-line">{{ $partyContact !== '' ? $partyContact : '-' }}</p>
                                @if($partyEmail !== '')
                                    <p class="party-line">{{ $partyEmail }}</p>
                                @endif
                                @if($partyGstin !== '')
                                    <p class="party-line">GSTIN : {{ $partyGstin }}</p>
                                @endif
                                @if($ticket)
                                    <p class="party-line">Ticket # : {{ $ticketRef !== '' ? $ticketRef : '-' }}</p>
                                @endif
                            </td>
                            <td style="width:50%; padding:9px 11px 10px; vertical-align:top;">
                                <p class="panel-title">{{ $documentLabel }} Details :</p>
                                <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
                                    <tr>
                                        <td style="width:108px; line-height:1.32; padding:0 4px 2px 0; vertical-align:top;">{{ $documentLabel }} No</td>
                                        <td style="width:10px;  line-height:1.32; padding:0 2px 2px 0; vertical-align:top;">:</td>
                                        <td style=" line-height:1.32; padding:0 0 2px 0; vertical-align:top; word-break:break-word;">{{ $documentNumber }}</td>
                                    </tr>
                                    <tr>
                                        <td style="width:108px; line-height:1.32; padding:0 4px 2px 0; vertical-align:top;">Date</td>
                                        <td style="width:10px;  line-height:1.32; padding:0 2px 2px 0; vertical-align:top;">:</td>
                                        <td style=" line-height:1.32; padding:0 0 2px 0; vertical-align:top; word-break:break-word;">{{ $documentDate }}</td>
                                    </tr>
                                   
                                    @if($displaySubject !== '')
                                        <tr>
                                            <td style="width:108px; line-height:1.32; padding:0 4px 0 0; vertical-align:top;">Subject</td>
                                            <td style="width:10px;  line-height:1.32; padding:0 2px 0 0; vertical-align:top;">:</td>
                                            <td style=" line-height:1.32; padding:0; vertical-align:top; word-break:break-word;">{{ $displaySubject }}</td>
                                        </tr>
                                    @endif
                                    @if($showMsmeNumber && $companyMsmeNumber !== '')
                                        <tr>
                                            <td style="width:108px; line-height:1.32; padding:0 4px 0 0; vertical-align:top;">MSME No</td>
                                            <td style="width:10px;  line-height:1.32; padding:0 2px 0 0; vertical-align:top;">:</td>
                                            <td style=" line-height:1.32; padding:0; vertical-align:top; word-break:break-word;">{{ $companyMsmeNumber }}</td>
                                        </tr>
                                    @endif
                                </table>
                            </td>
                        </tr>
                    </table>

                    @if($ticket && ! $isInvoice)
                        <div class="subject-block">
                            <p class="subject-label">{{ $documentLabel }} Subject</p>
                            <p class="subject-value">{{ $displaySubject !== '' ? $displaySubject : 'Service billing document' }}</p>
                        </div>
                    @endif

                    <table class="items-table" style="margin-top: 8px;">
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
                            @foreach($lineItems as $row)
                                <tr>
                                    <td class="sn">{{ $row['sr_no'] }}</td>
                                    <td class="desc">
                                        <div class="item-main">{{ $row['name'] }}</div>
                                        @if($row['description'] !== '')
                                            <div class="item-sub">{{ $row['description'] }}</div>
                                        @endif
                                        @if($row['note'] !== '')
                                            <div class="item-sub">{{ $row['note'] }}</div>
                                        @endif
                                    </td>
                                    <td class="qty">{{ number_format((float) $row['qty'], 2) }}</td>
                                    <td class="unit">{{ $row['unit'] }}</td>
                                    <td class="price">{{ number_format((float) $row['price'], 2) }}</td>
                                    <td class="amount">{{ number_format((float) $row['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <table style="width:100%; border-collapse:collapse; table-layout:fixed; border-bottom:1px solid var(--border);">
                        <tr>
                            <td style="width:50%; padding:6px 9px 7px; vertical-align:top; border-right:1px solid var(--border);">
                                <p class="summary-title">Tax Summary</p>
                                <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
                                    <tr>
                                        <td style="padding:0 0 2px 0;  line-height:1.25;">Subtotal</td>
                                        <td style="padding:0 0 2px 0;  line-height:1.25; text-align:right;">{{ number_format($subtotal, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 2px 0;  line-height:1.25;">Discount</td>
                                        <td style="padding:0 0 2px 0;  line-height:1.25; text-align:right;">{{ number_format($discount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 2px 0;  line-height:1.25;">GST Amount</td>
                                        <td style="padding:0 0 2px 0;  line-height:1.25; text-align:right;">{{ number_format($gstAmount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0 0 2px 0;  line-height:1.25;">Paid Amount</td>
                                        <td style="padding:0 0 2px 0;  line-height:1.25; text-align:right;">{{ number_format($paidAmount, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:0;  line-height:1.25;">Pending Amount</td>
                                        <td style="padding:0;  line-height:1.25; text-align:right;">{{ number_format($pendingAmount, 2) }}</td>
                                    </tr>
                                </table>
                            </td>
                            <td style="width:50%; padding:6px 9px 7px; vertical-align:top;">
                                <p class="summary-title">Billing Total</p>
                                <table style="width:100%; border-collapse:collapse; table-layout:fixed;">
                                    <tr>
                                        <td style="padding:0;  line-height:1.25;">Grand Total</td>
                                        <td style="padding:0; font-size:16px; line-height:1.25; text-align:right; font-weight:800;">&#8377;{{ number_format($grandTotal, 2) }}</td>
                                    </tr>
                                </table>
                                <!-- @if($isInvoice)
                                    <div class="bill-box">
                                        <div><strong>Payment Status</strong> : {{ $statusLabel }}</div>
                                        <div><strong>Payment Method</strong> : {{ $paymentMethodLabel ?? '-' }}</div>
                                    </div>
                                @endif -->
                            </td>
                        </tr>
                    </table>

                    <div class="words">
                        {{ $amountInWords($grandTotal) }}
                    </div>

                    @if(! $isInvoice && filled($defaultTermsSource))
                        <div style="margin-top: 6px;"><strong>Terms & Conditions :</strong>
                            <div class="terms-list">{!! $defaultTermsHtml !!}</div>
                        </div>
                    @endif
                    @if($invoiceHasAdditionalNotes && filled($invoiceAdditionalNotes))
                        <br>
                            <div class="document-section-title">Additional Notes</div>
                            <div class="document-section-line"></div>
                            <div class="document-section-body">{{ $invoiceAdditionalNotes }}</div>
                        
                    @endif
                    <table class="pdf-footer" style="margin-top: 90px;">
                        <tr>
                            <td class="sign" style="width:100%; text-align:right; border-right:0;">
                                <div class="muted">For {{ Str::upper($companyName) }}</div>
                                <div><strong>Authorised Signatory</strong></div>
                            </td>
                        </tr>
                    </table>
                @else
                    @unless($hideDefaultHeader)
                        <div class="topline print-brand-header">
                            <div class="gstin"><strong>GSTIN :</strong> {{ $companyGstin }}</div>
                            <div class="right">
                                <div><strong>{{ $documentLabel }} No :</strong> {{ $documentNumber }}</div>
                                <div><strong>Dated :</strong> {{ $documentDate }}</div>
                            </div>
                        </div>

                        <div class="brand-block print-brand-header">
                        
                            <h1 class="company-name">{{ strtoupper($companyName) }}</h1>
                            <p class="company-meta">
                                {{ $companyAddress !== '' ? $companyAddress : ' ' }}
                                @if($companyContact !== '')
                                    <br>{{ $companyContact }}
                                @endif
                            </p>
                        </div>
                    @endunless

                    <div class="panels">
                        <div class="panel" data-lh="client_name">
                            <p class="panel-title">Party Details :</p>
                            <p class="party-name">{{ $partyName !== '' ? $partyName : '-' }}</p>
                            <p class="party-line">{{ $partyAddress !== '' ? $partyAddress : '-' }}</p>
                            <p class="party-line">{{ $partyContact !== '' ? $partyContact : '-' }}</p>
                            @if($partyEmail !== '')
                                <p class="party-line">{{ $partyEmail }}</p>
                            @endif
                            @if($partyGstin !== '')
                                <p class="party-line">GSTIN : {{ $partyGstin }}</p>
                            @endif
                            @if($ticket)
                                <p class="party-line">Ticket # : {{ $ticketRef !== '' ? $ticketRef : '-' }}</p>
                            @endif
                        </div>
                        <div class="panel">
                            <p class="panel-title">{{ $documentLabel }} Details :</p>
                            <div class="meta-row">
                                <div class="label">{{ $documentLabel }} No</div>
                                <div>:</div>
                                <div class="value">{{ $documentNumber }}</div>
                            </div>
                            <div class="meta-row">
                                <div class="label">Date</div>
                                <div>:</div>
                                <div class="value">{{ $documentDate }}</div>
                            </div>
                            <!-- @if(! $isInvoice)
                                <div class="meta-row">
                                    <div class="label">Status</div>
                                    <div>:</div>
                                    <div class="value">
                                        <span class="badge badge-neutral">Quotation</span>
                                    </div>
                                </div>
                            @endif -->
                            @if($displaySubject !== '')
                                <div class="meta-row" data-lh="subject">
                                    <div class="label">Subject</div>
                                    <div>:</div>
                                    <div class="value">{{ $displaySubject }}</div>
                                </div>
                            @endif
                            @if($showMsmeNumber && $companyMsmeNumber !== '')
                                <div class="meta-row">
                                    <div class="label">MSME No</div>
                                    <div>:</div>
                                    <div class="value">{{ $companyMsmeNumber }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                    @if($ticket && ! $isInvoice)
                        <div class="subject-block">
                            <p class="subject-label">{{ $documentLabel }} Subject</p>
                            <p class="subject-value">{{ $displaySubject !== '' ? $displaySubject : 'Service billing document' }}</p>
                        </div>
                    @endif

                    <table class="items-table" data-lh="items_table">
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
                            @foreach($lineItems as $row)
                                <tr>
                                    <td class="sn">{{ $row['sr_no'] }}</td>
                                    <td class="desc">
                                        <div class="item-main">{{ $row['name'] }}</div>
                                        @if($row['description'] !== '')
                                            <div class="item-sub">{{ $row['description'] }}</div>
                                        @endif
                                        @if($row['note'] !== '')
                                            <div class="item-sub">{{ $row['note'] }}</div>
                                        @endif
                                    </td>
                                    <td class="qty">{{ number_format((float) $row['qty'], 2) }}</td>
                                    <td class="unit">{{ $row['unit'] }}</td>
                                    <td class="price">{{ number_format((float) $row['price'], 2) }}</td>
                                    <td class="amount">{{ number_format((float) $row['amount'], 2) }}</td>
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
                            <!-- @if($isInvoice)
                                <div class="bill-box">
                                    <div><strong>Payment Status</strong> : {{ $statusLabel }}</div>
                                    <div><strong>Payment Method</strong> : {{ $paymentMethodLabel ?? '-' }}</div>
                                </div>
                            @endif -->
                        </div>
                    </div>

                    <div class="words">
                        {{ $amountInWords($grandTotal) }}
                    </div>

                    @if(! $isInvoice && filled($defaultTermsSource))
                        <div style="margin-top: 6px;"><strong>Terms & Conditions :</strong>
                            <div class="terms-list">{!! $defaultTermsHtml !!}</div>
                        </div>
                    @endif
                    @if($invoiceHasAdditionalNotes && filled($invoiceAdditionalNotes))
                     <br>   
                            <div class="document-section-title">Additional Notes</div>
                            <div class="document-section-line"></div>
                            <div class="document-section-body">{{ $invoiceAdditionalNotes }}</div>
                       
                    @endif

                    <div class="footer-grid" style="grid-template-columns: 1fr; border-top: 1px solid var(--heavy);">
                        <div class="footer-right" data-lh="signature_block" style="align-items: flex-end;">
                            <div class="muted" style="margin-bottom: 14px;">For {{ Str::upper($companyName) }}</div>
                            <div><strong>Authorised Signatory</strong></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endif

    @if($autoPrint)
        <script>
            window.addEventListener('load', function () {
                setTimeout(function () {
                    window.print();
                }, 250);
            });
        </script>
    @endif
</body>
</html>
