<script setup lang="ts">
/**
 * The full reading of one project, as an overlay.
 *
 * Everything shown here is lifted straight out of the sheet the organiser
 * supplied, in the sheet's own words: the labels are not translated and not
 * renamed, because three generations of the template use different ones and
 * mapping them onto a fixed list is how a project loses the one figure that
 * made it interesting. Fields a sheet left blank are simply absent, and the
 * investment figure carries the sheet's own wording in its title so a reader
 * can see what was actually written.
 *
 * The sheet is a frame rather than one long scroll: a bar at the top that
 * stays put while the reading moves under it, and a bar at the bottom holding
 * the two things a reader can do with a project — say they are interested in
 * it, and send it to somebody. Those were the sheet's missing half. A
 * catalogue that can only be read is a brochure; the point of putting
 * seventy-four projects on a page is that one of them starts a conversation.
 *
 * It is a side sheet on a wide screen and a bottom sheet on a phone, which is
 * not a style choice: a drawer that arrives from the right is reached with a
 * mouse, and one that arrives from the bottom is reached with a thumb.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import type { Project } from '../lib/forum-types';
import { lockPageScroll, unlockPageScroll } from '../lib/motion';
import { formatMoney } from '../lib/money';
import { sheetIcon } from '../lib/sheet-icons';
import { sheetLabel } from '../lib/sheet-labels';
import { sheetValue } from '../lib/sheet-values';
import { eventDates, tr, useStore } from '../lib/state';
import FactIcon from './FactIcon.vue';
import ForumMark from './ForumMark.vue';
import PrintMap from './PrintMap.vue';
import PrintStats from './PrintStats.vue';
import PrintCover from './PrintCover.vue';
import PrintClosing from './PrintClosing.vue';

const props = defineProps<{
  project: Project | null;
  /** Where this project sits in the list behind the sheet, 1-based. */
  index: number;
  total: number;
}>();
const emit = defineEmits<{ close: []; step: [delta: number]; enquire: [] }>();

const { t } = useI18n();
const { forum, lang } = useStore();

/** The forum's own line, for the printed letterhead. */
const eventLine = computed(() => {
  const event = forum.value?.event;
  return event ? `${eventDates()} · ${tr(event.city)}` : '';
});

/**
 * The running foot: who to write to, on every page.
 *
 * The letterhead says which forum the sheet came from and the closing page
 * says how to reach it, which is fine until somebody prints page six and puts
 * that one page in front of a board. A document that is forwarded a page at a
 * time carries its sender on each page.
 */
const footLine = computed(() => {
  const contact = forum.value?.contact;
  if (!contact) return '';
  return [tr(contact.organisation), contact.email].filter(Boolean).join(' · ');
});
const closeButton = ref<HTMLButtonElement | null>(null);
const sheet = ref<HTMLElement | null>(null);
const body = ref<HTMLElement | null>(null);
let returnFocus: HTMLElement | null = null;

const regionName = computed(() => {
  const names = forum.value?.portfolio.regionNames;
  const region = props.project?.region ? names?.[props.project.region] : undefined;
  return region ? tr(region) : '';
});

const themeName = computed(() => {
  const theme = forum.value?.themes.find((x) => x.id === props.project?.segment);
  return theme ? tr(theme.title) : '';
});

/**
 * The featured project carries its own three-language text; the rest keep the
 * English the organiser prepared them in.
 */
const heading = computed(() => {
  const p = props.project;
  if (!p) return '';
  return p.titleI18n ? tr(p.titleI18n) : (p.displayTitle ?? p.title);
});
const sub = computed(() => {
  const p = props.project;
  return p?.subtitleI18n ? tr(p.subtitleI18n) : (p?.subtitle ?? null);
});
const story = computed(() => {
  const p = props.project;
  return p?.overviewI18n ? tr(p.overviewI18n) : (p?.overview ?? null);
});

/** The sheet's own renders and photographs. */
const shots = computed(() => props.project?.photos ?? []);

/**
 * What the picture is, for a reader who cannot see it.
 *
 * It used to say "<title> — 2 of 5", which describes the gallery rather than
 * the image: somebody listening to the page learned there were five pictures
 * and nothing about any of them. Every sheet's images are renders of the
 * project, so the alt says that, and names the project and the region it is
 * in — which is the information a sighted reader takes from the picture too.
 * The index is added only when there is more than one to be lost among.
 */
function altFor(index: number): string {
  const title = heading.value;
  const region = regionName.value;
  const total = shots.value.length;
  if (total > 1) {
    return region
      ? t('portfolio.photoAltMore', { title, region, n: index + 1, total })
      : `${t('portfolio.photoAltNoRegion', { title })} — ${index + 1}/${total}`;
  }
  return region
    ? t('portfolio.photoAlt', { title, region })
    : t('portfolio.photoAltNoRegion', { title });
}
const shown = ref(0);

/**
 * The renders the printed sheet shows after the first one.
 *
 * On screen these are the thumbnail strip, which is a control and prints as
 * nothing; on paper there is no reason to show one picture of a building
 * when the sheet supplied four.
 */
/* Three, which is one row of plates. A sheet that supplied six renders of
   the same building was spending seventy millimetres of paper on the fourth,
   fifth and sixth views of it — and the page that cost is the page the
   portfolio counts were on. */
const otherShots = computed(() =>
  shots.value.filter((_, i) => i !== shown.value).slice(0, 3),
);

const money = computed(() => {
  const p = props.project;
  if (!p?.investment || !p.currency) return null;
  return formatMoney(p.investment, p.currency, lang.value);
});

/**
 * What stands in for a figure the sheet does not give.
 *
 * Seven sheets state no sum, and they do it in their own words — "TBD", "Not
 * specified", "By agreement" — or by leaving the field out. Printed as
 * written, an Uzbek page said "Not specified" where the list beside it said
 * "Koʻrsatilmagan": the same silence, in two languages, neither of them the
 * reader's. The sheet's exact wording is still one hover away, on the
 * element's title.
 *
 * "By agreement" is kept apart from the rest because it is not silence: it
 * says the sum is open to negotiation.
 */
const noMoney = computed(() => {
  const raw = props.project?.investmentDisplay ?? '';
  return /agreement|kelishuv|договор/i.test(raw)
    ? t('portfolio.byAgreement')
    : t('portfolio.noFigure');
});

/**
 * The name of a field in the reader's language.
 *
 * The value is never touched — it is what the organiser stated — but the
 * sheets are written in English and the page is not, so `LAND AREA` over a
 * number is the page speaking a language the reader did not choose. A label
 * the dictionary does not know keeps the sheet's own wording.
 */
const fieldName = (label: string) => sheetLabel(label, lang.value);

/**
 * What a row says, in the reader's language and in one format.
 *
 * Three cases, in order. A sum of money is printed the way every other sum on
 * the page is printed — the sheets write the same figure as "USD 250M", "USD
 * 41.0M", "USD 0,5M" and "USD 680 000", and a reader comparing two projects
 * should not have to normalise them in their head. A stage word the organiser
 * reuses across every sheet is translated. Anything else is the sheet's own
 * statement about its own project and is left exactly as written.
 *
 * The original wording is never lost: it rides along on the title attribute,
 * the same way the headline figure's does.
 */
const fieldText = (row: { value: string; amount?: number; currency?: string }) =>
  row.amount && row.currency
    ? formatMoney(row.amount, row.currency, lang.value)
    : sheetValue(row.value, lang.value);

/** The sheet's own wording, for the tooltip, when the page has reworded it. */
const fieldTitle = (row: { value: string; amount?: number }) =>
  row.amount ? t('portfolio.asStated', { raw: row.value }) : undefined;

/** The headline chips, minus the investment, which has its own block above. */
const metrics = computed(() =>
  (props.project?.metrics ?? []).filter((m) => !/^INVESTMENT$/i.test(m.label)),
);

/**
 * The portfolio counted by region, for the small map the printed project page
 * carries. It answers "where is this" and "what else is near it" in one
 * figure-bearing picture, which is why the page after it no longer draws one.
 */
const regionCounts = computed(() => forum.value?.portfolio?.regionCounts ?? {});

/**
 * The label/value blocks, in the order a sheet presents them.
 *
 * The sheets repeat themselves — a destination named in PROJECT CONCEPT turns
 * up again under INVESTMENT OPPORTUNITY and a third time in WHY INVEST — so a
 * value that has already been shown is dropped from the later block. Only an
 * exact repeat is dropped; a longer sentence that happens to mention the same
 * place is left alone. Blocks that empty out disappear with it.
 */
const blocks = computed(() => {
  const p = props.project;
  if (!p) return [];
  const seen = new Set((p.metrics ?? []).map((m) => m.value.trim().toLowerCase()));
  return [
    { key: 'concept', rows: p.concept },
    { key: 'opportunity', rows: p.opportunity },
    { key: 'whyInvest', rows: p.whyInvest },
    { key: 'place', rows: p.place },
  ]
    .map((block) => ({
      key: block.key,
      rows: block.rows.filter((row) => {
        const value = row.value.trim().toLowerCase();
        if (seen.has(value)) return false;
        seen.add(value);
        return true;
      }),
    }))
    .filter((b) => b.rows.length > 0);
});

/* ----------------------------------------------------------------- PDF */

/**
 * The sheet as a PDF, through the browser's own print-to-PDF.
 *
 * It replaced a "copy the link" button, which the client took off: a link is
 * no use to somebody who wants to put the project in front of a board. The
 * obvious alternative would be to generate the file with a PDF library, but
 * that means shipping one, embedding a font that carries `Oʻ` and `gʻ` and
 * the Cyrillic set, and re-laying out every sheet by hand in its coordinate
 * space — for a worse result than the engine already in the browser.
 *
 * So the page prints itself instead: the print stylesheet below turns the
 * open sheet into an A4 document with the forum's own letterhead, and the
 * reader's dialogue offers both a printer and "Save as PDF". The typography,
 * the hyphenation and the images are the ones they have already seen.
 *
 * The photographs are let through `-webkit-print-color-adjust` so the sheet
 * does not come out as text on white.
 */
function toPdf(): void {
  // The gallery prints whichever shot is on screen, so the reader chooses it
  // the same way they were already choosing it.
  window.print();
}

/* ------------------------------------------------ moving between sheets */

const canPrev = computed(() => props.total > 1 && props.index > 1);
const canNext = computed(() => props.total > 1 && props.index < props.total);

function step(delta: number): void {
  if (delta < 0 && !canPrev.value) return;
  if (delta > 0 && !canNext.value) return;
  emit('step', delta);
}

/**
 * Keep the tab ring inside the sheet.
 *
 * `aria-modal` tells assistive technology that the page behind is inert but
 * does nothing about the tab order — without this, a few tabs out of the sheet
 * land on the filter controls underneath, which are behind the scrim and
 * cannot be seen.
 */
function trapFocus(event: KeyboardEvent): void {
  const host = sheet.value;
  if (!host) return;
  const focusable = [
    ...host.querySelectorAll<HTMLElement>(
      'a[href], button:not(:disabled), input, select, textarea, [tabindex]:not([tabindex="-1"])',
    ),
  ].filter((el) => el.offsetParent !== null);
  if (!focusable.length) return;
  const first = focusable[0]!;
  const last = focusable[focusable.length - 1]!;
  const active = document.activeElement;
  if (event.shiftKey && (active === first || !host.contains(active))) {
    event.preventDefault();
    last.focus();
  } else if (!event.shiftKey && active === last) {
    event.preventDefault();
    first.focus();
  }
}

function onKey(event: KeyboardEvent) {
  if (!props.project) return;
  if (event.key === 'Escape') {
    event.preventDefault();
    emit('close');
    return;
  }
  if (event.key === 'Tab') {
    trapFocus(event);
    return;
  }
  // Not while the reader is typing into something.
  if (event.target instanceof HTMLInputElement) return;
  if (event.key === 'ArrowLeft') {
    event.preventDefault();
    step(-1);
  } else if (event.key === 'ArrowRight') {
    event.preventDefault();
    step(1);
  }
}

watch(
  () => props.project?.id,
  async (next, previous) => {
    if (!next) return;
    // A different project in the same sheet: back to the first photograph and
    // to the top of the reading, or the next one opens halfway down.
    shown.value = 0;
    if (previous) {
      await nextTick();
      body.value?.scrollTo({ top: 0, behavior: 'auto' });
    }
  },
);

watch(
  () => Boolean(props.project),
  async (open, wasOpen) => {
    if (open && !wasOpen) {
      lockPageScroll();
      returnFocus = document.activeElement as HTMLElement | null;
      await nextTick();
      closeButton.value?.focus();
    } else if (!open && wasOpen) {
      unlockPageScroll();
      returnFocus?.focus();
      returnFocus = null;
    }
  },
);

onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKey);
  if (props.project) unlockPageScroll();
});
</script>

<template>
  <Teleport to="body">
    <div v-if="project" class="panel">
      <button type="button" class="panel__scrim" :aria-label="t('portfolio.close')" @click="emit('close')" />

      <div
        ref="sheet"
        class="panel__sheet"
        role="dialog"
        aria-modal="true"
        :aria-label="project.displayTitle ?? project.title"
      >
        <!-- The grab bar of a bottom sheet. Drawn only on the phone layout,
             where the sheet comes up from the edge of the screen and has to
             say that it is a sheet; decoration, so it is not announced. -->
        <span class="panel__grip" aria-hidden="true" />

        <header class="panel__bar">
          <p class="panel__step">
            <button
              type="button"
              class="panel__arrow"
              :disabled="!canPrev"
              :aria-label="t('portfolio.prev')"
              @click="step(-1)"
            >
              <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                <path
                  d="M15 5l-7 7 7 7"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
            <span class="panel__count">{{ index }} / {{ total }}</span>
            <button
              type="button"
              class="panel__arrow"
              :disabled="!canNext"
              :aria-label="t('portfolio.next')"
              @click="step(1)"
            >
              <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true">
                <path
                  d="M9 5l7 7-7 7"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </button>
          </p>

          <button ref="closeButton" type="button" class="panel__close" @click="emit('close')">
            <span>{{ t('portfolio.close') }}</span>
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
              <path
                d="M6 6l12 12M18 6L6 18"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
              />
            </svg>
          </button>
        </header>

        <!-- Only on paper, and only as the first page: a sheet that has been
             saved as a PDF is forwarded on its own, so it opens the way a
             proposal opens rather than halfway through a reading. -->
        <PrintCover
          :kicker="t('portfolio.coverKicker')"
          :title="heading"
          :meta="[regionName, themeName].filter(Boolean).join(' · ')"
          :money="money ?? noMoney"
          :money-label="t('portfolio.labels.investment')"
        />

        <!--
          A table, and not for want of a div.

          On paper the page box has no margins — that is the only way the
          cover and the closing page reach the edge of the sheet — so the
          reading has to carry its own. Padding gives it to the first page and
          the last and leaves the pages between running into the paper's edge;
          a table header group is the one thing a browser repeats at the top
          of every fragment. So the letterhead sits in a <thead>, where it
          becomes a running head: every page of the reading carries the mark
          and the dates, the way the organiser's own decks do.

          On screen the whole table is `display: contents` and the sheet's
          flex layout sees the body exactly as it did before.
        -->
        <table class="panel__doc">
          <thead>
            <tr>
              <td>
                <div class="panel__letterhead" aria-hidden="true">
                  <ForumMark form="lockup" variant="dark" class="panel__letterheadMark" />
                  <p>{{ eventLine }}</p>
                </div>
              </td>
            </tr>
          </thead>
          <!--
            Declared before <tbody>, as the HTML parser requires, and printed
            after it: a table footer group is the one element a browser
            repeats at the foot of every fragment, which is what makes this a
            running foot rather than a line at the end of the document.
          -->
          <tfoot>
            <tr>
              <td>
                <div class="panel__runningFoot" aria-hidden="true">
                  <p>{{ footLine }}</p>
                  <p>{{ eventLine }}</p>
                </div>
              </td>
            </tr>
          </tfoot>
          <tbody>
            <tr>
              <td>
                <!-- data-lenis-prevent: this element scrolls itself, so the
                     smooth-scroll driver must leave its wheel and touch
                     events alone. -->
                <!--
                  On paper the project comes first, right behind its cover.
                  It used to come ninth, after six pages about the country and
                  the forum, which is the wrong way round for a document that
                  is forwarded under the project's own name: the reader who
                  opens it is asking about this building, and the country is
                  the answer to their second question, not their first.
                -->
                <div ref="body" class="panel__body" data-lenis-prevent>
          <p class="panel__where">
            {{ regionName }}
            <span v-if="themeName" class="panel__theme">{{ regionName ? '· ' : '' }}{{ themeName }}</span>
          </p>
          <h3 class="panel__title">{{ heading }}</h3>
          <p v-if="sub" class="panel__sub">{{ sub }}</p>

          <!--
            The band the page opens with: the picture on one side, the figures
            on the other. `display: contents` on screen, so the sheet reads
            exactly as it did; a two-column grid on paper, which is what makes
            the project fit the page it is now given.
          -->
          <div class="panel__band">
          <!-- The sheet's own visuals. One image is the common case, so the
               thumbnail strip only appears when there is something to switch to. -->
          <figure v-if="shots.length" class="panel__gallery">
            <!-- Seen through the arch defined once in App.vue. -->
            <img
              :src="`photos/${shots[shown]}-full.webp`"
              :alt="altFor(shown)"
              width="1000"
              height="563"
              loading="lazy"
              decoding="async"
            />
            <figcaption v-if="shots.length > 1" :aria-label="t('portfolio.gallery')">
              <button
                v-for="(shot, i) in shots"
                :key="shot"
                type="button"
                :aria-pressed="i === shown"
                :aria-label="altFor(i)"
                @click="shown = i"
              >
                <img
                  :src="`photos/${shot}-thumb.webp`"
                  alt=""
                  width="220"
                  height="150"
                  loading="lazy"
                  decoding="async"
                />
              </button>
            </figcaption>
          </figure>

          <div class="panel__figures">
          <p class="panel__money">
            <span class="eyebrow">{{ t('portfolio.labels.investment') }}</span>
            <!-- The sheet's own wording, so a reader can check our arithmetic. -->
            <strong
              v-if="money"
              class="figure"
              :title="t('portfolio.asStated', { raw: project.investmentDisplay ?? '' })"
            >
              {{ money }}
            </strong>
            <strong
              v-else
              class="figure panel__money--none"
              :title="
                project.investmentDisplay
                  ? t('portfolio.asStated', { raw: project.investmentDisplay })
                  : undefined
              "
            >
              {{ noMoney }}
            </strong>
          </p>

          <ul v-if="metrics.length" class="panel__chips">
            <li v-for="m in metrics" :key="m.label">
              <FactIcon :name="sheetIcon(m.label)" class="panel__chipIcon" />
              <span>{{ fieldName(m.label) }}</span>
              <strong :title="fieldTitle(m)">{{ fieldText(m) }}</strong>
            </li>
          </ul>

          <!-- Only on paper: the country with this project's region picked
               out, and every other region carrying its own count. One
               picture answering both "where is it" and "what else is near
               it" — the page that used to answer the second one on its own
               is now three paragraphs under the reading. -->
          <div class="panel__where2" aria-hidden="true">
            <p class="panel__whereHead">{{ t('print.whereTitle') }}</p>
            <PrintMap :counts="regionCounts" :here="project.region" />
            <p class="panel__whereFoot">{{ t('print.mapCredit') }}</p>
          </div>
          </div>

          <!-- Under the photograph on paper, where the ministry's own sheets
               put the project description; in the ordinary flow on screen,
               because `.panel__story` is `display: contents` there. -->
          <div v-if="story" class="panel__story">
            <h4 class="panel__heading">{{ t('portfolio.labels.overview') }}</h4>
            <p class="panel__overview">{{ story }}</p>
          </div>
          </div>

          <template v-for="block in blocks" :key="block.key">
            <h4 class="panel__heading">{{ t(`portfolio.blocks.${block.key}`) }}</h4>
            <dl class="panel__rows">
              <div v-for="row in block.rows" :key="row.label">
                <dt>
                  <FactIcon :name="sheetIcon(row.label)" class="panel__rowIcon" />
                  <span>{{ fieldName(row.label) }}</span>
                </dt>
                <dd :title="fieldTitle(row)">{{ fieldText(row) }}</dd>
              </div>
            </dl>
          </template>

          <p v-if="project.statusNote" class="panel__status">{{ project.statusNote }}</p>

          <!-- Only on paper: the sheet's other renders, which on screen are
               the thumbnail strip above. They close the reading rather than
               opening it — a page that leads with four renders of the same
               building has said nothing by the time it ends. -->
          <div v-if="otherShots.length" class="panel__plates" aria-hidden="true">
            <img
              v-for="shot in otherShots"
              :key="shot"
              :src="`photos/${shot}-full.webp`"
              alt=""
              width="1000"
              height="563"
              loading="lazy"
              decoding="async"
            />
          </div>
</div>

                <!-- Then the country the project is in, in three pages: the
                     market, the economy and its people, and what an arriving
                     investor is actually offered. -->
                <PrintStats :region="project.region" />
              </td>
            </tr>
          </tbody>
        </table>

        <!-- The last page: where to write back to, as the committee rather
             than as a person. -->
        <PrintClosing
          :heading="t('portfolio.contactsTitle')"
          :lead="t('portfolio.contactsLead')"
          :segment="project.segment"
        />

        <!-- What a reader can do with the project they have just read. -->
        <footer class="panel__act">
          <button type="button" class="btn btn--solid panel__enquire" @click="emit('enquire')">
            {{ t('portfolio.enquire') }}
          </button>
          <button type="button" class="btn panel__pdf" @click="toPdf">
            <svg viewBox="0 0 24 24" width="17" height="17" aria-hidden="true">
              <path
                d="M12 3v10m0 0l-3.5-3.5M12 13l3.5-3.5M5 16v2.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V16"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <span>{{ t('portfolio.pdf') }}</span>
          </button>
        </footer>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
.panel {
  position: fixed;
  inset: 0;
  z-index: 95;
  display: grid;
  /* The phone layout: the sheet rises from the bottom edge. */
  align-items: end;
  justify-items: stretch;
}
.panel__scrim {
  position: absolute;
  inset: 0;
  border: 0;
  background: rgb(5 7 10 / 0.8);
  backdrop-filter: blur(6px);
  cursor: pointer;
}

/**
 * The sheet: a fixed bar, a scrolling reading and a fixed footer.
 *
 * A grid rather than one scrolling box, so the two bars stay where they are
 * put. The sheet used to float its close button and let the heading flow
 * around it, which worked until the heading ran to two lines on a phone and
 * the button ended up inside the title.
 */
.panel__sheet {
  position: relative;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr) auto;
  width: 100%;
  /* Not the whole screen: the strip of scrim above the sheet is what says the
     page is still there behind it, and it is where a thumb goes to dismiss.
     svh rather than vh, or the footer hides under the browser's own bottom
     bar on iOS. */
  height: min(92svh, 100%);
  background: var(--surface-2);
  border: 1px solid var(--hairline);
  border-block-end: 0;
  border-start-start-radius: 1rem;
  border-start-end-radius: 1rem;
  overflow: hidden;
  animation: sheet-up 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

@keyframes sheet-up {
  from {
    transform: translateY(2.5rem);
    opacity: 0;
  }
}
@media (prefers-reduced-motion: reduce) {
  .panel__sheet {
    animation: none;
  }
}

.panel__grip {
  position: absolute;
  inset-block-start: 0.5rem;
  inset-inline: 0;
  margin-inline: auto;
  inline-size: 2.5rem;
  block-size: 0.25rem;
  border-radius: 999px;
  background: var(--hairline-strong);
}

/* ------------------------------------------------------------- the bars */

.panel__bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 0.75rem;
  padding: 1.1rem clamp(1.1rem, 4vw, 2rem) 0.7rem;
  border-block-end: 1px solid var(--hairline);
  background: var(--surface-2);
}

.panel__step {
  display: flex;
  align-items: center;
  gap: 0.25rem;
}
.panel__arrow {
  display: grid;
  place-items: center;
  inline-size: var(--tap);
  block-size: var(--tap);
  border: 1px solid var(--hairline);
  border-radius: 0.25rem;
  background: transparent;
  color: var(--ink);
  cursor: pointer;
  transition: border-color 0.18s, color 0.18s;
}
@media (hover: hover) {
  .panel__arrow:hover:not(:disabled) {
    border-color: var(--gold);
  }
}
.panel__arrow:disabled {
  opacity: 0.35;
  cursor: default;
}
.panel__count {
  min-inline-size: 3.5rem;
  text-align: center;
  font-size: 0.8rem;
  font-variant-numeric: tabular-nums;
  color: var(--ink-dim);
}

.panel__close {
  display: inline-flex;
  align-items: center;
  gap: 0.45rem;
  min-block-size: var(--tap);
  padding: 0.5rem 1rem;
  border: 1px solid var(--hairline);
  border-radius: 0.25rem;
  background: transparent;
  color: var(--ink);
  font: inherit;
  font-size: 0.8rem;
  cursor: pointer;
}
@media (hover: hover) {
  .panel__close:hover {
    border-color: var(--gold);
  }
}

.panel__act {
  display: flex;
  flex-wrap: wrap;
  gap: 0.6rem;
  padding: 0.85rem clamp(1.1rem, 4vw, 2rem);
  /* Clears the home indicator on a gesture-navigation phone. */
  padding-block-end: max(0.85rem, env(safe-area-inset-bottom));
  border-block-start: 1px solid var(--hairline);
  background: var(--surface);
}
/* The two take one row wherever the row can hold them, and the call to
   action is the one that grows. Its basis is small enough that a 412-pixel
   phone keeps both on the line; below that they stack, which is the right
   answer there. */
.panel__enquire {
  flex: 1 1 9rem;
  justify-content: center;
}
.panel__pdf {
  flex: 0 1 auto;
  justify-content: center;
  gap: 0.45rem;
}

/* ---------------------------------------------------------- the reading */

.panel__body {
  overflow-y: auto;
  overscroll-behavior: contain;
  /* Keeps the momentum scroll inside the sheet rather than handing the
     overscroll to the page behind it. */
  -webkit-overflow-scrolling: touch;
  padding: clamp(1.25rem, 4vw, 2rem) clamp(1.1rem, 4vw, 2rem) 2rem;
}

.panel__where {
  font-size: 0.75rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: var(--accent-soft);
}
.panel__theme {
  color: var(--ink-dim);
}
.panel__title {
  margin-block-start: 0.5rem;
  font-size: clamp(1.5rem, 3.5vw, 2.25rem);
}
.panel__sub {
  margin-block-start: 0.5rem;
  color: var(--ink-dim);
  font-size: 0.9rem;
}

/* The gallery: one large image with a thumbnail strip when there is more than
   one. Sized by aspect ratio so the panel does not jump as images arrive. */
.panel__gallery {
  margin: 1.5rem 0 0;
}
/**
 * Square, and the squareness is the point.
 *
 * The arch is clipped in bounding-box units, so the box it is applied to is
 * what decides whether it comes to a point or flattens into a dome: a wide
 * box gives a dome. A square is as tall as the widest the sheet can be, which
 * is the sharpest point available without the photograph taking the whole
 * screen and pushing the investment figure two flicks down.
 *
 * `block-size: auto` is load-bearing. The `height` attribute on the image is a
 * presentational attribute, which is still a used height, and a used height
 * beats `aspect-ratio` — so without this the ratio here was quietly doing
 * nothing and the box was 625 pixels tall whatever its width.
 */
.panel__gallery > img {
  display: block;
  inline-size: 100%;
  block-size: auto;
  aspect-ratio: 1 / 1;
  object-fit: cover;
  clip-path: url(#uz-arch);
  background: var(--surface-2);
}
.panel__gallery figcaption {
  display: flex;
  gap: 0.5rem;
  margin-block-start: 0.5rem;
  overflow-x: auto;
  scrollbar-width: thin;
  /* The strip is a swipe on a phone, and a swipe that stops between two
     thumbnails looks broken. */
  scroll-snap-type: x proximity;
}
.panel__gallery figcaption button {
  flex: 0 0 auto;
  scroll-snap-align: start;
  padding: 0;
  border: 1px solid transparent;
  border-radius: 0.2rem;
  background: none;
  cursor: pointer;
  line-height: 0;
  overflow: hidden;
  opacity: 0.6;
  transition: opacity 0.18s, border-color 0.18s;
}
.panel__gallery figcaption button[aria-pressed='true'] {
  opacity: 1;
  border-color: var(--gold);
}
@media (hover: hover) {
  .panel__gallery figcaption button:hover {
    opacity: 1;
    border-color: var(--gold);
  }
}
.panel__gallery figcaption img {
  inline-size: 4.5rem;
  block-size: 3rem;
  object-fit: cover;
  display: block;
}

.panel__money {
  display: grid;
  gap: 0.25rem;
  margin-block-start: 1.75rem;
  padding-block: 1.25rem;
  border-block: 1px solid var(--hairline);
}
.panel__money strong {
  font-size: clamp(1.75rem, 5vw, 2.75rem);
  font-weight: 400;
}
.panel__money--none {
  font-size: clamp(1.1rem, 3vw, 1.5rem) !important;
  color: var(--ink-dim);
}
/* The headline chips, as a wrapping row — the sheet shows them side by side. */
/* Each chip carries its own border rather than the grid showing through a 1px
   gap: the chip count is whatever the sheet states, so the last row is usually
   short and a see-through grid leaves a lit empty cell beside it. */
.panel__chips {
  list-style: none;
  margin: 1.25rem 0 0;
  padding: 0;
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(8rem, 1fr));
  gap: 0.5rem;
}
/* The two halves of the opening band are paper furniture; on screen the
   sheet is one column and they are not there at all. */
.panel__band,
.panel__figures,
.panel__story {
  display: contents;
}
.panel__where2 {
  display: none;
}

.panel__chips li {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 0.15rem 0.5rem;
  align-content: start;
  padding: 0.7rem 0.85rem;
  border: 1px solid var(--hairline);
  border-radius: 0.6rem;
}
/* The glyph sits in the first column across both rows, so the label and the
   value stay on one axis however long the label wraps. */
.panel__chipIcon {
  grid-row: span 2;
  align-self: start;
  margin-block-start: 0.1rem;
  color: var(--accent-soft);
}
.panel__chips span {
  font-size: 0.72rem;
  letter-spacing: 0.12em;
  color: var(--ink-dim);
}
.panel__chips strong {
  font-weight: 400;
  font-size: 0.95rem;
  color: var(--ink);
}

.panel__heading {
  margin-block-start: 2rem;
  font-family: var(--font-body);
  font-size: 0.72rem;
  font-weight: 600;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: var(--accent-soft);
}
.panel__overview {
  margin-block-start: 0.6rem;
  font-size: 0.95rem;
}

.panel__rows {
  display: grid;
  gap: 0;
  margin: 0.6rem 0 0;
}
/* Stacked on a phone: a label column wide enough for "INFRASTRUCTURE AND
   UTILITIES" leaves the value about four characters a line. */
.panel__rows > div {
  display: grid;
  gap: 0.15rem;
  padding-block: 0.7rem;
  border-block-end: 1px solid var(--hairline);
}
.panel__rows dt {
  display: flex;
  align-items: baseline;
  gap: 0.45rem;
  font-size: 0.78rem;
  color: var(--ink-dim);
  text-wrap: balance;
}
.panel__rowIcon {
  align-self: center;
  color: var(--accent-soft);
}
.panel__rows dd {
  margin: 0;
  font-size: 0.92rem;
  color: var(--ink);
}

.panel__status {
  margin-block-start: 1.5rem;
  padding: 0.85rem 1rem;
  border: 1px solid var(--hairline);
  border-radius: 0.6rem;
  font-size: 0.85rem;
  color: var(--ink-soft);
}

/* --------------------------------------------------- the desktop sheet */

/**
 * Wide enough for a pointer: the sheet goes back to the right-hand edge and
 * runs the full height, which is where a reader who is not holding the device
 * expects a detail view to open.
 */
@media (min-width: 48rem) {
  .panel {
    align-items: stretch;
    justify-items: end;
  }
  .panel__sheet {
    width: min(100%, 40rem);
    height: 100%;
    border: 0;
    border-inline-start: 1px solid var(--hairline);
    border-radius: 0;
    animation-name: sheet-in;
  }
  .panel__grip {
    display: none;
  }
  .panel__rows > div {
    grid-template-columns: minmax(0, 11rem) minmax(0, 1fr);
    gap: 1rem;
  }
}

@keyframes sheet-in {
  from {
    transform: translateX(2rem);
    opacity: 0;
  }
}

/* All three exist only on paper. Declared before the print block so the
   print rules, which match at the same specificity, come later and win. */
.panel__letterhead,
.panel__runningFoot,
.panel__plates {
  display: none;
}

/* On screen the wrapper is scaffolding for the printed page and nothing
   else, so it steps out of the box tree entirely and the sheet's flex
   layout sees .panel__body as its own child. */
.panel__doc,
.panel__doc > tbody,
.panel__doc > tbody > tr,
.panel__doc > tbody > tr > td,
.panel__doc > thead,
.panel__doc > tfoot {
  display: contents;
}

/* ------------------------------------------------------------------ print */

/**
 * The open sheet, as an A4 document.
 *
 * The PDF button calls `window.print()`; this is what the browser then lays
 * out. It replaced a "copy the link" button, which the client took off — a
 * link is no use to somebody who wants to put a project in front of a board.
 * A PDF library was the obvious alternative and the wrong one: it would mean
 * shipping the library, embedding a font that carries `Oʻ`, `gʻ` and the
 * Cyrillic set, and rebuilding every sheet by hand in its coordinate space,
 * for a worse result than the engine already in the browser. Printed, the
 * typography, the hyphenation and the photographs are the ones the reader
 * already has in front of them.
 *
 * These rules live beside the screen rules they override rather than in the
 * global stylesheet, because a scoped block is injected after it and would
 * otherwise lose the cascade to `.panel__sheet`'s own desktop width.
 */
@media print {
  /* The sheet's furniture: a dialogue's controls, not a document's. */
  .panel__scrim,
  .panel__grip,
  .panel__bar,
  .panel__act,
  .panel__gallery figcaption {
    display: none !important;
  }

  .panel {
    position: static;
    display: block;
    overflow: visible;
  }
  .panel__sheet {
    position: static;
    display: block;
    overflow: visible;
    inline-size: auto;
    block-size: auto;
    max-block-size: none;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    background: #fff;
    animation: none;
  }
  /* The page box has no margins at all (index.css), so the reading supplies
     its own: the running head pays for the top of every page, the body for
     the sides and the foot. */
  .panel__doc {
    display: table;
    inline-size: 100%;
    border-collapse: collapse;
  }
  .panel__doc > thead {
    display: table-header-group;
  }
  .panel__doc > tbody {
    display: table-row-group;
  }
  .panel__doc > tfoot {
    display: table-footer-group;
  }
  .panel__doc > thead > tr,
  .panel__doc > tbody > tr,
  .panel__doc > tfoot > tr {
    display: table-row;
  }
  .panel__doc > thead > tr > td,
  .panel__doc > tbody > tr > td,
  .panel__doc > tfoot > tr > td {
    display: table-cell;
    padding: 0;
  }

  .panel__body {
    overflow: visible;
    max-block-size: none;
    padding: 0 14mm 2mm;
    font-size: 10pt;
    /* The general pages come first and run on into one another; the project
       itself always opens a page. */
    break-before: page;
  }

  /* A sheet saved as a PDF is forwarded on its own, so it carries the mark it
     came from, the dates, and somewhere to write back to. */
  .panel__letterhead {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    margin: 14mm 14mm 7mm;
    padding-block-end: 0.6rem;
    border-block-end: 1px solid #c9ccd8;
  }
  /* 54mm, not 42: the lockup sets its five lines at one size beside the
     emblem now, so the same measure would print the name a third smaller. */
  .panel__letterheadMark {
    inline-size: 54mm;
    flex: none;
  }
  .panel__letterhead p {
    margin: 0;
    font-size: 9pt;
    color: #4a5070;
  }

  /* The running foot. It pays for the bottom of every page the way the
     letterhead pays for the top, so nothing in the reading has to. */
  .panel__runningFoot {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 8mm;
    margin: 6mm 14mm 10mm;
    padding-block-start: 2mm;
    border-block-start: 1px solid #c9ccd8;
  }
  .panel__runningFoot p {
    margin: 0;
    font-size: 7pt;
    letter-spacing: 0.04em;
    color: #626b8b;
  }

  .panel__title {
    font-size: 18pt;
    line-height: 1.15;
  }

  /**
   * The opening band: the building on the left, its numbers on the right.
   *
   * This is the one arrangement that makes a project fit one page. Stacked —
   * picture, then sum, then a wrapping field of chips — the same content ran
   * to a page and a half and pushed the reading onto a third; set side by
   * side it ends above the fold with room under it for the overview.
   *
   * 66mm for the figures, which is the width at which "Qoplanish muddati"
   * stops hyphenating and a sum in soums still fits on one line.
   */
  .panel__band {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 62mm;
    gap: 3mm 6mm;
    align-items: start;
    margin-block-start: 4mm;
  }
  /* Placed rather than flowed: the DOM order is picture, figures, overview —
     which is the order the sheet reads in on screen — and on paper the
     overview belongs under the picture with the figures running down beside
     both. */
  .panel__gallery {
    grid-column: 1;
    grid-row: 1;
  }
  .panel__figures {
    display: block;
    grid-column: 2;
    grid-row: 1 / span 2;
  }
  .panel__story {
    display: block;
    grid-column: 1;
    grid-row: 2;
  }

  /* A photograph is most of what a project looks like; without this it prints
     as text on white in every browser's default. */
  .panel__gallery {
    margin: 0;
  }
  .panel__gallery img {
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
    inline-size: 100%;
    block-size: 62mm;
    object-fit: cover;
  }
  .panel__heading {
    margin-block-start: 0;
  }
  .panel__overview {
    margin-block-start: 1.5mm;
    font-size: 9.5pt;
    line-height: 1.45;
  }

  /* The figures column, which is the page's answer to "is this worth an
     hour": the sum first, then one glyphed row per thing the sheet states. */
  .panel__money {
    margin: 0 0 2mm;
    padding-block-end: 2mm;
    border-block-end: 0.4mm solid #15193a;
  }
  .panel__chips {
    display: block;
    margin: 0;
  }
  .panel__chips li {
    display: grid;
    grid-template-columns: 5mm minmax(0, 1fr);
    gap: 0 2mm;
    padding: 1.5mm 0;
    border: 0;
    border-block-end: 0.3mm solid #e3e5ec;
    border-radius: 0;
    break-inside: avoid;
  }
  .panel__chipIcon {
    inline-size: 4.4mm;
    block-size: 4.4mm;
    margin-block-start: 0.4mm;
    color: #995f00;
  }
  .panel__chips span {
    font-size: 6.8pt;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #4a5070;
  }
  .panel__chips strong {
    font-size: 9.5pt;
    color: #15193a;
  }

  /* The map under the figures: 66mm wide, which is the column. */
  .panel__where2 {
    display: block;
    margin-block-start: 4mm;
    break-inside: avoid;
  }
  .panel__whereHead {
    margin: 0 0 1.5mm;
    font-size: 7pt;
    font-weight: 700;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: #00566e;
  }
  .panel__whereFoot {
    margin: 1.5mm 0 0;
    font-size: 6.2pt;
    line-height: 1.35;
    color: #8e95ad;
  }

  /* The field blocks, two columns: a sheet with nineteen rows is otherwise
     nineteen short lines down the left of an empty page. */
  /* `display: block`, because the screen rule makes this a grid and a grid
     container ignores `columns` — which is why a sheet with nineteen rows was
     still printing nineteen full-width lines down an empty page. */
  .panel__rows {
    display: block;
    columns: 2;
    column-gap: 8mm;
  }
  .panel__rows > div {
    break-inside: avoid;
    padding-block: 1.2mm;
  }
  .panel__rows dt {
    gap: 1.6mm;
    font-size: 7pt;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #4a5070;
  }
  .panel__rows dd {
    font-size: 8.8pt;
    line-height: 1.35;
  }
  .panel__rowIcon {
    inline-size: 3.6mm;
    block-size: 3.6mm;
    color: #995f00;
  }

  /**
   * The other renders, two to a row, at the foot of the reading.
   *
   * They are let through `print-color-adjust` for the same reason the first
   * one is, and each is capped so that a sheet with six renders does not
   * become a sheet that is mostly renders.
   */
  .panel__plates {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 2.5mm;
    margin-block-start: 4mm;
  }
  .panel__plates img {
    inline-size: 100%;
    block-size: 30mm;
    object-fit: cover;
    break-inside: avoid;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }

  /* A heading keeps its table with it, and nothing breaks mid-row. */
  .panel__heading {
    break-after: avoid;
  }
  .panel__rows > div,
  .panel__chips li,
  .panel__gallery {
    break-inside: avoid;
  }
}

</style>
