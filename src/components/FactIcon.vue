<script setup lang="ts">
/**
 * Line icons for the facts a project sheet states, and for the pages around
 * it — ThemeIcon draws the directions, ActionIcon draws the buttons, this
 * draws the figures.
 *
 * Same 24-unit grid and same 1.4 stroke as the other two, so the three sets
 * read as one hand. That is the whole reason they are drawn here rather than
 * installed: a borrowed set would sit next to the wordmark looking borrowed.
 *
 * They matter most on paper. A printed sheet is read by somebody holding four
 * of them, and a column of twelve label/value pairs is twelve lines that all
 * look alike until they are read; a glyph beside each one is what lets the eye
 * find the money, the plot and the payback without reading the other nine.
 *
 * A glyph is never the only thing saying what a row is — every one of them
 * sits beside its own label — so the set carries no meaning of its own and is
 * `aria-hidden` everywhere it appears.
 */
const props = defineProps<{ name: string }>();

const PATHS: Record<string, string> = {
  /* --- what a project states about itself --------------------------- */
  // A banknote with a coin on it.
  money: 'M2.5 6.5h19v11h-19v-11Zm9.5 7.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM5.5 9.5h.01m12.99 5h.01',
  // A plot seen from above, with its measure marks.
  area: 'M3.5 4.5h17v15h-17v-15Zm0 5h17m-12-5v15',
  // Two figures and a smaller one — a crowd, not a person.
  capacity:
    'M8.5 10.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8.5-.5a2.4 2.4 0 1 0 0-4.8 2.4 2.4 0 0 0 0 4.8ZM3 20v-1.2a5.5 5.5 0 0 1 11 0V20m2.5-7a4.8 4.8 0 0 1 4.5 4.8V20',
  // A briefcase.
  jobs: 'M3 8h18v11H3V8Zm6 0V6.2A2.2 2.2 0 0 1 11.2 4h1.6A2.2 2.2 0 0 1 15 6.2V8M3 13h18',
  // A clock, for anything measured in time.
  payback: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-13.5V12l3.5 2',
  // A bed.
  rooms: 'M3 19v-8m0 4h18v4m0 0v-4a3 3 0 0 0-3-3H3m4.5 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z',
  // Nodes joined by lines — the engineering networks a plot is or is not on.
  utilities:
    'M5 7.5a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm14 0a2 2 0 1 0 0-4 2 2 0 0 0 0 4Zm-7 13a2 2 0 1 0 0-4 2 2 0 0 0 0 4ZM7 5.5h10M6.2 7.2 11 15.8m6.8-8.6L13 15.8',
  // A plane over a runway.
  transport: 'm3 13 18-7-4.5 8.5L14 13l-2 5-1.5-4.5L3 13Zm2 7h14',
  // A map pin.
  location: 'M12 21.5s7-6 7-11.5a7 7 0 1 0-14 0c0 5.5 7 11.5 7 11.5Zm0-9a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z',
  // A flag on a pole — where the project has got to.
  stage: 'M6 21V3m0 0h11l-2.2 3.8L17 10.5H6',
  // A building with floors.
  building: 'M4 21V5l8-3 8 3v16M3 21h18M9 9h2m3 0h2M9 13h2m3 0h2M10 21v-4h4v4',
  // A line going up and to the right.
  growth: 'M4 19.5h16M6.5 16l4-5 3 3L19 7m0 0h-3.6M19 7v3.6',
  // Two overlapping rings — who is on the other side of the table.
  partner: 'M9.2 17.5a5 5 0 1 1 0-10 5 5 0 0 1 0 10Zm5.6 0a5 5 0 1 1 0-10 5 5 0 0 1 0 10Z',
  // A price tag.
  tag: 'M3.5 11.5v-8h8l9 9-8 8-9-9Zm4-5.5h.01',
  // A calendar.
  calendar: 'M4 6.5h16V21H4V6.5Zm0 4.5h16M8.5 3.5v4m7-4v4',
  // Stacked plates — floors, layers, tiers.
  layers: 'm12 3 9 4.8-9 4.8-9-4.8L12 3Zm9 9.2-9 4.8-9-4.8m18 4.4-9 4.8-9-4.8',

  /* --- the pages around the project --------------------------------- */
  // A globe with its parallels.
  globe: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0c2.6-2.4 3.9-5.4 3.9-9S14.6 5.4 12 3C9.4 5.4 8.1 8.4 8.1 12s1.3 6.6 3.9 9ZM3.3 9h17.4M3.3 15h17.4',
  // A ring with a dot — the target the state has set.
  target: 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0-4.6a4.4 4.4 0 1 0 0-8.8 4.4 4.4 0 0 0 0 8.8Zm0-3.4a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z',
  // A sheet with lines.
  document: 'M6 2.5h8l4 4v15H6v-19Zm8 0v4h4M9 12.5h6M9 16h6',
  // A gateway arch — the zones and the reliefs behind them.
  gate: 'M3 21V9.5L12 3l9 6.5V21M3 21h18M8.5 21v-6a3.5 3.5 0 0 1 7 0v6',

  /* --- how to write back -------------------------------------------- */
  // A handset.
  phone:
    'M7.2 3h3.4l1.5 3.9-2.2 1.6a12.4 12.4 0 0 0 5.6 5.6l1.6-2.2L21 13.4v3.4A2.2 2.2 0 0 1 18.6 19 16.6 16.6 0 0 1 5 5.4 2.2 2.2 0 0 1 7.2 3Z',
  // An envelope.
  mail: 'M3 6h18v12H3V6Zm0 0 9 7 9-7',
};

const d = () => PATHS[props.name] ?? PATHS.tag;
</script>

<template>
  <svg
    class="fact-icon"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.4"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
  >
    <path :d="d()" />
  </svg>
</template>

<style scoped>
.fact-icon {
  /* Sized from the type it sits beside, so it never looks bolted on. */
  inline-size: 1.25em;
  block-size: 1.25em;
  flex: 0 0 auto;
}
</style>
