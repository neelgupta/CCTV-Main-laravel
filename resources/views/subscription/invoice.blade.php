<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Invoice - {{ $subscription->billing_reference ?: ('SUB-' . $subscription->id) }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            body { background: #fff !important; }
            .no-print { display: none !important; }
            .invoice-shell {
                box-shadow: none !important;
                margin: 0 !important;
                max-width: none !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900">
    <div class="mx-auto max-w-5xl p-6 print:p-0">
        <div class="mb-4 flex items-center justify-between no-print">
            <a href="{{ $backUrl ?? route('subscription.show') }}" class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Back
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                Print Invoice
            </button>
        </div>

        <div class="invoice-shell mx-auto max-w-4xl border border-slate-300 bg-white p-4 shadow-sm">
            <div class="border border-slate-300">

                <div class="border-t border-slate-300 px-4 py-5 text-center">
                    <h1 class="text-4xl font-black uppercase tracking-wide text-slate-950">
                        {{ $businessName ?: config('app.name') }}
                    </h1>
                    @if($businessAddress)
                        <div class="mt-1 text-sm">{{ $businessAddress }}</div>
                    @endif
                    <div class="text-sm">
                        {{ $businessMobile ?: '-' }}@if($businessEmail) | {{ $businessEmail }}@endif
                    </div>
                    <div>GSTIN : {{ $businessGst ?: '-' }}</div>
                </div>

                <div class="grid grid-cols-2 border-t border-slate-300">
                    <div class="border-r border-slate-300 px-4 py-3">
                        <div class="mb-2 font-bold">Party Details :</div>
                        <div class="text-lg font-bold uppercase">{{ $partyCompanyName }}</div>
                        @if($partyCompanyAddress)
                            <div class="mt-1 text-sm leading-5">{{ $partyCompanyAddress }}</div>
                        @endif
                        <div class="text-sm">{{ $partyCompanyMobile ?: '-' }}</div>
                        <div class="text-sm">{{ $partyCompanyEmail ?: '-' }}</div>
                        @if($partyCompanyGst)
                            <div class="mt-1 text-sm">GSTIN : {{ $partyCompanyGst }}</div>
                        @endif
                    </div>
                    <div class="px-4 py-3">
                        <div class="mb-2 font-bold">Invoice Details :</div>
                        <table class="w-full text-sm">
                            <tr>
                                <td class="py-1 align-top">Invoice No</td>
                                <td class="py-1 px-2 align-top">:</td>
                                <td class="py-1 align-top">{{ $invoiceNumber }}</td>
                            </tr>
                            <tr>
                                <td class="py-1 align-top">Date</td>
                                <td class="py-1 px-2 align-top">:</td>
                                <td class="py-1 align-top">{{ $invoiceDate }}</td>
                            </tr>
                            <tr>
                                <td class="py-1 align-top">Status</td>
                                <td class="py-1 px-2 align-top">:</td>
                                <td class="py-1 align-top">
                                    <span class="inline-block rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold uppercase text-emerald-700">
                                        {{ $status }}
                                    </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="border-t border-slate-300">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-100 text-[12px] font-bold uppercase">
                                <th class="border border-slate-300 px-2 py-1">S.N</th>
                                <th class="border border-slate-300 px-2 py-1">Description of Goods</th>
                                <th class="border border-slate-300 px-2 py-1">Qty.</th>
                                <th class="border border-slate-300 px-2 py-1">Unit</th>
                                <th class="border border-slate-300 px-2 py-1">Price</th>
                                <th class="border border-slate-300 px-2 py-1">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td class="border border-slate-300 px-2 py-1 text-center">1</td>
                                <td class="border border-slate-300 px-2 py-1">
                                    {{ $planName }}
                                    <div class="text-xs text-slate-500">Subscription Plan (Incl. GST {{ $gstRate }}%)</div>
                                </td>
                                <td class="border border-slate-300 px-2 py-1 text-center">1.00</td>
                                <td class="border border-slate-300 px-2 py-1 text-center">Plan</td>
                                <td class="border border-slate-300 px-2 py-1 text-right">₹{{ $subtotalFormatted }}</td>
                                <td class="border border-slate-300 px-2 py-1 text-right">₹{{ $subtotalFormatted }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="grid grid-cols-2 border-t border-slate-300">
                    <div class="border-r border-slate-300 px-4 py-3">
                        <div class="font-bold uppercase">Tax Summary</div>
                        <table class="mt-2 w-full text-sm">
                            <tr>
                                <td class="py-1">Subtotal</td>
                                <td class="py-1 text-right">₹{{ $subtotalFormatted }}</td>
                            </tr>
                            <tr>
                                <td class="py-1">Discount</td>
                                <td class="py-1 text-right">₹0.00</td>
                            </tr>
                            <tr>
                                <td class="py-1">GST Amount ({{ $gstRate }}%)</td>
                                <td class="py-1 text-right">₹{{ $gstAmountFormatted }}</td>
                            </tr>
                            <tr>
                                <td class="py-1">Paid Amount</td>
                                <td class="py-1 text-right">₹{{ $grandTotal }}</td>
                            </tr>
                            <tr>
                                <td class="py-1">Pending Amount</td>
                                <td class="py-1 text-right">₹0.00</td>
                            </tr>
                        </table>
                    </div>
                    <div class="px-4 py-3">
                        <div class="font-bold uppercase">Billing Total</div>
                        <div class="mt-2 text-sm">Grand Total</div>
                        <div class="text-right text-3xl font-black">₹{{ $grandTotal }}</div>
                        <!-- <div class="mt-4 border border-slate-300 p-3 text-sm">
                            <div>Payment Status : {{ $status }}</div>
                            <div>Payment Method : {{ $paymentMethod }}</div>
                        </div> -->
                    </div>
                </div>

                <div class="border-t border-slate-300 px-4 py-2 text-sm font-semibold">
                    Rupees {{ $amountWords ? ucwords($amountWords) : 'Zero' }} Only
                </div>

                <div class="grid grid-cols-2 border-t border-slate-300">
                    <div class="px-4 py-3">
                        <div class="text-sm"><span class="font-semibold">Note :</span> Thank you for your business.</div>
                        <div class="mt-2 text-sm"><span class="font-semibold">Payment Method :</span> {{ $paymentMethod }}</div>
                    </div>
                    <div class="border-l border-slate-300 px-4 py-3 text-center">
                        <div class="py-2 text-lg text-slate-600">For {{ $businessName ?: $company->name }}</div>
                        <div class="mt-3 border-t border-slate-300 pt-2 text-sm font-semibold">Authorised Signatory</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @if(request()->boolean('print'))
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>
</html>
