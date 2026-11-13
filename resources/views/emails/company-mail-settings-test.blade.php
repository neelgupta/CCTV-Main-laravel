@php
    $companyName = $company?->name ?: 'Company';
@endphp
<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <p style="margin: 0 0 12px;">Hello,</p>

    <p style="margin: 0 0 12px;">
        This is a test email from {{ $companyName }}.
    </p>

    <p style="margin: 0 0 12px;">
        If you received this message, the company SMTP configuration is working correctly.
    </p>

    <p style="margin: 0;">Thank you.</p>
</div>
