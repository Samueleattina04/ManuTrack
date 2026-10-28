// Capacità del dispositivo per il 3D. Questo file è piccolo e sta nel pacchetto principale:
// Three.js si scarica solo se qui si decide di usarlo.
//   'full' → 3D subito; 'weak' → 2D, con la possibilità di provare il 3D; 'none' → solo 2D.

let cached = null;

export function webglSupported() {
    try {
        const canvas = document.createElement('canvas');
        const gl = canvas.getContext('webgl2') || canvas.getContext('webgl');
        if (!gl) return false;
        gl.getExtension('WEBGL_lose_context')?.loseContext();
        return true;
    } catch (e) {
        return false;
    }
}

export function deviceTier() {
    if (cached) return cached;
    if (!webglSupported()) return (cached = 'none');
    const memory = navigator.deviceMemory;
    const cores = navigator.hardwareConcurrency;
    const saveData = navigator.connection && navigator.connection.saveData;
    cached = saveData || (memory && memory < 3) || (cores && cores < 4) ? 'weak' : 'full';
    return cached;
}

export const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Preferenza della persona (3D o 2D) per pagina; il browser può non permettere di salvarla
export function savedMode(key) {
    try { return localStorage.getItem(key); } catch (e) { return null; }
}
export function saveMode(key, value) {
    try { localStorage.setItem(key, value); } catch (e) { /* niente: vale solo per questa visita */ }
}

// Qualunque colore CSS (anche oklch o color-mix) diventa esadecimale per Three.js
let probe = null;
export function cssColor(name, fallback = '#808080') {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim() || fallback;
    probe ??= document.createElement('canvas').getContext('2d', { willReadFrequently: true });
    if (!probe) return fallback;
    probe.clearRect(0, 0, 1, 1);
    probe.fillStyle = '#000';
    probe.fillStyle = value;
    probe.fillRect(0, 0, 1, 1);
    const [r, g, b] = probe.getImageData(0, 0, 1, 1).data;
    return '#' + [r, g, b].map((n) => n.toString(16).padStart(2, '0')).join('');
}

// Registro delle viste 3D attive, letto dai controlli automatici (window.__mt3d):
// quante sono aperte, quanti fotogrammi hanno disegnato, e cosa restava in memoria alla chiusura.
const stats = { active: 0, opened: 0, closed: 0, frames: 0, leaks: [] };
if (typeof window !== 'undefined') window.__mt3d = stats;

export function track(name) {
    stats.active++;
    stats.opened++;
    let closed = false;
    return {
        frame() { stats.frames++; },
        // memory: renderer.info.memory dopo aver liberato tutto (deve essere 0 geometrie e 0 texture)
        close(memory) {
            if (closed) return;
            closed = true;
            stats.active--;
            stats.closed++;
            if (memory && (memory.geometries || memory.textures)) stats.leaks.push({ name, ...memory });
        },
    };
}

// Lasciando la pagina (anche verso la cache "indietro/avanti" del browser) le viste 3D si chiudono
export function onLeave(callback) {
    window.addEventListener('pagehide', callback);
    return () => window.removeEventListener('pagehide', callback);
}
