// Verifica nel browser (Chromium) dell'app installabile. Richiede un'installazione
// raggiungibile e un utente: BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD.
// Uso: node scripts/e2e/pwa-check.cjs   (Playwright: npx playwright install chromium)
const { chromium } = require('playwright');

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
const results = [];
const check = (name, ok, detail = '') => { results.push({ name, ok, detail }); console.log(`${ok ? '✔' : '✘'} ${name}${detail ? ' — ' + detail : ''}`); };

if (!process.env.LOGIN_EMAIL || !process.env.LOGIN_PASSWORD) {
    console.error('Imposta LOGIN_EMAIL e LOGIN_PASSWORD (ed eventualmente BASE_URL).');
    process.exit(2);
}

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    // Conta le richieste di permesso: non devono partire al caricamento delle pagine
    await context.addInitScript(() => {
        window.__permissionRequests = 0;
        if ('Notification' in window) {
            const original = Notification.requestPermission.bind(Notification);
            Notification.requestPermission = (...a) => { window.__permissionRequests++; return original(...a); };
        }
    });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(base + '/login');
    await page.fill('#f-email', process.env.LOGIN_EMAIL);
    await page.fill('#f-password', process.env.LOGIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

    // Service worker
    await page.waitForFunction(() => navigator.serviceWorker && navigator.serviceWorker.ready.then(() => true), null, { timeout: 15000 });
    const sw = await page.evaluate(async () => { const r = await navigator.serviceWorker.ready; return { active: !!r.active, scope: r.scope }; });
    check('Service worker attivo', sw.active, sw.scope);
    await page.reload({ waitUntil: 'networkidle' });
    check('Pagina controllata dal service worker', await page.evaluate(() => !!navigator.serviceWorker.controller));
    const cached = await page.evaluate(async () => { const keys = await caches.keys(); const c = await caches.open(keys.find((k) => k.startsWith('mt-static-'))); return (await c.keys()).length; });
    check('File statici in cache', cached > 3, `${cached} file`);

    // Manifest
    const manifest = await page.evaluate(async () => (await fetch(document.querySelector('link[rel=manifest]').href)).json());
    check('Manifest con display standalone e icone maskable', manifest.display === 'standalone' && manifest.icons.some((i) => i.purpose === 'maskable'));

    // Nessuna richiesta di permesso al caricamento
    for (const path of ['/', '/app', '/notifiche/diagnostica']) {
        await page.goto(base + path, { waitUntil: 'networkidle' });
        check(`Nessuna richiesta di permesso al caricamento di ${path}`, (await page.evaluate(() => window.__permissionRequests)) === 0);
    }

    // Diagnostica
    await page.goto(base + '/notifiche/diagnostica', { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    const rows = await page.locator('li:has(.status)').count();
    check('Diagnostica: controlli mostrati', rows >= 9, `${rows} voci`);

    // Offline
    await context.setOffline(true);
    await page.goto(base + '/utenti').catch(() => {});
    const offlineText = await page.textContent('h1').catch(() => '');
    check('Senza rete compare la pagina offline', /offline/i.test(offlineText || ''), offlineText);
    await context.setOffline(false);

    // Notifiche: permesso concesso dal test, poi tocco su "Attiva notifiche"
    await context.grantPermissions(['notifications'], { origin: base });
    await page.goto(base + '/notifiche/diagnostica', { waitUntil: 'networkidle' });
    await page.click('text=Attiva notifiche');
    await page.waitForTimeout(6000);
    const sub = await page.evaluate(async () => { const r = await navigator.serviceWorker.ready; const s = await r.pushManager.getSubscription(); return s ? s.endpoint : null; });
    check('Iscrizione push creata (richiede accesso al servizio push di Google)', !!sub, sub ? new URL(sub).host : 'non disponibile in questo ambiente');

    check('Nessun errore JavaScript', errors.length === 0, errors.join(' | '));
    await browser.close();

    const failed = results.filter((r) => !r.ok);
    console.log(`\n${results.length - failed.length}/${results.length} controlli riusciti`);
    process.exit(failed.some((r) => !r.name.includes('servizio push')) ? 1 : 0);
})();
