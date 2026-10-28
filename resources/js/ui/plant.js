// Pagina Stabilimento: sceglie tra vista 3D e 2D, tiene la macchina selezionata e
// aggiorna gli stati ogni 30 secondi. La vista 2D (SVG dal server) c'è sempre:
// il 3D si aggiunge solo se il dispositivo lo regge, e se fallisce si torna al 2D.
import { deviceTier, onLeave, savedMode, saveMode } from '../three/capability';

const MODE_KEY = 'mt-plant-mode';
const POLL_MS = 30000;

export default (data, opts) => ({
    data,
    mode: '2d',
    tier: 'none',
    loading: false,
    error: null,
    selectedId: null,
    ctrl: null,
    timer: null,

    init() {
        this.tier = deviceTier();
        const saved = savedMode(MODE_KEY);
        if (this.tier !== 'none' && (saved === '3d' || (saved === null && this.tier === 'full'))) {
            this.show3d(false);
        }
        this.timer = setInterval(() => { if (!document.hidden) this.refresh(); }, POLL_MS);
        // Lasciando la pagina si libera il 3D; tornando indietro (cache del browser) si riapre
        this.stopLeave = onLeave(() => { this.ctrl?.dispose(); this.ctrl = null; });
        this.onShow = (e) => { if (e.persisted && this.mode === '3d' && !this.ctrl) this.show3d(false); };
        window.addEventListener('pageshow', this.onShow);
    },

    destroy() {
        clearInterval(this.timer);
        this.stopLeave?.();
        window.removeEventListener('pageshow', this.onShow);
        this.ctrl?.dispose();
        this.ctrl = null;
    },

    get selected() {
        return this.data.items.find((i) => i.id === this.selectedId) || null;
    },

    get sortedItems() {
        return [...this.data.items].sort((a, b) => a.code.localeCompare(b.code, 'it'));
    },

    ordersText(n) {
        return n === 0 ? opts.orders[0] : (n === 1 ? opts.orders[1] : opts.orders[2].replace(':count', n));
    },

    get can3d() {
        return this.tier !== 'none';
    },

    async show3d(remember = true) {
        if (remember) saveMode(MODE_KEY, '3d');
        this.mode = '3d';
        this.error = null;
        if (this.ctrl || this.loading) return;
        this.loading = true;
        try {
            const { mountPlant } = await import('../three/plant3d.js');
            await this.$nextTick();
            this.ctrl = mountPlant(this.$refs.stage, this.data, { onSelect: (id) => this.select(id) });
            this.ctrl.select(this.selectedId);
        } catch (e) {
            console.error(e);
            this.error = opts.error;
            this.mode = '2d';
        } finally {
            this.loading = false;
        }
    },

    show2d() {
        saveMode(MODE_KEY, '2d');
        this.mode = '2d';
        this.ctrl?.dispose();
        this.ctrl = null;
    },

    select(id) {
        this.selectedId = id;
        this.ctrl?.select(id);
        if (id && window.matchMedia('(max-width: 1023px)').matches) {
            this.$nextTick(() => this.$refs.panel?.scrollIntoView({ block: 'nearest', behavior: 'smooth' }));
        }
    },

    async refresh() {
        try {
            const res = await fetch(opts.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const statuses = await res.json();
            for (const item of this.data.items) {
                const s = statuses[item.asset_id];
                if (s) Object.assign(item, s);
            }
            // La vista 2D è disegnata dal server: si aggiornano solo le classi di colore
            for (const el of this.$root.querySelectorAll('[data-plant-asset]')) {
                const s = statuses[el.dataset.plantAsset];
                if (s) el.setAttribute('class', `plant-item tone-${s.tone}`);
            }
            this.ctrl?.setStatuses(statuses);
        } catch (e) {
            // Rete assente: si riprova al giro successivo
        }
    },
});
