@php
    $companyName = $company?->name ?: 'Our team';
@endphp
<div style="font-family: Arial, sans-serif; color: #0f172a; line-height: 1.6;">
    <p style="margin: 0 0 12px;">Hello {{ $client->name }},</p>

    <p style="margin: 0 0 12px;">
        Your complaint has been successfully registered with {{ $companyName }}.
    </p>

    <p style="margin: 0 0 12px;">
        <strong>Ticket Number:</strong> {{ $ticket->ticket_number }}
    </p>

    <p style="margin: 0 0 12px;">
        Our team will review it shortly. Please keep this ticket number for reference.
    </p>

    <p style="margin: 0;">Thank you.</p>
</div>
