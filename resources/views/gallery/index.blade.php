<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 leading-tight">Company Gallery</h2>
                <p class="mt-1 text-sm text-gray-500">All media uploaded by your company across modules.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="w-full sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @forelse($mediaFiles as $media)
                    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        <div class="flex h-44 items-center justify-center bg-gray-50">
                            @if($media->is_image)
                                <img src="{{ $media->url }}" alt="{{ $media->original_name }}" class="h-full w-full object-cover">
                            @else
                                <div class="text-center">
                                    <div class="text-4xl">File</div>
                                    <div class="mt-2 text-xs text-gray-500">{{ strtoupper(pathinfo($media->original_name, PATHINFO_EXTENSION)) }}</div>
                                </div>
                            @endif
                        </div>
                        <div class="p-4">
                            <div class="truncate text-sm font-semibold text-gray-900">{{ $media->title ?? $media->original_name }}</div>
                            <div class="mt-1 text-xs text-gray-500">{{ $media->module ?? 'General' }} · {{ $media->human_size }}</div>

                            {{-- Storage path visibility --}}
                            @php
                                $storageBadge = $media->isOnDrive()
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                    : 'bg-slate-100 text-slate-600 ring-slate-200';
                                $storageTitle = collect([
                                    'Storage: '.$media->storage_provider_label,
                                    $media->drive_account_email ? 'Account: '.$media->drive_account_email : null,
                                    $media->storage_path_label ? 'Path: '.$media->storage_path_label : null,
                                    $media->uploaded_at ? 'Uploaded: '.$media->uploaded_at->format('d M Y, g:i A') : null,
                                ])->filter()->implode("\n");
                            @endphp
                            <div class="mt-2" title="{{ $storageTitle }}">
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $storageBadge }}">
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H7a2 2 0 01-2-2V8z"/></svg>
                                    {{ $media->storage_provider_label }}
                                </span>
                                @if($media->storage_path_label)
                                    <div class="mt-1 truncate font-mono text-[11px] text-slate-400">{{ $media->storage_path_label }}</div>
                                @endif
                            </div>
                            <div class="mt-3 flex items-center justify-between gap-2">
                                <a href="{{ $media->url }}" target="_blank" rel="noopener" class="text-xs font-medium text-indigo-600 hover:underline">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z" />
                                                <circle cx="12" cy="12" r="3" />
                                    </svg>
                                </a>
                                <form method="POST" action="{{ route('settings.gallery.destroy', $media) }}" class="js-gallery-delete-form">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-medium text-red-600 hover:underline js-gallery-delete">
                                        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" fill="currentColor"><path d="M262.2 48C248.9 48 236.9 56.3 232.2 68.8L216 112L120 112C106.7 112 96 122.7 96 136C96 149.3 106.7 160 120 160L520 160C533.3 160 544 149.3 544 136C544 122.7 533.3 112 520 112L424 112L407.8 68.8C403.1 56.3 391.2 48 377.8 48L262.2 48zM128 208L128 512C128 547.3 156.7 576 192 576L448 576C483.3 576 512 547.3 512 512L512 208L464 208L464 512C464 520.8 456.8 528 448 528L192 528C183.2 528 176 520.8 176 512L176 208L128 208zM288 280C288 266.7 277.3 256 264 256C250.7 256 240 266.7 240 280L240 456C240 469.3 250.7 480 264 480C277.3 480 288 469.3 288 456L288 280zM400 280C400 266.7 389.3 256 376 256C362.7 256 352 266.7 352 280L352 456C352 469.3 362.7 480 376 480C389.3 480 400 469.3 400 456L400 280z"/></svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500">
                        No media files found yet.
                    </div>
                @endforelse
            </div>

            <div class="mt-6">
                {{ $mediaFiles->links() }}
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.js-gallery-delete-form').forEach(function (form) {
                    form.addEventListener('submit', function (event) {
                        event.preventDefault();

                        const submitButton = form.querySelector('.js-gallery-delete');

                        if (!window.Swal) {
                            if (confirm('Delete this file from storage and gallery?')) {
                                form.submit();
                            }
                            return;
                        }

                        Swal.fire({
                            title: 'Delete file?',
                            text: 'This will remove the file from storage and hide it from the gallery.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#dc2626',
                            cancelButtonColor: '#6b7280',
                            confirmButtonText: 'Delete',
                            cancelButtonText: 'Cancel',
                        }).then((result) => {
                            if (result.isConfirmed) {
                                if (submitButton) {
                                    submitButton.disabled = true;
                                }
                                form.submit();
                            }
                        });
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
