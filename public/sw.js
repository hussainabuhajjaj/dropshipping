const CACHE_NAME = 'simbazu-shell-v3';
const CORE_ASSETS = ['/manifest.webmanifest', '/images/category-default.png'];

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(CORE_ASSETS)).catch(() => null)
  );
  self.skipWaiting();
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))
    )
  );
  self.clients.claim();
});

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return;
  }

  const requestUrl = new URL(event.request.url);
  if (requestUrl.origin !== self.location.origin) {
    return;
  }

  const accept = event.request.headers.get('accept') || '';
  const isHtmlRequest = event.request.mode === 'navigate' || accept.includes('text/html');

  // Never intercept dynamic HTML/Inertia documents.
  // If we respondWith(fetch()) and it fails, it turns navigations into opaque network errors.
  // Let the browser handle navigations normally.
  if (isHtmlRequest) {
    return;
  }

  event.respondWith(
    fetch(event.request)
      .then((response) => {
        if (!response || response.status !== 200 || response.type !== 'basic') {
          return response;
        }

        const cloned = response.clone();
        caches.open(CACHE_NAME).then((cache) => cache.put(event.request, cloned)).catch(() => null);
        return response;
      })
      .catch(async () => {
        const cached = await caches.match(event.request);
        return cached || new Response('', { status: 504, statusText: 'Offline' });
      })
  );
});
