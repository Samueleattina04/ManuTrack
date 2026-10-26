// Genera il tracciato vettoriale della scritta "ManuTrack" del logo.
// Uso: npm run brand  →  resources/brand/wordmark.json
// La scritta viene convertita in tracciati, così il logo non dipende dai font installati.
// Proporzioni ricavate da public/images/logo-originale.png, in unità del file originale
// con origine nell'angolo in alto a sinistra della M: la scritta va da x = -184 a x = 596,
// l'altezza delle maiuscole è 107 e la linea di base è a y = 584.
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import opentype from 'opentype.js';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..');
const fontFile = path.join(root, 'node_modules/@fontsource/open-sans/files/open-sans-latin-700-normal.woff');
const font = opentype.parse(fs.readFileSync(fontFile).buffer.slice(0));

const TARGET = { left: -184, right: 596, capHeight: 107, baseline: 584 };
const text = 'ManuTrack';
const size = 1000;
const tracking = Number(process.env.TRACKING ?? -40); // spaziatura in unità a size 1000

// Disegna lettera per lettera per controllare la spaziatura
let x = 0;
const commands = [];
const glyphs = font.stringToGlyphs(text);
glyphs.forEach((g, i) => {
  const p = g.getPath(x, 0, size);
  commands.push(...p.commands);
  const kern = i < glyphs.length - 1 ? font.getKerningValue(g, glyphs[i + 1]) : 0;
  x += (g.advanceWidth + kern) * (size / font.unitsPerEm) + tracking;
});
const full = new opentype.Path();
full.commands = commands;
const bb = full.getBoundingBox();
const capH = font.charToGlyph('M').getPath(0, 0, size).getBoundingBox();
const capHeight = -capH.y1;

const sy = TARGET.capHeight / capHeight;
const sx = (TARGET.right - TARGET.left) / (bb.x2 - bb.x1);
const fmt = (n) => Math.round(n * 100) / 100;
const tx = (v) => fmt((v - bb.x1) * sx + TARGET.left);
const ty = (v) => fmt(v * sy + TARGET.baseline);

const d = full.commands.map((c) => {
  switch (c.type) {
    case 'M': return `M${tx(c.x)} ${ty(c.y)}`;
    case 'L': return `L${tx(c.x)} ${ty(c.y)}`;
    case 'Q': return `Q${tx(c.x1)} ${ty(c.y1)} ${tx(c.x)} ${ty(c.y)}`;
    case 'C': return `C${tx(c.x1)} ${ty(c.y1)} ${tx(c.x2)} ${ty(c.y2)} ${tx(c.x)} ${ty(c.y)}`;
    case 'Z': return 'Z';
  }
}).join('');

const out = {
  source: 'Open Sans Bold (SIL OFL 1.1), convertita in tracciati',
  text,
  box: { left: TARGET.left, right: TARGET.right, top: TARGET.baseline - TARGET.capHeight, baseline: TARGET.baseline },
  stretch: { x: fmt(sx / sy) },
  d,
};
fs.writeFileSync(path.join(root, 'resources/brand/wordmark.json'), JSON.stringify(out, null, 2) + '\n');
console.log('wordmark.json scritto; rapporto sx/sy =', fmt(sx / sy));
