// Verifica nel browser (Chromium) delle pagine Macchine: cambio di stato, lettura di un
// contatore, caricamento di una foto, importazione CSV ed etichette QR.
// Richiede un'installazione con almeno una macchina e un utente amministratore:
// BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD.  Uso: node scripts/e2e/assets-check.cjs
// Attenzione: crea una macchina "E2E-01" e modifica lo stato della prima macchina.
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
if (!process.env.LOGIN_EMAIL || !process.env.LOGIN_PASSWORD) {
    console.error('Imposta LOGIN_EMAIL e LOGIN_PASSWORD (ed eventualmente BASE_URL).');
    process.exit(2);
}
const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? '✔' : '✘'} ${name}${detail ? ' — ' + detail : ''}`); };

(async () => {
    const browser = await chromium.launch();
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(base + '/login');
    await page.fill('#f-email', process.env.LOGIN_EMAIL);
    await page.fill('#f-password', process.env.LOGIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

    // Elenco e filtro per stato
    await page.goto(base + '/macchine');
    const rows = await page.locator('table tbody tr').count();
    check('Elenco delle macchine', rows > 0, `${rows} righe`);
    const firstLink = await page.locator('table tbody tr a').first().getAttribute('href');

    // Scheda: cambio di stato
    await page.goto(firstLink);
    const target = await page.locator('input[name=newStatus]:not([disabled])').first();
    const value = await target.getAttribute('value');
    await target.locator('xpath=..').click();
    await page.getByRole('button', { name: /Segna come/ }).click();
    await page.waitForTimeout(800);
    check('Cambio di stato dalla scheda', (await page.locator('.status').first().textContent()).trim().length > 0 && (await page.content()).includes(`value="${value}" wire:model.live="newStatus" class="sr-only" disabled`), value);

    // Contatore: la prima macchina dell'elenco che ne ha uno
    await page.goto(base + '/macchine');
    const links = await page.locator('table tbody tr a').evaluateAll((as) => as.map((a) => a.href));
    let reading = null;
    for (const link of links.slice(0, 15)) {
        await page.goto(link);
        if (await page.locator('input[id^="r-"]').count()) { reading = page.locator('input[id^="r-"]').first(); break; }
    }
    check('Macchina con contatore trovata', reading !== null);
    if (reading) {
        const current = await page.locator('section[aria-labelledby=s-meters] .text-2xl').first().textContent();
        const next = parseInt(current.replace(/\D/g, ''), 10) + 7;
        await reading.fill(String(next));
        await page.getByRole('button', { name: 'Registra' }).first().click();
        await page.waitForTimeout(800);
        const shown = await page.locator('section[aria-labelledby=s-meters] .text-2xl').first().textContent();
        check('Lettura del contatore registrata', shown.replace(/\D/g, '') === String(next), shown);
    }

    // Foto: caricamento, miniatura e file servito come JPEG
    const photo = path.join(os.tmpdir(), 'e2e-foto.png');
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DAwMDAxMDAwMDAAAANHQEDasKb6QAAAABJRU5ErkJggg==', 'base64');
    fs.writeFileSync(photo, png);
    await page.goto(firstLink);
    const before = await page.locator('section[aria-labelledby=s-photos] img').count();
    await page.setInputFiles('input[type=file][x-photo-upload=photos]', photo);
    await page.waitForTimeout(2500);
    const imgs = page.locator('section[aria-labelledby=s-photos] img');
    check('Foto caricata con miniatura', (await imgs.count()) === before + 1);
    const src = await imgs.last().getAttribute('src');
    const res = await page.request.get(src);
    check('Foto servita dal controllo dei permessi, in WebP', res.ok() && res.headers()['content-type'] === 'image/webp', res.headers()['content-type']);

    // Importazione CSV con anteprima
    const csv = path.join(os.tmpdir(), 'e2e-macchine.csv');
    fs.writeFileSync(csv, 'codice;nome;criticita\nE2E-01;Macchina di prova E2E;C\n');
    await page.goto(base + '/macchine/importa');
    await page.setInputFiles('input[type=file]', csv);
    await page.getByText('Anteprima').first().waitFor({ timeout: 10000 });
    await page.getByRole('button', { name: /Importa 1 macchina/ }).click();
    await page.getByText('Importazione completata').waitFor({ timeout: 10000 });
    check('Importazione con anteprima e conferma', true);

    // Etichette
    const id = firstLink.split('/').pop();
    await page.goto(`${base}/macchine/etichette?ids=${id}&formato=piccole`);
    check('Etichette QR', (await page.locator('.label svg').count()) === 1);

    check('Nessun errore JavaScript', errors.length === 0, errors.join(' | '));
    await browser.close();
    const ok = results.filter(Boolean).length;
    console.log(`\n${ok}/${results.length} controlli riusciti`);
    process.exit(ok === results.length ? 0 : 1);
})();
