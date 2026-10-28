// Vista 3D dello stabilimento (gemello digitale semplificato). Si carica solo dalla pagina
// Stabilimento, e solo se il dispositivo regge il 3D (vedi capability.js).
// Disegna su richiesta (quando qualcosa cambia), non in continuo: niente batteria sprecata.
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { buildShape, shapeMaterials, setMaterialsColor } from './shapes.js';
import { cssColor, reducedMotion } from './capability.js';

const TONES = { ok: '--mt-ok', ko: '--mt-ko', mnt: '--mt-mnt', stop: '--mt-stop' };

function themeColors() {
    const tones = {};
    for (const [tone, v] of Object.entries(TONES)) tones[tone] = cssColor(v);
    return {
        tones,
        floor: cssColor('--mt-surface-2', '#20262f'),
        grid: cssColor('--mt-line-soft', '#3a414c'),
        gridStrong: cssColor('--mt-line', '#4a525e'),
        zone: cssColor('--mt-accent-soft', '#4b6b3a'),
        zoneLine: cssColor('--mt-accent-fg', '#9fd06a'),
        text: cssColor('--mt-fg', '#f2f3f5'),
        accent: cssColor('--mt-accent-fg', '#9fd06a'),
    };
}

// Etichetta di testo come texture (codice macchina, nome zona)
function labelTexture(text, color, { size = 64, bg = null } = {}) {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const font = `600 ${size}px "IBM Plex Sans", system-ui, sans-serif`;
    ctx.font = font;
    const pad = size * 0.35;
    canvas.width = Math.ceil(ctx.measureText(text).width + pad * 2);
    canvas.height = Math.ceil(size * 1.5);
    ctx.font = font;
    if (bg) {
        ctx.fillStyle = bg;
        ctx.beginPath();
        ctx.roundRect(0, 0, canvas.width, canvas.height, size * 0.3);
        ctx.fill();
    }
    ctx.fillStyle = color;
    ctx.textBaseline = 'middle';
    ctx.fillText(text, pad, canvas.height / 2);
    const texture = new THREE.CanvasTexture(canvas);
    texture.colorSpace = THREE.SRGBColorSpace;
    texture.anisotropy = 4;
    return { texture, aspect: canvas.width / canvas.height };
}

export function mountPlant(container, data, { onSelect = () => {} } = {}) {
    const W = data.layout.width;
    const D = data.layout.depth;
    const reduce = reducedMotion();
    let colors = themeColors();

    const renderer = new THREE.WebGLRenderer({ antialias: window.devicePixelRatio < 2, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.domElement.setAttribute('aria-hidden', 'true');
    renderer.domElement.style.touchAction = 'none';
    renderer.domElement.style.display = 'block';
    container.appendChild(renderer.domElement);

    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, 1, 0.1, 5000);
    const size = Math.max(W, D);
    const target = new THREE.Vector3(W / 2, 0, D / 2);
    const viewDir = new THREE.Vector3(0.42, 0.78, 0.9).normalize();
    // Inquadra tutto il pavimento (sfera che lo contiene) con l'altezza e la larghezza della vista
    const fit = () => {
        const radius = Math.hypot(W, D) / 2;
        const vfov = THREE.MathUtils.degToRad(camera.fov);
        const hfov = 2 * Math.atan(Math.tan(vfov / 2) * camera.aspect);
        const dist = (radius / Math.sin(Math.min(vfov, hfov) / 2)) * 0.82;
        camera.position.copy(target).addScaledVector(viewDir, dist);
        camera.lookAt(target);
    };

    scene.add(new THREE.HemisphereLight(0xffffff, 0x445566, 1.4));
    const sun = new THREE.DirectionalLight(0xffffff, 1.6);
    sun.position.set(W * 0.2, size * 1.5, D * 0.1);
    scene.add(sun);

    // Pavimento con griglia: linee ogni metro, più marcate ogni 5
    const floorMat = new THREE.MeshStandardMaterial({ color: colors.floor, roughness: 0.95 });
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(W, D), floorMat);
    floor.rotation.x = -Math.PI / 2;
    floor.position.set(W / 2, 0, D / 2);
    scene.add(floor);
    const gridMat = new THREE.LineBasicMaterial({ color: colors.grid, transparent: true, opacity: 0.55 });
    const gridStrongMat = new THREE.LineBasicMaterial({ color: colors.gridStrong, transparent: true, opacity: 0.8 });
    const gridStep = size > 120 ? 5 : 1;
    const minor = [];
    const major = [];
    for (let x = 0; x <= W + 1e-6; x += gridStep) (Math.round(x) % 5 === 0 ? major : minor).push(x, 0.002, 0, x, 0.002, D);
    for (let z = 0; z <= D + 1e-6; z += gridStep) (Math.round(z) % 5 === 0 ? major : minor).push(0, 0.002, z, W, 0.002, z);
    const lines = (arr, mat) => {
        const g = new THREE.BufferGeometry();
        g.setAttribute('position', new THREE.Float32BufferAttribute(arr, 3));
        return new THREE.LineSegments(g, mat);
    };
    scene.add(lines(minor, gridMat), lines(major, gridStrongMat));

    // Zone sul pavimento
    const zoneMat = new THREE.MeshBasicMaterial({ color: colors.zone, transparent: true, opacity: 0.35, depthWrite: false });
    const zoneLineMat = new THREE.LineBasicMaterial({ color: colors.zoneLine });
    for (const z of data.zones) {
        const group = new THREE.Group();
        group.position.set(z.x, 0.005, z.z);
        group.rotation.y = -THREE.MathUtils.degToRad(z.rotation);
        const plane = new THREE.Mesh(new THREE.PlaneGeometry(z.w, z.d), zoneMat);
        plane.rotation.x = -Math.PI / 2;
        group.add(plane);
        const edges = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.PlaneGeometry(z.w, z.d)), zoneLineMat);
        edges.rotation.x = -Math.PI / 2;
        group.add(edges);
        const { texture, aspect } = labelTexture(z.label, colors.zoneLine);
        const h = Math.min(0.9, z.d * 0.25);
        const label = new THREE.Mesh(new THREE.PlaneGeometry(Math.min(h * aspect, z.w * 0.95), h), new THREE.MeshBasicMaterial({ map: texture, transparent: true, depthWrite: false }));
        label.rotation.x = -Math.PI / 2;
        label.position.set(-z.w / 2 + Math.min(h * aspect, z.w * 0.95) / 2 + 0.1, 0.01, z.d / 2 - h / 2 - 0.1);
        group.add(label);
        scene.add(group);
    }

    // Macchine colorate per stato, con il codice sopra
    const items = new Map();
    const pickables = [];
    for (const item of data.items) {
        const materials = shapeMaterials(colors.tones[item.tone] || colors.tones.stop);
        const group = buildShape(item.shape, item.w, item.d, item.h, materials);
        group.position.set(item.x, 0, item.z);
        group.rotation.y = -THREE.MathUtils.degToRad(item.rotation);
        group.userData.id = item.id;
        group.traverse((o) => { if (o.isMesh) { o.userData.id = item.id; pickables.push(o); } });
        const { texture, aspect } = labelTexture(item.code, colors.text, { bg: 'rgba(15,18,24,0.72)' });
        const sprite = new THREE.Sprite(new THREE.SpriteMaterial({ map: texture, depthTest: false, transparent: true }));
        const lh = Math.max(0.45, Math.min(0.8, size / 60));
        sprite.scale.set(lh * aspect, lh, 1);
        sprite.position.set(item.x, item.h + lh * 0.9, item.z);
        sprite.renderOrder = 10;
        scene.add(group, sprite);
        items.set(item.id, { item, group, materials, sprite });
    }

    // Anello sotto la macchina scelta
    const ring = new THREE.Mesh(new THREE.RingGeometry(0.5, 0.62, 48), new THREE.MeshBasicMaterial({ color: colors.accent, transparent: true, opacity: 0.95, depthWrite: false }));
    ring.rotation.x = -Math.PI / 2;
    ring.visible = false;
    scene.add(ring);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.target.copy(target);
    controls.enableDamping = !reduce;
    controls.dampingFactor = 0.12;
    controls.maxPolarAngle = Math.PI * 0.46;
    controls.minDistance = 3;
    controls.maxDistance = size * 3.2;
    controls.screenSpacePanning = false;
    controls.update();

    // Disegno su richiesta
    let frame = 0;
    let visible = true;
    const render = () => {
        frame = 0;
        const moving = controls.update();
        const pulsing = animatePulse();
        renderer.render(scene, camera);
        if ((moving || pulsing) && visible && !document.hidden) request();
    };
    const request = () => { if (!frame) frame = requestAnimationFrame(render); };
    controls.addEventListener('change', request);

    // Le macchine in guasto pulsano (non con "riduci movimento")
    function animatePulse() {
        if (reduce) return false;
        let any = false;
        const t = performance.now() / 1000;
        for (const { item, materials } of items.values()) {
            const on = item.tone === 'ko';
            materials.body.emissive.set(on ? colors.tones.ko : 0x000000);
            materials.body.emissiveIntensity = on ? 0.25 + 0.25 * Math.sin(t * 4) : 0;
            any ||= on;
        }
        return any;
    }

    let fitted = false;
    const resize = () => {
        const w = container.clientWidth || 1;
        const h = container.clientHeight || 1;
        renderer.setSize(w, h, false);
        renderer.domElement.style.width = '100%';
        renderer.domElement.style.height = '100%';
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        if (!fitted) { fit(); fitted = true; controls.update(); }
        request();
    };
    const resizeObserver = new ResizeObserver(resize);
    resizeObserver.observe(container);
    const io = new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; if (visible) request(); });
    io.observe(container);
    const onVisibility = () => { if (!document.hidden) request(); };
    document.addEventListener('visibilitychange', onVisibility);

    // Scelta con un tocco o un clic (non se si sta ruotando la vista)
    const raycaster = new THREE.Raycaster();
    const pointer = new THREE.Vector2();
    let down = null;
    const pick = (e) => {
        const r = renderer.domElement.getBoundingClientRect();
        pointer.set(((e.clientX - r.left) / r.width) * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
        raycaster.setFromCamera(pointer, camera);
        const hit = raycaster.intersectObjects(pickables, false)[0];
        return hit ? hit.object.userData.id : null;
    };
    const onDown = (e) => { down = { x: e.clientX, y: e.clientY }; };
    const onUp = (e) => {
        if (!down || Math.hypot(e.clientX - down.x, e.clientY - down.y) > 6) return;
        down = null;
        onSelect(pick(e));
    };
    const onMove = (e) => {
        if (e.pointerType !== 'mouse' || e.buttons) return;
        renderer.domElement.style.cursor = pick(e) ? 'pointer' : 'grab';
    };
    renderer.domElement.addEventListener('pointerdown', onDown);
    renderer.domElement.addEventListener('pointerup', onUp);
    renderer.domElement.addEventListener('pointermove', onMove);

    const theme = new MutationObserver(() => {
        colors = themeColors();
        floorMat.color.set(colors.floor);
        gridMat.color.set(colors.grid);
        gridStrongMat.color.set(colors.gridStrong);
        zoneMat.color.set(colors.zone);
        zoneLineMat.color.set(colors.zoneLine);
        ring.material.color.set(colors.accent);
        for (const { item, materials } of items.values()) setMaterialsColor(materials, colors.tones[item.tone] || colors.tones.stop);
        request();
    });
    theme.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme'] });

    resize();
    container.dataset.ready = '1';

    return {
        select(id) {
            const entry = id ? items.get(id) : null;
            ring.visible = !!entry;
            if (entry) {
                const r = Math.max(entry.item.w, entry.item.d) * 0.62;
                ring.scale.set(r / 0.56, r / 0.56, 1);
                ring.position.set(entry.item.x, 0.01, entry.item.z);
            }
            request();
        },
        /** Stati aggiornati: { asset_id: { tone } } */
        setStatuses(statuses) {
            for (const entry of items.values()) {
                const s = statuses[entry.item.asset_id];
                if (s && s.tone !== entry.item.tone) {
                    entry.item.tone = s.tone;
                    setMaterialsColor(entry.materials, colors.tones[s.tone] || colors.tones.stop);
                    entry.materials.body.emissiveIntensity = 0;
                }
            }
            request();
        },
        dispose() {
            cancelAnimationFrame(frame);
            resizeObserver.disconnect();
            io.disconnect();
            theme.disconnect();
            document.removeEventListener('visibilitychange', onVisibility);
            controls.dispose();
            scene.traverse((o) => {
                o.geometry?.dispose();
                const mats = Array.isArray(o.material) ? o.material : o.material ? [o.material] : [];
                for (const m of mats) { m.map?.dispose(); m.dispose(); }
            });
            renderer.dispose();
            renderer.forceContextLoss();
            renderer.domElement.remove();
            delete container.dataset.ready;
        },
    };
}
