// Coda delle segnalazioni scritte senza rete (IndexedDB, sul telefono).
// Ogni segnalazione ha un identificativo creato qui (client_uuid): il server
// la registra una volta sola anche se l'invio viene ripetuto.
// La stessa coda la legge il service worker per l'invio in background (Android).

const DB_NAME = 'manutrack';
const STORE = 'outbox';
export const SYNC_TAG = 'mt-outbox';
export const ENDPOINT = '/segnalazioni/invia';

function open() {
    return new Promise((resolve, reject) => {
        const req = indexedDB.open(DB_NAME, 1);
        req.onupgradeneeded = () => req.result.createObjectStore(STORE, { keyPath: 'uuid' });
        req.onsuccess = () => resolve(req.result);
        req.onerror = () => reject(req.error);
    });
}

async function tx(mode, fn) {
    const db = await open();
    return new Promise((resolve, reject) => {
        const t = db.transaction(STORE, mode);
        const result = fn(t.objectStore(STORE));
        t.oncomplete = () => { db.close(); resolve(result && 'result' in result ? result.result : undefined); };
        t.onerror = () => { db.close(); reject(t.error); };
    });
}

export const outbox = {
    add: (item) => tx('readwrite', (s) => s.put(item)),
    remove: (uuid) => tx('readwrite', (s) => s.delete(uuid)),
    all: () => tx('readonly', (s) => s.getAll()),
    async forUser(userId) { return (await this.all()).filter((i) => i.userId === userId); },
};

/** FormData di una segnalazione (anche dal service worker) */
export function formData(item) {
    const fd = new FormData();
    for (const [k, v] of Object.entries(item.fields)) if (v !== null && v !== undefined && v !== '') fd.append(k, v === true ? '1' : v === false ? '0' : v);
    fd.append('client_uuid', item.uuid);
    fd.append('reported_at', item.createdAt);
    fd.append('user_id', item.userId);
    for (const p of item.photos || []) fd.append('photos[]', new File([p.blob], p.name, { type: p.type }));
    return fd;
}

/**
 * Invia una segnalazione. Esiti: { ok: true, data } | { ok: false, retry: bool, status, message }
 * retry = true per i problemi temporanei (rete, sessione scaduta, server occupato): resta in coda.
 */
export async function send(item) {
    let res;
    try {
        res = await fetch(ENDPOINT, {
            method: 'POST',
            body: formData(item),
            credentials: 'same-origin',
            headers: { 'X-ManuTrack': '1', Accept: 'application/json' },
        });
    } catch (e) {
        return { ok: false, retry: true, status: 0, message: 'offline' };
    }
    let data = {};
    try { data = await res.json(); } catch (e) { /* risposta non JSON */ }
    if (res.ok) return { ok: true, data };
    const retry = res.status === 401 || res.status === 419 || res.status === 409 || res.status >= 500 || res.status === 429;
    return { ok: false, retry, status: res.status, message: data.message || '', errors: data.errors || {} };
}

/**
 * Invia la coda di questa persona. Le segnalazioni rifiutate dal server per un
 * motivo che non si risolve riprovando restano in coda segnate "failed": non si
 * cancellano mai da sole, decide la persona (Riprova o Elimina) dalla pagina Segnala.
 */
export async function flush(userId, { includeFailed = false } = {}) {
    let sent = 0;
    let left = 0;
    for (const item of await outbox.forUser(userId)) {
        if (item.failed && !includeFailed) { left++; continue; }
        const result = await send(item);
        if (result.ok) {
            await outbox.remove(item.uuid);
            sent++;
            continue;
        }
        left++;
        const message = result.message || Object.values(result.errors || {}).flat()[0] || '';
        await outbox.add({ ...item, attempts: (item.attempts || 0) + 1, lastError: result.status, failed: !result.retry, message });
        if (result.status === 401 || result.status === 419 || result.status === 0) break; // sessione scaduta o niente rete
    }
    window.dispatchEvent(new CustomEvent('outbox-changed'));
    return { sent, left };
}

export async function requestBackgroundSync() {
    try {
        const reg = await navigator.serviceWorker?.ready;
        if (reg && 'sync' in reg) await reg.sync.register(SYNC_TAG);
    } catch (e) { /* non supportato (iPhone): si invia alla riapertura dell'app */ }
}

export function uuid() {
    if (crypto.randomUUID) return crypto.randomUUID();
    const b = crypto.getRandomValues(new Uint8Array(16));
    b[6] = (b[6] & 0x0f) | 0x40; b[8] = (b[8] & 0x3f) | 0x80;
    const h = [...b].map((x) => x.toString(16).padStart(2, '0')).join('');
    return `${h.slice(0, 8)}-${h.slice(8, 12)}-${h.slice(12, 16)}-${h.slice(16, 20)}-${h.slice(20)}`;
}

/**
 * Alpine: avviso "segnalazioni in attesa di invio" presente in tutte le pagine.
 * Prova a inviare all'apertura, al ritorno della rete e quando l'app torna in primo piano.
 */
export function outboxBanner(userId) {
    return {
        pending: 0,
        sending: false,
        justSent: 0,
        async init() {
            if (!userId || !('indexedDB' in window)) return;
            await this.refresh();
            this.trySend();
            window.addEventListener('online', () => this.trySend());
            document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && this.trySend());
            window.addEventListener('outbox-changed', () => this.refresh());
        },
        async refresh() {
            try { this.pending = (await outbox.forUser(userId)).length; } catch (e) { this.pending = 0; }
        },
        async trySend() {
            if (this.sending || !navigator.onLine) return;
            await this.refresh();
            if (!this.pending) return;
            this.sending = true;
            try {
                const { sent } = await flush(userId);
                this.justSent = sent;
                if (sent) setTimeout(() => (this.justSent = 0), 6000);
            } finally {
                this.sending = false;
                await this.refresh();
            }
        },
    };
}
