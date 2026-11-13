<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Lead Summary - {{ $lead->client_name }}</title>
    <style>
        body { font-family: Calibri, Arial, sans-serif; color: #1f2937; margin: 26px; }
        h1 { margin: 0 0 10px 0; font-size: 22px; color: #0f172a; }
        .meta { margin: 0 0 18px 0; color: #4b5563; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; text-align: left; padding: 8px 10px; font-size: 13px; }
        th { background: #eef2ff; color: #1e1b4b; width: 34%; }
        .pill { display: inline-block; padding: 2px 8px; border-radius: 999px; background: #e0e7ff; color: #3730a3; }
    </style>
</head>
<body>
    <h1>Lead Summary</h1>
    <p class="meta">Client: {{ $lead->client_name }} | Generated: {{ now()->format('M d, Y H:i') }}</p>

    <table>
        <tbody>
            <tr><th>Client Name</th><td>{{ $lead->client_name }}</td></tr>
            <tr><th>Email</th><td>{{ $lead->email ?? 'N/A' }}</td></tr>
            <tr><th>Mobile</th><td>{{ $lead->mobile ?? 'N/A' }}</td></tr>
            <tr><th>Address</th><td>{{ $lead->address ?? 'N/A' }}</td></tr>
            <tr><th>Lead Source</th><td>{{ $lead->source ?? 'N/A' }}</td></tr>
            <tr><th>BNI / Referral Person Name</th><td>{{ $lead->referral_person_name ?? 'N/A' }}</td></tr>
            @foreach(collect($lead->company?->normalizedLeadCustomFields() ?? []) as $field)
                @php
                    $value = data_get($lead->custom_fields ?? [], $field['key']);
                    $displayValue = $lead->formatCustomFieldValue($field, $value);
                @endphp
                <tr>
                    <th>{{ $field['label'] }}</th>
                    <td>{{ $displayValue }}</td>
                </tr>
            @endforeach
            <tr><th>Status</th><td><span class="pill">{{ $lead->status?->name ?? 'N/A' }}</span></td></tr>
            <tr><th>Category</th><td>{{ $lead->category?->name ?? 'N/A' }}</td></tr>
            <tr><th>Assigned To</th><td>{{ $lead->assignedUsers->isNotEmpty() ? $lead->assignedUsers->pluck('name')->join(', ') : ($lead->assignedTo?->name ?? 'Unassigned') }}</td></tr>
            <tr><th>Created By</th><td>{{ $lead->creator?->name ?? 'N/A' }}</td></tr>
            <tr><th>Estimated Value</th><td>₹{{ number_format((float) $lead->estimated_value, 2) }}</td></tr>
            <tr><th>Effective Value</th><td>₹{{ number_format((float) $effectiveValue, 2) }}</td></tr>
            <tr><th>Accepted Quotation</th><td>{{ $acceptedQuotation ? ('Version ' . $acceptedQuotation->version . ' (₹' . number_format((float) $acceptedQuotation->amount, 2) . ')') : 'No accepted quotation' }}</td></tr>
            <tr><th>Total Quotations</th><td>{{ $lead->quotations->count() }}</td></tr>
            <tr><th>Total Comments</th><td>{{ $lead->comments->count() }}</td></tr>
            <tr><th>Total Documents</th><td>{{ $lead->documents->count() }}</td></tr>
            <tr><th>Created At</th><td>{{ optional($lead->created_at)->format('Y-m-d H:i:s') }}</td></tr>
            <tr><th>Updated At</th><td>{{ optional($lead->updated_at)->format('Y-m-d H:i:s') }}</td></tr>
        </tbody>
    </table>
</body>
</html>
