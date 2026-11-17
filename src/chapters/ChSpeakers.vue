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
 * The ones with a portrait, and the ones without, in that order.
 *
 * One grid could not hold both. A card with a photograph is three times the
 * height of a card without one, and a CSS grid makes every card in a row as
 * tall as the tallest: one portrait among three names left three cards
 * three-quarters empty, and letting each card keep its own height left a
 * hole beside the portrait instead. Grouping them is the one arrangement
 * that reads as a decision rather than a bug.
 *
 * Inside each group the organiser's own order is untouched, and when every
 * speaker has a portrait the second group is empty and this is one grid
 * again — which is where it is headed as the pictures arrive.
 */
const pictured = computed(() => speakers.value.filter((s) => s.photo));
const listed = computed(() => speakers.value.filter((s) => !s.photo));

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
        <ul v-if="pictured.length" class="speakers__grid">
          <li v-for="speaker in pictured" :key="speaker.id" class="speakers__card card">
            <div class="speakers__frame">
              <img
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
            </div>
            <h3 class="speakers__name">{{ tr(speaker.name) }}</h3>
            <p v-if="tr(speaker.role)" class="speakers__role">{{ tr(speaker.role) }}</p>
            <p v-if="tr(speaker.org)" class="speakers__org">{{ tr(speaker.org) }}</p>
          </li>
        </ul>

        <ul v-if="listed.length" class="speakers__grid speakers__grid--plain">
          <li v-for="speaker in listed" :key="speaker.id" class="speakers__card card speakers__card--plain">
            <div class="speakers__frame">
              <span class="speakers__initials" aria-hidden="true">{{ initials(speaker) }}</span>
            </div>
            <h3 class="speakers__name">{{ tr(speaker.name) }}</h3>
            <p v-if="tr(speaker.role)" class="speakers__role">{{ tr(speaker.role) }}</p>
            <p v-if="tr(speaker.org)" class="speakers__org">{{ tr(speaker.org) }}</p>
          </li>
        </ul>
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
 * A card for somebody whose portrait has not arrived.
 *
 * With four speakers and one portrait missing, an empty arch the size of a
 * photograph reads as a picture still loading. With thirty-three of them it
 * reads as a page that failed — which is what the chapter looked like on 17
 * November 2026, when the forum's own programme added thirty-four names and
 * one picture.
 *
 * So a card with no portrait keeps the arch and shrinks it to a medallion
 * beside the name: still the same shape, still the initials, but a mark on
 * a card rather than a hole in one. Cards with photographs are left exactly
 * as they were, which is the point — they now stand out instead of being
 * lost in a grid of blanks.
 */
/* The named list under the portraits: narrower columns, because none of
   these cards is carrying a picture that needs the width. */
.speakers__grid--plain {
  grid-template-columns: repeat(auto-fill, minmax(13.5rem, 1fr));
  margin-block-start: clamp(1rem, 2vw, 1.75rem);
  /*
   * Every one of these cards is the same size.
   *
   * Letting each keep its own height was worse than the problem it solved:
   * a role runs from one line to four, so the cards came out between 151
   * and 263 pixels tall and the grid read as rubble. Matching them row by
   * row was better and still not right — the rows then disagreed with each
   * other. `1fr` on the auto-placed rows settles it: every row takes the
   * height of the tallest, so the whole block is one lattice.
   *
   * It costs white space under the shortest cards. That is the trade, and
   * on a page whose other grids are all even it is the right way round.
   */
  grid-auto-rows: 1fr;
}

.speakers__card--plain {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  align-content: start;
  gap: 0.2rem 0.7rem;
}
.speakers__card--plain .speakers__frame {
  inline-size: 2.9rem;
  aspect-ratio: 5 / 6;
  margin-block-end: 0;
}
.speakers__card--plain .speakers__initials {
  font-size: 0.95rem;
}
/* The mark and the name share the top line; everything under it runs the
   full width of the card. A role or an organisation set in a column three
   centimetres narrower than the card breaks after every second word, which
   is how this looked at first. */
.speakers__card--plain .speakers__name {
  align-self: center;
  min-inline-size: 0;
  /* Two columns at 390px leave about 165px for a name, and
     "Muhammadsiddiq" is wider than that on its own. Long names break
     rather than push the card past its own edge. */
  overflow-wrap: anywhere;
}
.speakers__card--plain .speakers__role,
.speakers__card--plain .speakers__org {
  grid-column: 1 / -1;
}
.speakers__card--plain .speakers__role {
  margin-block-start: 0.3rem;
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

.speakers__initials {
  font-family: var(--font-display);
  font-size: clamp(2rem, 6vw, 2.75rem);
  letter-spacing: 0.06em;
  color: color-mix(in srgb, var(--gold) 75%, var(--ink-dim));
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
