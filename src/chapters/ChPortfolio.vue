<script setup lang="ts">
/**
 * Chapter IV — the project portfolio.
 *
 * Seventy-four projects is too many to read and too few to need a search
 * engine, so the chapter is built around a breakdown that is also the
 * navigation. The first question an investor asks of a regional portfolio is a
 * question about places, so the first answer is a map: fourteen regions, each
 * carrying the number of projects it brought, and choosing one filters
 * everything below. Direction and ticket size stay as bars underneath — those
 * are not places and a map cannot hold them.
 *
 * The chapter has been through two earlier versions of this. The first was a
 * beeswarm on a logarithmic axis: accurate, and unreadable without three
 * separate legends. The second was three columns of bars: readable, but it
 * said nothing a table would not have said, and the region column in
 * particular asked the reader to know where Surkhandarya is.
 */
import { computed, nextTick, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

import ForumMark from '../components/ForumMark.vue';
import ProjectPanel from '../components/ProjectPanel.vue';
import CountFigure from '../components/CountFigure.vue';
import RegionBrief, { type RegionDetail } from '../components/RegionBrief.vue';
import RegionMap, { type MapRegion } from '../components/RegionMap.vue';
import StatBars, { type BarRow } from '../components/StatBars.vue';
import type { Project } from '../lib/forum-types';
import { formatMoney, millionWord } from '../lib/money';
import { sheetValue } from '../lib/sheet-values';
import { openEnquiry, scrollToId, useChapterTimeline } from '../lib/motion';
import {
  bandOf,
  clearFilters,
  eventDates,
  enquirySubject,
  filters,
  openProject,
  openProjectId,
  replaceProject,
  SIZE_BANDS,
  tr,
  useStore,
  type PortfolioSort,
  type SizeBand,
} from '../lib/state';

const { t } = useI18n();
const { forum, lang } = useStore();
const root = ref<HTMLElement | null>(null);

const projects = computed(() => forum.value?.portfolio.projects ?? []);
const regionNames = computed(() => forum.value?.portfolio.regionNames ?? {});
const themes = computed(() => forum.value?.themes ?? []);

/**
 * The totals, including the one that does not add up and why.
 *
 * Seventy-four projects, but the regions account for seventy-three: the
 * Madaniyat Hotel is a network concept for every region at once and is filed
 * under none of them. A reader who adds the region column and gets a
 * different number than the headline is right to distrust both, so the page
 * now states the split instead of leaving it to be discovered.
 */
const totals = computed(() => {
  const p = forum.value?.portfolio;
  const projects = p?.projects.length ?? 0;
  const placed = (p?.projects ?? []).filter((x) => x.region).length;
  return {
    projects,
    placed,
    nationwide: projects - placed,
    usd: (p?.totalUsd ?? 0) / 1e6,
    statedUsd: p?.statedUsdCount ?? 0,
    statedUzs: p?.statedUzsCount ?? 0,
    open: p?.openCount ?? 0,
    regions: Object.keys(p?.regionCounts ?? {}).length,
  };
});

const SORTS: PortfolioSort[] = ['largest', 'smallest', 'region', 'name'];
const SORT_LABEL: Record<PortfolioSort, string> = {
  largest: 'portfolio.sortLargest',
  smallest: 'portfolio.sortSmallest',
  region: 'portfolio.sortRegion',
  name: 'portfolio.sortName',
};

/**
 * The featured project carries its own three-language text, because its source
 * document was Uzbek; everything else keeps the English the organiser prepared.
 */
const title = (p: Project) => (p.titleI18n ? tr(p.titleI18n) : (p.displayTitle ?? p.title));
const subtitleOf = (p: Project) => (p.subtitleI18n ? tr(p.subtitleI18n) : p.subtitle);
const thumb = (p: Project) => (p.photos[0] ? `photos/${p.photos[0]}-thumb.webp` : null);

/**
 * What a project is asking for, in words, or the sheet's silence — in the
 * reader's language either way. A sheet that says the sum is open to
 * negotiation is saying something, and keeps its own line.
 */
const moneyOf = (p: Project) => {
  if (p.investment && p.currency) return formatMoney(p.investment, p.currency, lang.value);
  return /agreement|kelishuv|договор/i.test(p.investmentDisplay ?? '')
    ? t('portfolio.byAgreement')
    : t('portfolio.noFigure');
};

/**
 * The sheet's own one-word description of the project, when it gives one —
 * in the reader's language where it is one of the stage words the organiser
 * reuses across every sheet, and in the sheet's own words otherwise.
 */
const activityOf = (p: Project) => {
  const raw = p.metrics.find((m) => /^(ACTIVITY|OPERATOR|STATUS)$/i.test(m.label))?.value;
  return raw ? sheetValue(raw, lang.value) : null;
};

/** " mln USD" / " млн USD" / " M USD" — the unit word follows the language. */
const usdSuffix = computed(() => ` ${millionWord(lang.value)} USD`);

function matches(p: Project): boolean {
  const f = filters.value;
  if (f.region !== 'all' && p.region !== f.region) return false;
  if (f.segment !== 'all' && p.segment !== f.segment) return false;
  if (f.size !== 'all' && bandOf(p) !== f.size) return false;
  const q = f.query.trim().toLowerCase();
  if (!q) return true;
  // Everything the sheet states, so a search for "glamping", "ski" or a
  // district name finds the project whether the word is in the title, a chip,
  // the concept list or the overview.
  const haystack = [
    title(p),
    p.subtitle,
    p.overview,
    p.region && regionNames.value[p.region] ? tr(regionNames.value[p.region]) : '',
    ...[...p.metrics, ...p.concept, ...p.whyInvest, ...p.place].map((s) => `${s.label} ${s.value}`),
  ]
    .filter(Boolean)
    .join(' ')
    .toLowerCase();
  return haystack.includes(q);
}

/** USD asked for, or -1 where the sheet states no dollar figure. */
const amount = (p: Project) => (p.currency === 'USD' ? (p.investment ?? -1) : -1);

const shown = computed(() => {
  const list = projects.value.filter(matches);
  const by = filters.value.sort;
  return [...list].sort((a, b) => {
    // The hand-transcribed concept leads the list however it is sorted.
    if (Boolean(a.featured) !== Boolean(b.featured)) return a.featured ? -1 : 1;
    if (by === 'largest') return amount(b) - amount(a);
    if (by === 'smallest') return amount(a) - amount(b);
    if (by === 'name') return title(a).localeCompare(title(b));
    // A sheet can arrive without a region; those sort to the end.
    if (a.region !== b.region) {
      if (!a.region) return 1;
      if (!b.region) return -1;
      return a.region.localeCompare(b.region);
    }
    return amount(b) - amount(a);
  });
});

/** The direction a project is filed under, by name. */
const segmentName = (id: string | null) => {
  const theme = id ? forum.value?.themes.find((x) => x.id === id) : null;
  return theme ? tr(theme.title) : '—';
};

/** The forum's own line, for the printed letterhead. */
const eventLine = computed(() => {
  const event = forum.value?.event;
  return event ? `${eventDates()} · ${tr(event.city)}` : '';
});

/* ------------------------------------------------------------------ PDF */

/**
 * The list as it stands, as a document.
 *
 * The sheet of one project already prints; this is the other half of it. An
 * investor who has narrowed seventy-four projects to the nine in one region
 * under one direction wants those nine on paper, not a screenshot and not a
 * link that reproduces the filtering only if the other person clicks the same
 * things. One press and the filtered list is a table with the forum's own
 * letterhead, in whichever language they are reading.
 *
 * `data-print` tells the stylesheet which of the two documents is wanted, so
 * printing the chapter and printing an open sheet do not have to guess.
 */
function portfolioToPdf(): void {
  const root = document.documentElement;
  root.dataset.print = 'portfolio';
  const clear = () => {
    delete root.dataset.print;
    window.removeEventListener('afterprint', clear);
  };
  window.addEventListener('afterprint', clear);
  window.print();
  // Safari resolves print() before afterprint in some versions; this is the
  // belt to that braces, and removing an attribute twice costs nothing.
  window.setTimeout(clear, 1000);
}

/** What the printed list says it was narrowed by. */
const printFilters = computed(() => {
  const parts: string[] = [];
  const f = filters.value;
  if (f.region !== 'all' && regionNames.value[f.region]) parts.push(tr(regionNames.value[f.region]));
  if (f.segment !== 'all') {
    const theme = forum.value?.themes.find((x) => x.id === f.segment);
    if (theme) parts.push(tr(theme.title));
  }
  if (f.size !== 'all') parts.push(t(`portfolio.bands.${f.size}`));
  if (f.query.trim()) parts.push(`“${f.query.trim()}”`);
  return parts.join(' · ');
});

const filtered = computed(
  () =>
    filters.value.region !== 'all' ||
    filters.value.segment !== 'all' ||
    filters.value.size !== 'all' ||
    filters.value.query.trim() !== '',
);

/* --------------------------------------------------- the open project */

/**
 * Which project is open is page state, not chapter state: it is in the URL,
 * so a link opens on the project rather than on the top of the page, and the
 * back button closes the sheet. See lib/state.ts.
 */
const selected = computed(
  () => projects.value.find((p) => p.id === openProjectId.value) ?? null,
);

/**
 * Where the open project sits in the list behind the sheet.
 *
 * The arrows walk the list the reader is actually looking at — filtered and
 * sorted as they left it — rather than all seventy-four in file order. A
 * project reached by link, or one the filters have since excluded, is not in
 * that list, and there is then nowhere to step to.
 */
const place = computed(() => {
  const i = shown.value.findIndex((p) => p.id === openProjectId.value);
  return i === -1 ? { index: 1, total: 1 } : { index: i + 1, total: shown.value.length };
});

function stepProject(delta: number): void {
  const i = shown.value.findIndex((p) => p.id === openProjectId.value);
  const next = i === -1 ? undefined : shown.value[i + delta];
  if (next) replaceProject(next.id);
}

/**
 * From the sheet to the enquiry form, carrying the project.
 *
 * Closing and scrolling cannot happen in the same tick: the sheet releases the
 * page scroll in a watcher, and a scroll asked for before that has run is
 * swallowed by the lock.
 */
async function enquire(): Promise<void> {
  const project = selected.value;
  if (!project) return;
  enquirySubject.value = { id: project.id, title: title(project), segment: project.segment };
  replaceProject(null);
  await nextTick();
  openEnquiry();
}

/**
 * A link naming a project that is not in this portfolio opens nothing, and
 * should not leave the name in the address bar either. Immediate, because the
 * chapter only mounts once the data is in — by the time this runs the list is
 * already there and will not change again.
 */
watch(
  [projects, openProjectId],
  () => {
    if (openProjectId.value && projects.value.length && !selected.value) replaceProject(null);
  },
  { immediate: true },
);

/* ------------------------------------------------------------- the map */

/**
 * Counted over the whole portfolio, not over what is currently shown: these
 * are the filter, and a filter that recounts itself as you use it tells you
 * nothing about what else is there.
 */
const count = (fn: (p: Project) => string | null) => {
  const out = new Map<string, number>();
  for (const p of projects.value) {
    const key = fn(p);
    if (key) out.set(key, (out.get(key) ?? 0) + 1);
  }
  return out;
};

/**
 * All fourteen, including the ones that brought nothing.
 *
 * Namangan has no project in this portfolio. It is still drawn and still
 * listed: a map of the country with a region missing from it would be a
 * different claim entirely, and "nothing yet" is itself worth seeing.
 */
const mapRegions = computed<MapRegion[]>(() => {
  const counts = count((p) => p.region);
  return Object.entries(regionNames.value)
    .map(([id, name]) => ({
      id,
      name: tr(name),
      count: counts.get(id) ?? 0,
      countText: t('portfolio.results', counts.get(id) ?? 0),
    }))
    .sort((a, b) => b.count - a.count || a.name.localeCompare(b.name));
});

const detail = computed<RegionDetail | null>(() => {
  const id = filters.value.region;
  const name = regionNames.value[id];
  if (id === 'all' || !name) return null;

  const here = projects.value.filter((p) => p.region === id);
  const usd = here.reduce((sum, p) => sum + (p.currency === 'USD' ? (p.investment ?? 0) : 0), 0);

  const byDirection = new Map<string, number>();
  for (const p of here) {
    if (p.segment) byDirection.set(p.segment, (byDirection.get(p.segment) ?? 0) + 1);
  }

  return {
    id,
    name: tr(name),
    countText: t('portfolio.results', here.length),
    soughtText: usd ? formatMoney(usd, 'USD', lang.value) : t('portfolio.noFigure'),
    directions: themes.value
      .filter((theme) => byDirection.has(theme.id))
      .map((theme) => ({ id: theme.id, label: tr(theme.title), count: byDirection.get(theme.id)! }))
      .sort((a, b) => b.count - a.count),
    top: [...here]
      .sort((a, b) => amount(b) - amount(a))
      .slice(0, 3)
      .map((p) => ({ id: p.id, title: title(p), money: moneyOf(p) })),
  };
});


/* ------------------------------------------------- the remaining bars */

const segmentRows = computed<BarRow[]>(() => {
  const counts = count((p) => p.segment);
  return themes.value
    .map((theme) => ({ id: theme.id, label: tr(theme.title), count: counts.get(theme.id) ?? 0 }))
    .sort((a, b) => b.count - a.count);
});

/**
 * The four size bands, and not the fifth.
 *
 * `bandOf` files a project with no dollar figure under `open`, and that row
 * used to be drawn with the rest: a bar labelled "not stated in dollars" in
 * a chart about how large the projects are, which is not a size. The twelve
 * projects behind it are all still in the list; they simply have nothing to
 * be measured against, so the chart no longer pretends they do.
 */
const sizeRows = computed<BarRow[]>(() => {
  const counts = count((p) => bandOf(p));
  return SIZE_BANDS.map((b) => b.id).map((id) => ({
    id,
    label: t(`portfolio.bands.${id}`),
    count: counts.get(id) ?? 0,
  }));
});

useChapterTimeline(
  () => root.value,
  ({ gsap, root: el }) => {
    gsap.from(el.querySelectorAll('.breakdown .bars__fill'), {
      scrollTrigger: { trigger: el.querySelector('.breakdown'), start: 'top 82%' },
      scaleX: 0,
      transformOrigin: '0% 50%',
      duration: 0.7,
      stagger: 0.02,
      ease: 'power2.out',
    });
  },
);
</script>

<template>
  <section id="portfolio" ref="root" class="chapter portfolio">
    <div class="shell">
      <p class="eyebrow chapter-mark">{{ t('chapters.portfolio.n') }}</p>
      <h2>{{ t('chapters.portfolio.title') }}</h2>
      <p class="portfolio__lead">
        {{
          totals.nationwide
            ? t('portfolio.leadSplit', {
                placed: totals.placed,
                regions: totals.regions,
                nationwide: totals.nationwide,
              })
            : t('portfolio.lead', { count: totals.projects, regions: totals.regions })
        }}
      </p>

      <ul class="portfolio__totals">
        <li>
          <CountFigure :value="totals.projects" />
          <span class="portfolio__caption">{{ t('portfolio.projectsLabel') }}</span>
        </li>
        <li>
          <CountFigure :value="totals.usd" :suffix="usdSuffix" />
          <span class="portfolio__caption">{{ t('portfolio.totalLabel') }}</span>
        </li>
        <li>
          <CountFigure :value="totals.regions" />
          <span class="portfolio__caption">{{ t('portfolio.regionsLabel') }}</span>
        </li>
      </ul>
      <p class="portfolio__footnote">
        {{ t('portfolio.totalNote', { n: totals.statedUsd, uzs: totals.statedUzs, open: totals.open }) }}
      </p>

      <div class="rule-rosette" aria-hidden="true"><span class="rule-rosette__mark" /></div>

      <!-- The map, which is also the navigation ---------------------------- -->
      <div class="region">
        <RegionMap
          :regions="mapRegions"
          :active="filters.region"
          :caption="t('portfolio.map.caption')"
          :empty-label="t('portfolio.map.none')"
          @pick="(id) => (filters.region = id)"
        />
        <RegionBrief
          :regions="mapRegions"
          :active="filters.region"
          :detail="detail"
          :nationwide="totals.nationwide"
          @pick="(id) => (filters.region = id)"
          @open="(id) => openProject(id)"
          @see-all="scrollToId('portfolio-list')"
        />
      </div>
      <!-- Controls -------------------------------------------------------- -->
      <div class="portfolio__controls">
        <p class="field field--search">
          <label for="pf-q">{{ t('portfolio.searchLabel') }}</label>
          <input
            id="pf-q"
            v-model="filters.query"
            type="search"
            autocomplete="off"
            :placeholder="t('portfolio.searchPlaceholder')"
          />
        </p>
        <p class="field">
          <label for="pf-sort">{{ t('portfolio.sortBy') }}</label>
          <select id="pf-sort" v-model="filters.sort">
            <option v-for="option in SORTS" :key="option" :value="option">
              {{ t(SORT_LABEL[option]) }}
            </option>
          </select>
        </p>
        <button type="button" class="btn portfolio__pdf" @click="portfolioToPdf">
          <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
            <path
              d="M12 3v10m0 0l-3.5-3.5M12 13l3.5-3.5M5 16v2.5A1.5 1.5 0 0 0 6.5 20h11a1.5 1.5 0 0 0 1.5-1.5V16"
              fill="none"
              stroke="currentColor"
              stroke-width="1.7"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <span>{{ t('portfolio.listPdf') }}</span>
        </button>
      </div>

      <!-- The two breakdowns a map cannot carry --------------------------- -->
      <div class="breakdown">
        <StatBars
          :title="t('portfolio.byDirection')"
          :rows="segmentRows"
          :active="filters.segment"
          @pick="(id) => (filters.segment = id)"
        />
        <StatBars
          :title="t('portfolio.bySize')"
          :rows="sizeRows"
          :active="filters.size"
          @pick="(id) => (filters.size = id as SizeBand)"
        />
      </div>

      <p class="portfolio__status" role="status">
        {{ t('portfolio.results', shown.length) }}
        <button v-if="filtered" type="button" class="portfolio__clear" @click="clearFilters">
          {{ t('portfolio.clear') }}
        </button>
      </p>

      <!-- Only on paper, and only when the PDF button asked for this one.
           A table rather than the cards: a reader with the list in their hand
           is comparing rows, and a photograph each would make it four times
           as long for nothing. -->
      <div class="portfolio__doc" aria-hidden="true">
        <!-- The page box has no margins (index.css), so the running head in
             this table's <thead> pays for the top of every page — see the
             same arrangement, and the reason for it, in ProjectPanel.vue. -->
        <table class="portfolio__paged">
          <thead>
            <tr>
              <td>
                <div class="portfolio__docHead">
                  <ForumMark form="lockup" variant="dark" class="portfolio__docMark" />
                  <p>{{ eventLine }}</p>
                </div>
              </td>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td class="portfolio__docCell">
        <h3>{{ t('chapters.portfolio.title') }}</h3>
        <p class="portfolio__docMeta">
          {{ t('portfolio.results', shown.length) }}<template v-if="printFilters"> · {{ printFilters }}</template>
        </p>
        <table class="portfolio__docTable">
          <thead>
            <tr>
              <th>{{ t('portfolio.docCols.project') }}</th>
              <th>{{ t('portfolio.docCols.region') }}</th>
              <th>{{ t('portfolio.docCols.direction') }}</th>
              <th class="portfolio__docNum">{{ t('portfolio.labels.investment') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="project in shown" :key="project.id">
              <td>{{ title(project) }}</td>
              <td>{{ project.region && regionNames[project.region] ? tr(regionNames[project.region]) : '—' }}</td>
              <td>{{ segmentName(project.segment) }}</td>
              <td class="portfolio__docNum">{{ moneyOf(project) }}</td>
            </tr>
          </tbody>
        </table>
        <p class="portfolio__docFoot">{{ t('portfolio.pdfFooter') }}</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- The list -------------------------------------------------------- -->
      <p v-if="!shown.length" class="portfolio__empty">{{ t('portfolio.empty') }}</p>

      <ol v-else id="portfolio-list" class="portfolio__list">
        <li v-for="project in shown" :key="project.id">
          <button
            type="button"
            class="row"
            :class="{ 'row--featured': project.featured, 'row--open': project.id === openProjectId }"
            @click="openProject(project.id)"
          >
            <!-- The sheets carry their own renders and photographs; a project
                 described only in figures reads as a spreadsheet row. -->
            <span class="row__shot" :class="{ 'row__shot--empty': !thumb(project) }">
              <!-- Intrinsic size given so the browser can schedule the decode
                   without measuring; CSS still sets how big it draws. -->
              <img
                v-if="thumb(project)"
                :src="thumb(project)!"
                alt=""
                width="220"
                height="150"
                loading="lazy"
                decoding="async"
              />
            </span>
            <span class="row__main">
              <span class="row__title">{{ title(project) }}</span>
              <span class="row__meta">
                {{ project.region && regionNames[project.region] ? tr(regionNames[project.region]) : subtitleOf(project) }}
                <template v-if="project.region && activityOf(project)"> · {{ activityOf(project) }}</template>
              </span>
            </span>
            <span
              class="row__money figure"
              :title="t('portfolio.asStated', { raw: project.investmentDisplay ?? '' })"
            >
              {{ moneyOf(project) }}
            </span>
            <!-- A row that opens something should look like it does. The word
                 is only drawn where there is room for it; the mark always. -->
            <span class="row__go">
              <span class="row__goText">{{ t('portfolio.open') }}</span>
              <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true">
                <path
                  d="M9 5l7 7-7 7"
                  fill="none"
                  stroke="currentColor"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </svg>
            </span>
          </button>
        </li>
      </ol>
    </div>

    <ProjectPanel
      :project="selected"
      :index="place.index"
      :total="place.total"
      @close="openProject(null)"
      @step="stepProject"
      @enquire="enquire"
    />
  </section>
</template>

<style scoped>
.portfolio {
  align-content: start;
  /* 7rem, not 5: the fixed bar is 93px and a chapter that sets its own top
     padding has to clear it at every window height, as .chapter now does. */
  padding-block-start: clamp(7rem, 14vh, 9rem);
}
h2 {
  font-size: clamp(2rem, 4.5vw, 3.5rem);
  margin-block-start: 0.75rem;
}
.portfolio__lead {
  margin-block-start: 1.5rem;
  color: var(--ink);
  font-size: 1.1rem;
}

.portfolio__totals {
  list-style: none;
  margin: clamp(2.5rem, 6vh, 3.5rem) 0 0;
  padding: 0;
  display: grid;
  gap: 1.5rem;
}
/* Two rows with the figure in a stretching first row, so a figure that wraps
   to two lines ("1 250,6 mln USD" does, in the band where the columns are
   narrowest) does not drop its own caption below the other two. */
.portfolio__totals li {
  display: grid;
  grid-template-rows: 1fr auto;
  gap: 0.2rem;
  padding-block-end: 1.25rem;
  border-block-end: 1px solid var(--hairline);
}
/* The figure is itself a <span>, so the caption needs its own class — a bare
   `span` rule here outranks .figure and greys the numbers out. */
.portfolio__totals :deep(.count-figure) {
  font-size: clamp(2rem, 4.6vw, 3.25rem);
  line-height: 1.05;
  text-wrap: balance;
}
.portfolio__caption {
  font-size: 0.85rem;
  color: var(--ink-dim);
}
@media (min-width: 48rem) {
  .portfolio__totals {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 2rem;
  }
}

.portfolio__footnote {
  margin-block-start: 1rem;
  max-width: 44rem;
  font-size: 0.8rem;
  color: var(--ink-dim);
}

/* The map and the panel that answers it. Stacked until there is room for the
   panel to stand beside the map without squeezing the Fergana valley into a
   smudge. */
.region {
  display: grid;
  gap: clamp(1.5rem, 4vw, 2.5rem);
}
@media (min-width: 64rem) {
  .region {
    grid-template-columns: minmax(0, 1fr) minmax(18rem, 23rem);
    align-items: start;
  }
}

.portfolio__controls {
  display: grid;
  gap: 0.75rem;
  margin-block-start: clamp(2.5rem, 6vh, 3.5rem);
}
@media (min-width: 52rem) {
  .portfolio__controls {
    grid-template-columns: minmax(0, 1fr) 14rem;
    align-items: end;
  }
}
.field {
  display: grid;
  gap: 0.3rem;
}
.field label {
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--ink-dim);
}
.field input,
.field select {
  width: 100%;
  padding: 0.7rem 0.9rem;
  border: 1px solid var(--hairline);
  border-radius: 0.25rem;
  background: var(--surface-2);
  color: var(--ink);
  font: inherit;
  font-size: 0.9rem;
}
@media (hover: hover) {
  .field input:hover,
  .field select:hover {
    border-color: var(--gold);
  }
}
/* Under 16px, iOS zooms the page in when a field takes focus and does not
   zoom back out; the reader is then left on a page half a screen wide. */
@media (max-width: 48rem) {
  .field input,
  .field select {
    min-block-size: var(--tap);
    font-size: 1rem;
  }
}

.portfolio__status {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 1rem;
  margin-block-start: 1.75rem;
  font-size: 0.85rem;
  color: var(--ink-soft);
}
.portfolio__clear {
  padding: 0.3rem 0.8rem;
  border: 1px solid var(--hairline);
  border-radius: 0.2rem;
  background: transparent;
  color: var(--ink-dim);
  font: inherit;
  font-size: 0.75rem;
  cursor: pointer;
}
@media (hover: hover) {
  .portfolio__clear:hover {
    color: var(--ink);
    border-color: var(--gold);
  }
}

/* Two columns on a wide screen, stacked below. */
.breakdown {
  margin-block-start: clamp(2rem, 5vh, 3rem);
  display: grid;
  gap: clamp(1.75rem, 4vw, 2.5rem);
}
@media (min-width: 52rem) {
  .breakdown {
    /* The direction labels are the longer of the two sets. */
    grid-template-columns: 1.3fr 1fr;
  }
}

.portfolio__empty {
  margin-block-start: 2rem;
  color: var(--ink-dim);
}

.portfolio__list {
  list-style: none;
  margin: clamp(2rem, 5vh, 3rem) 0 0;
  padding: 0;
}
/**
 * Seventy-four rows, each with an image, is a lot of layout for a section most
 * visitors scroll past, and `content-visibility: auto` used to skip the ones
 * off screen. It has been taken out.
 *
 * Skipped content is not re-laid-out when the window is resized, and in
 * Chrome the stale layout escapes the skipped subtree: resize the window
 * narrower after scrolling past this chapter and `.shell` keeps the width it
 * had — the whole page then sits at its old size inside a smaller window,
 * with the map running off the side. It is a browser bug rather than a
 * mistake in this rule, but the rule is what triggers it, and a page that
 * does not survive being resized is not worth 120 milliseconds.
 *
 * The rows are cheap anyway: their images are lazy and their layout is a
 * three-column grid. Measured without it, the phone scroll holds the same
 * 16.7 ms median and Lighthouse does not move.
 */

/**
 * One project, as a row.
 *
 * Two shapes, named rather than reordered by hand: on a phone the figure drops
 * under the title and the thumbnail and the chevron run the full height beside
 * them, because a money column on a 360px screen leaves the title about three
 * words. On anything wider the four parts sit in a line.
 */
.row {
  display: grid;
  /* The thumbnail column is a fixed width, not `auto`: a row whose project has
     no photograph shows a narrower mark, and an auto column took its width
     from each row's own content, so every such row started its title further
     left than the rest. */
  grid-template-columns: 4.5rem minmax(0, 1fr) auto;
  grid-template-areas:
    'shot main go'
    'shot money go';
  align-items: center;
  gap: 0.2rem 0.9rem;
  width: 100%;
  min-block-size: var(--tap);
  padding: 0.7rem 0.5rem;
  border: 0;
  border-block-end: 1px solid var(--hairline);
  border-radius: 0.25rem;
  background: transparent;
  color: inherit;
  font: inherit;
  text-align: start;
  cursor: pointer;
  transition: background-color 0.18s, color 0.18s;
}
@media (min-width: 40rem) {
  .row {
    grid-template-columns: 4.5rem minmax(0, 1fr) auto auto;
    grid-template-areas: 'shot main money go';
    gap: 1rem;
  }
}
@media (hover: hover) {
  .row:hover {
    background: var(--surface-2);
    color: var(--ink);
  }
}
.row--featured {
  background: var(--raise);
}
/* The row whose sheet is open, so closing it lands the reader back on a row
   they can see they came from. */
.row--open {
  background: var(--surface-2);
  box-shadow: inset 2px 0 0 var(--gold-leaf);
}

.row__shot {
  grid-area: shot;
  display: block;
  inline-size: 4.5rem;
  block-size: 3.2rem;
  border-radius: 0.2rem;
  overflow: hidden;
  background: var(--surface-2);
}
.row__shot img {
  inline-size: 100%;
  block-size: 100%;
  object-fit: cover;
  display: block;
}
/**
 * A project whose sheet carried no usable image.
 *
 * It keeps the full width of the slot and draws a narrower arch inside it,
 * centred — the arch is a portrait shape and a photograph is a landscape one,
 * and the slot has to stay the same width either way or the titles beside it
 * do not line up. It used to be the narrow shape itself, which is what made
 * every row without a photograph start further left than the rest.
 *
 * The arch is the one the panel shows a photograph through, with a lattice
 * behind it: a window onto a view that has not been supplied, which is
 * exactly what it is.
 */
.row__shot--empty {
  position: relative;
  background: transparent;
  overflow: visible;
}
.row__shot--empty::before,
.row__shot--empty::after {
  content: '';
  position: absolute;
  inset-block: 0;
  /* Centred in the slot: (4.5rem - 2.6rem) / 2. */
  inset-inline: 0.95rem;
  clip-path: url(#uz-arch);
}
.row__shot--empty::before {
  background-color: color-mix(in srgb, var(--accent-deep) 7%, transparent);
}
/* The lattice is a layer of its own so it can be held back to a quarter: at
   full strength, and at a tile size where only one star fits in a mark this
   small, it reads as a black asterisk rather than as a screen. */
.row__shot--empty::after {
  opacity: 0.25;
  background-image: var(--ornament-panjara);
  background-size: 19px auto;
  background-position: center;
}
.row__main {
  grid-area: main;
  display: grid;
  gap: 0.15rem;
  min-width: 0;
}
.row__title {
  color: var(--ink);
  font-size: 1rem;
}
.row__meta {
  font-size: 0.8rem;
  color: var(--ink-dim);
}
.row__money {
  grid-area: money;
  font-size: 1rem;
  color: var(--accent);
  white-space: nowrap;
}

/* The chevron, and the word beside it once there is room to set it. */
.row__go {
  grid-area: go;
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  color: var(--ink-dim);
  font-size: 0.78rem;
  font-weight: 600;
  white-space: nowrap;
  transition: color 0.18s, gap 0.18s;
}
@media (hover: hover) {
  .row:hover .row__go {
    color: var(--accent);
    gap: 0.55rem;
  }
}
.row__goText {
  display: none;
}
@media (min-width: 60rem) {
  .row__goText {
    display: inline;
  }
}

/* ------------------------------------------------------------------ print */

/**
 * The filtered list, as a document.
 *
 * `data-print="portfolio"` is set by the PDF button for the length of the
 * print, so this and the single sheet's print layout never have to guess
 * which of them was wanted. Everything else on the page goes, including the
 * chapter the table is inside.
 */
.portfolio__doc {
  display: none;
}

@media print {
  :root[data-print='portfolio'] .portfolio__doc {
    display: block;
  }
  /* The chapter's own furniture, and everything the table does not need. */
  :root[data-print='portfolio'] .portfolio__lead,
  :root[data-print='portfolio'] .portfolio__totals,
  :root[data-print='portfolio'] .portfolio__footnote,
  :root[data-print='portfolio'] .portfolio__controls,
  :root[data-print='portfolio'] .portfolio__status,
  :root[data-print='portfolio'] .portfolio__empty,
  :root[data-print='portfolio'] .portfolio__list,
  :root[data-print='portfolio'] .breakdown,
  :root[data-print='portfolio'] .region,
  :root[data-print='portfolio'] .rule-rosette,
  :root[data-print='portfolio'] .chapter-mark,
  :root[data-print='portfolio'] h2 {
    display: none !important;
  }

  .portfolio__paged {
    inline-size: 100%;
    border-collapse: collapse;
  }
  .portfolio__paged > thead > tr > td,
  .portfolio__paged > tbody > tr > td {
    padding: 0;
  }
  /* The margins the page box no longer has, on the cell that holds the
     whole document — the table inside it sizes itself to what is left. */
  .portfolio__docCell {
    padding: 0 14mm 16mm !important;
  }
  .portfolio__docHead {
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
  .portfolio__docMark {
    inline-size: 54mm;
    flex: none;
  }
  .portfolio__docHead p {
    margin: 0;
    font-size: 9pt;
    color: #4a5070;
  }
  .portfolio__doc h3 {
    margin-block: 1.2rem 0.2rem;
    font-size: 17pt;
  }
  .portfolio__docMeta {
    margin: 0;
    font-size: 9pt;
    color: #4a5070;
  }
  .portfolio__docTable {
    inline-size: 100%;
    margin-block-start: 1rem;
    border-collapse: collapse;
    font-size: 8.5pt;
  }
  .portfolio__docTable th {
    text-align: start;
    font-size: 7.5pt;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #4a5070;
    padding-block: 0.35rem;
    border-block-end: 1px solid #9aa0b8;
  }
  .portfolio__docTable td {
    padding-block: 0.3rem;
    border-block-end: 1px solid #e2e4ec;
    vertical-align: top;
  }
  .portfolio__docNum {
    text-align: end;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
  }
  /* A row is never split across a page, and the header repeats on each. */
  .portfolio__docTable tr {
    break-inside: avoid;
  }
  .portfolio__docTable thead {
    display: table-header-group;
  }
  .portfolio__docFoot {
    margin-block-start: 1.2rem;
    padding-block-start: 0.5rem;
    border-block-start: 1px solid #c9ccd8;
    font-size: 8pt;
    color: #4a5070;
  }
}
</style>
