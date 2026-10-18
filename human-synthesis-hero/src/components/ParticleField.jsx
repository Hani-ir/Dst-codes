import { useEffect, useMemo, useRef } from 'react'
import { Canvas, useFrame, useThree } from '@react-three/fiber'
import * as THREE from 'three'
import { generateAllShapes } from '../data/shapes.js'
import { STAGES } from '../data/stages.js'

const PARTICLE_COUNT_DESKTOP = 6000
const PARTICLE_COUNT_MOBILE = 2000
const LINE_SAMPLE = 700
// Bone and skull are meant to read as sparse constellations, not a dense
// tangle, so far fewer of the sampled particles get wired into lines there.
const LINE_SAMPLE_PER_STAGE = [700, 700, 260, 380]
const MAX_NEIGHBORS = [3, 3, 2, 2]
const NEIGHBOR_MAX_DIST = [0.5, 0.55, 0.4, 0.42]

// Point visibility is lower for the sparser constellation-style stages
// (bone, skull) so the connecting lines carry more of the silhouette.
const POINT_OPACITY = [0.9, 0.82, 0.32, 0.3]
const LINE_OPACITY = [0.3, 0.5, 0.6, 0.5]

// Three.js's default PointsMaterial renders square sprites, which reads
// as a glitch once sizeAttenuation blows a point up on approach. A soft
// radial-gradient texture makes every point (stars included) a round,
// softly glowing dot instead.
function createDotTexture() {
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = 64
  const ctx = canvas.getContext('2d')
  const gradient = ctx.createRadialGradient(32, 32, 0, 32, 32, 32)
  gradient.addColorStop(0, 'rgba(255,255,255,1)')
  gradient.addColorStop(0.4, 'rgba(255,255,255,0.7)')
  gradient.addColorStop(1, 'rgba(255,255,255,0)')
  ctx.fillStyle = gradient
  ctx.fillRect(0, 0, 64, 64)
  return new THREE.CanvasTexture(canvas)
}

function buildNeighborPairs(shape, sampleIndices, maxDist, maxNeighbors) {
  const n = sampleIndices.length
  const maxDist2 = maxDist * maxDist
  const raw = []

  for (let a = 0; a < n; a++) {
    const i = sampleIndices[a]
    const ix = shape[i * 3]
    const iy = shape[i * 3 + 1]
    const iz = shape[i * 3 + 2]
    const candidates = []

    for (let b = 0; b < n; b++) {
      if (a === b) continue
      const j = sampleIndices[b]
      const dx = shape[j * 3] - ix
      const dy = shape[j * 3 + 1] - iy
      const dz = shape[j * 3 + 2] - iz
      const d2 = dx * dx + dy * dy + dz * dz
      if (d2 < maxDist2) candidates.push([d2, j])
    }

    candidates.sort((x, y) => x[0] - y[0])
    for (let k = 0; k < Math.min(maxNeighbors, candidates.length); k++) {
      const j = candidates[k][1]
      raw.push(Math.min(i, j), Math.max(i, j))
    }
  }

  const seen = new Set()
  const out = []
  for (let p = 0; p < raw.length; p += 2) {
    const key = raw[p] * 1e6 + raw[p + 1]
    if (!seen.has(key)) {
      seen.add(key)
      out.push(raw[p], raw[p + 1])
    }
  }
  return out
}

// Stars travel from deep behind the particle shape (FAR_Z) toward the
// camera/viewer (NEAR_Z), then loop back. Their (x, y) offset from the
// center axis stays fixed while z closes in — under perspective that
// alone makes them radiate outward from screen-center to the edges as
// they approach, the classic "flying through space" look.
const STAR_FAR_Z = -45
const STAR_NEAR_Z = 4.3
const STAR_SPEED = 5.5

function Starfield({ stageFloatRef }) {
  const count = 1600
  const matRef = useRef()
  const geoRef = useRef()

  const positions = useRef()
  if (!positions.current) {
    const arr = new Float32Array(count * 3)
    for (let i = 0; i < count; i++) {
      const angle = Math.random() * Math.PI * 2
      const radius = 0.3 + Math.random() * 10
      arr[i * 3] = Math.cos(angle) * radius
      arr[i * 3 + 1] = Math.sin(angle) * radius
      // Staggered start depths so stars don't all arrive at once.
      arr[i * 3 + 2] = STAR_FAR_Z + Math.random() * (STAR_NEAR_Z - STAR_FAR_Z)
    }
    positions.current = arr
  }

  const colorA = useMemo(() => new THREE.Color(), [])
  const colorB = useMemo(() => new THREE.Color(), [])
  const current = useMemo(() => new THREE.Color(), [])
  const dotTexture = useMemo(() => createDotTexture(), [])

  useFrame((_, delta) => {
    const stageFloat = stageFloatRef.current
    const stageIdx = Math.min(3, Math.floor(stageFloat))
    const nextIdx = Math.min(3, stageIdx + 1)
    const localT = stageFloat - stageIdx

    colorA.set(STAGES[stageIdx].starColor)
    colorB.set(STAGES[nextIdx].starColor)
    current.copy(colorA).lerp(colorB, localT)

    if (matRef.current) {
      matRef.current.color.copy(current)
      // Visible from stage 1 onward (floor of 0.4) and ramps to full by
      // mid stage-2, boosted ~50% over the original faint-haze levels.
      const density = Math.min(1, Math.max(0.4, stageFloat / 1.3))
      matRef.current.opacity = Math.min(1, 0.18 + density * 0.9)
    }

    const pos = positions.current
    for (let i = 0; i < count; i++) {
      const iz = i * 3 + 2
      pos[iz] += STAR_SPEED * delta
      if (pos[iz] > STAR_NEAR_Z) {
        const angle = Math.random() * Math.PI * 2
        const radius = 0.3 + Math.random() * 10
        pos[i * 3] = Math.cos(angle) * radius
        pos[i * 3 + 1] = Math.sin(angle) * radius
        pos[iz] = STAR_FAR_Z
      }
    }
    if (geoRef.current) geoRef.current.attributes.position.needsUpdate = true
  })

  return (
    <points>
      <bufferGeometry ref={geoRef}>
        <bufferAttribute attach="attributes-position" args={[positions.current, 3]} />
      </bufferGeometry>
      <pointsMaterial
        ref={matRef}
        map={dotTexture}
        alphaTest={0.01}
        size={0.045}
        color="#2dd4bf"
        transparent
        opacity={0.18}
        sizeAttenuation
        depthWrite={false}
      />
    </points>
  )
}

function Particles({ stageFloatRef, isTouch }) {
  const { camera } = useThree()
  const count = isTouch ? PARTICLE_COUNT_MOBILE : PARTICLE_COUNT_DESKTOP

  const shapes = useMemo(() => generateAllShapes(count), [count])

  // A fixed, per-particle outward direction used to "explode" a shape
  // apart and let the next one "implode" back together from that same
  // scattered point, instead of lerping straight from A to B.
  const explodeDir = useMemo(() => {
    const dir = new Float32Array(count * 3)
    for (let i = 0; i < count; i++) {
      const theta = Math.random() * Math.PI * 2
      const phi = Math.acos(2 * Math.random() - 1)
      dir[i * 3] = Math.sin(phi) * Math.cos(theta)
      dir[i * 3 + 1] = Math.sin(phi) * Math.sin(theta)
      dir[i * 3 + 2] = Math.cos(phi)
    }
    return dir
  }, [count])
  const burstAmount = isTouch ? 1.1 : 1.7

  const sampleIndices = useMemo(() => {
    const size = Math.min(LINE_SAMPLE, count)
    return Array.from({ length: size }, (_, k) => Math.floor((k * count) / size))
  }, [count])

  const neighborPairs = useMemo(
    () =>
      shapes.map((shape, idx) => {
        const stageSample = sampleIndices.slice(0, Math.min(sampleIndices.length, LINE_SAMPLE_PER_STAGE[idx]))
        return buildNeighborPairs(shape, stageSample, NEIGHBOR_MAX_DIST[idx], MAX_NEIGHBORS[idx])
      }),
    [shapes, sampleIndices],
  )

  // Pad every stage's pair list to the same length so the line
  // geometries can use a fixed-size buffer across stage transitions.
  const { pairs, pairsLen } = useMemo(() => {
    const maxLen = Math.max(...neighborPairs.map((p) => p.length), 2)
    const padded = neighborPairs.map((p) => {
      if (p.length === maxLen) return p
      const out = p.slice()
      while (out.length < maxLen) out.push(p[0] ?? 0, p[1] ?? 0)
      return out
    })
    return { pairs: padded, pairsLen: maxLen }
  }, [neighborPairs])

  const positions = useRef(new Float32Array(shapes[0]))
  const pointsGeoRef = useRef()
  const materialRef = useRef()
  const lineAGeoRef = useRef()
  const lineBGeoRef = useRef()
  const lineMatARef = useRef()
  const lineMatBRef = useRef()

  const mouseWorld = useRef(new THREE.Vector3(9999, 9999, 0))
  const raycaster = useRef(new THREE.Raycaster())
  const plane = useRef(new THREE.Plane(new THREE.Vector3(0, 0, 1), 0))
  const clock = useRef(0)
  const idleTime = useRef(0)
  const lastStageFloat = useRef(0)

  const colorA = useMemo(() => new THREE.Color(), [])
  const colorB = useMemo(() => new THREE.Color(), [])
  const currentColor = useMemo(() => new THREE.Color(), [])

  useEffect(() => {
    if (isTouch) return
    const onMove = (event) => {
      const ndc = new THREE.Vector2(
        (event.clientX / window.innerWidth) * 2 - 1,
        -(event.clientY / window.innerHeight) * 2 + 1,
      )
      raycaster.current.setFromCamera(ndc, camera)
      const point = new THREE.Vector3()
      const hit = raycaster.current.ray.intersectPlane(plane.current, point)
      if (hit) mouseWorld.current.copy(point)
    }
    window.addEventListener('pointermove', onMove)
    return () => window.removeEventListener('pointermove', onMove)
  }, [camera, isTouch])

  useFrame((_, delta) => {
    clock.current += delta
    const stageFloat = stageFloatRef.current
    const stageIdx = Math.min(3, Math.floor(stageFloat))
    const nextIdx = Math.min(3, stageIdx + 1)
    const localT = stageFloat - stageIdx

    if (Math.abs(stageFloat - lastStageFloat.current) > 0.0008) {
      idleTime.current = 0
      lastStageFloat.current = stageFloat
    } else {
      idleTime.current += delta
    }
    // Ambient "breathing" pulse once the shape has settled and scroll is idle.
    const breathe = idleTime.current > 0.6 ? Math.sin(clock.current * 0.6) * 0.025 : 0

    const shapeA = shapes[stageIdx]
    const shapeB = shapes[nextIdx]
    const pos = positions.current
    const repulseRadius = isTouch ? 0 : 1.1
    const repulseStrength = 0.6

    // Explode outward at the midpoint of the transition, then let the
    // next shape converge back in from that same scattered point —
    // zero at both ends (t=0 is exactly A, t=1 is exactly B), peaking
    // at t=0.5, so forward and backward scroll both stay continuous.
    const burst = Math.sin(Math.PI * localT) * burstAmount

    for (let i = 0; i < count; i++) {
      const ix = i * 3
      const gx = shapeA[ix] + (shapeB[ix] - shapeA[ix]) * localT + explodeDir[ix] * burst
      const gy = shapeA[ix + 1] + (shapeB[ix + 1] - shapeA[ix + 1]) * localT + explodeDir[ix + 1] * burst
      const gz = shapeA[ix + 2] + (shapeB[ix + 2] - shapeA[ix + 2]) * localT + explodeDir[ix + 2] * burst

      let tx = gx * (1 + breathe)
      let ty = gy * (1 + breathe)
      const tz = gz

      if (!isTouch) {
        const dx = pos[ix] - mouseWorld.current.x
        const dy = pos[ix + 1] - mouseWorld.current.y
        const d = Math.sqrt(dx * dx + dy * dy)
        if (d < repulseRadius && d > 0.0001) {
          const f = (1 - d / repulseRadius) * repulseStrength
          tx += (dx / d) * f
          ty += (dy / d) * f
        }
      }

      pos[ix] += (tx - pos[ix]) * 0.08
      pos[ix + 1] += (ty - pos[ix + 1]) * 0.08
      pos[ix + 2] += (tz - pos[ix + 2]) * 0.08
    }

    if (pointsGeoRef.current) {
      pointsGeoRef.current.attributes.position.needsUpdate = true
    }

    colorA.set(STAGES[stageIdx].colorHot)
    colorB.set(STAGES[nextIdx].colorHot)
    currentColor.copy(colorA).lerp(colorB, localT)

    if (materialRef.current) {
      materialRef.current.color.copy(currentColor)
      const opA = POINT_OPACITY[stageIdx]
      const opB = POINT_OPACITY[nextIdx]
      materialRef.current.opacity = opA + (opB - opA) * localT
    }

    const opacityA = (1 - localT) * LINE_OPACITY[stageIdx]
    const opacityB = localT * LINE_OPACITY[nextIdx]
    if (lineMatARef.current) {
      lineMatARef.current.color.copy(currentColor)
      lineMatARef.current.opacity = opacityA
    }
    if (lineMatBRef.current) {
      lineMatBRef.current.color.copy(currentColor)
      lineMatBRef.current.opacity = opacityB
    }

    const pairsA = pairs[stageIdx]
    const pairsB = pairs[nextIdx]

    if (lineAGeoRef.current) {
      const arr = lineAGeoRef.current.attributes.position.array
      for (let p = 0; p < pairsA.length; p++) {
        const idx = pairsA[p]
        arr[p * 3] = pos[idx * 3]
        arr[p * 3 + 1] = pos[idx * 3 + 1]
        arr[p * 3 + 2] = pos[idx * 3 + 2]
      }
      lineAGeoRef.current.attributes.position.needsUpdate = true
    }
    if (lineBGeoRef.current) {
      const arr = lineBGeoRef.current.attributes.position.array
      for (let p = 0; p < pairsB.length; p++) {
        const idx = pairsB[p]
        arr[p * 3] = pos[idx * 3]
        arr[p * 3 + 1] = pos[idx * 3 + 1]
        arr[p * 3 + 2] = pos[idx * 3 + 2]
      }
      lineBGeoRef.current.attributes.position.needsUpdate = true
    }
  })

  return (
    <group>
      <points>
        <bufferGeometry ref={pointsGeoRef}>
          <bufferAttribute attach="attributes-position" args={[positions.current, 3]} />
        </bufferGeometry>
        <pointsMaterial
          ref={materialRef}
          size={isTouch ? 0.028 : 0.022}
          color="#2dd4bf"
          transparent
          depthWrite={false}
          opacity={0.85}
          sizeAttenuation
        />
      </points>

      <lineSegments>
        <bufferGeometry ref={lineAGeoRef}>
          <bufferAttribute attach="attributes-position" args={[new Float32Array(pairsLen * 3), 3]} />
        </bufferGeometry>
        <lineBasicMaterial ref={lineMatARef} color="#2dd4bf" transparent opacity={0.3} />
      </lineSegments>

      <lineSegments>
        <bufferGeometry ref={lineBGeoRef}>
          <bufferAttribute attach="attributes-position" args={[new Float32Array(pairsLen * 3), 3]} />
        </bufferGeometry>
        <lineBasicMaterial ref={lineMatBRef} color="#2dd4bf" transparent opacity={0} />
      </lineSegments>
    </group>
  )
}

export default function ParticleField({ stageFloatRef, isTouch }) {
  return (
    <Canvas
      className="!fixed !inset-0 !h-screen !w-screen"
      camera={{ position: [0, 0, 6], fov: 45 }}
      gl={{ antialias: true, alpha: false }}
      dpr={[1, 2]}
    >
      <color attach="background" args={['#000000']} />
      <ambientLight intensity={0.4} />
      <Starfield stageFloatRef={stageFloatRef} />
      <Particles stageFloatRef={stageFloatRef} isTouch={isTouch} />
    </Canvas>
  )
}
