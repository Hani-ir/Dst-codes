# Human Synthesis — Hero

Scroll-driven, cursor-reactive Three.js hero section (face → cell cluster → bone → skull).

## Run

```bash
npm install
npm run dev
```

## Structure

- `src/components/Hero.jsx` — layout, nav, "The World" label, per-stage headline/description (Framer Motion)
- `src/components/ParticleField.jsx` — the R3F canvas: particle morph, plexus/constellation lines, starfield, cursor repulsion
- `src/hooks/useScrollStage.js` — single scroll-progress source feeding both the UI and the 3D morph
- `src/data/shapes.js` — the four target point-cloud generators (face, cell, bone, skull)
- `src/data/stages.js` — per-stage copy and color palette

Scroll scrubs continuously through ~400vh; each 25% band is one stage. On desktop, move the cursor over the canvas to repel nearby particles.
