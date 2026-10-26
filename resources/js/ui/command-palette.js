// Palette comandi (Ctrl/Cmd + K): filtra le voci di navigazione e le azioni.
// Le voci sono passate dal server come JSON; nelle fasi successive si aggiunge
// la ricerca di macchine, ordini e ricambi.
export default (items = []) => ({
    open: false,
    query: '',
    active: 0,
    items,

    get results() {
        const q = this.query.trim().toLowerCase();
        const list = this.items.filter((i) => !i.disabled);
        if (!q) return list;
        return list.filter((i) => (i.label + ' ' + (i.keywords || '')).toLowerCase().includes(q));
    },

    show() {
        this.open = true;
        this.query = '';
        this.active = 0;
        this.$nextTick(() => this.$refs.input?.focus());
    },

    move(step) {
        const n = this.results.length;
        if (n) this.active = (this.active + step + n) % n;
    },

    go(item = this.results[this.active]) {
        if (item?.url) window.location.href = item.url;
    },
});
