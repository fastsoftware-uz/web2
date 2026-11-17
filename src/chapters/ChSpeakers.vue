<script setup lang="ts">
/**
 * Chapter II — who speaks at the forum.
 *
 * Every other chapter on this page is written from a document the organiser
 * has already issued. This one is not, and cannot be: a speaker list is
 * settled in the weeks before the forum and changes until the morning of it.
 * So the chapter reads an array that the build ships empty and the admin
 * fills — see server/public/site.php — and says plainly that the list is not
 * published yet while it is still empty, rather than pretending the forum has
 * no speakers.
 *
 * A card is a face, a name and a role, in that order, because that is the
 * order somebody scanning for a person they know reads them in. Where there
 * is no photograph the frame is not left empty: it carries the person's
 * initials under the same arch, so a list half of which has portraits does
 * not come out as a row of holes.
 */
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import SpeakerMark from '../components/SpeakerMark.vue';
import type { ForumSpeaker } from '../lib/forum-types';
import { useChapterTimeline } from '../lib/motion';
import { tr, useStore } from '../lib/state';

const { t } = useI18n();
const { forum } = useStore();
const root = ref<HTMLElement | null>(null);

/**
 * The speakers worth drawing.
 *
 * A record with no name in any language is a half-filled form somebody saved
 * on their way to lunch; it is skipped rather than published as a blank card.
 */
const speakers = computed<ForumSpeaker[]>(() =>
  (forum.value?.speakers ?? []).filter((s) => tr(s.name).trim() !== ''),
);

/** The first letter of each of the first two words — "O S", not "ОЛЬГА". */
/**
 * Everybody, portraits first.
 *
 * One card, one size. For a day there were two — a tall one for a
 * photograph and a short one for a name — because a grid makes every card
 * in a row as tall as the tallest and mixing them wasted half the page.
 * The client asked on 18 November 2026 for the short ones to be the size
 * of the tall ones, with something drawn where the photograph goes, and
 * that is the better answer: the chapter is one lattice again and a
 * portrait arriving changes nothing but the picture.
 *
 * Portraits first because the first eight are what the reader sees before
 * pressing anything, and a face is worth more there than a figure. Inside
 * each half the organiser's own order is untouched.
 */
const ordered = computed(() => [
  ...speakers.value.filter((s) => s.photo),
  ...speakers.value.filter((s) => !s.photo),
]);

/**
 * Eight to begin with — two rows of four — and the rest behind a button.
 *
 * Thirty-eight cards is four screens of faces between the programme and
 * the directions, which is three screens more than anybody reads on the
 * way past. Collapsed, the chapter is one screen and says how many more
 * there are; nothing is hidden from a reader who wants it, and nothing is
 * hidden from search engines either — the button only toggles a class on
 * cards that are all in the markup. The count comes from the list, so it
 * is right on the day the organiser adds the fortieth.
 */
const FIRST = 8;
const expanded = ref(false);
const rest = computed(() => Math.max(0, ordered.value.length - FIRST));

function initials(speaker: ForumSpeaker): string {
  return tr(speaker.name)
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((word) => [...word][0]?.toLocaleUpperCase() ?? '')
    .join('');
}

/** Name and role in one string, for the photograph's alternative text. */
function describe(speaker: ForumSpeaker): string {
  const role = tr(speaker.role) || tr(speaker.org);
  return role ? t('speakers.photoAlt', { name: tr(speaker.name), role }) : tr(speaker.name);
}

useChapterTimeline(
  () => root.value,
  ({ gsap, root: el }) => {
    gsap.from(el.querySelectorAll('.speakers__card'), {
      y: 24,
      opacity: 0,
      duration: 0.7,
      stagger: 0.06,
      ease: 'power3.out',
      scrollTrigger: { trigger: el, start: 'top 70%' },
    });
  },
);
</script>

<template>
  <section id="speakers" ref="root" class="chapter speakers">
    <div class="shell">
      <p class="eyebrow chapter-mark">{{ t('chapters.speakers.n') }}</p>
      <h2>{{ t('chapters.speakers.title') }}</h2>
      <p class="speakers__lead">{{ t('speakers.lead') }}</p>

      <p v-if="speakers.length === 0" class="speakers__empty">{{ t('speakers.empty') }}</p>

      <template v-else>
        <ul class="speakers__grid">
          <li
            v-for="(speaker, i) in ordered"
            :key="speaker.id"
            class="speakers__card card"
            :class="{ 'speakers__card--folded': !expanded && i >= FIRST }"
          >
            <div class="speakers__frame">
              <img
                v-if="speaker.photo"
                class="speakers__photo"
                :src="`photos/${speaker.photo}-full.webp`"
                :srcset="`photos/${speaker.photo}-thumb.webp 220w, photos/${speaker.photo}-full.webp 720w`"
                sizes="(max-width: 40rem) 45vw, 17rem"
                :alt="describe(speaker)"
                width="720"
                height="860"
                loading="lazy"
                decoding="async"
              />
              <!-- No portrait yet: a drawn figure, and the initials under
                   it so the card still says who it belongs to at a
                   glance. -->
              <template v-else>
                <SpeakerMark :kind="speaker.icon ?? null" />
                <span class="speakers__initials" aria-hidden="true">{{ initials(speaker) }}</span>
              </template>
            </div>
            <h3 class="speakers__name">{{ tr(speaker.name) }}</h3>
            <p v-if="tr(speaker.role)" class="speakers__role">{{ tr(speaker.role) }}</p>
            <p v-if="tr(speaker.org)" class="speakers__org">{{ tr(speaker.org) }}</p>
          </li>
        </ul>

        <!-- `aria-expanded` rather than two labels: a reader being read the
             page is told the state, and the visible word stays the thing
             the button does next. -->
        <button
          v-if="rest"
          type="button"
          class="btn speakers__more"
          :aria-expanded="expanded"
          @click="expanded = !expanded"
        >
          {{ expanded ? t('speakers.less') : t('speakers.more', { n: rest }) }}
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" :class="{ 'is-up': expanded }">
            <path d="M12 4v15m0 0-6-6m6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.8" />
          </svg>
        </button>
      </template>
    </div>
  </section>
</template>

<style scoped>
.speakers__lead {
  max-width: 44rem;
  margin-block-start: 1.25rem;
  color: var(--ink-soft);
}

.speakers__empty {
  margin-block-start: 2.5rem;
  padding: 1.5rem 1.75rem;
  border: 1px dashed var(--hairline);
  border-radius: 0.3rem;
  color: var(--ink-dim);
}

.speakers__grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(15rem, 1fr));
  /* Every row the height of the tallest, so all thirty-eight cards are
     one size however long a role runs. */
  grid-auto-rows: 1fr;
  gap: clamp(1rem, 2vw, 1.75rem);
  margin-block-start: clamp(2rem, 4vw, 3rem);
  padding: 0;
  list-style: none;
}

.speakers__card {
  display: flex;
  flex-direction: column;
  gap: 0.35rem;
  padding: 1rem 1.15rem 1.35rem;
}

/*
 * The ones past the eighth, before the button is pressed.
 *
 * `display: none` rather than a shorter list in the script, so every
 * speaker is in the markup from the first paint: a crawler reads all
 * thirty-eight, Ctrl+F finds a name nobody has expanded to yet, and the
 * button does not have to build a hundred nodes at the moment it is
 * pressed.
 */
.speakers__card--folded {
  display: none;
}

/*
 * The button under the grid.
 *
 * `justify-self: center` rather than a wrapper: the grid above it is the
 * section's own column, and a button pulled to the middle of it needs no
 * element of its own to sit in.
 */
.speakers__more {
  /* `flex` with a content-sized width, not `inline-flex`: an inline box
     ignores auto margins, which is why this sat under the first column
     instead of the middle of the grid. */
  display: flex;
  inline-size: fit-content;
  align-items: center;
  gap: 0.5rem;
  margin-block-start: clamp(1.25rem, 2.5vw, 2rem);
  margin-inline: auto;
}
.speakers__more svg {
  transition: rotate 0.25s ease;
}
.speakers__more svg.is-up {
  rotate: 180deg;
}
@media (prefers-reduced-motion: reduce) {
  .speakers__more svg {
    transition: none;
  }
}

/* The portrait stands in the same pointed arch a project's photograph does;
   see #uz-arch in App.vue. The ground behind it is the page's own raised
   paper, so a cut-out portrait and a square studio shot sit together. */
.speakers__frame {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 5 / 6;
  margin-block-end: 0.85rem;
  overflow: hidden;
  clip-path: url(#uz-arch);
  background:
    radial-gradient(120% 90% at 50% 10%, color-mix(in srgb, var(--gold-leaf) 16%, transparent), transparent 70%),
    var(--surface-sunk, color-mix(in srgb, var(--ink) 6%, var(--raise)));
}

.speakers__photo {
  inline-size: 100%;
  block-size: 100%;
  object-fit: cover;
  object-position: 50% 20%;
}

/*
 * The initials, under the drawn figure rather than instead of it.
 *
 * Small, because the figure is what carries the card now and two gold
 * things of the same weight in one arch is one too many. They are still
 * here because a figure says nothing about whose card this is, and the eye
 * going down a grid of thirty-eight finds a pair of letters faster than it
 * reads a name.
 */
.speakers__initials {
  position: absolute;
  inset-block-end: 12%;
  font-family: var(--font-display);
  font-size: 1.05rem;
  letter-spacing: 0.12em;
  color: color-mix(in srgb, var(--gold) 62%, var(--ink-dim));
}

.speakers__name {
  font-size: 1.0625rem;
  line-height: 1.25;
}

.speakers__role {
  font-size: 0.9375rem;
  line-height: 1.45;
  color: var(--gold);
}

.speakers__org {
  font-size: 0.875rem;
  color: var(--ink-dim);
}

@media (max-width: 30rem) {
  .speakers__grid {
    grid-template-columns: repeat(auto-fill, minmax(9.5rem, 1fr));
  }
  .speakers__card {
    padding: 0.75rem 0.85rem 1rem;
  }
}
</style>
