<?php
header('Content-Type: application/javascript');
header('Service-Worker-Allowed: /');
?>
const CACHE_NAME = 'lsc-v1';
const OFFLINE_URL = '/offline.php';

const PRECACHE_ASSETS = [
    '/',
    '/offline.php',
    '/login.php',
];

self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_NAME).then(cache => {
            return cache.addAll(PRECACHE_ASSETS);
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys().then(keys =>
            Promise.all(
                keys
                    .filter(key => key !== CACHE_NAME)
                    .map(key => caches.delete(key))
            )
        ).then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);

    if (request.method !== 'GET') return;

    if (request.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(
            fetch(request)
                .then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                    }
                    return response;
                })
                .catch(() =>
                    caches.match(request).then(cached =>
                        cached || caches.match(OFFLINE_URL)
                    )
                )
        );
        return;
    }

    if (url.pathname.match(/\.(css|js|woff2?|ttf|eot|png|jpg|jpeg|svg|ico)$/) ||
        url.hostname.includes('fonts.') ||
        url.hostname.includes('cdn.jsdelivr.net')) {
        event.respondWith(
            caches.match(request).then(cached => {
                if (cached) return cached;
                return fetch(request).then(response => {
                    if (response.ok) {
                        const clone = response.clone();
                        caches.open(CACHE_NAME).then(cache => cache.put(request, clone));
                    }
                    return response;
                });
            })
        );
    }
});
