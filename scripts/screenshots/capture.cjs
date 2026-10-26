// Screenshot di controllo a fine fase (PC e telefono) con Playwright.
// Uso: node scripts/screenshots/capture.cjs <url-base> <cartella-output> [pagine...]
// Ogni pagina: "nome=/percorso". Esempio: home=/ sistema=/sistema
const { chromium } = require('playwright');

const base = process.argv[2] || 'http://127.0.0.1:8000';
const out = process.argv[3] || 'screenshots';
const pages = (process.argv.slice(4).length ? process.argv.slice(4) : ['home=/']).map((p) => p.split('='));

const devices = {
    pc: { viewport: { width: 1440, height: 900 }, deviceScaleFactor: 1 },
    telefono: {
        viewport: { width: 390, height: 844 }, deviceScaleFactor: 3, isMobile: true, hasTouch: true,
        userAgent: 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1',
    },
};

(async () => {
    const browser = await chromium.launch();
    const problems = [];
    for (const [device, opts] of Object.entries(devices)) {
        for (const theme of (process.env.THEMES || 'dark').split(',')) {
            const ctx = await browser.newContext({ ...opts, colorScheme: theme === 'light' ? 'light' : 'dark' });
            await ctx.addInitScript((t) => { try { localStorage.setItem('mt-theme', t); } catch (e) {} }, theme);
            const page = await ctx.newPage();
            page.on('console', (m) => m.type() === 'error' && problems.push(`${device} ${m.text()}`));
            page.on('pageerror', (e) => problems.push(`${device} ${e.message}`));
            for (const [name, path] of pages) {
                await page.goto(base + path, { waitUntil: 'networkidle' });
                await page.waitForTimeout(1200);
                const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
                if (overflow > 0) problems.push(`${device} ${name}: scorrimento orizzontale di ${overflow}px`);
                // PC: pagina intera; telefono: la schermata visibile, come la vede chi la usa
                await page.screenshot({ path: `${out}/${name}-${device}-${theme}.png`, fullPage: device === 'pc' });
            }
            await ctx.close();
        }
    }
    await browser.close();
    console.log(problems.length ? 'PROBLEMI:\n' + problems.join('\n') : 'Nessun problema rilevato');
})();
