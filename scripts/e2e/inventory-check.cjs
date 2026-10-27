// Verifica nel browser (Chromium) del magazzino ricambi: nuovo ricambio con giacenza
// iniziale, prelievo e rettifica dalla scheda, riordino, prelievo e reso su un ordine.
// Richiede un'installazione con almeno un magazzino attivo, un ordine di lavoro in corso
// o in attesa, e un utente amministratore: BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD.
// Uso: node scripts/e2e/inventory-check.cjs
// Attenzione: crea un ricambio "E2E-<numero>" e registra movimenti di magazzino.
const { chromium } = require('playwright');

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
if (!process.env.LOGIN_EMAIL || !process.env.LOGIN_PASSWORD) {
    console.error('Imposta LOGIN_EMAIL e LOGIN_PASSWORD (ed eventualmente BASE_URL).');
    process.exit(2);
}
const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? '✔' : '✘'} ${name}${detail ? ' — ' + detail : ''}`); };
const text = async (loc) => ((await loc.textContent()) || '').replace(/\s+/g, ' ').trim();

(async () => {
    const browser = await chromium.launch();
    const page = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));

    await page.goto(base + '/login');
    await page.fill('#f-email', process.env.LOGIN_EMAIL);
    await page.fill('#f-password', process.env.LOGIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

    // Nuovo ricambio con giacenza iniziale
    const code = 'E2E-' + Date.now().toString().slice(-6);
    await page.goto(base + '/magazzino/ricambi/nuovo');
    await page.fill('[name="form.code"]', code);
    await page.fill('[name="form.name"]', 'Ricambio di prova automatica');
    await page.fill('[name="form.min_stock"]', '3');
    await page.fill('[name="initial.quantity"]', '5');
    await page.fill('[name="initial.unit_cost"]', '10,00');
    await Promise.all([page.waitForURL(/\/magazzino\/ricambi\/\d+$/), page.click('form button[type=submit]')]);
    const partUrl = page.url();
    check('Nuovo ricambio con giacenza iniziale', (await text(page.locator('main'))).includes(code));

    // Prelievo dalla scheda: scende alla scorta minima
    await page.click('button:has-text("Preleva")');
    await page.waitForSelector('#mv-title');
    await page.fill('#mv-qty', '2');
    await page.click('form:has(#mv-qty) button[type=submit]');
    await page.waitForTimeout(800);
    await page.reload();
    check('Prelievo dalla scheda: ricambio sotto scorta', (await text(page.locator('main'))).includes('Sotto scorta'));

    // Riordino
    await page.goto(base + '/magazzino/riordino');
    check('Il ricambio compare tra quelli da riordinare', (await text(page.locator('main'))).includes(code));

    // Rettifica
    await page.goto(partUrl);
    await page.click('button:has-text("Rettifica")');
    await page.waitForSelector('#mv-title');
    await page.fill('#mv-qty', '10');
    await page.fill('form:has(#mv-qty) [name="move.note"]', 'Conta di prova');
    await page.click('form:has(#mv-qty) button[type=submit]');
    await page.waitForTimeout(800);
    await page.reload();
    check('Rettifica: torna disponibile', (await text(page.locator('main'))).includes('Disponibile'));

    // Prelievo e reso su un ordine attivo
    await page.goto(base + '/ordini');
    const orderLink = await page.locator('a[href*="/ordini/"]').filter({ hasText: /OdL-/ }).first().getAttribute('href').catch(() => null);
    if (!orderLink) {
        check('Ordine di lavoro attivo per il prelievo', false, 'nessun ordine trovato');
    } else {
        await page.goto(orderLink.startsWith('http') ? orderLink : base + orderLink);
        const issueBtn = page.locator('button:has-text("Preleva ricambio")');
        if (!(await issueBtn.count())) {
            check('Sezione Ricambi nell\'ordine', false, 'ordine non attivo o permessi mancanti');
        } else {
            await issueBtn.click();
            await page.fill('#op-search', code);
            await page.waitForTimeout(800);
            await page.click(`section:has(#w-parts) li button:has-text("${code}")`);
            await page.waitForTimeout(500);
            await page.fill('#op-qty', '2');
            await page.click('section:has(#w-parts) form button[type=submit]');
            await page.waitForTimeout(900);
            const row = page.locator(`section:has(#w-parts) > ul > li:has-text("${code}")`);
            check('Prelievo dall\'ordine', (await row.count()) === 1 && (await text(row)).includes('2 pz'));
            await row.locator('button:has-text("Rendi")').click();
            await page.fill('#op-ret', '5');
            await page.click('section:has(#w-parts) form button[type=submit]');
            await page.waitForTimeout(700);
            check('Reso oltre il prelevato rifiutato', (await page.locator('section:has(#w-parts) .field-error').count()) > 0);
            await page.fill('#op-ret', '1');
            await page.click('section:has(#w-parts) form button[type=submit]');
            await page.waitForTimeout(800);
            check('Reso registrato', (await text(row)).includes('1 pz'));
        }
    }

    check('Nessun errore JavaScript', errors.length === 0, errors.join(' | '));
    await browser.close();
    const failed = results.filter((r) => !r).length;
    console.log(failed ? `\n${failed} controlli non superati` : '\nTutti i controlli superati');
    process.exit(failed ? 1 : 0);
})();
