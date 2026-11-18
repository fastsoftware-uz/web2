<script setup lang="ts">
/**
 * The forum's title card: the wordmark arrives a line of type at a time, the
 * date and place follow, and a photograph stands behind all of it.
 *
 * It used to be a turning globe — three.js, the world's coastlines, the Uzbek
 * border in gold — with a drawn still behind it wherever WebGL or motion was
 * unavailable. The client took both off on 15 November 2026, together with
 * the carved screen and the girih field this stage tiles itself with: with a
 * photograph behind the type, every one of them was a second drawing laid
 * over the first. The card is now the picture, the night wash that keeps the
 * type legible, and the words. The globe is still in site-2-classic, which is
 * a frozen snapshot and is not edited to follow a later decision.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import ActionIcon from '../components/ActionIcon.vue';
import ForumMark from '../components/ForumMark.vue';
import type { ForumHeroShot } from '../lib/forum-types';
import { canAnimate, scrollToId, useChapterTimeline } from '../lib/motion';
import { registerUrl } from '../lib/register-link';
import { eventDates, tr, useStore } from '../lib/state';

const { t } = useI18n();
const { forum } = useStore();
const root = ref<HTMLElement | null>(null);

const event = computed(() => forum.value?.event);
/** The forum's own registration page; see lib/register-link.ts. */
const registerHref = computed(() => registerUrl(forum.value));

/**
 * The pictures behind the title card.
 *
 * Uploaded from the admin and recorded as stems under `photos/`, exactly
 * like a project's pictures — so the venue can be changed, or a better frame
 * put up, without a build. Where there are none the card falls back to the
 * stage's own night gradient, which is what it is painted on in any case.
 *
 * A bare string is accepted as well as a list: that is the shape the file
 * had before the card turned between several pictures, and a server may
 * still be publishing one written by an older build.
 */
const shots = computed<ForumHeroShot[]>(() => {
  const hero = forum.value?.media?.hero;
  if (!hero) return [];
  const list = Array.isArray(hero) ? hero : [{ photo: hero }];
  return list.filter((shot) => typeof shot?.photo === 'string' && shot.photo !== '');
});

/**
 * Which one is on screen.
 *
 * The client asked on 15 November 2026 for all of the venue pictures rather
 * than a choice between them, so they come round in turn: nine seconds each,
 * a second and a half of cross-fade, and back to the first. Nine because the
 * card is the one screen a reader studies — the name, the date, two buttons —
 * and a background that changes while they are still reading it is a
 * distraction rather than a slideshow.
 *
 * Nothing turns for somebody who asked for reduced motion, or while the tab
 * is in the background, or when there is only one picture to show.
 */
const SHOWN_MS = 9000;
const current = ref(0);
/**
 * The second picture and the ones after it, held back until the first screen
 * is drawn.
 *
 * The title card is this page's Largest Contentful Paint and the first
 * picture is most of it. Three images in the markup are three requests the
 * browser starts at once over the same connection, and the two nobody can
 * see yet would be taking bandwidth from the one everybody can. So they are
 * added after the page has loaded — long before the first nine seconds are
 * up, and off the critical path entirely.
 */
const ready = ref(false);
/* Rendered, not merely hidden: an <img> with `display: none` is still
   fetched, so holding the others back means not writing them yet. */
const shown = computed(() => (ready.value ? shots.value : shots.value.slice(0, 1)));
let timer: number | undefined;

function turning(): boolean {
  return ready.value && shots.value.length > 1 && canAnimate() && !document.hidden;
}

function tick(): void {
  current.value = (current.value + 1) % shots.value.length;
}

function retime(): void {
  window.clearInterval(timer);
  timer = turning() ? window.setInterval(tick, SHOWN_MS) : undefined;
}

function wake(): void {
  retime();
}

onMounted(() => {
  const start = () => {
    ready.value = true;
    retime();
  };
  if (document.readyState === 'complete') window.setTimeout(start, 600);
  else window.addEventListener('load', () => window.setTimeout(start, 600), { once: true });
  document.addEventListener('visibilitychange', wake);
});

onBeforeUnmount(() => {
  window.clearInterval(timer);
  document.removeEventListener('visibilitychange', wake);
});

/* A picture removed from the admin while the page is open must not leave the
   card pointing past the end of the list. */
watch(shots, (list) => {
  if (current.value >= list.length) current.value = 0;
  retime();
});

/**
 * What the small line under the buttons says.
 *
 * It belongs to the picture that is showing, and changes with it: whoever
 * the photograph belongs to, if the organiser has said so, and otherwise —
 * while the card is carrying the image this repository ships — the fact that
 * it is an image and not a photograph of the venue, in the reader's own
 * language, because somebody deciding where to travel should not have to
 * guess which building they are looking at.
 */
const heroNote = computed(() => {
  const shot = shots.value[current.value];
  if (!shot) return '';
  if (shot.credit) return shot.credit;
  return shot.illustrative ? t('prologue.heroNote') : '';
});
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


useChapterTimeline(
  () => root.value,
  ({ gsap, root: el }) => {
    gsap.from(el.querySelectorAll('.prologue__reveal'), {
      y: 26,
      opacity: 0,
      duration: 0.9,
      stagger: 0.08,
      // After the wordmark's own lines have landed — see ForumMark.vue, whose
      // entrance this waits on and was shortened with.
      delay: 0.5,
      ease: 'power3.out',
    });
  },
);
</script>

<template>
  <section id="prologue" ref="root" class="chapter stage prologue">
    <div class="prologue__bg">
      <!-- The venue, if the organiser has put one up. `alt=""`: the card's
           name and date say what this is, and the photograph adds nothing a
           reader who cannot see it is missing.

           Three widths, and the middle one is the point: with only 320 and
           2200 on offer, a 412-pixel phone at three times the density asked
           for the 2200 and spent 175 kB on a picture it draws at a third of
           that. 1100 covers every phone and most laptops. -->
      <img
        v-for="(shot, i) in shown"
        :key="shot.photo"
        class="prologue__photo"
        :class="{ 'is-on': i === current }"
        :src="`photos/${shot.photo}-full.webp`"
        :srcset="
          `photos/${shot.photo}-thumb.webp 320w,` +
          `photos/${shot.photo}-card.webp 1100w,` +
          `photos/${shot.photo}-full.webp 2200w`
        "
        sizes="100vw"
        alt=""
        :fetchpriority="i === 0 ? 'high' : 'low'"
        decoding="async"
      />
      <!-- Night over the photograph, so the wordmark keeps its contrast
           whatever was uploaded. Decoration; announced as nothing. -->
      <div v-if="shots.length" class="prologue__scrim" aria-hidden="true" />
    </div>

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

        <!-- Registration is on the forum's own registration page, so this is
             a link and not a button: it opens in a new tab on a middle click
             like any other link, and the reader can see where it goes before
             pressing it. -->
        <a
          class="btn prologue__register"
          :href="registerHref"
          target="_blank"
          rel="noopener noreferrer"
        >
          <ActionIcon name="enquiry" />
          <span>{{ t('nav.register') }}</span>
          <ActionIcon name="right" class="prologue__onward" />
        </a>
      </div>

      <!-- The line belongs to whichever picture is showing, so it is swapped
           as they turn. `aria-live` is deliberately absent: it is a caption
           on decoration, and a reader who is being read the page should not
           be interrupted every nine seconds to be told so. -->
      <p v-if="heroNote" class="prologue__credit">{{ heroNote }}</p>

      <button type="button" class="prologue__scroll prologue__reveal" @click="scrollToId('programme')">
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
/**
 * The photograph, and everything that has to happen to it.
 *
 * It fills the card and is cropped from the centre, because the card is
 * whatever shape the window is and a building photographed in landscape has
 * to survive a phone held upright. Over it, one thing only: a wash that is
 * the stage's own night at the bottom, where the type sits, and almost clear
 * at the top, where the sky usually is.
 */
.prologue__photo {
  position: absolute;
  inset: 0;
  inline-size: 100%;
  block-size: 100%;
  object-fit: cover;
  object-position: 50% 55%;
  /* They are stacked, and the one whose turn it is fades up over the one
     before it. Opacity only — nothing moves, nothing is scaled — so the
     browser hands the whole thing to the compositor and the fade costs no
     layout. 1.6s because the pictures are of the same place from different
     sides, and a quick cut between them reads as a glitch. */
  opacity: 0;
  transition: opacity 1.6s ease-in-out;
}
.prologue__photo.is-on {
  opacity: 1;
}
/* Nothing fades for somebody who asked for reduced motion; the card holds
   the first picture, which is what the timer does as well. */
@media (prefers-reduced-motion: reduce) {
  .prologue__photo {
    transition: none;
  }
}
.prologue__scrim {
  position: absolute;
  inset: 0;
  background:
    linear-gradient(
      180deg,
      color-mix(in srgb, #0b1030 30%, transparent) 0%,
      color-mix(in srgb, #0b1030 52%, transparent) 46%,
      color-mix(in srgb, #0b1030 82%, transparent) 100%
    ),
    /* A little more night on the side the type runs down. */
      linear-gradient(90deg, color-mix(in srgb, #0b1030 55%, transparent) 0%, transparent 65%);
}
/* The stage tiles itself with a girih field — see .stage::before in
   styles/index.css. Every other chapter on cobalt wants it; this one has a
   photograph, and a lattice over a photograph is a lattice in front of a
   building. */
.prologue::before {
  content: none;
}

/** Whose photograph it is. Small, under everything, and never in the way. */
.prologue__credit {
  margin-block-start: 1.75rem;
  font-size: 0.7rem;
  letter-spacing: 0.08em;
  color: var(--ink-dim);
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
