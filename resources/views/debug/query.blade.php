<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950 text-slate-100">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Developer Console & Query Explorer</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"
        rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }

        code,
        pre {
            font-family: 'JetBrains Mono', monospace;
        }
    </style>
</head>

<body class="h-full flex flex-col antialiased">

    <!-- Header Section -->
    <header class="border-b border-slate-800 bg-slate-900/60 backdrop-blur sticky top-0 z-50 px-6 py-4">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div
                    class="flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-500/10 text-indigo-400 ring-1 ring-indigo-500/20">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-lg font-bold text-slate-100 tracking-tight">CCTV Developer Console</h1>
                    <p class="text-xs text-slate-400">Database Inspector, Log Viewer & DQL Execution Portal</p>
                </div>
            </div>

            <div class="flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-1 text-xs font-medium text-emerald-400 ring-1 ring-inset ring-emerald-500/20">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    Secure Key Verified
                </span>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="flex-1 max-w-7xl w-full mx-auto p-6 space-y-6">

        <!-- Error Alert -->
        @if ($error)
            <div
                class="rounded-xl bg-rose-500/10 p-4 border border-rose-500/20 text-sm text-rose-400 flex items-start gap-3">
                <svg class="h-5 w-5 shrink-0 mt-0.5 text-rose-500" fill="none" stroke="currentColor"
                    viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                    </path>
                </svg>
                <div>
                    <span class="font-semibold block mb-1">Execution Error Occurred:</span>
                    <code
                        class="bg-rose-950/40 px-2 py-0.5 rounded border border-rose-500/10 block mt-2 text-xs overflow-x-auto whitespace-pre-wrap">{{ $error }}</code>
                </div>
            </div>
        @endif

        <!-- Navigation Tabs -->
        <div class="flex border-b border-slate-800">
            <nav class="-mb-px flex space-x-6" aria-label="Tabs">
                <a href="{{ route('debug.query', ['key' => $dbKey, 'action' => 'table']) }}"
                    class="border-b-2 py-4 px-1 text-sm font-semibold transition {{ $action === 'table' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-700' }}">
                    Table Inspector
                </a>

                <a href="{{ route('debug.logs', ['key' => $logKey]) }}"
                    class="border-b-2 py-4 px-1 text-sm font-semibold transition {{ $action === 'logs' ? 'border-indigo-500 text-indigo-400' : 'border-transparent text-slate-400 hover:text-slate-300 hover:border-slate-700' }}">
                    Server Log Viewer
                </a>
            </nav>
        </div>

        <!-- Forms & Config Box -->
        <div class="rounded-2xl border border-slate-800 bg-slate-900/40 p-6 backdrop-blur">
            @if ($action === 'table')
                <!-- Table Inspector Form -->
                <form method="GET" action="{{ route('debug.query', ['key' => $dbKey]) }}" id="table-form"
                    autocomplete="off" class="space-y-6">
                    <input type="hidden" name="action" value="table">

                    {{-- Retain current dynamic filter parameters --}}
                    @foreach (request()->except(['action', 'table', 'limit', 'sort_by', 'sort_dir', 'page', 'format', 'json']) as $k => $v)
                        <input type="hidden" name="{{ $k }}" value="{{ $v }}"
                            class="dynamic-filter-input">
                    @endforeach

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                        <div class="md:col-span-1">
                            <label
                                class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Model
                                Class / Table</label>
                            <input type="text" name="table" value="{{ request('table') }}"
                                class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono"
                                required>
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Limit
                                Rows</label>
                            <input type="number" name="limit" value="{{ request('limit', 50) }}" min="1"
                                max="1000"
                                class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Sort
                                By</label>
                            @if (!empty($columns))
                                <select name="sort_by"
                                    class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono">
                                    @foreach ($columns as $col)
                                        <option value="{{ $col }}"
                                            {{ request('sort_by', 'id') === $col ? 'selected' : '' }}>
                                            {{ $col }}</option>
                                    @endforeach
                                </select>
                            @else
                                <input type="text" name="sort_by" value="{{ request('sort_by', 'id') }}"
                                    class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 font-mono">
                            @endif
                        </div>
                        <div>
                            <label
                                class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Direction</label>
                            <select name="sort_dir"
                                class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                <option value="desc" {{ request('sort_dir', 'desc') === 'desc' ? 'selected' : '' }}>
                                    DESC (Newest)</option>
                                <option value="asc" {{ request('sort_dir', 'desc') === 'asc' ? 'selected' : '' }}>
                                    ASC (Oldest)</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="submit"
                            class="rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition shadow">
                            Fetch Table Data
                        </button>
                        <a href="{{ route('debug.query', ['key' => $dbKey, 'action' => 'table']) }}"
                            class="rounded-xl border border-slate-700 bg-slate-950 px-5 py-2.5 text-sm font-semibold text-slate-300 hover:bg-slate-900 transition">
                            Reset Filters
                        </a>
                    </div>
                </form>
            @endif



            @if ($action === 'logs')
                <!-- Server Log Viewer Form -->
                <form method="GET" action="{{ route('debug.logs', ['key' => $logKey]) }}"
                    class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Select
                            Log File</label>
                        <select name="file"
                            class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            @foreach ($logFiles as $f)
                                <option value="{{ $f['name'] }}"
                                    {{ $selectedFile === $f['name'] ? 'selected' : '' }}>
                                    {{ $f['name'] }} ({{ number_format($f['size'] / 1024, 2) }} KB)
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Tail
                            Lines</label>
                        <input type="number" name="lines" value="{{ request('lines', 100) }}" step="50"
                            min="50" max="2000"
                            class="block w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-slate-200 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                    </div>
                    <div>
                        <button type="submit"
                            class="w-full rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition shadow">
                            Fetch Latest Logs
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <!-- Results Display Panel -->
        @if ($action === 'logs' && !empty($logLines))
            <!-- Logs Results -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-200">
                        Active Log File: <code
                            class="text-xs text-indigo-400 bg-indigo-500/10 border border-indigo-500/20 px-2 py-0.5 rounded">{{ $selectedFile }}</code>
                    </h3>
                </div>

                @php
                    $parsedLines = [];
                    $currentBlock = null;

                    foreach ($logLines as $line) {
                        $lineStr = trim($line);
                        if (empty($lineStr)) {
                            continue;
                        }

                        if (
                            preg_match(
                                '/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]\s+([a-zA-Z0-9_.-]+)\.([A-Z]+):\s+(.*)$/',
                                $lineStr,
                                $matches,
                            )
                        ) {
                            if ($currentBlock) {
                                $parsedLines[] = $currentBlock;
                            }

                            $timestamp = $matches[1];
                            $env = $matches[2];
                            $level = strtoupper($matches[3]);
                            $rest = $matches[4];

                            $jsonPayload = null;
                            $message = $rest;
                            if (preg_match('/^(.*?)\s*(\{.*\}|\[.*\])\s*$/', $rest, $subMatches)) {
                                $potentialMessage = trim($subMatches[1]);
                                $potentialJson = trim($subMatches[2]);
                                $decoded = json_decode($potentialJson, true);
                                if (json_last_error() === JSON_ERROR_NONE) {
                                    $jsonPayload = $decoded;
                                    $message = $potentialMessage ?: 'JSON Parameters';
                                }
                            }

                            $currentBlock = [
                                'type' => 'entry',
                                'timestamp' => $timestamp,
                                'env' => $env,
                                'level' => $level,
                                'message' => $message,
                                'json' => $jsonPayload,
                                'trace' => [],
                            ];
                        } else {
                            if ($currentBlock) {
                                $currentBlock['trace'][] = $line;
                            } else {
                                $lastIdx = count($parsedLines) - 1;
                                if ($lastIdx >= 0 && $parsedLines[$lastIdx]['type'] === 'raw') {
                                    $parsedLines[$lastIdx]['content'] .= "\n" . $line;
                                } else {
                                    $parsedLines[] = [
                                        'type' => 'raw',
                                        'content' => $line,
                                    ];
                                }
                            }
                        }
                    }
                    if ($currentBlock) {
                        $parsedLines[] = $currentBlock;
                    }
                    $parsedLines = array_reverse($parsedLines);
                @endphp

                <div class="space-y-3">
                    @foreach ($parsedLines as $index => $block)
                        @if ($block['type'] === 'entry')
                            <div
                                class="rounded-2xl border border-slate-800 bg-slate-900/30 p-5 space-y-3 hover:border-slate-700 transition">
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="text-slate-400 font-mono">[{{ $block['timestamp'] }}]</span>
                                    <span
                                        class="bg-slate-800 text-slate-400 px-2 py-0.5 rounded font-mono">{{ $block['env'] }}</span>

                                    @php
                                        $levelColors = match (strtolower($block['level'])) {
                                            'info' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
                                            'warning' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                            'error',
                                            'critical',
                                            'alert',
                                            'emergency'
                                                => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                                            default => 'bg-purple-500/10 text-purple-400 border-purple-500/20',
                                        };
                                    @endphp
                                    <span
                                        class="px-2 py-0.5 rounded border text-[10px] font-bold uppercase tracking-wider {{ $levelColors }}">
                                        {{ $block['level'] }}
                                    </span>
                                </div>
                                <div class="text-sm font-medium text-slate-200 whitespace-pre-wrap leading-relaxed">
                                    {{ $block['message'] }}
                                </div>

                                @if (!empty($block['json']))
                                    <div
                                        class="rounded-xl border border-slate-800 bg-slate-950 p-4 overflow-x-auto text-xs text-indigo-300">
                                        <pre class="leading-relaxed">{{ json_encode($block['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                    </div>
                                @endif

                                @if (!empty($block['trace']))
                                    <div>
                                        <button onclick="toggleTrace(this)"
                                            class="inline-flex items-center gap-1 text-xs text-indigo-400 hover:text-indigo-300 transition font-semibold">
                                            Show Stack Trace (+)
                                        </button>
                                        <div
                                            class="hidden mt-3 rounded-xl border border-slate-800 bg-slate-950 p-4 text-[11px] text-slate-400 leading-relaxed overflow-x-auto max-h-96 whitespace-pre">
                                            {{ implode("\n", $block['trace']) }}
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div
                                class="rounded-xl border border-slate-800 bg-slate-950 p-4 text-xs font-mono text-slate-400 whitespace-pre-wrap leading-relaxed">
                                {{ $block['content'] }}
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @elseif(!empty($data))
            <!-- Database Results Table -->
            @php
                $totalPages = ceil($total / $limit);
            @endphp

            <div class="space-y-4">
                <div
                    class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-slate-900/40 p-4 border border-slate-800 rounded-2xl">
                    <div class="text-sm text-slate-400">
                        Showing <strong class="text-slate-200">{{ count($data) }}</strong> of <strong
                            class="text-slate-200">{{ number_format($total) }}</strong> records
                        (Page <strong class="text-slate-200">{{ $page }}</strong> of <strong
                            class="text-slate-200">{{ $totalPages ?: 1 }}</strong>)
                    </div>

                    <div class="flex items-center gap-2">
                        @if ($page > 1)
                            <a href="{{ request()->url() . '?' . http_build_query(array_merge(request()->all(), ['page' => $page - 1])) }}"
                                class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-900 transition">
                                &larr; Prev
                            </a>
                        @else
                            <span
                                class="rounded-lg border border-slate-800 bg-slate-950 px-3 py-1.5 text-xs font-semibold text-slate-600 cursor-not-allowed">
                                &larr; Prev
                            </span>
                        @endif

                        @if ($page < $totalPages)
                            <a href="{{ request()->url() . '?' . http_build_query(array_merge(request()->all(), ['page' => $page + 1])) }}"
                                class="rounded-lg border border-slate-700 bg-slate-950 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-900 transition">
                                Next &rarr;
                            </a>
                        @else
                            <span
                                class="rounded-lg border border-slate-800 bg-slate-950 px-3 py-1.5 text-xs font-semibold text-slate-600 cursor-not-allowed">
                                Next &rarr;
                            </span>
                        @endif
                    </div>
                </div>

                <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900/20 backdrop-blur">
                    <table class="min-w-full divide-y divide-slate-800 text-left text-xs text-slate-300">
                        <thead
                            class="bg-slate-900/60 font-semibold text-slate-200 uppercase tracking-wider text-[10px]">
                            <tr>
                                @foreach ($columns as $col)
                                    <th scope="col" class="px-4 py-3 border-r border-slate-800 last:border-r-0">
                                        {{ $col }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 bg-transparent font-mono">
                            @foreach ($data as $row)
                                <tr class="hover:bg-slate-800/20 transition">
                                    @foreach ($columns as $col)
                                        @php
                                            $val = ((array) $row)[$col] ?? null;
                                        @endphp
                                        <td class="px-4 py-3 border-r border-slate-800 last:border-r-0 max-w-xs truncate"
                                            title="{{ is_scalar($val) ? $val : '' }}">
                                            @if (is_null($val))
                                                <span class="text-slate-600 italic">NULL</span>
                                            @elseif(is_scalar($val))
                                                {{ $val }}
                                            @else
                                                <code
                                                    class="text-indigo-400 text-[10px]">{{ json_encode($val) }}</code>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @elseif(request('table'))
            <div class="rounded-2xl border border-slate-850 bg-slate-900/10 p-6 text-center text-slate-500 text-sm">
                No matching database query records found.
            </div>
        @endif

    </main>

    <!-- Footer -->
    <footer class="border-t border-slate-800 py-6 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} CCTV Workshop Console. For Authorized Developer Debugging Only.
    </footer>

    <script>
        function toggleTrace(button) {
            const traceDiv = button.nextElementSibling;
            if (traceDiv.classList.contains('hidden')) {
                traceDiv.classList.remove('hidden');
                button.innerText = 'Hide Stack Trace (-)';
            } else {
                traceDiv.classList.add('hidden');
                button.innerText = 'Show Stack Trace (+)';
            }
        }
    </script>
</body>

</html>
