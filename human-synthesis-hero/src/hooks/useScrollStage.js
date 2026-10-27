import { useEffect, useRef, useState } from 'react'
import { STAGES } from '../data/stages.js'

const STAGE_COUNT = STAGES.length

// How long to wait after the last scroll event before snapping to the
// nearest stage, and how close counts as "idle" for snap purposes.
const SNAP_IDLE_MS = 160

/**
 * Single source of truth for scroll progress.
 * Measures how far `containerRef` has scrolled through the viewport
 * and exposes both the global 0..1 progress and the per-stage
 * (index + local 0..1) breakdown, feeding the React UI and the
 * Three.js morph system from the same number.
 *
 * Also owns scroll-snapping: once the user stops scrolling, it eases
 * the real page scroll to the nearest stage boundary so the viewer
 * never settles mid-morph between two shapes.
 */
export function useScrollStage(containerRef) {
  const [progress, setProgress] = useState(0)
  const rafId = useRef(null)
  const snapTimerRef = useRef(null)

  useEffect(() => {
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches

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

    const snapToNearestStage = () => {
      const el = containerRef.current
      if (!el) return
      const scrollable = el.offsetHeight - window.innerHeight
      if (scrollable <= 0) return

      const rect = el.getBoundingClientRect()
      const scrolled = Math.min(1, Math.max(0, -rect.top / scrollable))
      const nearestIndex = Math.min(STAGE_COUNT - 1, Math.max(0, Math.round(scrolled * STAGE_COUNT)))
      const targetProgress = nearestIndex / STAGE_COUNT
      const targetScrollY = window.scrollY + (targetProgress - scrolled) * scrollable

      window.scrollTo({ top: targetScrollY, behavior: reducedMotion ? 'auto' : 'smooth' })
    }

    const onScroll = () => {
      if (rafId.current == null) {
        rafId.current = requestAnimationFrame(measure)
      }
      if (snapTimerRef.current) clearTimeout(snapTimerRef.current)
      snapTimerRef.current = setTimeout(snapToNearestStage, SNAP_IDLE_MS)
    }

    measure()
    window.addEventListener('scroll', onScroll, { passive: true })
    window.addEventListener('resize', onScroll)

    return () => {
      window.removeEventListener('scroll', onScroll)
      window.removeEventListener('resize', onScroll)
      if (rafId.current != null) cancelAnimationFrame(rafId.current)
      if (snapTimerRef.current) clearTimeout(snapTimerRef.current)
    }
  }, [containerRef])

  const stageFloat = progress * STAGE_COUNT
  const stageIndex = Math.min(STAGE_COUNT - 1, Math.floor(stageFloat))
  const stageProgress = stageFloat - stageIndex

  return { progress, stageIndex, stageProgress, stageCount: STAGE_COUNT }
}
