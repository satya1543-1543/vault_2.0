// sw.js - Service Worker

const CACHE_NAME = 'v3'; // <<< INCREMENTED version number
const API_BASE_URL = '/api'; // Define your API base path

// IMPORTANT: List all files that make up your app's shell
const CORE_ASSETS = [
  '.', // Represents index.html (or the start_url)
  'manifest.json',
  // Add paths to your actual icon files here:
  // 'icons/icon-192x192.png', // Example
  // 'icons/icon-512x512.png', // Example
  // Ensure these paths are correct relative to the root
  'https://i.ibb.co/7JfMHZQj/vault.png', // Your favicon seems to be this one

  // External resources
  'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css',
  'https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap',
  // Font Awesome webfonts (These are usually loaded BY the CSS above)
  // Caching them explicitly might improve offline performance further, but requires finding the exact URLs.
  // Check DevTools -> Network tab to see the .woff2 files loaded by fontawesome CSS. Example:
  // 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/webfonts/fa-solid-900.woff2',
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
            // Handle potential errors for individual assets during install
            return cache.add(new Request(assetUrl, { cache: 'reload' })).catch(err => {
                console.warn(`Service Worker: Failed to cache ${assetUrl} during install:`, err);
            });
        });
        return Promise.all(cachePromises);
      })
      .then(() => {
        console.log('Service Worker: Core assets cached (some might have failed, check warnings).');
        // Force the waiting service worker to become the active service worker.
        return self.skipWaiting();
      })
      .catch(error => {
        console.error('Service Worker: Caching failed overall', error);
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

// --- FETCH: Implement specific strategies ---
self.addEventListener('fetch', (event) => {
  // Skip non-GET requests and requests for browser extensions
  if (event.request.method !== 'GET' || event.request.url.startsWith('chrome-extension://')) {
      return;
  }

  const requestUrl = new URL(event.request.url);

  // Strategy 1: Network Only for API calls
  if (requestUrl.pathname.startsWith(API_BASE_URL)) {
    // console.log('Service Worker: Fetching API request from network:', event.request.url);
    event.respondWith(fetch(event.request));
    return; // Don't apply other strategies
  }

  // Strategy 2: Network First for Navigation requests (HTML)
  if (event.request.mode === 'navigate') {
    // console.log('Service Worker: Fetching navigation request (Network First):', event.request.url);
    event.respondWith(
      fetch(event.request)
        .then(networkResponse => {
          // Optional: Cache the successful response for offline fallback
          // Be careful not to cache error pages (check response.ok)
          // if (networkResponse.ok) {
          //   const cacheCopy = networkResponse.clone();
          //   caches.open(CACHE_NAME).then(cache => cache.put(event.request, cacheCopy));
          // }
          return networkResponse;
        })
        .catch(() => {
          // Network failed, try the cache
          // console.log('Service Worker: Network failed for navigation, trying cache:', event.request.url);
          return caches.match(event.request)
            .then(cachedResponse => {
                return cachedResponse; // Return cached page or undefined if not cached
                // Optional: return cachedResponse || caches.match('/offline.html');
            });
        })
    );
    return; // Don't apply other strategies
  }

  // Strategy 3: Cache First for other static assets (CSS, JS, Fonts, Images)
  // console.log('Service Worker: Fetching static asset (Cache First):', event.request.url);
  event.respondWith(
    caches.match(event.request)
      .then((cachedResponse) => {
        // Return cached response if found
        if (cachedResponse) {
          // console.log('Service Worker: Serving static asset from cache:', event.request.url);
          return cachedResponse;
        }

        // If not in cache, fetch from network AND cache it
        // console.log('Service Worker: Fetching static asset from network:', event.request.url);
        return fetch(event.request).then((networkResponse) => {
            // Check if we received a valid response
            if (!networkResponse || networkResponse.status !== 200 || networkResponse.type !== 'basic') {
              // Don't cache non-basic responses (like opaque responses from CDNs without CORS) or errors
              return networkResponse;
            }

            // IMPORTANT: Clone the response. A response is a stream
            // and because we want the browser to consume the response
            // as well as the cache consuming the response, we need
            // to clone it so we have two streams.
            const responseToCache = networkResponse.clone();

            caches.open(CACHE_NAME)
              .then(cache => {
                // console.log('Service Worker: Caching new static asset:', event.request.url);
                cache.put(event.request, responseToCache);
              });

            return networkResponse;
        }).catch(error => {
            console.error('Service Worker: Fetch failed for static asset:', error, event.request.url);
            // Optional: Return a fallback asset like a placeholder image
        });
      })
  );
});
