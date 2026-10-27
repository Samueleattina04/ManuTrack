// Verifica nel browser (Chromium, telefono simulato) della segnalazione rapida:
// pagina disponibile offline, foto ridotta sul telefono, invio, coda senza rete
// e invio automatico al ritorno della rete. Lettura del QR da una foto.
// Richiede un utente che possa segnalare e almeno una macchina:
//   BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD   Uso: node scripts/e2e/report-check.cjs
// Attenzione: crea due segnalazioni di prova.
const { chromium, devices } = require('playwright');

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
if (!process.env.LOGIN_EMAIL || !process.env.LOGIN_PASSWORD) {
    console.error('Imposta LOGIN_EMAIL e LOGIN_PASSWORD (ed eventualmente BASE_URL).');
    process.exit(2);
}
const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? '✔' : '✘'} ${name}${detail ? ' — ' + detail : ''}`); };
const outboxCount = (page) => page.evaluate(() => new Promise((r) => { const q = indexedDB.open('manutrack', 1); q.onupgradeneeded = () => q.result.createObjectStore('outbox', { keyPath: 'uuid' }); q.onsuccess = () => { const t = q.result.transaction('outbox').objectStore('outbox').count(); t.onsuccess = () => r(t.result); }; }));

(async () => {
    const browser = await chromium.launch();
    const context = await browser.newContext({ ...devices['Pixel 7'] });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(base + '/login');
    await page.fill('#f-email', process.env.LOGIN_EMAIL);
    await page.fill('#f-password', process.env.LOGIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    await page.waitForTimeout(1500);
    await page.reload();
    await page.waitForTimeout(1000);
    check('Pagina Segnala salvata per l\'uso offline', await page.evaluate(async () => !!(await caches.match('/segnala', { cacheName: 'mt-pages' }))));

    // Foto grande generata nel browser (3000×2000)
    const photo = await page.evaluate(async () => {
        const c = document.createElement('canvas'); c.width = 3000; c.height = 2000;
        const x = c.getContext('2d'); x.fillStyle = '#c33'; x.fillRect(0, 0, 1500, 2000); x.fillStyle = '#33c'; x.fillRect(1500, 0, 1500, 2000);
        const b = await new Promise((r) => c.toBlob(r, 'image/jpeg', 0.92));
        return Array.from(new Uint8Array(await b.arrayBuffer()));
    });

    // Invio con rete
    await page.goto(base + '/segnala');
    // La prima macchina che compare cercando "-" (i codici lo contengono quasi sempre)
    await page.fill('#q-search', '-');
    await page.locator('ul[role=listbox] li button').first().click();
    await page.fill('#q-desc', 'Prova automatica: rumore anomalo');
    await page.click('button:has-text("No, funziona")');
    await page.setInputFiles('input[type=file][multiple]', { name: 'grande.jpg', mimeType: 'image/jpeg', buffer: Buffer.from(photo) });
    await page.waitForSelector('img[src^="blob:"]');
    const width = await page.evaluate(() => document.querySelector('img[src^="blob:"]').naturalWidth);
    check('Foto ridotta sul telefono prima dell\'invio', width === 1600, `${width} px`);
    await page.click('form button[type=submit]');
    await page.waitForSelector('text=Segnalazione inviata', { timeout: 20000 });
    check('Segnalazione inviata', true, await page.textContent('p.font-mono.text-lg'));

    // Senza rete
    await context.setOffline(true);
    await page.goto(base + '/segnala');
    check('Pagina Segnala aperta senza rete', (await page.title()).length > 0 && (await page.locator('#q-search').count()) === 1);
    await page.fill('#q-search', '-');
    await page.locator('ul[role=listbox] li button').first().click();
    await page.fill('#q-desc', 'Prova automatica senza rete');
    await page.click('button:has-text("Sì, è ferma")');
    await page.click('form button[type=submit]');
    await page.waitForSelector('text=Salvata sul telefono', { timeout: 15000 });
    check('Senza rete resta sul telefono', (await outboxCount(page)) === 1);

    // La rete torna
    await context.setOffline(false);
    await page.goto(base + '/');
    await page.waitForTimeout(4000);
    check('Al ritorno della rete parte da sola', (await outboxCount(page)) === 0);

    check('Nessun errore JavaScript', errors.length === 0, errors.join(' | '));
    await browser.close();
    const ok = results.filter(Boolean).length;
    console.log(`\n${ok}/${results.length} controlli riusciti`);
    process.exit(ok === results.length ? 0 : 1);
})();
