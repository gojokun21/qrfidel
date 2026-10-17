/*
 * Service worker minimal al PWA-ului client (SDD §2, ADR 010):
 * - precache: pagina offline și iconițele;
 * - /build/assets/* (nume cu hash, deci imuabile): cache-first;
 * - navigările din zona client: network-first, cu pagina offline ca rezervă. Scope-ul e `/` (clientul trece
 *   prin /b/{slug} și /login/magic), dar dashboard-ul staff-ului și restul rămân neatinse.
 * Paginile autentificate și răspunsurile QR nu se păstrează niciodată în cache (date personale, CSRF).
 */
const VERSION = 'v4';
const SHELL = `shell-${VERSION}`;
const ASSETS = `assets-${VERSION}`;
const OFFLINE_URL = '/offline.html';

const CUSTOMER_PATHS = [/^\/me(\/|$)/, /^\/b\//, /^\/l\//, /^\/login\/magic(\/|$)/];
const MAX_ASSETS = 30;

const PRECACHE = [OFFLINE_URL, '/icons/icon-192.png', '/icons/icon-512.png', '/manifest.webmanifest'];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(SHELL).then((cache) => cache.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((key) => key !== SHELL && key !== ASSETS).map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET' || url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate' && CUSTOMER_PATHS.some((pattern) => pattern.test(url.pathname))) {
        event.respondWith(fetch(request).catch(() => caches.match(OFFLINE_URL)));
        return;
    }

    if (url.pathname.startsWith('/build/assets/')) {
        event.respondWith(
            caches.open(ASSETS).then((cache) => cache.match(request).then((cached) => cached || fetch(request).then((response) => {
                if (response.ok) {
                    cache.put(request, response.clone()).then(() => trim(cache));
                }
                return response;
            }))),
        );
        return;
    }

    if (PRECACHE.includes(url.pathname)) {
        event.respondWith(caches.match(request).then((cached) => cached || fetch(request)));
    }
});

// Fiecare deploy aduce bundle-uri noi (alt hash); cele vechi ies din cache, cele mai vechi primele.
async function trim(cache) {
    const keys = await cache.keys();
    await Promise.all(keys.slice(0, Math.max(0, keys.length - MAX_ASSETS)).map((key) => cache.delete(key)));
}
