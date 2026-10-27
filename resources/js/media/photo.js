// Preparazione delle foto sul telefono, prima dell'invio:
// - lato lungo al massimo 1600 px, compressione;
// - WebP se il browser lo sa generare, altrimenti JPEG (Safari su iPhone);
// - gli HEIC dell'iPhone diventano JPEG (Safari li sa aprire);
// - la ricodifica toglie EXIF e posizione GPS; l'orientamento viene applicato.
// Se il browser non riesce ad aprire il file (es. HEIC su Android) si invia
// l'originale: il server lo conserva e spiega cosa è successo.

const MAX = 1600;
let webpSupport = null;

async function canEncodeWebp() {
    if (webpSupport !== null) return webpSupport;
    try {
        const c = document.createElement('canvas');
        c.width = c.height = 2;
        const blob = await new Promise((r) => c.toBlob(r, 'image/webp', 0.8));
        webpSupport = !!blob && blob.type === 'image/webp';
    } catch (e) {
        webpSupport = false;
    }
    return webpSupport;
}

async function decode(file) {
    // createImageBitmap applica l'orientamento EXIF (imageOrientation predefinito "from-image")
    if ('createImageBitmap' in window) {
        try { return await createImageBitmap(file); } catch (e) { /* si prova con <img> */ }
    }
    const url = URL.createObjectURL(file);
    try {
        const img = new Image();
        img.decoding = 'async';
        img.src = url;
        await img.decode();
        return img;
    } finally {
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    }
}

/**
 * @param {File} file
 * @returns {Promise<File>} la foto pronta da inviare (o l'originale se non si può convertire)
 */
export async function preparePhoto(file) {
    if (!file || !/^image\/|\.(heic|heif)$/i.test(file.type || file.name)) return file;
    let source;
    try {
        source = await decode(file);
    } catch (e) {
        return file;
    }
    const w = source.width || source.naturalWidth;
    const h = source.height || source.naturalHeight;
    if (!w || !h) return file;

    const scale = Math.min(1, MAX / Math.max(w, h));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(w * scale);
    canvas.height = Math.round(h * scale);
    const ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff'; // trasparenze dei PNG → bianco (il JPEG non le supporta)
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
    if (source.close) source.close();

    const webp = await canEncodeWebp();
    const type = webp ? 'image/webp' : 'image/jpeg';
    const blob = await new Promise((r) => canvas.toBlob(r, type, webp ? 0.8 : 0.82));
    if (!blob) return file;

    // Una foto già piccola e leggera non va ricompressa peggio
    if (scale === 1 && blob.size >= file.size && /^image\/(jpeg|webp)$/.test(file.type)) return file;

    const base = (file.name || 'foto').replace(/\.[^.]+$/, '');
    return new File([blob], `${base}.${webp ? 'webp' : 'jpg'}`, { type, lastModified: Date.now() });
}

/** Prepara più foto insieme (in sequenza: sui telefoni la memoria è poca) */
export async function preparePhotos(files) {
    const out = [];
    for (const f of files) out.push(await preparePhoto(f));
    return out;
}

/**
 * Alpine: campo file per Livewire che prepara le foto prima del caricamento.
 *   <input type="file" multiple x-data x-photo-upload="photos">
 */
export function photoUploadDirective(el, { expression }, { cleanup }) {
    const handler = async (event) => {
        const files = Array.from(el.files || []);
        if (!files.length) return;
        event.stopImmediatePropagation();
        const component = window.Livewire.find(el.closest('[wire\\:id]').getAttribute('wire:id'));
        el.dispatchEvent(new CustomEvent('photo-preparing', { bubbles: true }));
        const ready = await preparePhotos(files);
        el.value = '';
        component.uploadMultiple(expression, ready,
            () => el.dispatchEvent(new CustomEvent('photo-uploaded', { bubbles: true })),
            () => el.dispatchEvent(new CustomEvent('photo-uploaded', { bubbles: true })));
    };
    el.addEventListener('change', handler);
    cleanup(() => el.removeEventListener('change', handler));
}
