@php
    $customLogoPath = null;
    foreach (['png', 'svg', 'jpg', 'webp'] as $ext) {
        if (file_exists(public_path("assets/images/logo.{$ext}"))) {
            $customLogoPath = "assets/images/logo.{$ext}";
            break;
        }
    }
@endphp

@if($customLogoPath)
    <img src="{{ asset($customLogoPath) }}" alt="{{ config('app.name', 'CCTV Workshop') }}" {{ $attributes->merge(['class' => 'max-h-full max-w-full object-contain']) }}>
@else
    {{-- Default CCTV / Camera SVG Icon (falls back if custom file is not present) --}}
    <div {{ $attributes->merge(['class' => 'flex h-11 w-11 shrink-0 items-center justify-center bg-[#C22032] shadow-sm shadow-rose-900/20 text-white']) }}>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
            <circle cx="12" cy="13" r="4"/>
        </svg>
    </div>
@endif
