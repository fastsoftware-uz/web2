<script setup lang="ts">
/**
 * Everything that floats above the chapters: the logo, the top navigation, the
 * reading progress bar, the vertical chapter number and the full-screen menu.
 *
 * The navigation follows the model used on the forum's own site — a sticky bar
 * carrying section links, a language dropdown and one primary call to action,
 * collapsing to a single button on narrow screens. It is rebuilt in this site's
 * own language rather than copied: the links point at this page's chapters, the
 * palette is the forum gold on night, and the button that replaces the links
 * opens the full-screen chapter list this site already had rather than an
 * accordion under the bar.
 *
 * The bar shows five sections. All seven chapters stay in the overlay, which is
 * also the only navigation below 68rem.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import ActionIcon from './ActionIcon.vue';
import ForumMark from './ForumMark.vue';
import { lockPageScroll, openEnquiry, scrollToId, unlockPageScroll } from '../lib/motion';
import { registerHref as registerAddress, registersElsewhere } from '../lib/register-link';
import { setLang, useStore } from '../lib/state';
import { LANGS, type Lang } from '../lib/shared/content-types';

const props = defineProps<{ chapters: { id: string; key: string }[] }>();

const { t } = useI18n();
const { lang, forum } = useStore();

/** The sections the bar links to, and the chapter each one lands on. */
const NAV = [
  { key: 'programme', id: 'programme' },
  { key: 'speakers', id: 'speakers' },
  { key: 'themes', id: 'themes' },
  { key: 'projects', id: 'portfolio' },
  { key: 'contacts', id: 'epilogue' },
] as const;

const LANG_NAMES: Record<Lang, string> = {
  uz: 'Oʻzbekcha',
  ru: 'Русский',
  en: 'English',
};

const progress = ref(0);
/** Past the title card the bar sits over body copy and needs a real backdrop. */
const scrolled = ref(false);
const activeIndex = ref(0);
const menuOpen = ref(false);
const langOpen = ref(false);

const activeId = computed(() => props.chapters[activeIndex.value]?.id);

/**
 * The page is paper except for the two lit stages it opens and closes on, and
 * the bar floats over both. It therefore carries whichever token set the
 * chapter under it uses — and the mark swaps with it, because the white lockup
 * disappears on paper and the dark one disappears on a stage.
 */
const STAGES = new Set(['prologue', 'epilogue']);
const onStage = computed(() => STAGES.has(activeId.value ?? ''));

/**
 * The bar's one call to action, which changes with where the reader is.
 *
 * It used to say "register your interest" from the first screen to the last,
 * which is the wrong thing to offer somebody who has not yet seen what there
 * is to be interested in — and on a phone, where the section links collapse
 * into the menu, it was the *only* thing on the bar, so the seventy-four
 * projects had no visible way in at all. A visitor told us they could not
 * find them.
 *
 * So before the portfolio the bar offers the portfolio, and from the
 * portfolio onwards it offers registration. One button, always the next step.
 *
 * It scrolls to the last chapter rather than opening the registration page:
 * the reader is already most of the way down the page, and the chapter
 * carries the same form. The page is a click away in the bar for anybody who
 * arrived to register and nothing else. When registration is somewhere else
 * there is no form to scroll to, so it becomes a link instead — one a reader
 * can see coming, middle-click, and open in a tab.
 */
const PORTFOLIO_INDEX = computed(() => props.chapters.findIndex((c) => c.id === 'portfolio'));
const seenPortfolio = computed(
  () => PORTFOLIO_INDEX.value !== -1 && activeIndex.value >= PORTFOLIO_INDEX.value,
);
const barAction = computed(() =>
  seenPortfolio.value
    ? {
        id: 'epilogue',
        key: 'nav.register',
        short: 'nav.register',
        icon: 'enquiry',
        /* While the form is in the last chapter, the bar scrolls to it;
           when registration is elsewhere, it is a link out. */
        href: away.value ? registerHref.value : null,
      }
    : {
        id: 'portfolio',
        key: 'nav.ctaProjects',
        short: 'nav.ctaProjects',
        icon: 'portfolio',
        href: null,
      },
);

/**
 * Scroll handling is split in two so neither part measures layout per event.
 *
 * The progress bar needs the document height, which is a layout read, so it is
 * read once per resize and cached. The active chapter used to cost one
 * getBoundingClientRect per chapter per scroll event — eight forced reflows
 * every frame — and is now an IntersectionObserver.
 */
let maxScroll = 0;
let ticking = false;

function measure() {
  maxScroll = document.documentElement.scrollHeight - window.innerHeight;
  updateProgress();
}

function updateProgress() {
  progress.value = maxScroll > 0 ? Math.min(1, window.scrollY / maxScroll) : 0;
  /*
   * 8px, not 120.
   *
   * The bar floats over the title card on a scrim, which is right while the
   * page is at rest: the photograph runs under it and nothing is hidden.
   * The moment the page moves, though, the card's own type travels up
   * underneath a bar that is still see-through, and the organiser's line
   * was being drawn straight through the section links. A scrim cannot fix
   * that — it is translucent by definition — so the bar takes its own
   * surface as soon as there is anything behind it to cover.
   */
  scrolled.value = window.scrollY > 8;
}

function onScroll() {
  if (ticking) return;
  ticking = true;
  requestAnimationFrame(() => {
    updateProgress();
    ticking = false;
  });
}

let chapterObserver: IntersectionObserver | null = null;

function watchChapters() {
  chapterObserver?.disconnect();
  // A band across the upper middle of the viewport: whichever chapter is
  // crossing it is the one being read.
  chapterObserver = new IntersectionObserver(
    (entries) => {
      for (const entry of entries) {
        if (!entry.isIntersecting) continue;
        const index = props.chapters.findIndex((c) => c.id === entry.target.id);
        if (index !== -1) activeIndex.value = index;
      }
    },
    { rootMargin: '-40% 0px -55% 0px', threshold: 0 },
  );
  for (const chapter of props.chapters) {
    const el = document.getElementById(chapter.id);
    if (el) chapterObserver.observe(el);
  }
}

function onKey(event: KeyboardEvent) {
  if (event.key !== 'Escape') return;
  if (langOpen.value) {
    event.preventDefault();
    langOpen.value = false;
  } else if (menuOpen.value) {
    event.preventDefault();
    menuOpen.value = false;
  }
}

/** A dropdown that only closes on its own button is a trap; close on any outside press. */
function onPointerDown(event: Event) {
  if (!langOpen.value) return;
  const target = event.target as HTMLElement | null;
  if (!target?.closest('.langs')) langOpen.value = false;
}

// The overlay covers the page and scrolls itself on a short screen, so the
// page behind it is held still while it is open.
watch(menuOpen, (open) => (open ? lockPageScroll() : unlockPageScroll()));

onMounted(() => {
  measure();
  watchChapters();
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', measure);
  document.addEventListener('keydown', onKey);
  document.addEventListener('pointerdown', onPointerDown);
});

onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('resize', measure);
  document.removeEventListener('keydown', onKey);
  document.removeEventListener('pointerdown', onPointerDown);
  chapterObserver?.disconnect();
  chapterObserver = null;
  if (menuOpen.value) unlockPageScroll();
});

/**
 * Where "Roʻyxatdan oʻtish" goes.
 *
 * This site's own page, carrying the language the reader is using — unless
 * the data file says registration has moved, in which case it is somebody
 * else's address and the language goes with the reader rather than with the
 * link. See lib/register-link.ts.
 */
const registerHref = computed(() => registerAddress(forum.value, lang.value));

/**
 * The organiser's information pack, as shipped in public/.
 *
 * A file rather than a page, and the same file in every language: it is the
 * committee's own English document and there is no translated edition of it
 * to point at.
 */
const INFO_PDF = './Information_TITF_ITIF_2026.pdf';
/** A link that leaves the site opens in a tab and says so; ours does not. */
const away = computed(() => registersElsewhere(forum.value));


function go(id: string) {
  menuOpen.value = false;
  // The enquiry is the one destination that is a form rather than a reading,
  // so arriving there means putting the cursor in it.
  if (id === 'epilogue') openEnquiry();
  else scrollToId(id);
}

function choose(code: Lang) {
  setLang(code);
  langOpen.value = false;
}
</script>

<template>
  <div
    class="progress"
    role="progressbar"
    :aria-label="t('progress')"
    :aria-valuenow="Math.round(progress * 100)"
    aria-valuemin="0"
    aria-valuemax="100"
  >
    <span :style="{ transform: `scaleX(${progress})` }" />
  </div>

  <header class="bar" :class="{ 'bar--solid': scrolled, 'on-stage': onStage }">
    <a
      href="#prologue"
      class="logo"
      aria-label="Tourism Investment Forum 2026"
      @click.prevent="go('prologue')"
    >
      <!-- The link carries the name; the mark inside it must not repeat it. -->
      <ForumMark
        form="compact"
        :variant="onStage ? 'white' : 'dark'"
        class="mark--by-height"
        aria-hidden="true"
      />
    </a>

    <!-- Named apart from the overlay's nav: two navigation landmarks with the
         same name are indistinguishable when they are listed. -->
    <nav class="nav" :aria-label="t('menu.sections')">
      <a
        v-for="item in NAV"
        :key="item.key"
        :href="`#${item.id}`"
        :aria-current="activeId === item.id ? 'true' : undefined"
        @click.prevent="go(item.id)"
      >
        {{ t(`nav.${item.key}`) }}
      </a>
    </nav>

    <div class="bar__right">
      <!-- The organiser's own information pack: the invitation, the
           programme, the venue and how to get there, ten pages of it. It
           sits first in the bar because it is the thing a guest who has
           just been invited opens, and it is a link to a file rather than a
           page on this site, so it opens in a tab of its own and says so in
           its accessible name. Relative, like every other address here: the
           site is served from / on the organiser's server and from /web2/
           on Pages. -->
      <a
        class="info-link"
        :href="INFO_PDF"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="t('nav.infoAria')"
      >{{ t('nav.info') }}</a>

      <div class="langs">
        <button
          type="button"
          class="langs__toggle"
          :aria-expanded="langOpen"
          :aria-label="`${t('menu.language')}: ${lang}`"
          @click="langOpen = !langOpen"
        >
          <span>{{ lang }}</span>
          <svg viewBox="0 0 12 8" width="10" height="7" aria-hidden="true">
            <path d="M1 1l5 5 5-5" fill="none" stroke="currentColor" stroke-width="1.6" />
          </svg>
        </button>
        <ul v-if="langOpen" class="langs__menu">
          <li v-for="code in LANGS" :key="code">
            <button type="button" :aria-current="lang === code ? 'true' : undefined" @click="choose(code)">
              {{ LANG_NAMES[code] }}
            </button>
          </li>
        </ul>
      </div>

      <!-- Registration. A link rather than a button because it goes to
           another page, which means it opens in a new tab on a middle click
           like every other link, and still works if the script never ran.
           Hidden below 48rem, where the bar is already carrying the language,
           the contextual call to action and the menu — it is the first item
           in the menu at that width.

           And hidden once the call to action beside it has itself become
           registration: from the portfolio onwards the two were the same
           offer twice. Before that point this is the quick way out to the
           registration page for somebody who came to register and not to
           read. -->
      <a
        v-if="!seenPortfolio"
        class="reg-link"
        :href="registerHref"
        :target="away ? '_blank' : undefined"
        :rel="away ? 'noopener noreferrer' : undefined"
      >{{ t('nav.register') }}</a>

      <!-- Two labels, one shown at a time: "Qiziqishni bildirish" is 159px
           wide and the bar runs out of room for it below about 370px, where
           a short form says the same thing beside the same icon. -->
      <a
        v-if="barAction.href"
        class="bar__cta"
        :href="barAction.href"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="t(barAction.key)"
      >
        <ActionIcon :name="barAction.icon" />
        <span class="bar__ctaLong" aria-hidden="true">{{ t(barAction.key) }}</span>
        <span class="bar__ctaShort" aria-hidden="true">{{ t(barAction.short) }}</span>
      </a>
      <button v-else type="button" class="bar__cta" :aria-label="t(barAction.key)" @click="go(barAction.id)">
        <ActionIcon :name="barAction.icon" />
        <span class="bar__ctaLong" aria-hidden="true">{{ t(barAction.key) }}</span>
        <span class="bar__ctaShort" aria-hidden="true">{{ t(barAction.short) }}</span>
      </button>

      <button type="button" class="menu-toggle" :aria-expanded="menuOpen" @click="menuOpen = true">
        <span class="menu-toggle__text">{{ t('menu.open') }}</span>
        <svg class="menu-toggle__icon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
          <path
            d="M4 7h16M4 12h16M4 17h16"
            fill="none"
            stroke="currentColor"
            stroke-width="1.8"
            stroke-linecap="round"
          />
        </svg>
      </button>
    </div>
  </header>

  <!-- The chapter rail. It carries the stage's tokens over a stage, like the
       bar, or its stars disappear into the cobalt. -->
  <nav
    class="rail"
    :class="{ 'on-stage': onStage }"
    :style="{ '--read': progress }"
    :aria-label="t('menu.rail')"
  >
    <ol>
      <li v-for="(chapter, i) in chapters" :key="chapter.id">
        <button
          type="button"
          class="rail__node"
          :class="{ 'rail__node--read': i < activeIndex }"
          :aria-current="activeIndex === i ? 'true' : undefined"
          @click="go(chapter.id)"
        >
          <span class="rail__star" aria-hidden="true" />
          <!-- The button's accessible name, and the tooltip. Faded out rather
               than hidden, so it is still the name when nobody is hovering. -->
          <span class="rail__label">{{ t(`chapters.${chapter.key}.title`) }}</span>
        </button>
      </li>
    </ol>
  </nav>

  <Teleport to="body">
    <!-- data-lenis-prevent: the list can outgrow a short screen and has to
         scroll itself, which the smooth-scroll driver would otherwise take. -->
    <div v-if="menuOpen" class="overlay" data-lenis-prevent>
      <button type="button" class="overlay__scrim" :aria-label="t('menu.close')" @click="menuOpen = false" />
      <nav class="overlay__inner" :aria-label="t('menu.label')">
        <button type="button" class="overlay__close" @click="menuOpen = false">
          {{ t('menu.close') }}
        </button>
        <p class="overlay__register">
          <a
            class="btn btn--solid"
            :href="registerHref"
            :target="away ? '_blank' : undefined"
            :rel="away ? 'noopener noreferrer' : undefined"
          >
            {{ t('nav.register') }}
          </a>
          <!-- The bar hides the information pack below 48rem; this is where
               it goes instead, beside registration. -->
          <a
            class="btn"
            :href="INFO_PDF"
            target="_blank"
            rel="noopener noreferrer"
            :aria-label="t('nav.infoAria')"
          >
            {{ t('nav.info') }}
          </a>
        </p>
        <ol>
          <li v-for="(chapter, i) in chapters" :key="chapter.id">
            <button type="button" :aria-current="activeIndex === i ? 'true' : undefined" @click="go(chapter.id)">
              <span class="overlay__n">{{ String(i + 1).padStart(2, '0') }}</span>
              <span class="overlay__label">
                <small>{{ t(`chapters.${chapter.key}.n`) }}</small>
                {{ t(`chapters.${chapter.key}.title`) }}
              </span>
            </button>
          </li>
        </ol>
      </nav>
    </div>
  </Teleport>
</template>

<style scoped>
/* Reading progress.
 *
 * It was a run of jiyak for an hour — the braid is the page's own ornament
 * and weaving it across the top as the page is read is the right idea. At
 * three pixels it is also unreadable: the braid's two cobalt rules and its
 * chevrons average out to a grey bar. A thread that cannot be seen to be a
 * thread is just a dirty line, so this stays the hairline and the gradient. */
.progress {
  position: fixed;
  inset-block-start: 0;
  inset-inline: 0;
  z-index: 60;
  height: 2px;
  background: var(--hairline);
}
.progress > span {
  display: block;
  height: 100%;
  background: var(--glow);
  transform-origin: 0 50%;
  will-change: transform;
}

.bar {
  position: fixed;
  inset-block-start: 0;
  inset-inline: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: clamp(0.75rem, 3vw, 2.5rem);
  padding-block: 0.9rem;
  /* Clears a notch when the phone is on its side. */
  padding-inline-start: max(clamp(1rem, 3vw, 2.5rem), env(safe-area-inset-left));
  padding-inline-end: max(clamp(1rem, 3vw, 2.5rem), env(safe-area-inset-right));
  /* Both states are mixed from --surface, so the bar follows the chapter it is
     over without either colour being written down twice. */
  background: linear-gradient(
    to bottom,
    color-mix(in srgb, var(--surface) 85%, transparent),
    transparent
  );
  transition: background-color 0.25s, border-color 0.25s;
}
/* Over the title card a gradient scrim is enough. Below it the bar crosses
   running text, so it takes a surface of its own — opaque, because a
   translucent white bar picks up whichever chapter is passing beneath and
   reads as a dirty grey band rather than as paper. */
.bar--solid {
  background: var(--surface);
  border-block-end: 1px solid var(--hairline);
  /* The gold hairline a tiled panel is finished with, at the one place on the
     page where the page is cut across. */
  box-shadow: 0 1px 0 color-mix(in srgb, var(--gold-leaf) 35%, transparent);
}

.logo {
  /* The lockup is 1.33:1 — nearly square — so in a bar it is sized by height,
     not by width, or it pushes the bar open on small screens. */
  display: block;
  flex: 0 0 auto;
  /**
   * Sized against the section links beside it, not against the bar.
   *
   * At 4rem the mark set its own three lines at about 11px while the links
   * next to them were 17px, and the emblem over them came out at 28px — a
   * mark that reads as a caption to the navigation rather than as the thing
   * the navigation hangs off. At 5rem the type lands near the links' size and
   * the emblem is half as big again. The floor is still low, because at
   * 280px the bar has to carry the mark, the language button and the menu
   * button between the edges of the screen.
   */
  height: clamp(2.75rem, 7vw, 5rem);
}

/* The section links. Hidden below 68rem, where the overlay takes over — the
   same trade the forum's own bar makes at its `lg` breakpoint. */
.nav {
  display: none;
  align-items: center;
  gap: clamp(1rem, 2.2vw, 2rem);
  min-width: 0;
}
.nav a {
  position: relative;
  padding-block: 0.4rem;
  /* Bigger and gold, at the client's asking. --gold is the one gold in each
     token set that may carry text: #995f00 on ganch and #dfae63 on the
     stage, both 4.5:1, so the bar reads the same on the title card and over
     the paper chapters without either failing. */
  font-size: 0.95rem;
  letter-spacing: 0.005em;
  color: var(--gold);
  text-decoration: none;
  white-space: nowrap;
  transition: color 0.18s;
}
.nav a::after {
  content: '';
  position: absolute;
  inset-block-end: 0;
  inset-inline: 0;
  height: 1px;
  background: var(--gold-leaf);
  transform: scaleX(0);
  transform-origin: 0 50%;
  transition: transform 0.22s;
}
@media (hover: hover) {
  .nav a:hover {
    color: var(--gold-leaf);
  }
}
.nav a[aria-current='true']::after {
  transform: scaleX(1);
}
@media (hover: hover) {
  .nav a:hover::after {
    transform: scaleX(1);
  }
}
.nav a[aria-current='true'] {
  color: var(--gold-leaf);
}
/* A tablet held in landscape gets the bar's links and no mouse. The padding
   grows rather than the box, so the underline stays on the word. */
@media (pointer: coarse) {
  .nav a {
    padding-block: 0.85rem;
  }
}

.bar__right {
  display: flex;
  align-items: center;
  gap: 0.6rem;
}

/* Outlined, not gold: the bar already has one gold button, and two of them
   side by side is two primary actions, which is none. */
.reg-link {
  display: none;
  align-items: center;
  min-block-size: var(--tap);
  padding: 0.55rem 1rem;
  border: 1px solid var(--hairline-strong);
  border-radius: 0.25rem;
  color: var(--gold);
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: border-color 0.18s, color 0.18s;
}
@media (hover: hover) {
  .reg-link:hover {
    border-color: var(--gold-leaf);
    color: var(--gold-leaf);
  }
}
@media (min-width: 48rem) {
  .reg-link {
    display: inline-flex;
  }
}

/* The information pack. The same shape as the registration link beside it,
   in ink rather than gold: two gold buttons in one corner and neither reads
   as the main one. */
.info-link {
  display: none;
  align-items: center;
  min-block-size: var(--tap);
  padding: 0.55rem 1rem;
  border: 1px solid var(--hairline-strong);
  border-radius: 0.25rem;
  /* The token, not `inherit`: the bar sets no colour of its own, so over the
     title card `inherit` is the parchment page's dark ink on cobalt. */
  color: var(--ink);
  font-size: 0.85rem;
  font-weight: 600;
  text-decoration: none;
  white-space: nowrap;
  transition: border-color 0.18s, color 0.18s;
}
@media (hover: hover) {
  .info-link:hover {
    border-color: var(--gold-leaf);
    color: var(--gold-leaf);
  }
}
/* 75rem, not the 48rem the registration link uses.
 *
 * The chapter links come back at about 1080px and this is the fourth thing
 * in the other corner: measured, the two meet at 1100px and overlap by
 * 43 pixels. At 1200 there are 35 between them, which is the gap the bar
 * keeps everywhere else. Below that the pack is in the menu instead. */
@media (min-width: 75rem) {
  .info-link {
    display: inline-flex;
  }
}

.overlay__register {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin: 0 0 clamp(1.5rem, 4vh, 2.5rem);
}

.langs {
  position: relative;
}
.langs__toggle {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.35rem;
  min-block-size: var(--tap);
  padding: 0.45rem 0.75rem;
  border: 1px solid var(--hairline);
  border-radius: 0.25rem;
  background: transparent;
  color: var(--ink-soft);
  font: inherit;
  font-size: 0.72rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  cursor: pointer;
  transition: border-color 0.18s, color 0.18s;
}
.langs__toggle[aria-expanded='true'] {
  border-color: var(--gold);
  color: var(--ink);
}
@media (hover: hover) {
  .langs__toggle:hover {
    border-color: var(--gold);
    color: var(--ink);
  }
}
.langs__menu {
  position: absolute;
  inset-block-start: calc(100% + 0.5rem);
  inset-inline-end: 0;
  z-index: 10;
  min-width: 9rem;
  margin: 0;
  padding: 0.3rem;
  list-style: none;
  border: 1px solid var(--hairline);
  border-radius: 0.3rem;
  background: var(--surface-2);
  box-shadow: 0 1rem 2rem rgb(0 0 0 / 0.45);
}
.langs__menu button {
  display: flex;
  align-items: center;
  width: 100%;
  min-block-size: var(--tap);
  padding: 0.55rem 0.75rem;
  border: 0;
  border-radius: 0.2rem;
  background: transparent;
  color: var(--ink-soft);
  font: inherit;
  font-size: 0.85rem;
  text-align: start;
  cursor: pointer;
}
@media (hover: hover) {
  .langs__menu button:hover {
    background: var(--surface-2);
    color: var(--ink);
  }
}
.langs__menu button[aria-current='true'] {
  color: var(--accent-soft);
}

/* The bar's one call to action, as on the forum's own site. Hidden on the
   narrowest screens, where the title card's buttons are a scroll away. */
.bar__cta {
  display: none;
  align-items: center;
  gap: 0.4rem;
  min-block-size: var(--tap);
  padding: 0.55rem 1.1rem;
  border: 0;
  border-radius: 0.25rem;
  background: var(--gold-leaf);
  color: var(--on-gold);
  font: inherit;
  font-size: 0.8rem;
  font-weight: 700;
  white-space: nowrap;
  cursor: pointer;
  /* It is an <a> when it offers registration and a <button> when it offers
     the portfolio; the link must not arrive underlined. */
  text-decoration: none;
  transition: background-color 0.18s, color 0.18s;
}
@media (hover: hover) {
  .bar__cta:hover {
    background: var(--ink);
    color: var(--surface);
  }
}

.menu-toggle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  min-block-size: var(--tap);
  padding: 0.55rem 1.1rem;
  border: 1px solid var(--hairline);
  border-radius: 0.25rem;
  background: transparent;
  color: var(--ink);
  font: inherit;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  transition: border-color 0.18s;
}
@media (hover: hover) {
  .menu-toggle:hover {
    border-color: var(--gold);
  }
}
.menu-toggle__icon {
  display: none;
}

/**
 * The call to action is on the bar at every width, including the narrowest.
 *
 * It used to appear only above 30rem, which meant the smallest phones — the
 * ones least likely to scroll four screens on spec — had a bar with nothing
 * on it but a language code and a menu. Room is made by dropping the menu
 * button to its icon, the same thing it does on a wide screen once the
 * section links are visible.
 */
.bar__cta {
  display: inline-flex;
}
@media (max-width: 29.99rem) {
  .menu-toggle {
    inline-size: var(--tap);
    padding: 0.55rem;
  }
  .menu-toggle__text {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
  }
  .menu-toggle__icon {
    display: block;
  }
  .bar__cta {
    padding-inline: 0.75rem;
  }
}

/* The long label only where it fits; the accessible name is the long one at
   every width, so what is announced never shrinks with the screen. */
.bar__ctaShort {
  display: none;
}
@media (max-width: 25rem) {
  .bar__ctaLong {
    display: none;
  }
  .bar__ctaShort {
    display: inline;
  }
}

@media (min-width: 68rem) {
  .nav {
    display: flex;
  }
  /* With the sections on the bar the overlay becomes the "everything" menu, so
     its button steps back to an icon. */
  .menu-toggle {
    inline-size: var(--tap);
    padding: 0.55rem;
    border-radius: 0.25rem;
  }
  .menu-toggle__text {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
  }
  .menu-toggle__icon {
    display: block;
  }
}

.overlay {
  position: fixed;
  inset: 0;
  z-index: 90;
  overflow-y: auto;
  overscroll-behavior: contain;
}
.overlay__scrim {
  position: fixed;
  inset: 0;
  border: 0;
  background: color-mix(in srgb, var(--surface) 94%, transparent);
  backdrop-filter: blur(8px);
  cursor: pointer;
}
.overlay__inner {
  position: relative;
  width: var(--shell);
  margin-inline: auto;
  padding-block: clamp(4rem, 12vh, 8rem);
}
.overlay__close {
  position: absolute;
  inset-block-start: 1.5rem;
  inset-inline-end: 0;
  min-block-size: var(--tap);
  padding: 0.6rem 1.1rem;
  border: 1px solid var(--hairline);
  border-radius: 0.25rem;
  background: transparent;
  color: var(--ink);
  font: inherit;
  font-size: 0.8rem;
  cursor: pointer;
}
.overlay ol {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.25rem;
}
.overlay li button {
  display: flex;
  align-items: baseline;
  gap: clamp(1rem, 3vw, 2.5rem);
  width: 100%;
  padding: clamp(0.6rem, 1.4vh, 1rem) 0;
  border: 0;
  border-block-end: 1px solid var(--hairline);
  background: transparent;
  color: var(--ink-soft);
  text-align: start;
  cursor: pointer;
  transition: color 0.2s;
}
.overlay li button[aria-current='true'] {
  color: var(--ink);
}
@media (hover: hover) {
  .overlay li button:hover {
    color: var(--ink);
  }
}
.overlay__n {
  font-family: var(--font-body);
  font-size: 0.75rem;
  color: var(--ink-dim);
  font-variant-numeric: tabular-nums;
}
.overlay__label {
  font-family: var(--font-display);
  font-size: clamp(1.4rem, 4vw, 2.6rem);
  line-height: 1.1;
}
.overlay__label small {
  display: block;
  font-family: var(--font-body);
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--gold);
  margin-block-end: 0.2rem;
}
</style>
