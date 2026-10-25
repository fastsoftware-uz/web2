<script setup lang="ts">
/**
 * The forum's title card: the wordmark arrives a line of type at a time, the
 * date and place follow, and a generated globe turns behind it. Without WebGL
 * or with reduced motion the globe is replaced by a static arc graphic — the
 * chapter still reads.
 */
import { computed, defineAsyncComponent, onMounted, ref, shallowRef } from 'vue';
import { useI18n } from 'vue-i18n';

import ActionIcon from '../components/ActionIcon.vue';
import ForumMark from '../components/ForumMark.vue';
import { canAnimate, hasWebGl, onIdle, scrollToId, useChapterTimeline } from '../lib/motion';
// The border, flattened at the attitude the globe is aimed at; built by
// scripts/build-globe.mjs and inlined here rather than fetched, because a
// fallback that waits for a request is not a fallback.
import uzStill from '../assets/uz-still.json';
import { eventDates, tr, useStore } from '../lib/state';

const { t } = useI18n();
const { forum } = useStore();
const root = ref<HTMLElement | null>(null);

const Globe = shallowRef<ReturnType<typeof defineAsyncComponent> | null>(null);

const event = computed(() => forum.value?.event);
const dates = computed(() => eventDates());

/**
 * How many projects are waiting four screens down.
 *
 * On the title card the portfolio was a button that said what it would do and
 * nothing about what was behind it, so there was no reason to press it. The
 * number is the reason, and it comes from the data rather than from the copy,
 * so it is right on the day the organiser sends another batch of sheets.
 */
const projectCount = computed(() => forum.value?.portfolio.projects.length ?? 0);

onMounted(() => {
  if (!canAnimate() || !hasWebGl()) return;
  // three.js is ~450 kB; it waits for an idle moment and never blocks the text.
  onIdle(() => {
    Globe.value = defineAsyncComponent(() => import('../components/HeroGlobe.vue'));
  });
});

useChapterTimeline(
  () => root.value,
  ({ gsap, root: el }) => {
    gsap.from(el.querySelectorAll('.prologue__reveal'), {
      y: 26,
      opacity: 0,
      duration: 0.9,
      stagger: 0.1,
      // After the wordmark's own three lines have landed.
      delay: 0.85,
      ease: 'power3.out',
    });
  },
);
</script>

<template>
  <section id="prologue" ref="root" class="chapter stage prologue">
    <div class="prologue__bg">
      <component :is="Globe" v-if="Globe" />
      <!-- Fallback and under-layer: generated arcs, no asset, always present. -->
      <svg v-else class="prologue__arcs" viewBox="0 0 800 800" aria-hidden="true">
        <defs>
          <radialGradient id="prologue-halo" cx="50%" cy="50%" r="50%">
            <stop offset="0%" stop-color="#E4BB7D" stop-opacity="0.26" />
            <stop offset="60%" stop-color="#C18429" stop-opacity="0.1" />
            <stop offset="100%" stop-color="#101538" stop-opacity="0" />
          </radialGradient>
        </defs>
        <circle cx="400" cy="400" r="400" fill="url(#prologue-halo)" />
        <g fill="none" stroke="#D2A156" stroke-opacity="0.2">
          <circle cx="400" cy="400" r="250" />
          <circle cx="400" cy="400" r="180" />
          <ellipse cx="400" cy="400" rx="250" ry="90" />
          <ellipse cx="400" cy="400" rx="250" ry="170" />
          <ellipse cx="400" cy="400" rx="90" ry="250" />
          <ellipse cx="400" cy="400" rx="170" ry="250" />
        </g>
        <!-- Uzbekistan, on the still as on the globe. Somebody who has asked
             for less motion, or whose browser has no WebGL, should still be
             shown the country the page is about rather than a halo. -->
        <path :d="uzStill.d" fill="none" stroke="#E4BB7D" stroke-opacity="0.9" stroke-width="2.5" />
        <circle :cx="uzStill.mark.x" :cy="uzStill.mark.y" r="4" fill="#E4BB7D" />
      </svg>
    </div>

    <!-- The carved screen the globe is seen through. Decoration, announced as
         nothing; see .aperture in styles/index.css. -->
    <div class="aperture" aria-hidden="true" />

    <div class="shell prologue__content">
      <p class="eyebrow prologue__organiser">{{ t('prologue.organiser') }}</p>
      <!-- The national colours, under the name of the State body running the
           forum. Decoration, and announced as nothing. -->
      <div class="flag-rule prologue__flag" aria-hidden="true" />

      <h1 class="prologue__mark">
        <span class="sr-only">{{ event ? tr(event.name) : '' }}</span>
        <!-- The forum's name is "First International Tourism Investment
             Forum"; the supplied artwork carries only the last four words, so
             the card was announcing a forum that has been held before. The two
             missing words are now part of the lockup itself, set in the
             typeface the artwork is set in and fitted to its measure, so the
             name reads as one inscription — see scripts/lib/forum-title.mjs.
             They stay in English in every language, because what they are part
             of is a logotype. The accessible name is the whole thing, in the
             reader's language. -->
        <ForumMark form="lockup" animate drift aria-hidden="true" />
      </h1>

      <!-- The title card carries the name, the date and the two things you
           can do. The line about the trade fair, the motto and the lead
           paragraph came off at the client's request: on the one screen
           everybody sees, four blocks of prose between the date and the
           buttons is three blocks too many. The motto is the organiser's own
           and is still in the data; nothing was deleted from the record. -->
      <p v-if="event" class="prologue__when prologue__reveal">
        <span class="prologue__dates">{{ dates }}</span>
        <span class="prologue__dot" aria-hidden="true">·</span>
        <span>{{ tr(event.city) }}</span>
      </p>

      <!--
        Two actions, at the client's asking on 25 October 2026.

        It was one for a while, and for a reason: a second button competes
        with the one thing most readers have come to do. What changed is that
        registration opened — somebody who already knows they are coming
        should not have to read a chapter to find the form. So the portfolio
        keeps the solid gold and registration stands beside it outlined,
        which reads as a first and a second rather than as a choice between
        equals.
      -->
      <div class="prologue__actions prologue__reveal">
        <!-- No aria-label: an accessible name that does not contain the
             visible words is a name speech-input users cannot say. The count
             is visible, so it is part of the name, and only the unit word is
             hidden. -->
        <button type="button" class="btn btn--solid prologue__go" @click="scrollToId('portfolio')">
          <ActionIcon name="portfolio" />
          <span>{{ t('prologue.cta') }}</span>
          <!-- The count, and then the arrow that says where pressing it goes.
               Both are inside the button so the eye lands on one object. -->
          <template v-if="projectCount">
            <span class="prologue__count">{{ projectCount }}</span>
            <span class="sr-only">{{ t('prologue.ctaUnit') }}</span>
          </template>
          <ActionIcon name="down" class="prologue__nudge" />
        </button>

        <button type="button" class="btn prologue__register" @click="scrollToId('epilogue')">
          <ActionIcon name="enquiry" />
          <span>{{ t('nav.register') }}</span>
          <ActionIcon name="right" class="prologue__onward" />
        </button>
      </div>

      <button type="button" class="prologue__scroll prologue__reveal" @click="scrollToId('crossroads')">
        <span>{{ t('scrollHint') }}</span>
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
          <path d="M12 4v15m0 0-6-6m6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.8" />
        </svg>
      </button>
    </div>

    <!-- The gold seam where the stage gives way to the page. Decoration,
         and announced as nothing. -->
    <div class="frieze frieze--edge frieze--bottom" aria-hidden="true" />
  </section>
</template>

<style scoped>
/* The header is fixed and, at 360px, about 86px tall. The chapter's default
   padding is not enough to clear it, so the title card sets its own. */
.prologue {
  padding-block-start: clamp(7rem, 16vh, 9rem);
}

.prologue__bg {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  pointer-events: none;
}
.prologue__arcs {
  width: min(120vh, 110vw);
  height: auto;
  opacity: 0.9;
}

.prologue__content {
  position: relative;
  z-index: 2;
  max-width: 46rem;
}

.prologue__flag {
  inline-size: 3.25rem;
  margin-block-start: 0.85rem;
}

.prologue__organiser {
  /* `--forum-sand` and `--forum-mid` were never defined anywhere: the
     declarations were invalid and the colour simply inherited. The forum's
     gold is --gold / --gold-leaf. */
  color: var(--gold);
}

.prologue__mark {
  margin-block: 1.5rem 0;
  /* The lockup is 1.59:1 now that its five lines are all one size and the
     emblem stands beside them rather than over them — wider and shorter than
     it was, so it can take more of the column without growing taller. */
  width: min(92%, 29rem);
}
.prologue__when {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.6rem;
  margin-block-start: 2rem;
  font-family: var(--font-display);
  font-size: clamp(1.3rem, 3vw, 2rem);
  color: var(--ink);
}
.prologue__dot {
  color: var(--gold-leaf);
}

/* The buttons follow the date directly now, so the gap under it is the one
   that used to sit under the lead paragraph: the card still breathes, it
   just has nothing in between to say. */
.prologue__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-block-start: 2.75rem;
}

/**
 * The count the portfolio button carries.
 *
 * A pill rather than another word: it has to be read as a quantity at a
 * glance, by somebody who is not reading the button at all. Drawn out of the
 * button's own ink at low opacity, so it belongs to the gold rather than
 * sitting on top of it.
 */
.prologue__count {
  display: inline-grid;
  place-items: center;
  min-inline-size: 1.9em;
  padding-inline: 0.45em;
  margin-inline-start: 0.1em;
  border-radius: 999px;
  background: color-mix(in srgb, currentColor 16%, transparent);
  font-variant-numeric: tabular-nums;
  font-weight: 700;
  line-height: 1.7;
}

/**
 * Registration, beside the portfolio.
 *
 * Two things move on it, and both say the same thing twice over: the arrow
 * leans the way the button goes, the way every arrow on the site does, and
 * once in a while a line of gold crosses the face of it — the same gold as
 * the seams between the chapters, at the speed of something catching the
 * light rather than something demanding attention. Both stop under
 * `prefers-reduced-motion`, with every other animation on the page.
 */
.prologue__register {
  position: relative;
  overflow: hidden;
}
.prologue__register::before {
  content: '';
  position: absolute;
  inset-block: 0;
  inset-inline-start: -45%;
  inline-size: 34%;
  /**
   * A gleam, not a grey bar.
   *
   * Two things were wrong with the obvious way of writing this. `color-mix`
   * with `transparent` mixes a colour with transparent *black*, which drains
   * it; and gold at 40% over navy composites to grey whatever the colour,
   * because the two are that far apart. So the band is near-opaque at its
   * centre and it is screened over the button, which adds light instead of
   * averaging it — the way a sheen behaves on a lit surface.
   *
   * It is ::before because every .btn already has an ::after: the keyline
   * inset three pixels inside it, at 18% opacity. Hanging the gleam on the
   * same pseudo-element inherited that opacity and that border, and the
   * sweep came out as a grey rectangle with two hard edges.
   */
  background: linear-gradient(
    100deg,
    rgb(228 187 125 / 0) 0%,
    rgb(240 206 152 / 0.78) 48%,
    rgb(240 206 152 / 0.78) 52%,
    rgb(228 187 125 / 0) 100%
  );
  mix-blend-mode: screen;
  animation: sheen 7s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  pointer-events: none;
}
.prologue__register .prologue__onward {
  animation: onward 2.4s ease-in-out infinite;
}
.prologue__register:hover .prologue__onward,
.prologue__register:focus-visible .prologue__onward {
  animation-play-state: paused;
}
/* Nothing crosses a button somebody is already pressing. */
.prologue__register:hover::before,
.prologue__register:focus-visible::before {
  animation-play-state: paused;
  opacity: 0;
}

/* The arrow keeps nodding at the page below, the same cue the scroll hint
   gives, because the button's whole promise is that there is more down
   there. It stops under `prefers-reduced-motion`, with everything else. */
.prologue__go .prologue__nudge {
  animation: nudge 2.4s ease-in-out infinite;
}
.prologue__go:hover .prologue__nudge,
.prologue__go:focus-visible .prologue__nudge {
  animation-play-state: paused;
  translate: 0 3px;
  transition: translate 0.2s;
}

.prologue__scroll {
  display: inline-flex;
  align-items: center;
  min-block-size: var(--tap);
  gap: 0.6rem;
  margin-block-start: 3rem;
  padding: 0.6rem 0;
  border: 0;
  background: transparent;
  color: var(--ink-dim);
  font: inherit;
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  cursor: pointer;
  transition: color 0.2s;
}
@media (hover: hover) {
  .prologue__scroll:hover {
    color: var(--ink);
  }
}
.prologue__scroll svg {
  animation: nudge 2.4s ease-in-out infinite;
}

@keyframes sheen {
  0% {
    translate: 0;
  }
  16% {
    translate: 400%;
  }
  100% {
    translate: 400%;
  }
}

@keyframes onward {
  0%,
  100% {
    transform: translateX(0);
  }
  50% {
    transform: translateX(3px);
  }
}

@keyframes nudge {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(4px);
  }
}
</style>
