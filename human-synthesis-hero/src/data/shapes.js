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

export function generateAllShapes(count) {
  return [generateFace(count), generateCell(count), generateBone(count), generateSkull(count)]
}
