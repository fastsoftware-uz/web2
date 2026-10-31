<script setup lang="ts">
/**
 * Chapter V — three days, four halls, as the printed leaflet sets them out.
 *
 * The forum is one day inside a three-day fair, and until the organiser's
 * «ДАСТУР» leaflet arrived the page could only show that one day, from the
 * invitation, which called itself preliminary. The leaflet gives all three
 * and splits each of them across the halls they run in — so the page is a
 * day picker over a grid of halls rather than one list of hours.
 *
 * A grid, and this time a real one: unlike the old single-day list, these
 * rows *are* comparable along a second axis. 14:00 on day one is a different
 * session in each of three rooms, and choosing between them is the whole
 * question a reader has at that hour. Each hall is a column with its own
 * hours; on a phone the columns stack and keep their headings, because a card
 * that has lost its room is a card that cannot be attended.
 *
 * The signing zone is printed inside a hall column in the leaflet, at hours
 * that hall is already running a session. It is therefore not that hall, and
 * it sits under the grid as the day's own strip rather than being filed into
 * a room the document does not actually claim.
 *
 * The three preliminary sessions that used to close this chapter are gone
 * with the agenda they belonged to: the leaflet names twelve sessions on the
 * same afternoon, and a page cannot carry two timetables for one day.
 */
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

import type { DayItem, ProgrammeDay, ProgrammeHall } from '../lib/forum-types';
import { useChapterTimeline } from '../lib/motion';
import { tr, useStore } from '../lib/state';

const { t } = useI18n();
const { forum } = useStore();
const root = ref<HTMLElement | null>(null);

const programme = computed(() => forum.value?.programme);
const days = computed<ProgrammeDay[]>(() => programme.value?.days ?? []);

/** Which day is open. The forum's own day leads, because this is its site. */
const chosen = ref(1);
const day = computed(() => days.value.find((d) => d.n === chosen.value) ?? days.value[0] ?? null);

const hallsById = computed(() => {
  const map = new Map<string, ProgrammeHall>();
  for (const hall of programme.value?.halls ?? []) map.set(hall.id, hall);
  return map;
});

/**
 * The day's halls, split the way the leaflet prints them: the seated
 * conference halls in a grid, and the one that runs a single all-day activity
 * — it is given a floor area and no seat count — as a strip under it.
 */
function tracksOf(d: ProgrammeDay | null, side: boolean) {
  if (!d) return [];
  return d.tracks
    .map((track) => ({ ...track, room: hallsById.value.get(track.hall) }))
    .filter((track) => track.room && (track.room.seats === null) === side);
}

const halls = computed(() => tracksOf(day.value, false));
const sideHalls = computed(() => tracksOf(day.value, true));

const hallMeta = (hall: ProgrammeHall) =>
  hall.seats === null
    ? t('programme.hallArea', { area: hall.area })
    : t('programme.hallMeta', { area: hall.area, seats: hall.seats });

const kindLabel = (item: DayItem) =>
  item.kind === 'session' && item.n
    ? t('programme.kinds.sessionN', { n: item.n })
    : t(`programme.kinds.${item.kind}`);

const hours = (item: { start: string; end?: string }) =>
  item.end ? `${item.start}–${item.end}` : item.start;

useChapterTimeline(
  () => root.value,
  ({ gsap, root: el }) => {
    gsap.from(el.querySelectorAll('.halls__col'), {
      scrollTrigger: { trigger: el.querySelector('.halls'), start: 'top 82%' },
      opacity: 0,
      y: 18,
      duration: 0.5,
      stagger: 0.08,
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

      <!-- The day picker: the leaflet's «1-кун · 2-кун · 3-кун». ----------- -->
      <div v-if="days.length" class="pick" role="tablist" :aria-label="t('programme.pickLabel')">
        <button
          v-for="d in days"
          :key="d.n"
          type="button"
          role="tab"
          class="pick__day"
          :class="{ 'pick__day--on': d.n === chosen }"
          :aria-selected="d.n === chosen"
          :tabindex="d.n === chosen ? 0 : -1"
          @click="chosen = d.n"
        >
          <span class="pick__n">{{ t('programme.dayN', { n: d.n }) }}</span>
          <span class="pick__date">{{ tr(d.label) }}</span>
          <span class="pick__weekday">{{ tr(d.weekday) }}</span>
        </button>
      </div>

      <template v-if="day">
        <p class="programme__headline">
          {{ tr(day.headline) }}
          <span v-if="day.forumDay" class="programme__badge">{{ t('programme.forumDay') }}</span>
        </p>

        <!-- One column per seated hall. ---------------------------------- -->
        <div class="halls" :key="day.n">
          <section v-for="track in halls" :key="track.hall" class="halls__col">
            <header class="halls__head">
              <p class="halls__kind">{{ t('programme.hall') }}</p>
              <h3 class="halls__name">{{ tr(track.room!.name) }}</h3>
              <p class="halls__meta">{{ hallMeta(track.room!) }}</p>
            </header>

            <ol class="slot">
              <li
                v-for="(item, i) in track.items"
                :key="`${item.start}-${i}`"
                class="slot__item"
                :class="{ 'slot__item--break': item.kind === 'break' }"
              >
                <p class="slot__time">{{ hours(item) }}</p>
                <template v-if="item.kind !== 'break'">
                  <p class="slot__kind">{{ kindLabel(item) }}</p>
                  <h4 class="slot__title">{{ tr(item.title) }}</h4>
                  <ol v-if="item.parts?.length" class="slot__parts">
                    <li
                      v-for="(part, j) in item.parts"
                      :key="j"
                      :class="{ 'slot__part--break': part.kind === 'break' }"
                    >
                      <span v-if="part.n" class="slot__partN">{{ part.n }}</span>
                      {{ tr(part.title) }}
                    </li>
                  </ol>
                </template>
                <h4 v-else class="slot__break">{{ tr(item.title) }}</h4>
              </li>
            </ol>
          </section>
        </div>

        <!-- The hall that runs one thing all day, and the signing zone. --- -->
        <div v-if="sideHalls.length || day.signings.length" class="aside">
          <section v-for="track in sideHalls" :key="track.hall" class="aside__hall">
            <p class="aside__room">
              <span class="aside__kind">{{ t('programme.hall') }}</span>
              {{ tr(track.room!.name) }} · {{ hallMeta(track.room!) }}
            </p>
            <ul class="aside__items">
              <li v-for="(item, i) in track.items" :key="i">
                <span class="aside__time">{{ hours(item) }}</span>
                <span class="aside__title">{{ tr(item.title) }}</span>
              </li>
            </ul>
          </section>

          <section v-if="day.signings.length" class="aside__hall aside__hall--sign">
            <p class="aside__room">
              <span class="aside__kind">{{ t('programme.zone') }}</span>
              {{ tr(day.signings[0]!.title) }}
            </p>
            <ul class="aside__items">
              <li v-for="(slot, i) in day.signings" :key="i">
                <span class="aside__time">{{ hours(slot) }}</span>
                <span class="aside__title">{{ tr(slot.body) }}</span>
              </li>
            </ul>
          </section>
        </div>
      </template>

      <p v-if="programme" class="programme__note">{{ tr(programme.note) }}</p>
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

/* ------------------------------------------------------------ the day picker */

.pick {
  display: grid;
  gap: 0.6rem;
  margin-block-start: clamp(1.75rem, 4vh, 2.75rem);
}
@media (min-width: 42rem) {
  .pick {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
.pick__day {
  display: grid;
  gap: 0.15rem;
  justify-items: start;
  min-block-size: var(--tap);
  padding: 0.85rem 1.1rem;
  border: 1px solid var(--hairline);
  border-radius: 0.2rem;
  background: transparent;
  color: var(--ink-dim);
  font: inherit;
  text-align: start;
  cursor: pointer;
  transition:
    border-color 0.2s,
    color 0.2s;
}
@media (hover: hover) {
  .pick__day:hover {
    border-color: var(--accent);
    color: var(--ink);
  }
}
/* The chosen day carries the gold rule the chapter marks use, so which one is
   open is legible without relying on the fill alone. */
.pick__day--on {
  border-color: var(--accent);
  box-shadow: inset 0 3px 0 0 var(--accent);
  color: var(--ink);
}
.pick__n {
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--accent-soft);
}
.pick__day--on .pick__n {
  color: var(--accent);
}
.pick__date {
  font-family: var(--font-display);
  font-size: clamp(1.1rem, 2vw, 1.4rem);
  line-height: 1.1;
  color: var(--ink);
}
.pick__weekday {
  font-size: 0.8rem;
  color: var(--ink-dim);
}

.programme__headline {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.75rem;
  margin-block-start: clamp(1.5rem, 3.5vh, 2.25rem);
  font-family: var(--font-display);
  font-size: clamp(1.05rem, 2.2vw, 1.4rem);
  color: var(--ink);
}
.programme__badge {
  padding: 0.18rem 0.6rem;
  border: 1px solid var(--accent);
  border-radius: 1rem;
  font-family: var(--font-body);
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--accent);
}

/* ---------------------------------------------------------------- the halls */

.halls {
  margin-block-start: clamp(1.25rem, 3vh, 2rem);
  display: grid;
  gap: 1rem;
  align-items: start;
}
@media (min-width: 50rem) {
  .halls {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
@media (min-width: 72rem) {
  .halls {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}

.halls__head {
  padding-block-end: 0.7rem;
  border-block-end: 2px solid var(--accent);
}
.halls__kind,
.aside__kind {
  margin: 0;
  font-size: 0.66rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--ink-dim);
}
.halls__name {
  margin: 0.2rem 0 0;
  font-family: var(--font-display);
  font-weight: 400;
  font-size: clamp(1.15rem, 2.2vw, 1.45rem);
  line-height: 1.1;
  color: var(--ink);
}
.halls__meta {
  margin: 0.15rem 0 0;
  font-size: 0.78rem;
  font-variant-numeric: tabular-nums;
  color: var(--ink-dim);
}

.slot {
  list-style: none;
  margin: 0;
  padding: 0;
  display: grid;
  gap: 0.6rem;
  margin-block-start: 0.8rem;
}
.slot__item {
  padding: 0.85rem 1rem;
  border: 1px solid var(--hairline);
  border-radius: 0.2rem;
  /* The left edge carries the hour the way the leaflet's cards do. */
  border-inline-start: 3px solid var(--accent-soft);
}
.slot__item--break {
  padding-block: 0.5rem;
  border: none;
  border-block: 1px dashed var(--hairline);
  border-radius: 0;
  display: flex;
  align-items: baseline;
  gap: 0.75rem;
}
.slot__time {
  margin: 0;
  font-size: 0.78rem;
  font-variant-numeric: tabular-nums;
  color: var(--accent-soft);
}
.slot__kind {
  margin: 0.25rem 0 0;
  font-size: 0.66rem;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--ink-dim);
}
.slot__title {
  margin: 0.3rem 0 0;
  font-family: var(--font-display);
  font-weight: 400;
  font-size: 1rem;
  line-height: 1.25;
  color: var(--ink);
}
.slot__break {
  margin: 0;
  font-family: var(--font-body);
  font-size: 0.85rem;
  font-weight: 500;
  color: var(--ink-dim);
}
.slot__item--break .slot__time {
  color: var(--ink-dim);
}

.slot__parts {
  margin: 0.6rem 0 0;
  padding: 0;
  list-style: none;
  display: grid;
  gap: 0.35rem;
  font-size: 0.85rem;
  color: var(--ink-soft);
}
.slot__parts li {
  padding-inline-start: 0.9rem;
  border-inline-start: 1px solid var(--hairline);
}
.slot__part--break {
  color: var(--ink-dim);
  font-size: 0.8rem;
}
.slot__partN {
  margin-inline-end: 0.3rem;
  font-variant-numeric: tabular-nums;
  color: var(--accent-soft);
}

/* ------------------------------------------- the all-day hall and the zone */

.aside {
  margin-block-start: 1rem;
  display: grid;
  gap: 1rem;
}
@media (min-width: 50rem) {
  .aside {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
.aside__hall {
  padding: 1rem 1.1rem;
  border: 1px solid var(--hairline);
  border-radius: 0.2rem;
  border-inline-start: 3px solid var(--accent);
}
.aside__hall--sign {
  border-inline-start-style: dashed;
}
.aside__room {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 0.5rem;
  margin: 0;
  font-family: var(--font-display);
  font-size: 1rem;
  color: var(--ink);
}
.aside__items {
  list-style: none;
  margin: 0.6rem 0 0;
  padding: 0;
  display: grid;
  gap: 0.35rem;
}
.aside__items li {
  display: flex;
  flex-wrap: wrap;
  gap: 0.2rem 0.9rem;
}
.aside__time {
  flex: 0 0 auto;
  inline-size: 7rem;
  font-size: 0.8rem;
  font-variant-numeric: tabular-nums;
  color: var(--accent-soft);
}
.aside__title {
  font-size: 0.9rem;
  color: var(--ink-soft);
}

.programme__note {
  margin-block-start: clamp(1.75rem, 4vh, 2.5rem);
  padding-block-start: 1rem;
  border-block-start: 1px solid var(--hairline);
  max-inline-size: 48rem;
  font-size: 0.85rem;
  color: var(--ink-dim);
}
</style>
