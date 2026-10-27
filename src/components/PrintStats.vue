<script setup lang="ts">
/**
 * The country the project is in, in numbers: three printed pages.
 *
 * A project sheet is forwarded on its own and read by somebody who has to
 * decide whether Uzbekistan is worth an hour of their board's time before
 * they decide whether this project is. The sheet used to answer that with the
 * forum's timetable, which is the one thing in the document that answers no
 * investment question at all — so the agenda came out and this went in.
 *
 * Three pages, in the order the question is actually asked:
 *
 *   1. the economy — what it is worth, how fast it moves, what it is made of;
 *   2. the people — who would run the project, and what an hour of their work
 *      costs, region by region;
 *   3. the climate — where the state says the country is going by 2030, and
 *      what a foreign investor is actually offered for coming.
 *
 * Every figure is read from `content.json`, the same file the site reads, and
 * none of it is typed here. That is not a tidiness point: it is the only way
 * these pages cannot drift away from the pages the reader can check them
 * against, and the only way a corrected figure reaches the PDF by being
 * corrected once.
 *
 * ## The charts
 *
 * They are CSS and one inline SVG, not a chart library. A library would have
 * to be shipped, told about three languages' digit grouping, and then talked
 * out of its own canvas — which prints as a bitmap. Bars that are elements
 * print as vectors, hyphenate their labels and inherit the document's type.
 *
 * Colours come from the shared, validated dataviz palette. Its light mode
 * carries a documented contrast WARN against the surface, which is only
 * dismissable where the chart ships a relief channel — so every bar and every
 * slice here carries its value as text beside it, and no chart on these pages
 * asks the reader to read a colour.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { formatNumber } from '../lib/shared/content';
import type { Kpi, Lang } from '../lib/shared/content-types';
import { VIZ } from '../lib/shared/viz-palette';
import { tr, trSuffixed, useStore } from '../lib/state';

const props = defineProps<{
  /** The project's region, so its row in the salary chart can be marked. */
  region?: string | null;
}>();

const { t } = useI18n();
const { content, lang } = useStore();

const economy = computed(() => content.value?.economy_2025 ?? null);
const people = computed(() => content.value?.human_capital ?? null);
const regions = computed(() => content.value?.regions ?? null);
const strategy = computed(() => content.value?.strategy_2030 ?? null);
const zones = computed(() => content.value?.sez_and_incentives ?? null);

/* ------------------------------------------------------------ the figures */

/**
 * The magnitude words, because the data file writes them in English.
 *
 * `{ value: 147, prefix: '~$', suffix: ' bn' }` is a number and a unit, and
 * the unit is a word: printed into Uzbek copy as "bn" it is the one piece of
 * English left in a sheet that is otherwise translated. The site's own money
 * formatter keeps the same three words (lib/money.ts) and this keeps them in
 * step with it.
 */
const UNITS: Record<Lang, Record<string, string>> = {
  uz: { bn: 'mlrd', m: 'mln', tn: 'trln' },
  ru: { bn: 'млрд', m: 'млн', tn: 'трлн' },
  en: { bn: 'bn', m: 'm', tn: 'tn' },
};

/** A KPI as one string: "~$147 mlrd", "7,7%", "600 000+". */
function kpiText(kpi: Kpi): string {
  let unit = (kpi.suffix ?? '').trim();
  let plus = '';
  if (unit.endsWith('+')) {
    plus = '+';
    unit = unit.slice(0, -1).trim();
  }
  const word = UNITS[lang.value][unit];
  const digits = formatNumber(kpi.value, lang.value, { maximumFractionDigits: 1 });
  const prefix = kpi.prefix ?? '';
  return word ? `${prefix}${digits} ${word}${plus}` : `${prefix}${digits}${unit}${plus}`;
}

/** A share, as the chart labels write it: "48,7%". */
const pct = (share: number) =>
  `${formatNumber(share, lang.value, { maximumFractionDigits: 1 })}%`;

/** Picks named KPIs out of a list, in the order asked for, skipping any gaps. */
function pickKpis(list: Kpi[] | undefined, ids: string[]): Kpi[] {
  if (!list) return [];
  return ids.map((id) => list.find((k) => k.id === id)).filter((k): k is Kpi => k !== undefined);
}

/* ------------------------------------------------------- page 1: the economy */

/**
 * The eight the data file states, in the order an investor reads them: how
 * big, how fast, how stable, then how much money is already arriving.
 */
const ECONOMY_ORDER = [
  'gdp',
  'gdp_growth',
  'foreign_investment',
  'trade',
  'industry_growth',
  'inflation',
  'population_eoy',
  'foreign_enterprises',
];
const economyKpis = computed(() => pickKpis(economy.value?.kpis, ECONOMY_ORDER));

/** The headline figure, for the middle of the ring. */
const gdpKpi = computed(() => economy.value?.kpis.find((k) => k.id === 'gdp') ?? null);

/**
 * The middle of the ring, where there is room for two words.
 *
 * The data file's label carries its unit in a parenthetical — "Nominal GDP
 * (bn $)" — which the figure above it has already said, and which in Russian
 * is long enough to set itself across the ring.
 */
const gdpLabel = computed(() => {
  const kpi = gdpKpi.value;
  return kpi ? tr(kpi.label).replace(/\s*\([^)]*\)\s*$/, '') : '';
});

/* The ring. 2πr with r = 46, in the SVG's own units. */
const RING_R = 46;
const RING_C = 2 * Math.PI * RING_R;
/** The gap between slices, so four shares read as four and not as a wheel. */
const RING_GAP = 1.6;

/**
 * The four branches of GDP as arcs.
 *
 * Drawn as one dashed circle per slice rather than as paths: a stroked arc
 * keeps its width exactly, and a browser that rounds a path's join is a
 * browser that prints a hairline between two slices.
 */
const gdpSlices = computed(() => {
  const parts = economy.value?.gdp_structure ?? [];
  let turned = 0;
  return parts.map((part, i) => {
    const length = (part.share / 100) * RING_C;
    const drawn = Math.max(0, length - RING_GAP);
    const slice = {
      id: part.id ?? String(i),
      label: tr(part.label),
      share: part.share,
      color: VIZ.light.categorical[i % VIZ.light.categorical.length] as string,
      dash: `${drawn} ${RING_C - drawn}`,
      offset: -turned,
    };
    turned += length;
    return slice;
  });
});

/**
 * The largest branches of the economy, whichever parent they belong to.
 *
 * The donut answers "what is this economy made of" at the level of four
 * words; this answers it at the level a project belongs to. Seven rows: the
 * eighth is three per cent, a bar that short says nothing the number beside
 * it does not already say, and in Russian it was the row that pushed the page
 * over. Each keeps its parent's colour, so the two charts are one chart read
 * twice.
 */
const branches = computed(() => {
  const parts = economy.value?.gdp_structure ?? [];
  const rows = parts.flatMap((part, i) =>
    (part.children ?? []).map((child) => ({
      label: tr(child.label),
      share: child.share,
      color: VIZ.light.categorical[i % VIZ.light.categorical.length] as string,
      parent: tr(part.label),
    })),
  );
  return rows.sort((a, b) => b.share - a.share).slice(0, 7);
});

const branchMax = computed(() => Math.max(1, ...branches.value.map((b) => b.share)));

/**
 * A growth driver states its figure as written text — "$89 bn" — rather than
 * as a number and a unit, so `kpiText` cannot reach it. Left alone it is the
 * one piece of English on an Uzbek page, standing beside "~$147 mlrd" and
 * contradicting it, so the magnitude word is swapped for the same three words
 * the rest of the document uses.
 */
function localiseUnits(value: string): string {
  const words = UNITS[lang.value];
  return value.replace(/(\d)\s*(bn|tn|m)\b/gi, (whole, digit: string, unit: string) => {
    const word = words[unit.toLowerCase()];
    return word ? `${digit} ${word}` : whole;
  });
}

const drivers = computed(() =>
  (economy.value?.growth_drivers ?? []).map((d) => ({
    id: d.id,
    value: localiseUnits(d.value),
    label: tr(d.label),
  })),
);

/* --------------------------------------------------------- page 2: the people */

const PEOPLE_ORDER = ['population', 'under30', 'median_age', 'labor_force', 'new_entrants', 'students'];
const peopleKpis = computed(() => pickKpis(people.value?.kpis, PEOPLE_ORDER));

/**
 * The four rates, as bars against the same 0–100 axis.
 *
 * They are percentages of different things and that is the point of putting
 * them on one axis: an employment rate of 70,5 and an unemployment rate of
 * 4,8 are only informative beside each other.
 */
const RATE_ORDER = ['employment', 'unemployment', 'literacy', 'higher_ed'];
const rates = computed(() =>
  pickKpis(people.value?.kpis, RATE_ORDER).map((k) => ({
    id: k.id,
    label: tr(k.label),
    value: k.value,
  })),
);

/**
 * Average monthly pay, region by region, largest first.
 *
 * This is the chart on these pages a reader is most likely to take a pencil
 * to: it is the cost side of every project in the portfolio, and the spread
 * across it — the capital pays more than twice what the cheapest region does
 * — is the single strongest argument for putting a project outside Tashkent.
 * The project's own region is marked, the way its row is marked in the
 * portfolio tables two pages later.
 */
const salaries = computed(() => {
  const r = regions.value;
  if (!r) return [];
  return [...r.items]
    .sort((a, b) => b.avg_salary - a.avg_salary)
    .map((item) => ({
      id: item.id,
      label: tr({ uz: item.uz, ru: item.ru, en: item.en }),
      value: item.avg_salary,
      here: item.id === props.region,
    }));
});

const salaryMax = computed(() => Math.max(1, ...salaries.value.map((s) => s.value)));
const nationalAverage = computed(() => regions.value?.national_avg_salary ?? 0);
/** Where the national average falls across the plot, as a percentage of it. */
const averageAt = computed(() => (nationalAverage.value / salaryMax.value) * 100);

const salaryText = (value: number) =>
  formatNumber(value, lang.value, { maximumFractionDigits: 0 });

/* -------------------------------------------------------- page 3: the climate */

/** The five priorities, with whatever targets the data file states for each. */
const priorities = computed(() =>
  (strategy.value?.priorities ?? []).map((p) => ({
    n: p.n,
    text: tr({ uz: p.uz, ru: p.ru, en: p.en }),
    targets: (trSuffixed<string[] | undefined>(
      p as unknown as Record<string, unknown>,
      'targets',
    ) ?? []) as string[],
  })),
);

const adopted = computed(() => {
  const s = strategy.value;
  if (!s) return '';
  return trSuffixed<string>(s as unknown as Record<string, unknown>, 'adopted') ?? s.adopted;
});

const zoneTypes = computed(() => {
  const z = zones.value;
  if (!z) return [];
  return (trSuffixed<string[]>(z as unknown as Record<string, unknown>, 'zone_types') ?? []) as string[];
});

/** The incentives, in the data file's own words. */
const incentives = computed(() => (zones.value?.points ?? []).map((p) => tr(p)));

</script>

<template>
  <div v-if="content" class="pstat" aria-hidden="true">
    <!-- ======================================================= 1. the economy -->
    <section v-if="economy" class="pstat__page">
      <header class="pstat__head">
        <p class="pstat__eyebrow">{{ t('pstats.economyEyebrow') }}</p>
        <h2 class="pstat__title">{{ t('pstats.economyTitle') }}</h2>
        <p class="pstat__lead">{{ t('pstats.economyLead') }}</p>
      </header>

      <ul class="pstat__kpis">
        <li v-for="kpi in economyKpis" :key="kpi.id">
          <strong>{{ kpiText(kpi) }}</strong>
          <span>{{ tr(kpi.label) }}</span>
        </li>
      </ul>

      <section v-if="drivers.length" class="pstat__block pstat__keepWhole">
        <h3 class="pstat__sub">{{ t('pstats.driversTitle') }}</h3>
        <ul class="pstat__drivers">
          <li v-for="driver in drivers" :key="driver.id">
            <strong>{{ driver.value }}</strong>
            <span>{{ driver.label }}</span>
          </li>
        </ul>
      </section>

      <section class="pstat__block pstat__gdp">
        <h3 class="pstat__sub">{{ t('pstats.gdpTitle') }}</h3>

        <div class="pstat__gdpCols">
          <figure class="pstat__ring">
            <svg viewBox="0 0 128 128" role="presentation">
              <!-- The track, so a rounding error between two slices reads as
                   a seam rather than as white paper. -->
              <circle class="pstat__ringTrack" cx="64" cy="64" :r="RING_R" />
              <g transform="rotate(-90 64 64)">
                <circle
                  v-for="slice in gdpSlices"
                  :key="slice.id"
                  class="pstat__ringArc"
                  cx="64"
                  cy="64"
                  :r="RING_R"
                  :stroke="slice.color"
                  :stroke-dasharray="slice.dash"
                  :stroke-dashoffset="slice.offset"
                />
              </g>
            </svg>
            <figcaption v-if="gdpKpi" class="pstat__ringMiddle">
              <strong>{{ kpiText(gdpKpi) }}</strong>
              <span>{{ gdpLabel }}</span>
            </figcaption>
          </figure>

          <ol class="pstat__legend">
            <li v-for="slice in gdpSlices" :key="slice.id">
              <span class="pstat__chip" :style="{ background: slice.color }"></span>
              <span class="pstat__legendName">{{ slice.label }}</span>
              <span class="pstat__legendShare">{{ pct(slice.share) }}</span>
            </li>
          </ol>
        </div>
      </section>

      <section class="pstat__block">
        <h3 class="pstat__sub">{{ t('pstats.branchesTitle') }}</h3>
        <ol class="pstat__bars">
          <li v-for="row in branches" :key="row.label">
            <span class="pstat__barLabel">{{ row.label }}</span>
            <span class="pstat__barTrack">
              <span
                class="pstat__barFill"
                :style="{ inlineSize: (row.share / branchMax) * 100 + '%', background: row.color }"
              ></span>
            </span>
            <span class="pstat__barValue">{{ pct(row.share) }}</span>
          </li>
        </ol>
      </section>
    </section>

    <!-- ======================================================== 2. the people -->
    <section v-if="people" class="pstat__page">
      <header class="pstat__head">
        <p class="pstat__eyebrow">{{ t('pstats.peopleEyebrow') }}</p>
        <h2 class="pstat__title">{{ t('pstats.peopleTitle') }}</h2>
        <p class="pstat__lead">{{ t('pstats.peopleLead') }}</p>
      </header>

      <ul class="pstat__kpis pstat__kpis--three">
        <li v-for="kpi in peopleKpis" :key="kpi.id">
          <strong>{{ kpiText(kpi) }}</strong>
          <span>{{ tr(kpi.label) }}</span>
        </li>
      </ul>

      <section v-if="rates.length" class="pstat__block pstat__keepWhole">
        <h3 class="pstat__sub">{{ t('pstats.ratesTitle') }}</h3>
        <ol class="pstat__bars pstat__bars--rates">
          <li v-for="rate in rates" :key="rate.id">
            <span class="pstat__barLabel">{{ rate.label }}</span>
            <span class="pstat__barTrack">
              <span class="pstat__barFill" :style="{ inlineSize: rate.value + '%' }"></span>
            </span>
            <span class="pstat__barValue">{{ pct(rate.value) }}</span>
          </li>
        </ol>
      </section>

      <section v-if="salaries.length" class="pstat__block">
        <h3 class="pstat__sub">{{ t('pstats.salaryTitle') }}</h3>
        <p v-if="salaries.some((s) => s.here)" class="pstat__caption">
          {{ t('pstats.salaryHere') }}
        </p>
        <p class="pstat__axisNote">
          <span class="pstat__unit">{{ t('pstats.salaryUnit') }}</span>
          <span class="pstat__avgKey">
            <span class="pstat__avgMark"></span>
            {{ t('pstats.salaryAverage') }} · {{ salaryText(nationalAverage) }}
          </span>
        </p>

        <div class="pstat__salary">
          <!-- The average, drawn across the plot only: it is a value on the
               bars' axis, not a line across the page. -->
          <div class="pstat__guide">
            <div class="pstat__guideLine" :style="{ insetInlineStart: averageAt + '%' }"></div>
          </div>

          <ol class="pstat__bars pstat__bars--salary">
            <li v-for="row in salaries" :key="row.id" :class="{ 'pstat__row--here': row.here }">
              <span class="pstat__barLabel">{{ row.label }}</span>
              <span class="pstat__barTrack">
                <span
                  class="pstat__barFill"
                  :class="{ 'pstat__barFill--here': row.here }"
                  :style="{ inlineSize: (row.value / salaryMax) * 100 + '%' }"
                ></span>
              </span>
              <span class="pstat__barValue">{{ salaryText(row.value) }}</span>
            </li>
          </ol>
        </div>
      </section>
    </section>

    <!-- ======================================================= 3. the climate -->
    <section v-if="strategy" class="pstat__page">
      <header class="pstat__head">
        <p class="pstat__eyebrow">{{ t('pstats.futureEyebrow') }}</p>
        <h2 class="pstat__title">{{ t('pstats.futureTitle') }}</h2>
        <p class="pstat__lead">{{ tr(strategy.summary) }}</p>
      </header>

      <ul class="pstat__kpis pstat__kpis--three pstat__keepWhole">
        <li>
          <strong>{{ formatNumber(strategy.goals_total, lang) }}</strong>
          <span>{{ t('pstats.goalsWord') }}</span>
        </li>
        <li>
          <strong>{{ formatNumber(priorities.length, lang) }}</strong>
          <span>{{ t('pstats.prioritiesWord') }}</span>
        </li>
        <li>
          <strong class="pstat__kpiWord">{{ adopted }}</strong>
          <span>{{ t('pstats.adoptedWord') }}</span>
        </li>
      </ul>

      <section class="pstat__block">
        <h3 class="pstat__sub">{{ t('pstats.prioritiesTitle') }}</h3>
        <ol class="pstat__priorities">
          <li v-for="priority in priorities" :key="priority.n">
            <span class="pstat__roman">{{ priority.n }}</span>
            <div>
              <p class="pstat__priorityText">{{ priority.text }}</p>
              <ul v-if="priority.targets.length" class="pstat__targets">
                <li v-for="(target, i) in priority.targets" :key="i">{{ target }}</li>
              </ul>
            </div>
          </li>
        </ol>
      </section>

      <section v-if="zones" class="pstat__block pstat__keepWhole">
        <h3 class="pstat__sub">{{ t('pstats.zonesTitle') }}</h3>
        <ul class="pstat__zones">
          <li v-for="zone in zoneTypes" :key="zone">{{ zone }}</li>
        </ul>
        <ul class="pstat__incentives">
          <li v-for="(point, i) in incentives" :key="i">{{ point }}</li>
        </ul>
        <p class="pstat__note">{{ tr(zones.disclaimer) }}</p>
      </section>
    </section>
  </div>
</template>

<style scoped>
/* None of this is on screen. */
.pstat {
  display: none;
}

@media print {
  .pstat {
    display: block;
    color: #15193a;
    font-size: 9pt;
    line-height: 1.45;
  }

  /**
   * One page each.
   *
   * The page box has no margins — the cover and the closing page reach the
   * paper's edge — so the sides are paid for here, in the same measure the
   * reading uses, and the running head pays for the top.
   */
  .pstat__page {
    break-before: page;
    padding-inline: 14mm;
    padding-block-end: 4mm;
  }

  /* ------------------------------------------------------------- the head */

  .pstat__head {
    padding-block-end: 3mm;
    border-block-end: 0.4mm solid #15193a;
  }
  .pstat__eyebrow {
    margin: 0;
    font-size: 7.5pt;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #995f00;
  }
  .pstat__title {
    margin: 2mm 0 0;
    font-family: var(--font-display);
    font-size: 17pt;
    font-weight: 600;
    line-height: 1.16;
  }
  .pstat__lead {
    margin: 2mm 0 0;
    max-inline-size: 155mm;
    font-size: 9pt;
    line-height: 1.5;
    color: #3c4467;
  }

  .pstat__sub {
    margin: 0 0 2.5mm;
    font-size: 8pt;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #00566e;
    break-after: avoid;
  }

  .pstat__block {
    margin-block-start: 5mm;
  }
  /* Blocks small enough to be worth keeping whole rather than fragmenting. */
  .pstat__keepWhole {
    break-inside: avoid;
  }

  /* A caption that belongs to the chart under it, set above it so a page
     break cannot take it away from the chart. */
  .pstat__caption {
    margin: 0 0 1.5mm;
    font-size: 7pt;
    color: #626b8b;
  }

  .pstat__note {
    margin: 2.5mm 0 0;
    font-size: 7pt;
    line-height: 1.45;
    color: #626b8b;
  }

  /* ------------------------------------------------------- the number tiles */

  /**
   * The figures, as a ruled field rather than as boxes.
   *
   * Eight bordered cards is eight rectangles competing with the charts under
   * them; two hairlines and the type's own alignment do the same work and
   * leave the page quiet.
   */
  .pstat__kpis {
    margin: 4mm 0 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 3mm 6mm;
  }
  .pstat__kpis--three {
    grid-template-columns: repeat(3, 1fr);
  }
  .pstat__kpis li {
    padding-block-start: 1.4mm;
    border-block-start: 0.35mm solid #c9ccd8;
    break-inside: avoid;
  }
  .pstat__kpis strong {
    display: block;
    font-family: var(--font-display);
    font-size: 15pt;
    font-weight: 600;
    line-height: 1.1;
    font-variant-numeric: tabular-nums;
    letter-spacing: -0.01em;
  }
  /* A date where the others carry a number: set as text, not as a figure. */
  .pstat__kpiWord {
    font-size: 11pt !important;
    line-height: 1.25 !important;
  }
  .pstat__kpis span {
    display: block;
    margin-block-start: 1.4mm;
    font-size: 7.5pt;
    line-height: 1.35;
    color: #4a5070;
  }

  /* -------------------------------------------------------------- the ring */

  .pstat__gdp {
    break-inside: avoid;
  }
  .pstat__gdpCols {
    display: grid;
    grid-template-columns: 42mm 1fr;
    gap: 9mm;
    align-items: center;
  }

  .pstat__ring {
    position: relative;
    margin: 0;
    inline-size: 42mm;
    block-size: 42mm;
  }
  .pstat__ring svg {
    inline-size: 100%;
    block-size: 100%;
  }
  /* Colour is the whole point of a donut, so it is let through the browser's
     ink-saving default — as the photographs on the project page are. */
  .pstat__ringTrack,
  .pstat__ringArc {
    fill: none;
    stroke-width: 20;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pstat__ringTrack {
    stroke: #eef0f4;
  }
  .pstat__ringMiddle {
    position: absolute;
    inset-block: 0;
    inset-inline: 9mm;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1mm;
    text-align: center;
  }
  .pstat__ringMiddle strong {
    font-family: var(--font-display);
    font-size: 11pt;
    font-weight: 600;
    line-height: 1.05;
  }
  .pstat__ringMiddle span {
    font-size: 6pt;
    line-height: 1.3;
    color: #4a5070;
  }

  .pstat__legend {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .pstat__legend li {
    display: grid;
    grid-template-columns: 3.4mm 1fr auto;
    gap: 3mm;
    align-items: baseline;
    padding-block: 1.9mm;
    border-block-end: 0.3mm solid #e3e5ec;
  }
  .pstat__legend li:first-child {
    border-block-start: 0.3mm solid #e3e5ec;
  }
  .pstat__chip {
    inline-size: 3.4mm;
    block-size: 3.4mm;
    border-radius: 0.6mm;
    transform: translateY(0.4mm);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pstat__legendName {
    font-size: 9.5pt;
  }
  .pstat__legendShare {
    font-family: var(--font-display);
    font-size: 12pt;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
  }

  /* -------------------------------------------------------------- the bars */

  .pstat__bars {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .pstat__bars li {
    display: grid;
    grid-template-columns: 52mm 1fr 16mm;
    gap: 3mm;
    align-items: center;
    padding-block: 1.1mm;
    break-inside: avoid;
  }
  .pstat__barLabel {
    font-size: 7.5pt;
    line-height: 1.25;
    color: #3c4467;
  }
  .pstat__barTrack {
    position: relative;
    display: block;
    block-size: 3.4mm;
    background: #f1f2f6;
    border-radius: 0.5mm;
    overflow: hidden;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pstat__barFill {
    display: block;
    block-size: 100%;
    min-inline-size: 0.6mm;
    background: #006fb9;
    border-radius: 0.5mm;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pstat__barValue {
    text-align: end;
    font-size: 8.5pt;
    font-weight: 600;
    font-variant-numeric: tabular-nums;
    color: #15193a;
  }

  .pstat__bars--rates li {
    padding-block: 1.2mm;
  }

  /* ------------------------------------------------------ the salary chart */

  .pstat__axisNote {
    display: flex;
    justify-content: space-between;
    gap: 6mm;
    margin: 0 0 2.5mm;
    font-size: 7.5pt;
    color: #626b8b;
  }
  .pstat__unit {
    letter-spacing: 0.04em;
  }
  .pstat__avgKey {
    display: inline-flex;
    align-items: center;
    gap: 2mm;
    color: #995f00;
    font-weight: 600;
  }
  .pstat__avgMark {
    inline-size: 6mm;
    block-size: 0;
    border-block-start: 0.5mm dashed #995f00;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  .pstat__salary {
    position: relative;
  }
  /* The plot's own box: the same two tracks the rows reserve, so a percentage
     inside it is a percentage of the axis rather than of the page. */
  .pstat__guide {
    position: absolute;
    inset-block: 0;
    inset-inline-start: 55mm;
    inset-inline-end: 19mm;
  }
  .pstat__guideLine {
    position: absolute;
    inset-block: 0;
    border-inline-start: 0.4mm dashed #995f00;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pstat__bars--salary li {
    padding-block: 0.7mm;
  }
  .pstat__bars--salary .pstat__barTrack {
    block-size: 3mm;
    background: transparent;
  }
  .pstat__row--here .pstat__barLabel,
  .pstat__row--here .pstat__barValue {
    font-weight: 700;
    color: #00566e;
  }
  .pstat__barFill--here {
    background: #c18429;
  }

  /* ----------------------------------------------------------- the drivers */

  .pstat__drivers {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 6mm;
  }
  .pstat__drivers li {
    padding-inline-start: 3.5mm;
    border-inline-start: 0.6mm solid #c18429;
    break-inside: avoid;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pstat__drivers strong {
    display: block;
    font-family: var(--font-display);
    font-size: 13pt;
    font-weight: 600;
    line-height: 1.1;
  }
  .pstat__drivers span {
    display: block;
    margin-block-start: 1.4mm;
    font-size: 8pt;
    line-height: 1.35;
    color: #4a5070;
  }

  /* -------------------------------------------------------- the priorities */

  .pstat__priorities {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .pstat__priorities > li {
    display: grid;
    grid-template-columns: 11mm 1fr;
    gap: 4mm;
    padding-block: 2.1mm;
    border-block-start: 0.3mm solid #e3e5ec;
    break-inside: avoid;
  }
  .pstat__roman {
    font-family: var(--font-display);
    font-size: 13pt;
    font-weight: 600;
    line-height: 1.05;
    color: #c18429;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pstat__priorityText {
    margin: 0;
    font-size: 9.5pt;
    line-height: 1.35;
  }
  .pstat__targets {
    margin: 1.4mm 0 0;
    padding-inline-start: 4.5mm;
    font-size: 7.5pt;
    line-height: 1.35;
    color: #4a5070;
  }
  .pstat__targets li {
    margin-block-end: 0.5mm;
  }

  /* ---------------------------------------------------- zones and the terms */

  .pstat__zones {
    margin: 0;
    padding: 0;
    list-style: none;
    display: flex;
    flex-wrap: wrap;
    gap: 1.6mm;
  }
  .pstat__zones li {
    padding: 1mm 2.8mm;
    border: 0.3mm solid #c9ccd8;
    border-radius: 6mm;
    font-size: 8pt;
    color: #15193a;
  }
  .pstat__incentives {
    margin: 3mm 0 0;
    padding-inline-start: 5mm;
    font-size: 8pt;
    line-height: 1.4;
  }
  .pstat__incentives li {
    margin-block-end: 1mm;
    break-inside: avoid;
  }
}
</style>
