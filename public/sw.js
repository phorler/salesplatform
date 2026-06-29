// Minimal service worker: enables installability and offline access to the app
// shell. Network-first so we never serve stale server-rendered pages; cached
// static assets are a fallback when offline.

const CACHE = 'sp-v1';
const ASSETS = [
    '/manifest.webmanifest',
    '/icons/icon-192.png',
    '/icons/icon-512.png',
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE).then((cache) => cache.addAll(ASSETS)).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
            .then(() => self.clients.claim())
    );
});

self.addEventListener('fetch', (event) => {
    const { request } = event;
    if (request.method !== 'GET') return;

    event.respondWith(
        fetch(request).catch(() => caches.match(request))
    );
});
