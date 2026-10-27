<script setup lang="ts">
/**
 * The portfolio as a map of Uzbekistan: fourteen regions, each carrying the
 * number of projects it has brought to the forum. Choosing one filters the
 * portfolio to it.
 *
 * Why a map and not a bar chart. The chapter used to open on a beeswarm, which
 * nobody could read, and then on three columns of bars, which could be read but
 * said nothing a table would not. An investor's first question about a regional
 * portfolio is a question about places — where is this, what is near it, how
 * far is it from Tashkent — and a list of names cannot answer any of them. The
 * bars for direction and ticket size stay below: those are not places.
 *
 * The outlines are projected at build time (scripts/build-region-map.mjs) and
 * arrive as flat SVG paths, so nothing here does geography — this component
 * draws, counts and handles input. The geometry it shares with the printed
 * sheet's map lives in lib/region-map.ts.
 *
 * The drawing is a schematic of administrative boundaries from an open data
 * set, published under ODbL and credited in the footer. It is a diagram, not a
 * survey, and it asserts no boundary.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { type MapFile, discAt, fetchRegionMap } from '../lib/region-map';

export interface MapRegion {
  id: string;
  name: string;
  count: number;
  /** "14 ta loyiha" — pluralised by the caller, which has the i18n. */
  countText: string;
}

const props = defineProps<{
  regions: MapRegion[];
  /** The region currently filtering the portfolio, or 'all'. */
  active: string;
  /** Accessible name for the figure as a whole. */
  caption: string;
  emptyLabel: string;
}>();

const emit = defineEmits<{ pick: [id: string] }>();

const figure = ref<HTMLElement | null>(null);
const map = ref<MapFile | null>(null);
const failed = ref(false);
const hovered = ref<string | null>(null);

/**
 * Fetched when the reader gets near, not with the rest of the content.
 *
 * The chapter is four screens down. Loading the outlines up front costs the
 * title card a fetch it does not need and a few hundred milliseconds of main
 * thread parsing thirty kilobytes of path data — measurably, in total blocking
 * time. The observer is given a screen and a half of warning, which is more
 * than enough at any scroll speed a person produces.
 *
 * An observer is safe here in a way it was not for the counters: those had to
 * notice an element the reader had already jumped past, and a crossing that
 * never happened was never reported. This one only has to notice that the map
 * is near, and an element that is already on screen when the observer starts
 * is reported straight away.
 *
 * If the file never arrives the region list beside the map still works, so
 * this is the one fetch on the page allowed to fail quietly.
 */
async function load(): Promise<void> {
  try {
    map.value = await fetchRegionMap();
  } catch {
    failed.value = true;
  }
}

let watcher: IntersectionObserver | null = null;

onMounted(() => {
  if (typeof IntersectionObserver === 'undefined' || !figure.value) {
    void load();
    return;
  }
  watcher = new IntersectionObserver(
    (entries) => {
      if (!entries.some((e) => e.isIntersecting)) return;
      watcher?.disconnect();
      watcher = null;
      void load();
    },
    { rootMargin: '150% 0px' },
  );
  watcher.observe(figure.value);
});

onBeforeUnmount(() => {
  watcher?.disconnect();
  watcher = null;
});

const countOf = computed(() => new Map(props.regions.map((r) => [r.id, r.count])));
const byId = computed(() => new Map(props.regions.map((r) => [r.id, r])));
const busiest = computed(() => Math.max(1, ...props.regions.map((r) => r.count)));

/**
 * How dark a region is painted.
 *
 * The square root rather than the count itself: Tashkent has seven times the
 * projects of Andijan, and on a linear ramp everything but the capital comes
 * out the colour of the paper. The scale is read off the discs anyway — the
 * fill is there to show the shape of the distribution, not to be measured.
 */
function shade(id: string): number {
  const n = countOf.value.get(id) ?? 0;
  if (!n) return 0;
  return 0.16 + 0.84 * Math.sqrt(n / busiest.value);
}

const isActive = (id: string) => props.active === id;
const isLit = (id: string) => hovered.value === id || props.active === id;

/** The counter discs are drawn at this radius in the map's own units. */
const DISC_RADIUS = 17;

/**
 * The radius each disc is *pressed* at, which is not the radius it is drawn
 * at — and which is zero unless the screen is being touched.
 *
 * A 1000-unit map on a 390-pixel screen scales by 0.39, so a disc drawn at 22
 * units is a 17-pixel target, a third of what a thumb needs. The country is
 * twice as wide as it is tall and has fourteen regions in it, so no honest
 * drawing of it gives every region a 44-pixel target on a phone; the list
 * below the map is the control that can, and it is the reason this map is
 * allowed to stay a map. An invisible circle over each disc closes most of
 * the rest of the gap.
 *
 * It is only there under a thumb. The circle sits above the outlines, so on a
 * desktop — where the outlines are large and precise and a 34-unit disc is
 * already an easy target — it would do nothing but swallow clicks meant for
 * the region underneath it. The radius is set in CSS for that reason; the
 * value here only has to be large enough for the CSS to reduce.
 *
 * At 44 the target comes out around 36 pixels across on a 412-pixel screen —
 * short of the 44 a platform asks for, which is the geometry and not a choice,
 * and twice what the drawn disc gives. Callout discs are at least 100 units
 * apart, so at this radius they still do not take each other's area.
 */
const HIT_RADIUS = 44;

function describe(id: string): string {
  const region = byId.value.get(id);
  if (!region) return id;
  return `${region.name} — ${region.count ? region.countText : props.emptyLabel}`;
}

function choose(id: string): void {
  if (!(countOf.value.get(id) ?? 0)) return;
  emit('pick', isActive(id) ? 'all' : id);
}
</script>

<template>
  <figure v-if="!failed" ref="figure" class="uzmap" :class="{ 'uzmap--ready': map }">
    <svg v-if="map" :viewBox="map.viewBox" role="group" :aria-label="caption">
      <defs>
        <!-- The chosen region is filled with the same girih the page is set
             in, at a weight that reads as a texture rather than a pattern. -->
        <pattern id="uzmap-girih" width="60" height="60" patternUnits="userSpaceOnUse">
          <rect width="60" height="60" fill="var(--map-active)" />
          <path
            d="M30 2 36 18 52 11 45 27 60 30 45 33 52 49 36 42 30 58 24 42 8 49 15 33 0 30 15 27 8 11 24 18Z"
            fill="none"
            stroke="var(--map-girih)"
            stroke-width="1.1"
          />
        </pattern>
      </defs>

      <g class="uzmap__regions">
        <path
          v-for="region in map.regions"
          :key="region.id"
          class="uzmap__region"
          :class="{
            'uzmap__region--on': isActive(region.id),
            'uzmap__region--empty': !countOf.get(region.id),
          }"
          :d="region.d"
          :style="{ '--shade': shade(region.id) }"
          :tabindex="countOf.get(region.id) ? 0 : -1"
          :role="countOf.get(region.id) ? 'button' : 'img'"
          :aria-pressed="countOf.get(region.id) ? isActive(region.id) : undefined"
          @click="choose(region.id)"
          @keydown.enter.prevent="choose(region.id)"
          @keydown.space.prevent="choose(region.id)"
          @pointerenter="hovered = region.id"
          @pointerleave="hovered = null"
          @focus="hovered = region.id"
          @blur="hovered = null"
        >
          <title>{{ describe(region.id) }}</title>
        </path>
      </g>

      <!-- Counts, drawn after every outline so no border crosses a number. -->
      <g class="uzmap__discs" aria-hidden="true">
        <template v-for="region in map.regions" :key="`d-${region.id}`">
          <template v-if="countOf.get(region.id)">
            <path v-if="discAt(region).leader" class="uzmap__leader" :d="discAt(region).leader!" />
            <g
              class="uzmap__disc"
              :class="{ 'uzmap__disc--on': isLit(region.id) }"
              @click="choose(region.id)"
              @pointerenter="hovered = region.id"
              @pointerleave="hovered = null"
            >
              <circle
                class="uzmap__hit"
                :cx="discAt(region).x"
                :cy="discAt(region).y"
                :r="HIT_RADIUS"
              />
              <circle :cx="discAt(region).x" :cy="discAt(region).y" :r="DISC_RADIUS" />
              <text :x="discAt(region).x" :y="discAt(region).y">{{ countOf.get(region.id) }}</text>
            </g>
          </template>
        </template>
      </g>
    </svg>

    <!-- Reserves the map's height before it arrives, so chapter IV does not
         jump a screen downwards mid-scroll. -->
    <div v-else class="uzmap__wait" aria-hidden="true" />

    <!-- The caption used to be printed here under the map and the client
         took it off: fourteen numbered markers on a map of Uzbekistan do not
         need a sentence explaining that the numbers are counts. It is still
         the map's accessible name on the <svg> above, where it is the only
         thing a screen reader has to go on, and the line still appears on
         hover, naming the region under the pointer. The row keeps its height
         either way so the page does not jump when you point at something. -->
    <figcaption>
      <span v-if="hovered" class="uzmap__hint">{{ describe(hovered) }}</span>
    </figcaption>
  </figure>
</template>

<style scoped>
.uzmap {
  margin: 0;
  position: relative;
  /* Read by the girih pattern in <defs>, which inherits from the root rather
     than from the paths that use it. */
  --map-active: var(--accent-deep);
  --map-girih: var(--gold-leaf);
}

svg {
  display: block;
  width: 100%;
  height: auto;
  overflow: visible;
  /* The outlines arrive a moment after the chapter does. A CSS fade rather
     than a GSAP timeline: the chapter's timeline is built on mount, when
     there is nothing here yet to animate. */
  animation: map-in 0.55s ease-out both;
}
@keyframes map-in {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
}

/**
 * The map before its outlines arrive.
 *
 * A dashed box is what a developer leaves behind, not what a reader should
 * meet. This is a panjara — a carved window screen — which is the one thing
 * on this page that means "an opening that is not open yet". It reserves
 * exactly the height the map will take, so nothing moves when it arrives, and
 * for the second it is on screen it is worth looking at.
 */
.uzmap__wait {
  aspect-ratio: 1000 / 560;
  border-radius: 0.3rem;
  background-color: var(--surface-2);
  background-image: var(--ornament-panjara);
  background-size: var(--ornament-panjara-size);
  opacity: 0.5;
}

/* The fill carries the count; the shade is set per path as a custom property
   and mixed here, so one rule covers all fourteen. */
.uzmap__region {
  fill: color-mix(in srgb, var(--accent-deep) calc(var(--shade, 0) * 100%), var(--surface-2));
  stroke: var(--surface);
  stroke-width: 1.4;
  stroke-linejoin: round;
  cursor: pointer;
  transition: fill 0.2s, stroke 0.2s;
}
@media (hover: hover) {
  .uzmap__region:hover {
    fill: color-mix(in srgb, var(--accent-deep) calc(var(--shade, 0) * 100% + 14%), var(--surface-2));
  }
}
.uzmap__region--on {
  fill: url(#uzmap-girih);
  stroke: var(--gold-leaf);
  stroke-width: 2.2;
}
/* A region with nothing in the portfolio is still drawn — the country is not
   edited to match the data — but it is not a control. */
.uzmap__region--empty {
  fill: var(--surface-2);
  stroke: var(--hairline);
  cursor: default;
}
/**
 * No box around a chosen region.
 *
 * A focused <path> gets the browser's own ring, and a ring around a path is
 * drawn around its bounding box — a rectangle over the map, which is what
 * clicking Karakalpakstan put there. The region already says it is chosen:
 * it is filled with the lattice and outlined in gold. Keyboard focus keeps a
 * ring, but one that follows the border rather than a rectangle. */
.uzmap__region:focus {
  outline: none;
}
.uzmap__region:focus-visible {
  outline: none;
  stroke: var(--gold-leaf);
  stroke-width: 3;
  paint-order: stroke;
}

.uzmap__leader {
  fill: none;
  stroke: var(--hairline-strong);
  stroke-width: 1.2;
}

/* Pressed, never seen. Painted rather than left without a fill, because an
   SVG shape with no fill is not hit-tested at all — and collapsed to nothing
   where there is a pointer that can aim. */
.uzmap__hit {
  r: 0;
  fill: transparent;
  stroke: none;
}
@media (pointer: coarse) {
  .uzmap__hit {
    r: 44;
  }
}

.uzmap__disc circle:not(.uzmap__hit) {
  fill: var(--surface);
  stroke: var(--gold-leaf);
  stroke-width: 1.6;
  transition: fill 0.2s, stroke-width 0.2s;
}
.uzmap__disc text {
  fill: var(--ink);
  font-family: var(--font-display);
  font-size: 19px;
  font-weight: 600;
  font-variant-numeric: tabular-nums;
  text-anchor: middle;
  dominant-baseline: central;
  pointer-events: none;
  transition: fill 0.2s;
}
.uzmap__disc {
  cursor: pointer;
}
.uzmap__disc--on circle:not(.uzmap__hit) {
  fill: var(--gold-leaf);
  stroke-width: 2.4;
}
.uzmap__disc--on text {
  fill: var(--on-gold);
}

figcaption {
  margin-block-start: 0.9rem;
  min-block-size: 1.4rem;
  font-size: 0.85rem;
  color: var(--ink-dim);
}
.uzmap__hint {
  font-variant-numeric: tabular-nums;
}

/* Below this the valley regions are a few pixels across; the discs are then
   the only thing that can be hit, so they are grown rather than the map. */
@media (max-width: 40rem) {
  .uzmap__disc circle:not(.uzmap__hit) {
    r: 22;
  }
  .uzmap__disc text {
    font-size: 24px;
  }
}

/* A phone cannot spare the gutter. The country is twice as wide as it is
   tall, so on a narrow screen every pixel of width is a pixel of map — the
   drawing runs to both edges and only the caption keeps the column the rest
   of the chapter is set in. */
@media (max-width: 47.99rem) {
  .uzmap {
    margin-inline: calc(var(--gutter) * -1);
  }
  .uzmap figcaption {
    padding-inline: var(--gutter);
  }
}
</style>
