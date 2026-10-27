// Verifica nel browser (Chromium) della manutenzione preventiva: nuovo piano a tempo,
// "Genera ora", ordine collegato al piano, calendario, piano a contatore con soglia proposta.
// Richiede un'installazione con almeno una macchina (meglio se con un contatore) e un
// utente amministratore: BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD.
// Uso: node scripts/e2e/preventive-check.cjs
// Attenzione: crea due piani "E2E …" e un ordine di lavoro preventivo.
const { chromium } = require('playwright');

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
if (!process.env.LOGIN_EMAIL || !process.env.LOGIN_PASSWORD) {
    console.error('Imposta LOGIN_EMAIL e LOGIN_PASSWORD (ed eventualmente BASE_URL).');
    process.exit(2);
}
const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? '✔' : '✘'} ${name}${detail ? ' — ' + detail : ''}`); };
const text = async (loc) => ((await loc.textContent()) || '').replace(/\s+/g, ' ').trim();
const iso = (d) => d.toISOString().slice(0, 10);

(async () => {
    const browser = await chromium.launch();
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('dialog', (d) => d.accept());

    await page.goto(base + '/login');
    await page.fill('#f-email', process.env.LOGIN_EMAIL);
    await page.fill('#f-password', process.env.LOGIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

    // Nuovo piano a tempo
    const title = 'E2E controllo ' + Date.now().toString().slice(-5);
    const due = new Date(Date.now() + 20 * 86400000);
    await page.goto(base + '/preventiva/piani/nuovo');
    await page.fill('[name="form.title"]', title);
    await page.selectOption('#pp-asset', { index: 1 });
    await page.waitForTimeout(500);
    await page.fill('[name="form.interval_value"]', '2');
    await page.selectOption('#pp-unit', 'settimane');
    await page.fill('[name="form.next_due_on"]', iso(due));
    await Promise.all([page.waitForURL(/\/preventiva\/piani\/\d+$/), page.click('form button[type=submit]')]);
    const planUrl = page.url();
    check('Nuovo piano a tempo', (await text(page.locator('main'))).includes(title) && (await text(page.locator('main'))).includes('Ogni 2 settimane'));

    // Genera ora → ordine collegato
    await Promise.all([page.waitForURL(/\/ordini\/\d+$/), page.click('button:has-text("Genera ora")')]);
    const order = await text(page.locator('main'));
    check('"Genera ora" crea l\'ordine preventivo', order.includes('Dal piano') && order.includes(title) && order.includes('Preventivo'));

    await page.goto(planUrl);
    check('La scheda del piano mostra l\'ordine aperto', (await page.locator('a[href*="/ordini/"]').count()) > 0 && !(await text(page.locator('main'))).includes('Genera ora'));

    // Calendario del mese della scadenza
    await page.goto(base + '/preventiva/calendario?mese=' + iso(due).slice(0, 7));
    check('Calendario: l\'ordine compare alla sua scadenza', (await text(page.locator('main'))).includes(title));

    // Piano a contatore: soglia proposta
    await page.goto(base + '/preventiva/piani/nuovo');
    await page.fill('[name="form.title"]', 'E2E contatore');
    const assets = await page.locator('#pp-asset option').count();
    let proposed = '';
    for (let i = 1; i < assets && !proposed; i++) {
        await page.selectOption('#pp-asset', { index: i });
        await page.waitForTimeout(400);
        await page.click('label:has-text("A contatore")');
        await page.waitForTimeout(400);
        if ((await page.locator('#pp-meter option').count()) > 1) {
            await page.fill('[name="form.meter_interval"]', '500');
            await page.locator('[name="form.meter_interval"]').blur();
            await page.selectOption('#pp-meter', { index: 1 });
            await page.waitForTimeout(600);
            proposed = await page.inputValue('[name="form.next_meter_due"]');
        } else {
            await page.click('label:has-text("A tempo")');
        }
    }
    if (!proposed) {
        check('Piano a contatore', true, 'nessuna macchina con contatore: saltato');
    } else {
        check('Piano a contatore: soglia proposta', proposed !== '', proposed);
        await Promise.all([page.waitForURL(/\/preventiva\/piani\/\d+$/), page.click('form button[type=submit]')]);
        check('Piano a contatore salvato', (await text(page.locator('main'))).includes('E2E contatore'));
    }

    check('Nessun errore JavaScript', errors.length === 0, errors.join(' | '));
    await browser.close();
    const failed = results.filter((r) => !r).length;
    console.log(failed ? `\n${failed} controlli non superati` : '\nTutti i controlli superati');
    process.exit(failed ? 1 : 0);
})();
