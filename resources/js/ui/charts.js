// Grafici degli indicatori. Chart.js (licenza MIT) si scarica solo quando una pagina
// mostra un grafico: con import() Vite lo mette in un file a parte.
// Ogni grafico ha accanto la tabella con gli stessi dati (accessibilità e stampa).

let chartModule = null;

async function loadChart() {
    if (!chartModule) {
        chartModule = import('chart.js').then((m) => {
            m.Chart.register(
                m.BarController, m.LineController, m.BarElement, m.LineElement, m.PointElement,
                m.CategoryScale, m.LinearScale, m.Tooltip, m.Legend, m.Filler,
            );
            return m.Chart;
        });
    }
    return chartModule;
}

function palette() {
    const css = getComputedStyle(document.documentElement);
    const v = (name, fallback) => css.getPropertyValue(name).trim() || fallback;
    return {
        text: rgba(v('--mt-fg-2', '#5b6475')),
        grid: rgba(v('--mt-line-soft', '#808080'), 0.6),
        accent: v('--mt-accent', '#2f7d32'),
        ok: v('--mt-ok', '#1f9d6b'),
        warn: v('--mt-warn', '#c98a00'),
        ko: v('--mt-ko', '#d23c3c'),
    };
}

// Qualunque colore CSS (anche oklch o color-mix) diventa rgba con la trasparenza voluta
const probe = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
function rgba(color, alpha = 1) {
    if (!probe) return color;
    probe.clearRect(0, 0, 1, 1);
    probe.fillStyle = '#000';
    probe.fillStyle = color;
    probe.fillRect(0, 0, 1, 1);
    const [r, g, b] = probe.getImageData(0, 0, 1, 1).data;
    return `rgba(${r}, ${g}, ${b}, ${alpha})`;
}

const nf = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 1 });

/**
 * x-data="kpiChart({ kind, labels, datasets, unit, horizontal, stacked })"
 * datasets: [{ label, data, color: 'accent'|'ok'|'warn'|'ko', type?: 'bar'|'line' }]
 */
export default (config) => ({
    chart: null,
    observer: null,

    async init() {
        const Chart = await loadChart();
        this.draw(Chart);
        // Cambio di tema chiaro/scuro: si ridisegna con i colori nuovi
        this.observer = new MutationObserver(() => this.draw(Chart));
        this.observer.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });
    },

    destroy() {
        this.observer?.disconnect();
        this.chart?.destroy();
    },

    draw(Chart) {
        const c = palette();
        const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const small = window.innerWidth < 640;
        this.chart?.destroy();
        this.chart = new Chart(this.$refs.canvas, {
            type: config.type || 'bar',
            data: {
                labels: config.labels,
                datasets: config.datasets.map((d) => ({
                    type: d.type || config.type || 'bar',
                    label: d.label,
                    data: d.data,
                    backgroundColor: rgba(c[d.color], d.type === 'line' ? 1 : 0.8),
                    borderColor: rgba(c[d.color]),
                    borderWidth: d.type === 'line' ? 2 : 0,
                    borderRadius: d.type === 'line' ? 0 : 4,
                    pointRadius: d.type === 'line' ? 3 : 0,
                    spanGaps: false, // dove i dati non bastano la linea si interrompe
                    maxBarThickness: 36,
                })),
            },
            options: {
                locale: 'it-IT',
                indexAxis: config.horizontal ? 'y' : 'x',
                responsive: true,
                maintainAspectRatio: false,
                animation: reduce ? false : { duration: 500 },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: config.datasets.length > 1, labels: { color: c.text, boxWidth: 12, font: { size: small ? 11 : 12 } } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const v = config.horizontal ? ctx.parsed.x : ctx.parsed.y;
                                return `${ctx.dataset.label}: ${v === null ? config.empty : nf.format(v)}${config.unit && v !== null ? ' ' + config.unit : ''}`;
                            },
                        },
                    },
                },
                scales: {
                    x: { stacked: !!config.stacked, grid: { color: config.horizontal ? c.grid : 'transparent' }, ticks: { color: c.text, maxTicksLimit: small ? 6 : 12, font: { size: small ? 10 : 12 } } },
                    y: { stacked: !!config.stacked, beginAtZero: true, grid: { color: config.horizontal ? 'transparent' : c.grid }, ticks: { color: c.text, font: { size: small ? 10 : 12 } } },
                },
            },
        });
    },
});
