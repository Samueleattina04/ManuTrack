// ManuTrack — funzioni comuni per app installabile e notifiche push.
// Regola: Notification.requestPermission() si chiama SOLO dentro un gesto
// dell'utente (enablePush, da un pulsante). Mai al caricamento della pagina.

export function environment() {
    const ua = navigator.userAgent;
    const isIPadOS = navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1;
    const isIOS = /iPhone|iPod/.test(ua) || /iPad/.test(ua) || isIPadOS;
    const isAndroid = /Android/.test(ua);
    const m = ua.match(/OS (\d+)[_.](\d+)/);
    const iosVersion = isIOS && m ? parseFloat(`${m[1]}.${m[2]}`) : null;
    const isSafari = /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS|OPiOS|Chrome|Android/.test(ua);
    const browser = /EdgA?\/|EdgiOS/.test(ua) ? 'Edge'
        : /SamsungBrowser/.test(ua) ? 'Samsung Internet'
        : /Firefox|FxiOS/.test(ua) ? 'Firefox'
        : /CriOS|Chrome/.test(ua) ? 'Chrome'
        : /Safari/.test(ua) ? 'Safari' : 'Browser';
    const standalone = window.matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;
    const platform = isIOS ? 'ios' : isAndroid ? 'android' : 'desktop';
    const swSupported = 'serviceWorker' in navigator;
    const pushSupported = swSupported && 'PushManager' in window && 'Notification' in window;

    return {
        platform,
        isIOS,
        isAndroid,
        iosVersion,
        isSafari,
        browser,
        standalone,
        secure: window.isSecureContext,
        swSupported,
        pushSupported,
        badging: 'setAppBadge' in navigator,
        permission: 'Notification' in window ? Notification.permission : 'unsupported',
        // Su iPhone le notifiche push richiedono l'app installata e iOS ≥ 16.4
        pushNeedsInstall: isIOS && !standalone,
        iosTooOld: isIOS && iosVersion !== null && iosVersion < 16.4,
    };
}

export function deviceName(env = environment()) {
    const device = env.isIOS ? (navigator.maxTouchPoints > 1 && /Macintosh|iPad/.test(navigator.userAgent) ? 'iPad' : 'iPhone')
        : env.isAndroid ? 'Android' : (navigator.userAgentData?.platform || navigator.platform || 'PC');
    const os = env.iosVersion ? ` · iOS ${env.iosVersion}` : '';
    return `${device}${os} · ${env.browser}${env.standalone ? ' · app installata' : ''}`;
}

export function vapidKey() {
    return document.querySelector('meta[name="vapid-public-key"]')?.content || '';
}

function base64UrlToUint8Array(value) {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const raw = atob((value + padding).replace(/-/g, '+').replace(/_/g, '/'));
    return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

export async function api(method, url, body) {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: body ? JSON.stringify(body) : undefined,
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* risposta vuota */ }
    return { ok: res.ok, status: res.status, data };
}

export async function registration() {
    if (!('serviceWorker' in navigator)) return null;
    return (await navigator.serviceWorker.getRegistration('/')) || null;
}

export async function currentSubscription() {
    const reg = await registration();
    return reg ? reg.pushManager.getSubscription() : null;
}

export async function sha256(text) {
    const buf = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(text));
    return [...new Uint8Array(buf)].map((b) => b.toString(16).padStart(2, '0')).join('');
}

/**
 * Da chiamare direttamente nel gestore di un clic/tocco.
 * Restituisce { ok, permission, message }.
 */
export async function enablePush() {
    if (!('Notification' in window)) return { ok: false, permission: 'unsupported' };

    // Prima istruzione del gesto: su iPhone la richiesta deve partire subito dal tocco
    const permission = await Notification.requestPermission();
    if (permission !== 'granted') return { ok: false, permission };

    const key = vapidKey();
    if (!key) return { ok: false, permission, message: 'no-vapid' };

    const reg = (await registration()) || (await navigator.serviceWorker.register('/sw.js', { scope: '/' }));
    await navigator.serviceWorker.ready;

    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
        sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: base64UrlToUint8Array(key) });
    }

    const encodings = window.PushManager?.supportedContentEncodings || ['aes128gcm'];
    const res = await api('POST', '/push/iscrizioni', { ...sub.toJSON(), contentEncoding: encodings.includes('aes128gcm') ? 'aes128gcm' : encodings[0] });

    return { ok: res.ok, status: res.status, permission, subscription: sub, message: res.ok ? null : (res.data.message || `HTTP ${res.status}`) };
}

export async function disablePush() {
    const sub = await currentSubscription();
    if (!sub) return { ok: true };
    const endpoint = sub.endpoint;
    await sub.unsubscribe();
    const res = await api('DELETE', '/push/iscrizioni', { endpoint });
    return { ok: res.ok };
}

export async function sendTest(onlyThisDevice = true) {
    const sub = onlyThisDevice ? await currentSubscription() : null;
    return api('POST', '/push/prova', { endpoint: sub ? sub.endpoint : null });
}

export async function workerVersion() {
    const reg = await registration();
    const worker = reg?.active;
    if (!worker) return null;
    return new Promise((resolve) => {
        const channel = new MessageChannel();
        channel.port1.onmessage = (e) => resolve(e.data);
        worker.postMessage('VERSION', [channel.port2]);
        setTimeout(() => resolve(null), 1500);
    });
}
