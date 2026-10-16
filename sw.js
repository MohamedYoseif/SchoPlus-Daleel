/**
 * SchoPlus Schools Directory (daleel.schoplus.sd)
 * Progressive Web App (PWA) Service Worker v3.7.2
 */

const CACHE_NAME = 'daleel-pwa-v3.7.3';
const STATIC_ASSETS = [
    '/',
    '/manifest.json',
    '/assets/images/logo.png?v=3.7.3',
    '/assets/images/logo-icon.png?v=3.7.3',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.rtl.min.css',
    'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'
];

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches.open(CACHE_NAME).then((cache) => {
            return cache.addAll(STATIC_ASSETS).catch((err) => {
                console.warn('[PWA SW] Pre-caching partial failure:', err);
            });
        }).then(() => self.skipWaiting())
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys().then((keys) => {
            return Promise.all(
                keys.map((key) => {
                    if (key !== CACHE_NAME) {
                        return caches.delete(key);
                    }
                })
            );
        }).then(() => self.clients.claim())
    );
});

// Network first, falling back to cache
self.addEventListener('fetch', (event) => {
    if (event.request.method !== 'GET') return;
    
    // Ignore chrome-extension or external analytics
    const url = new URL(event.request.url);
    if (url.protocol !== 'http:' && url.protocol !== 'https:') return;

    // For static images / fonts: cache first
    if (url.pathname.match(/\.(png|jpg|jpeg|svg|webp|woff2|woff|ttf|css|js)$/)) {
        event.respondWith(
            caches.match(event.request).then((cachedResponse) => {
                if (cachedResponse) return cachedResponse;
                return fetch(event.request).then((networkResponse) => {
                    if (networkResponse && networkResponse.status === 200) {
                        const copy = networkResponse.clone();
                        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
                    }
                    return networkResponse;
                }).catch(() => cachedResponse);
            })
        );
        return;
    }

    // For HTML/PHP navigation requests: network first with offline cache fallback
    event.respondWith(
        fetch(event.request).then((networkResponse) => {
            if (networkResponse && networkResponse.status === 200) {
                const copy = networkResponse.clone();
                caches.open(CACHE_NAME).then((cache) => cache.put(event.request, copy));
            }
            return networkResponse;
        }).catch(() => {
            return caches.match(event.request).then((cached) => {
                if (cached) return cached;
                return caches.match('/');
            });
        })
    );
});
