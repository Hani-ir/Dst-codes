import { useEffect, useRef, useState } from 'react'

const STAGE_COUNT = 4

/**
 * Single source of truth for scroll progress.
 * Measures how far `containerRef` has scrolled through the viewport
 * and exposes both the global 0..1 progress and the per-stage
 * (index + local 0..1) breakdown, feeding the React UI and the
 * Three.js morph system from the same number.
 */
export function useScrollStage(containerRef) {
  const [progress, setProgress] = useState(0)
  const rafId = useRef(null)

  useEffect(() => {
    const measure = () => {
      rafId.current = null
      const el = containerRef.current
      if (!el) return

      const rect = el.getBoundingClientRect()
      const scrollable = el.offsetHeight - window.innerHeight
      const scrolled = -rect.top

      const next = scrollable > 0 ? Math.min(1, Math.max(0, scrolled / scrollable)) : 0
      setProgress(next)
    }

    const onScroll = () => {
      if (rafId.current == null) {
        rafId.current = requestAnimationFrame(measure)
      }
    }

    measure()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)

    return () => {
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
      if (rafId.current != null) cancelAnimationFrame(rafId.current)
    }
  }, [containerRef])

  const stageFloat = progress * STAGE_COUNT
  const stageIndex = Math.min(STAGE_COUNT - 1, Math.floor(stageFloat))
  const stageProgress = stageFloat - stageIndex

  return { progress, stageIndex, stageProgress, stageCount: STAGE_COUNT }
}
