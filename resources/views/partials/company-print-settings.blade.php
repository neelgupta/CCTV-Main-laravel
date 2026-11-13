@php
    $company = $company ?? auth()->user()?->company;
    $printSettings = $company?->effective_print_settings ?? [];
    $accentColor = $printSettings['accent_color'] ?? '#4f46e5';
    $useLetterhead = (bool) ($printSettings['use_letterhead'] ?? true);
    $pdfMargins = is_array($pdfMargins ?? null) ? $pdfMargins : [];

    $topMargin = (int) ($pdfMargins['top_margin'] ?? $printSettings['page_margin_top'] ?? 20);
    $rightMargin = (int) ($pdfMargins['right_margin'] ?? $printSettings['page_margin_right'] ?? 20);
    $bottomMargin = (int) ($pdfMargins['bottom_margin'] ?? $printSettings['page_margin_bottom'] ?? 20);
    $leftMargin = (int) ($pdfMargins['left_margin'] ?? $printSettings['page_margin_left'] ?? 20);
    $letterheadTitle = $printSettings['letterhead_title'] ?? ($company?->name ?? 'Workshop Platform');
    $letterheadSubtitle = $printSettings['letterhead_subtitle'] ?? 'Official Document';
    $letterheadUrl = $company?->letterhead_url;
    $fieldPositions = collect(is_array($printSettings['field_positions'] ?? null) ? $printSettings['field_positions'] : [])
        ->filter(fn ($f) => is_array($f) && filled($f['shortcode'] ?? null));
@endphp

@if($company?->enable_print_settings)
    @page {
        margin: 0;
    }

    .paper,
    .page-container,
    .container {
        box-sizing: border-box !important;
        width: 100% !important;
        max-width: none !important;
        margin: 0 !important;
        padding: {{ $topMargin }}mm {{ $rightMargin }}mm {{ $bottomMargin }}mm {{ $leftMargin }}mm !important;
        box-shadow: none !important;
        border-radius: 0 !important;
    }

    :root {
        --company-accent: {{ $accentColor }};
        --company-accent-soft: color-mix(in srgb, {{ $accentColor }} 14%, white);
    }

    .company-letterhead,
    .company-letterhead * {
        display: {{ $useLetterhead ? 'block' : 'none' }};
    }

    .print-brand-header,
    .print-brand-header * {
        display: none !important;
    }

    .print-header-grid {
        display: block !important;
    }

    .print-header-trim {
        border-bottom: none !important;
        padding-bottom: 0 !important;
        margin-bottom: 14px !important;
        justify-content: flex-end !important;
    }

    .print-header-fill {
        width: 100% !important;
        text-align: left !important;
    }

    @if($useLetterhead && $letterheadUrl)
        .paper,
        .page-container,
        .container {
            background-image: url('{{ $letterheadUrl }}');
            background-size: 100% 100%;
            background-repeat: no-repeat;
            background-position: top left;
        }
    @endif

    @if($useLetterhead && $letterheadUrl && $fieldPositions->isNotEmpty())
        {{-- Exact letterhead format: fields are placed at admin-defined coordinates
             instead of the default uniform-margin flow layout. Coordinates are
             percentages of the A4 content box (277mm tall after the 10mm @page margin). --}}
        .paper,
        .page-container,
        .container {
            position: relative !important;
            height: 277mm !important;
            overflow: hidden !important;
        }

        [data-lh] {
            position: absolute !important;
        }

        @foreach($fieldPositions as $field)
            [data-lh="{{ $field['shortcode'] }}"] {
                left: {{ (float) ($field['x_pct'] ?? 0) }}% !important;
                top: {{ (float) ($field['y_pct'] ?? 0) }}% !important;
                @if(isset($field['width_pct']))
                    width: {{ (float) $field['width_pct'] }}% !important;
                @endif
                @if(isset($field['height_pct']))
                    height: {{ (float) $field['height_pct'] }}% !important;
                    overflow: auto !important;
                @endif
            }
        @endforeach
    @endif
@endif
