<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $appName = config('app.name', 'SaaS Workshop');
            $themeColor = '#0f172a';
            $browserIcon = asset('pwa/icon-192.png');
            $browserIconPath = public_path('pwa/icon-192.png');
            $browserIconVersion = is_file($browserIconPath) ? filemtime($browserIconPath) : '1';
        @endphp

        <title>{{ trim($__env->yieldContent('title', $appName)) }}</title>
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

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <style>
            /* Custom Modal Styles */
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(17, 24, 39, 0.7);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                opacity: 0;
                visibility: hidden;
                transition: all 0.15s ease-in-out;
            }
            .modal-overlay.active {
                opacity: 1;
                visibility: visible;
            }
            .modal-box {
                background: white;
                border-radius: 8px;
                box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.05), 0 10px 15px -3px rgba(0, 0, 0, 0.1);
                max-width: 420px;
                width: 90%;
                transform: translateY(-10px);
                transition: transform 0.15s ease-in-out;
            }
            .modal-overlay.active .modal-box {
                transform: translateY(0);
            }
            .modal-icon-wrap {
                text-align: center;
                padding: 24px 24px 8px;
            }
            .modal-icon {
                width: 48px;
                height: 48px;
                border-radius: 50%;
                background: #fee2e2;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 12px;
            }
            .modal-icon i {
                font-size: 24px;
                color: #dc2626;
            }
            .modal-title {
                font-size: 18px;
                font-weight: 600;
                color: #111827;
                margin: 0 0 8px 0;
                text-align: center;
            }
            .modal-body {
                padding: 0 24px 20px;
                text-align: center;
            }
            .modal-body p {
                color: #6b7280;
                font-size: 14px;
                line-height: 1.5;
                margin: 0;
            }
            .modal-footer {
                padding: 16px 24px;
                display: flex;
                justify-content: center;
                gap: 12px;
                border-top: 1px solid #e5e7eb;
            }
            .modal-btn-cancel {
                padding: 10px 20px;
                background: white;
                border: 1px solid #d1d5db;
                border-radius: 8px;
                color: #374151;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.15s;
            }
            .modal-btn-cancel:hover {
                background: #f3f4f6;
            }
            .modal-btn-confirm {
                padding: 10px 20px;
                background: #dc2626;
                border: none;
                border-radius: 8px;
                color: white;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.15s;
            }
            .modal-btn-confirm:hover {
                background: #b91c1c;
            }
            
            /* Remove underlines from all buttons and links */
            button, a, .btn {
                text-decoration: none !important;
            }
            
            /* Border bottom for headers */
            .border-b-2 {
                border-bottom: none !important;
            }

            /* Tailwind-like utility classes */
            .min-h-screen { min-height: 100vh; }
            .font-sans { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; }
            .antialiased { -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale; }
            .bg-gray-100 { background-color: #f3f4f6; }
            .text-gray-800 { color: #1f2937; }
            .max-w-7xl { max-width: 80rem; }
            .mx-auto { margin-left: auto; margin-right: auto; }
            .bg-white { background-color: #fff; }
            .rounded-xl { border-radius: 0.75rem; }
            .shadow-sm { box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); }
            .border { border-width: 1px; }
            .border-gray-200 { border-color: #e5e7eb; }
            .overflow-hidden { overflow: hidden; }
            .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
            .py-4 { padding-top: 1rem; padding-bottom: 1rem; }
            .py-6 { padding-top: 1.5rem; padding-bottom: 1.5rem; }
            .py-12 { padding-top: 3rem; padding-bottom: 3rem; }
            .py-1 { padding-top: 0.25rem; padding-bottom: 0.25rem; }
            .py-1\.5 { padding-top: 0.375rem; padding-bottom: 0.375rem; }
            .px-3 { padding-left: 0.75rem; padding-right: 0.75rem; }
            .px-4 { padding-left: 1rem; padding-right: 1rem; }
            .px-2\.5 { padding-left: 0.625rem; padding-right: 0.625rem; }
            .px-2 { padding-left: 0.5rem; padding-right: 0.5rem; }
            .py-0\.5 { padding-top: 0.125rem; padding-bottom: 0.125rem; }
            .px-6 { padding-left: 1.5rem; padding-right: 1.5rem; }
            .py-3 { padding-top: 0.75rem; padding-bottom: 0.75rem; }
            .mr-1 { margin-right: 0.25rem; }
            .mr-2 { margin-right: 0.5rem; }
            .mb-1 { margin-bottom: 0.25rem; }
            .mb-4 { margin-bottom: 1rem; }
            .mt-4 { margin-top: 1rem; }
            .mx-6 { margin-left: 1.5rem; margin-right: 1.5rem; }
            .mx-auto { margin-left: auto; margin-right: auto; }
            .space-x-2 > * + * { margin-left: 0.5rem; }
            .flex { display: flex; }
            .inline-flex { display: inline-flex; }
            .items-center { align-items: center; }
            .justify-between { justify-content: space-between; }
            .justify-end { justify-content: flex-end; }
            .text-lg { font-size: 1.125rem; line-height: 1.75rem; }
            .text-sm { font-size: 0.875rem; line-height: 1.25rem; }
            .text-xs { font-size: 0.75rem; line-height: 1rem; }
            .text-base { font-size: 1rem; line-height: 1.5rem; }
            .font-medium { font-weight: 500; }
            .font-semibold { font-weight: 600; }
            .text-center { text-align: center; }
            .text-right { text-align: right; }
            .text-gray-500 { color: #6b7280; }
            .text-gray-600 { color: #4b5563; }
            .text-gray-700 { color: #374151; }
            .text-gray-800 { color: #1f2937; }
            .text-gray-900 { color: #111827; }
            .text-indigo-700 { color: #4f46e5; }
            .text-red-700 { color: #b91c1c; }
            .text-cyan-700 { color: #0e7490; }
            .text-green-800 { color: #166534; }
            .uppercase { text-transform: uppercase; }
            .tracking-wider { letter-spacing: 0.05em; }
            .whitespace-nowrap { white-space: nowrap; }
            .w-full { width: 100%; }
            .w-4 { width: 1rem; }
            .w-8 { width: 2rem; }
            .w-16 { width: 4rem; }
            .h-4 { height: 1rem; }
            .h-8 { height: 2rem; }
            .h-16 { height: 4rem; }
            .h-5 { height: 1.25rem; }
            .h-3\.5 { height: 0.875rem; }
            .rounded-lg { border-radius: 0.5rem; }
            .rounded-full { border-radius: 9999px; }
            .rounded { border-radius: 0.25rem; }
            .divide-y > * + * { border-top-width: 1px; border-top-color: #e5e7eb; }
            .divide-gray-200 > * + * { border-top-color: #e5e7eb; }
            .bg-indigo-600 { background-color: #4f46e5; }
            .bg-indigo-700 { background-color: #4338ca; }
            .bg-indigo-50 { background-color: #eef2ff; }
            .bg-indigo-100 { background-color: #e0e7ff; }
            .bg-gray-50 { background-color: #f9fafb; }
            .bg-gray-100 { background-color: #f3f4f6; }
            .bg-gray-200 { background-color: #e5e7eb; }
            .bg-red-50 { background-color: #fef2f2; }
            .bg-red-100 { background-color: #fee2e2; }
            .bg-cyan-50 { background-color: #ecfeff; }
            .bg-cyan-100 { background-color: #cffafe; }
            .bg-green-50 { background-color: #f0fdf4; }
            .bg-green-100 { background-color: #dcfce7; }
            .hover\:bg-indigo-700:hover { background-color: #4338ca; }
            .hover\:bg-indigo-100:hover { background-color: #e0e7ff; }
            .hover\:bg-gray-100:hover { background-color: #f3f4f6; }
            .hover\:bg-gray-200:hover { background-color: #e5e7eb; }
            .hover\:bg-red-100:hover { background-color: #fee2e2; }
            .hover\:bg-cyan-100:hover { background-color: #cffafe; }
            .hover\:bg-gray-50:hover { background-color: #f9fafb; }
            .transition-colors { transition-property: background-color, border-color, color, fill, stroke; transition-timing-function: cubic-bezier(0.4, 0, 0.2, 1); transition-duration: 150ms; }
            .border-transparent { border-color: transparent; }
            .border-indigo-600 { border-color: #4f46e5; }
            .overflow-x-auto { overflow-x: auto; }
            .inline-flex { display: inline-flex; }
            .items-center { align-items: center; }
            .font-mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; }

            /* Pagination */
            .pagination {
                display: flex;
                justify-content: center;
                padding: 0;
                margin: 0;
            }
            .pagination nav {
                display: flex;
                gap: 4px;
            }
            .pagination a, .pagination span {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 6px 12px;
                border-radius: 6px;
                font-size: 14px;
                text-decoration: none;
                transition: all 0.2s;
            }
            .pagination a {
                color: #6b7280;
                background: white;
                border: 1px solid #e5e7eb;
            }
            .pagination a:hover {
                background: #f3f4f6;
                color: #374151;
            }
            .pagination .active span {
                background: #4f46e5;
                color: white;
                border: 1px solid #4f46e5;
            }
        </style>

        @stack('styles')
        
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    </head>
    <body class="font-sans antialiased bg-gray-100">
        <div class="min-h-screen">
            @include('layouts.navigation')

            <main class="py-4">
                @yield('content')
            </main>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

        @stack('scripts')

        <x-pwa-install-banner />
        
        <script>
            function showDeleteConfirm(form) {
                if (!window.Swal) {
                    if (confirm('Are you sure you want to delete this form?')) {
                        form.submit();
                    }
                    return;
                }

                Swal.fire({
                    title: 'Delete this form?',
                    text: 'This action cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc2626',
                    cancelButtonColor: '#64748b',
                    confirmButtonText: 'Yes, delete it',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                });
            }
        </script>
    </body>
</html>
