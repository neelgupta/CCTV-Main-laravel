<x-app-layout>
    @php
        $backupCount = (int) ($stats['backup_count'] ?? 0);
        $latestBackupTime = $stats['latest_backup_time_human'] ?? 'No backups yet';
        $storageUsed = $stats['storage_used_human'] ?? '0 B';
        $googleDriveBackup = $googleDriveBackup ?? [];
        $googleDriveHistory = collect($googleDriveBackup['history'] ?? []);
        $googleDriveConnected = (bool) ($googleDriveBackup['connected'] ?? false);
        $googleDriveFolderId = (string) ($googleDriveBackup['folder_id'] ?? '');
        $googleDriveLatestFile = (string) ($googleDriveBackup['latest_backup_file'] ?? 'No backups yet');
        $googleDriveLastBackupTime = (string) ($googleDriveBackup['last_backup_time'] ?? 'No backups yet');
        $googleDriveLastBackupStatus = (string) ($googleDriveBackup['last_backup_status'] ?? 'Not Run');
        $googleDriveNextScheduledBackup = (string) ($googleDriveBackup['next_scheduled_backup'] ?? '02:00 AM daily');
        $googleDriveConnectionMessage = (string) ($googleDriveBackup['connection_message'] ?? '');
    @endphp

    <x-slot name="header">
        <div class="rounded-[28px] border border-slate-200 bg-white px-6 py-6 shadow-sm">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h2 class="text-2xl font-bold text-slate-950">Backup Settings</h2>
                    <p class="mt-1 text-sm text-slate-500">Run Google Drive backups, review history, and keep the legacy local archive available.</p>
                </div>

                <div class="flex flex-col gap-3 sm:flex-row">
                    <form id="create-backup-form" method="POST" action="{{ route('super-admin.database-backups.store') }}" class="shrink-0">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50"
                        >
                            Create Local Backup
                        </button>
                    </form>

                    <form method="POST" action="{{ route('super-admin.database-backups.google-drive.store') }}" class="shrink-0" data-swal-confirm="Create a new MySQL backup and upload it to Google Drive now?" data-swal-title="Google Drive Backup">
                        @csrf
                        <button
                            type="submit"
                            class="inline-flex items-center justify-center rounded-2xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-950/10 transition hover:bg-slate-800"
                        >
                            Run Google Drive Backup
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </x-slot>

    @if(session('success'))
        <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="mb-6 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
            {{ session('error') }}
        </div>
    @endif

    <section class="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Google Drive Status</div>
            <div class="mt-3 text-3xl font-bold {{ $googleDriveConnected ? 'text-emerald-600' : 'text-rose-600' }}">
                {{ $googleDriveConnected ? 'Connected' : 'Not Connected' }}
            </div>
            <div class="mt-2 text-sm text-slate-500">{{ $googleDriveConnectionMessage }}</div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Google Drive Folder ID</div>
            <div class="mt-3 break-all text-2xl font-bold text-slate-950">{{ $googleDriveFolderId ?: 'Not configured' }}</div>
            <div class="mt-2 text-sm text-slate-500">Backups are uploaded into the configured Drive folder.</div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Next Scheduled Backup</div>
            <div class="mt-3 text-2xl font-bold text-slate-950">{{ $googleDriveNextScheduledBackup }}</div>
            <div class="mt-2 text-sm text-slate-500">Scheduled daily at 2:00 AM.</div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Last Backup Time</div>
            <div class="mt-3 text-2xl font-bold text-slate-950">{{ $googleDriveLastBackupTime }}</div>
            <div class="mt-2 text-sm text-slate-500">Most recent Google Drive backup run.</div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Last Backup Status</div>
            <div class="mt-3 text-3xl font-bold text-slate-950">{{ $googleDriveLastBackupStatus }}</div>
            <div class="mt-2 text-sm text-slate-500">Latest recorded upload result.</div>
        </div>

        <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
            <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Latest Backup File</div>
            <div class="mt-3 break-all text-lg font-bold text-slate-950">{{ $googleDriveLatestFile }}</div>
            <div class="mt-2 text-sm text-slate-500">Stored as <code>database_YYYY_MM_DD_HH_MM_SS.sql.zip</code>.</div>
        </div>
    </section>

    <section class="mb-6 rounded-[28px] border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 class="text-xl font-bold text-slate-950">Google Drive Backup History</h3>
                <p class="text-sm text-slate-500">Newest backup first. Older backups are pruned automatically after the latest 30.</p>
            </div>
            <div class="text-sm text-slate-500">
                Total backups: <span class="font-semibold text-slate-900">{{ number_format($googleDriveHistory->count()) }}</span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[1100px] table-fixed text-sm">
                <colgroup>
                    <col class="w-[32%]">
                    <col class="w-[20%]">
                    <col class="w-[12%]">
                    <col class="w-[14%]">
                    <col class="w-[22%]">
                </colgroup>
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                    <tr>
                        <th class="px-6 py-4 text-left">File Name</th>
                        <th class="px-6 py-4 text-left">Created At</th>
                        <th class="px-6 py-4 text-left">Size</th>
                        <th class="px-6 py-4 text-left">Status</th>
                        <th class="px-6 py-4 text-left">Google Drive Link</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200">
                    @forelse($googleDriveHistory as $log)
                        @php
                            $status = strtolower((string) $log->status);
                            $statusClasses = match ($status) {
                                'success' => 'border-emerald-200 bg-emerald-50 text-emerald-700',
                                'failed' => 'border-rose-200 bg-rose-50 text-rose-700',
                                default => 'border-amber-200 bg-amber-50 text-amber-700',
                            };
                            $statusLabel = strtoupper($status);
                        @endphp
                        <tr>
                            <td class="px-6 py-4 align-top">
                                <div class="font-semibold text-slate-950">{{ $log->backup_name }}</div>
                                <div class="text-xs text-slate-400">Google Drive database backup</div>
                            </td>
                            <td class="px-6 py-4 align-top text-slate-700">{{ $log->createdAtLabel() }}</td>
                            <td class="px-6 py-4 align-top text-slate-700">{{ $log->fileSizeHuman() }}</td>
                            <td class="px-6 py-4 align-top">
                                <span class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold tracking-wide {{ $statusClasses }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-top">
                                @if($log->google_file_url)
                                    <a href="{{ $log->google_file_url }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-600 hover:text-indigo-500">
                                        Open in Google Drive
                                    </a>
                                @elseif($log->google_file_id)
                                    <a href="https://drive.google.com/file/d/{{ $log->google_file_id }}/view" target="_blank" rel="noopener noreferrer" class="font-semibold text-indigo-600 hover:text-indigo-500">
                                        Open in Google Drive
                                    </a>
                                @else
                                    <span class="text-slate-400">Not available</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500">
                                No Google Drive backups have been created yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="space-y-6">
        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Backup Count</div>
                <div id="backup-count" class="mt-3 text-3xl font-bold text-slate-950">{{ number_format($backupCount) }}</div>
                <div class="mt-2 text-sm text-slate-500">Latest 30 backups are retained automatically.</div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Latest Backup Time</div>
                <div id="latest-backup-time" class="mt-3 text-2xl font-bold text-slate-950">{{ $latestBackupTime }}</div>
                <div class="mt-2 text-sm text-slate-500">Most recent successful backup.</div>
            </div>

            <div class="rounded-[28px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="text-xs font-black uppercase tracking-[0.24em] text-slate-400">Storage Used</div>
                <div id="storage-used" class="mt-3 text-3xl font-bold text-slate-950">{{ $storageUsed }}</div>
                <div class="mt-2 text-sm text-slate-500">Space consumed by stored backup archives.</div>
            </div>
        </section>

        <section class="rounded-[28px] border border-slate-200 bg-white shadow-sm">
            <div class="flex flex-col gap-3 border-b border-slate-200 px-6 py-5 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h3 class="text-xl font-bold text-slate-950">Backup Archive</h3>
                    <p class="text-sm text-slate-500">Files are sorted with the latest backup first.</p>
                </div>
                <div class="text-sm text-slate-500">
                    Total backups: <span class="font-semibold text-slate-900">{{ number_format($backupCount) }}</span>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1180px] table-fixed text-sm">
                    <colgroup>
                        <col class="w-[26%]">
                        <col class="w-[18%]">
                        <col class="w-[12%]">
                        <col class="w-[18%]">
                        <col class="w-[13%]">
                        <col class="w-[13%]">
                    </colgroup>
                    <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">
                        <tr>
                            <th class="px-6 py-4 text-left">File Name</th>
                            <th class="px-6 py-4 text-left">Backup Date</th>
                            <th class="px-6 py-4 text-left">File Size</th>
                            <th class="px-6 py-4 text-left">Created Time</th>
                            <th class="px-6 py-4 text-left">Download</th>
                            <th class="px-6 py-4 text-right">Delete</th>
                        </tr>
                    </thead>
                    <tbody id="backup-table-body" class="divide-y divide-slate-200">
                        @forelse($backups as $backup)
                            <tr id="backup-row-{{ $backup['filename'] }}">
                                <td class="px-6 py-4 align-top">
                                    <div class="font-semibold text-slate-950">{{ $backup['filename'] }}</div>
                                    <div class="text-xs text-slate-400">ZIP archive</div>
                                </td>
                                <td class="px-6 py-4 align-top text-slate-700">{{ $backup['backup_date_human'] }}</td>
                                <td class="px-6 py-4 align-top text-slate-700">{{ $backup['size_human'] }}</td>
                                <td class="px-6 py-4 align-top text-slate-700">{{ $backup['created_time_human'] }}</td>
                                <td class="px-6 py-4 align-top">
                                    <a href="{{ $backup['download_url'] }}" class="font-semibold text-indigo-600 hover:text-indigo-500">
                                        Download
                                    </a>
                                </td>
                                <td class="px-6 py-4 align-top text-right">
                                    <form
                                        method="POST"
                                        action="{{ $backup['delete_url'] }}"
                                        class="inline-flex"
                                        data-swal-confirm="Are you sure you want to delete this backup?"
                                        data-swal-title="Delete Backup"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-100">
                                            Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr id="backup-empty-row">
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500">
                                    No backups found. Create your first backup to get started.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('create-backup-form');
            const tableBody = document.getElementById('backup-table-body');
            const backupCountEl = document.getElementById('backup-count');
            const latestBackupTimeEl = document.getElementById('latest-backup-time');
            const storageUsedEl = document.getElementById('storage-used');
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderBackupRow(backup) {
                return `
                    <tr id="backup-row-${escapeHtml(backup.filename)}">
                        <td class="px-6 py-4 align-top">
                            <div class="font-semibold text-slate-950">${escapeHtml(backup.filename)}</div>
                            <div class="text-xs text-slate-400">ZIP archive</div>
                        </td>
                        <td class="px-6 py-4 align-top text-slate-700">${escapeHtml(backup.backup_date_human)}</td>
                        <td class="px-6 py-4 align-top text-slate-700">${escapeHtml(backup.size_human)}</td>
                        <td class="px-6 py-4 align-top text-slate-700">${escapeHtml(backup.created_time_human)}</td>
                        <td class="px-6 py-4 align-top">
                            <a href="${escapeHtml(backup.download_url)}" class="font-semibold text-indigo-600 hover:text-indigo-500">Download</a>
                        </td>
                        <td class="px-6 py-4 align-top text-right">
                            <form method="POST" action="${escapeHtml(backup.delete_url)}" class="inline-flex" data-swal-confirm="Are you sure you want to delete this backup?" data-swal-title="Delete Backup">
                                <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button type="submit" class="rounded-xl bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-600 transition hover:bg-rose-100">Delete</button>
                            </form>
                        </td>
                    </tr>
                `;
            }

            function bindDeleteConfirmation(form) {
                if (!form || form.dataset.backupDeleteBound === '1') {
                    return;
                }

                form.dataset.backupDeleteBound = '1';
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const ok = window.appConfirm
                        ? await window.appConfirm('Are you sure you want to delete this backup?', {
                            title: 'Delete Backup',
                            confirmText: 'Yes, delete it',
                            cancelText: 'Cancel',
                            confirmButtonColor: '#e11d48',
                            cancelButtonColor: '#64748b',
                        })
                        : window.confirm('Are you sure you want to delete this backup?');

                    if (ok) {
                        form.submit();
                    }
                });
            }

            function updateStats(stats) {
                if (backupCountEl && typeof stats.backup_count !== 'undefined') {
                    backupCountEl.textContent = Number(stats.backup_count).toLocaleString();
                }

                if (latestBackupTimeEl && stats.latest_backup_time_human) {
                    latestBackupTimeEl.textContent = stats.latest_backup_time_human;
                }

                if (storageUsedEl && stats.storage_used_human) {
                    storageUsedEl.textContent = stats.storage_used_human;
                }
            }

            if (form) {
                form.addEventListener('submit', async function (event) {
                    event.preventDefault();

                    const button = form.querySelector('button[type="submit"]');
                    const originalText = button ? button.textContent : '';

                    if (button) {
                        button.disabled = true;
                        button.textContent = 'Creating...';
                    }

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken || '',
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            body: new FormData(form),
                        });

                        const payload = await response.json().catch(() => ({}));

                        if (!response.ok) {
                            throw new Error(payload.message || 'Database backup failed.');
                        }

                        if (window.appAlert) {
                            window.appAlert(payload.message || 'Database backup created successfully.', 'Success', 'success');
                        }

                        if (payload.backup && tableBody) {
                            const emptyRow = document.getElementById('backup-empty-row');
                            if (emptyRow) {
                                emptyRow.remove();
                            }
                            tableBody.insertAdjacentHTML('afterbegin', renderBackupRow(payload.backup));
                            bindDeleteConfirmation(tableBody.firstElementChild?.querySelector('form[data-swal-confirm]'));
                        }

                        if (payload.stats) {
                            updateStats(payload.stats);
                        }
                    } catch (error) {
                        if (window.appAlert) {
                            window.appAlert(error.message || 'Database backup failed.', 'Error', 'error');
                        } else {
                            window.alert(error.message || 'Database backup failed.');
                        }
                    } finally {
                        if (button) {
                            button.disabled = false;
                            button.textContent = originalText || 'Create Backup';
                        }
                    }
                });
            }

            tableBody?.querySelectorAll('form[data-swal-confirm]').forEach(bindDeleteConfirmation);
        });
    </script>
</x-app-layout>
