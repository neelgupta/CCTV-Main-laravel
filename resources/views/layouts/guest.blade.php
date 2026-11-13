<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $appName = config('app.name', 'SaaS Workshop');
            $themeColor = '#0f172a';
            $browserIcon = asset('pwa/icon-192.png');
            $browserIconPath = public_path('pwa/icon-192.png');
            $browserIconVersion = is_file($browserIconPath) ? filemtime($browserIconPath) : '1';
            $shareSiteName = 'CCTV Pro One';
            $shareTitle = 'CCTV Pro One | CCTV Cameras & Security Solutions';
            $metaDescription = 'Explore CCTV cameras, DVRs, NVRs, video surveillance systems, and security accessories for your home, office, and business.';
            $shareImagePath = public_path('pwa/og-image.jpg');
            $shareImageVersion = is_file($shareImagePath) ? filemtime($shareImagePath) : '1';
            $shareImage = asset('pwa/og-image.jpg') . '?v=' . $shareImageVersion;
        @endphp

        <title>{{ $appName }}</title>
        <meta name="description" content="{{ $metaDescription }}">

        <!-- Link preview (WhatsApp, Facebook, LinkedIn, X) -->
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $shareSiteName }}">
        <meta property="og:title" content="{{ $shareTitle }}">
        <meta property="og:description" content="{{ $metaDescription }}">
        <meta property="og:url" content="{{ url('/') }}">
        <meta property="og:image" content="{{ $shareImage }}">
        <meta property="og:image:secure_url" content="{{ $shareImage }}">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="750">
        <meta property="og:image:height" content="600">
        <meta property="og:image:alt" content="{{ $shareSiteName }} logo">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ $shareTitle }}">
        <meta name="twitter:description" content="{{ $metaDescription }}">
        <meta name="twitter:image" content="{{ $shareImage }}">

        <meta name="theme-color" content="{{ $themeColor }}">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="{{ $appName }}">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="application-name" content="{{ $appName }}">
        <meta name="format-detection" content="telephone=no">
        <link rel="manifest" href="{{ asset('manifest.json') }}">
        <link rel="icon" type="image/png" href="{{ $browserIcon }}?v={{ $browserIconVersion }}">
        <link rel="shortcut icon" href="{{ $browserIcon }}?v={{ $browserIconVersion }}">
        <link rel="apple-touch-icon" href="{{ $browserIcon }}?v={{ $browserIconVersion }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800,900&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-gray-900 antialiased">
        <div class="min-h-screen bg-[var(--ww-surface)]">
            {{ $slot }}
        </div>

        <x-pwa-install-banner />

        @stack('scripts')
        @include('partials.custom-select-init')
    </body>
</html>
