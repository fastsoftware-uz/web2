<script setup lang="ts">
/**
 * Chapter V — the day, hour by hour, and the three sessions under it.
 *
 * The timeline is a description list rather than a table: the rows are not
 * comparable along a second axis, and a <dl> keeps the time attached to the
 * item it belongs to when it is read out.
 *
 * Topic lists are collapsed by default — plenary 2 alone has six sub-themes,
 * and the shape of the day is the point of this chapter.
 */
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import type { ForumSession, ProgrammeItem } from '../lib/forum-types';
import { useChapterTimeline } from '../lib/motion';
import { tr, useStore } from '../lib/state';

const { t } = useI18n();
const { forum } = useStore();
const root = ref<HTMLElement | null>(null);
const open = ref(new Set<string>());

const programme = computed(() => forum.value?.programme);
const sessions = computed(() => forum.value?.sessions ?? []);

const keyOf = (item: ProgrammeItem) => `${item.start}-${item.kind}`;

/**
 * Which session timetables are open.
 *
 * The three sessions run at the same time, so a reader is choosing between
 * them rather than reading all three: the cards show what each one is about,
 * and the hour-by-hour list is one click away.
 */
const openSessions = ref(new Set<string>());
function toggleSession(session: ForumSession) {
  const next = new Set(openSessions.value);
  if (!next.delete(session.id)) next.add(session.id);
  openSessions.value = next;
}

function toggle(item: ProgrammeItem) {
  const key = keyOf(item);
  const next = new Set(open.value);
  if (!next.delete(key)) next.add(key);
  open.value = next;
}

const kindLabel = (item: ProgrammeItem) =>
  item.kind === 'plenary' ? t('programme.kinds.plenary', { n: item.plenary ?? 1 }) : t(`programme.kinds.${item.kind}`);

useChapterTimeline(
  () => root.value,
  ({ gsap, root: el }) => {
    gsap.from(el.querySelectorAll('.day__row'), {
      scrollTrigger: { trigger: el.querySelector('.day'), start: 'top 78%' },
      opacity: 0,
      x: -18,
      duration: 0.55,
      stagger: 0.06,
      ease: 'power2.out',
    });
  },
);
</script>

<template>
  <section id="programme" ref="root" class="chapter programme">
    <div class="shell">
      <p class="eyebrow chapter-mark">{{ t('chapters.programme.n') }}</p>
      <h2>{{ t('chapters.programme.title') }}</h2>

      <dl v-if="programme" class="day">
        <div v-for="item in programme.items" :key="keyOf(item)" class="day__row" :class="`day__row--${item.kind}`">
          <dt class="day__time">
            <span class="day__clock">{{ item.start }}</span>
            <!-- The last item of the day has no end in the organiser's own
                 programme, and inventing one would be inventing a fact. -->
            <template v-if="item.end">
              <span class="day__dash" aria-hidden="true">–</span>
              <span class="day__clock day__clock--end">{{ item.end }}</span>
            </template>
          </dt>
          <dd class="day__item">
            <p class="day__kind">{{ kindLabel(item) }}</p>
            <h3 class="day__title">{{ tr(item.title) }}</h3>
            <p v-if="item.body" class="day__text">{{ tr(item.body) }}</p>

            <template v-if="item.bullets?.length">
              <button type="button" class="day__toggle" :aria-expanded="open.has(keyOf(item))" @click="toggle(item)">
                {{ open.has(keyOf(item)) ? t('programme.collapse') : t('programme.expand') }}
                <span class="day__chev" :class="{ 'day__chev--up': open.has(keyOf(item)) }" aria-hidden="true">
                  <svg viewBox="0 0 24 24" width="12" height="12">
                    <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                  </svg>
                </span>
              </button>
              <ul v-if="open.has(keyOf(item))" class="day__bullets">
                <li v-for="(bullet, i) in item.bullets" :key="i">{{ tr(bullet) }}</li>
              </ul>
            </template>
          </dd>
        </div>
      </dl>


      <!-- The three sessions, each with its own hours ----------------------- -->
      <h3 class="programme__sub">{{ t('programme.sessionsTitle') }}</h3>

      <ol class="sessions">
        <li v-for="session in sessions" :key="session.id" class="sessions__item card">
          <p class="sessions__head">
            <span class="sessions__n">{{ String(session.number).padStart(2, '0') }}</span>
            <span v-if="session.time" class="sessions__time">{{ session.time }}</span>
          </p>
          <h4>{{ tr(session.title) }}</h4>
          <p class="sessions__topics">{{ tr(session.topics) }}</p>
          <!-- The English title the organiser gave the session, under the
               rule. It used to be introduced by a "working title" eyebrow;
               the label said less than the line under it. -->
          <p class="sessions__working">
            <em>{{ session.workingTitle }}</em>
          </p>

          <template v-if="session.items?.length">
            <button
              type="button"
              class="day__toggle sessions__toggle"
              :aria-expanded="openSessions.has(session.id)"
              @click="toggleSession(session)"
            >
              {{ openSessions.has(session.id) ? t('programme.sessionHide') : t('programme.sessionProgramme') }}
              <span class="day__chev" :class="{ 'day__chev--up': openSessions.has(session.id) }" aria-hidden="true">
                <svg viewBox="0 0 24 24" width="12" height="12">
                  <path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
              </span>
            </button>
            <dl v-if="openSessions.has(session.id)" class="run">
              <div v-for="(item, i) in session.items" :key="i" class="run__row">
                <dt class="run__time">
                  {{ item.start }}<template v-if="item.end">–{{ item.end }}</template>
                </dt>
                <dd class="run__item">
                  {{ tr(item.title) }}
                  <span v-if="item.speaker" class="run__speaker">{{ tr(item.speaker) }}</span>
                </dd>
              </div>
            </dl>
          </template>
        </li>
      </ol>
    </div>
  </section>
</template>

<style scoped>
.programme {
  align-content: start;
  /* 7rem, not 5: the fixed bar is 93px and a chapter that sets its own top
     padding has to clear it at every window height, as .chapter now does. */
  padding-block-start: clamp(7rem, 14vh, 9rem);
}
h2 {
  font-size: clamp(2rem, 4.5vw, 3.5rem);
  margin-block-start: 0.75rem;
}
.programme__lead {
  margin-block-start: 1.5rem;
  color: var(--ink);
  font-size: 1.1rem;
}
.programme__body {
  margin-block-start: 0.6rem;
  max-width: 36rem;
}

.day {
  margin: clamp(2.5rem, 7vh, 4rem) 0 0;
}
.day__row {
  display: grid;
  gap: 0.35rem 2rem;
  padding-block: 1.3rem;
  border-block-start: 1px solid var(--hairline);
}
.day__row:last-child {
  border-block-end: 1px solid var(--hairline);
}
@media (min-width: 48rem) {
  .day__row {
    grid-template-columns: 9rem minmax(0, 1fr);
  }
}

.day__time {
  display: flex;
  align-items: baseline;
  gap: 0.35rem;
  font-variant-numeric: tabular-nums;
  font-size: 0.95rem;
  color: var(--ink);
}
.day__dash,
.day__clock--end {
  color: var(--ink-dim);
  font-size: 0.85rem;
}

.day__item {
  margin: 0;
}
.day__kind {
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--accent-soft);
}
.day__row--break .day__kind {
  color: var(--ink-dim);
}
.day__title {
  margin-block-start: 0.3rem;
  font-size: clamp(1.05rem, 2vw, 1.35rem);
}
.day__text {
  margin-block-start: 0.45rem;
  font-size: 0.9rem;
}

.day__toggle {
  display: inline-flex;
  align-items: center;
  gap: 0.4rem;
  margin-block-start: 0.7rem;
  min-block-size: var(--tap);
  padding: 0.3rem 0.8rem;
  border: 1px solid var(--hairline);
  border-radius: 0.2rem;
  background: transparent;
  color: var(--ink-dim);
  font: inherit;
  font-size: 0.75rem;
  cursor: pointer;
}
@media (hover: hover) {
  .day__toggle:hover {
    color: var(--ink);
    border-color: var(--accent);
  }
}
.day__chev {
  display: inline-flex;
  transition: transform 0.2s;
}
.day__chev--up {
  transform: rotate(180deg);
}

.day__bullets {
  margin: 0.8rem 0 0;
  padding-inline-start: 1.1rem;
  display: grid;
  gap: 0.35rem;
  font-size: 0.88rem;
  color: var(--ink-soft);
}


.programme__sub {
  margin-block-start: clamp(3.5rem, 10vh, 6rem);
  font-size: clamp(1.4rem, 3vw, 2.25rem);
}

.sessions {
  list-style: none;
  margin: clamp(1.75rem, 4vh, 2.5rem) 0 0;
  padding: 0;
  display: grid;
  gap: 1rem;
  counter-reset: none;
  /* Each card is its own height: one of them opens a timetable of a dozen
     lines, and stretching the other two to match leaves them holding a
     paragraph in the middle of an empty card. */
  align-items: start;
}
@media (min-width: 56rem) {
  .sessions {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.sessions__item {
  display: grid;
  gap: 0.5rem;
  padding: clamp(1.25rem, 2.5vw, 1.75rem);
}
/* Three cards side by side rather than two and a gap. */
@media (min-width: 72rem) {
  .sessions {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.sessions__head {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  margin: 0;
}
.sessions__n {
  font-size: 0.75rem;
  letter-spacing: 0.2em;
  color: var(--accent-soft);
  font-variant-numeric: tabular-nums;
}
/* The three run at once, so the hours are what a reader compares them on. */
.sessions__time {
  font-size: 0.8rem;
  font-variant-numeric: tabular-nums;
  color: var(--ink-dim);
}
.sessions__toggle {
  justify-self: start;
  margin-block-start: 0.25rem;
}

/* One session's own timetable. */
.run {
  margin: 0.75rem 0 0;
  display: grid;
  gap: 0.1rem;
}
.run__row {
  display: grid;
  grid-template-columns: 5.6rem 1fr;
  gap: 0.75rem;
  padding-block: 0.5rem;
  border-block-start: 1px solid var(--hairline);
  font-size: 0.85rem;
}
.run__time {
  font-variant-numeric: tabular-nums;
  color: var(--ink-dim);
  white-space: nowrap;
}
.run__item {
  margin: 0;
  color: var(--ink-soft);
}
.run__speaker {
  display: block;
  margin-block-start: 0.15rem;
  font-size: 0.8rem;
  color: var(--ink-dim);
}
.sessions h4 {
  margin: 0;
  font-family: var(--font-display);
  font-weight: 400;
  font-size: clamp(1.1rem, 2vw, 1.35rem);
  line-height: 1.15;
  color: var(--ink);
}
.sessions__topics {
  font-size: 0.88rem;
}
.sessions__working {
  display: grid;
  gap: 0.2rem;
  margin-block-start: auto;
  padding-block-start: 0.75rem;
  border-block-start: 1px solid var(--hairline);
}
.sessions__working em {
  font-style: normal;
  font-size: 0.85rem;
  color: var(--ink);
}
</style>
