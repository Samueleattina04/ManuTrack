// Verifica nel browser (Chromium) del ciclo di vita del 3D (pianta, anteprima della macchina, M):
// - il rendering si ferma quando la scheda del browser è nascosta e quando la vista esce dallo schermo,
//   e riparte quando torna visibile;
// - lasciando la pagina (pagehide) geometrie e texture sono liberate (0 in memoria) e il contesto
//   WebGL è perso; tornando indietro dalla cache del browser la vista si riapre;
// - un modello GLB che rimanda a file esterni non fa nessuna richiesta fuori dal sito.
// Legge window.__mt3d (resources/js/three/capability.js).
// Richiede: BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD; una pianta con almeno una macchina in guasto
// (le macchine in guasto pulsano, quindi la vista disegna di continuo); ASSET_ID di una macchina
// senza modello e, facoltativo, EXTERNAL_ASSET_ID di una macchina il cui GLB rimanda a un URL esterno.
// Uso: node scripts/e2e/three-lifecycle-check.cjs
const { chromium } = require('playwright');

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
if (!process.env.LOGIN_EMAIL || !process.env.LOGIN_PASSWORD || !process.env.ASSET_ID) {
    console.error('Imposta LOGIN_EMAIL, LOGIN_PASSWORD e ASSET_ID (ed eventualmente BASE_URL, EXTERNAL_ASSET_ID).');
    process.exit(2);
}
const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? '✔' : '✘'} ${name}${detail ? ' — ' + detail : ''}`); };

const frames = (page) => page.evaluate(() => window.__mt3d.frames);
const framesIn = async (page, ms = 1200) => { const a = await frames(page); await page.waitForTimeout(ms); return (await frames(page)) - a; };
const setHidden = (page, hidden) => page.evaluate((h) => {
    Object.defineProperty(document, 'hidden', { configurable: true, get: () => h });
    Object.defineProperty(document, 'visibilityState', { configurable: true, get: () => (h ? 'hidden' : 'visible') });
    document.dispatchEvent(new Event('visibilitychange'));
}, hidden);
// Lasciare la pagina come fa il browser (anche verso la cache indietro/avanti)
const leave = (page) => page.evaluate(() => {
    window.__canvases = [...document.querySelectorAll('canvas')].map((c) => ({ c, gl: c.getContext('webgl2') || c.getContext('webgl') }));
    window.dispatchEvent(new PageTransitionEvent('pagehide', { persisted: true }));
});
const afterLeave = (page) => page.evaluate(() => ({
    active: window.__mt3d.active,
    leaks: window.__mt3d.leaks,
    canvasesInPage: document.querySelectorAll('canvas').length,
    contextsLost: window.__canvases.every(({ gl }) => !gl || gl.isContextLost()),
}));

(async () => {
    const browser = await chromium.launch();
    const ctx = await browser.newContext({ viewport: { width: 1280, height: 860 } });
    const page = await ctx.newPage();
    const errors = [];
    const outside = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('request', (r) => { if (!r.url().startsWith(base) && !r.url().startsWith('data:') && !r.url().startsWith('blob:')) outside.push(r.url()); });
    await page.goto(base + '/login');
    await page.fill('#f-email', process.env.LOGIN_EMAIL);
    await page.fill('#f-password', process.env.LOGIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

    // ---- Pianta dello stabilimento
    await page.evaluate(() => localStorage.removeItem('mt-plant-mode'));
    await page.goto(base + '/stabilimento', { waitUntil: 'networkidle' });
    await page.locator('[x-ref="stage"][data-ready="1"]').waitFor({ timeout: 20000 });
    const running = await framesIn(page);
    check('Pianta: disegna mentre è visibile (macchine in guasto che pulsano)', running > 10, `${running} fotogrammi in 1,2 s`);
    await setHidden(page, true);
    await page.waitForTimeout(200);
    const hidden = await framesIn(page);
    check('Pianta: si ferma con la scheda del browser nascosta', hidden <= 1, `${hidden} fotogrammi`);
    await setHidden(page, false);
    check('Pianta: riparte quando la scheda torna visibile', (await framesIn(page)) > 10);
    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
    await page.evaluate(() => { const s = document.querySelector('[x-ref="stage"]'); s.closest('section').style.marginBottom = '3000px'; window.scrollTo(0, document.body.scrollHeight); });
    await page.waitForTimeout(400);
    const offscreen = await framesIn(page);
    check('Pianta: si ferma quando la vista è fuori dallo schermo', offscreen <= 1, `${offscreen} fotogrammi`);
    await page.evaluate(() => window.scrollTo(0, 0));
    check('Pianta: riparte quando torna sullo schermo', (await framesIn(page)) > 10);
    await leave(page);
    let st = await afterLeave(page);
    check('Pianta: lasciando la pagina, geometrie e texture liberate e contesto WebGL perso', st.active === 0 && st.leaks.length === 0 && st.canvasesInPage === 0 && st.contextsLost, JSON.stringify(st));
    await page.evaluate(() => window.dispatchEvent(new PageTransitionEvent('pageshow', { persisted: true })));
    await page.locator('[x-ref="stage"][data-ready="1"]').waitFor({ timeout: 20000 }).catch(() => {});
    check('Pianta: tornando indietro la vista 3D si riapre', (await page.evaluate(() => window.__mt3d.active)) === 1);
    await page.click('button:has-text("2D")');
    st = await page.evaluate(() => ({ active: window.__mt3d.active, leaks: window.__mt3d.leaks }));
    check('Pianta: passando al 2D il 3D viene liberato', st.active === 0 && st.leaks.length === 0, JSON.stringify(st));
    await page.evaluate(() => localStorage.removeItem('mt-plant-mode'));

    // ---- Anteprima della macchina
    await page.goto(base + '/macchine/' + process.env.ASSET_ID, { waitUntil: 'networkidle' });
    await page.click('button:has-text("Mostra in 3D")');
    await page.locator('#s-3d ~ * [data-ready="1"], [x-ref="stage"][data-ready="1"]').first().waitFor({ timeout: 20000 });
    await page.evaluate(() => document.getElementById('s-3d').scrollIntoView({ block: 'start' }));
    check('Anteprima: disegna mentre ruota', (await framesIn(page)) > 10);
    await setHidden(page, true);
    await page.waitForTimeout(200);
    check('Anteprima: si ferma con la scheda nascosta', (await framesIn(page)) <= 1);
    await setHidden(page, false);
    check('Anteprima: riparte quando torna visibile', (await framesIn(page)) > 10);
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.evaluate(() => { document.body.style.paddingTop = '4000px'; });
    await page.waitForTimeout(400);
    check('Anteprima: si ferma fuori dallo schermo', (await framesIn(page)) <= 1);
    await page.evaluate(() => { document.body.style.paddingTop = ''; });
    await leave(page);
    st = await afterLeave(page);
    check('Anteprima: lasciando la pagina, risorse liberate e contesto perso', st.active === 0 && st.leaks.length === 0 && st.canvasesInPage === 0 && st.contextsLost, JSON.stringify(st));

    // ---- M d'ingresso (panoramica)
    await page.goto(base + '/', { waitUntil: 'networkidle' });
    await page.waitForFunction(() => window.__mt3d && window.__mt3d.active === 1, null, { timeout: 20000 });
    check('M 3D: disegna mentre è visibile', (await framesIn(page)) > 10);
    await setHidden(page, true);
    await page.waitForTimeout(200);
    check('M 3D: si ferma con la scheda nascosta', (await framesIn(page)) <= 1);
    await setHidden(page, false);
    await page.evaluate(() => { document.body.style.paddingTop = '4000px'; window.scrollTo(0, 0); });
    await page.waitForTimeout(400);
    check('M 3D: si ferma fuori dallo schermo', (await framesIn(page)) <= 1);
    await page.evaluate(() => { document.body.style.paddingTop = ''; });
    await leave(page);
    st = await afterLeave(page);
    check('M 3D: lasciando la pagina, risorse liberate e contesto perso', st.active === 0 && st.leaks.length === 0 && st.contextsLost, JSON.stringify(st));

    // ---- GLB con un riferimento esterno
    if (process.env.EXTERNAL_ASSET_ID) {
        outside.length = 0;
        await page.goto(base + '/macchine/' + process.env.EXTERNAL_ASSET_ID, { waitUntil: 'networkidle' });
        await page.click('button:has-text("Mostra in 3D")');
        await page.locator('[role="alert"]:has-text("non si è potuto mostrare")').waitFor({ timeout: 20000 }).catch(() => {});
        await page.waitForTimeout(800);
        const blocked = await page.evaluate(() => window.__mt3d.blocked || []);
        const shown = await page.locator('[role="alert"]:has-text("non si è potuto mostrare")').isVisible();
        check('GLB con URL esterno: nessuna richiesta fuori dal sito', outside.length === 0, outside.join(', '));
        check('GLB con URL esterno: riferimento bloccato e messaggio, senza 3D aperto', blocked.length > 0 && shown && (await page.evaluate(() => window.__mt3d.active)) === 0, blocked.join(', '));
    }

    check('Nessun errore JavaScript', errors.length === 0, errors.join(' | '));
    await browser.close();
    const ok = results.every(Boolean);
    console.log(ok ? `\nTutto bene (${results.length} controlli).` : '\nCi sono controlli falliti.');
    process.exit(ok ? 0 : 1);
})();
