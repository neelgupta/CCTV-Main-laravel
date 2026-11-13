import './bootstrap';

import Alpine from 'alpinejs';
import speechToText from './speech-to-text';
import { setupFirebasePushNotifications } from './push-notifications';

window.Alpine = Alpine;
window.speechToText = speechToText;
Alpine.data('speechToText', speechToText);

Alpine.start();

const isIos = typeof navigator !== 'undefined' && (/iphone|ipad|ipod/.test(navigator.userAgent.toLowerCase()) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1));
const isStandalone = (typeof window !== 'undefined' && (window.matchMedia?.('(display-mode: standalone)')?.matches || window.navigator.standalone === true));

const PWA_STORAGE_KEY = 'saas-workshop-pwa-banner-dismissed';
const PWA_STATE = {
    deferredPrompt: null,
    installed: isStandalone,
    banner: null,
    installButtons: [],
    headerInstallButton: null,
    offlineNotice: null,
    iosInstructions: null,
    standardActions: null,
};

function isLocalhost() {
    return ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);
}

function setHidden(element, hidden) {
    if (!element) return;
    element.classList.toggle('hidden', hidden);
}

function showBanner() {
    if (PWA_STATE.installed || !PWA_STATE.banner) return;
    setHidden(PWA_STATE.banner, false);
}

function hideBanner(permanent = false) {
    if (PWA_STATE.banner) {
        setHidden(PWA_STATE.banner, true);
    }

    if (permanent) {
        try {
            window.localStorage.setItem(PWA_STORAGE_KEY, '1');
        } catch (error) {
            // Ignore storage failures.
        }
    }
}

function syncInstallControls() {
    if (PWA_STATE.installed) {
        hideBanner();
        if (PWA_STATE.headerInstallButton) {
            PWA_STATE.headerInstallButton.hidden = true;
        }
        return;
    }

    const dismissed = (() => {
        try {
            return window.localStorage.getItem(PWA_STORAGE_KEY) === '1';
        } catch (error) {
            return false;
        }
    })();

    if (isIos) {
        // iOS does not trigger beforeinstallprompt, show custom share sheet instructions
        if (PWA_STATE.headerInstallButton) {
            PWA_STATE.headerInstallButton.hidden = false;
        }
        if (PWA_STATE.standardActions) {
            setHidden(PWA_STATE.standardActions, true);
        }
        if (PWA_STATE.iosInstructions) {
            setHidden(PWA_STATE.iosInstructions, false);
        }
        if (!dismissed) {
            showBanner();
        }
        return;
    }

    const canInstall = Boolean(PWA_STATE.deferredPrompt);
    PWA_STATE.installButtons.forEach((button) => {
        button.disabled = !canInstall;
        button.setAttribute('aria-disabled', String(!canInstall));
    });

    if (PWA_STATE.headerInstallButton) {
        PWA_STATE.headerInstallButton.hidden = !canInstall;
    }

    if (canInstall && !dismissed) {
        showBanner();
    }
}

async function promptInstall() {
    if (isIos) {
        // Toggle/show iOS guide banner
        showBanner();
        if (PWA_STATE.iosInstructions) {
            setHidden(PWA_STATE.iosInstructions, false);
        }
        return;
    }

    if (!PWA_STATE.deferredPrompt) return;

    PWA_STATE.deferredPrompt.prompt();
    try {
        await PWA_STATE.deferredPrompt.userChoice;
    } catch (error) {
        // Browser may reject in some edge cases; we still clear prompt state.
    }

    PWA_STATE.deferredPrompt = null;
    syncInstallControls();
    hideBanner();
}

function setupPwaControls() {
    PWA_STATE.banner = document.querySelector('[data-pwa-shell]');
    PWA_STATE.installButtons = Array.from(document.querySelectorAll('[data-pwa-install]'));
    PWA_STATE.headerInstallButton = document.querySelector('[data-pwa-install-trigger]');
    PWA_STATE.offlineNotice = document.querySelector('[data-pwa-offline]');
    PWA_STATE.iosInstructions = document.querySelector('[data-pwa-ios-instructions]');
    PWA_STATE.standardActions = document.querySelector('[data-pwa-standard-actions]');

    if (PWA_STATE.banner) {
        const dismissButtons = Array.from(document.querySelectorAll('[data-pwa-dismiss], [data-pwa-later]'));
        dismissButtons.forEach((button) => {
            button.addEventListener('click', () => hideBanner(true));
        });
    }

    PWA_STATE.installButtons.forEach((button) => {
        button.addEventListener('click', promptInstall);
    });

    if (PWA_STATE.headerInstallButton) {
        PWA_STATE.headerInstallButton.addEventListener('click', promptInstall);
    }

    window.addEventListener('online', () => {
        setHidden(PWA_STATE.offlineNotice, true);
    });

    window.addEventListener('offline', () => {
        if (PWA_STATE.offlineNotice) {
            setHidden(PWA_STATE.offlineNotice, false);
        }
    });

    setHidden(PWA_STATE.offlineNotice, window.navigator.onLine);

    syncInstallControls();
}

function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) return;
    if (window.location.protocol !== 'https:' && !isLocalhost()) return;

    window.addEventListener('load', () => {
        navigator.serviceWorker
            .register('/service-worker.js')
            .then((registration) => {
                registration.addEventListener('updatefound', () => {
                    const installingWorker = registration.installing;
                    if (!installingWorker) return;

                    installingWorker.addEventListener('statechange', () => {
                        if (installingWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            // A fresh cache is ready. Reload to use the latest assets.
                            window.location.reload();
                        }
                    });
                });
            })
            .catch(() => {
                // Silent fallback: the site remains usable without offline caching.
            });
    });
}

window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    PWA_STATE.deferredPrompt = event;
    syncInstallControls();
});

window.addEventListener('appinstalled', () => {
    PWA_STATE.installed = true;
    PWA_STATE.deferredPrompt = null;

    hideBanner();

    if (PWA_STATE.headerInstallButton) {
        PWA_STATE.headerInstallButton.hidden = true;
    }

    try {
        window.localStorage.removeItem(PWA_STORAGE_KEY);
    } catch (error) {
        // Ignore storage failures.
    }
});

setupPwaControls();
registerServiceWorker();
setupFirebasePushNotifications();

function setupLeadsReportCustomFieldsModal() {
    const modal = document.querySelector('[data-leads-custom-fields-modal]');
    const trigger = document.querySelector('[data-leads-custom-fields-trigger]');
    if (!modal || !trigger) return;

    const closeButtons = Array.from(modal.querySelectorAll('[data-leads-custom-fields-close]'));
    const backdrop = modal.querySelector('[data-leads-custom-fields-backdrop]');

    const openModal = () => {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    };

    const closeModal = () => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    };

    trigger.addEventListener('click', openModal);
    closeButtons.forEach((button) => button.addEventListener('click', closeModal));
    backdrop?.addEventListener('click', closeModal);

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
            closeModal();
        }
    });

    modal.addEventListener('submit', () => {
        document.body.classList.remove('overflow-hidden');
    }, true);
}

function setupReportFiltersToggle() {
    const trigger = document.querySelector('[data-report-filters-trigger]');
    const panel = document.querySelector('[data-report-filters-panel]');
    if (!trigger || !panel) return;

    const syncState = () => {
        const isOpen = !panel.hasAttribute('hidden');
        trigger.setAttribute('aria-expanded', String(isOpen));
        panel.setAttribute('aria-hidden', String(!isOpen));
    };

    const openPanel = () => {
        panel.removeAttribute('hidden');
        syncState();
    };

    const closePanel = () => {
        panel.setAttribute('hidden', '');
        syncState();
    };

    trigger.addEventListener('click', () => {
        if (panel.hasAttribute('hidden')) {
            openPanel();
        } else {
            closePanel();
        }
    });

    syncState();
}

function setupTaskBlockerModal() {
    if (window.__taskBlockerModalSetup) {
        return;
    }

    const blockerModal = document.getElementById('blocker-modal');
    const blockerForm = document.getElementById('blocker-form');
    const blockerTitle = document.getElementById('blocker-modal-title');
    const blockerLevel = document.getElementById('blocker_level');
    const blockerDescription = document.getElementById('blocker_description');
    const blockerLevelError = document.getElementById('blocker_level_error');
    const blockerDescriptionError = document.getElementById('blocker_description_error');
    const blockerFormError = document.getElementById('blocker_form_error');
    const blockerSaveButton = document.querySelector('[data-blocker-save-button]');

    if (!blockerModal || !blockerForm || !blockerTitle || !blockerLevel || !blockerDescription) {
        return;
    }

    window.__taskBlockerModalSetup = true;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const blockerUpdateUrl = blockerForm.getAttribute('action') || '';
    const blockerClearUrl = document.querySelector('[data-task-blocker-clear-url]')?.getAttribute('data-task-blocker-clear-url') || '';
    const shouldOpenBlockerModal = blockerForm.dataset.openOnLoad === 'true';
    const defaultBlockerData = {
        title: blockerTitle.textContent || 'Add Blocker',
        level: blockerLevel.value || '',
        description: blockerDescription.value || '',
    };
    let blockerSubmissionInProgress = false;

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function clearBlockerErrors() {
        if (blockerLevelError) {
            blockerLevelError.textContent = '';
            blockerLevelError.classList.add('hidden');
        }

        if (blockerDescriptionError) {
            blockerDescriptionError.textContent = '';
            blockerDescriptionError.classList.add('hidden');
        }

        if (blockerFormError) {
            blockerFormError.textContent = '';
            blockerFormError.classList.add('hidden');
        }
    }

    function setFieldError(field, errorEl, message) {
        if (!field || !errorEl) return;

        if (message) {
            errorEl.textContent = message;
            errorEl.classList.remove('hidden');
            field.setAttribute('aria-invalid', 'true');
            field.classList.add('border-rose-300', 'focus:border-rose-400', 'focus:ring-rose-100');
        } else {
            errorEl.textContent = '';
            errorEl.classList.add('hidden');
            field.removeAttribute('aria-invalid');
            field.classList.remove('border-rose-300', 'focus:border-rose-400', 'focus:ring-rose-100');
        }
    }

    function setSubmittingState(isSubmitting) {
        blockerSubmissionInProgress = isSubmitting;

        if (blockerSaveButton) {
            blockerSaveButton.disabled = isSubmitting;
            blockerSaveButton.classList.toggle('opacity-70', isSubmitting);
            blockerSaveButton.classList.toggle('cursor-wait', isSubmitting);
        }
    }

    function updateBlockerTriggerButtons(blocker) {
        document.querySelectorAll('[data-blocker-trigger]').forEach((button) => {
            const label = blocker.has_blocker ? 'Edit Blocker' : 'Add Blocker';
            button.textContent = label;
            button.dataset.blockerMode = blocker.has_blocker ? 'edit' : 'add';
            button.dataset.blockerTitle = blocker.title || label;
            button.dataset.blockerLevel = blocker.level || '';
            button.dataset.blockerDescription = blocker.description || '';
        });
    }

    function renderBlockerPanel(blocker) {
        const panel = document.getElementById('task-blocker-panel');
        if (!panel) return;

        panel.outerHTML = `
            <div id="task-blocker-panel" data-task-blocker-panel data-task-blocker-state="${blocker.has_blocker ? 'active' : 'empty'}">
                ${
                    blocker.has_blocker
                        ? `
                            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-amber-700">Blocker</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-900">Active blocker on this task</dd>
                                    </div>
                                    <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold ${escapeHtml(blocker.level_class || 'bg-emerald-100 text-emerald-800')}">
                                        ${escapeHtml(blocker.level_label || 'Low')}
                                    </span>
                                </div>
                                <div class="mt-4">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Blocker Description</dt>
                                    <dd class="mt-1 whitespace-pre-line text-sm leading-6 text-slate-700">${escapeHtml(blocker.description || '')}</dd>
                                </div>
                                <div class="mt-4 flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        data-blocker-trigger
                                        data-blocker-mode="edit"
                                        data-blocker-title="${escapeHtml(blocker.title || 'Edit Blocker')}"
                                        data-blocker-level="${escapeHtml(blocker.level || '')}"
                                        data-blocker-description="${escapeHtml(blocker.description || '')}"
                                        class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                                    >
                                        Edit
                                    </button>
                                    <form method="POST" action="${blockerClearUrl}" onsubmit="return confirm('Resolve and remove this blocker?');">
                                        <input type="hidden" name="_token" value="${csrfToken}">
                                        <input type="hidden" name="_method" value="DELETE">
                                        <button type="submit" class="inline-flex items-center justify-center rounded-xl border border-rose-200 bg-white px-4 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                                            Resolve
                                        </button>
                                    </form>
                                </div>
                            </div>
                        `
                        : `
                            <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50/70 p-4">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-slate-400">Blocker</dt>
                                        <dd class="mt-1 text-sm font-medium text-slate-600">No blocker added yet.</dd>
                                    </div>
                                    <button
                                        type="button"
                                        data-blocker-trigger
                                        data-blocker-mode="add"
                                        data-blocker-title="Add Blocker"
                                        data-blocker-level=""
                                        data-blocker-description=""
                                        class="inline-flex items-center justify-center rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700"
                                    >
                                        Add Blocker
                                    </button>
                                </div>
                            </div>
                        `
                }
            </div>
        `;
    }

    function openBlockerModal(button = null) {
        clearBlockerErrors();

        const data = button ? {
            title: button.dataset.blockerTitle || defaultBlockerData.title,
            level: button.dataset.blockerLevel ?? defaultBlockerData.level,
            description: button.dataset.blockerDescription ?? defaultBlockerData.description,
        } : defaultBlockerData;

        if (blockerTitle) blockerTitle.textContent = data.title;
        if (blockerLevel) blockerLevel.value = data.level || '';
        if (blockerDescription) blockerDescription.value = data.description || '';

        blockerModal.classList.remove('hidden');
        blockerModal.classList.add('flex');
        blockerModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');

        blockerDescription.focus();
    }

    window.openTaskBlockerModal = function (button) {
        openBlockerModal(button || null);
        return false;
    };

    function closeBlockerModal() {
        blockerModal.classList.add('hidden');
        blockerModal.classList.remove('flex');
        blockerModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        clearBlockerErrors();
        setSubmittingState(false);
    }

    document.addEventListener('click', function (event) {
        const button = event.target.closest('[data-blocker-trigger]');

        if (!button) return;

        event.preventDefault();
        openBlockerModal(button);
    }, true);

    document.querySelectorAll('[data-close-blocker-modal]').forEach((button) => button.addEventListener('click', closeBlockerModal));

    blockerModal.addEventListener('click', (event) => {
        if (event.target === blockerModal) closeBlockerModal();
    });

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !blockerModal.classList.contains('hidden')) {
            closeBlockerModal();
        }
    });

    blockerForm.addEventListener('submit', async function (event) {
        event.preventDefault();

        clearBlockerErrors();

        const level = String(blockerLevel?.value || '').trim();
        const description = String(blockerDescription?.value || '').trim();
        let hasValidationErrors = false;

        if (!level) {
            setFieldError(blockerLevel, blockerLevelError, 'Please select a blocker type.');
            hasValidationErrors = true;
        } else {
            setFieldError(blockerLevel, blockerLevelError, '');
        }

        if (!description) {
            setFieldError(blockerDescription, blockerDescriptionError, 'Please enter a blocker description.');
            hasValidationErrors = true;
        } else {
            setFieldError(blockerDescription, blockerDescriptionError, '');
        }

        if (hasValidationErrors || blockerSubmissionInProgress) {
            return;
        }

        setSubmittingState(true);

        try {
            const response = await fetch(blockerUpdateUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
                body: new FormData(blockerForm),
            });

            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                if (response.status === 422 && payload.errors) {
                    if (payload.errors.blocker_level?.length) {
                        setFieldError(blockerLevel, blockerLevelError, payload.errors.blocker_level[0]);
                    }
                    if (payload.errors.blocker_description?.length) {
                        setFieldError(blockerDescription, blockerDescriptionError, payload.errors.blocker_description[0]);
                    }
                    if (!payload.errors.blocker_level?.length && !payload.errors.blocker_description?.length && blockerFormError) {
                        blockerFormError.textContent = payload.message || 'Please review the blocker details.';
                        blockerFormError.classList.remove('hidden');
                    }
                    return;
                }

                if (blockerFormError) {
                    blockerFormError.textContent = payload.message || 'Unable to save blocker right now.';
                    blockerFormError.classList.remove('hidden');
                }

                return;
            }

            const blocker = payload.blocker || {};
            updateBlockerTriggerButtons(blocker);
            renderBlockerPanel(blocker);
            closeBlockerModal();
        } catch (error) {
            if (blockerFormError) {
                blockerFormError.textContent = 'Unable to save blocker right now. Please try again.';
                blockerFormError.classList.remove('hidden');
            }
        } finally {
            setSubmittingState(false);
        }
    });

    if (shouldOpenBlockerModal) {
        openBlockerModal();
    }
}

function setupDatePickerEnhancement() {
    document.addEventListener('click', (e) => {
        const input = e.target.closest?.('input[type="date"], input[type="datetime-local"], input[type="month"]');
        if (!input || input.disabled || input.readOnly || typeof input.showPicker !== 'function') {
            return;
        }

        const rect = input.getBoundingClientRect();
        const isRtl = document.dir === 'rtl' || getComputedStyle(input).direction === 'rtl';
        const isIconZone = isRtl
            ? (e.clientX - rect.left <= 44)
            : (rect.right - e.clientX <= 44);

        if (isIconZone) {
            try {
                input.showPicker();
            } catch (err) {
                // Safely ignore if already opened natively or not permitted
            }
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupLeadsReportCustomFieldsModal);
    document.addEventListener('DOMContentLoaded', setupReportFiltersToggle);
    document.addEventListener('DOMContentLoaded', setupTaskBlockerModal);
    document.addEventListener('DOMContentLoaded', setupDatePickerEnhancement);
} else {
    setupLeadsReportCustomFieldsModal();
    setupReportFiltersToggle();
    setupTaskBlockerModal();
    setupDatePickerEnhancement();
}
