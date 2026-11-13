<div class="rounded-[1.75rem] border border-white/70 bg-white/90 p-5 shadow-lg shadow-slate-950/5 backdrop-blur">
    <div class="flex items-center justify-between gap-3">
        <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Renewals</p>
            <h3 class="mt-1 text-lg font-bold text-slate-950">Renewal Table</h3>
        </div>
        <span class="text-xs text-slate-500">Action-ready renewal watchlist</span>
    </div>
    <div class="mt-5 overflow-x-auto rounded-2xl border border-slate-200">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">AMC</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Customer</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Expiry</th>
                    <th class="px-4 py-3 text-left text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Status</th>
                    <th class="px-4 py-3 text-right text-[11px] font-semibold uppercase tracking-wide text-white text-slate-500">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 bg-white">
                @forelse ($renewalReport['follow_up_rows'] as $row)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="font-semibold text-slate-900">{{ $row['project_name'] }}</div>
                            <div class="text-xs text-slate-500">{{ $row['contract_no'] }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-700">{{ $row['customer_name'] }}</td>
                        <td class="px-4 py-3 text-slate-700">
                            <div>{{ $row['expiry_date'] }}</div>
                            @if (! is_null($row['days_left']))
                                <div class="text-xs text-slate-500">
                                    @if ($row['days_left'] < 0)
                                        {{ abs($row['days_left']) }} days overdue
                                    @elseif ($row['days_left'] === 0)
                                        Ends today
                                    @else
                                        {{ $row['days_left'] }} days left
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $toneClasses[$row['tone']] ?? $toneClasses['slate'] }}">
                                {{ $row['status_label'] }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ $canEditAmc ? $row['edit_url'] : $row['show_url'] }}" class="inline-flex items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                {{ $row['action_label'] }}
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-8 text-center text-sm text-slate-500">No renewal records match the current filter set.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
