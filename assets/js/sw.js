/**
 * ZSEM Tech PWA Service Worker (v2.0)
 * Provides offline caching, network-first strategy for dynamic queries,
 * and background offline sync capabilities.
 */
const CACHE_NAME = 'zsem-tech-v2.1.0';
const STATIC_ASSETS = [
    './assets/css/style.css',
    './assets/css/dashboard-new.css',
    './assets/css/fonts.css',
    './assets/js/theme-handler.js',
    './assets/js/devtools-guard.js',
    './assets/js/performance-metrics.js',
    './assets/js/app-dialogs.js',
    './assets/js/offline-engine.js',
    './data_question/inf02.json',
    './data_question/inf03.json',
    './data_question/inf04.json',
    './data_question/inf07.json',
    './data_question/inf08.json',
    './zsemtech_profile.ico',
];

// Install Event: Pre-cache core application shell and question datasets
self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('[PWA SW] Pre-cache non-fatal warning:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

// Background Sync Event: Trigger offline sync when connection is restored
self.addEventListener('sync', (event) => {
    if (event.tag === 'sync-offline-progress') {
        event.waitUntil(
            self.clients.matchAll().then((clients) => {
                clients.forEach((client) => {
                    client.postMessage({ type: 'TRIGGER_OFFLINE_SYNC' });
                });
            })
        );
    }
});

// Activate Event: Clean up stale legacy caches
self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((cacheNames) => {
            return Promise.all(
                cacheNames.map((name) => {
                    if (name !== CACHE_NAME) {
                        return caches.delete(name);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Fetch Event: Cache-First for static assets, Network-First for API/pages
self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Only handle GET requests
    if (request.method !== 'GET') {
        return;
    }

    // Skip non-origin requests except CDN assets
    if (!url.origin.includes(self.location.origin) && !url.origin.includes('cdn.jsdelivr.net')) {
        return;
    }

    // Static CSS/JS/Fonts -> Cache First with Network Fallback
    if (request.destination === 'style' || request.destination === 'script' || request.destination === 'font' || request.destination === 'image') {
        event.respondWith(
            caches.match(request).then((cached) => {
                if (cached) return cached;
                return fetch(request).then((response) => {
                    if (response && response.status === 200) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(request, clone));
                    }
                    return response;
                }).catch(() => caches.match('./zsemtech_profile.ico'));
            })
        );
        return;
    }

    // HTML / Dynamic Pages -> Network First with Offline Fallback
    event.respondWith(
        fetch(request).catch(() => {
            return caches.match(request).then((cached) => {
                if (cached) return cached;
                return new Response(
                    '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><title>Brak połączenia — ZSEM Tech</title><link rel="stylesheet" href="./assets/css/style.css"></head><body style="font-family:sans-serif;padding:3rem;text-align:center;background:#0f172a;color:#fff;"><h1>📡 Jesteś w trybie offline</h1><p>Twoje postępy zostaną automatycznie zsynchronizowane po przywróceniu połączenia internetowego.</p><button onclick="window.location.reload()" style="background:#6366f1;color:#fff;border:0;padding:10px 20px;border-radius:8px;cursor:pointer;">Spróbuj ponownie</button></body></html>',
                    { headers: { 'Content-Type': 'text/html; charset=utf-8' } }
                );
            });
        })
    );
});
