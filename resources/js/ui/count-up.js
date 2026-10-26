// x-count-up: anima un numero da 0 al valore già scritto nell'elemento.
// Il valore finale è nel markup, quindi senza JavaScript (o con movimento
// ridotto) il numero è comunque corretto.
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');

export default (el, { expression }) => {
    if (reduce.matches) return;
    const target = parseFloat(String(expression || el.textContent).replace(/\./g, '').replace(',', '.'));
    if (!Number.isFinite(target)) return;
    const decimals = (String(expression || el.textContent).split(',')[1] || '').length;
    const fmt = new Intl.NumberFormat('it-IT', { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    const start = performance.now();
    const duration = 900;

    const step = (now) => {
        const k = Math.min(1, (now - start) / duration);
        el.textContent = fmt.format(target * (1 - Math.pow(1 - k, 3)));
        if (k < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
};
