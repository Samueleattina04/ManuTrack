// Registra il service worker, segnala le nuove versioni e cattura l'invito
// all'installazione di Chrome/Edge (beforeinstallprompt) per usarlo con un pulsante.
let updating = false;

export default function registerServiceWorker() {
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        window.__mtInstallPrompt = event;
        window.dispatchEvent(new CustomEvent('install-available'));
    });
    window.addEventListener('appinstalled', () => {
        window.__mtInstallPrompt = null;
        window.dispatchEvent(new CustomEvent('app-installed'));
    });

    if (!('serviceWorker' in navigator) || !window.isSecureContext) return;

    // Il contatore sull'icona si azzera quando l'app viene aperta
    const clearBadge = () => { if (document.visibilityState === 'visible' && 'clearAppBadge' in navigator) navigator.clearAppBadge().catch(() => {}); };
    document.addEventListener('visibilitychange', clearBadge);

    window.addEventListener('load', async () => {
        clearBadge();
        let reg;
        try {
            reg = await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        } catch (e) {
            console.warn('ManuTrack: service worker non registrato', e);
            return;
        }

        const announce = (worker) => window.dispatchEvent(new CustomEvent('sw-update', { detail: { worker } }));
        if (reg.waiting && navigator.serviceWorker.controller) announce(reg.waiting);
        reg.addEventListener('updatefound', () => {
            const worker = reg.installing;
            worker?.addEventListener('statechange', () => {
                if (worker.state === 'installed' && navigator.serviceWorker.controller) announce(worker);
            });
        });

        // Copia della pagina Segnala per usarla anche senza rete (una volta per sessione)
        if (document.querySelector('meta[name="mt-can-report"]') && 'caches' in window) {
            try {
                if (!sessionStorage.getItem('mt-warm')) {
                    caches.open('mt-pages').then((c) => c.add('/segnala')).then(() => sessionStorage.setItem('mt-warm', '1')).catch(() => {});
                }
            } catch (e) { /* sessionStorage non disponibile */ }
        }

        // Controlla gli aggiornamenti ogni ora e quando l'app torna in primo piano
        setInterval(() => reg.update().catch(() => {}), 60 * 60 * 1000);
        document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && reg.update().catch(() => {}));
    });

    // Il service worker avvisa quando ha inviato (o no) le segnalazioni in coda
    navigator.serviceWorker.addEventListener('message', (event) => {
        if (event.data?.type === 'outbox-changed') window.dispatchEvent(new CustomEvent('outbox-changed'));
    });

    navigator.serviceWorker.addEventListener('controllerchange', () => {
        // Si ricarica solo se l'aggiornamento l'ha chiesto l'utente
        if (updating) window.location.reload();
    });
}

/** Banner "Nuova versione disponibile" (Alpine) */
export function updateBanner() {
    return {
        worker: null,
        init() {
            window.addEventListener('sw-update', (e) => { this.worker = e.detail.worker; });
        },
        apply() {
            updating = true;
            this.worker?.postMessage('SKIP_WAITING');
            setTimeout(() => window.location.reload(), 3000);
        },
    };
}
