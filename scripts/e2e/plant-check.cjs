// Verifica nel browser (Chromium) del 3D (fase 10):
// - le pagine operative (segnalazione, ordini, scheda macchina chiusa) non scaricano Three.js;
// - lo stabilimento parte in 3D su un dispositivo capace, in 2D su uno debole (e si può provare
//   il 3D), solo in 2D senza WebGL; la vista 2D apre il pannello della macchina;
// - nell'editor una macchina si trascina e la posizione resta salvata;
// - l'anteprima 3D della macchina (forma generica o GLB) si apre su richiesta;
// - con "riduci movimento" la M d'ingresso resta in SVG.
// Richiede un'installazione con una pianta (Stabilimento) con macchine e un amministratore:
// BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD; facoltativo GLB_FILE (un file .glb da caricare).
// Uso: node scripts/e2e/plant-check.cjs
// Attenzione: sposta una macchina nella prima pianta e, con GLB_FILE, carica un modello sulla prima macchina.
const { chromium } = require('playwright');

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
if (!process.env.LOGIN_EMAIL || !process.env.LOGIN_PASSWORD) {
    console.error('Imposta LOGIN_EMAIL e LOGIN_PASSWORD (ed eventualmente BASE_URL).');
    process.exit(2);
}
const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? '✔' : '✘'} ${name}${detail ? ' — ' + detail : ''}`); };
const is3d = (url) => /\/build\/assets\/(three|plant3d|preview3d|logo3d|shapes)-[\w-]+\.js/.test(url);

(async () => {
    const browser = await chromium.launch();
    const errors = [];
    // Un solo accesso (l'app limita i tentativi al minuto): la sessione si riusa in ogni contesto
    let session = null;
    const login = async () => {
        const ctx = await browser.newContext();
        const p = await ctx.newPage();
        await p.goto(base + '/login');
        await p.fill('#f-email', process.env.LOGIN_EMAIL);
        await p.fill('#f-password', process.env.LOGIN_PASSWORD);
        await Promise.all([p.waitForNavigation(), p.click('button[type=submit]')]);
        session = await ctx.storageState();
        await ctx.close();
    };
    const context = async (opts = {}, init = null) => {
        if (!session) await login();
        const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, storageState: session, ...opts });
        if (init) await ctx.addInitScript(init);
        return ctx;
    };
    const watch = (page) => {
        const loaded = [];
        page.on('request', (r) => { if (is3d(r.url())) loaded.push(r.url()); });
        page.on('pageerror', (e) => errors.push(e.message));
        page.on('dialog', (d) => d.accept());
        return loaded;
    };

    // 1. Pagine operative senza 3D (anche su un telefono)
    const phone = await context({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
    let page = await phone.newPage();
    let loaded = watch(page);
    await page.goto(base + '/segnala', { waitUntil: 'networkidle' });
    await page.goto(base + '/ordini?vista=tutti', { waitUntil: 'networkidle' });
    const order = await page.evaluate(() => [...document.querySelectorAll('a[href*="/ordini/"]')].map((a) => a.getAttribute('href')).find((h) => /\/ordini\/\d+$/.test(h)));
    if (order) await page.goto(new URL(order, base).href, { waitUntil: 'networkidle' });
    await page.goto(base + '/macchine', { waitUntil: 'networkidle' });
    const assetHref = await page.evaluate(() => [...document.querySelectorAll('a[href*="/macchine/"]')].map((a) => a.getAttribute('href')).find((h) => /\/macchine\/\d+$/.test(h)));
    await page.goto(new URL(assetHref, base).href, { waitUntil: 'networkidle' });
    check('Segnalazione, ordini e scheda macchina non scaricano Three.js', loaded.length === 0, loaded.join(', '));

    // 2. Anteprima 3D su richiesta nella scheda macchina
    await page.click('button:has-text("Mostra in 3D")');
    await page.locator('[x-ref="stage"][data-ready="1"]').first().waitFor({ timeout: 20000 }).catch(() => {});
    check('Anteprima 3D della macchina su richiesta', (await page.locator('[x-ref="stage"][data-ready="1"] canvas').count()) === 1 && loaded.some((u) => u.includes('preview3d-')));
    await phone.close();

    // 3. Stabilimento su un dispositivo capace: 3D
    const pc = await context();
    page = await pc.newPage();
    loaded = watch(page);
    await page.goto(base + '/stabilimento', { waitUntil: 'networkidle' });
    await page.locator('[data-ready="1"]').waitFor({ timeout: 20000 }).catch(() => {});
    check('Stabilimento in 3D su un dispositivo capace', (await page.locator('[data-ready="1"] canvas').count()) === 1 && loaded.some((u) => u.includes('three-')));

    // 4. Vista 2D: un tocco apre il pannello con la macchina
    await page.click('button:has-text("2D")');
    const first = page.locator('[data-plant-asset]').first();
    const label = await first.getAttribute('aria-label');
    await first.click();
    const code = label.split(' ')[0];
    check('Vista 2D: la macchina scelta apre il pannello', (await page.locator('aside[x-ref="panel"]').innerText()).includes(code), code);
    check('La scelta 2D resta alla visita successiva', await page.evaluate(() => localStorage.getItem('mt-plant-mode') === '2d'));
    await page.evaluate(() => localStorage.removeItem('mt-plant-mode'));

    // 5. Editor: trascinamento salvato
    const editHref = await page.locator('a:has-text("Modifica pianta")').first().getAttribute('href');
    await page.goto(new URL(editHref, base).href, { waitUntil: 'networkidle' });
    const item = page.locator('[data-plant-asset]').first();
    const before = { x: Number(await item.getAttribute('data-x')), z: Number(await item.getAttribute('data-z')) };
    const box = await item.boundingBox();
    const svgBox = await page.locator('.plant-svg').boundingBox();
    const perMeter = svgBox.width / (await page.locator('.plant-svg').evaluate((s) => s.viewBox.baseVal.width));
    await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
    await page.mouse.down();
    await page.mouse.move(box.x + box.width / 2 + perMeter * 2, box.y + box.height / 2 + perMeter, { steps: 8 });
    await page.mouse.up();
    await page.waitForTimeout(1200);
    await page.reload({ waitUntil: 'networkidle' });
    const moved = page.locator(`[data-item="${await item.getAttribute('data-item')}"]`);
    const after = { x: Number(await moved.getAttribute('data-x')), z: Number(await moved.getAttribute('data-z')) };
    check('Editor: la macchina trascinata resta nella nuova posizione', after.x !== before.x || after.z !== before.z, `${before.x},${before.z} → ${after.x},${after.z}`);
    // e con la tastiera
    await moved.focus();
    await page.keyboard.press('Shift+ArrowLeft');
    await page.waitForTimeout(900);
    const kb = Number(await page.locator(`[data-item="${await moved.getAttribute('data-item')}"]`).getAttribute('data-x'));
    check('Editor: frecce della tastiera spostano di 1 metro', Math.abs(kb - (after.x - 1)) < 0.01 || after.x - 1 < 0, `${after.x} → ${kb}`);

    // 6. Modello GLB (facoltativo)
    if (process.env.GLB_FILE) {
        await page.goto(new URL(assetHref, base).href, { waitUntil: 'networkidle' });
        await page.setInputFiles('#s-model', process.env.GLB_FILE);
        await Promise.all([page.waitForNavigation(), page.click('button:has-text("Carica un modello 3D")')]);
        await page.click('button:has-text("Mostra in 3D")');
        await page.locator('[x-ref="stage"][data-ready="1"]').first().waitFor({ timeout: 20000 }).catch(() => {});
        check('Modello GLB caricato e mostrato', (await page.locator('[x-ref="stage"][data-ready="1"] canvas').count()) === 1);
    }
    await pc.close();

    // 7. Dispositivo debole: 2D, poi "Prova il 3D"
    const weak = await context({}, () => { Object.defineProperty(navigator, 'deviceMemory', { get: () => 2 }); });
    page = await weak.newPage();
    loaded = watch(page);
    await page.goto(base + '/stabilimento', { waitUntil: 'networkidle' });
    check('Dispositivo debole: parte in 2D senza scaricare Three.js', loaded.length === 0 && (await page.locator('button:has-text("Prova il 3D")').isVisible()));
    await page.click('button:has-text("Prova il 3D")');
    await page.locator('[data-ready="1"]').waitFor({ timeout: 20000 }).catch(() => {});
    check('Dispositivo debole: "Prova il 3D" funziona', (await page.locator('[data-ready="1"] canvas').count()) === 1);
    await weak.close();

    // 8. Senza WebGL: solo 2D
    const nogl = await context({}, () => {
        const orig = HTMLCanvasElement.prototype.getContext;
        HTMLCanvasElement.prototype.getContext = function (type, ...a) { return /webgl/.test(type) ? null : orig.call(this, type, ...a); };
    });
    page = await nogl.newPage();
    loaded = watch(page);
    await page.goto(base + '/stabilimento', { waitUntil: 'networkidle' });
    check('Senza WebGL: solo 2D, nessun pulsante 3D', loaded.length === 0 && !(await page.locator('button:has-text("3D")').first().isVisible()) && (await page.locator('[data-plant-asset]').count()) > 0);
    await nogl.close();

    // 9. "Riduci movimento": la M d'ingresso resta SVG
    const calm = await context({ reducedMotion: 'reduce' });
    page = await calm.newPage();
    loaded = watch(page);
    await page.goto(base + '/', { waitUntil: 'networkidle' });
    check('Riduci movimento: nessun 3D nella panoramica', loaded.length === 0);
    await calm.close();

    check('Nessun errore JavaScript', errors.length === 0, errors.join(' | '));
    await browser.close();
    const ok = results.every(Boolean);
    console.log(ok ? `\nTutto bene (${results.length} controlli).` : '\nCi sono controlli falliti.');
    process.exit(ok ? 0 : 1);
})();
