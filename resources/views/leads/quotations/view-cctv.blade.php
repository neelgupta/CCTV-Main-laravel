<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $titleCompany = $lead->company ?? auth()->user()?->company;
        $titleDisplayVersion = $quotation->version ?? $titleCompany?->nextAvailableQuotationVersion();
        $quoteNo =  $titleDisplayVersion;
    @endphp
    <title>CCTV Quotation {{ $quoteNo }}</title>
    @php
        use App\Support\QuotationTaxCalculator;

        $company = $lead->company ?? auth()->user()?->company;
        $displayVersion = $quotation->version ?? $company?->nextAvailableQuotationVersion();
        $quoteNo = $displayVersion;
        $showBrandHeader = $company?->shouldShowPrintLogo() ?? true;
        $companyLogoUrl = $showBrandHeader ? ($company?->print_logo_url ?? $company?->logo_url) : null;
        $companyBrandName = $companyLogoUrl ? null : ($company?->name ?? 'Workshop Platform');
        $formData = $quotation->form_data ?? [];
        $items = collect($formData['items'] ?? [])->map(function ($item) {
            $qty = (float) ($item['qty'] ?? 0);
            $price = (float) ($item['price'] ?? $item['rate'] ?? $item['unit_price'] ?? 0);
            $baseTotal = (float) ($item['base_total'] ?? ($qty * $price));
            $gst = (float) ($item['gst'] ?? 0);
            $gstAmount = (float) ($item['gst_amount'] ?? (($baseTotal * $gst) / 100));

            return array_merge($item, [
                'qty' => $qty,
                'price' => $price,
                'base_total' => $baseTotal,
                'gst' => $gst,
                'gst_amount' => $gstAmount,
                'total' => (float) ($item['total'] ?? $item['amount'] ?? ($baseTotal + $gstAmount)),
            ]);
        });
        $discount = (float) data_get($formData, 'pricing.discount', data_get($formData, 'discount', 0));
        $companyStateName = QuotationTaxCalculator::resolveCompanyStateName($company);
        $customerStateName = QuotationTaxCalculator::resolveCustomerStateName($formData, $lead, $client ?? null);
        $isIntraState = QuotationTaxCalculator::isIntraState($companyStateName, $customerStateName);
        $taxSummary = QuotationTaxCalculator::calculate($items->all(), $discount, $isIntraState);
        $legacyTermsText = collect([
            ['label' => 'Warranty', 'value' => data_get($formData, 'terms.warranty')],
            ['label' => 'Payment', 'value' => data_get($formData, 'terms.payment')],
            ['label' => 'Delivery', 'value' => data_get($formData, 'terms.delivery')],
        ])
            ->filter(fn ($row) => filled($row['value'] ?? null))
            ->map(fn ($row) => $row['label'] . ': ' . $row['value'])
            ->implode("\n");
        $termSource = trim((string) (data_get($formData, 'terms.conditions') ?: $legacyTermsText ?: ($company?->defaultTermsConditionsText() ?? '')));
        $termHtml = $termSource !== ''
            ? (\Illuminate\Support\Str::contains($termSource, '<') ? $termSource : nl2br(e($termSource)))
            : ($company?->defaultTermsConditionsHtml() ?? '');
    @endphp
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; line-height: 1.6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: auto; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #2563eb; padding-bottom: 20px; margin-bottom: 30px; }
        .company-info h1 { margin: 0; color: #1e40af; font-size: 24px; }
        .company-info p { margin: 2px 0; font-size: 13px; color: #666; }
        .quotation-info { text-align: right; }
        .quotation-info h2 { margin: 0; color: #333; font-size: 20px; }
        .quotation-info p { margin: 2px 0; font-size: 13px; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 16px; font-weight: bold; color: #1e40af; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px; margin-bottom: 15px; text-transform: uppercase; }
        .grid { display: grid; grid-template-cols: 1fr 1fr; gap: 20px; }
        .details p { margin: 4px 0; font-size: 14px; }
        .details label { font-weight: bold; color: #4b5563; display: inline-block; width: 120px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #eff6ff; border: 1px solid #bfdbfe; padding: 10px; text-align: left; font-size: 12px; text-transform: uppercase; color: #1e40af; }
        td { border: 1px solid #e2e8f0; padding: 10px; font-size: 13px; }
        .text-right { text-align: right; }
        .totals { margin-left: auto; width: 300px; margin-top: 20px; }
        .totals-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 14px; }
        .totals-row.grand-total { border-top: 2px solid #2563eb; margin-top: 10px; padding-top: 10px; font-weight: bold; font-size: 18px; color: #1e40af; }
        .footer { margin-top: 50px; border-top: 1px solid #eee; pt: 20px; font-size: 12px; color: #999; text-align: center; }
        @media print {
            .no-print { display: none; }
            body { background: white; }
            .container { width: 100%; padding: 0; }
        }
        .no-print { background: #f1f5f9; padding: 15px; display: flex; justify-content: center; gap: 10px; border-bottom: 1px solid #e2e8f0; }
        .btn { padding: 8px 16px; border-radius: 6px; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 14px; }
        .btn-print { background: #2563eb; color: white; border: none; }
        .btn-download { background: #fff; color: #ef4444; border: 1px solid #ef4444; }
        .btn-back { background: white; color: #2563eb; border: 1px solid #2563eb; }
        @include('partials.company-print-settings', ['company' => $company])
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn btn-print">Print Quotation</button>
        <a href="{{ route('leads.quotation.download-pdf', [$lead, $quotation]) }}" class="btn btn-download">PDF Download</a>
        @if($lead && filled($lead->getRouteKey()))
            <button type="button" class="btn" style="background:#4f46e5;color:#fff;border-color:#4f46e5;" onclick="openSaveTemplateModal()">Save as Template</button>
            <a href="{{ route('leads.show', $lead) }}" class="btn btn-back">Back to Lead</a>
        @else
            <a href="{{ url()->previous() }}" class="btn btn-back">Back</a>
        @endif
    </div>

    <!-- Save Template Modal for View CCTV -->
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

    <div class="container">
        <div class="header print-header-trim">
            <div class="company-info print-brand-header">
                @if($showBrandHeader && $companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="Logo" style="max-height: 60px; margin-bottom: 10px;">
                @endif
                <h1>{{ $company?->name ?? 'Workshop Platform' }}</h1>
                <p>{{ $company->address }}</p>
                <p>Email: {{ $company->email }} | Phone: {{ $company->phone_number }}</p>
                @if($company->gst_number)<p>GSTIN: {{ $company->gst_number }}</p>@endif
            </div>
            <div class="quotation-info">
                <h2>QUOTATION</h2>
                <p><label>Quotation No:</label> {{ $quoteNo }}</p>
                <p><label>Date:</label> {{ date('d M Y', strtotime($formData['date'] ?? now())) }}</p>
                <p><label>Valid Until:</label> {{ date('d M Y', strtotime($formData['valid_until'] ?? now()->addDays(30))) }}</p>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Client Information</div>
            <div class="grid">
                <div class="details">
                    <p><label>Name:</label> {{ $formData['client']['name'] ?? $lead->client_name }}</p>
                    <p><label>Contact:</label> {{ $formData['client']['contact'] ?? $lead->mobile }}</p>
                    @if(!empty($formData['client']['msme_number']))
                        <p><label>MSME No:</label> {{ $formData['client']['msme_number'] }}</p>
                    @endif
                </div>
                <div class="details">
                    <p><label>Billing Address:</label> {{ $formData['client']['billing_address'] ?? $lead->address }}</p>
                    <p><label>Site Address:</label> {{ $formData['client']['site_address'] ?? $lead->address }}</p>
                    @if(!empty($formData['project']['site_name']))
                        <p><label>Site Name:</label> {{ $formData['project']['site_name'] }}</p>
                    @endif
                </div>
            </div>
        </div>
        @php($subject = trim((string) data_get($formData, 'project.subject', '')))
        @if($subject !== '')
            <div class="section">
                <div style="font-weight: bold; font-size: 15px; border-left: 4px solid #1e40af; padding-left: 10px; margin-bottom: 20px;">
                    Subject: {{ $subject }}
                </div>
            </div>
        @endif
        <div class="section">
            <div class="section-title">Equipment & Items</div>
            <table>
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        <tr>
                            <td>
                                <div>{{ $item['name'] ?? '' }}</div>
                                @if(!empty($item['description']) && trim($item['description']) !== trim($item['name'] ?? ''))
                                    <div style="font-size: 11px; color: #6b7280; margin-top: 2px;">{{ $item['description'] }}</div>
                                @endif
                            </td>
                            <td class="text-right">{{ $item['qty'] ?? 0 }}</td>
                            <td class="text-right">&#8377;{{ number_format((float) ($item['price'] ?? 0), 2) }}</td>
                            <td class="text-right">&#8377;{{ number_format((float) ($item['total'] ?? 0), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="section">
            <div class="grid">
                <div>
                    <div class="totals">
                        <div class="totals-row">
                            <span>Items Subtotal:</span>
                            <span>&#8377;{{ number_format($taxSummary['subtotal'], 2) }}</span>
                        </div>
                        @if($taxSummary['discount'] > 0)
                            <div class="totals-row">
                                <span>Discount:</span>
                                <span>-&#8377;{{ number_format($taxSummary['discount'], 2) }}</span>
                            </div>
                        @endif
                        @if($isIntraState)
                            <div class="totals-row">
                                <span>SGST:</span>
                                <span>&#8377;{{ number_format($taxSummary['sgst_amount'], 2) }}</span>
                            </div>
                            <div class="totals-row">
                                <span>CGST:</span>
                                <span>&#8377;{{ number_format($taxSummary['cgst_amount'], 2) }}</span>
                            </div>
                        @else
                            <div class="totals-row">
                                <span>IGST:</span>
                                <span>&#8377;{{ number_format($taxSummary['igst_amount'], 2) }}</span>
                            </div>
                        @endif
                        <div class="totals-row grand-total">
                            <span>Grand Total:</span>
                            <span>&#8377;{{ number_format($taxSummary['grand_total'], 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if(filled($termSource))
        <div class="section">
            <div class="section-title">Terms & Conditions</div>
            <div class="details">{!! $termHtml !!}</div>
        </div>
        @endif

        <div class="footer">
            <p>This is a computer-generated quotation.</p>
            <p>&copy; {{ date('Y') }} {{ $companyBrandName ?? 'Workshop Platform' }}. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
