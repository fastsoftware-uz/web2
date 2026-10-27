<script setup lang="ts">
/**
 * The portfolio as a map, for paper.
 *
 * The page it sits on used to answer "where are the other eighty-five" with
 * two columns of names and numbers. A reader deciding whether to ask for more
 * sheets is asking a question about places — what else is near Bukhara, is
 * anything in the valley, how far is any of it from the capital — and a sorted
 * list cannot answer one of them. A map answers all three without being read.
 *
 * It is the same geometry the site's own map draws (lib/region-map.ts), so
 * the two cannot disagree, and it is drawn as plain SVG paths, so it prints as
 * vector rather than as a screenshot of a chart.
 *
 * The fetch is made on mount rather than when something scrolls into view:
 * this is inside a sheet that is `display: none` until the print dialog opens,
 * where no observer will ever fire. If the file does not arrive the map is
 * simply absent and the counts beside it still say everything, which is the
 * same bargain the page version makes.
 */
import { computed, onMounted, ref } from 'vue';

import { type MapFile, discAt, fetchRegionMap } from '../lib/region-map';

const props = defineProps<{
  /** Projects per region id, as the portfolio counts them. */
  counts: Record<string, number>;
  /** The region this sheet's project is in, to be marked. */
  here?: string | null;
}>();

const map = ref<MapFile | null>(null);

onMounted(async () => {
  try {
    map.value = await fetchRegionMap();
  } catch {
    map.value = null;
  }
});

const busiest = computed(() => Math.max(1, ...Object.values(props.counts)));

/**
 * The ink a region is given, between the lightest fill that still reads as a
 * fill and the darkest the counter can sit on.
 *
 * On a square-root ramp rather than a linear one: the capital has twenty-two
 * projects and the smallest regions two, and a linear ramp spends almost its
 * whole range on that one region and leaves the other twelve indistinguishable.
 */
const RAMP_FROM = [222, 234, 246];
const RAMP_TO = [0, 79, 132];

function fill(id: string): string {
  const n = props.counts[id] ?? 0;
  if (!n) return '#f3f4f8';
  const t = 0.18 + 0.82 * Math.sqrt(n / busiest.value);
  const mix = RAMP_FROM.map((from, i) => Math.round(from + (RAMP_TO[i]! - from) * t));
  return `rgb(${mix.join(' ')})`;
}

const DISC_R = 22;
</script>

<template>
  <figure v-if="map" class="pmap" aria-hidden="true">
    <svg :viewBox="map.viewBox" role="presentation">
      <g class="pmap__regions">
        <path
          v-for="region in map.regions"
          :key="region.id"
          class="pmap__region"
          :class="{ 'pmap__region--here': region.id === props.here }"
          :d="region.d"
          :style="{ fill: region.id === props.here ? '#c18429' : fill(region.id) }"
        />
      </g>

      <!-- Counters last, so no outline is drawn across a number. -->
      <g class="pmap__discs">
        <template v-for="region in map.regions" :key="`d-${region.id}`">
          <template v-if="counts[region.id]">
            <path v-if="discAt(region).leader" class="pmap__leader" :d="discAt(region).leader!" />
            <circle
              class="pmap__disc"
              :cx="discAt(region).x"
              :cy="discAt(region).y"
              :r="DISC_R"
            />
            <text
              class="pmap__count"
              :x="discAt(region).x"
              :y="discAt(region).y"
            >
              {{ counts[region.id] }}
            </text>
          </template>
        </template>
      </g>
    </svg>
  </figure>
</template>

<style scoped>
.pmap {
  display: none;
}

@media print {
  .pmap {
    display: block;
    margin: 0;
  }
  .pmap svg {
    inline-size: 100%;
    block-size: auto;
  }

  /* A map is colour doing the work of a number, so it is let through the
     browser's ink-saving default — and every region carries its count as a
     figure anyway, so nothing here has to be read as a colour. */
  .pmap__region {
    stroke: #ffffff;
    stroke-width: 2.2;
    stroke-linejoin: round;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pmap__region--here {
    stroke: #15193a;
    stroke-width: 3;
  }

  .pmap__leader {
    fill: none;
    stroke: #8e95ad;
    stroke-width: 1.6;
    stroke-dasharray: 5 4;
  }
  .pmap__disc {
    fill: #ffffff;
    stroke: #15193a;
    stroke-width: 1.6;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .pmap__count {
    font-family: var(--font-display);
    font-size: 26px;
    font-weight: 600;
    text-anchor: middle;
    dominant-baseline: central;
    fill: #15193a;
  }
}
</style>
