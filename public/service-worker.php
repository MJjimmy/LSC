<?php
header('Content-Type: application/javascript');
header('Service-Worker-Allowed: /');
header('Cache-Control: no-cache');
?>
const CACHE_NAME = 'lsc-public-v3';
const OFFLINE_URL = '/offline.php';
// Only application-owned, non-personal resources may enter Cache Storage.
const PUBLIC_ASSETS = ['/icons/lsc-icon.svg', '/icons/lsc-og.jpg'];
const PRECACHE_ASSETS = [OFFLINE_URL, ...PUBLIC_ASSETS];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(cache => cache.addAll(PRECACHE_ASSETS.map(path =>
                new Request(new URL(path, self.location.origin), {credentials: 'omit', cache: 'reload', redirect: 'error'})
            )))
            .then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys => Promise.all(
            keys.filter(key => key.startsWith('lsc-') && key !== CACHE_NAME)
                .map(key => caches.delete(key))
        )).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    if (request.method !== 'GET' || url.origin !== self.location.origin) return;
    // Let the browser handle authentication redirects, including lscapp://.
    // Wrapping these navigations in fetch() can replace a native redirect with offline HTML.
    if (url.pathname === '/app-auth.php' || url.pathname === '/oauth.php') return;

    if (request.mode === 'navigate' || request.headers.get('Accept')?.includes('text/html')) {
        // Never store or replay documents, including login/reset pages.
        // Bypass HTTP cache as well as the worker's Cache Storage.
        event.respondWith(fetch(request, {cache: 'no-store'}).catch(() =>
            caches.open(CACHE_NAME).then(cache => cache.match(OFFLINE_URL)).then(cached =>
                cached || new Response('You are offline. Reconnect and try again.', {
                    status: 503, headers: {'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store'}
                })
            )
        ));
        return;
    }

    if (!url.search && PUBLIC_ASSETS.includes(url.pathname)) {
        // Precached public icons only; no arbitrary extension/CDN rule.
        event.respondWith(caches.open(CACHE_NAME).then(cache => cache.match(url.pathname))
            .then(cached => cached || fetch(request, {credentials: 'omit'})));
    }
});
