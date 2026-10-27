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
 * when and where it is, what the portfolio holds, and what the organisers mean
 * to come out of it.
 *
 * The day's timetable used to follow, on two pages of its own. It is gone: an
 * hour-by-hour agenda dates the moment the programme moves, and a reader
 * deciding whether a project is worth an hour of their board's time is not
 * reading the clock. What stands in its place is PrintStats — the country the
 * project is in, in numbers.
 *
 * Every figure is read from the data file the site itself reads. Nothing here
 * is written by hand, which is why the page cannot drift from the page.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { formatMoney } from '../lib/money';
import { formatDateRange, formatNumber } from '../lib/shared/content';
import type { Kpi } from '../lib/shared/content-types';
import { eventDates, tr, useStore } from '../lib/state';

const { t } = useI18n();
const { content, forum, lang } = useStore();

const event = computed(() => forum.value?.event ?? null);
const contact = computed(() => forum.value?.contact ?? null);
const portfolio = computed(() => forum.value?.portfolio ?? null);

/** The forum's own statement of purpose, in the reader's language. */
const purpose = computed(() => {
  const intro = forum.value?.intro;
  return intro ? tr(intro.purpose) : '';
});

/**
 * What the projects cost, counted into bands.
 *
 * Only the sheets that state a sum in dollars can be counted — the ten in
 * soums and the seven left open are named under the chart rather than binned
 * into it, because a project whose price is "by agreement" is not a small
 * project, it is an unknown one.
 *
 * The bands are the chart's own, not the data's: they are round numbers an
 * investor thinks in, and the note says how many sheets each one holds.
 */
const BAND_EDGES = [1, 5, 20, 50];
const BAND_KEYS = ['0–1', '1–5', '5–20', '20–50', '50+'];

const sizeBands = computed(() => {
  const p = portfolio.value;
  if (!p) return [];
  const counts = new Array<number>(BAND_KEYS.length).fill(0);
  for (const project of p.projects) {
    if (project.currency !== 'USD' || !project.investment) continue;
    const millions = project.investment / 1e6;
    let band = BAND_EDGES.findIndex((edge) => millions < edge);
    if (band === -1) band = BAND_EDGES.length;
    counts[band] = (counts[band] ?? 0) + 1;
  }
  const top = Math.max(1, ...counts);
  return counts.map((n, i) => ({
    key: BAND_KEYS[i] as string,
    n,
    // Of the plot, less the band the figure above the bar occupies; and a
    // floor, so the smallest band is still a column.
    height: n ? Math.max(6, (n / top) * 78) : 0,
  }));
});

/** "$ mln" — the band axis's unit, in the reader's own word for a million. */
const sizeUnit = computed(() => `$ ${UNITS[lang.value]?.m ?? 'm'}`);

const sizeNote = computed(() => {
  const p = portfolio.value;
  if (!p) return '';
  return t('print.sizeNote', {
    usd: p.statedUsdCount,
    uzs: p.statedUzsCount,
    open: p.openCount,
  });
});

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
/**
 * The last forum, in four numbers.
 *
 * A different event from this one — Tashkent's general investment forum, held
 * in June — which is why it is named and dated on the page rather than folded
 * into the figures above it. It is here because "will anybody actually come"
 * is a question a first edition has to answer with somebody else's attendance
 * before it can answer it with its own.
 */
const tiif = computed(() => content.value?.tiif_2026 ?? null);
const TIIF_ORDER = ['agreements', 'countries', 'intl_participants', 'attendees'];
const UNITS: Record<string, Record<string, string>> = {
  uz: { bn: 'mlrd', m: 'mln', tn: 'trln' },
  ru: { bn: 'млрд', m: 'млн', tn: 'трлн' },
  en: { bn: 'bn', m: 'm', tn: 'tn' },
};

/** "$43,1 mlrd", "10 400+" — the same reading PrintStats gives a figure. */
function statText(kpi: Kpi): string {
  let unit = (kpi.suffix ?? '').trim();
  let plus = '';
  if (unit.endsWith('+')) {
    plus = '+';
    unit = unit.slice(0, -1).trim();
  }
  const word = UNITS[lang.value]?.[unit];
  const digits = formatNumber(kpi.value, lang.value, { maximumFractionDigits: 1 });
  const prefix = kpi.prefix ?? '';
  return word ? `${prefix}${digits} ${word}${plus}` : `${prefix}${digits}${unit}${plus}`;
}

const forumStats = computed(() => {
  const stats = tiif.value?.stats;
  if (!stats) return [];
  return TIIF_ORDER.map((id) => stats.find((k) => k.id === id)).filter(
    (k): k is Kpi => k !== undefined,
  );
});

const tiifLine = computed(() => {
  const forumPast = tiif.value;
  if (!forumPast) return '';
  return [tr(forumPast.name), formatDateRange(forumPast.dates, lang.value)]
    .filter(Boolean)
    .join(' · ');
});

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

    <div v-if="sizeBands.length" class="brief__sizeBlock">
      <h3 class="brief__sub">{{ t('print.sizeTitle') }}</h3>
      <p class="brief__axis">
        <span>{{ t('print.sizeAxis') }}</span>
        <span>{{ sizeUnit }}</span>
      </p>
      <div class="brief__cols">
        <div v-for="band in sizeBands" :key="band.key" class="brief__col">
          <span class="brief__colPlot">
            <span class="brief__colValue">{{ band.n }}</span>
            <span class="brief__colBar" :style="{ blockSize: band.height + '%' }"></span>
          </span>
          <span class="brief__colKey">{{ band.key }}</span>
        </div>
      </div>
      <p class="brief__note">{{ sizeNote }}</p>
    </div>

    <div v-if="forumStats.length" class="brief__pastBlock">
      <h3 class="brief__sub">{{ t('print.forumTitle') }}</h3>
      <p class="brief__pastLine">{{ tiifLine }}</p>
      <ul class="brief__numbers brief__numbers--quiet">
        <li v-for="stat in forumStats" :key="stat.id">
          <strong>{{ statText(stat) }}</strong>
          <span>{{ tr(stat.label) }}</span>
        </li>
      </ul>
    </div>
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
    /* No break after: the statistics pages open their own, and the portfolio
       tables follow them. */
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
    margin: 3mm 0 0;
    max-inline-size: 150mm;
    font-size: 10pt;
    line-height: 1.5;
  }

  .brief__facts {
    margin: 5mm 0 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 3mm 10mm;
    padding-block: 3.5mm;
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
    margin: 5mm 0 2.5mm;
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
    padding: 3mm 3mm;
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

  /* Another event's numbers, so they are set a step quieter than the forum's
     own — a reader should not have to check which four belong to this one. */
  .brief__pastBlock {
    margin-block-start: 5mm;
    break-inside: avoid;
  }
  .brief__pastLine {
    margin: -1mm 0 2.5mm;
    font-size: 7.5pt;
    color: #4a5070;
  }
  .brief__numbers--quiet li {
    padding: 2.6mm 2.5mm;
    border-color: #e3e5ec;
  }
  .brief__numbers--quiet strong {
    font-size: 12.5pt;
  }
  .brief__numbers--quiet span {
    font-size: 7pt;
  }

  /* ------------------------------------------- what the projects cost */

  .brief__sizeBlock {
    margin-block-start: 5mm;
    break-inside: avoid;
  }
  .brief__axis {
    display: flex;
    justify-content: space-between;
    gap: 6mm;
    margin: -0.5mm 0 2mm;
    font-size: 7pt;
    color: #626b8b;
  }
  .brief__cols {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 6mm;
  }
  .brief__col {
    text-align: center;
  }
  .brief__colValue {
    display: block;
    font-family: var(--font-display);
    font-size: 11pt;
    font-weight: 600;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    padding-block-end: 1.2mm;
  }
  .brief__colPlot {
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
    block-size: 24mm;
  }
  .brief__colBar {
    display: block;
    inline-size: 100%;
    background: #006fb9;
    border-radius: 0.6mm 0.6mm 0 0;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .brief__colKey {
    display: block;
    margin-block-start: 1.4mm;
    padding-block-start: 1.2mm;
    border-block-start: 0.3mm solid #c9ccd8;
    font-size: 7.5pt;
    font-variant-numeric: tabular-nums;
    color: #4a5070;
  }
}
</style>
