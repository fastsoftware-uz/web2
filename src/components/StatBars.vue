<script setup lang="ts">
/**
 * One column of the portfolio breakdown: a label, a bar and a count per row,
 * and every row is a filter.
 *
 * This replaced a beeswarm on a logarithmic axis. The beeswarm was accurate and
 * nobody could read it — it needed a legend for the dot size, a second one for
 * the axis, and a third for the lane of projects that had no dollar figure at
 * all. Three plain bar lists answer the three questions an investor actually
 * arrives with (where, what kind, how big) and, because each row filters, they
 * are faster than the controls above them as well.
 *
 * Bars are proportional to the largest row, not to the total: the point is to
 * compare the rows with each other, and against a total of seventy-four the
 * smaller ones would be invisible.
 */
import { computed } from 'vue';

export interface BarRow {
  id: string;
  label: string;
  count: number;
}

const props = defineProps<{
  title: string;
  rows: BarRow[];
  /** The row currently filtering the portfolio, or 'all'. */
  active: string;
}>();

const emit = defineEmits<{ pick: [id: string] }>();

const max = computed(() => Math.max(1, ...props.rows.map((r) => r.count)));
const shown = computed(() => props.rows.filter((r) => r.count > 0));
</script>

<template>
  <section class="bars">
    <h3 class="bars__title">{{ title }}</h3>
    <ul>
      <li v-for="row in shown" :key="row.id">
        <button
          type="button"
          :aria-pressed="active === row.id"
          @click="emit('pick', active === row.id ? 'all' : row.id)"
        >
          <span class="bars__label">{{ row.label }}</span>
          <span class="bars__track" aria-hidden="true">
            <span class="bars__fill" :style="{ inlineSize: `${(row.count / max) * 100}%` }" />
          </span>
          <span class="bars__count">{{ row.count }}</span>
        </button>
      </li>
    </ul>
  </section>
</template>

<style scoped>
.bars__title {
  font-family: var(--font-body);
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--ink-dim);
}

ul {
  list-style: none;
  margin: 0.9rem 0 0;
  padding: 0;
  display: grid;
  gap: 0.1rem;
}

button {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 4.5rem 2rem;
  align-items: center;
  gap: 0.75rem;
  width: 100%;
  padding: 0.42rem 0.5rem;
  border: 0;
  border-radius: 0.4rem;
  background: transparent;
  color: var(--ink-soft);
  font: inherit;
  font-size: 0.88rem;
  text-align: start;
  cursor: pointer;
  transition: background-color 0.16s, color 0.16s;
}
@media (pointer: coarse) {
  button {
    min-block-size: var(--tap);
  }
}
@media (hover: hover) {
  button:hover {
    background: var(--surface-2);
    color: var(--ink);
  }
}
button[aria-pressed='true'] {
  background: var(--surface-2);
  color: var(--ink);
  font-weight: 500;
}

.bars__label {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/*
 * On a phone the name goes above its bar instead of being cut off.
 *
 * The row is label | bar | count, and at 390px the label column is about 206
 * pixels while «Madaniy, tarixiy va meros asosidagi» wants 294 — so six of
 * the six directions ended in an ellipsis, which is the one place on the
 * page where the reader is choosing between them by name. Given the whole
 * width the names fit, and the bar and its count keep their own line
 * underneath.
 */
@media (max-width: 30rem) {
  button {
    grid-template-columns: minmax(0, 1fr) 2rem;
    row-gap: 0.3rem;
  }
  .bars__label {
    grid-column: 1 / -1;
    overflow: visible;
    text-overflow: clip;
    white-space: normal;
    line-height: 1.3;
  }
}

.bars__track {
  /* Same height and same ink as the region bars beside them: three bar charts
     on one screen, all counting the same seventy-four projects, drawn two
     different colours and two different weights read as three unrelated
     charts. Turquoise is what you can act on, not a data colour. */
  height: 0.35rem;
  border-radius: 999px;
  background: var(--hairline);
  overflow: hidden;
}
.bars__fill {
  display: block;
  block-size: 100%;
  border-radius: 999px;
  background: var(--accent-deep);
  opacity: 0.6;
  transition: opacity 0.16s;
}
button[aria-pressed='true'] .bars__fill {
  opacity: 1;
}
@media (hover: hover) {
  button:hover .bars__fill {
    opacity: 1;
  }
}

.bars__count {
  text-align: end;
  font-variant-numeric: tabular-nums;
  font-size: 0.85rem;
  color: var(--ink-dim);
}
button[aria-pressed='true'] .bars__count {
  color: var(--accent-soft);
}
</style>
