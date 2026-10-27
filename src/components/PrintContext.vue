<script setup lang="ts">
/**
 * The page after the reading: the portfolio this project came out of.
 *
 * One sheet on its own says nothing about how much else there is. This is the
 * whole portfolio counted twice — by place and by direction — so a reader who
 * likes the project can see where the rest of them are and ask for the ones
 * next to it.
 *
 * It used to be two columns of names and numbers. The places are now a map,
 * because "what else is near this" is a question about geography that a sorted
 * list cannot answer; the directions are bars, because six numbers that share
 * a total are a shape. The names stay beside the map: a reader who wants to
 * name a region should not have to recognise its outline.
 *
 * Everything is counted from the data file at build time, not typed here.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { tr, useStore } from '../lib/state';
import PrintMap from './PrintMap.vue';

const props = defineProps<{
  /** The project this sheet is about, so its own row can be marked. */
  region?: string | null;
  segment?: string | null;
}>();

const { t } = useI18n();
const { forum } = useStore();

const portfolio = computed(() => forum.value?.portfolio ?? null);

/** Regions, most projects first; the project's own region is marked. */
const regions = computed(() => {
  const p = portfolio.value;
  if (!p) return [];
  return Object.entries(p.regionCounts)
    .map(([id, n]) => ({
      id,
      n,
      name: p.regionNames[id] ? tr(p.regionNames[id]) : id,
      here: id === props.region,
    }))
    .sort((a, b) => b.n - a.n || a.name.localeCompare(b.name));
});

/** The six thematic directions, named from the themes the site shows. */
const directions = computed(() => {
  const p = portfolio.value;
  if (!p) return [];
  const themes = forum.value?.themes ?? [];
  return Object.entries(p.segmentCounts)
    .map(([id, n]) => {
      const theme = themes.find((x) => x.id === id);
      return { id, n, name: theme ? tr(theme.title) : id, here: id === props.segment };
    })
    .sort((a, b) => b.n - a.n || a.name.localeCompare(b.name));
});

const directionMax = computed(() => Math.max(1, ...directions.value.map((d) => d.n)));
</script>

<template>
  <section v-if="portfolio" class="ctx" aria-hidden="true">
    <p class="ctx__eyebrow">{{ t('print.contextEyebrow') }}</p>
    <h2 class="ctx__title">{{ t('print.contextTitle') }}</h2>
    <p class="ctx__lead">{{ t('print.contextLead', { n: portfolio.projects.length }) }}</p>

    <div class="ctx__top">
      <div>
        <PrintMap :counts="portfolio.regionCounts" :here="props.region" />
        <p class="ctx__credit">{{ t('print.mapCredit') }}</p>
      </div>

      <div>
        <h3 class="ctx__sub">{{ t('print.byRegion') }}</h3>
        <ol class="ctx__list">
          <li v-for="row in regions" :key="row.id" :class="{ 'ctx__row--here': row.here }">
            <span>{{ row.name }}</span>
            <span class="ctx__n">{{ row.n }}</span>
          </li>
        </ol>
      </div>
    </div>

    <div class="ctx__cols">
      <div>
        <h3 class="ctx__sub">{{ t('print.byDirection') }}</h3>
        <ol class="ctx__bars">
          <li v-for="row in directions" :key="row.id" :class="{ 'ctx__row--here': row.here }">
            <span class="ctx__barHead">
              <span class="ctx__barLabel">{{ row.name }}</span>
              <span class="ctx__n">{{ row.n }}</span>
            </span>
            <span class="ctx__barTrack">
              <span
                class="ctx__barFill"
                :class="{ 'ctx__barFill--here': row.here }"
                :style="{ inlineSize: (row.n / directionMax) * 100 + '%' }"
              ></span>
            </span>
          </li>
        </ol>
        <p class="ctx__note">{{ t('print.contextNote') }}</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.ctx {
  display: none;
}

@media print {
  .ctx {
    display: block;
    padding-inline: 14mm;
    padding-block-end: 8mm;
    padding-block-start: 3mm;
    /* Kept whole: split across a page break the map and its key read as two
       unrelated things. */
    break-inside: avoid;
    color: #15193a;
    font-size: 9pt;
  }

  .ctx__eyebrow {
    margin: 0;
    font-size: 7.5pt;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #995f00;
  }
  .ctx__title {
    margin: 2mm 0 0;
    font-family: var(--font-display);
    font-size: 15pt;
    font-weight: 600;
  }
  .ctx__lead {
    margin: 2.5mm 0 3mm;
    max-inline-size: 150mm;
    font-size: 9pt;
    line-height: 1.45;
    color: #3c4467;
  }

  /* The map takes the room it needs to stay legible at a glance; the names
     sit beside it rather than under it, so the two are read together. */
  .ctx__top {
    display: grid;
    grid-template-columns: 116mm 1fr;
    gap: 8mm;
    align-items: start;
    margin-block-start: 2mm;
  }
  .ctx__cols {
    margin-block-start: 5mm;
  }
  /* Six rows across two columns rather than six down one: the page is wide
     and the bars are short. */
  .ctx__bars {
    columns: 2;
    column-gap: 10mm;
  }
  .ctx__sub {
    margin: 0 0 2mm;
    font-size: 8pt;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #00566e;
    break-after: avoid;
  }

  /* The outlines are ODbL, and the licence's attribution has to travel with
     the drawing — a sheet is forwarded away from the site's own footer. */
  .ctx__credit {
    margin: 1.5mm 0 0;
    font-size: 6.5pt;
    line-height: 1.35;
    color: #7a81a0;
  }

  .ctx__list {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .ctx__list li {
    display: flex;
    justify-content: space-between;
    gap: 4mm;
    padding-block: 0.9mm;
    border-block-end: 1px solid #e3e5ec;
    font-size: 8.5pt;
  }
  .ctx__n {
    text-align: end;
    font-variant-numeric: tabular-nums;
    color: #4a5070;
  }

  .ctx__bars {
    margin: 0;
    padding: 0;
    list-style: none;
  }
  .ctx__bars li {
    display: grid;
    grid-template-columns: 1fr;
    gap: 1mm;
    padding-block: 1.4mm;
    border-block-end: 1px solid #e3e5ec;
    break-inside: avoid;
  }
  .ctx__barHead {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 4mm;
  }
  .ctx__barLabel {
    font-size: 8pt;
    line-height: 1.25;
  }
  .ctx__barTrack {
    position: relative;
    display: block;
    block-size: 2.6mm;
    background: #f1f2f6;
    border-radius: 0.5mm;
    overflow: hidden;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .ctx__barFill {
    display: block;
    block-size: 100%;
    min-inline-size: 0.6mm;
    background: #006fb9;
    border-radius: 0.5mm;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .ctx__barFill--here {
    background: #c18429;
  }

  /* The row this sheet belongs to, so the reader can place it. */
  .ctx__row--here {
    font-weight: 700;
    color: #00566e;
  }
  .ctx__row--here .ctx__n {
    color: #00566e;
  }
  .ctx__note {
    margin: 4mm 0 0;
    font-size: 7.5pt;
    line-height: 1.45;
    color: #4a5070;
  }
}
</style>
