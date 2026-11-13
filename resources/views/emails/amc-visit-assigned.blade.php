@php
    $amc = $visit->amc;
@endphp
<div style="font-family: Arial, sans-serif; color: #0f172a;">
    <p style="font-size: 16px; margin: 0 0 12px;">Hello {{ $technician->name }},</p>

    <p style="margin: 0 0 12px;">
        An AMC visit has been assigned to you automatically.
    </p>

    <ul style="margin: 0 0 16px; padding-left: 18px;">
        <li><strong>AMC:</strong> {{ $amc?->contract_no ?: 'AMC' }}</li>
        <li><strong>Client:</strong> {{ $amc?->customer_name ?: '-' }}</li>
        <li><strong>Visit Date:</strong> {{ $visit->visit_date?->format('d M Y') ?: '-' }}</li>
        <li><strong>Address:</strong> {{ $amc?->address ?: '-' }}</li>
        <li><strong>Priority:</strong> Medium</li>
    </ul>

    <p style="margin: 0 0 16px;">
        Please check the AMC details page for more information.
    </p>
</div>
