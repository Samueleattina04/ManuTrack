// Scorciatoie da tastiera per PC.
//   Ctrl/Cmd + K  palette comandi        /  cerca
//   G poi D/O/M/P/R/N/S  vai a…           ?  elenco scorciatoie
// Le destinazioni arrivano dagli attributi data-shortcut dei link di navigazione.
function typing(e) {
    const t = e.target;
    return t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName);
}

export default () => ({
    pendingG: false,
    gTimer: null,
    helpOpen: false,

    onKey(e) {
        if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('palette-open'));
            return;
        }
        if (typing(e) || e.metaKey || e.ctrlKey || e.altKey) return;

        if (this.pendingG) {
            this.pendingG = false;
            clearTimeout(this.gTimer);
            const link = document.querySelector(`[data-shortcut="g ${e.key.toLowerCase()}"]`);
            if (link && link.getAttribute('aria-disabled') !== 'true') {
                e.preventDefault();
                link.click();
            }
            return;
        }

        if (e.key === 'g') {
            this.pendingG = true;
            this.gTimer = setTimeout(() => (this.pendingG = false), 1200);
        } else if (e.key === '/') {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('palette-open'));
        } else if (e.key === '?') {
            e.preventDefault();
            this.helpOpen = true;
        } else if (e.key === 'Escape') {
            this.helpOpen = false;
        }
    },
});
