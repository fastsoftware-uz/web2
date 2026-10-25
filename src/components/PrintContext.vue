<script setup lang="ts">
/**
 * The page after the reading: the portfolio this project came out of.
 *
 * One sheet on its own says nothing about how much else there is. This is
 * the whole portfolio counted two ways — by region and by direction — so a
 * reader who likes the project can see at a glance where the rest of them
 * are, and ask for the ones next to it.
 *
 * Both tables are counted from the data file at build time, not typed here.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { tr, useStore } from '../lib/state';

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
</script>

<template>
  <section v-if="portfolio" class="ctx" aria-hidden="true">
    <p class="ctx__eyebrow">{{ t('print.contextEyebrow') }}</p>
    <h2 class="ctx__title">{{ t('print.contextTitle') }}</h2>
    <p class="ctx__lead">{{ t('print.contextLead', { n: portfolio.projects.length }) }}</p>

    <div class="ctx__cols">
      <div>
        <h3 class="ctx__sub">{{ t('print.byRegion') }}</h3>
        <table class="ctx__table">
          <tbody>
            <tr v-for="row in regions" :key="row.id" :class="{ 'ctx__row--here': row.here }">
              <td>{{ row.name }}</td>
              <td class="ctx__n">{{ row.n }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <div>
        <h3 class="ctx__sub">{{ t('print.byDirection') }}</h3>
        <table class="ctx__table">
          <tbody>
            <tr v-for="row in directions" :key="row.id" :class="{ 'ctx__row--here': row.here }">
              <td>{{ row.name }}</td>
              <td class="ctx__n">{{ row.n }}</td>
            </tr>
          </tbody>
        </table>
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
    /* Kept whole: split across a page break the two tables read as four. */
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
    margin: 2.5mm 0 5mm;
    max-inline-size: 150mm;
    font-size: 9pt;
    line-height: 1.45;
    color: #3c4467;
  }

  .ctx__cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10mm;
    align-items: start;
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
  .ctx__table {
    inline-size: 100%;
    border-collapse: collapse;
  }
  .ctx__table td {
    padding-block: 1.15mm;
    border-block-end: 1px solid #e3e5ec;
    vertical-align: baseline;
  }
  .ctx__n {
    text-align: end;
    font-variant-numeric: tabular-nums;
    color: #4a5070;
  }
  /* The row this sheet belongs to, so the reader can place it. */
  .ctx__row--here td {
    font-weight: 700;
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
