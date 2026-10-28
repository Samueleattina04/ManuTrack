// Anteprima 3D ruotabile della macchina: il modello GLB caricato oppure la forma generica
// della categoria. Si scarica solo quando la persona preme "Mostra in 3D".
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { buildShape, shapeMaterials } from './shapes.js';
import { cssColor, reducedMotion, track } from './capability.js';

export async function mountPreview(container, cfg) {
    const reduce = reducedMotion();
    const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true, powerPreference: 'low-power' });
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.outputColorSpace = THREE.SRGBColorSpace;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.domElement.setAttribute('aria-hidden', 'true');
    renderer.domElement.style.touchAction = 'none';
    renderer.domElement.style.display = 'block';

    const life = track('preview');
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(35, 1, 0.01, 1000);
    scene.add(new THREE.HemisphereLight(0xffffff, 0x556070, 1.5));
    const sun = new THREE.DirectionalLight(0xffffff, 1.8);
    sun.position.set(3, 6, 4);
    scene.add(sun);

    let object;
    try {
    if (cfg.modelUrl) {
        // Solo il file del modello e i dati incorporati (data:, blob:): nessun indirizzo esterno,
        // nemmeno relativo, viene mai richiesto. Un modello che ne ha bisogno non si mostra.
        const allowed = new URL(cfg.modelUrl, window.location.href).href;
        const blocked = [];
        const manager = new THREE.LoadingManager();
        manager.setURLModifier((url) => {
            if (/^(data|blob):/i.test(url)) return url;
            if (new URL(url, window.location.href).href === allowed) return url;
            blocked.push(url);
            return 'data:application/octet-stream;base64,';
        });
        let gltf;
        try {
            gltf = await new GLTFLoader(manager).loadAsync(cfg.modelUrl);
        } finally {
            if (blocked.length) window.__mt3d.blocked = [...(window.__mt3d.blocked || []), ...blocked];
        }
        if (blocked.length) {
            gltf.scene.traverse((o) => { o.geometry?.dispose(); });
            throw new Error('Riferimenti esterni bloccati: ' + blocked.join(', '));
        }
        object = gltf.scene;
    } else {
        object = buildShape(cfg.shape, cfg.w, cfg.d, cfg.h, shapeMaterials(cssColor(`--mt-${cfg.tone}`, '#2fd3b0')));
    }
    } catch (e) {
        renderer.dispose();
        renderer.forceContextLoss();
        life.close(null);
        throw e;
    }

    // Centrato, appoggiato a terra e inquadrato
    const box = new THREE.Box3().setFromObject(object);
    const sizeV = box.getSize(new THREE.Vector3());
    const center = box.getCenter(new THREE.Vector3());
    object.position.sub(new THREE.Vector3(center.x, box.min.y, center.z));
    scene.add(object);
    const radius = Math.max(sizeV.length() / 2, 0.01);
    const target = new THREE.Vector3(0, sizeV.y / 2, 0);

    const ground = new THREE.Mesh(
        new THREE.CircleGeometry(radius * 1.15, 48),
        new THREE.MeshBasicMaterial({ color: cssColor('--mt-surface-2', '#20262f'), transparent: true, opacity: 0.9 }),
    );
    ground.rotation.x = -Math.PI / 2;
    ground.position.y = -0.001;
    scene.add(ground);

    container.appendChild(renderer.domElement);
    const controls = new OrbitControls(camera, renderer.domElement);
    controls.target.copy(target);
    controls.enableDamping = !reduce;
    controls.enablePan = false;
    controls.minDistance = radius * 1.2;
    controls.maxDistance = radius * 8;
    controls.maxPolarAngle = Math.PI * 0.55;
    controls.autoRotate = !reduce;
    controls.autoRotateSpeed = 1.2;
    controls.addEventListener('start', () => { controls.autoRotate = false; });

    let frame = 0;
    let visible = true;
    let disposed = false;
    const loop = () => {
        frame = 0;
        const moving = controls.update();
        renderer.render(scene, camera);
        life.frame();
        // Un solo fotogramma prenotato alla volta: anche i controlli chiedono di ridisegnare
        if ((moving || controls.autoRotate) && visible && !document.hidden) request();
    };
    // Niente disegno se la scheda è nascosta o la vista è fuori dallo schermo (riparte da sola)
    const request = () => { if (!frame && !disposed && visible && !document.hidden) frame = requestAnimationFrame(loop); };
    controls.addEventListener('change', request);

    let fitted = false;
    const resize = () => {
        const w = container.clientWidth || 1;
        const h = container.clientHeight || 1;
        renderer.setSize(w, h, false);
        renderer.domElement.style.width = '100%';
        renderer.domElement.style.height = '100%';
        camera.aspect = w / h;
        camera.updateProjectionMatrix();
        if (!fitted) {
            const vfov = THREE.MathUtils.degToRad(camera.fov);
            const fov = Math.min(vfov, 2 * Math.atan(Math.tan(vfov / 2) * camera.aspect));
            const dist = (radius / Math.sin(fov / 2)) * 0.95;
            camera.position.copy(target).add(new THREE.Vector3(0.75, 0.5, 1).normalize().multiplyScalar(dist));
            controls.update();
            fitted = true;
        }
        request();
    };
    const ro = new ResizeObserver(resize);
    ro.observe(container);
    const io = new IntersectionObserver(([e]) => { visible = e.isIntersecting; if (visible) request(); });
    io.observe(container);
    const onVisibility = () => { if (!document.hidden) request(); };
    document.addEventListener('visibilitychange', onVisibility);
    resize();
    container.dataset.ready = '1';

    return {
        dispose() {
            if (disposed) return;
            disposed = true;
            cancelAnimationFrame(frame);
            frame = 0;
            ro.disconnect();
            io.disconnect();
            document.removeEventListener('visibilitychange', onVisibility);
            controls.dispose();
            scene.traverse((o) => {
                o.geometry?.dispose();
                const mats = Array.isArray(o.material) ? o.material : o.material ? [o.material] : [];
                for (const m of mats) {
                    for (const k of Object.keys(m)) if (m[k] && m[k].isTexture) m[k].dispose();
                    m.dispose();
                }
            });
            renderer.renderLists.dispose();
            const memory = { ...renderer.info.memory };
            renderer.dispose();
            renderer.forceContextLoss();
            renderer.domElement.remove();
            delete container.dataset.ready;
            life.close(memory);
        },
    };
}
