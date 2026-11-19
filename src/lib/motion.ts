/**
 * Motion plumbing: GSAP registration, Lenis smooth scroll and a single source of
 * truth for "is motion allowed here".
 *
 * `prefers-reduced-motion` is not treated as a hint to slow things down. When it
 * is set, Lenis never starts, nothing is pinned and nothing is scrubbed — the
 * page becomes an ordinary vertical document. Every chapter checks `canAnimate`
 * before building a timeline.
 */
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import { onBeforeUnmount, onMounted } from 'vue';

gsap.registerPlugin(ScrollTrigger);

export { gsap, ScrollTrigger };

const reduceQuery =
  typeof matchMedia === 'undefined' ? null : matchMedia('(prefers-reduced-motion: reduce)');

/** False when the visitor asked for reduced motion. Read at setup time. */
export const canAnimate = (): boolean => !reduceQuery?.matches;

/**
 * Where a chapter is allowed to hold still and be scrubbed by the scroll.
 *
 * Pinning converts the scroll position into the progress of an animation. It
 * is a good effect with a wheel, which arrives in discrete ticks the page can
 * answer one at a time, and a bad one under a thumb: the page stops where the
 * finger expects it to carry on, the gesture has to be learnt, and the scroll
 * events a phone sends while the page has momentum arrive in clumps, so the
 * scrubbed animation is drawn in steps rather than drawn smoothly. Every
 * pinned chapter therefore asks this first and offers an unpinned version of
 * itself to everything else.
 *
 * `pointer: fine` rather than a width alone, so a tablet held in landscape —
 * wide, but still a thumb — gets the unpinned version, and a touchscreen
 * laptop, whose primary pointer is still the mouse, does not.
 */
export const SCRUB_QUERY = '(min-width: 64rem) and (pointer: fine)';

/** Its complement, for the other half of a gsap.matchMedia pair. */
export const NO_SCRUB_QUERY = `not all and ${SCRUB_QUERY}`;

let lenis: Lenis | null = null;

export function startSmoothScroll(): void {
  if (!canAnimate() || lenis) return;

  lenis = new Lenis({
    duration: 1.05,
    // A gentle exponential ease; anything longer starts to feel like lag.
    easing: (t) => Math.min(1, 1.001 - 2 ** (-10 * t)),
    smoothWheel: true,
  });

  // Lenis drives the scroll position, so ScrollTrigger must be told to read it
  // from Lenis's RAF rather than from native scroll events.
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.add((time) => lenis?.raf(time * 1000));
  gsap.ticker.lagSmoothing(0);
}

export function stopSmoothScroll(): void {
  lenis?.destroy();
  lenis = null;
}

/**
 * How far above a section to stop, so the fixed bar does not sit on its heading.
 * Lenis drives the scroll itself and never reads `scroll-padding-top`, so the
 * two have to be kept in step by hand; the CSS value is in styles/index.css.
 */
const BAR_CLEARANCE = 96;

/**
 * Hold the page still while an overlay is open.
 *
 * Lenis drives the window scroll from wheel and touch events on the document,
 * so an overlay cannot simply rely on its own `overflow: auto`: on a phone the
 * gesture reaches Lenis and the page behind moves instead of the panel. Lenis
 * is therefore stopped outright and the document's own overflow is locked too,
 * for the case where Lenis never started (reduced motion).
 *
 * Counted rather than boolean: the project panel can be open over the chapter
 * menu, and the first one to close must not unlock the page under the second.
 */
let scrollLocks = 0;

export function lockPageScroll(): void {
  scrollLocks += 1;
  if (scrollLocks > 1) return;
  lenis?.stop();
  document.documentElement.style.overflow = 'hidden';
}

export function unlockPageScroll(): void {
  scrollLocks = Math.max(0, scrollLocks - 1);
  if (scrollLocks > 0) return;
  document.documentElement.style.overflow = '';
  lenis?.start();
}

export function scrollToId(id: string, onArrival?: () => void): void {
  const target = document.getElementById(id);
  if (!target) return;
  if (lenis) lenis.scrollTo(target, { offset: -BAR_CLEARANCE, onComplete: onArrival });
  else {
    target.scrollIntoView({ behavior: canAnimate() ? 'smooth' : 'auto' });
    // Native smooth scrolling reports nothing, so the callback waits out a
    // duration rather than an event. Reduced motion lands immediately.
    window.setTimeout(() => onArrival?.(), canAnimate() ? 600 : 0);
  }
}

/**
 * Go to the enquiry and put the cursor in it.
 *
 * Pressing "register interest" used to move the page and nothing else, and a
 * reader who presses a call to action and sees only the page slide is right
 * to wonder whether anything happened. Focusing the form's first question on
 * arrival makes the button open something, which is what it promises.
 *
 * `preventScroll` matters: focusing an element normally scrolls it into view,
 * which would undo the position the smooth scroll just settled on.
 *
 * Nothing calls this while registration is handled somewhere else: there is
 * no form in the epilogue then to put a cursor in. See register-link.ts.
 */
export function openEnquiry(): void {
  scrollToId('epilogue', () => {
    const first = document.querySelector<HTMLElement>(
      '.wizard input, .wizard select, .wizard textarea, .wizard button',
    );
    first?.focus({ preventScroll: true });
  });
}

/**
 * Build a GSAP timeline for one chapter and tear it down on unmount.
 *
 * The callback runs inside a gsap.context bound to the chapter root, so every
 * tween and ScrollTrigger it creates is reverted together — no stray triggers
 * left pinning a section that no longer exists.
 */
export function useChapterTimeline(
  root: () => HTMLElement | null | undefined,
  build: (ctx: { gsap: typeof gsap; root: HTMLElement }) => void,
): void {
  let context: gsap.Context | null = null;

  onMounted(() => {
    const el = root();
    if (!el || !canAnimate()) return;
    context = gsap.context(() => build({ gsap, root: el }), el);
  });

  onBeforeUnmount(() => {
    context?.revert();
    context = null;
  });
}

/** True when the browser can actually give us a WebGL context. */
export function hasWebGl(): boolean {
  try {
    const canvas = document.createElement('canvas');
    return Boolean(canvas.getContext('webgl2') ?? canvas.getContext('webgl'));
  } catch {
    return false;
  }
}

/** Run work when the browser is idle, with a timeout so it always runs. */
export function onIdle(task: () => void, timeout = 2500): void {
  if (typeof requestIdleCallback === 'function') requestIdleCallback(task, { timeout });
  else setTimeout(task, 300);
}
