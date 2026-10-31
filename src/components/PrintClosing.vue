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
 * It also carries the forum itself, which used to have a page of its own as
 * page two, and the portfolio, which used to have one after the project.
 * Both went when the sheet was cut to seven. A reader who has not seen the
 * site still needs to know what the event is, when it is and how much else
 * there is behind this one sheet — but they need it after the project, next
 * to the address they would write to, not in front of it. And it belongs
 * here for a second reason: the one thing to do with "there are a hundred
 * and eleven more" is to ask for them, and this is the page that says where.
 *
 * It is also the one page in the document that cannot overflow. The reading
 * before it is as long as whatever a region wrote, so anything placed at the
 * end of it is at the mercy of where the page break lands; this page is a
 * fixed canvas, and the counts sit on it at the same height every time.
 *
 * Every row is a link as well as a line. A PDF read on a screen is the common
 * case now, and a telephone number that cannot be tapped, an address that
 * cannot be opened in a map and an e-mail that has to be retyped are three
 * ways of making somebody not get in touch.
 *
 * On screen it renders nothing; the block is `display: none` until print.
 */
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

import { formatMoney } from '../lib/money';
import { eventDates, tr, useStore } from '../lib/state';
import FactIcon from './FactIcon.vue';
import ForumMark from './ForumMark.vue';
import ground from '../assets/print-closing.svg?url';

defineProps<{
  /** The heading in the band — "Contacts". */
  heading: string;
  /** One line on what to do next. */
  lead: string;
  /** The project's own direction, so its row in the counts is marked. */
  segment?: string | null;
}>();

const { t } = useI18n();
const { forum, lang } = useStore();

const contact = computed(() => forum.value?.contact ?? null);
const event = computed(() => forum.value?.event ?? null);
const portfolio = computed(() => forum.value?.portfolio ?? null);

const dates = computed(() => {
  const e = event.value;
  return e ? `${eventDates()} · ${tr(e.city)}` : '';
});

/** What the forum is, in four lines — the old page two, in four lines. */
const facts = computed(() => {
  const e = event.value;
  const c = contact.value;
  if (!e) return [];
  return [
    { id: 'when', label: t('print.when'), value: `${eventDates()}, ${tr(e.city)}` },
    { id: 'within', label: t('print.within'), value: tr(e.within) },
    { id: 'organiser', label: t('print.organiser'), value: c ? tr(c.organisation) : '' },
    { id: 'languages', label: t('print.languages'), value: t('print.languagesValue') },
  ].filter((f) => f.value);
});

/**
 * The portfolio in four numbers.
 *
 * The total carries the site's own caveat: it adds the projects whose figure
 * is stated in dollars, and the sheets that state a sum in soums or leave it
 * open are counted in the first number but not added into the fourth.
 */
const numbers = computed(() => {
  const p = portfolio.value;
  if (!p) return [];
  return [
    { id: 'n', value: String(p.projects.length), label: t('print.nProjects') },
    { id: 'r', value: String(Object.keys(p.regionCounts).length), label: t('print.nRegions') },
    { id: 'd', value: String(Object.keys(p.segmentCounts).length), label: t('print.nDirections') },
    { id: 't', value: formatMoney(p.totalUsd, 'USD', lang.value), label: t('print.nTotal') },
  ];
});

/**
 * The portfolio by direction, the project's own marked.
 *
 * Six numbers that share a total, so they are a shape — but printed here as
 * counts rather than as bars: this is the foot of the contact page, not a
 * chart page, and the question it answers is "what else could I ask for".
 */
const directions = computed(() => {
  const p = portfolio.value;
  if (!p) return [];
  const themes = forum.value?.themes ?? [];
  return Object.entries(p.segmentCounts)
    .map(([id, n]) => {
      const theme = themes.find((x) => x.id === id);
      return { id, n, name: theme ? tr(theme.title) : id };
    })
    .sort((a, b) => b.n - a.n || a.name.localeCompare(b.name));
});

/** "41,3041 · 69,27863" — a position that survives being read off paper. */
const coords = computed(() => {
  const c = contact.value;
  if (!c?.lat || !c?.lon) return '';
  return `${c.lat.toFixed(5)} · ${c.lon.toFixed(5)}`;
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
            <dt><FactIcon name="building" /><span>{{ t('contact.organiser') }}</span></dt>
            <dd class="closing__org">{{ tr(contact.organisation) }}</dd>
          </div>
          <div>
            <dt><FactIcon name="mail" /><span>{{ t('contact.email') }}</span></dt>
            <dd><a :href="`mailto:${contact.email}`">{{ contact.email }}</a></dd>
          </div>
          <div>
            <dt><FactIcon name="phone" /><span>{{ t('contact.phone') }}</span></dt>
            <dd>
              <a :href="contact.phoneHref">{{ contact.phone }}</a>
              <span>{{ t('contact.shortPhone', { n: contact.shortPhone }) }}</span>
            </dd>
          </div>
          <div>
            <dt><FactIcon name="location" /><span>{{ t('contact.address') }}</span></dt>
            <dd>
              <a :href="contact.mapUrl">{{ contact.postcode }}, {{ tr(contact.address) }}</a>
              <span v-if="coords">{{ t('contact.coords') }} {{ coords }}</span>
            </dd>
          </div>
        </dl>

        <!-- What the event is, for a reader who has only ever seen this file. -->
        <div v-if="facts.length" class="closing__forum">
          <p class="closing__sub">{{ t('print.aboutTitle') }}</p>
          <dl class="closing__facts">
            <div v-for="fact in facts" :key="fact.id">
              <dt>{{ fact.label }}</dt>
              <dd>{{ fact.value }}</dd>
            </div>
          </dl>

          <p class="closing__sub closing__sub--tight">{{ t('print.portfolioTitle') }}</p>
          <ul v-if="numbers.length" class="closing__numbers">
            <li v-for="item in numbers" :key="item.id">
              <strong>{{ item.value }}</strong>
              <span>{{ item.label }}</span>
            </li>
          </ul>

          <p class="closing__sub closing__sub--tight">{{ t('print.byDirection') }}</p>
          <ul v-if="directions.length" class="closing__dirs">
            <li v-for="row in directions" :key="row.id" :class="{ 'closing__dir--here': row.id === segment }">
              <span>{{ row.name }}</span>
              <strong>{{ row.n }}</strong>
            </li>
          </ul>
          <p class="closing__note">{{ t('print.contextNote') }}</p>
        </div>
      </div>

      <div class="closing__foot">
        <p class="closing__event">{{ event ? tr(event.name) : '' }}</p>
        <p class="closing__dates">{{ dates }}<template v-if="event"> · {{ tr(event.within) }}</template></p>
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

  /*
   * 57mm, not 84: the page carries the forum and the portfolio as well as
   * the address now. Everything below is set to the measure Russian needs —
   * it runs about a tenth longer than the other two languages and this page
   * has a fixed floor, so it is Russian that decides the type sizes here.
   */
  .closing__body {
    inset-block-start: 57mm;
    inset-inline: 18mm;
  }
  .closing__lead {
    margin: 0 0 5mm;
    max-inline-size: 118mm;
    font-family: var(--font-display);
    font-size: 11.5pt;
    line-height: 1.35;
    color: #fff;
  }
  .closing__rows {
    margin: 0;
    display: grid;
    gap: 3mm;
  }
  .closing__rows div {
    display: grid;
    grid-template-columns: 38mm 1fr;
    gap: 4mm;
    align-items: baseline;
    padding-block-end: 2.2mm;
    border-block-end: 0.3mm solid rgb(255 255 255 / 0.18);
  }
  .closing__rows dt {
    display: flex;
    align-items: center;
    gap: 2.5mm;
    font-family: var(--font-body);
    font-size: 7.5pt;
    font-weight: 700;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #a7b6dd;
  }
  /* The glyph is the one piece of this page the eye lands on before it reads:
     gold on navy, at the size of the label it sits beside. */
  .closing__rows dt svg {
    inline-size: 4.6mm;
    block-size: 4.6mm;
    color: #e4bb7d;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .closing__rows dd {
    margin: 0;
    font-family: var(--font-body);
    font-size: 10pt;
    color: #fff;
  }
  /* A link in a PDF is a link; it should not also be blue and underlined. */
  .closing__rows a {
    color: inherit;
    text-decoration: none;
  }
  .closing__org {
    font-family: var(--font-display);
    font-size: 11.5pt;
  }
  .closing__rows dd span {
    display: block;
    margin-block-start: 1mm;
    font-size: 8pt;
    color: #a7b6dd;
  }

  /* ------------------------------------------------- what the forum is */

  .closing__forum {
    margin-block-start: 6mm;
    padding-block-start: 4mm;
    border-block-start: 0.4mm solid rgb(255 255 255 / 0.28);
  }
  .closing__sub {
    margin: 0 0 2.5mm;
  }
  .closing__sub--tight {
    margin: 4mm 0 2mm;
    font-size: 7.5pt;
    font-weight: 700;
    letter-spacing: 0.2em;
    text-transform: uppercase;
    color: #e4bb7d;
  }
  .closing__facts {
    margin: 0;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2.6mm 10mm;
  }
  .closing__facts dt {
    font-size: 7pt;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #8fa0cb;
  }
  .closing__facts dd {
    margin: 0.8mm 0 0;
    font-size: 9pt;
    line-height: 1.3;
    color: #fff;
  }
  .closing__numbers {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 4mm;
  }
  .closing__numbers li {
    padding: 2mm 3mm;
    border: 0.3mm solid rgb(255 255 255 / 0.28);
    border-radius: 1mm;
    text-align: center;
  }
  .closing__numbers strong {
    display: block;
    font-family: var(--font-display);
    font-size: 12pt;
    font-weight: 600;
    line-height: 1.1;
    color: #fff;
  }
  .closing__numbers span {
    display: block;
    margin-block-start: 1.2mm;
    font-size: 7pt;
    color: #a7b6dd;
  }

  /* The six directions, as counts. */
  .closing__dirs {
    margin: 0;
    padding: 0;
    list-style: none;
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1.2mm 8mm;
  }
  .closing__dirs li {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 3mm;
    padding-block-end: 1mm;
    border-block-end: 0.25mm solid rgb(255 255 255 / 0.18);
    font-size: 7.5pt;
    line-height: 1.25;
    color: #d6dcef;
  }
  .closing__dirs strong {
    font-variant-numeric: tabular-nums;
    font-weight: 600;
    color: #fff;
  }
  /* The row this sheet belongs to, so the reader can place it. */
  .closing__dir--here {
    color: #e4bb7d;
  }
  .closing__dir--here strong {
    color: #e4bb7d;
  }
  .closing__note {
    margin: 2.5mm 0 0;
    font-size: 6.8pt;
    line-height: 1.35;
    color: #8fa0cb;
  }

  /* 21mm, not 16: in Russian the second line wraps, and at 16 the wrap
     landed below the foot rule the rest of the document keeps to. */
  .closing__foot {
    inset-block-end: 21mm;
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
