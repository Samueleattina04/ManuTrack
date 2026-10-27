/* ManuTrack — service worker {{ $version }}
 * Generato da routes/web.php (ServiceWorkerController): non modificare a mano.
 * - file statici in cache (cache-first per i file con impronta nel nome);
 * - pagine sempre dalla rete, con la pagina offline se manca la connessione;
 * - aggiornamento controllato: la nuova versione attende il consenso dell'utente;
 * - notifiche push, clic che apre la pagina giusta, contatore sull'icona.
 */
const VERSION = @json($version);
const STATIC_CACHE = 'mt-static-' + VERSION;
const RUNTIME_CACHE = 'mt-runtime';
const OFFLINE_URL = @json(route('pwa.offline', absolute: false));
const PRECACHE = @json($precache);
const NO_CACHE = [/^\/livewire/, /^\/push\//, /^\/allegati\//, /^\/q\//, /^\/sw\.js$/, /^\/login/, /^\/logout/, /^\/user\//, /^\/two-factor/];

self.addEventListener('install', (event) => {
    event.waitUntil(caches.open(STATIC_CACHE).then((cache) => cache.addAll(PRECACHE)));
});

self.addEventListener('activate', (event) => {
    event.waitUntil((async () => {
        const keys = await caches.keys();
        await Promise.all(keys.filter((k) => k.startsWith('mt-static-') && k !== STATIC_CACHE).map((k) => caches.delete(k)));
        await self.clients.claim();
    })());
});

self.addEventListener('message', (event) => {
    if (event.data === 'SKIP_WAITING') self.skipWaiting();
    if (event.data === 'VERSION' && event.ports[0]) event.ports[0].postMessage(VERSION);
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    if (request.method !== 'GET') return;
    const url = new URL(request.url);
    if (url.origin !== self.location.origin || NO_CACHE.some((re) => re.test(url.pathname))) return;

    // Pagine: sempre dalla rete (dati aggiornati e privati), offline → pagina dedicata
    if (request.mode === 'navigate') {
        event.respondWith(fetch(request).catch(async () => (await caches.match(OFFLINE_URL)) || Response.error()));
        return;
    }

    // File compilati (nome con impronta): dalla cache, altrimenti dalla rete
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(caches.match(request).then((hit) => hit || fetch(request).then((res) => {
            if (res.ok) { const copy = res.clone(); caches.open(STATIC_CACHE).then((c) => c.put(request, copy)); }
            return res;
        })));
        return;
    }

    // Icone, logo e immagini: copia in cache aggiornata in background
    if (/^\/(icons|marchio|images)\//.test(url.pathname) || url.pathname === '/manifest.webmanifest') {
        event.respondWith(caches.open(RUNTIME_CACHE).then(async (cache) => {
            const hit = await cache.match(request);
            const network = fetch(request).then((res) => { if (res.ok) cache.put(request, res.clone()); return res; }).catch(() => hit);
            return hit || network;
        }));
    }
});

self.addEventListener('push', (event) => {
    let data = {};
    try { data = event.data ? event.data.json() : {}; } catch (e) { data = { body: event.data ? event.data.text() : '' }; }

    const title = data.title || 'ManuTrack';
    const options = {
        body: data.body || '',
        icon: data.icon || PRECACHE.find((p) => p.includes('icon-192')) || undefined,
        badge: data.badge || undefined,
        tag: data.tag || undefined,
        renotify: !!data.tag,
        timestamp: data.timestamp || Date.now(),
        data: { url: data.url || '/' },
    };

    event.waitUntil((async () => {
        await self.registration.showNotification(title, options);
        if (typeof data.count === 'number' && 'setAppBadge' in self.navigator) {
            try { data.count > 0 ? await self.navigator.setAppBadge(data.count) : await self.navigator.clearAppBadge(); } catch (e) {}
        }
        const clients = await self.clients.matchAll({ type: 'window' });
        clients.forEach((c) => c.postMessage({ type: 'push', data }));
    })());
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const target = new URL((event.notification.data && event.notification.data.url) || '/', self.location.origin).href;
    event.waitUntil((async () => {
        const clients = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (const client of clients) {
            if (new URL(client.url).origin === self.location.origin && 'focus' in client) {
                await client.focus();
                if ('navigate' in client) return client.navigate(target);
                return;
            }
        }
        return self.clients.openWindow(target);
    })());
});

// Il servizio push ha cambiato l'iscrizione: si rinnova e si avvisa il server
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil((async () => {
        const old = event.oldSubscription;
        const options = old ? old.options : null;
        if (!options) return;
        const sub = event.newSubscription || await self.registration.pushManager.subscribe(options);
        await fetch(@json(route('push.renew', absolute: false)), {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
            body: JSON.stringify({ old_endpoint: old.endpoint, subscription: sub.toJSON() }),
        });
    })());
});
