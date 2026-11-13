<div id="lead-follow-up-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/60 px-4 py-6">
    <div class="flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 bg-gradient-to-r from-slate-950 to-slate-800 px-6 py-5 text-white">
            <div>
                <h3 class="text-lg font-bold">Lead Follow-up</h3>
                <p id="lead-follow-up-current-date" class="mt-1 text-xs font-medium text-slate-300">Loading follow-up details...</p>
            </div>
            <button type="button" id="close-lead-follow-up-modal" class="text-2xl leading-none text-slate-300 hover:text-white">&times;</button>
        </div>

        <form method="POST" action="" id="lead-follow-up-form" class="overflow-y-auto">
            @csrf
            <input type="hidden" name="schedule_id" id="lead-follow-up-schedule-id">
            <input type="hidden" name="action_type" id="lead-action-type" value="followup">

            <div class="grid gap-5 p-5 lg:grid-cols-[1.1fr_0.9fr] lg:p-6">
                <section class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Lead Information</p>
                            <p id="lead-follow-up-lead-name" class="mt-1 text-xl font-bold text-slate-950">Lead</p>
                        </div>
                        <a id="lead-follow-up-detail-link"
                           href="#"
                           class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-slate-900"
                           aria-label="Open lead details"
                           title="Open lead details">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14 5h5a1 1 0 0 1 1 1v5" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 14 20 4" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4" />
                            </svg>
                        </a>
                    </div>

                    <div id="lead-follow-up-loading" class="rounded-2xl border border-slate-200 bg-slate-50/70 p-6 text-center text-sm text-slate-500">
                        <div class="inline-flex items-center justify-center mb-2">
                            <div class="h-6 w-6 rounded-full border-2 border-indigo-200 border-t-indigo-600 animate-spin"></div>
                        </div>
                        <div class="font-medium text-slate-600">Loading latest lead details...</div>
                    </div>

                    <div id="lead-follow-up-info" class="hidden space-y-4">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Company</p>
                                <p id="lead-info-company" class="mt-1 text-sm font-semibold text-slate-800">N/A</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Lead Status</p>
                                <p id="lead-info-status" class="mt-1 text-sm font-semibold text-slate-800">N/A</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Last Follow-up Status</p>
                                <p id="lead-info-followup-status" class="mt-1 text-sm font-semibold text-slate-800">N/A</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Mobile</p>
                                <p id="lead-info-mobile" class="mt-1 text-sm font-semibold text-slate-800">N/A</p>
                            </div>
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Email</p>
                                <p id="lead-info-email" class="mt-1 break-all text-sm font-semibold text-slate-800">N/A</p>
                            </div>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Address</p>
                            <p id="lead-info-address" class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700">N/A</p>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Last Follow-up Summary</p>
                            <p id="lead-info-last-summary" class="mt-1 whitespace-pre-wrap rounded-xl bg-white p-3 text-sm leading-6 text-slate-700">No follow-up history yet.</p>
                        </div>

                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-slate-400">Custom Fields</p>
                            <div id="lead-info-custom-fields" class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <div class="rounded-xl bg-white p-3 text-sm text-slate-500">No custom fields.</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4 rounded-2xl border border-slate-200 bg-white p-4">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-400">Follow-up Form</p>
                        <p class="mt-1 text-sm text-slate-500">Save today&apos;s outcome and schedule the next touchpoint.</p>
                    </div>

                    <div id="lead-follow-up-done-notice" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm font-semibold text-amber-800">
                        Lead next follow up already done.
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-sm font-semibold text-slate-700" for="lead-follow-up-remark">Today&apos;s Follow-up Summary <span class="text-red-600">*</span></label>
                            <x-speech-to-text target="lead-follow-up-remark" />
                        </div>
                        <textarea id="lead-follow-up-remark" name="summary" rows="5" maxlength="5000" required class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900" placeholder="Example: Customer asked for quotation"></textarea>
                        <p id="lead-follow-up-remark-error" class="mt-1 hidden min-h-[20px] text-xs text-red-600" aria-live="polite"></p>
                    </div>

                    <div id="lead-follow-up-reschedule-fields">
                        <label class="mb-1 block text-sm font-semibold text-slate-700" for="lead-follow-up-date">Next Follow-up Date <span class="text-red-600">*</span></label>
                        <input id="lead-follow-up-date" name="next_followup_date" type="date" required class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900">
                        <p id="lead-follow-up-date-error" class="mt-1 hidden min-h-[20px] text-xs text-red-600" aria-live="polite"></p>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700" for="lead-status-select">Today&apos;s Follow-up Status</label>
                        <select id="lead-status-select" name="status" class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900">
                            <option value="Pending" selected>Pending</option>
                            <option value="Done">Done</option>
                            <option value="Not Interested">Not Interested</option>
                            <option value="Converted">Converted</option>
                            <option value="Callback">Callback</option>
                            <option value="Follow-up Required">Follow-up Required</option>
                        </select>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-semibold text-slate-700">Latest 5 Follow-up Summaries</p>
                        <div id="lead-followup-history-list" class="max-h-52 space-y-2 overflow-y-auto rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm">
                            <div class="text-slate-500">No follow-up summaries yet.</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button type="button" id="cancel-lead-follow-up-modal" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" id="save-lead-follow-up-btn" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70">Save Follow-up</button>
                    </div>
                </section>
            </div>
        </form>
    </div>
</div>
