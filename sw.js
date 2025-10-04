/**
 * Service Worker per OrdiGO
 * Gestione cache e funzionalità offline
 */

const CACHE_NAME = 'ordigo-v1.0.1';
const STATIC_CACHE = 'ordigo-static-v1.0.1';
const DYNAMIC_CACHE = 'ordigo-dynamic-v1.0.1';

// File da cachare immediatamente
const STATIC_FILES = [
    // Solo risorse veramente statiche
    '/manifest.json',
    '/icons/icon-192x192.svg',
    '/js/offline.js',
    '/assets/tailwind.css',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://cdn.jsdelivr.net/npm/chart.js'
];

// Installazione Service Worker
self.addEventListener('install', event => {
    console.log('[SW] Installing Service Worker');
    
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                console.log('[SW] Caching static files');
                return cache.addAll(STATIC_FILES.map(url => {
                    // Gestisce URL relativi e assoluti
                    if (url.startsWith('http')) {
                        return url;
                    }
                    return new Request(url, { mode: 'no-cors' });
                }));
            })
            .catch(err => {
                console.log('[SW] Error caching static files:', err);
            })
    );
    
    // Forza l'attivazione immediata
    self.skipWaiting();
});

// Attivazione Service Worker
self.addEventListener('activate', event => {
    console.log('[SW] Activating Service Worker');
    
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    // Rimuove cache vecchie
                    if (cacheName !== STATIC_CACHE && cacheName !== DYNAMIC_CACHE) {
                        console.log('[SW] Removing old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    
    // Prende il controllo di tutte le pagine
    self.clients.claim();
});

// Intercettazione delle richieste
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Ignora richieste non HTTP/HTTPS
    if (!request.url.startsWith('http')) {
        return;
    }
    
    // Evita di cacheare pagine PHP dinamiche: preferisci rete
    if (request.url.includes('.php')) {
        event.respondWith(networkFirst(request));
        return;
    }

    // Strategia Cache First per file statici
    if (isStaticFile(request.url)) {
        event.respondWith(cacheFirst(request));
        return;
    }
    
    // Strategia Network First per API e dati dinamici
    if (isApiRequest(request.url)) {
        event.respondWith(networkFirst(request));
        return;
    }
    
    // Strategia Stale While Revalidate per pagine
    event.respondWith(staleWhileRevalidate(request));
});

// Verifica se è un file statico
function isStaticFile(url) {
    return url.includes('.css') || 
           url.includes('.js') || 
           url.includes('.png') || 
           url.includes('.jpg') || 
           url.includes('.jpeg') || 
           url.includes('.gif') || 
           url.includes('.svg') ||
           url.includes('cdn.tailwindcss.com') ||
           url.includes('cdnjs.cloudflare.com') ||
           url.includes('cdn.jsdelivr.net');
}

// Verifica se è una richiesta API
function isApiRequest(url) {
    return url.includes('api/') || 
           url.includes('action=') ||
           (url.includes('.php') && url.includes('POST'));
}

// Strategia Cache First
async function cacheFirst(request) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(STATIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('[SW] Cache First failed:', error);
        return new Response('Offline - Risorsa non disponibile', {
            status: 503,
            statusText: 'Service Unavailable'
        });
    }
}

// Strategia Network First
async function networkFirst(request) {
    try {
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(DYNAMIC_CACHE);
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    } catch (error) {
        console.log('[SW] Network First fallback to cache:', error);
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        // Risposta offline per richieste API
        return new Response(JSON.stringify({
            error: 'Offline',
            message: 'Operazione non disponibile offline',
            offline: true
        }), {
            status: 503,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}

// Strategia Stale While Revalidate
async function staleWhileRevalidate(request) {
    const cache = await caches.open(DYNAMIC_CACHE);
    const cachedResponse = await cache.match(request);
    
    const fetchPromise = fetch(request).then(networkResponse => {
        if (networkResponse.ok) {
            cache.put(request, networkResponse.clone());
        }
        return networkResponse;
    }).catch(error => {
        console.log('[SW] Network failed:', error);
        return cachedResponse;
    });
    
    return cachedResponse || fetchPromise;
}

// Gestione messaggi dal client
self.addEventListener('message', event => {
    const { type, data } = event.data;
    
    switch (type) {
        case 'SKIP_WAITING':
            self.skipWaiting();
            break;
            
        case 'GET_VERSION':
            event.ports[0].postMessage({ version: CACHE_NAME });
            break;
            
        case 'CLEAR_CACHE':
            clearAllCaches().then(() => {
                event.ports[0].postMessage({ success: true });
            });
            break;
            
        case 'SYNC_DATA':
            // Implementazione futura per sincronizzazione dati
            handleDataSync(data);
            break;
    }
});

// Pulizia cache
async function clearAllCaches() {
    const cacheNames = await caches.keys();
    return Promise.all(
        cacheNames.map(cacheName => caches.delete(cacheName))
    );
}

// Gestione sincronizzazione dati (placeholder)
function handleDataSync(data) {
    console.log('[SW] Data sync requested:', data);
    // Implementazione futura per sincronizzazione offline
}

// Background Sync per operazioni offline
self.addEventListener('sync', event => {
    console.log('[SW] Background sync:', event.tag);
    
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

async function doBackgroundSync() {
    try {
        // Recupera dati pendenti dal IndexedDB
        const pendingData = await getPendingData();
        
        for (const item of pendingData) {
            try {
                await syncDataItem(item);
                await removePendingData(item.id);
            } catch (error) {
                console.log('[SW] Sync failed for item:', item.id, error);
            }
        }
    } catch (error) {
        console.log('[SW] Background sync failed:', error);
    }
}

// Placeholder per gestione dati pendenti
async function getPendingData() {
    // Implementazione futura con IndexedDB
    return [];
}

async function syncDataItem(item) {
    // Implementazione futura per sincronizzazione singolo elemento
    console.log('[SW] Syncing item:', item);
}

async function removePendingData(id) {
    // Implementazione futura per rimozione dati sincronizzati
    console.log('[SW] Removing synced item:', id);
}

// Notifiche push (placeholder)
self.addEventListener('push', event => {
    console.log('[SW] Push received:', event);
    
    const options = {
        body: event.data ? event.data.text() : 'Nuova notifica da OrdiGO',
        icon: '/icon-192x192.png',
        badge: '/badge-72x72.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'Visualizza',
                icon: '/icon-explore.png'
            },
            {
                action: 'close',
                title: 'Chiudi',
                icon: '/icon-close.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('OrdiGO', options)
    );
});

// Gestione click notifiche
self.addEventListener('notificationclick', event => {
    console.log('[SW] Notification click:', event);
    
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

console.log('[SW] Service Worker loaded successfully');