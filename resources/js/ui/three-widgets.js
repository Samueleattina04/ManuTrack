// Piccoli componenti Alpine che caricano il 3D solo quando serve (Three.js è in un file a parte).
import { deviceTier, reducedMotion } from '../three/capability';

// M di ManuTrack in 3D al posto del logo SVG, quando il logo è visibile e il dispositivo regge
export const logo3d = () => ({
    ready: false,
    ctrl: null,
    io: null,

    init() {
        if (reducedMotion() || deviceTier() !== 'full') return;
        this.io = new IntersectionObserver(async ([entry]) => {
            if (!entry.isIntersecting) return;
            this.io.disconnect();
            try {
                const { mountLogo } = await import('../three/logo3d.js');
                this.ctrl = mountLogo(this.$refs.stage);
                this.ready = true;
            } catch (e) {
                // Resta il logo SVG
            }
        });
        this.io.observe(this.$el);
    },

    // iPhone: il giroscopio va chiesto con un gesto della persona (tocco sul logo)
    askMotion() {
        if (this.ready && typeof DeviceOrientationEvent !== 'undefined' && typeof DeviceOrientationEvent.requestPermission === 'function') {
            DeviceOrientationEvent.requestPermission().catch(() => {});
        }
    },

    destroy() {
        this.io?.disconnect();
        this.ctrl?.dispose();
    },
});

// Anteprima 3D della macchina nella sua scheda: si apre solo su richiesta
export const assetPreview = (cfg) => ({
    open: false,
    loading: false,
    error: null,
    can3d: deviceTier() !== 'none',
    ctrl: null,

    async show() {
        this.open = true;
        this.error = null;
        if (this.ctrl || this.loading) return;
        this.loading = true;
        try {
            await this.$nextTick();
            const { mountPreview } = await import('../three/preview3d.js');
            this.ctrl = await mountPreview(this.$refs.stage, cfg);
        } catch (e) {
            console.error(e);
            this.error = cfg.error;
            this.open = false;
        } finally {
            this.loading = false;
        }
    },

    hide() {
        this.ctrl?.dispose();
        this.ctrl = null;
        this.open = false;
    },

    destroy() {
        this.ctrl?.dispose();
    },
});
