// x-tilt: leggera inclinazione 3D al passaggio del mouse (solo puntatori fini,
// disattivata con prefers-reduced-motion). Esempio: <div class="card tilt" x-tilt>
const reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
const fine = window.matchMedia('(hover: hover) and (pointer: fine)');

export default (el, { expression }, { cleanup }) => {
    if (reduce.matches || !fine.matches) return;
    const max = Number(expression) || 8;

    const move = (e) => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - 0.5;
        const y = (e.clientY - r.top) / r.height - 0.5;
        el.style.transform = `perspective(800px) rotateX(${(-y * max).toFixed(2)}deg) rotateY(${(x * max * 1.2).toFixed(2)}deg) translateZ(4px)`;
        el.style.boxShadow = 'var(--mt-shadow-pop)';
    };
    const leave = () => {
        el.style.transform = '';
        el.style.boxShadow = '';
    };

    el.addEventListener('pointermove', move);
    el.addEventListener('pointerleave', leave);
    cleanup(() => {
        el.removeEventListener('pointermove', move);
        el.removeEventListener('pointerleave', leave);
    });
};
