// Segnalazione rapida dal telefono, in pochi tocchi:
//   1. macchina: QR, ricerca, recenti, oppure "non so" con l'ubicazione;
//   2. cosa succede, macchina ferma sì/no, urgenza, foto;
//   3. invio. Senza rete la segnalazione resta sul telefono e parte da sola
//      appena la connessione torna (in background su Android, alla riapertura su iPhone).
// La pagina non usa Livewire: funziona anche aperta dalla cache, offline.
import { outbox, send, flush, uuid, requestBackgroundSync } from './outbox';
import { startScanner, scanFile, tokenFromText } from './scanner';
import { preparePhoto } from '../media/photo';

const MAX_PHOTOS = 6;
const norm = (s) => (s || '').toString().toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');

export default (config) => ({
    step: 'asset',
    query: '',
    asset: null,
    noAsset: false,
    locationId: '',
    description: '',
    stopped: null,
    priority: 'media',
    photos: [],
    preparing: false,
    scanning: false,
    scanError: '',
    stopScan: null,
    errors: {},
    sending: false,
    result: null,
    online: navigator.onLine,
    pendingItems: [],

    init() {
        // Preselezione dal server, oppure dall'indirizzo se la pagina arriva dalla cache (offline)
        const fromUrl = new URLSearchParams(window.location.search).get('macchina');
        const preset = config.preselect ? config.assets.find((a) => a.id === config.preselect) : (fromUrl ? config.assets.find((a) => a.qr === fromUrl) : null);
        if (preset) this.pick(preset);
        window.addEventListener('online', () => (this.online = true));
        window.addEventListener('offline', () => (this.online = false));
        window.addEventListener('outbox-changed', () => this.loadPending());
        this.loadPending();
    },

    get results() {
        const q = norm(this.query).trim();
        if (!q) return [];
        const words = q.split(/\s+/);
        return config.assets.filter((a) => {
            const hay = norm(`${a.code} ${a.name} ${a.loc}`);
            return words.every((w) => hay.includes(w));
        }).slice(0, 20);
    },

    get recents() {
        return config.recent.map((id) => config.assets.find((a) => a.id === id)).filter(Boolean);
    },

    pick(asset) {
        this.asset = asset;
        this.noAsset = false;
        if (asset) this.step = 'details';
        this.$nextTick?.(() => this.$refs.description?.focus());
    },

    chooseNoAsset() {
        this.asset = null;
        this.noAsset = true;
        this.stopped = false;
        this.step = 'details';
    },

    back() {
        this.step = 'asset';
        this.errors = {};
    },

    async scan() {
        this.scanError = '';
        if (!navigator.mediaDevices?.getUserMedia) {
            this.$refs.qrFile.click();
            return;
        }
        this.scanning = true;
        await this.$nextTick();
        try {
            this.stopScan = await startScanner(this.$refs.video, (text) => this.useCode(text));
        } catch (e) {
            this.scanning = false;
            this.scanError = config.t.camera_denied;
        }
    },

    closeScan() {
        this.stopScan?.();
        this.stopScan = null;
        this.scanning = false;
    },

    async scanPhoto(event) {
        const file = event.target.files?.[0];
        event.target.value = '';
        if (!file) return;
        try {
            const text = await scanFile(file);
            text ? this.useCode(text) : (this.scanError = config.t.qr_unreadable);
        } catch (e) {
            this.scanError = config.t.qr_unreadable;
        }
    },

    useCode(text) {
        this.closeScan();
        const token = tokenFromText(text);
        const found = token && config.assets.find((a) => a.qr === token);
        if (found) {
            if (navigator.vibrate) navigator.vibrate(60);
            this.pick(found);
        } else {
            this.scanError = token ? config.t.qr_unknown : config.t.qr_other;
        }
    },

    addChip(chip) {
        const text = this.description.trim();
        this.description = text ? `${text}${/[.!?]$/.test(text) ? '' : '.'} ${chip}` : chip;
        this.$refs.description?.focus();
    },

    async addPhotos(event) {
        const files = Array.from(event.target.files || []);
        event.target.value = '';
        this.preparing = true;
        try {
            for (const f of files.slice(0, MAX_PHOTOS - this.photos.length)) {
                const ready = await preparePhoto(f);
                this.photos.push({ file: ready, url: URL.createObjectURL(ready) });
            }
        } finally {
            this.preparing = false;
        }
    },

    removePhoto(i) {
        URL.revokeObjectURL(this.photos[i].url);
        this.photos.splice(i, 1);
    },

    validate() {
        this.errors = {};
        if (this.description.trim().length < 3) this.errors.description = config.t.need_description;
        if (!this.asset && !this.noAsset) this.errors.asset = config.t.need_asset;
        if (this.asset && this.stopped === null) this.errors.stopped = config.t.need_stopped;
        return Object.keys(this.errors).length === 0;
    },

    async submit() {
        if (this.sending || !this.validate()) return;
        this.sending = true;
        const item = {
            uuid: uuid(),
            userId: config.userId,
            createdAt: new Date().toISOString(),
            label: this.asset ? `${this.asset.code} · ${this.asset.name}` : config.t.no_asset,
            fields: {
                asset_id: this.asset?.id ?? '',
                location_id: this.asset ? '' : this.locationId,
                description: this.description.trim(),
                machine_stopped: !!(this.asset && this.stopped),
                priority: this.priority,
            },
            photos: this.photos.map((p) => ({ name: p.file.name, type: p.file.type, blob: p.file })),
        };

        try {
            // Prima si salva sul telefono: se la pagina si chiude a metà invio, non si perde nulla
            await outbox.add(item);
        } catch (e) { /* IndexedDB non disponibile (navigazione privata): si prova solo l'invio */ }

        const result = await send(item);
        this.sending = false;

        if (result.ok) {
            await outbox.remove(item.uuid).catch(() => {});
            this.result = { queued: false, ...result.data };
            this.step = 'done';
        } else if (result.retry) {
            await requestBackgroundSync();
            this.result = { queued: true, expired: result.status === 401 || result.status === 419 };
            this.step = 'done';
        } else {
            await outbox.remove(item.uuid).catch(() => {});
            this.errors = Object.fromEntries(Object.entries(result.errors || {}).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]));
            if (!Object.keys(this.errors).length) this.errors.general = result.message || config.t.send_error;
        }
        window.dispatchEvent(new CustomEvent('outbox-changed'));
    },

    restart() {
        this.photos.forEach((p) => URL.revokeObjectURL(p.url));
        Object.assign(this, { step: 'asset', query: '', asset: null, noAsset: false, locationId: '', description: '', stopped: null, priority: 'media', photos: [], errors: {}, result: null });
    },

    async loadPending() {
        try { this.pendingItems = await outbox.forUser(config.userId); } catch (e) { this.pendingItems = []; }
    },

    async retryPending() {
        await flush(config.userId, { includeFailed: true });
        await this.loadPending();
    },

    async discard(uuid) {
        if (!window.confirm(config.t.discard_confirm)) return;
        await outbox.remove(uuid);
        window.dispatchEvent(new CustomEvent('outbox-changed'));
    },
});
