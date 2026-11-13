import { initializeApp, getApps } from 'firebase/app';
import { deleteToken, getMessaging, getToken, onMessage } from 'firebase/messaging';

const DEFAULT_ICON = '/assets/images/logo.png';

const state = {
    config: window.__firebasePushConfig || {},
    app: null,
    messaging: null,
    serviceWorkerRegistration: null,
    ready: false,
};

function isConfigured() {
    return Boolean(state.config.enabled && state.config.updatePreferenceUrl && state.config.registerTokenUrl && state.config.unregisterTokenUrl);
}

function hasBrowserSupport() {
    return 'Notification' in window && 'serviceWorker' in navigator;
}

function canUseServiceWorker() {
    return window.location.protocol === 'https:' || ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);
}

function getFirebaseConfig() {
    return state.config.clientConfig || {};
}

function updateStatusText(message, permission = window.Notification?.permission || 'default') {
    document.querySelectorAll('[data-push-status-text]').forEach((element) => {
        element.textContent = message;
    });

    document.querySelectorAll('[data-push-permission-text]').forEach((element) => {
        element.textContent = permission === 'granted' ? 'Granted' : permission === 'denied' ? 'Denied' : 'Not requested yet';
    });
}

function setButtonsDisabled(disabled) {
    document.querySelectorAll('[data-push-enable], [data-push-disable]').forEach((button) => {
        button.disabled = disabled;
    });
}

function csrfHeaders() {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    return {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
    };
}

async function postJson(url, payload, method = 'POST') {
    const response = await fetch(url, {
        method,
        headers: {
            ...csrfHeaders(),
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const errorMessage = data?.message || Object.values(data?.errors || {})?.flat()?.[0] || 'Request failed.';
        throw new Error(errorMessage);
    }

    return data;
}

async function syncPreference(enabled) {
    if (!state.config.updatePreferenceUrl) return;

    await postJson(state.config.updatePreferenceUrl, {
        push_enabled: Boolean(enabled),
    }, 'PUT');
}

async function registerToken(token) {
    if (!state.config.registerTokenUrl) return;

    await postJson(state.config.registerTokenUrl, {
        token,
        browser_name: navigator.userAgentData?.brands?.[0]?.brand || navigator.userAgent,
        platform_name: navigator.userAgentData?.platform || navigator.platform,
        user_agent: navigator.userAgent,
    });
}

async function unregisterToken(token) {
    if (!state.config.unregisterTokenUrl) return;

    await postJson(state.config.unregisterTokenUrl, {
        token,
    }, 'DELETE');
}

async function getCurrentToken() {
    const firebaseConfig = getFirebaseConfig();

    if (!state.messaging || !state.serviceWorkerRegistration || !firebaseConfig.vapidKey) {
        return null;
    }

    return getToken(state.messaging, {
        vapidKey: firebaseConfig.vapidKey,
        serviceWorkerRegistration: state.serviceWorkerRegistration,
    });
}

async function ensureTokenRegistered() {
    if (!isConfigured() || !hasBrowserSupport() || window.Notification.permission !== 'granted') {
        return;
    }

    const token = await getCurrentToken();
    if (!token) {
        return;
    }

    await registerToken(token);
    updateStatusText('Enabled on this device', 'granted');
}

async function enablePushNotifications() {
    if (!isConfigured() || !hasBrowserSupport()) {
        if (window.appAlert) {
            window.appAlert('Firebase push notifications are not configured yet.', 'Push Notifications', 'warning');
        }
        return;
    }

    setButtonsDisabled(true);

    try {
        const permission = await window.Notification.requestPermission();
        if (permission !== 'granted') {
            updateStatusText(permission === 'denied' ? 'Permission denied' : 'Permission not granted', permission);
            return;
        }

        await syncPreference(true);
        const token = await getCurrentToken();
        if (!token) {
            throw new Error('Unable to obtain a Firebase token for this browser.');
        }

        await registerToken(token);
        updateStatusText('Enabled on this device', 'granted');

        if (window.appAlert) {
            window.appAlert('Windows notifications are now enabled on this device.', 'Push Notifications', 'success');
        }
    } catch (error) {
        try {
            await syncPreference(false);
        } catch (syncError) {
            // Ignore rollback failures; the user can retry from the profile screen.
        }

        updateStatusText(error.message || 'Failed to enable notifications.', window.Notification.permission);

        if (window.appAlert) {
            window.appAlert(error.message || 'Failed to enable notifications.', 'Push Notifications', 'error');
        }
    } finally {
        setButtonsDisabled(false);
    }
}

async function disablePushNotifications() {
    if (!isConfigured() || !hasBrowserSupport()) {
        return;
    }

    setButtonsDisabled(true);

    try {
        const token = await getCurrentToken();
        if (token) {
            await unregisterToken(token);
            await deleteToken(state.messaging);
        }

        await syncPreference(false);
        updateStatusText('Disabled on this account', window.Notification.permission);

        if (window.appAlert) {
            window.appAlert('Windows notifications are now disabled on this device.', 'Push Notifications', 'info');
        }
    } catch (error) {
        if (window.appAlert) {
            window.appAlert(error.message || 'Failed to disable notifications.', 'Push Notifications', 'error');
        }
    } finally {
        setButtonsDisabled(false);
    }
}

function bindButtons() {
    document.querySelectorAll('[data-push-enable]').forEach((button) => {
        button.addEventListener('click', () => {
            enablePushNotifications();
        });
    });

    document.querySelectorAll('[data-push-disable]').forEach((button) => {
        button.addEventListener('click', () => {
            disablePushNotifications();
        });
    });
}

function showForegroundNotification(payload) {
    if (window.Notification.permission !== 'granted') {
        return;
    }

    const title = payload?.notification?.title || payload?.data?.title || 'Notification';
    const body = payload?.notification?.body || payload?.data?.body || '';
    const url = payload?.data?.url || '/dashboard';

    try {
        const notification = new window.Notification(title, {
            body,
            icon: payload?.notification?.icon || DEFAULT_ICON,
            data: { url },
        });

        notification.onclick = () => {
            window.focus();
            window.location.assign(url);
        };
    } catch (error) {
        // Silent fallback: if the browser blocks Notification constructor, the message still arrived.
    }
}

export async function setupFirebasePushNotifications() {
    if (!isConfigured() || !hasBrowserSupport()) {
        return;
    }

    if (!canUseServiceWorker()) {
        updateStatusText('Notifications require HTTPS or localhost', window.Notification.permission);
        return;
    }

    const firebaseConfig = getFirebaseConfig();

    if (!firebaseConfig.apiKey || !firebaseConfig.projectId || !firebaseConfig.messagingSenderId || !firebaseConfig.appId) {
        updateStatusText('Missing Firebase config from server', window.Notification.permission);
        return;
    }

    const app = getApps().length ? getApps()[0] : initializeApp(firebaseConfig);
    state.app = app;
    state.messaging = getMessaging(app);

    bindButtons();

    onMessage(state.messaging, (payload) => {
        showForegroundNotification(payload);
    });

    try {
        state.serviceWorkerRegistration = await navigator.serviceWorker.ready;
        state.ready = true;
    } catch (error) {
        updateStatusText('Service worker failed to activate', window.Notification.permission);
        return;
    }

    const permission = window.Notification.permission;

    if (state.config.prefersPush && permission === 'granted') {
        await ensureTokenRegistered();
        return;
    }

    updateStatusText(
        state.config.prefersPush
            ? (permission === 'denied' ? 'Permission denied in this browser' : 'Awaiting permission approval')
            : 'Disabled on this account',
        permission
    );
}
