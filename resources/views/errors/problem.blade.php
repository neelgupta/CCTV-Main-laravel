@php
    $statusCode = $statusCode ?? 500;
    $title = $title ?? 'Something Went Wrong';
    $description = $description ?? 'We could not complete this request. Please try again in a moment.';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} | {{ $title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        .error-icon {
            width: 4.75rem;
            height: 4.75rem;
            border-radius: 1.25rem;
            display: grid;
            place-items: center;
            margin: 0 auto;
            background: rgba(239, 68, 68, 0.12);
            color: #ef4444;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-2xl border border-slate-200 bg-white px-6 py-8 text-center shadow-lg shadow-slate-200/50">


            <div class="error-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-10 w-10">
                    <path fill-rule="evenodd" d="M9.401 3.003c1.155-2.001 4.043-2.001 5.198 0l7.134 12.37c1.154 2-.29 4.627-2.599 4.627H4.866c-2.31 0-3.753-2.628-2.599-4.628l7.134-12.369ZM12 8a.75.75 0 0 1 .75.75v4.5a.75.75 0 0 1-1.5 0v-4.5A.75.75 0 0 1 12 8Zm0 10a1.125 1.125 0 1 0 0-2.25A1.125 1.125 0 0 0 12 18Z" clip-rule="evenodd" />
                </svg>
            </div>

            <h1 class="mt-5 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">
                {{ $title }}
            </h1>
            <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base">
                {{ $description }}
            </p>

            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                <button type="button" onclick="location.reload()" class="inline-flex items-center justify-center rounded-lg bg-red-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-red-600">
                    Reload Page
                </button>
                <a href="{{ route('dashboard') }}" class="inline-flex items-center justify-center rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Go to Dashboard
                </a>
            </div>

            <p class="mt-6 text-xs font-medium uppercase tracking-[0.18em] text-slate-400">
                Error {{ $statusCode }}
            </p>
        </section>
    </main>
</body>
</html>
