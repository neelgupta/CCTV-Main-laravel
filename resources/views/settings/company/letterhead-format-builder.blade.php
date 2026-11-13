<x-app-layout>
    <x-slot name="header">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="text-2xl font-semibold text-slate-950 leading-tight">Letterhead Format Builder</h2>
            <p class="mt-1 text-sm text-slate-500">Place fields on your uploaded letterhead so invoices &amp; quotes print using your own format.</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl px-4 py-6">
        @if(!$company?->letterhead_url)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-800">
                No letterhead file has been uploaded yet. Go to
                <a href="{{ route('settings.company.index', ['tab' => 'print-branding']) }}" class="font-semibold underline">Company Settings &rarr; Print &amp; Branding</a>
                and upload one first (PNG, JPG, or PDF), then come back here.
            </div>
        @else
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-[280px_1fr]">
                <div class="rounded-2xl border border-slate-200 bg-white p-4">
                    <h3 class="text-sm font-semibold text-slate-800">Fields</h3>
                    <p class="mt-1 text-xs text-slate-500">Click a field to drop it onto the letterhead, then drag it into place.</p>
                    <div class="mt-3 flex flex-col gap-2" id="lh-palette">
                        @foreach($shortcodes as $key => $meta)
                            <button type="button"
                                    class="lh-palette-btn rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:border-indigo-300 hover:bg-indigo-50"
                                    data-shortcode="{{ $key }}"
                                    data-type="{{ $meta['type'] }}"
                                    data-label="{{ $meta['label'] }}">
                                {{ $meta['label'] }}
                            </button>
                        @endforeach
                    </div>

                    <div class="mt-5 border-t border-slate-100 pt-4">
                        <button type="button" id="lh-save-btn" class="w-full rounded-full bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">
                            Save Format
                        </button>
                        <p id="lh-save-status" class="mt-2 text-xs text-slate-500"></p>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-slate-100 p-4">
                    <div id="lh-canvas" class="relative mx-auto w-full max-w-[720px] select-none rounded-lg border border-slate-300 bg-white shadow-sm" style="aspect-ratio: 210 / 297;">
                        <img src="{{ $company->letterhead_url }}" alt="Letterhead preview" class="pointer-events-none absolute inset-0 h-full w-full rounded-lg object-fill">
                        <div id="lh-fields-layer" class="absolute inset-0"></div>
                    </div>
                    <p class="mt-3 text-center text-xs text-slate-500">Drag placed fields to reposition. Drag the bottom-right corner of the items table to resize it.</p>
                </div>
            </div>
        @endif
    </div>

    @if($company?->letterhead_url)
        <script>
        (function () {
            const canvas = document.getElementById('lh-canvas');
            const layer = document.getElementById('lh-fields-layer');
            const saveBtn = document.getElementById('lh-save-btn');
            const statusEl = document.getElementById('lh-save-status');
            const labels = @json(collect($shortcodes)->map(fn ($m) => $m['label'])->all());
            const types = @json(collect($shortcodes)->map(fn ($m) => $m['type'])->all());
            const existing = @json(array_values($fieldPositions ?? []));

            let fields = {}; // shortcode -> { x, y, width, height }

            function makeFieldEl(shortcode, pos) {
                const type = types[shortcode] || 'point';
                const el = document.createElement('div');
                el.className = 'lh-field absolute cursor-move rounded border-2 border-indigo-500 bg-indigo-500/10 px-2 py-1 text-[10px] font-semibold text-indigo-800';
                el.style.left = pos.x_pct + '%';
                el.style.top = pos.y_pct + '%';
                if (type === 'box') {
                    el.style.width = (pos.width_pct || 40) + '%';
                    el.style.height = (pos.height_pct || 20) + '%';
                }
                el.dataset.shortcode = shortcode;
                el.textContent = labels[shortcode] || shortcode;

                if (type === 'box') {
                    const handle = document.createElement('div');
                    handle.className = 'lh-resize-handle absolute bottom-0 right-0 h-3 w-3 cursor-nwse-resize bg-indigo-600';
                    el.appendChild(handle);
                }

                layer.appendChild(el);
                fields[shortcode] = {
                    x_pct: pos.x_pct,
                    y_pct: pos.y_pct,
                    width_pct: type === 'box' ? (pos.width_pct || 40) : null,
                    height_pct: type === 'box' ? (pos.height_pct || 20) : null,
                };

                wireDrag(el, shortcode);
            }

            function wireDrag(el, shortcode) {
                let dragging = false;
                let resizing = false;
                let startX, startY, startLeft, startTop, startWidth, startHeight;

                el.addEventListener('mousedown', function (e) {
                    if (e.target.classList.contains('lh-resize-handle')) {
                        resizing = true;
                    } else {
                        dragging = true;
                    }
                    const rect = canvas.getBoundingClientRect();
                    startX = e.clientX;
                    startY = e.clientY;
                    startLeft = parseFloat(el.style.left);
                    startTop = parseFloat(el.style.top);
                    startWidth = parseFloat(el.style.width) || 0;
                    startHeight = parseFloat(el.style.height) || 0;
                    e.preventDefault();
                    e.stopPropagation();
                });

                document.addEventListener('mousemove', function (e) {
                    if (!dragging && !resizing) return;
                    const rect = canvas.getBoundingClientRect();
                    const dxPct = ((e.clientX - startX) / rect.width) * 100;
                    const dyPct = ((e.clientY - startY) / rect.height) * 100;

                    if (dragging) {
                        const newLeft = Math.max(0, Math.min(100 - (startWidth || 0), startLeft + dxPct));
                        const newTop = Math.max(0, Math.min(100 - (startHeight || 0), startTop + dyPct));
                        el.style.left = newLeft + '%';
                        el.style.top = newTop + '%';
                        fields[shortcode].x_pct = Math.round(newLeft * 100) / 100;
                        fields[shortcode].y_pct = Math.round(newTop * 100) / 100;
                    } else if (resizing) {
                        const newWidth = Math.max(5, Math.min(100 - startLeft, startWidth + dxPct));
                        const newHeight = Math.max(5, Math.min(100 - startTop, startHeight + dyPct));
                        el.style.width = newWidth + '%';
                        el.style.height = newHeight + '%';
                        fields[shortcode].width_pct = Math.round(newWidth * 100) / 100;
                        fields[shortcode].height_pct = Math.round(newHeight * 100) / 100;
                    }
                });

                document.addEventListener('mouseup', function () {
                    dragging = false;
                    resizing = false;
                });
            }

            // Load previously saved positions.
            existing.forEach(function (pos) {
                if (pos && pos.shortcode) {
                    makeFieldEl(pos.shortcode, pos);
                }
            });

            // Palette clicks add a field at a default position (or move it if already placed).
            document.querySelectorAll('.lh-palette-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const shortcode = btn.dataset.shortcode;
                    if (fields[shortcode]) {
                        return; // already placed — drag the existing one
                    }
                    makeFieldEl(shortcode, { x_pct: 30, y_pct: 40, width_pct: 40, height_pct: 20 });
                });
            });

            saveBtn.addEventListener('click', function () {
                const payload = Object.keys(fields).map(function (shortcode) {
                    const f = fields[shortcode];
                    const entry = { shortcode: shortcode, x_pct: f.x_pct, y_pct: f.y_pct };
                    if (f.width_pct !== null) entry.width_pct = f.width_pct;
                    if (f.height_pct !== null) entry.height_pct = f.height_pct;
                    return entry;
                });

                statusEl.textContent = 'Saving...';
                fetch('{{ route('settings.company.letterhead-field-positions.update') }}', {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ field_positions: payload }),
                }).then(function (res) {
                    if (!res.ok) throw new Error('Save failed');
                    statusEl.textContent = 'Saved.';
                }).catch(function () {
                    statusEl.textContent = 'Could not save — please try again.';
                });
            });
        })();
        </script>
    @endif
</x-app-layout>
