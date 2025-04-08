// sw.js - Service Worker for Caching

const CACHE_NAME = 'v2'; // Change version if you update assets

// IMPORTANT: List all files that make up your app's shell
const CORE_ASSETS = [
  '.', // Represents index.html (or the start_url)
  'manifest.json',
  // Add paths to your actual icon files here:
  'icons/icon1.png', /* <<< REPLACE */
  'icons/icon2.png', /* <<< REPLACE */
  // External resources (needed even for embedded CSS/JS if they load external things)
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap',
  // Font Awesome webfonts (often loaded by its CSS - check browser DevTools Network tab)
  // You might need to add specific .woff2 files if caching them directly improves offline significantly
  // Example (might vary based on Font Awesome usage):
  // '/webfonts/fa-solid-900.woff2', // Adjust path if needed
];

// --- INSTALL: Cache core assets ---
self.addEventListener('install', (event) => {
  console.log('Service Worker: Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('Service Worker: Caching core assets');
        // Use addAll for atomic caching (all succeed or all fail)
        // Using { cache: 'reload' } ensures fresh copies are fetched during install
        const cachePromises = CORE_ASSETS.map(assetUrl => {
            return cache.add(new Request(assetUrl, { cache: 'reload' }));
        });
        return Promise.all(cachePromises);
      })
      .then(() => {
        console.log('Service Worker: Core assets cached successfully');
        // Force the waiting service worker to become the active service worker.
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Caching failed', error);
      })
  );
});

// --- ACTIVATE: Clean up old caches ---
self.addEventListener('activate', (event) => {
  console.log('Service Worker: Activating...');
  event.waitUntil(
    caches.keys().then((cacheNames) => {
      return Promise.all(
        cacheNames.map((cacheName) => {
          if (cacheName !== CACHE_NAME) {
            console.log('Service Worker: Deleting old cache:', cacheName);
            return caches.delete(cacheName);
          }
        })
      );
    }).then(() => {
      console.log('Service Worker: Activated and old caches cleaned.');
      // Tell the active service worker to take control of the page immediately.
      return self.clients.claim();
    })
  );
});

// --- FETCH: Serve from cache first, fallback to network ---
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests and requests for browser extensions
  if (event.request.method !== 'GET' || event.request.url.startsWith('chrome-extension://')) {
      return;
  }

  // Strategy: Cache First (for core assets and potentially others)
  event.respondWith(
    caches.match(event.request)
      .then((cachedResponse) => {
        // Return cached response if found
        if (cachedResponse) {
          // console.log('Service Worker: Serving from cache:', event.request.url);
          return cachedResponse;
        }

        // If not in cache, fetch from network
        // console.log('Service Worker: Fetching from network:', event.request.url);
        return fetch(event.request).then((networkResponse) => {
            // Optional: Cache dynamically fetched resources if needed
            // Be careful caching everything, especially API calls if you add them later.
            // Example: Cache successful responses for potential future offline use
            // if (networkResponse && networkResponse.status === 200) {
            //   const responseToCache = networkResponse.clone(); // Clone response
            //   caches.open(CACHE_NAME)
            //     .then(cache => {
            //       cache.put(event.request, responseToCache);
            //     });
            // }
            return networkResponse;
        }).catch(error => {
            console.error('Service Worker: Fetch failed:', error);
            // Optional: Return a custom offline fallback page/response
            // For example: return caches.match('/offline.html');
            // For this simple app, just letting the browser show its offline error is okay.
        });
      })
  );
});
