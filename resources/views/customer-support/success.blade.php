<x-guest-layout>
    @php
        $companyLogoUrl = $company?->print_logo_url ?? $company?->logo_url;
    @endphp

    <div id="customer-support-success-page" class="min-h-screen bg-gradient-to-br from-emerald-50 via-white to-slate-50 px-4 py-10 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-3xl">
            <div class="overflow-hidden rounded-3xl border border-emerald-200 bg-white shadow-xl">
                <div class="border-b border-emerald-100 bg-emerald-50 px-6 py-6 sm:px-8">
                    <div class="flex items-center gap-4">
                        @if($companyLogoUrl)
                            <img src="{{ $companyLogoUrl }}" alt="{{ $company?->name ?: 'Company' }}" class="h-14 w-14 rounded-2xl border border-emerald-100 bg-white object-contain p-2">
                        @endif
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-emerald-700">Complaint Registered</p>
                            <h1 class="mt-1 text-2xl font-bold text-slate-900">Thank you, {{ $client->name }}</h1>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-8 sm:px-8">
                    <div class="rounded-2xl border border-emerald-100 bg-emerald-50/70 p-6">
                        <p class="text-lg font-semibold text-slate-900">Your complaint has been registered successfully.</p>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Ticket Number</div>
                                <div class="mt-1 text-2xl font-bold text-emerald-700">{{ $ticket->ticket_number }}</div>
                            </div>
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-slate-400">Estimated Response Time</div>
                                <div class="mt-1 text-lg font-semibold text-slate-900">{{ $estimatedResponse }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 space-y-3 text-sm text-slate-600">
                        <p>Our team has been notified and your complaint is now in the support queue.</p>
                        <p>Need any further assistance? Please keep this ticket number for reference.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-guest-layout>
