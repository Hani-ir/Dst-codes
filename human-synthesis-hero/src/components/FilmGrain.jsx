import { useEffect, useMemo, useRef } from 'react'

function createNoiseTile(size = 128) {
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = size
  const ctx = canvas.getContext('2d')
  const imageData = ctx.createImageData(size, size)
  for (let i = 0; i < imageData.data.length; i += 4) {
    const v = Math.floor(Math.random() * 255)
    imageData.data[i] = v
    imageData.data[i + 1] = v
    imageData.data[i + 2] = v
    imageData.data[i + 3] = 255
  }
  ctx.putImageData(imageData, 0, 0)
  return canvas.toDataURL('image/png')
}

/**
 * A subtle, flickering film-grain overlay across the whole hero — the
 * fine dust/noise texture visible in the reference capture. Pure CSS +
 * a canvas-generated noise tile, no video/image assets.
 */
export default function FilmGrain() {
  const noiseUrl = useMemo(() => createNoiseTile(), [])
  const elRef = useRef(null)

  useEffect(() => {
    const reduceMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches
    if (reduceMotion || !elRef.current) return

    let raf = null
    let last = 0
    const tick = (time) => {
      raf = requestAnimationFrame(tick)
      if (time - last < 65) return
      last = time
      if (elRef.current) {
        const x = Math.floor(Math.random() * 128)
        const y = Math.floor(Math.random() * 128)
        elRef.current.style.backgroundPosition = `${x}px ${y}px`
      }
    }
    raf = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(raf)
  }, [])

  return (
    <div
      ref={elRef}
      aria-hidden="true"
      className="pointer-events-none absolute inset-0 z-30"
      style={{
        backgroundImage: `url(${noiseUrl})`,
        backgroundRepeat: 'repeat',
        opacity: 0.05,
        mixBlendMode: 'overlay',
      }}
    />
  )
}
