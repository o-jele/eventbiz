/* Glamorous service worker: offline-first static shell, network-first pages.
   Never caches POSTs or admin mutations — GET only. Bump VERSION to refresh. */
const VERSION = 'glam-v1';
const CORE = [
  '/',
  '/assets/style.css',
  '/assets/img/logo-creations.jpg',
  '/assets/img/logo-delights.jpg',
  '/assets/icons/icon-192.png'
];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(VERSION).then((cache) => cache.addAll(CORE)).then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((k) => k !== VERSION).map((k) => caches.delete(k)))
    ).then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') {
    return;
  }
  const url = new URL(req.url);
  if (url.origin !== self.location.origin) {
    return; // fonts and other third parties go direct
  }
  if (req.mode === 'navigate') {
    // Pages: network first, fall back to cached homepage offline.
    event.respondWith(
      fetch(req).then((res) => {
        const copy = res.clone();
        caches.open(VERSION).then((cache) => cache.put(req, copy));
        return res;
      }).catch(() => caches.match('/'))
    );
    return;
  }
  // Static assets: cache first, refresh in background.
  event.respondWith(
    caches.match(req).then((hit) => {
      const net = fetch(req).then((res) => {
        if (res && res.ok) {
          const copy = res.clone();
          caches.open(VERSION).then((cache) => cache.put(req, copy));
        }
        return res;
      }).catch(() => hit);
      return hit || net;
    })
  );
});
