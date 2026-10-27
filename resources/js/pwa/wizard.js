// Procedura guidata: installazione dell'app e attivazione delle notifiche.
import { environment, deviceName, enablePush, sendTest, currentSubscription } from './core';

export default (config = {}) => ({
    env: environment(),
    device: '',
    pushConfigured: !!config.pushConfigured,
    installAvailable: !!window.__mtInstallPrompt,
    installSkipped: false,
    subscribed: false,
    busy: false,
    message: '',
    messageTone: 'ok',
    copied: false,
    url: window.location.href,

    async init() {
        this.device = deviceName(this.env);
        window.addEventListener('install-available', () => (this.installAvailable = true));
        window.addEventListener('app-installed', () => { this.installAvailable = false; this.installSkipped = true; });
        try { this.subscribed = !!(await currentSubscription()) && this.env.permission === 'granted'; } catch (e) { this.subscribed = false; }
    },

    get step() {
        if (!this.env.standalone && !this.installSkipped && this.env.platform !== 'desktop') return 'install';
        if (!this.subscribed) return 'notify';
        return 'test';
    },

    get canNotify() {
        return this.env.secure && this.env.pushSupported && this.pushConfigured && !this.env.pushNeedsInstall && !this.env.iosTooOld;
    },

    get notifyBlocker() {
        if (!this.env.secure) return 'insecure';
        if (this.env.iosTooOld) return 'ios_old';
        if (this.env.pushNeedsInstall) return 'need_install';
        if (!this.env.pushSupported) return 'unsupported';
        if (!this.pushConfigured) return 'no_keys';
        if (this.env.permission === 'denied') return 'denied';
        return null;
    },

    async install() {
        const prompt = window.__mtInstallPrompt;
        if (!prompt) return;
        prompt.prompt();
        const choice = await prompt.userChoice;
        window.__mtInstallPrompt = null;
        this.installAvailable = false;
        if (choice.outcome === 'accepted') this.installSkipped = true;
    },

    async enable() {
        this.busy = true;
        this.message = '';
        try {
            const result = await enablePush();
            this.env.permission = result.permission;
            this.subscribed = result.ok;
            if (!result.ok && result.permission !== 'denied') {
                this.say(config.errorText.replace(':message', result.message || result.permission), 'ko');
            }
        } catch (e) {
            this.say(config.errorText.replace(':message', e.message), 'ko');
        } finally {
            this.busy = false;
        }
    },

    async test() {
        this.busy = true;
        try {
            const res = await sendTest(true);
            this.say(res.data.message || `HTTP ${res.status}`, res.ok && res.data.ok ? 'ok' : 'ko');
        } finally {
            this.busy = false;
        }
    },

    async copy() {
        try { await navigator.clipboard.writeText(this.url); this.copied = true; } catch (e) { this.$refs.link?.select(); }
    },

    say(text, tone) {
        this.message = text;
        this.messageTone = tone;
    },
});
