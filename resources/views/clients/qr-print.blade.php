<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer QR Code - {{ $client->name }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #f8fafc;
            color: #0f172a;
        }
        .page {
            max-width: 920px;
            margin: 32px auto;
            padding: 24px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 24px;
            box-shadow: 0 20px 50px rgba(15, 23, 42, 0.08);
        }
        .toolbar {
            display: flex;
            gap: 12px;
            margin-bottom: 18px;
        }
        .btn {
            border: 0;
            border-radius: 10px;
            padding: 12px 18px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary { background: #0f172a; color: #fff; }
        .btn-outline { background: #fff; color: #0f172a; border: 1px solid #cbd5e1; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 24px;
        }
        .brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .brand img {
            width: 72px;
            height: 72px;
            object-fit: contain;
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            padding: 8px;
            background: #fff;
        }
        .title {
            margin: 0;
            font-size: 28px;
            line-height: 1.1;
        }
        .sub {
            margin: 6px 0 0;
            color: #475569;
            font-size: 14px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 320px;
            gap: 24px;
        }
        .card {
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 20px;
            background: #f8fafc;
        }
        .qr-box {
            text-align: center;
        }
        .qr-box img {
            width: 320px;
            max-width: 100%;
            border-radius: 18px;
            background: #fff;
            padding: 12px;
            border: 1px solid #e2e8f0;
        }
        .meta {
            margin-top: 14px;
            font-size: 14px;
            color: #334155;
            word-break: break-all;
        }
        .label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .18em;
            color: #64748b;
            margin-bottom: 6px;
        }
        .value {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .page { box-shadow: none; margin: 0; border: 0; border-radius: 0; }
        }
        @media (max-width: 768px) {
            .grid { grid-template-columns: 1fr; }
            .header { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body onload="window.print()">
    @php
        $companyLogoUrl = $company?->print_logo_url ?? $company?->logo_url;
    @endphp
    <div class="page">
        <div class="toolbar">
            <button class="btn btn-primary" onclick="window.print()">Print QR</button>
            <a class="btn btn-outline" href="{{ route('settings.clients.show', $client) }}">Back to Client</a>
        </div>

        <div class="header">
            <div class="brand">
                @if($companyLogoUrl)
                    <img src="{{ $companyLogoUrl }}" alt="{{ $company?->name ?: 'Company' }}">
                @endif
                <div>
                    <div class="label">Customer QR Code</div>
                    <h1 class="title">{{ $client->name }}</h1>
                    <p class="sub">{{ $company?->name ?: 'Company' }}</p>
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <div class="label">Public Link</div>
                <div class="value">{{ $publicLink }}</div>
                <div style="margin-top: 18px; font-size: 14px; color: #475569;">
                    This QR code opens the public complaint form without login.
                </div>
            </div>

            <div class="card qr-box">
                <img src="{{ $qrImageUrl }}" alt="Customer QR Code">
                <div class="meta">
                    Scan this code to open the public complaint form.
                </div>
            </div>
        </div>
    </div>
</body>
</html>
