@props(['compact' => false])

@php
    $callNumber = trim((string) setting('support_call_number', ''));
    $whatsappNumber = trim((string) setting('support_whatsapp_number', ''));

    $telHref = $callNumber !== '' ? 'tel:'.preg_replace('/[^0-9+]/', '', $callNumber) : null;

    $whatsappDigits = preg_replace('/\D/', '', $whatsappNumber);
    // wa.me needs the country code; numbers saved as a bare 10-digit mobile are treated as Indian.
    if (strlen($whatsappDigits) === 10) {
        $whatsappDigits = '91'.$whatsappDigits;
    }
    $whatsappHref = $whatsappDigits !== '' ? 'https://wa.me/'.$whatsappDigits : null;
@endphp

@if($telHref || $whatsappHref)
    <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
        <button
            type="button"
            @click="open = !open"
            :aria-expanded="open.toString()"
            aria-haspopup="true"
            aria-label="Contact support"
            @class([
                'app-button app-button-secondary gap-2' => ! $compact,
                'inline-flex h-10 w-10 items-center justify-center border border-slate-200 bg-white text-slate-700 shadow-lg shadow-black/5 transition hover:bg-rose-50 hover:text-[#C22032]' => $compact,
            ])
        >
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path d="M4 14v-2a8 8 0 0116 0v2" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M4 14a2 2 0 012-2h1v6H6a2 2 0 01-2-2v-2zM20 14a2 2 0 00-2-2h-1v6h1a2 2 0 002-2v-2z" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M18 18v1a2 2 0 01-2 2h-3" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            @unless($compact)
                <span>Support</span>
                <svg class="h-4 w-4 text-slate-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 9l6 6 6-6" stroke-linecap="round" stroke-linejoin="round"/></svg>
            @endunless
        </button>

        <div
            x-show="open"
            @click.outside="open = false"
            x-transition
            class="absolute right-0 z-50 mt-2 w-64 rounded-[20px] border border-slate-200 bg-white p-2 shadow-2xl"
            style="display:none"
        >
            <div class="px-3 pb-2 pt-1 text-xs font-semibold uppercase tracking-[0.18em] text-slate-400">Contact support</div>

            @if($telHref)
                <a href="{{ $telHref }}" @click="open = false" class="flex items-center gap-3 rounded-xl px-3 py-3 hover:bg-slate-100">
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-sky-100 text-sky-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 4h4l2 5-2.5 1.5a11 11 0 005 5L15 13l5 2v4a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900">Call</span>
                        <span class="block truncate text-xs text-slate-500">{{ $callNumber }}</span>
                    </span>
                </a>
            @endif

            @if($whatsappHref)
                <a href="{{ $whatsappHref }}" target="_blank" rel="noopener" @click="open = false" class="flex items-center gap-3 rounded-xl px-3 py-3 hover:bg-slate-100">
                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.62.71.23 1.36.2 1.87.12.57-.08 1.76-.72 2.01-1.41.25-.69.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35M12.05 21.5h-.01a9.4 9.4 0 01-4.79-1.31l-.34-.2-3.56.93.95-3.47-.22-.36a9.4 9.4 0 01-1.44-5.01c0-5.2 4.23-9.43 9.44-9.43 2.52 0 4.89.98 6.67 2.77a9.37 9.37 0 012.76 6.67c0 5.2-4.23 9.43-9.43 9.43m8.02-17.45A11.27 11.27 0 0012.05.75C5.8.75.72 5.83.72 12.08c0 2 .52 3.95 1.52 5.66L.62 23.25l5.65-1.48a11.3 11.3 0 005.78 1.47h.01c6.25 0 11.33-5.08 11.33-11.33 0-3.03-1.18-5.87-3.32-8.02"/></svg>
                    </span>
                    <span class="min-w-0">
                        <span class="block text-sm font-semibold text-slate-900">WhatsApp</span>
                        <span class="block truncate text-xs text-slate-500">{{ $whatsappNumber }}</span>
                    </span>
                </a>
            @endif
        </div>
    </div>
@endif
