<script setup lang="ts">
/**
 * The first page of a printed project sheet.
 *
 * A sheet that leaves this site is forwarded on: it lands in an inbox, gets
 * printed by somebody who has never seen the page it came from, and is put in
 * front of a board. So it opens the way a proposal opens — the organiser's
 * mark, the project's name in a band of its own, and the forum it belongs to
 * at the foot — and only then does the reading start.
 *
 * On screen this renders nothing at all: the whole block is `display: none`
 * until `@media print`.
 *
 * The ground is an image (see print-cover.svg) and the type is HTML over it,
 * which is what keeps the name selectable, hyphenated by the browser and set
 * in whichever of the three languages the reader is in.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { eventDates, tr, useStore } from '../lib/state';
import ForumMark from './ForumMark.vue';
import ground from '../assets/print-cover.svg?url';

defineProps<{
  /** What the document is — "Investment proposal". */
  kicker: string;
  /** The project, in the reader's language. */
  title: string;
  /** Region · direction. */
  meta?: string | null;
  /** The figure, already formatted, or the stated wording when there is none. */
  money?: string | null;
  moneyLabel: string;
}>();

const { t } = useI18n();
const { forum } = useStore();

const event = computed(() => forum.value?.event ?? null);
/** The organiser, from the data rather than the copy: it is a name. */
const organisation = computed(() => {
  const c = forum.value?.contact;
  return c ? tr(c.organisation) : '';
});
const dates = computed(() => {
  const e = event.value;
  return e ? `${eventDates()} · ${tr(e.city)}, ${tr(e.country)}` : '';
});
</script>

<template>
  <section class="cover" aria-hidden="true">
    <!-- The sheet of paper itself: it steps out of the page box by exactly
         the page margins, so everything inside it is placed in the artwork's
         own coordinates. -->
    <div class="cover__sheet">
      <img class="cover__ground" :src="ground" alt="" />

      <ForumMark form="lockup" variant="white" class="cover__mark" />

      <div class="cover__band">
        <p class="cover__kicker">{{ kicker }}</p>
        <h1 class="cover__title">{{ title }}</h1>
      </div>

      <div class="cover__meta">
        <p v-if="meta" class="cover__where">{{ meta }}</p>
        <p v-if="money" class="cover__money">
          <span>{{ moneyLabel }}</span>
          <strong>{{ money }}</strong>
        </p>
      </div>

      <div class="cover__foot">
        <p class="cover__event">{{ event ? tr(event.name) : '' }}</p>
        <p class="cover__dates">{{ dates }}</p>
        <p class="cover__org">{{ t('contact.organiser') }} · {{ organisation }}</p>
      </div>
    </div>
  </section>
</template>

<style scoped>
/* Nothing of this is on screen; everything below is the printed page. */
.cover {
  display: none;
}

@media print {
  /**
   * One page of the printed document, and the page box's own size.
   *
   * It would be simpler to give this page a margin-less @page box of its own
   * and set it to 210×297mm. Chrome prints that correctly and then lays the
   * pages that follow out against the wrong inline origin — the reading comes
   * out shifted off the left edge of the paper. So the block stays the size
   * of the page box, and the artwork reaches the paper by stepping out of it.
   */
  .cover {
    position: relative;
    display: block;
    block-size: 296mm; /* a hair under the page, so none spills over */
    break-after: page;
    color: #fff;
  }
  .cover__sheet {
    position: absolute;
    inset: 0;
    inline-size: 210mm;
    block-size: 297mm;
    overflow: hidden;
  }

  .cover__ground {
    position: absolute;
    inset: 0;
    inline-size: 210mm;
    block-size: 297mm;
  }

  .cover__sheet > *:not(.cover__ground) {
    position: absolute;
    margin: 0;
  }

  .cover__mark {
    inset-block-start: 15mm;
    inset-inline-start: 18mm;
    inline-size: 58mm;
  }

  /* The band is drawn at 118mm in the artwork; the type is centred in it. */
  .cover__band {
    inset-block-start: 118mm;
    inset-inline: 0;
    block-size: 54mm;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2.5mm;
    padding-inline: 20mm;
    text-align: center;
    color: #15193a;
  }
  .cover__kicker {
    margin: 0;
    font-family: var(--font-body);
    font-size: 8.5pt;
    font-weight: 700;
    letter-spacing: 0.22em;
    text-transform: uppercase;
    color: #4a3410;
  }
  .cover__title {
    margin: 0;
    font-family: var(--font-display);
    font-size: 23pt;
    font-weight: 600;
    line-height: 1.16;
    letter-spacing: -0.01em;
    text-wrap: balance;
  }

  .cover__meta {
    inset-block-start: 180mm;
    inset-inline: 18mm;
    text-align: center;
  }
  .cover__where {
    margin: 0;
    font-family: var(--font-body);
    font-size: 10pt;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #c9d4ee;
  }
  .cover__money {
    margin-block-start: 6mm;
    display: flex;
    flex-direction: column;
    gap: 1.5mm;
  }
  .cover__money span {
    font-family: var(--font-body);
    font-size: 7.5pt;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #a7b6dd;
  }
  .cover__money strong {
    font-family: var(--font-display);
    font-size: 17pt;
    font-weight: 600;
    color: #e4bb7d;
  }

  .cover__foot {
    inset-block-end: 38mm;
    inset-inline: 18mm;
    text-align: center;
  }
  .cover__event {
    margin: 0;
    font-family: var(--font-display);
    font-size: 12pt;
    font-weight: 600;
    color: #fff;
  }
  .cover__dates,
  .cover__org {
    margin-block-start: 2mm;
    font-family: var(--font-body);
    font-size: 8.5pt;
    color: #a7b6dd;
  }
  .cover__org {
    font-size: 7.5pt;
    letter-spacing: 0.04em;
  }
}
</style>
