@page {
    margin: 6mm;
}

:root {
    --print-page-bg: #eef1f4;
    --print-paper: #ffffff;
    --print-ink: #2f2f2f;
    --print-muted: #666666;
    --print-line: #b9b9b9;
    --print-heavy: #4a4a4a;
    --print-brand: var(--company-accent, #222222);
    --print-summary: #4f46e5;
}

* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

html, body {
    margin: 0;
    padding: 0;
    background: var(--print-page-bg);
    color: var(--print-ink);
    font-family: Arial, Helvetica, sans-serif;
}

.no-print {
    max-width: 980px;
    margin: 16px auto 0;
    padding: 0 12px;
    display: flex;
    gap: 8px;
}

.print-sheet {
    max-width: 980px;
    margin: 12px auto 28px;
    padding: 0 12px;
}

.print-paper {
    width: 100%;
    max-width: 190mm;
    margin: 0 auto;
    background: var(--print-paper);
    border: 1px solid var(--print-line);
    box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
    padding: 8mm 8mm 7mm;
}

.print-shell {
    border: 1px solid var(--print-heavy);
    padding: 5px;
}

.print-brand-logo {
    max-height: 28px;
    max-width: 140px;
    object-fit: contain;
    display: block;
}

.print-company-name {
    margin: 0;
    font-size: 24px;
    line-height: 1.05;
    letter-spacing: 0.02em;
    font-weight: 800;
    text-transform: uppercase;
}

.print-topline {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    align-items: start;
    gap: 10px;
    font-size: 11px;
    margin-bottom: 4px;
    min-height: 18px;
}

.print-table {
    width: 100%;
    border-collapse: collapse;
}

.print-table th,
.print-table td {
    border-right: 1px solid var(--print-line);
    border-bottom: 1px solid var(--print-line);
    padding: 5px 6px;
    font-size: 11px;
    vertical-align: top;
}

.print-table th:last-child,
.print-table td:last-child {
    border-right: 0;
}

.print-table thead th {
    font-size: 11px;
    font-weight: 700;
    text-align: center;
    background: #f7f7f7;
    line-height: 1.2;
}

.print-section-title {
    margin: 0 0 12px;
    padding-left: 10px;
    border-left: 3px solid var(--print-brand);
    font-size: 12px;
    font-weight: 800;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--print-brand);
}

.print-meta-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0;
    border-bottom: 1px solid var(--print-heavy);
}

.print-card {
    padding: 8px 10px 10px;
    min-height: 98px;
    border-bottom: 1px solid var(--print-heavy);
}

.print-card + .print-card {
    border-left: 1px solid var(--print-heavy);
}

.print-footer {
    margin-top: 26px;
    padding-top: 16px;
    border-top: 1px solid var(--print-line);
    text-align: center;
    font-size: 11px;
    color: #94a3b8;
}

@media print {
    body { background: #fff; }
    .no-print { display: none; }
    .print-sheet {
        margin: 0;
        padding: 0;
        max-width: none;
        width: 100%;
    }
    .print-paper {
        box-shadow: none;
        border: 0;
        padding: 0;
        max-width: none;
        width: 100%;
        margin: 0;
    }
    .print-shell {
        width: 100%;
        margin: 0;
        border: 1px solid var(--print-heavy);
    }
}
