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

      <ul v-else class="speakers__grid">
        <li v-for="speaker in speakers" :key="speaker.id" class="speakers__card card">
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
            <span v-else class="speakers__initials" aria-hidden="true">{{ initials(speaker) }}</span>
          </div>
          <h3 class="speakers__name">{{ tr(speaker.name) }}</h3>
          <p v-if="tr(speaker.role)" class="speakers__role">{{ tr(speaker.role) }}</p>
          <p v-if="tr(speaker.org)" class="speakers__org">{{ tr(speaker.org) }}</p>
        </li>
      </ul>
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
