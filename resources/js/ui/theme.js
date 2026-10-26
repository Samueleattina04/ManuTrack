// Tema chiaro/scuro/sistema. La scelta resta sul dispositivo (localStorage);
// lo script inline in <head> la applica prima del disegno per evitare lampi.
const KEY = 'mt-theme';
const media = window.matchMedia('(prefers-color-scheme: light)');

function resolve(mode) {
    if (mode === 'system') return media.matches ? 'light' : 'dark';
    return mode === 'light' ? 'light' : 'dark';
}

function apply(mode) {
    const root = document.documentElement;
    root.dataset.theme = resolve(mode);
    root.dataset.themeMode = mode;
    const meta = document.querySelector('meta[name="theme-color"]:not([media])');
    if (meta) meta.content = getComputedStyle(root).getPropertyValue('--mt-bg').trim() || meta.content;
}

export default () => ({
    mode: document.documentElement.dataset.themeMode || 'dark',

    init() {
        media.addEventListener('change', () => this.mode === 'system' && apply('system'));
    },

    set(mode) {
        this.mode = mode;
        try {
            localStorage.setItem(KEY, mode);
        } catch (e) {
            // archiviazione non disponibile: il tema vale solo per questa pagina
        }
        apply(mode);
    },
});
