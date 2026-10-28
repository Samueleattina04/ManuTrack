// Editor della pianta: trascinamento (mouse, dito, penna) e tastiera sugli elementi dell'SVG.
// Le coordinate si calcolano in metri dall'SVG; il server arrotonda alla griglia e tiene
// tutto dentro il pavimento.
const STEP = 0.25;

export default () => ({
    drag: null,

    point(e) {
        const svg = this.$refs.svg;
        const pt = svg.createSVGPoint();
        pt.x = e.clientX;
        pt.y = e.clientY;
        return pt.matrixTransform(svg.getScreenCTM().inverse());
    },

    start(e) {
        if (e.button !== undefined && e.button !== 0) return;
        const el = e.currentTarget;
        const p = this.point(e);
        this.drag = { el, id: Number(el.dataset.item), x0: Number(el.dataset.x), z0: Number(el.dataset.z), px: p.x, pz: p.y, moved: false, rot: el.getAttribute('transform').match(/rotate\(([-\d.]+)\)/)?.[1] || '0' };
        el.setPointerCapture?.(e.pointerId);
        el.addEventListener('pointermove', this.moveHandler ??= (ev) => this.move(ev));
        el.addEventListener('pointerup', this.endHandler ??= (ev) => this.end(ev), { once: true });
        el.addEventListener('pointercancel', this.endHandler, { once: true });
        e.preventDefault();
    },

    move(e) {
        if (!this.drag) return;
        const p = this.point(e);
        const dx = p.x - this.drag.px;
        const dz = p.y - this.drag.pz;
        if (!this.drag.moved && Math.hypot(dx, dz) < 0.15) return;
        this.drag.moved = true;
        this.drag.x = Math.round((this.drag.x0 + dx) / STEP) * STEP;
        this.drag.z = Math.round((this.drag.z0 + dz) / STEP) * STEP;
        this.drag.el.classList.add('is-dragging');
        this.drag.el.setAttribute('transform', `translate(${this.drag.x} ${this.drag.z}) rotate(${this.drag.rot})`);
    },

    end() {
        const d = this.drag;
        this.drag = null;
        if (!d) return;
        d.el.removeEventListener('pointermove', this.moveHandler);
        d.el.classList.remove('is-dragging');
        if (d.moved) {
            this.$wire.move(d.id, d.x, d.z);
        } else {
            this.$wire.select(d.id);
        }
    },

    key(e) {
        const el = e.currentTarget;
        const id = Number(el.dataset.item);
        const step = e.shiftKey ? 1 : STEP;
        const moves = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
        if (moves[e.key]) {
            e.preventDefault();
            this.$wire.move(id, Number(el.dataset.x) + moves[e.key][0], Number(el.dataset.z) + moves[e.key][1])
                .then(() => this.$nextTick(() => this.$refs.svg.querySelector(`[data-item="${id}"]`)?.focus()));
        } else if (e.key === 'r' || e.key === 'R') {
            e.preventDefault();
            this.$wire.rotate(id, e.shiftKey ? -90 : 90).then(() => this.$nextTick(() => this.$refs.svg.querySelector(`[data-item="${id}"]`)?.focus()));
        } else if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            this.$wire.select(id);
        }
    },
});
