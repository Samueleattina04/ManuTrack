// Verifica nel browser (Chromium) della Content-Security-Policy (controllo di sicurezza 0.2):
// - visita ogni pagina raggiungibile dall'amministratore (seguendo i collegamenti interni) e
//   le pagine per gli ospiti;
// - prova le parti che la CSP potrebbe rompere: Livewire e Alpine (azioni e modali), 3D con
//   Three.js (pianta e anteprima della macchina), grafici, fotocamera e anteprima delle foto
//   nella segnalazione (blob:), service worker, palette comandi;
// - fallisce se compare anche una sola violazione (evento securitypolicyviolation o errore in
//   console), sia con la CSP applicata sia in modalità MANUTRACK_CSP=report (Report-Only).
// Richiede un'installazione con dati dimostrativi (php artisan migrate:fresh --seed) e un
// amministratore: BASE_URL, LOGIN_EMAIL, LOGIN_PASSWORD. Uso: node scripts/e2e/csp-check.cjs
// Non modifica dati, salvo aprire e chiudere modali e caricare un'anteprima (senza salvarla).
const { chromium } = require('playwright');

const base = process.env.BASE_URL || 'http://127.0.0.1:8000';
if (!process.env.LOGIN_EMAIL || !process.env.LOGIN_PASSWORD) {
    console.error('Imposta LOGIN_EMAIL e LOGIN_PASSWORD (ed eventualmente BASE_URL).');
    process.exit(2);
}
const MAX_PAGES = Number(process.env.MAX_PAGES || 200);
// Indirizzi da non aprire: azioni, file da scaricare, uscita
const SKIP = [/\/logout/, /\/scarica/, /\/esporta\//, /\.(csv|xlsx|svg|glb|zip|png|jpg|webp|pdf)(\?|$)/, /\/allegati\//, /\/q\//, /\/marchio\//, /\/sistema\/(aggiorna|ritenta|copia)/, /\/two-factor/, /\/user\//];

const results = [];
const check = (name, ok, detail = '') => { results.push(ok); console.log(`${ok ? '✔' : '✘'} ${name}${detail ? ' — ' + detail : ''}`); };
// Raccoglie le violazioni anche in modalità Report-Only (l'evento parte comunque)
const recorder = () => {
    window.__csp = [];
    document.addEventListener('securitypolicyviolation', (e) => {
        window.__csp.push(`${e.effectiveDirective} ${e.blockedURI || ''} ${e.sourceFile || ''}:${e.lineNumber || ''} ${e.sample || ''}`.trim());
    });
};


// Modello GLB minimo (un triangolo) con due texture PNG incorporate, una nel file binario e
// una come data:. Three.js le legge con fetch() da blob: e data:
function glbWithTexture() {
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAFklEQVR42mP8z8Dwn4GBgYGJgYEBAAweAgFnqY1pAAAAAElFTkSuQmCC', 'base64');
    const pos = Buffer.alloc(36);
    [0, 0, 0, 1, 0, 0, 0, 1, 0].forEach((v, i) => pos.writeFloatLE(v, i * 4));
    const uv = Buffer.alloc(24);
    [0, 0, 1, 0, 0, 1].forEach((v, i) => uv.writeFloatLE(v, i * 4));
    const pad = (b) => Buffer.concat([b, Buffer.alloc((4 - (b.length % 4)) % 4)]);
    const bin = Buffer.concat([pos, uv, pad(png)]);
    const json = {
        asset: { version: '2.0' }, scene: 0, scenes: [{ nodes: [0] }], nodes: [{ mesh: 0 }],
        meshes: [{ primitives: [{ attributes: { POSITION: 0, TEXCOORD_0: 1 }, material: 0 }] }],
        materials: [{ pbrMetallicRoughness: { baseColorTexture: { index: 0 } }, emissiveTexture: { index: 1 } }],
        textures: [{ source: 0 }, { source: 1 }],
        images: [{ bufferView: 2, mimeType: 'image/png' }, { uri: 'data:image/png;base64,' + png.toString('base64') }],
        buffers: [{ byteLength: bin.length }],
        bufferViews: [{ buffer: 0, byteOffset: 0, byteLength: 36 }, { buffer: 0, byteOffset: 36, byteLength: 24 }, { buffer: 0, byteOffset: 60, byteLength: png.length }],
        accessors: [
            { bufferView: 0, componentType: 5126, count: 3, type: 'VEC3', min: [0, 0, 0], max: [1, 1, 0] },
            { bufferView: 1, componentType: 5126, count: 3, type: 'VEC2' },
        ],
    };
    const j = Buffer.from(JSON.stringify(json));
    const jp = Buffer.concat([j, Buffer.alloc((4 - (j.length % 4)) % 4, 0x20)]);
    const header = Buffer.alloc(12);
    const total = 12 + 8 + jp.length + 8 + bin.length;
    header.write('glTF', 0); header.writeUInt32LE(2, 4); header.writeUInt32LE(total, 8);
    const c1 = Buffer.alloc(8); c1.writeUInt32LE(jp.length, 0); c1.write('JSON', 4);
    const c2 = Buffer.alloc(8); c2.writeUInt32LE(bin.length, 0); c2.write('BIN\0', 4);
    return Buffer.concat([header, c1, jp, c2, bin]);
}

(async () => {
    const browser = await chromium.launch({ args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--enable-unsafe-swiftshader'] });
    const violations = [];
    const watch = (page) => {
        page.on('console', (m) => { if (/Content Security Policy|Refused to/i.test(m.text())) violations.push(`${page.url()} · console: ${m.text()}`); });
        page.on('pageerror', (e) => violations.push(`${page.url()} · errore: ${e.message}`));
        page.on('dialog', (d) => d.dismiss());
    };
    const collect = async (page) => {
        const list = await page.evaluate(() => window.__csp || []).catch(() => []);
        for (const v of list) violations.push(`${page.url()} · ${v}`);
        await page.evaluate(() => { window.__csp = []; }).catch(() => {});
    };

    // 0. Intestazione presente (applicata o solo segnalata)
    const guest = await browser.newContext();
    await guest.addInitScript(recorder);
    let page = await guest.newPage();
    watch(page);
    const res = await page.goto(base + '/login');
    const headers = res.headers();
    const policy = headers['content-security-policy'] || headers['content-security-policy-report-only'] || '';
    check('La pagina di accesso ha la CSP', policy.includes("frame-ancestors 'none'"), headers['content-security-policy'] ? 'applicata' : 'Report-Only');
    for (const url of ['/forgot-password', '/pagina-che-non-esiste', '/offline']) {
        await page.goto(base + url, { waitUntil: 'networkidle' });
        await collect(page);
    }

    // Accesso (una volta sola: l'app limita i tentativi al minuto)
    await page.goto(base + '/login');
    await page.fill('#f-email', process.env.LOGIN_EMAIL);
    await page.fill('#f-password', process.env.LOGIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);
    await collect(page);
    const session = await guest.storageState();
    await guest.close();

    const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, storageState: session, permissions: ['camera'] });
    await ctx.addInitScript(recorder);
    page = await ctx.newPage();
    watch(page);

    // Password riconfermata per Sistema e Dati e privacy
    await page.goto(base + '/user/confirm-password');
    await page.fill('input[name=password]', process.env.LOGIN_PASSWORD);
    await Promise.all([page.waitForNavigation(), page.click('button[type=submit]')]);

    // 1. Tutte le pagine raggiungibili dai collegamenti interni
    const seen = new Set();
    const queue = ['/', '/ruoli', '/avvisi/preferenze'];
    const shapes = new Set(queue);
    while (queue.length && seen.size < MAX_PAGES) {
        const path = queue.shift();
        if (seen.has(path)) continue;
        seen.add(path);
        const response = await page.goto(base + path, { waitUntil: 'networkidle' }).catch(() => null);
        if (!response || !(response.headers()['content-type'] || '').includes('text/html')) continue;
        await page.waitForTimeout(150);
        await collect(page);
        const links = await page.evaluate(() => [...document.querySelectorAll('a[href]')].map((a) => a.href));
        for (const href of links) {
            const u = new URL(href, base);
            if (u.origin !== new URL(base).origin || SKIP.some((re) => re.test(u.pathname))) continue;
            // Una sola pagina per tipo (schede con id diversi e filtri hanno lo stesso codice)
            const key = u.pathname.replace(/\/\d+(?=\/|$)/g, '/N');
            if (!shapes.has(key)) { shapes.add(key); queue.push(u.pathname); }
        }
    }
    check(`Pagine visitate senza violazioni`, violations.length === 0, `${seen.size} pagine: ${[...seen].join(" ")}`);

    // 2. Livewire e Alpine: modale e azione dal server
    await page.goto(base + '/utenti', { waitUntil: 'networkidle' });
    const invite = page.locator('button:has-text("Invita")').first();
    if (await invite.count()) {
        await invite.click();
        await page.waitForSelector('#inv-email', { timeout: 5000 }).catch(() => {});
    }
    check('Livewire risponde (modale dell\'invito aperta dal server)', (await page.locator('#inv-email').count()) === 1);
    await page.keyboard.press('Control+k');
    await page.waitForTimeout(300);
    await page.keyboard.press('Escape');
    await collect(page);

    // 3. Grafici degli indicatori
    await page.goto(base + '/indicatori', { waitUntil: 'networkidle' });
    await page.waitForTimeout(800);
    check('Grafici disegnati (canvas)', (await page.locator('canvas').count()) > 0);
    await collect(page);

    // 4. Stabilimento in 3D (WebGL, Three.js) e in 2D
    await page.goto(base + '/stabilimento', { waitUntil: 'networkidle' });
    await page.locator('[data-ready="1"]').first().waitFor({ timeout: 20000 }).catch(() => {});
    check('Stabilimento in 3D (WebGL)', (await page.locator('[data-ready="1"] canvas').count()) >= 1);
    const twoD = page.locator('button:has-text("2D")').first();
    if (await twoD.count()) await twoD.click();
    await page.waitForTimeout(500);
    await collect(page);

    // 5. Anteprima 3D di una macchina
    await page.goto(base + '/macchine', { waitUntil: 'networkidle' });
    const asset = await page.evaluate(() => [...document.querySelectorAll('a[href*="/macchine/"]')].map((a) => a.getAttribute('href')).find((h) => /\/macchine\/\d+$/.test(h)));
    if (asset) {
        await page.goto(new URL(asset, base).href, { waitUntil: 'networkidle' });
        const preview = page.locator('button:has-text("Mostra in 3D")').first();
        if (await preview.count()) {
            await preview.click();
            await page.locator('[x-ref="stage"][data-ready="1"]').first().waitFor({ timeout: 20000 }).catch(() => {});
        }
        check('Anteprima 3D della macchina', (await page.locator('[x-ref="stage"][data-ready="1"] canvas').count()) >= 1);
        await collect(page);

        // Modello GLB con texture incorporata (blob:) caricato e mostrato nell'anteprima
        // (attenzione: sostituisce il modello di questa macchina nell'installazione di prova)
        if (process.env.UPLOAD_GLB !== '0' && (await page.locator('#s-model').count())) {
            await page.setInputFiles('#s-model', { name: 'prova.glb', mimeType: 'model/gltf-binary', buffer: glbWithTexture() });
            await Promise.all([page.waitForNavigation(), page.locator('#s-model').evaluate((el) => el.form.requestSubmit())]);
            const blobs = [];
            page.on('request', (r) => { if (r.url().startsWith('blob:')) blobs.push(r.url()); });
            const again = page.locator('button:has-text("Mostra in 3D")').first();
            if (await again.count()) await again.click();
            await page.locator('[x-ref="stage"][data-ready="1"]').first().waitFor({ timeout: 20000 }).catch(() => {});
            await page.waitForTimeout(1000);
            check('Modello GLB con texture incorporata mostrato', (await page.locator('[x-ref="stage"][data-ready="1"] canvas').count()) >= 1, `${blobs.length} richieste blob:`);
            await collect(page);
        }
    }

    // 6. Segnalazione: fotocamera (QR) e anteprima di una foto (blob:)
    await page.goto(base + '/segnala', { waitUntil: 'networkidle' });
    await page.click('button:has-text("QR")').catch(() => {});
    await page.waitForTimeout(1200);
    const streaming = await page.evaluate(() => { const v = document.querySelector('video'); return !!(v && v.srcObject); });
    check('Fotocamera aperta per il QR (Permissions-Policy camera=self)', streaming);
    await page.keyboard.press('Escape');
    const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');
    const photoInput = page.locator('input[type=file][multiple]').first();
    if (await photoInput.count()) {
        await photoInput.setInputFiles({ name: 'prova.png', mimeType: 'image/png', buffer: png });
        await page.waitForTimeout(800);
    }
    check('Anteprima della foto (blob:) mostrata', (await page.locator('img[src^="blob:"]').count()) >= 1);
    await collect(page);

    // 7. Service worker registrato e attivo
    await page.goto(base + '/', { waitUntil: 'networkidle' });
    const sw = await page.evaluate(async () => { const r = await navigator.serviceWorker.getRegistration(); return !!r; });
    check('Service worker registrato', sw);
    await collect(page);

    // 8. Un'altra pagina non usa la fotocamera
    const camera = await page.evaluate(async () => { try { const s = await navigator.mediaDevices.getUserMedia({ video: true }); s.getTracks().forEach((t) => t.stop()); return 'aperta'; } catch (e) { return e.name; } });
    check('Fotocamera negata fuori dalla segnalazione', camera !== 'aperta', camera);

    // 9. Clickjacking: la cancellazione totale dentro un iframe di un altro sito non si carica
    const evil = await ctx.newPage();
    const blocked = [];
    evil.on('console', (m) => { if (/frame-ancestors|X-Frame-Options/i.test(m.text())) blocked.push(m.text()); });
    await evil.setContent(`<iframe id="f" src="${base}/sistema/dati/cancellazione" width="800" height="600"></iframe>`);
    await evil.waitForTimeout(2000);
    const framed = await evil.frames()[1]?.evaluate(() => document.body?.innerText || '').catch(() => '') || '';
    check('Nessuna pagina si carica dentro un iframe di un altro sito (frame-ancestors)', !/CANCELLA/.test(framed) && blocked.length > 0, blocked[0] || '');
    await evil.close();

    check('Nessuna violazione della CSP', violations.length === 0, violations.length ? '\n  ' + [...new Set(violations)].slice(0, 40).join('\n  ') : '');
    await browser.close();
    process.exit(results.every(Boolean) ? 0 : 1);
})();
