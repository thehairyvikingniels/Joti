const CACHE_NAME = 'jotihunt-kiosk-v4';
const OFFLINE_URL = '/offline.php';

self.addEventListener('install', function(event) {
  event.waitUntil(
    caches.open(CACHE_NAME).then(function(cache) {
      return cache.add(new Request(OFFLINE_URL, { cache: 'reload' }));
    })
  );
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  event.waitUntil(
    caches.keys().then(function(cacheNames) {
      return Promise.all(
        cacheNames.map(function(cacheName) {
          if (cacheName !== CACHE_NAME) {
            return caches.delete(cacheName);
          }
        })
      );
    }).then(function() {
      return clients.claim();
    })
  );
});

self.addEventListener('fetch', function(event) {
  if (event.request.mode === 'navigate') {
    event.respondWith(
      fetch(event.request).catch(function() {
        return caches.match(OFFLINE_URL);
      })
    );
  } else if (event.request.destination === 'image') {
    event.respondWith(
      caches.match(event.request).then(function(cachedResponse) {
        const networkFetch = fetch(event.request).then(function(response) {
          const cacheCopy = response.clone();
          caches.open(CACHE_NAME).then(function(cache) {
            cache.put(event.request, cacheCopy);
          });
          return response;
        }).catch(function() {
          return cachedResponse;
        });
        return cachedResponse || networkFetch;
      })
    );
  }
});

self.addEventListener('push', function(event) {
  if (!(self.Notification && self.Notification.permission === 'granted')) {
    return;
  }

  let data = {};
  if (event.data) {
    try {
      data = event.data.json();
    } catch (e) {
      data = { title: 'Nieuwe Notificatie', body: event.data.text() };
    }
  }

  const title = data.title || 'Jotify';
  const options = {
    body: data.body || 'Er is een nieuwe update!',
    icon: 'media/geusje_bevosd.png',
    badge: 'media/geusje.png',
    vibrate: [200, 100, 200, 100, 200, 100, 200],
    data: {
      url: data.url || '/'
    }
  };

  event.waitUntil(self.registration.showNotification(title, options));
});

self.addEventListener('notificationclick', function(event) {
  event.notification.close();
  const urlToOpen = event.notification.data.url || '/';

  event.waitUntil(
    clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function(windowClients) {
      for (let i = 0; i < windowClients.length; i++) {
        const client = windowClients[i];
        if (client.url.indexOf(urlToOpen) >= 0 && 'focus' in client) {
          return client.focus();
        }
      }
      if (clients.openWindow) {
        return clients.openWindow(urlToOpen);
      }
    })
  );
});
