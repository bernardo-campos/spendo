const CACHE_NAME = 'spendo-private-shell-v2';
const APP_SHELL = '/app';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(Promise.all([
        caches.keys().then((names) => Promise.all(names
            .filter((name) => name.startsWith('spendo-private-shell-') && name !== CACHE_NAME)
            .map((name) => caches.delete(name)))),
        self.clients.claim(),
    ]));
});

self.addEventListener('message', (event) => {
    if (event.data?.type === 'CACHE_PRIVATE_SHELL') {
        event.waitUntil(cacheAppShell(event.data.assets ?? []));
    }

    if (event.data?.type === 'CLEAR_PRIVATE_OFFLINE_DATA') {
        event.waitUntil(caches.delete(CACHE_NAME));
    }
});

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    if (request.mode === 'navigate' && url.pathname.startsWith('/app')) {
        event.respondWith(networkFirstAppShell(request));
        return;
    }

    if (request.method === 'GET' && ['script', 'style', 'image', 'font'].includes(request.destination)) {
        event.respondWith(cacheFirstAsset(request));
    }
});

async function cacheAppShell(assets) {
    const response = await fetch(APP_SHELL, { credentials: 'include' });

    if (response.ok) {
        const cache = await caches.open(CACHE_NAME);
        await cache.put(APP_SHELL, response.clone());
        await Promise.all(assets.map(async (asset) => {
            const url = new URL(asset, self.location.origin);

            if (url.origin !== self.location.origin) {
                return;
            }

            try {
                const assetResponse = await fetch(url);

                if (assetResponse.ok) {
                    await cache.put(url, assetResponse);
                }
            } catch {
                // An individual asset can be refreshed on a future connected visit.
            }
        }));
    }
}

async function networkFirstAppShell(request) {
    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            await cache.put(APP_SHELL, response.clone());
        }

        return response;
    } catch {
        const cachedShell = await caches.match(APP_SHELL);

        if (cachedShell) {
            return cachedShell;
        }

        throw new Error('La aplicación todavía no se descargó en este dispositivo.');
    }
}

async function cacheFirstAsset(request) {
    const cached = await caches.match(request);

    if (cached) {
        return cached;
    }

    try {
        const response = await fetch(request);

        if (response.ok) {
            const cache = await caches.open(CACHE_NAME);
            await cache.put(request, response.clone());
        }

        return response;
    } catch {
        return new Response('', { status: 503, statusText: 'Asset unavailable offline' });
    }
}
