<x-app-layout>
    @php
        $dashboardToday = now()->toDateString();
        $calendarEventsPayload = $calendarEvents ?? [];
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
            <div>
                <h2 class="text-2xl font-semibold leading-tight text-slate-950">Calendar</h2>
                <p class="mt-1 text-sm text-slate-500">Expanded full-screen calendar view for tasks, leads, projects, AMC, and support.</p>
            </div>
            <a href="{{ route('dashboard') }}"
                class="inline-flex w-full items-center justify-center rounded-md border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 sm:w-auto">
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-6">
        @include('dashboard._calendar-toolbar', [
            'calendarToolbarDescription' => 'Use the expanded calendar to review and manage all event types in one place.',
            'calendarToolbarShowAction' => false,
            'calendarToolbarShowLiveBadge' => false,
        ])

        <div id="activity-calendar" class="mt-5 min-h-[78vh] w-full"></div>
    </div>

    <div id="calendar-task-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/60 px-4">
        <div class="w-full max-w-xl bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Add calendar task</h3>
                    <p class="text-xs text-slate-500">Create a task or appointment with a location.</p>
                </div>
                <button type="button" id="close-calendar-task-modal" class="text-slate-500 hover:text-slate-900 text-2xl leading-none">&times;</button>
            </div>
            <form method="POST" action="{{ route('dashboard.calendar-events.store') }}" id="calendar-task-form" class="p-6 space-y-4">
                @csrf
                <input type="hidden" name="_method" id="calendar-task-form-method" value="POST">
                <input type="hidden" name="event_date" id="calendar-task-date" required>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Title</label>
                    <input name="title" id="calendar-task-title" type="text" required class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900" placeholder="Task title">
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Date</label>
                        <input id="calendar-task-date-visible" type="date" required class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-semibold text-slate-700">Type</label>
                        <select name="event_type" id="calendar-task-type" class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900">
                            <option value="task">Task</option>
                            <option value="appointment">Appointment</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Location</label>
                    <input name="location" id="calendar-task-location" type="text" class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900" placeholder="Office, site, client location">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Status</label>
                    <select name="status" id="calendar-task-status" class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900">
                        <option value="pending">Pending</option>
                        <option value="done">Done</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-slate-700">Description</label>
                    <textarea name="description" id="calendar-task-description" rows="4" class="w-full rounded-xl border-slate-300 focus:border-slate-900 focus:ring-slate-900" placeholder="Notes, reminder, or details"></textarea>
                </div>
                <div class="flex items-center justify-between gap-3 pt-2">
                    <button type="submit" form="calendar-task-delete-form" id="delete-calendar-task-btn" class="hidden rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50">Delete</button>
                    <div class="ml-auto flex items-center gap-3">
                        <button type="button" id="cancel-calendar-task-modal" class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</button>
                        <button type="submit" id="save-calendar-task-btn" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Save Task</button>
                    </div>
                </div>
            </form>
            <form method="POST" action="" id="calendar-task-delete-form" class="hidden">
                @csrf
                @method('DELETE')
            </form>
        </div>
    </div>

    <div id="calendar-task-detail-modal" class="hidden fixed inset-0 z-50 items-center justify-center bg-slate-900/60 px-4">
        <div class="w-full max-w-lg bg-white shadow-2xl border border-slate-200 overflow-hidden">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Task details</h3>
                    <p class="text-xs text-slate-500">View or edit the saved task information.</p>
                </div>
                <button type="button" id="close-calendar-task-detail-modal" class="text-slate-500 hover:text-slate-900 text-2xl leading-none">&times;</button>
            </div>
            <div class="space-y-4 p-6">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Title</p>
                    <p id="calendar-task-detail-title" class="mt-1 text-lg font-bold text-slate-900"></p>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Date</p>
                        <p id="calendar-task-detail-date" class="mt-1 text-sm text-slate-700"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Location</p>
                        <p id="calendar-task-detail-location" class="mt-1 text-sm text-slate-700"></p>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Type</p>
                        <p id="calendar-task-detail-type" class="mt-1 text-sm text-slate-700"></p>
                    </div>
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Status</p>
                        <p id="calendar-task-detail-status" class="mt-1 text-sm text-slate-700"></p>
                    </div>
                </div>
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Description</p>
                    <p id="calendar-task-detail-description" class="mt-1 whitespace-pre-wrap text-sm leading-6 text-slate-700"></p>
                </div>
                <div class="flex items-center justify-end gap-3 pt-2">
                    <button type="button" id="edit-calendar-task-btn" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-800">Edit Task</button>
                </div>
            </div>
        </div>
    </div>

    @include('partials.lead-followup-modal')

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
    <style>
        .fc { --fc-border-color: transparent; --fc-today-bg-color: #eef2ff; }
        .fc .fc-toolbar.fc-header-toolbar { margin-bottom: 8px; }
        .fc .fc-toolbar-title { font-size: 1rem; font-weight: 800; color: #0f172a; }
        .fc .fc-button-primary { background: #e5e7eb; color: #0f172a; border: none; border-radius: 10px; padding: 4px 10px; box-shadow: none; }
        .fc .fc-button-primary:not(:disabled):hover { background: #d1d5db; }
        .fc .fc-daygrid-day-frame { border-radius: 10px; padding: 4px; }
        .fc .fc-daygrid-day-number { color: #0f172a; font-weight: 700; font-size: 12px; }
        .fc .fc-daygrid-day.fc-day-other .fc-daygrid-day-number { color: #cbd5e1; font-weight: 600; }
        .fc .fc-col-header-cell-cushion { color: #fff; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; font-size: 11px; }
        .fc .fc-daygrid-event { border: none; background: transparent; padding: 0; overflow: visible; margin: 2px 0 0 0; width: 100%; }
        .fc .fc-daygrid-day-events { display: flex; flex-direction: column; gap: 2px; align-items: stretch; min-height: 0; }
        .fc .fc-daygrid-event-harness { width: 100%; }
        .fc .fc-daygrid-event .fc-event-pill { display: flex; align-items: center; gap: 6px; width: 100%; min-width: 0; border-radius: 7px; padding: 3px 8px; font-size: 11px; line-height: 1.2; font-weight: 700; color: #fff; box-shadow: 0 1px 2px rgba(15, 23, 42, 0.12); cursor: pointer; }
        .fc .fc-daygrid-event .fc-event-pill .event-text { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .fc .fc-daygrid-event .fc-event-pill .event-chip { flex: none; width: 8px; height: 8px; border-radius: 9999px; background: rgba(255, 255, 255, 0.92); box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.25); }
        .event-custom-task .fc-event-pill { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .event-project .fc-event-pill { background: linear-gradient(135deg, #059669, #047857); }
        .event-amc .fc-event-pill { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .event-ticket .fc-event-pill { background: linear-gradient(135deg, #14b8a6, #0f766e); }
        .event-lead .fc-event-pill { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .event-overdue .fc-event-pill { background: linear-gradient(135deg, #dc2626, #b91c1c); }
        .event-absent .fc-event-pill { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .fc .fc-daygrid-more-link { display: inline-flex; margin-top: 2px; padding: 2px 6px; border-radius: 9999px; background: #e2e8f0; color: #0f172a; font-size: 11px; font-weight: 700; text-decoration: none; }
        .fc-theme-standard .fc-popover { border-radius: 14px; border: 1px solid #e5e7eb; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12); }
        .fc-theme-standard .fc-popover-title { font-weight: 800; color: #0f172a; }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const calendarEvents = @json($calendarEventsPayload);
            const dashboardToday = @json($dashboardToday);
            const leadStatuses = @json(($leadStatuses ?? collect())->map(fn ($status) => [
                'id' => (int) $status->id,
                'name' => (string) $status->name,
            ])->values());
            const taskModal = document.getElementById('calendar-task-modal');
            const taskDetailModal = document.getElementById('calendar-task-detail-modal');
            const leadFollowUpModal = document.getElementById('lead-follow-up-modal');
            const taskForm = document.getElementById('calendar-task-form');
            const taskFormMethodInput = document.getElementById('calendar-task-form-method');
            const taskDateInput = document.getElementById('calendar-task-date');
            const taskDateVisibleInput = document.getElementById('calendar-task-date-visible');
            const taskTitleInput = document.getElementById('calendar-task-title');
            const taskTypeInput = document.getElementById('calendar-task-type');
            const taskLocationInput = document.getElementById('calendar-task-location');
            const taskStatusInput = document.getElementById('calendar-task-status');
            const taskDescriptionInput = document.getElementById('calendar-task-description');
            const openTaskModalBtn = document.getElementById('open-calendar-task-modal');
            const closeTaskModalBtn = document.getElementById('close-calendar-task-modal');
            const cancelTaskModalBtn = document.getElementById('cancel-calendar-task-modal');
            const closeTaskDetailModalBtn = document.getElementById('close-calendar-task-detail-modal');
            const editTaskBtn = document.getElementById('edit-calendar-task-btn');
            const deleteTaskBtn = document.getElementById('delete-calendar-task-btn');
            const deleteTaskForm = document.getElementById('calendar-task-delete-form');
            const saveTaskBtn = document.getElementById('save-calendar-task-btn');
            const taskDetailTitle = document.getElementById('calendar-task-detail-title');
            const taskDetailDate = document.getElementById('calendar-task-detail-date');
            const taskDetailLocation = document.getElementById('calendar-task-detail-location');
            const taskDetailType = document.getElementById('calendar-task-detail-type');
            const taskDetailStatus = document.getElementById('calendar-task-detail-status');
            const taskDetailDescription = document.getElementById('calendar-task-detail-description');
            let activeTaskEvent = null;
            let activeLeadEvent = null;
            let activityCalendar = null;

            const syncTaskDateInputs = (value = '') => {
                if (taskDateInput) taskDateInput.value = value;
                if (taskDateVisibleInput) taskDateVisibleInput.value = value;
            };

            const resetTaskForm = () => {
                taskForm?.reset();
                if (taskForm) {
                    taskForm.action = @json(route('dashboard.calendar-events.store'));
                }
                if (taskFormMethodInput) {
                    taskFormMethodInput.value = 'POST';
                }
                syncTaskDateInputs(new Date().toISOString().slice(0, 10));
                if (taskTypeInput) taskTypeInput.value = 'task';
                if (taskStatusInput) taskStatusInput.value = 'pending';
                if (saveTaskBtn) saveTaskBtn.textContent = 'Save Task';
                deleteTaskBtn?.classList.add('hidden');
                if (deleteTaskForm) deleteTaskForm.action = '';
                activeTaskEvent = null;
            };

            const openTaskModal = (dateString = '') => {
                if (!taskModal || !taskDateInput) return;
                resetTaskForm();
                taskModal.classList.remove('hidden');
                taskModal.classList.add('flex');
                syncTaskDateInputs(dateString || new Date().toISOString().slice(0, 10));
            };

            const closeTaskModal = () => {
                if (!taskModal) return;
                taskModal.classList.add('hidden');
                taskModal.classList.remove('flex');
            };

            const openTaskDetailModal = (event) => {
                if (!taskDetailModal) return;
                activeTaskEvent = event;
                taskDetailTitle.textContent = event.title || 'Task';
                taskDetailDate.textContent = event.extendedProps.eventDate || event.startStr || '';
                taskDetailLocation.textContent = event.extendedProps.location || 'N/A';
                taskDetailType.textContent = event.extendedProps.eventType || 'task';
                taskDetailStatus.textContent = event.extendedProps.status || 'pending';
                taskDetailDescription.textContent = event.extendedProps.description || 'N/A';
                taskDetailModal.classList.remove('hidden');
                taskDetailModal.classList.add('flex');
            };

            const closeTaskDetailModal = () => {
                if (!taskDetailModal) return;
                taskDetailModal.classList.add('hidden');
                taskDetailModal.classList.remove('flex');
            };

            const leadFollowUpForm = document.getElementById('lead-follow-up-form');
            const leadActionTypeInput = document.getElementById('lead-action-type');
            const leadActionSelector = document.getElementById('lead-action-selector');
            const leadRescheduleFields = document.getElementById('lead-follow-up-reschedule-fields');
            const leadStatusFields = document.getElementById('lead-status-fields');
            const leadFollowUpDetailLink = document.getElementById('lead-follow-up-detail-link');
            const closeLeadFollowUpModalBtn = document.getElementById('close-lead-follow-up-modal');
            const cancelLeadFollowUpModalBtn = document.getElementById('cancel-lead-follow-up-modal');
            const saveLeadFollowUpBtn = document.getElementById('save-lead-follow-up-btn');
            const leadFollowUpDateInput = document.getElementById('lead-follow-up-date');
            const leadFollowUpDateError = document.getElementById('lead-follow-up-date-error');
            const leadFollowUpRemarkInput = document.getElementById('lead-follow-up-remark');
            const leadFollowUpRemarkError = document.getElementById('lead-follow-up-remark-error');
            const leadStatusSelect = document.getElementById('lead-status-select');
            const leadFollowUpLeadName = document.getElementById('lead-follow-up-lead-name');
            const leadFollowUpCurrentDate = document.getElementById('lead-follow-up-current-date');

            const leadFollowUpValidator = $('#lead-follow-up-form').validate({
                ignore: [],
                errorClass: 'text-xs text-red-600',
                errorElement: 'span',
                highlight: function (element) {
                    element.classList.add('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                },
                unhighlight: function (element) {
                    element.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                },
                rules: {
                    summary: { required: true, maxlength: 5000 },
                    next_followup_date: { required: true, min: dashboardToday }
                },
                messages: {
                    summary: { required: 'Please enter today\'s follow-up summary.' },
                    next_followup_date: {
                        required: 'Please choose next follow-up date.',
                        min: 'Please choose today or a future date.'
                    }
                },
                errorPlacement: function (error, element) {
                    if (element.attr('name') === 'summary') {
                        const slot = document.getElementById('lead-follow-up-remark-error');
                        if (slot) {
                            slot.textContent = error.text();
                            slot.classList.remove('hidden');
                            return;
                        }
                    }

                    if (element.attr('name') === 'next_followup_date') {
                        const slot = document.getElementById('lead-follow-up-date-error');
                        if (slot) {
                            slot.textContent = error.text();
                            slot.classList.remove('hidden');
                            return;
                        }
                    }

                    error.insertAfter(element);
                }
            });

            const getLeadEventDisplay = (title, followUpDate, status = '') => {
                const dateValue = String(followUpDate || '').slice(0, 10);
                const overdue = dateValue ? dateValue < dashboardToday : false;
                const normalizedTitle = String(title || 'Lead');
                const displayTitle = overdue ? `${normalizedTitle} (Overdue)` : normalizedTitle;
                const tooltipParts = [normalizedTitle];

                if (dateValue) tooltipParts.push(`Next follow-up: ${dateValue}`);
                if (overdue) tooltipParts.push('Overdue');
                if (status) tooltipParts.push(status);

                return { displayTitle, tooltip: tooltipParts.join(' | '), isOverdue: overdue };
            };

            const escapeHtml = (value = '') => String(value ?? '')
                .replaceAll('&', '&amp;')
                .replaceAll('<', '&lt;')
                .replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;')
                .replaceAll("'", '&#039;');

            const formatStatusLabel = (status = '') => String(status || 'Pending')
                .replaceAll('_', ' ')
                .replaceAll('-', ' ')
                .replace(/\b\w/g, (letter) => letter.toUpperCase());

            const setFollowUpLocked = (locked) => {
                document.getElementById('lead-follow-up-done-notice')?.classList.toggle('hidden', !locked);
                if (leadFollowUpRemarkInput) leadFollowUpRemarkInput.disabled = locked;
                if (leadFollowUpDateInput) leadFollowUpDateInput.disabled = locked;
                if (leadStatusSelect) leadStatusSelect.disabled = locked;
                if (saveLeadFollowUpBtn) saveLeadFollowUpBtn.disabled = locked;
            };

            const renderLeadDetails = (payload = {}) => {
                const lead = payload.lead || {};
                const histories = Array.isArray(payload.latest_followup_summaries) ? payload.latest_followup_summaries : [];
                const customFields = Array.isArray(lead.custom_fields) ? lead.custom_fields : [];
                const loading = document.getElementById('lead-follow-up-loading');
                const info = document.getElementById('lead-follow-up-info');
                const setText = (id, value, fallback = 'N/A') => {
                    const el = document.getElementById(id);
                    if (el) el.textContent = value || fallback;
                };

                loading?.classList.add('hidden');
                info?.classList.remove('hidden');
                if (leadFollowUpLeadName) leadFollowUpLeadName.textContent = lead.name || 'Lead';
                setText('lead-info-company', lead.company_name);
                setText('lead-info-status', lead.status);
                setText('lead-info-followup-status', lead.last_follow_up_status ? formatStatusLabel(lead.last_follow_up_status) : null);
                setText('lead-info-mobile', lead.mobile);
                setText('lead-info-email', lead.email);
                setText('lead-info-address', lead.address);
                setText('lead-info-last-summary', lead.last_follow_up_summary, 'No follow-up history yet.');
                setFollowUpLocked(lead.can_add_followup === false);

                const customWrap = document.getElementById('lead-info-custom-fields');
                if (customWrap) {
                    customWrap.innerHTML = customFields.length
                        ? customFields.map((field) => `
                            <div class="rounded-xl bg-white p-3">
                                <p class="text-[11px] font-bold uppercase tracking-wide text-slate-400">${escapeHtml(field.label || 'Custom Field')}</p>
                                <p class="mt-1 text-sm font-semibold text-slate-800">${escapeHtml(field.value || 'N/A')}</p>
                            </div>
                        `).join('')
                        : '<div class="rounded-xl bg-white p-3 text-sm text-slate-500">No custom fields.</div>';
                }

                const historyWrap = document.getElementById('lead-followup-history-list');
                if (historyWrap) {
                    historyWrap.innerHTML = histories.length
                        ? histories.map((item) => `
                            <div class="rounded-lg border border-slate-200 bg-white p-3">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-bold text-slate-500">${escapeHtml(formatStatusLabel(item.status))}</span>
                                    <span class="text-[11px] font-semibold text-slate-400">${escapeHtml(item.followup_date || '')}</span>
                                </div>
                                <p class="mt-1 whitespace-pre-wrap text-sm leading-5 text-slate-700">${escapeHtml(item.summary || '')}</p>
                            </div>
                        `).join('')
                        : '<div class="text-slate-500">No follow-up summaries yet.</div>';
                }
            };

            const setLeadModalLoading = () => {
                document.getElementById('lead-follow-up-loading')?.classList.remove('hidden');
                document.getElementById('lead-follow-up-info')?.classList.add('hidden');
                const historyWrap = document.getElementById('lead-followup-history-list');
                if (historyWrap) historyWrap.innerHTML = '<div class="text-slate-500">Loading follow-up summaries...</div>';
            };

            const upsertCalendarEvent = (payload = {}) => {
                if (!activityCalendar || !payload?.id) return;
                const existing = activityCalendar.getEventById(payload.id);
                if (existing) {
                    existing.setProp('title', payload.title || existing.title);
                    existing.setStart(payload.start || existing.startStr);
                    existing.setProp('backgroundColor', payload.color || existing.backgroundColor);
                    existing.setProp('borderColor', payload.color || existing.borderColor);
                    Object.entries(payload).forEach(([key, value]) => {
                        if (!['id', 'title', 'start', 'allDay', 'url', 'color'].includes(key)) {
                            existing.setExtendedProp(key, value);
                        }
                    });
                    return;
                }
                activityCalendar.addEvent(payload);
            };

            const setLeadActionMode = (mode = 'reschedule') => {
                if (leadActionTypeInput) leadActionTypeInput.value = 'followup';
                if (leadFollowUpDateInput) {
                    leadFollowUpDateInput.disabled = false;
                    leadFollowUpDateInput.min = dashboardToday;
                }
                if (leadFollowUpRemarkInput) leadFollowUpRemarkInput.disabled = false;
                if (leadStatusSelect) {
                    leadStatusSelect.disabled = false;
                    leadStatusSelect.required = false;
                }
                if (saveLeadFollowUpBtn) saveLeadFollowUpBtn.textContent = 'Save Follow-up';
            };

            const clearLeadFollowUpValidation = () => {
                if (leadFollowUpDateError) {
                    leadFollowUpDateError.textContent = '';
                    leadFollowUpDateError.classList.add('hidden');
                }
                if (leadFollowUpRemarkError) {
                    leadFollowUpRemarkError.textContent = '';
                    leadFollowUpRemarkError.classList.add('hidden');
                }
                leadFollowUpValidator?.resetForm();
                leadFollowUpDateInput?.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
                leadFollowUpRemarkInput?.classList.remove('border-red-500', 'focus:border-red-500', 'focus:ring-red-500');
            };

            const setLeadFollowUpError = (element, errorElement, message) => {
                if (!element || !errorElement) return false;
                errorElement.textContent = message;
                errorElement.classList.toggle('hidden', !message);
                element.classList.toggle('border-red-500', !!message);
                element.classList.toggle('focus:border-red-500', !!message);
                element.classList.toggle('focus:ring-red-500', !!message);
                element.setAttribute('aria-invalid', message ? 'true' : 'false');
                return !message;
            };

            const validateLeadFollowUpDate = () => {
                const actionType = String(leadActionTypeInput?.value || 'reschedule');
                const followUpDate = String(leadFollowUpDateInput?.value || '').trim();
                if (actionType === 'status_change') return setLeadFollowUpError(leadFollowUpDateInput, leadFollowUpDateError, '');
                if (!followUpDate) return setLeadFollowUpError(leadFollowUpDateInput, leadFollowUpDateError, 'Please choose a next follow-up date.');
                if (followUpDate < dashboardToday) return setLeadFollowUpError(leadFollowUpDateInput, leadFollowUpDateError, 'Past dates are not allowed. Please select today or a future date.');
                return setLeadFollowUpError(leadFollowUpDateInput, leadFollowUpDateError, '');
            };

            const validateLeadFollowUpRemark = () => {
                const remark = String(leadFollowUpRemarkInput?.value || '').trim();
                if (!remark) return setLeadFollowUpError(leadFollowUpRemarkInput, leadFollowUpRemarkError, 'Remark is required.');
                return setLeadFollowUpError(leadFollowUpRemarkInput, leadFollowUpRemarkError, '');
            };

            const syncLeadEventState = (event, updates = {}) => {
                if (!event) return;
                const followUpDate = updates.followUpDate ?? event.extendedProps.followUpDate ?? event.startStr ?? '';
                const statusName = updates.statusName ?? event.extendedProps.status ?? '';
                const statusId = updates.statusId ?? event.extendedProps.statusId ?? '';
                const title = event.title || event.extendedProps.displayTitle || 'Lead';
                const display = getLeadEventDisplay(title, followUpDate, statusName);
                if (followUpDate) event.setStart(followUpDate);
                event.setExtendedProp('followUpDate', followUpDate);
                event.setExtendedProp('status', statusName);
                event.setExtendedProp('statusId', statusId);
                event.setExtendedProp('displayTitle', display.displayTitle);
                event.setExtendedProp('tooltip', display.tooltip);
                event.setExtendedProp('isOverdue', display.isOverdue);
                event.setProp('backgroundColor', display.isOverdue ? '#dc2626' : '#2563eb');
                event.setProp('borderColor', display.isOverdue ? '#dc2626' : '#2563eb');
            };

            const openLeadActionModal = (event) => {
                if (!leadFollowUpModal || !leadFollowUpForm) return;
                activeLeadEvent = event;
                const leadTitle = event.title || 'Lead';
                const followUpDate = event.extendedProps.followUpDate || event.startStr || '';
                const currentStatus = event.extendedProps.status || '';
                const leadUrl = event.url || event.extendedProps.url || '';
                leadFollowUpLeadName.textContent = leadTitle;
                leadFollowUpCurrentDate.textContent = followUpDate
                    ? `Current schedule: ${followUpDate}${currentStatus ? ` | ${formatStatusLabel(currentStatus)}` : ''}`
                    : 'No follow-up date set for this schedule.';
                const scheduleInput = document.getElementById('lead-follow-up-schedule-id');
                if (scheduleInput) scheduleInput.value = event.extendedProps.scheduleId || '';
                leadFollowUpDateInput.value = '';
                leadFollowUpRemarkInput.value = '';
                if (leadStatusSelect) leadStatusSelect.value = 'Pending';
                clearLeadFollowUpValidation();
                if (leadFollowUpDateInput) leadFollowUpDateInput.min = dashboardToday;
                if (leadFollowUpDetailLink) {
                    leadFollowUpDetailLink.href = leadUrl || '#';
                    leadFollowUpDetailLink.classList.toggle('pointer-events-none', !leadUrl);
                    leadFollowUpDetailLink.classList.toggle('opacity-40', !leadUrl);
                    leadFollowUpDetailLink.classList.toggle('cursor-not-allowed', !leadUrl);
                    leadFollowUpDetailLink.setAttribute('aria-disabled', leadUrl ? 'false' : 'true');
                    if (leadUrl) {
                        leadFollowUpDetailLink.removeAttribute('tabindex');
                    } else {
                        leadFollowUpDetailLink.setAttribute('tabindex', '-1');
                    }
                }
                leadFollowUpForm.action = event.extendedProps.actionUrl || '';
                setLeadActionMode('reschedule');
                setFollowUpLocked(false);
                setLeadModalLoading();
                leadFollowUpModal.classList.remove('hidden');
                leadFollowUpModal.classList.add('flex');
                if (event.extendedProps.detailsUrl) {
                    fetch(event.extendedProps.detailsUrl, {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then((response) => response.ok ? response.json() : Promise.reject(new Error('Unable to load lead details.')))
                        .then(renderLeadDetails)
                        .catch(() => {
                            const loading = document.getElementById('lead-follow-up-loading');
                            if (loading) loading.textContent = 'Unable to load lead details. You can still save the follow-up.';
                        });
                }
                setTimeout(() => leadFollowUpRemarkInput?.focus(), 0);
            };

            const closeLeadFollowUpModal = () => {
                if (!leadFollowUpModal) return;
                leadFollowUpModal.classList.add('hidden');
                leadFollowUpModal.classList.remove('flex');
                activeLeadEvent = null;
            };

            const openTaskEditModal = (event) => {
                if (!taskModal || !taskForm || !taskFormMethodInput) return;
                closeTaskDetailModal();
                resetTaskForm();
                activeTaskEvent = event;
                taskForm.action = event.extendedProps.updateUrl || taskForm.action;
                taskFormMethodInput.value = 'PATCH';
                taskTitleInput.value = event.title || '';
                taskTypeInput.value = event.extendedProps.eventType || 'task';
                taskLocationInput.value = event.extendedProps.location || '';
                taskStatusInput.value = event.extendedProps.status || 'pending';
                taskDescriptionInput.value = event.extendedProps.description || '';
                syncTaskDateInputs(event.extendedProps.eventDate || event.startStr || '');
                if (saveTaskBtn) saveTaskBtn.textContent = 'Update Task';
                if (deleteTaskForm && event.extendedProps.deleteUrl) {
                    deleteTaskForm.action = event.extendedProps.deleteUrl;
                    deleteTaskBtn?.classList.remove('hidden');
                }
                taskModal.classList.remove('hidden');
                taskModal.classList.add('flex');
            };

            openTaskModalBtn?.addEventListener('click', () => openTaskModal());
            closeTaskModalBtn?.addEventListener('click', closeTaskModal);
            cancelTaskModalBtn?.addEventListener('click', closeTaskModal);
            closeTaskDetailModalBtn?.addEventListener('click', closeTaskDetailModal);
            editTaskBtn?.addEventListener('click', () => { if (activeTaskEvent) openTaskEditModal(activeTaskEvent); });
            taskDateVisibleInput?.addEventListener('input', (e) => syncTaskDateInputs(e.target.value));
            taskModal?.addEventListener('click', (e) => { if (e.target === taskModal) closeTaskModal(); });
            taskDetailModal?.addEventListener('click', (e) => { if (e.target === taskDetailModal) closeTaskDetailModal(); });
            closeLeadFollowUpModalBtn?.addEventListener('click', closeLeadFollowUpModal);
            cancelLeadFollowUpModalBtn?.addEventListener('click', closeLeadFollowUpModal);
            leadFollowUpModal?.addEventListener('click', (e) => { if (e.target === leadFollowUpModal) closeLeadFollowUpModal(); });
            leadActionSelector?.addEventListener('change', (e) => { setLeadActionMode(e.target.value || 'reschedule'); clearLeadFollowUpValidation(); });
            leadFollowUpDateInput?.addEventListener('input', validateLeadFollowUpDate);
            leadFollowUpDateInput?.addEventListener('change', validateLeadFollowUpDate);
            leadFollowUpRemarkInput?.addEventListener('input', validateLeadFollowUpRemark);
            leadFollowUpRemarkInput?.addEventListener('blur', validateLeadFollowUpRemark);

            leadFollowUpForm?.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!activeLeadEvent) return;
                if (saveLeadFollowUpBtn?.disabled) return;
                if (!leadFollowUpValidator.form()) {
                    if (!String(leadFollowUpRemarkInput?.value || '').trim()) {
                        leadFollowUpRemarkInput?.focus();
                    } else {
                        leadFollowUpDateInput?.focus();
                    }
                    return;
                }
                const followUpDate = String(leadFollowUpDateInput?.value || '').trim();
                const submitUrl = leadFollowUpForm.action || '';
                if (!submitUrl) return;
                saveLeadFollowUpBtn?.setAttribute('disabled', 'disabled');
                saveLeadFollowUpBtn && (saveLeadFollowUpBtn.textContent = 'Saving...');
                try {
                    const response = await fetch(submitUrl, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                            'Accept': 'application/json',
                        },
                        body: new FormData(leadFollowUpForm),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data?.success) {
                        throw new Error(data?.message || 'Unable to update the lead action.');
                    }
                    upsertCalendarEvent(data.current_event);
                    upsertCalendarEvent(data.next_event);
                    activityCalendar?.render();
                    closeLeadFollowUpModal();
                    if (window.Swal) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Follow-up saved',
                            text: data?.message || 'Next follow-up scheduled successfully.',
                            confirmButtonColor: '#0f172a',
                        });
                    }
                } finally {
                    saveLeadFollowUpBtn?.removeAttribute('disabled');
                    setLeadActionMode();
                }
            });

            const commonOptions = {
                initialView: 'dayGridMonth',
                height: 'auto',
                headerToolbar: { left: 'prev,next today', center: 'title', right: '' },
                dayMaxEventRows: 4,
                dayMaxEvents: 4,
                moreLinkClick: 'popover',
                displayEventTime: false,
                eventClassNames(arg) {
                    const classes = [`event-${arg.event.extendedProps.type || 'default'}`];
                    if (arg.event.extendedProps.type === 'lead' && String(arg.event.extendedProps.followUpDate || arg.event.startStr || '').slice(0, 10) < dashboardToday) {
                        classes.push('event-overdue');
                    }
                    return classes;
                },
                eventContent(arg) {
                    const wrap = document.createElement('div');
                    wrap.className = 'fc-event-pill';
                    wrap.title = arg.event.extendedProps.tooltip || arg.event.title || '';
                    const eventColor = arg.event.backgroundColor || arg.event.extendedProps.color || '';
                    if (eventColor) wrap.style.background = eventColor;
                    const chip = document.createElement('span');
                    chip.className = 'event-chip';
                    const text = document.createElement('span');
                    text.className = 'event-text';
                    text.textContent = arg.event.extendedProps.displayTitle || arg.event.title || '';
                    wrap.append(chip, text);
                    return { domNodes: [wrap] };
                },
                eventOrder(a, b) {
                    const rankA = Number(a.extendedProps.statusRank ?? 50);
                    const rankB = Number(b.extendedProps.statusRank ?? 50);
                    if (rankA !== rankB) return rankA - rankB;
                    return String(a.title || '').localeCompare(String(b.title || ''));
                },
                eventDidMount(info) {
                    info.el.style.border = '0';
                    info.el.style.background = 'transparent';
                    info.el.style.boxShadow = 'none';
                    info.el.style.padding = '0';
                    info.el.style.margin = '0';
                    info.el.style.lineHeight = '1';
                    info.el.style.cursor = 'pointer';
                },
                dayCellDidMount(info) {
                    const frame = info.el.querySelector('.fc-daygrid-day-frame');
                    if (!frame || frame.dataset.taskClickBound === '1') return;
                    frame.dataset.taskClickBound = '1';
                    frame.style.cursor = 'pointer';
                    frame.addEventListener('click', (event) => {
                        if (
                            event.target.closest('.fc-daygrid-event') ||
                            event.target.closest('.fc-event') ||
                            event.target.closest('.fc-event-main') ||
                            event.target.closest('.fc-more-link')
                        ) return;
                        openTaskModal(info.date.toISOString().slice(0, 10));
                    }, true);
                },
                dateClick(info) { openTaskModal(info.dateStr); },
                eventClick(info) {
                    info.jsEvent.preventDefault();
                    info.jsEvent.stopImmediatePropagation();
                    info.jsEvent.stopPropagation();
                    if (info.event.extendedProps.type === 'custom-task') {
                        openTaskDetailModal(info.event);
                        return;
                    }
                    if (info.event.extendedProps.type === 'lead') {
                        openLeadActionModal(info.event);
                        return;
                    }
                    if (info.event.url) {
                        window.location.href = info.event.url;
                    }
                }
            };

            const activityCalendarEl = document.getElementById('activity-calendar');
            if (activityCalendarEl) {
                activityCalendar = new FullCalendar.Calendar(activityCalendarEl, {
                    ...commonOptions,
                    events: calendarEvents,
                    eventDisplay: 'block',
                    displayEventEnd: true,
                });
                activityCalendar.render();
            }
        });
    </script>
</x-app-layout>
