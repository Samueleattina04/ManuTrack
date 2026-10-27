// Lettore di codici QR con la fotocamera.
// Usa BarcodeDetector dove c'è (Chrome su Android), altrimenti jsQR (iPhone),
// caricato solo quando serve.

let jsQR = null;
async function loadJsQR() {
    if (!jsQR) jsQR = (await import('jsqr')).default;
    return jsQR;
}

function detector() {
    try {
        return 'BarcodeDetector' in window ? new window.BarcodeDetector({ formats: ['qr_code'] }) : null;
    } catch (e) {
        return null;
    }
}

/**
 * Avvia la lettura dal video. onResult(testo) viene chiamato una volta.
 * Restituisce una funzione per fermare tutto.
 */
export async function startScanner(video, onResult) {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
    video.srcObject = stream;
    video.setAttribute('playsinline', '');
    video.muted = true;
    await video.play();

    let stopped = false;
    const stop = () => {
        stopped = true;
        stream.getTracks().forEach((t) => t.stop());
        video.srcObject = null;
    };

    const native = detector();
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    const decode = native ? null : await loadJsQR();

    const tick = async () => {
        if (stopped) return;
        if (video.readyState >= 2) {
            try {
                let text = null;
                if (native) {
                    const codes = await native.detect(video);
                    text = codes[0]?.rawValue || null;
                } else {
                    const w = video.videoWidth;
                    const h = video.videoHeight;
                    const scale = Math.min(1, 640 / Math.max(w, h));
                    canvas.width = Math.round(w * scale);
                    canvas.height = Math.round(h * scale);
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    text = decode(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' })?.data || null;
                }
                if (text) {
                    stop();
                    onResult(text);
                    return;
                }
            } catch (e) { /* fotogramma non leggibile: si riprova */ }
        }
        setTimeout(() => requestAnimationFrame(tick), 120);
    };
    requestAnimationFrame(tick);

    return stop;
}

/** Legge un QR da una foto (quando la fotocamera dal vivo non è disponibile) */
export async function scanFile(file) {
    const bitmap = await createImageBitmap(file);
    const scale = Math.min(1, 1200 / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    const ctx = canvas.getContext('2d');
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    const native = detector();
    if (native) {
        const codes = await native.detect(canvas);
        if (codes[0]) return codes[0].rawValue;
    }
    const decode = await loadJsQR();
    const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
    return decode(img.data, img.width, img.height)?.data || null;
}

/** Codice della macchina da un testo letto: "https://…/q/<codice>" */
export function tokenFromText(text) {
    try {
        const url = new URL(text, window.location.origin);
        const m = url.pathname.match(/\/q\/([a-z0-9]{16,40})\/?$/);
        return m ? m[1] : null;
    } catch (e) {
        return null;
    }
}
