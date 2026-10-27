/*
 * ManuTrack — script principale (leggero: niente 3D né grafici qui;
 * quei moduli si caricano con import() solo nelle pagine che li usano).
 */
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import theme from './ui/theme';
import shortcuts from './ui/shortcuts';
import commandPalette from './ui/command-palette';
import tilt from './ui/tilt';
import countUp from './ui/count-up';
import kpiChart from './ui/charts';
import registerServiceWorker, { updateBanner } from './pwa/register';
import installWizard from './pwa/wizard';
import pushDiagnostics from './pwa/diagnostics';
import { environment } from './pwa/core';
import { photoUploadDirective } from './media/photo';
import quickReport from './requests/quick';
import { outboxBanner } from './requests/outbox';

Alpine.data('theme', theme);
Alpine.data('shortcuts', shortcuts);
Alpine.data('commandPalette', commandPalette);
Alpine.data('kpiChart', kpiChart);
Alpine.directive('tilt', tilt);
Alpine.directive('count-up', countUp);
// Foto ridotte e convertite sul telefono prima del caricamento (vedi media/photo.js)
Alpine.directive('photo-upload', photoUploadDirective);
Alpine.data('swUpdate', updateBanner);
Alpine.data('installWizard', installWizard);
Alpine.data('pushDiagnostics', pushDiagnostics);
Alpine.data('quickReport', quickReport);
Alpine.data('outboxBanner', outboxBanner);
// Invito all'installazione in panoramica: compare sul telefono finché l'app non è installata
Alpine.data('appPrompt', () => ({
    env: environment(),
    dismissed: (() => { try { return localStorage.getItem('mt-app-prompt') === 'no'; } catch (e) { return false; } })(),
    get visible() { return !this.dismissed && this.env.platform !== 'desktop' && (!this.env.standalone || this.env.permission === 'default'); },
    dismiss() { this.dismissed = true; try { localStorage.setItem('mt-app-prompt', 'no'); } catch (e) {} },
}));

registerServiceWorker();

Livewire.start();

// Contatore degli avvisi non letti sull'icona dell'app installata (dove il sistema lo permette)
document.addEventListener('DOMContentLoaded', () => {
    const unread = Number(document.body.dataset.unread);
    if (Number.isNaN(unread) || !('setAppBadge' in navigator)) return;
    (unread > 0 ? navigator.setAppBadge(unread) : navigator.clearAppBadge()).catch(() => {});
});
