// La M di ManuTrack in 3D: le due facce del logo estruse, con un leggero movimento e la
// reazione al mouse o all'inclinazione del telefono. Si carica solo su dispositivi capaci
// e senza "riduci movimento"; altrimenti resta il logo SVG.
import * as THREE from 'three';
import { cssColor, track } from './capability.js';

const FACE_LIGHT = [[0, 0], [203, 202], [203, 339], [90, 227], [86, 377], [0, 292]];
const FACE_DARK = [[406, 0], [203, 202], [203, 339], [316, 227], [320, 377], [406, 292]];

function faceMesh(points, color, depth) {
    // Coordinate SVG (y verso il basso) → Three.js (y verso l'alto), centrate sulla M
    const shape = new THREE.Shape(points.map(([x, y]) => new THREE.Vector2(x - 203, 188.5 - y)));
    const geometry = new THREE.ExtrudeGeometry(shape, { depth, bevelEnabled: true, bevelThickness: 6, bevelSize: 4, bevelSegments: 1 });
    geometry.translate(0, 0, -depth / 2);
    const material = new THREE.MeshStandardMaterial({ color, flatShading: true, roughness: 0.45, metalness: 0.15 });
    return new THREE.Mesh(geometry, material);
}

export function mountLogo(container) {
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.domElement.setAttribute('aria-hidden', 'true');
    renderer.domElement.style.display = 'block';

    const life = track('logo');
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(30, 1, 10, 4000);
    camera.position.set(0, 0, 900);
    scene.add(new THREE.AmbientLight(0xffffff, 1.1));
    const key = new THREE.DirectionalLight(0xffffff, 2.2);
    key.position.set(-300, 400, 600);
    scene.add(key);

    const colors = () => ({ light: cssColor('--logo-faccia-chiara', '#ee9455'), dark: cssColor('--logo-faccia-scura', '#5e2910') });
    let c = colors();
    const light = faceMesh(FACE_LIGHT, c.light, 70);
    const dark = faceMesh(FACE_DARK, c.dark, 70);
    const m = new THREE.Group();
    m.add(light, dark);
    scene.add(m);

    // Inclinazione desiderata (dal mouse o dal giroscopio) e movimento lento a riposo
    const tilt = { x: 0, y: 0 };
    const onPointer = (e) => {
        tilt.y = (e.clientX / window.innerWidth - 0.5) * 0.7;
        tilt.x = (e.clientY / window.innerHeight - 0.5) * 0.5;
    };
    const onOrientation = (e) => {
        if (e.beta == null || e.gamma == null) return;
        tilt.y = THREE.MathUtils.clamp(e.gamma / 45, -1, 1) * 0.5;
        tilt.x = THREE.MathUtils.clamp((e.beta - 45) / 45, -1, 1) * 0.35;
    };
    window.addEventListener('pointermove', onPointer, { passive: true });
    window.addEventListener('deviceorientation', onOrientation, { passive: true });

    let frame = 0;
    let visible = true;
    const t0 = performance.now();
    const loop = () => {
        const t = (performance.now() - t0) / 1000;
        m.rotation.y += (tilt.y + Math.sin(t * 0.6) * 0.18 - m.rotation.y) * 0.06;
        m.rotation.x += (tilt.x + Math.sin(t * 0.45) * 0.05 - m.rotation.x) * 0.06;
        m.position.y = Math.sin(t * 0.9) * 6;
        renderer.render(scene, camera);
        life.frame();
        frame = visible && !document.hidden && !disposed ? requestAnimationFrame(loop) : 0;
    };
    let disposed = false;
    const start = () => { if (!frame && !disposed) frame = requestAnimationFrame(loop); };

    const resize = () => {
        const w = container.clientWidth || 1;
        const h = container.clientHeight || 1;
        renderer.setSize(w, h, false);
        renderer.domElement.style.width = '100%';
        renderer.domElement.style.height = '100%';
        camera.aspect = w / h;
        // La M (406 × 377) riempie la vista con un po' di margine per la rotazione
        const fit = 260 / Math.tan(THREE.MathUtils.degToRad(camera.fov / 2));
        camera.position.z = camera.aspect < 1.08 ? fit * (1.08 / camera.aspect) : fit;
        camera.updateProjectionMatrix();
        start();
    };
    const ro = new ResizeObserver(resize);
    ro.observe(container);
    const io = new IntersectionObserver(([e]) => { visible = e.isIntersecting; if (visible) start(); });
    io.observe(container);
    const onVisibility = () => { if (!document.hidden) start(); };
    document.addEventListener('visibilitychange', onVisibility);
    const theme = new MutationObserver(() => { c = colors(); light.material.color.set(c.light); dark.material.color.set(c.dark); });
    theme.observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'style'] });

    container.appendChild(renderer.domElement);
    resize();

    return {
        dispose() {
            if (disposed) return;
            disposed = true;
            cancelAnimationFrame(frame);
            frame = 0;
            ro.disconnect();
            io.disconnect();
            theme.disconnect();
            document.removeEventListener('visibilitychange', onVisibility);
            window.removeEventListener('pointermove', onPointer);
            window.removeEventListener('deviceorientation', onOrientation);
            for (const mesh of [light, dark]) { mesh.geometry.dispose(); mesh.material.dispose(); }
            renderer.renderLists.dispose();
            const memory = { ...renderer.info.memory };
            renderer.dispose();
            renderer.forceContextLoss();
            renderer.domElement.remove();
            life.close(memory);
        },
    };
}
