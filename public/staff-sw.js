const CACHE_NAME = 'playdrive-staff-v3'
const APP_SHELL = [
  '/staff/',
  '/staff',
  '/staff.webmanifest?v=3',
  '/images/logos/icon-192.png',
  '/images/logos/icon-512.png',
  '/images/logos/apple-touch-icon.png',
]

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(APP_SHELL)).catch(() => undefined)
  )
  self.skipWaiting()
})

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys().then((keys) => Promise.all(keys.filter((key) => key !== CACHE_NAME).map((key) => caches.delete(key)))).then(() => self.clients.claim())
  )
})

self.addEventListener('fetch', (event) => {
  if (event.request.method !== 'GET') {
    return
  }

  const url = new URL(event.request.url)

  if (url.origin !== self.location.origin) {
    return
  }

  if (url.pathname.startsWith('/staff')) {
    event.respondWith(
      fetch(event.request)
        .then((response) => {
          const clone = response.clone()
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone)).catch(() => undefined)
          return response
        })
        .catch(() => caches.match(event.request).then((cached) => cached || caches.match('/staff/')))
    )
    return
  }

  if (url.pathname.startsWith('/images/logos/')) {
    event.respondWith(
      caches.match(event.request).then((cached) => {
        if (cached) {
          return cached
        }

        return fetch(event.request).then((response) => {
          const clone = response.clone()
          caches.open(CACHE_NAME).then((cache) => cache.put(event.request, clone)).catch(() => undefined)
          return response
        })
      })
    )
  }
})
