<script setup lang="ts">
/**
 * The last page of a printed project sheet.
 *
 * The cover's pair: same ground turned over, the gold band at the head
 * instead of the middle. It carries the one thing a forwarded sheet is
 * missing — somewhere to write back to — and it carries it as the organising
 * committee, not as a person. The source decks closed on an officer's name
 * and personal mobile number; that is exactly what this project does not
 * publish, so the page gives the committee, its switchboard, its address and
 * the forum's own address, all of them from the data file.
 *
 * On screen it renders nothing; the block is `display: none` until print.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { eventDates, tr, useStore } from '../lib/state';
import ForumMark from './ForumMark.vue';
import ground from '../assets/print-closing.svg?url';

defineProps<{
  /** The heading in the band — "Contacts". */
  heading: string;
  /** One line on what to do next. */
  lead: string;
}>();

const { t } = useI18n();
const { forum } = useStore();

const contact = computed(() => forum.value?.contact ?? null);
const event = computed(() => forum.value?.event ?? null);
const dates = computed(() => {
  const e = event.value;
  return e ? `${eventDates()} · ${tr(e.city)}` : '';
});
</script>

<template>
  <section class="closing" aria-hidden="true">
    <div class="closing__sheet">
      <img class="closing__ground" :src="ground" alt="" />

      <div class="closing__band">
        <h2 class="closing__heading">{{ heading }}</h2>
        <ForumMark form="compact" variant="dark" class="closing__mark" />
      </div>

      <div v-if="contact" class="closing__body">
        <p class="closing__lead">{{ lead }}</p>

        <dl class="closing__rows">
        <div>
          <dt>{{ t('contact.organiser') }}</dt>
          <dd class="closing__org">{{ tr(contact.organisation) }}</dd>
        </div>
        <div>
          <dt>{{ t('contact.email') }}</dt>
          <dd>{{ contact.email }}</dd>
        </div>
        <div>
          <dt>{{ t('contact.phone') }}</dt>
          <dd>
            {{ contact.phone }}
            <span>{{ t('contact.shortPhone', { n: contact.shortPhone }) }}</span>
          </dd>
        </div>
        <div>
          <dt>{{ t('contact.address') }}</dt>
          <dd>{{ contact.postcode }}, {{ tr(contact.address) }}</dd>
        </div>
        </dl>
      </div>

      <div class="closing__foot">
        <p class="closing__event">{{ event ? tr(event.name) : '' }}</p>
        <p class="closing__dates">{{ dates }}<template v-if="event">· {{ tr(event.within) }}</template></p>
      </div>
    </div>
  </section>
</template>

<style scoped>
.closing {
  display: none;
}

@media print {
  /* The cover's arrangement exactly — see PrintCover.vue for why the page is
     built this way round. */
  .closing {
    position: relative;
    display: block;
    block-size: 296mm;
    break-before: page;
    color: #fff;
  }
  .closing__sheet {
    position: absolute;
    inset: 0;
    inline-size: 210mm;
    block-size: 297mm;
    overflow: hidden;
  }

  .closing__ground {
    position: absolute;
    inset: 0;
    inline-size: 210mm;
    block-size: 297mm;
  }

  .closing__sheet > *:not(.closing__ground) {
    position: absolute;
    margin: 0;
  }

  /* The band is drawn across the head of the page in the artwork. */
  .closing__band {
    inset-block-start: 0;
    inset-inline: 0;
    block-size: 34mm;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8mm;
    padding-inline: 18mm;
    color: #15193a;
  }
  .closing__heading {
    margin: 0;
    font-family: var(--font-display);
    font-size: 17pt;
    font-weight: 600;
  }
  /* The compact lockup is four units wide to three tall, and the emblem sits
     at the top of its box, so it is set narrow enough to keep its crown
     clear of the band's edge. */
  .closing__mark {
    inline-size: 29mm;
    flex: none;
    margin-block-start: 2mm;
  }

  .closing__body {
    inset-block-start: 84mm;
    inset-inline: 18mm;
  }
  .closing__lead {
    margin: 0 0 9mm;
    max-inline-size: 118mm;
    font-family: var(--font-display);
    font-size: 13pt;
    line-height: 1.4;
    color: #fff;
  }
  .closing__rows {
    margin: 0;
    display: grid;
    gap: 6mm;
  }
  .closing__rows div {
    display: grid;
    grid-template-columns: 34mm 1fr;
    gap: 4mm;
    align-items: baseline;
    padding-block-end: 4mm;
    border-block-end: 0.3mm solid rgb(255 255 255 / 0.18);
  }
  .closing__rows dt {
    font-family: var(--font-body);
    font-size: 7.5pt;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #a7b6dd;
  }
  .closing__rows dd {
    margin: 0;
    font-family: var(--font-body);
    font-size: 11pt;
    color: #fff;
  }
  .closing__org {
    font-family: var(--font-display);
    font-size: 12.5pt;
  }
  .closing__rows dd span {
    display: block;
    margin-block-start: 1.5mm;
    font-size: 8.5pt;
    color: #a7b6dd;
  }

  .closing__foot {
    inset-block-end: 24mm;
    inset-inline: 18mm;
  }
  .closing__event {
    margin: 0;
    font-family: var(--font-display);
    font-size: 11pt;
    color: #e4bb7d;
  }
  .closing__dates {
    margin-block-start: 2mm;
    font-family: var(--font-body);
    font-size: 8.5pt;
    color: #a7b6dd;
  }
}
</style>
