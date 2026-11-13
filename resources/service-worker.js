const CACHE_VERSION = '2026-06-22';
const SHELL_CACHE = `shell-${CACHE_VERSION}`;
const ASSET_CACHE = `assets-${CACHE_VERSION}`;
const PAGE_CACHE = `pages-${CACHE_VERSION}`;
const FALLBACK_URL = '/offline.html';
const PRECACHE_URLS = [
    '/',
    '/manifest.json',
    FALLBACK_URL,
    '/assets/images/logo.png',
    '/pwa/icon-512.png',
];

try {
    try {
        importScripts('/firebase-config.js');
        importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
        importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');
    } catch (e) {
        // Continue without firebase if offline or scripts cannot load
    }

    if (self.FIREBASE_CONFIG && !firebase.apps.length) {
        firebase.initializeApp(self.FIREBASE_CONFIG);
    }

    if (self.firebase?.messaging) {
        const messaging = firebase.messaging();

        messaging.onBackgroundMessage((payload) => {
            const title = payload?.notification?.title || payload?.data?.title || 'Notification';
            const options = {
                body: payload?.notification?.body || payload?.data?.body || '',
                icon: payload?.notification?.icon || '/assets/images/logo.png',
                badge: payload?.notification?.badge || '/assets/images/logo.png',
                data: {
                    url: payload?.data?.url || '/dashboard',
                },
                tag: payload?.notification?.tag || payload?.data?.tag || 'dashboard-notification',
                renotify: true,
                requireInteraction: true,
            };

            self.registration.showNotification(title, options);
        });
    }
} catch (error) {
    // Firebase is optional until the app is configured with the required env vars.
}

function isSameOrigin(requestUrl) {
    return requestUrl.origin === self.location.origin;
}

function isAssetRequest(requestUrl) {
    return (
        requestUrl.pathname.startsWith('/build/') ||
        requestUrl.pathname.startsWith('/storage/') ||
        requestUrl.pathname.startsWith('/pwa/') ||
        requestUrl.pathname.endsWith('.css') ||
        requestUrl.pathname.endsWith('.js') ||
        requestUrl.pathname.endsWith('.png') ||
        requestUrl.pathname.endsWith('.jpg') ||
        requestUrl.pathname.endsWith('.jpeg') ||
        requestUrl.pathname.endsWith('.webp') ||
        requestUrl.pathname.endsWith('.svg') ||
        requestUrl.pathname.endsWith('.ico') ||
        requestUrl.pathname.endsWith('.woff') ||
        requestUrl.pathname.endsWith('.woff2')
    );
}

async function cacheBuildManifestAssets() {
    const response = await fetch('/build/manifest.json', { cache: 'no-store' });

    if (!response.ok) {
        return;
    }

    const manifest = await response.json();
    const urls = new Set(['/build/manifest.json']);

    const visit = (value) => {
        if (!value) return;

        if (typeof value === 'string') {
            if (value.startsWith('http://') || value.startsWith('https://')) return;
            if (value.startsWith('/')) {
                urls.add(value);
                return;
            }
            urls.add(value.startsWith('build/') ? `/${value}` : `/build/${value}`);
            return;
        }

        if (Array.isArray(value)) {
            value.forEach(visit);
            return;
        }

        if (typeof value === 'object') {
            Object.values(value).forEach(visit);
        }
    };

    Object.values(manifest).forEach(visit);

    const cache = await caches.open(ASSET_CACHE);

    await Promise.all(
        Array.from(urls).map(async (url) => {
            try {
                const assetResponse = await fetch(url, { cache: 'no-store' });
                if (assetResponse.ok) {
                    await cache.put(url, assetResponse);
                }
            } catch (error) {
                // Ignore individual asset failures and keep the service worker install resilient.
            }
        })
    );
}

async function warmCoreCache() {
    const cache = await caches.open(SHELL_CACHE);

    await Promise.all(
        PRECACHE_URLS.map(async (url) => {
            try {
                const response = await fetch(url, { cache: 'no-store' });
                if (response.ok) {
                    await cache.put(url, response);
                }
            } catch (error) {
                // Ignore individual shell failures so install can continue.
            }
        })
    );
}

async function networkFirstPage(request) {
    const cache = await caches.open(PAGE_CACHE);

    try {
        const response = await fetch(request);
        if (response && response.ok && response.type === 'basic') {
            cache.put(request, response.clone());
        }
        return response;
    } catch (error) {
        const cached = await cache.match(request);
        if (cached) {
            return cached;
        }

        const fallback = await caches.match(FALLBACK_URL);
        if (fallback) {
            return fallback;
        }

        throw error;
    }
}

async function cacheFirst(request) {
    const cache = await caches.open(ASSET_CACHE);
    const cached = await cache.match(request);

    if (cached) {
        return cached;
    }

    const response = await fetch(request);
    if (response && response.ok && response.type === 'basic') {
        cache.put(request, response.clone());
    }

    return response;
}

self.addEventListener('install', (event) => {
    event.waitUntil((async () => {
        await warmCoreCache();
        await cacheBuildManifestAssets();
        self.skipWaiting();
    })());
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const expectedCaches = new Set([SHELL_CACHE, ASSET_CACHE, PAGE_CACHE]);
        const keys = await caches.keys();
        await Promise.all(
            keys
                .filter((key) => !expectedCaches.has(key))
                .map((key) => caches.delete(key))
        );
        await self.clients.claim();
    })());
});

self.addEventListener('fetch', (event) => {
    const { request } = event;

    if (request.method !== 'GET') {
        return;
    }

    const requestUrl = new URL(request.url);

    if (!isSameOrigin(requestUrl)) {
        return;
    }

    if (request.mode === 'navigate') {
        event.respondWith(networkFirstPage(request));
        return;
    }

    if (isAssetRequest(requestUrl)) {
        event.respondWith(cacheFirst(request));
        return;
    }

    event.respondWith(
        fetch(request).catch(async () => {
            const cached = await caches.match(request);
            return cached || Response.error();
        })
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = event.notification?.data?.url || '/dashboard';

    event.waitUntil((async () => {
        const clientsList = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });

        for (const client of clientsList) {
            if ('focus' in client) {
                try {
                    const clientUrl = new URL(client.url);
                    const target = new URL(targetUrl, self.location.origin);

                    if (clientUrl.origin === target.origin && clientUrl.pathname === target.pathname) {
                        return client.focus();
                    }
                } catch (error) {
                    // Ignore URL parsing issues and continue opening a new window.
                }
            }
        }

        if (self.clients.openWindow) {
            return self.clients.openWindow(targetUrl);
        }

        return null;
    })());
});
