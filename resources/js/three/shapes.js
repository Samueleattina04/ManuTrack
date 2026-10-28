// Forme generiche delle macchine (stesse di app/Support/Plant/Shapes.php), costruite con
// pochi poligoni a facce piane come il logo. Ingombro: w (x) × d (z) × h (y), base a y = 0.
import * as THREE from 'three';

const geometryCache = new Map();
function geo(key, make) {
    if (!geometryCache.has(key)) geometryCache.set(key, make());
    return geometryCache.get(key);
}
const unitBox = () => geo('box', () => new THREE.BoxGeometry(1, 1, 1));
const unitCyl = (seg = 16) => geo('cyl' + seg, () => new THREE.CylinderGeometry(0.5, 0.5, 1, seg));

function part(geometry, material, [sx, sy, sz], [x, y, z], rotation = null) {
    const mesh = new THREE.Mesh(geometry, material);
    mesh.scale.set(sx, sy, sz);
    mesh.position.set(x, y, z);
    if (rotation) mesh.rotation.set(...rotation);
    return mesh;
}

/**
 * Gruppo della forma. materials: { body, detail } (il dettaglio è una tinta più scura).
 */
export function buildShape(shape, w, d, h, materials) {
    const { body, detail } = materials;
    const g = new THREE.Group();
    const r = Math.min(w, d) / 2;
    switch (shape) {
        case 'cylinder':
            g.add(part(unitCyl(20), body, [r * 2, h * 0.85, r * 2], [0, h * 0.425 + h * 0.08, 0]));
            g.add(part(unitBox(), detail, [w * 0.9, h * 0.08, d * 0.9], [0, h * 0.04, 0]));
            g.add(part(unitCyl(20), detail, [r * 1.2, h * 0.07, r * 1.2], [0, h * 0.965, 0]));
            break;
        case 'press':
            g.add(part(unitBox(), detail, [w, h * 0.22, d], [0, h * 0.11, 0]));
            g.add(part(unitBox(), body, [w * 0.18, h * 0.62, d * 0.8], [-w * 0.41, h * 0.53, 0]));
            g.add(part(unitBox(), body, [w * 0.18, h * 0.62, d * 0.8], [w * 0.41, h * 0.53, 0]));
            g.add(part(unitBox(), body, [w, h * 0.18, d * 0.9], [0, h * 0.91, 0]));
            g.add(part(unitBox(), detail, [w * 0.5, h * 0.2, d * 0.6], [0, h * 0.62, 0]));
            break;
        case 'conveyor': {
            const top = h;
            g.add(part(unitBox(), body, [w, 0.12, d], [0, top - 0.06, 0]));
            g.add(part(unitBox(), detail, [w * 0.98, 0.03, d * 0.86], [0, top + 0.015, 0]));
            const legs = Math.max(2, Math.round(w / 2));
            for (let i = 0; i < legs; i++) {
                const x = -w / 2 + 0.15 + (i * (w - 0.3)) / (legs - 1);
                g.add(part(unitBox(), detail, [0.08, top - 0.12, 0.08], [x, (top - 0.12) / 2, -d / 2 + 0.1]));
                g.add(part(unitBox(), detail, [0.08, top - 0.12, 0.08], [x, (top - 0.12) / 2, d / 2 - 0.1]));
            }
            break;
        }
        case 'tank':
            g.add(part(unitCyl(24), body, [r * 2, h * 0.7, r * 2], [0, h * 0.15 + h * 0.35, 0]));
            g.add(part(geo('dome', () => new THREE.SphereGeometry(0.5, 24, 8, 0, Math.PI * 2, 0, Math.PI / 2)), body, [r * 2, h * 0.3, r * 2], [0, h * 0.85, 0]));
            for (const [x, z] of [[1, 1], [1, -1], [-1, 1], [-1, -1]]) {
                g.add(part(unitBox(), detail, [0.1, h * 0.15, 0.1], [x * r * 0.6, h * 0.075, z * r * 0.6]));
            }
            break;
        case 'robot':
            g.add(part(unitCyl(16), detail, [r * 1.6, h * 0.12, r * 1.6], [0, h * 0.06, 0]));
            g.add(part(unitCyl(16), body, [r * 0.9, h * 0.2, r * 0.9], [0, h * 0.22, 0]));
            g.add(part(unitBox(), body, [r * 0.35, h * 0.5, r * 0.35], [0, h * 0.55, 0], [0, 0, -0.35]));
            g.add(part(unitBox(), body, [r * 0.3, h * 0.42, r * 0.3], [r * 0.45, h * 0.82, 0], [0, 0, 1.05]));
            g.add(part(unitBox(), detail, [r * 0.3, r * 0.3, r * 0.4], [r * 0.85, h * 0.7, 0]));
            break;
        default: // box: armadio o macchina generica con quadro comandi
            g.add(part(unitBox(), body, [w, h * 0.9, d], [0, h * 0.45, 0]));
            g.add(part(unitBox(), detail, [w * 0.7, h * 0.1, d * 0.7], [0, h * 0.95, 0]));
            g.add(part(unitBox(), detail, [w * 0.3, h * 0.25, 0.04], [w * 0.2, h * 0.6, d / 2 + 0.02]));
    }
    return g;
}

/** Materiali a facce piane per una tinta (il dettaglio è più scuro) */
export function shapeMaterials(color) {
    const base = new THREE.Color(color);
    return {
        body: new THREE.MeshStandardMaterial({ color: base, flatShading: true, roughness: 0.55, metalness: 0.1 }),
        detail: new THREE.MeshStandardMaterial({ color: base.clone().multiplyScalar(0.62), flatShading: true, roughness: 0.6, metalness: 0.15 }),
    };
}

export function setMaterialsColor(materials, color) {
    materials.body.color.set(color);
    materials.detail.color.set(color).multiplyScalar(0.62);
}
