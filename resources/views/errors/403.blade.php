@php($title = 'Access Denied')
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} | {{ config('app.name') }}</title>
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --border: #e2e8f0;
            --text: #0f172a;
            --muted: #64748b;
            --accent: #4f46e5;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            padding: 32px;
        }
        .card {
            width: 100%;
            max-width: 540px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 15px 40px -18px rgba(15, 23, 42, 0.28);
            padding: 32px;
            text-align: center;
        }
        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            border-radius: 16px;
            background: rgba(79, 70, 229, 0.08);
            display: grid;
            place-items: center;
            color: var(--accent);
        }
        .icon svg { width: 28px; height: 28px; }
        h1 {
            margin: 0 0 10px;
            font-size: 22px;
            letter-spacing: -0.01em;
        }
        p {
            margin: 0;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.6;
        }
        .actions {
            margin-top: 22px;
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        a, button {
            text-decoration: none;
            border: none;
            border-radius: 10px;
            padding: 11px 16px;
            font-weight: 700;
            letter-spacing: 0.01em;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease, opacity 0.15s ease;
        }
        .btn-primary {
            background: var(--accent);
            color: white;
            box-shadow: 0 12px 25px -14px rgba(79, 70, 229, 0.75);
        }
        .btn-secondary {
            background: #e2e8f0;
            color: var(--text);
        }
        a:hover, button:hover { transform: translateY(-1px); }
        a:active, button:active { transform: translateY(0); opacity: 0.9; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 3h.01M10.29 3.86L4.12 7.64c-.68.4-1.12 1.14-1.12 1.94v4.84c0 .8.44 1.54 1.12 1.94l6.17 3.78c.66.4 1.5.4 2.16 0l6.17-3.78c.68-.4 1.12-1.14 1.12-1.94V9.58c0-.8-.44-1.54-1.12-1.94l-6.17-3.78a2.28 2.28 0 00-2.16 0z" />
            </svg>
        </div>
        <h1>403 — Insufficient Permissions</h1>
        <p>You don’t have access to this page. If this seems incorrect, please contact your administrator.</p>
        <div class="actions">
            <a class="btn-primary" href="{{ url('/') }}">Go to Home</a>
            <button class="btn-secondary" type="button" onclick="history.back()">Go Back</button>
        </div>
    </div>
</body>
</html>
