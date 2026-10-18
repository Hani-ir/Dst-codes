import { useEffect, useRef, useState } from 'react'
import { AnimatePresence, motion } from 'framer-motion'
import ParticleField from './ParticleField.jsx'
import { useScrollStage } from '../hooks/useScrollStage.js'
import { STAGES } from '../data/stages.js'

const NAV_ITEMS = ['Products', 'Features', 'Technology', 'Support', 'Pricing']

function useIsTouch() {
  const [isTouch, setIsTouch] = useState(false)
  useEffect(() => {
    setIsTouch('ontouchstart' in window || navigator.maxTouchPoints > 0)
  }, [])
  return isTouch
}

export default function Hero() {
  const containerRef = useRef(null)
  const { progress, stageIndex, stageCount } = useScrollStage(containerRef)
  const isTouch = useIsTouch()
  const [menuOpen, setMenuOpen] = useState(false)

  // stageFloatRef is the single source of truth handed to the Three.js
  // layer: a plain ref (not state) so the 60fps morph loop never forces
  // a React re-render — only the text UI below re-renders, on stageIndex.
  const stageFloatRef = useRef(0)
  stageFloatRef.current = progress * stageCount

  const stage = STAGES[stageIndex]

  return (
    <div ref={containerRef} className="relative h-[400vh] bg-black">
      <div className="fixed inset-0 h-screen w-screen overflow-hidden bg-black">
        <ParticleField stageFloatRef={stageFloatRef} isTouch={isTouch} />

        {/* Top-left pinned label */}
        <div className="pointer-events-none absolute left-5 top-5 z-20 text-xs font-medium uppercase tracking-widest text-white/90 md:left-8 md:top-8 md:text-sm">
          The World
        </div>

        {/* Right-side vertical nav (desktop/tablet) */}
        <nav className="absolute right-6 top-1/2 z-20 hidden -translate-y-1/2 flex-col items-end gap-4 md:right-10 md:flex">
          {NAV_ITEMS.map((item) => (
            <a
              key={item}
              href="#"
              className="group flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-white/80 transition hover:text-white"
            >
              <span className="group-hover:underline">{item}</span>
              <span
                className="h-1.5 w-1.5 rounded-full transition-colors duration-700"
                style={{ backgroundColor: stage.color }}
              />
            </a>
          ))}
        </nav>

        {/* Mobile menu toggle */}
        <button
          type="button"
          aria-label="Toggle menu"
          onClick={() => setMenuOpen((v) => !v)}
          className="absolute right-5 top-5 z-20 flex h-9 w-9 flex-col items-center justify-center gap-1.5 md:hidden"
        >
          <span className="h-[1.5px] w-5 bg-white transition" />
          <span className="h-[1.5px] w-5 bg-white transition" />
          <span className="h-[1.5px] w-5 bg-white transition" />
        </button>

        <AnimatePresence>
          {menuOpen && (
            <motion.nav
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: -10 }}
              transition={{ duration: 0.2 }}
              className="absolute right-5 top-16 z-20 flex flex-col items-end gap-3 rounded-lg bg-white/5 px-4 py-4 backdrop-blur-sm md:hidden"
            >
              {NAV_ITEMS.map((item) => (
                <a
                  key={item}
                  href="#"
                  className="flex items-center gap-2 text-xs font-bold uppercase tracking-[0.2em] text-white/80"
                >
                  <span>{item}</span>
                  <span
                    className="h-1.5 w-1.5 rounded-full"
                    style={{ backgroundColor: stage.color }}
                  />
                </a>
              ))}
            </motion.nav>
          )}
        </AnimatePresence>

        {/* Per-stage content */}
        <div className="absolute inset-0 z-10 flex flex-col justify-end px-5 pb-10 md:px-10 md:pb-16">
          <div className="flex flex-col gap-6 md:flex-row md:items-end md:justify-between">
            <AnimatePresence>
              <motion.div
                key={`headline-${stage.key}`}
                initial={{ opacity: 0, y: 40 }}
                animate={{ opacity: 1, y: 0 }}
                exit={{ opacity: 0, y: -30 }}
                transition={{ duration: 0.6, ease: 'easeOut' }}
                className="max-w-3xl"
              >
                <h1
                  className="font-black leading-[0.95] text-white"
                  style={{ fontSize: 'clamp(2.5rem, 8vw, 7rem)' }}
                >
                  {stage.headline.map((line) => (
                    <span key={line} className="block">
                      {line}
                    </span>
                  ))}
                </h1>
                {stage.caption && (
                  <p className="mt-3 text-xs font-semibold uppercase tracking-[0.25em] text-white/50 md:text-sm">
                    {stage.caption}
                  </p>
                )}
              </motion.div>
            </AnimatePresence>

            <AnimatePresence>
              {stage.showDescription && stage.description && (
                <motion.p
                  key={`desc-${stage.key}`}
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: -15 }}
                  transition={{ duration: 0.6, ease: 'easeOut', delay: 0.1 }}
                  className="max-w-full text-left text-[13px] leading-relaxed text-white/80 md:max-w-[320px] md:text-right md:text-sm"
                >
                  {stage.description}
                </motion.p>
              )}
            </AnimatePresence>
          </div>
        </div>
      </div>
    </div>
  )
}
