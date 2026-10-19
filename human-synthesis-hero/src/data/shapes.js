// Procedural point-cloud generators for the four morph targets.
// Every generator takes the SAME `count` so the resulting Float32Arrays
// map 1:1 by index — that's what makes a smooth per-particle lerp possible.

function gaussian2D(dx, dy, sigma) {
  return Math.exp(-(dx * dx + dy * dy) / (2 * sigma * sigma))
}

/** Stage 1: a symmetric, front-facing human face/mask point cloud. */
export function generateFace(count) {
  const pts = new Float32Array(count * 3)
  for (let i = 0; i < count; i++) {
    const theta = Math.random() * Math.PI * 2
    const v = Math.random()
    const phi = Math.acos(2 * v - 1)

    let x = Math.sin(phi) * Math.cos(theta)
    let y = Math.sin(phi) * Math.sin(theta)
    let z = Math.cos(phi)

    // Bias the cloud toward the front hemisphere (a mask, not a full sphere).
    z = Math.abs(z) * 0.55 + z * 0.45

    // Face proportions: taller than wide, shallow depth.
    x *= 1.05
    y *= 1.35
    z *= 0.85

    // Carve eye sockets, nose ridge and mouth via radial falloffs.
    const eyeL = gaussian2D(x + 0.34, y - 0.12, 0.13)
    const eyeR = gaussian2D(x - 0.34, y - 0.12, 0.13)
    const nose = gaussian2D(x, y - 0.48, 0.1)
    const mouth = gaussian2D(x, y - 0.86, 0.19)

    z -= (eyeL + eyeR) * 0.32
    z += nose * 0.22
    z -= mouth * 0.14

    pts[i * 3] = x * 1.55
    pts[i * 3 + 1] = y * 1.7 + 0.15
    pts[i * 3 + 2] = z * 1.15
  }
  return pts
}

/** Stage 2: a dense, irregular, electrified cell/nucleus-like cluster. */
export function generateCell(count) {
  const pts = new Float32Array(count * 3)
  for (let i = 0; i < count; i++) {
    const theta = Math.random() * Math.PI * 2
    const phi = Math.acos(2 * Math.random() - 1)

    const base = 1.35
    const n1 = Math.sin(theta * 3 + phi * 5) * 0.26
    const n2 = Math.sin(theta * 7 - phi * 2.3) * 0.16
    const n3 = Math.sin(phi * 9 + theta * 2.2) * 0.1
    const jitter = (Math.random() - 0.5) * 0.1

    const r = base + n1 + n2 + n3 + jitter

    pts[i * 3] = r * Math.sin(phi) * Math.cos(theta)
    pts[i * 3 + 1] = r * Math.sin(phi) * Math.sin(theta)
    pts[i * 3 + 2] = r * Math.cos(phi)
  }
  return pts
}

/** Stage 3: an elongated femur-like bone — hollow shaft with bulbous rounded ends. */
export function generateBone(count) {
  const pts = new Float32Array(count * 3)
  const length = 3.4
  const shaftRadius = 0.2
  const capRadius = 0.5
  // A small fraction of points scatter through the bulb volumes (so the
  // rounded ends read as knuckles, not rings), the rest trace the shell.
  const fillFraction = 0.25

  for (let i = 0; i < count; i++) {
    const t = Math.random() // 0..1 along the shaft
    const y = (t - 0.5) * length

    const topCap = Math.exp(-Math.pow((t - 0.07) * 7, 2))
    const bottomCap = Math.exp(-Math.pow((t - 0.93) * 7, 2))
    const capAmount = Math.max(topCap, bottomCap)
    const radius = shaftRadius + (capRadius - shaftRadius) * capAmount

    const theta = Math.random() * Math.PI * 2
    const isFilled = Math.random() < fillFraction * capAmount
    const rr = isFilled ? radius * Math.cbrt(Math.random()) : radius

    pts[i * 3] = Math.cos(theta) * rr
    pts[i * 3 + 1] = y
    pts[i * 3 + 2] = Math.sin(theta) * rr
  }
  return pts
}

/** Stage 4: an oval cranial dome, tapering into a jaw toward the front-bottom. */
export function generateSkull(count) {
  const pts = new Float32Array(count * 3)
  const radiusX = 1.05
  const radiusY = 1.3
  const radiusZ = 1.1

  for (let i = 0; i < count; i++) {
    const theta = Math.random() * Math.PI * 2
    const phi = Math.acos(2 * Math.random() - 1)

    let x = Math.sin(phi) * Math.cos(theta)
    let y = Math.sin(phi) * Math.sin(theta)
    let z = Math.cos(phi)

    if (y < -0.2) {
      const taper = 1 - (Math.abs(y + 0.2) / 1.0) * 0.55
      const clamped = Math.max(0.35, taper)
      x *= clamped
      z *= clamped * (z > 0 ? 1.12 : 0.7) // jaw juts slightly forward
    }

    pts[i * 3] = x * radiusX
    pts[i * 3 + 1] = y * radiusY + 0.3
    pts[i * 3 + 2] = z * radiusZ
  }
  return pts
}

// --- Tank (box) helpers --------------------------------------------

/** A random point on the surface of an axis-aligned box, face-area weighted. */
function sampleBoxSurface(w, h, d, cx, cy, cz) {
  const areas = [h * d, h * d, w * d, w * d, w * h, w * h]
  const total = areas[0] + areas[1] + areas[2] + areas[3] + areas[4] + areas[5]
  let r = Math.random() * total
  let face = 0
  for (; face < 5; face++) {
    if (r < areas[face]) break
    r -= areas[face]
  }
  const hw = w / 2
  const hh = h / 2
  const hd = d / 2
  const u = Math.random() - 0.5
  const v = Math.random() - 0.5
  let x = 0
  let y = 0
  let z = 0
  if (face === 0) { x = -hw; y = u * h; z = v * d }
  else if (face === 1) { x = hw; y = u * h; z = v * d }
  else if (face === 2) { y = -hh; x = u * w; z = v * d }
  else if (face === 3) { y = hh; x = u * w; z = v * d }
  else if (face === 4) { z = -hd; x = u * w; y = v * h }
  else { z = hd; x = u * w; y = v * h }
  return [cx + x, cy + y, cz + z]
}

/** A random point along one of a box's 12 edges — traces a crisp wireframe. */
function sampleBoxEdges(w, h, d, cx, cy, cz) {
  const hw = w / 2
  const hh = h / 2
  const hd = d / 2
  const corners = [
    [-hw, -hh, -hd], [hw, -hh, -hd], [hw, -hh, hd], [-hw, -hh, hd],
    [-hw, hh, -hd], [hw, hh, -hd], [hw, hh, hd], [-hw, hh, hd],
  ]
  const edges = [
    [0, 1], [1, 2], [2, 3], [3, 0],
    [4, 5], [5, 6], [6, 7], [7, 4],
    [0, 4], [1, 5], [2, 6], [3, 7],
  ]
  const e = edges[Math.floor(Math.random() * edges.length)]
  const t = Math.random()
  const a = corners[e[0]]
  const b = corners[e[1]]
  return [
    cx + a[0] + (b[0] - a[0]) * t,
    cy + a[1] + (b[1] - a[1]) * t,
    cz + a[2] + (b[2] - a[2]) * t,
  ]
}

/** A point on the outline of a rectangle on the box's top (+Y) face. */
function sampleRectOutlineTop(w, d, cx, cy, cz) {
  const hw = w / 2
  const hd = d / 2
  const perim = 2 * (w + d)
  let r = Math.random() * perim
  let x
  let z
  if (r < w) { x = -hw + r; z = -hd }
  else if ((r -= w) < d) { x = hw; z = -hd + r }
  else if ((r -= d) < w) { x = hw - r; z = hd }
  else { r -= w; x = -hw; z = hd - r }
  return [cx + x, cy, cz + z]
}

/** Rotate a point around Y then X — a fixed isometric tilt so the box
 * reads as a 3D object (top + two side faces visible) even though the
 * camera itself never moves. */
function isoRotate(x, y, z, radY, radX) {
  const cosY = Math.cos(radY)
  const sinY = Math.sin(radY)
  const x1 = x * cosY + z * sinY
  const z1 = -x * sinY + z * cosY
  const cosX = Math.cos(radX)
  const sinX = Math.sin(radX)
  const y2 = y * cosX - z1 * sinX
  const z2 = y * sinX + z1 * cosX
  return [x1, y2, z2]
}

/**
 * Stage 5: the DST Lubestation tank — a two-tier rounded cabinet (a
 * wider "DST" box on top of a narrower "LUBESTATION" pedestal), a round
 * side vent, two top lids, and a small stacked-bar logo mark on the
 * front face. Built as box wireframe + surface dust + feature details,
 * then tilted into a fixed isometric angle so every face reads clearly
 * from the hero's static front-on camera.
 */
export function generateTank(count) {
  const pts = new Float32Array(count * 3)

  // Local box-space dimensions (pre-rotation). S matches the box's
  // overall footprint to the other shapes' ~1.3-1.7 unit radius scale
  // (the box dimensions below are full widths, not radii, so they'd
  // otherwise read about half the size of the face/skull/bone clouds).
  const S = 1.8
  const topW = 1.6 * S, topH = 0.9 * S, topD = 1.1 * S
  const topCX = 0, topCY = 0.25 * S, topCZ = 0
  const baseW = 1.4 * S, baseH = 0.5 * S, baseD = 0.95 * S
  const baseCX = 0, baseCY = -0.45 * S, baseCZ = 0

  const ventCX = -topW / 2 - 0.018, ventCY = topCY, ventCZ = 0.09 * S
  const ventR = 0.32 * S

  const radY = (35 * Math.PI) / 180
  const radX = (-18 * Math.PI) / 180

  for (let i = 0; i < count; i++) {
    const r = Math.random()
    let x, y, z

    if (r < 0.35) {
      // Edge wireframe — the crispest cue that this is a box.
      ;[x, y, z] = Math.random() < 0.65
        ? sampleBoxEdges(topW, topH, topD, topCX, topCY, topCZ)
        : sampleBoxEdges(baseW, baseH, baseD, baseCX, baseCY, baseCZ)
    } else if (r < 0.62) {
      // Surface dust for a touch of volume/texture.
      ;[x, y, z] = sampleBoxSurface(topW, topH, topD, topCX, topCY, topCZ)
    } else if (r < 0.78) {
      ;[x, y, z] = sampleBoxSurface(baseW, baseH, baseD, baseCX, baseCY, baseCZ)
    } else if (r < 0.88) {
      // Round side vent: ring + spokes + a square backing panel.
      const vr = Math.random()
      if (vr < 0.5) {
        const a = Math.random() * Math.PI * 2
        x = ventCX
        y = ventCY + Math.cos(a) * ventR
        z = ventCZ + Math.sin(a) * ventR
      } else if (vr < 0.8) {
        const a = Math.floor(Math.random() * 4) * (Math.PI / 2) + Math.PI / 8
        const t = Math.random()
        x = ventCX
        y = ventCY + Math.cos(a) * ventR * t
        z = ventCZ + Math.sin(a) * ventR * t
      } else {
        const hw = 0.42 * S, hh = 0.42 * S
        const u = Math.random() - 0.5
        const v = Math.random() - 0.5
        const edge = Math.random() < 0.5
        x = ventCX - 0.015 * S
        y = ventCY + (edge ? u * hw * 2 : (Math.random() < 0.5 ? -hh : hh))
        z = ventCZ + (edge ? (Math.random() < 0.5 ? -hw : hw) : v * hw * 2)
      }
    } else if (r < 0.96) {
      // Two raised lids on the top face, each an outlined rectangle.
      const leftLid = Math.random() < 0.55
      const lw = (leftLid ? 0.62 : 0.58) * S
      const ld = (leftLid ? 0.56 : 0.5) * S
      const lcx = (leftLid ? -0.4 : 0.45) * S
      const lcz = (leftLid ? -0.1 : 0.08) * S
      ;[x, y, z] = sampleRectOutlineTop(lw, ld, topCX + lcx, topCY + topH / 2 + 0.01, topCZ + lcz)
      if (Math.random() < 0.12) {
        // A small handle loop above the left lid.
        const a = Math.PI * 0.2 + Math.random() * Math.PI * 0.6
        x = topCX + lcx + Math.cos(a) * 0.12 * S
        y = topCY + topH / 2 + 0.05 * S + Math.sin(a) * 0.08 * S
        z = topCZ + lcz + 0.3 * S
      }
    } else {
      // Small stacked-bar logo mark on the front face.
      const level = Math.floor(Math.random() * 6)
      const barW = (0.05 + level * 0.035) * S
      const bx = -0.35 * S + (Math.random() - 0.5) * barW
      const by = topCY - 0.15 * S + (5 - level) * 0.055 * S
      x = bx
      y = by
      z = -topD / 2 - 0.015
    }

    const [rx, ry, rz] = isoRotate(x, y, z, radY, radX)
    pts[i * 3] = rx
    pts[i * 3 + 1] = ry
    pts[i * 3 + 2] = rz
  }

  return pts
}

// --- Tank wireframe (exact, not randomly sampled) ------------------
// generateTank() above scatters points near the box for a dusty, morph-
// able cloud, but random nearest-neighbor connections can't draw a
// clean edge. This builds literal straight line segments — the box's
// 12+12 edges, the vent as a true circle, the two lids as exact
// rectangles, and the logo as stacked bars — for a crisp, legible
// overlay instead of an abstract blob.
function pushBoxEdgeSegments(out, w, h, d, cx, cy, cz) {
  const hw = w / 2, hh = h / 2, hd = d / 2
  const c = [
    [-hw, -hh, -hd], [hw, -hh, -hd], [hw, -hh, hd], [-hw, -hh, hd],
    [-hw, hh, -hd], [hw, hh, -hd], [hw, hh, hd], [-hw, hh, hd],
  ]
  const edges = [
    [0, 1], [1, 2], [2, 3], [3, 0],
    [4, 5], [5, 6], [6, 7], [7, 4],
    [0, 4], [1, 5], [2, 6], [3, 7],
  ]
  for (const [a, b] of edges) {
    out.push(cx + c[a][0], cy + c[a][1], cz + c[a][2])
    out.push(cx + c[b][0], cy + c[b][1], cz + c[b][2])
  }
}

function pushRectOutlineSegments(out, w, d, cx, cy, cz) {
  const hw = w / 2, hd = d / 2
  const c = [[-hw, -hd], [hw, -hd], [hw, hd], [-hw, hd]]
  for (let i = 0; i < 4; i++) {
    const a = c[i], b = c[(i + 1) % 4]
    out.push(cx + a[0], cy, cz + a[1])
    out.push(cx + b[0], cy, cz + b[1])
  }
}

function pushCircleSegments(out, cx, cy, cz, r, segments) {
  for (let i = 0; i < segments; i++) {
    const a0 = (i / segments) * Math.PI * 2
    const a1 = ((i + 1) / segments) * Math.PI * 2
    out.push(cx, cy + Math.cos(a0) * r, cz + Math.sin(a0) * r)
    out.push(cx, cy + Math.cos(a1) * r, cz + Math.sin(a1) * r)
  }
}

/** Exact line-segment geometry for the tank — see the comment above. */
export function generateTankWireframe() {
  const S = 1.8
  const topW = 1.6 * S, topH = 0.9 * S, topD = 1.1 * S
  const topCX = 0, topCY = 0.25 * S, topCZ = 0
  const baseW = 1.4 * S, baseH = 0.5 * S, baseD = 0.95 * S
  const baseCX = 0, baseCY = -0.45 * S, baseCZ = 0
  const ventCX = -topW / 2 - 0.018, ventCY = topCY, ventCZ = 0.09 * S
  const ventR = 0.32 * S
  const radY = (35 * Math.PI) / 180
  const radX = (-18 * Math.PI) / 180

  const segs = []
  pushBoxEdgeSegments(segs, topW, topH, topD, topCX, topCY, topCZ)
  pushBoxEdgeSegments(segs, baseW, baseH, baseD, baseCX, baseCY, baseCZ)
  pushCircleSegments(segs, ventCX, ventCY, ventCZ, ventR, 28)
  pushRectOutlineSegments(segs, 0.62 * S, 0.56 * S, topCX - 0.4 * S, topCY + topH / 2 + 0.01, topCZ - 0.1 * S)
  pushRectOutlineSegments(segs, 0.58 * S, 0.5 * S, topCX + 0.45 * S, topCY + topH / 2 + 0.01, topCZ + 0.08 * S)
  for (let level = 0; level < 6; level++) {
    const barW = (0.05 + level * 0.035) * S
    const y = topCY - 0.15 * S + (5 - level) * 0.055 * S
    const z = -topD / 2 - 0.015
    segs.push(-barW / 2, y, z, barW / 2, y, z)
  }

  const out = new Float32Array(segs.length)
  for (let i = 0; i < segs.length; i += 3) {
    const [rx, ry, rz] = isoRotate(segs[i], segs[i + 1], segs[i + 2], radY, radX)
    // Matches generateAllShapes' post-generation scale so this overlay
    // lines up with the dust cloud from generateTank().
    out[i] = rx * SHAPE_SCALE
    out[i + 1] = ry * SHAPE_SCALE
    out[i + 2] = rz * SHAPE_SCALE
  }
  return out
}

// Cumulative volume reduction: 30% first, then another 50% on top of
// that (0.7 * 0.5 = 0.35 of the original volume remains). Volume scales
// with the cube of linear length, so the scale factor applied to each
// coordinate is the cube root of the remaining fraction, not the
// fraction itself.
export const SHAPE_SCALE = Math.cbrt(0.7 * 0.5)

export function generateAllShapes(count) {
  const shapes = [generateFace(count), generateCell(count), generateBone(count)]
  for (const shape of shapes) {
    for (let i = 0; i < shape.length; i++) shape[i] *= SHAPE_SCALE
  }
  return shapes
}
