<script setup lang="ts">
/**
 * The second page of a printed project sheet: what the forum is.
 *
 * A sheet is forwarded on its own and read by somebody who has not seen the
 * site. The cover gives them the project's name and the reading gives them
 * its figures, and between the two there was nothing at all about the event
 * the project is being presented at — no purpose, no dates one can act on, no
 * sense of how large the portfolio behind this one sheet is.
 *
 * So this page answers that before the reading starts: what the forum is for,
 * when and where it is, what the portfolio holds, which sessions run, and
 * what the organisers mean to come out of it.
 *
 * Every figure is read from the data file the site itself reads. Nothing here
 * is written by hand, which is why the page cannot drift from the page.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { formatMoney } from '../lib/money';
import { eventDates, tr, useStore } from '../lib/state';

const { t } = useI18n();
const { forum, lang } = useStore();

const event = computed(() => forum.value?.event ?? null);
const contact = computed(() => forum.value?.contact ?? null);
const portfolio = computed(() => forum.value?.portfolio ?? null);
const sessions = computed(() => forum.value?.sessions ?? []);

/** The forum's own statement of purpose, in the reader's language. */
const purpose = computed(() => {
  const intro = forum.value?.intro;
  return intro ? tr(intro.purpose) : '';
});

/** Three of the goals. The fourth and fifth are the same ground in other words. */
const goals = computed(() => (forum.value?.goals ?? []).slice(0, 3).map((g) => tr(g)));

/**
 * The day, as the invitation sets it out.
 *
 * It is on a page of its own rather than squeezed under the sessions: a
 * reader deciding whether to come is reading the clock, and the three
 * sessions after lunch are the part of the day this sheet's project is most
 * likely to be presented in.
 */
const programme = computed(() => forum.value?.programme ?? null);
const kindLabel = (kind: string) =>
  kind === 'plenary' ? t('programme.kinds.plenary', { n: 1 }) : t(`programme.kinds.${kind}`);

const facts = computed(() => {
  const e = event.value;
  const c = contact.value;
  if (!e) return [];
  return [
    { label: t('print.when'), value: `${eventDates()}, ${tr(e.city)}` },
    { label: t('print.within'), value: tr(e.within) },
    { label: t('print.organiser'), value: c ? tr(c.organisation) : '' },
    { label: t('print.languages'), value: t('print.languagesValue') },
  ].filter((f) => f.value);
});

/**
 * The portfolio in four numbers.
 *
 * The total is the one the site shows and carries the same caveat: it adds up
 * the projects whose figure is stated in dollars, and the sheets that state a
 * sum in soums or leave it open are counted but not added.
 */
const numbers = computed(() => {
  const p = portfolio.value;
  if (!p) return [];
  return [
    { n: String(p.projects.length), label: t('print.nProjects') },
    { n: String(Object.keys(p.regionCounts).length), label: t('print.nRegions') },
    { n: String(Object.keys(p.segmentCounts).length), label: t('print.nDirections') },
    { n: formatMoney(p.totalUsd, 'USD', lang.value), label: t('print.nTotal') },
  ];
});
</script>

<template>
  <section v-if="event" class="brief" aria-hidden="true">
    <p class="brief__eyebrow">{{ t('print.aboutTitle') }}</p>
    <h2 class="brief__name">{{ tr(event.name) }}</h2>
    <p v-if="purpose" class="brief__purpose">{{ purpose }}</p>

    <dl class="brief__facts">
      <div v-for="fact in facts" :key="fact.label">
        <dt>{{ fact.label }}</dt>
        <dd>{{ fact.value }}</dd>
      </div>
    </dl>

    <h3 class="brief__sub">{{ t('print.portfolioTitle') }}</h3>
    <ul class="brief__numbers">
      <li v-for="item in numbers" :key="item.label">
        <strong>{{ item.n }}</strong>
        <span>{{ item.label }}</span>
      </li>
    </ul>
    <p class="brief__note">{{ t('print.totalNote') }}</p>

    <div v-if="goals.length" class="brief__goalsBlock">
      <h3 class="brief__sub">{{ t('print.goalsTitle') }}</h3>
      <ul class="brief__goals">
        <li v-for="(goal, i) in goals" :key="i">{{ goal }}</li>
      </ul>
    </div>

    <!-- The day itself, on its own page. -->
    <section v-if="programme" class="brief__day">
      <p class="brief__eyebrow">{{ t('print.dayEyebrow') }}</p>
      <h3 class="brief__dayTitle">{{ tr(programme.day) }}</h3>
      <p class="brief__dayNote">{{ tr(programme.note) }}</p>

      <dl class="brief__hours">
        <div v-for="item in programme.items" :key="`${item.start}-${item.kind}`">
          <dt>
            {{ item.start }}<template v-if="item.end">–{{ item.end }}</template>
          </dt>
          <dd>
            <span class="brief__kind">{{ kindLabel(item.kind) }}</span>
            <strong>{{ tr(item.title) }}</strong>
            <span v-if="item.body" class="brief__hourBody">{{ tr(item.body) }}</span>
          </dd>
        </div>
      </dl>

    </section>

    <!--
      The three sessions, hour by hour, on a page of their own.

      They run at the same time in three halls, so a reader is choosing
      between them: that choice is easier to make with what is said in each
      one in front of you than with three titles. Two columns, because three
      timetables in one would run off the foot of the page.
    -->
    <section v-if="sessions.length" class="brief__sessionsPage">
      <p class="brief__eyebrow">{{ t('print.sessionsEyebrow') }}</p>
      <h3 class="brief__dayTitle">{{ t('print.sessionsTitle') }}</h3>
      <p class="brief__dayNote">{{ t('print.sessionsNote') }}</p>

      <ol class="brief__sessions">
        <li v-for="session in sessions" :key="session.id">
          <p class="brief__sessionHead">
            <span class="brief__sessionN">{{ String(session.number).padStart(2, '0') }}</span>
            <span v-if="session.time" class="brief__sessionTime">{{ session.time }}</span>
          </p>
          <!-- The title and the hours. What the session is about is in the
               hours themselves, line by line, so the summary that stands
               under the title on the site is left out here: it cost the
               third session its place on this page. -->
          <strong>{{ tr(session.title) }}</strong>

          <dl v-if="session.items?.length" class="brief__run">
            <div v-for="(item, i) in session.items" :key="i">
              <dt>{{ item.start }}<template v-if="item.end">–{{ item.end }}</template></dt>
              <dd>
                {{ tr(item.title) }}
                <em v-if="item.speaker">{{ tr(item.speaker) }}</em>
              </dd>
            </div>
          </dl>
        </li>
      </ol>
    </section>
  </section>
</template>

<style scoped>
.brief {
  display: none;
}

@media print {
  .brief {
    display: block;
    /* The reading supplies the page's side margins; this page sits in the
       same cell and keeps them. */
    padding-inline: 14mm;
    /* No break after: the portfolio tables follow the sessions on whatever
       is left of that page, and the project itself takes the next one. */
    padding-block-end: 6mm;
    color: #15193a;
    font-size: 9pt;
    line-height: 1.45;
  }

  .brief__eyebrow {
    margin: 0;
    font-size: 7.5pt;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #995f00;
  }
  .brief__name {
    margin: 2mm 0 0;
    font-family: var(--font-display);
    font-size: 19pt;
    font-weight: 600;
    line-height: 1.15;
  }
  .brief__purpose {
    margin: 3.5mm 0 0;
    max-inline-size: 150mm;
    font-size: 10pt;
    line-height: 1.5;
  }

  .brief__facts {
    margin: 6mm 0 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3.5mm 10mm;
    padding-block: 4mm;
    border-block: 1px solid #c9ccd8;
  }
  .brief__facts div {
    break-inside: avoid;
  }
  .brief__facts dt {
    font-size: 7pt;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #4a5070;
  }
  .brief__facts dd {
    margin: 1mm 0 0;
    font-size: 10pt;
  }

  .brief__sub {
    margin: 6.5mm 0 2.5mm;
    font-size: 8pt;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #00566e;
    break-after: avoid;
  }

  .brief__numbers {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4mm;
  }
  .brief__numbers li {
    padding: 3.5mm 3mm;
    border: 1px solid #c9ccd8;
    border-radius: 1mm;
    text-align: center;
    break-inside: avoid;
  }
  .brief__numbers strong {
    display: block;
    font-family: var(--font-display);
    font-size: 15pt;
    font-weight: 600;
    line-height: 1.1;
  }
  .brief__numbers span {
    display: block;
    margin-block-start: 1.5mm;
    font-size: 7.5pt;
    color: #4a5070;
  }
  .brief__note {
    margin: 2.5mm 0 0;
    font-size: 7.5pt;
    color: #4a5070;
  }

  .brief__sessionsPage {
    break-before: page;
    padding-block-start: 2mm;
  }
  /* One column, the width of the page: two columns fitted two sessions and
     sent the third to a page of its own, and the wider measure costs the
     timetables a line each rather than a page. */
  .brief__sessions {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .brief__sessions li {
    margin-block-end: 3.5mm;
    padding-block-end: 2.5mm;
    border-block-end: 1px solid #c9ccd8;
    break-inside: avoid;
  }
  .brief__sessionHead {
    margin: 0;
    display: flex;
    justify-content: space-between;
    gap: 6mm;
    font-size: 7.5pt;
    letter-spacing: 0.14em;
    color: #4a5070;
    font-variant-numeric: tabular-nums;
  }
  .brief__sessions strong {
    display: block;
    margin-block: 1mm 1.2mm;
    font-family: var(--font-display);
    font-size: 11pt;
    font-weight: 600;
    line-height: 1.2;
  }
  .brief__sessions > li > span {
    display: block;
    font-size: 8pt;
    line-height: 1.4;
    color: #4a5070;
  }

  /* One session's own hours. */
  .brief__run {
    margin: 2mm 0 0;
  }
  .brief__run > div {
    display: grid;
    grid-template-columns: 21mm 1fr;
    gap: 3mm;
    padding-block: 1.05mm;
    border-block-start: 1px solid #eef0f4;
  }
  .brief__run dt {
    font-size: 7.5pt;
    font-variant-numeric: tabular-nums;
    color: #4a5070;
    white-space: nowrap;
  }
  .brief__run dd {
    margin: 0;
    font-size: 8pt;
    line-height: 1.35;
  }
  .brief__run em {
    display: block;
    margin-block-start: 0.6mm;
    font-style: normal;
    font-size: 7.5pt;
    color: #4a5070;
  }

  /* The day, on the page after the forum's own description. */
  .brief__day {
    break-before: page;
    padding-block-start: 2mm;
  }
  .brief__dayTitle {
    margin: 2mm 0 0;
    font-family: var(--font-display);
    font-size: 15pt;
    font-weight: 600;
  }
  .brief__dayNote {
    margin: 2mm 0 5mm;
    max-inline-size: 150mm;
    font-size: 8.5pt;
    color: #4a5070;
  }
  .brief__hours {
    margin: 0;
    display: grid;
    gap: 0;
  }
  .brief__hours > div {
    display: grid;
    grid-template-columns: 26mm 1fr;
    gap: 5mm;
    padding-block: 2.6mm;
    border-block-start: 1px solid #e3e5ec;
    break-inside: avoid;
  }
  .brief__hours dt {
    font-variant-numeric: tabular-nums;
    font-size: 9pt;
    color: #15193a;
    white-space: nowrap;
  }
  .brief__hours dd {
    margin: 0;
    display: grid;
    gap: 0.8mm;
  }
  .brief__kind {
    font-size: 6.5pt;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #00566e;
  }
  .brief__hours strong {
    font-weight: 600;
    font-size: 9.5pt;
    line-height: 1.35;
  }
  .brief__hourBody {
    font-size: 8.5pt;
    color: #4a5070;
  }

  /**
   * The three aims, under the day, in two columns.
   *
   * They were a grid first, and Chrome does not fragment a grid container
   * across pages: the whole block went to a page of its own. As a block they
   * fragmented, which was worse — one bullet alone on the page after. Two
   * columns make them short enough to sit under the last hour of the day,
   * and the block is kept whole so they cannot be split again.
   */
  .brief__goalsBlock {
    margin-block-start: 1mm;
    break-inside: avoid;
  }
  .brief__goals {
    margin: 0;
    padding-inline-start: 5mm;
    columns: 2;
    column-gap: 9mm;
    font-size: 8.5pt;
  }
  .brief__goals li {
    margin-block-end: 1.6mm;
    break-inside: avoid;
  }
}
</style>
