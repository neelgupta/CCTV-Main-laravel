@php
    $company = $company ?? auth()->user()?->company;
    $letterhead = $letterhead ?? null;
    $currentFileName = $letterhead?->file_path ? basename($letterhead->file_path) : null;
    $currentFileUrl = $currentFileName ? route('settings.letterhead.file', ['v' => $letterhead?->updated_at?->timestamp ?? time()]) : null;
    $margins = [
        'top_margin' => old('top_margin', $letterhead?->top_margin ?? 20),
        'bottom_margin' => old('bottom_margin', $letterhead?->bottom_margin ?? 20),
        'left_margin' => old('left_margin', $letterhead?->left_margin ?? 20),
        'right_margin' => old('right_margin', $letterhead?->right_margin ?? 20),
    ];
    $isEnabled = old('enabled', $letterhead?->enabled ?? true);
@endphp

<div class="grid gap-6 lg:grid-cols-[1.15fr_0.85fr]">
    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="mb-6">
            <h3 class="text-lg font-bold text-slate-900">Letterhead Settings</h3>
            <p class="mt-1 text-sm text-slate-500">The uploaded file must be a PDF up to 10MB. Replacing it will remove the previous file from storage.</p>
        </div>

        <form id="pdf-letterhead-settings-form" method="POST" action="{{ route('settings.letterhead.update') }}" enctype="multipart/form-data" class="space-y-6" novalidate>
            @csrf

            <div class="grid gap-4 md:grid-cols-2">
                <div class="md:col-span-2">
                    <x-input-label for="pdf_letterhead_file" value="Letterhead PDF" />
                    <input
                        id="pdf_letterhead_file"
                        name="letterhead_file"
                        type="file"
                        accept="application/pdf,.pdf"
                        class="mt-1 block w-full rounded-2xl border border-slate-300 bg-white text-sm text-slate-700 shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-cyan-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-cyan-900 focus:border-cyan-400 focus:ring-cyan-400"
                    >
                    <p class="mt-2 text-xs text-slate-500">Only PDF files are allowed. Existing files are replaced when you upload a new one.</p>
                    <x-input-error :messages="$errors->get('letterhead_file')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="pdf_top_margin" value="Top Margin (mm)" />
                    <x-text-input id="pdf_top_margin" name="top_margin" type="number" min="0" max="100" step="1" value="{{ $margins['top_margin'] }}" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('top_margin')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="pdf_bottom_margin" value="Bottom Margin (mm)" />
                    <x-text-input id="pdf_bottom_margin" name="bottom_margin" type="number" min="0" max="100" step="1" value="{{ $margins['bottom_margin'] }}" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('bottom_margin')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="pdf_left_margin" value="Left Margin (mm)" />
                    <x-text-input id="pdf_left_margin" name="left_margin" type="number" min="0" max="100" step="1" value="{{ $margins['left_margin'] }}" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('left_margin')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="pdf_right_margin" value="Right Margin (mm)" />
                    <x-text-input id="pdf_right_margin" name="right_margin" type="number" min="0" max="100" step="1" value="{{ $margins['right_margin'] }}" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('right_margin')" class="mt-2" />
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                <label for="pdf_letterhead_enabled" class="flex items-start gap-3">
                    <input type="hidden" name="enabled" value="0">
                    <input id="pdf_letterhead_enabled" name="enabled" type="checkbox" value="1" class="mt-1 rounded border-slate-300 text-cyan-600 focus:ring-cyan-500" @checked($isEnabled)>
                    <span>
                        <span class="block text-sm font-semibold text-slate-900">Enabled</span>
                        <span class="mt-1 block text-sm text-slate-500">Only one active letterhead should be enabled at a time.</span>
                    </span>
                </label>
                <x-input-error :messages="$errors->get('enabled')" class="mt-2" />
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <x-primary-button type="submit">Save Letterhead</x-primary-button>
                <p class="text-sm text-slate-500">Changes apply immediately after saving.</p>
            </div>
        </form>
    </div>

    <div class="space-y-6">
        <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h3 class="text-lg font-bold text-slate-900">Current File</h3>
            @if($currentFileName)
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Filename</dt>
                        <dd class="font-medium text-slate-900">{{ $currentFileName }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-slate-100 pb-3">
                        <dt class="text-slate-500">Status</dt>
                        <dd>
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $letterhead?->enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700' }}">
                                {{ $letterhead?->enabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </dd>
                    </div>
                    <div class="grid grid-cols-2 gap-3 pt-1">
                        @if(filled($letterhead?->source_filename))
                            <div class="col-span-2 rounded-2xl bg-slate-50 p-3">
                                <div class="text-xs uppercase tracking-wide text-slate-500">Source File</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $letterhead?->source_filename }}</div>
                            </div>
                        @endif
                        @if(filled($letterhead?->page_count))
                            <div class="rounded-2xl bg-slate-50 p-3">
                                <div class="text-xs uppercase tracking-wide text-slate-500">Pages Cached</div>
                                <div class="mt-1 text-base font-semibold text-slate-900">{{ $letterhead?->page_count }}</div>
                            </div>
                        @endif
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <div class="text-xs uppercase tracking-wide text-slate-500">Top</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $letterhead?->top_margin ?? 20 }} mm</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <div class="text-xs uppercase tracking-wide text-slate-500">Bottom</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $letterhead?->bottom_margin ?? 20 }} mm</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <div class="text-xs uppercase tracking-wide text-slate-500">Left</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $letterhead?->left_margin ?? 20 }} mm</div>
                        </div>
                        <div class="rounded-2xl bg-slate-50 p-3">
                            <div class="text-xs uppercase tracking-wide text-slate-500">Right</div>
                            <div class="mt-1 text-base font-semibold text-slate-900">{{ $letterhead?->right_margin ?? 20 }} mm</div>
                        </div>
                    </div>
                    <div class="pt-1">
                        <a href="{{ $currentFileUrl }}" target="_blank" rel="noopener" class="inline-flex items-center rounded-xl border border-cyan-200 bg-cyan-50 px-4 py-2 text-sm font-semibold text-cyan-900 transition hover:bg-cyan-100">
                            Open PDF
                        </a>
                    </div>
                </dl>
            @else
                <p class="mt-4 text-sm text-slate-500">No letterhead PDF has been uploaded yet.</p>
            @endif
        </div>

        @if($currentFileUrl)
            <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-slate-900">PDF Preview</h3>
                </div>
                <iframe src="{{ $currentFileUrl }}" class="h-[720px] w-full" title="Letterhead PDF preview"></iframe>
            </div>
        @endif
    </div>
</div>

@push('scripts')
    <script>
        (function () {
            const form = document.getElementById('pdf-letterhead-settings-form');
            const previewFrame = document.getElementById('letterhead-preview-frame');
            const refreshButton = document.getElementById('letterhead-preview-refresh');
            const fileInput = document.getElementById('pdf_letterhead_file');
            const marginInputs = [
                document.getElementById('pdf_top_margin'),
                document.getElementById('pdf_bottom_margin'),
                document.getElementById('pdf_left_margin'),
                document.getElementById('pdf_right_margin'),
            ].filter(Boolean);
            const previewUrl = @js(route('settings.letterhead.preview'));
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            let previewTimer = null;
            let activeObjectUrl = null;

            const revokePreviewUrl = () => {
                if (activeObjectUrl) {
                    URL.revokeObjectURL(activeObjectUrl);
                    activeObjectUrl = null;
                }
            };

            const updatePreview = async () => {
                if (!form || !previewFrame || !csrfToken) {
                    return;
                }

                const formData = new FormData();
                const file = fileInput?.files?.[0];

                if (file) {
                    formData.append('letterhead_file', file);
                }

                marginInputs.forEach((input) => {
                    if (input && input.value !== '') {
                        formData.append(input.name, input.value);
                    }
                });

                formData.append('_token', csrfToken);

                try {
                    refreshButton && (refreshButton.disabled = true);
                    refreshButton && (refreshButton.textContent = 'Updating...');

                    const response = await fetch(previewUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/pdf',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: formData,
                    });

                    if (!response.ok) {
                        throw new Error('Preview failed');
                    }

                    const blob = await response.blob();
                    revokePreviewUrl();
                    activeObjectUrl = URL.createObjectURL(blob);
                    previewFrame.src = activeObjectUrl;
                } catch (error) {
                    previewFrame.src = previewUrl;
                } finally {
                    refreshButton && (refreshButton.disabled = false);
                    refreshButton && (refreshButton.textContent = 'Refresh Preview');
                }
            };

            const schedulePreview = () => {
                window.clearTimeout(previewTimer);
                previewTimer = window.setTimeout(updatePreview, 350);
            };

            fileInput?.addEventListener('change', schedulePreview);
            marginInputs.forEach((input) => {
                input.addEventListener('input', schedulePreview);
                input.addEventListener('change', schedulePreview);
            });

            refreshButton?.addEventListener('click', updatePreview);

            window.addEventListener('beforeunload', revokePreviewUrl);
        })();
    </script>
@endpush
