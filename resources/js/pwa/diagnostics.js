// Pagina diagnostica: mostra perché una notifica non arriva su questo dispositivo.
import { environment, deviceName, registration, currentSubscription, sha256, enablePush, disablePush, sendTest, workerVersion } from './core';

export default (config = {}) => ({
    env: environment(),
    device: '',
    checks: [],
    busy: false,
    message: '',
    messageTone: 'ok',
    currentHash: null,

    async init() {
        this.device = deviceName(this.env);
        await this.refresh();
    },

    async refresh() {
        const t = config.texts;
        const env = (this.env = environment());
        const reg = await registration().catch(() => null);
        const version = await workerVersion().catch(() => null);
        const sub = await currentSubscription().catch(() => null);
        this.currentHash = sub ? await sha256(sub.endpoint) : null;
        const onServer = this.currentHash ? config.hashes.includes(this.currentHash) : false;
        const v = t.values;

        const row = (key, state, value, hint = null) => ({ key, label: t.checks[key], state, value, hint });
        this.checks = [
            row('https', env.secure ? 'ok' : 'ko', env.secure ? v.yes : v.no, env.secure ? null : t.hints.https),
            row('sw_support', env.swSupported ? 'ok' : 'ko', env.swSupported ? v.yes : v.no),
            row('sw_active', reg?.active ? 'ok' : 'warn', reg?.active ? (version || v.yes) : v.no),
            row('installed', env.standalone ? 'ok' : (env.isIOS ? 'ko' : 'info'), env.standalone ? v.yes : (env.isIOS ? v.no : v.not_needed), env.isIOS && !env.standalone ? t.hints.installed_ios : null),
            ...(env.isIOS ? [row('ios_version', env.iosTooOld ? 'ko' : 'ok', env.iosVersion ? v.version.replace(':version', env.iosVersion) : '?', env.iosTooOld ? t.hints.ios_version : null)] : []),
            row('push_support', env.pushSupported ? 'ok' : 'ko', env.pushSupported ? v.yes : v.unsupported),
            row('permission', env.permission === 'granted' ? 'ok' : env.permission === 'denied' ? 'ko' : 'warn', v[env.permission] || env.permission, env.permission === 'denied' ? t.hints.permission_denied : null),
            row('subscription', sub ? 'ok' : 'warn', sub ? v.yes : v.no, sub ? null : t.hints.subscription),
            row('server_subscription', onServer ? 'ok' : 'warn', onServer ? v.yes : v.no, sub && !onServer ? t.hints.server_subscription : null),
            row('vapid', config.pushConfigured ? 'ok' : 'ko', config.pushConfigured ? v.yes : v.no, config.pushConfigured ? null : t.hints.vapid),
            row('badge', env.badging ? 'ok' : 'info', env.badging ? v.yes : v.unsupported, env.badging ? null : t.hints.badge),
        ];
    },

    async enable() {
        this.busy = true;
        try {
            const result = await enablePush();
            this.say(result.ok ? config.texts.enabled : (result.message || result.permission), result.ok ? 'ok' : 'ko');
            if (result.ok) window.location.reload();
        } catch (e) {
            this.say(e.message, 'ko');
        } finally {
            this.busy = false;
            await this.refresh();
        }
    },

    async disable() {
        this.busy = true;
        await disablePush().catch(() => {});
        this.busy = false;
        window.location.reload();
    },

    async test(onlyThis) {
        this.busy = true;
        try {
            const res = await sendTest(onlyThis);
            this.say(res.data.message || `HTTP ${res.status}`, res.ok && res.data.ok ? 'ok' : 'ko');
        } finally {
            this.busy = false;
        }
    },

    async copyReport() {
        const lines = [`ManuTrack · ${config.appUrl}`, `Dispositivo: ${this.device}`, `User agent: ${navigator.userAgent}`, ...this.checks.map((c) => `${c.label}: ${c.value}`)];
        try { await navigator.clipboard.writeText(lines.join('\n')); this.say(config.texts.copied, 'ok'); } catch (e) { this.say(lines.join(' | '), 'info'); }
    },

    say(text, tone) {
        this.message = text;
        this.messageTone = tone;
    },
});
