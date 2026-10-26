/**
 * Content + language state for site 2.
 *
 * A module-level reactive store rather than provide//inject: every chapter needs
 * the same two things, and there is exactly one of each per page.
 */
import { computed, ref, watch } from 'vue';

import { formatDateRange, loadContent } from './shared/content';
import { isLang, type Content, type Lang } from './shared/content-types';
import { applyDocumentSeo } from './shared/seo';
import { loadForum, type ForumData } from './forum-types';
import { DEFAULT_DATES, i18n, UI_META } from './i18n';

const LANG_KEY = 'iu-lang';

const content = ref<Content | null>(null);
const forum = ref<ForumData | null>(null);
const error = ref<string | null>(null);
const lang = ref<Lang>(readInitialLang());

function readInitialLang(): Lang {
  const fromQuery = new URLSearchParams(window.location.search).get('lang');
  if (isLang(fromQuery)) return fromQuery;
  const stored = localStorage.getItem(LANG_KEY);
  if (isLang(stored)) return stored;
  return 'uz';
}

let started = false;
export function startLoading(): void {
  if (started) return;
  started = true;
  // Both files or neither: every chapter after the prologue needs the forum
  // data, and a page that renders half its facts is worse than an error.
  Promise.all([loadContent(), loadForum()])
    .then(([site, event]) => {
      content.value = site;
      forum.value = event;
    })
    .catch((cause: unknown) => {
      error.value = cause instanceof Error ? cause.message : String(cause);
    });
}

export function retry(): void {
  error.value = null;
  started = false;
  startLoading();
}

/**
 * Portfolio filter state.
 *
 * It lives here rather than inside the portfolio chapter because chapter III
 * links into it: picking a theme there sets the segment and scrolls down, and
 * the portfolio has to already be showing that segment when it arrives.
 */
export type PortfolioSort = 'largest' | 'smallest' | 'region' | 'name';

/**
 * Ticket-size bands, in the units the sheets state. A project whose figure is
 * open, or stated in soums, is in `open` — it cannot be put in a dollar band
 * without inventing a rate.
 */
export type SizeBand = 'all' | 'lt1' | 'm1to10' | 'm10to50' | 'gte50' | 'open';

export const SIZE_BANDS: { id: Exclude<SizeBand, 'all'>; min: number; max: number }[] = [
  { id: 'lt1', min: 0, max: 1e6 },
  { id: 'm1to10', min: 1e6, max: 1e7 },
  { id: 'm10to50', min: 1e7, max: 5e7 },
  { id: 'gte50', min: 5e7, max: Infinity },
];

/** Which band a project falls in, or 'open' when it states no dollar figure. */
export function bandOf(project: { investment: number | null; currency: string | null }): string {
  if (project.currency !== 'USD' || !project.investment) return 'open';
  return SIZE_BANDS.find((b) => project.investment! >= b.min && project.investment! < b.max)?.id ?? 'open';
}

export const filters = ref({
  region: 'all',
  segment: 'all',
  size: 'all' as SizeBand,
  query: '',
  sort: 'largest' as PortfolioSort,
});

export function showSegment(segment: string): void {
  filters.value = { ...filters.value, segment, region: 'all', size: 'all', query: '' };
}

export function clearFilters(): void {
  filters.value = { region: 'all', segment: 'all', size: 'all', query: '', sort: filters.value.sort };
}

/* ------------------------------------------------- the open project */

/**
 * Which project is open, as page state rather than component state.
 *
 * It lives here, and in the URL, because a project is the one thing on this
 * page a visitor has a reason to send to somebody else. An investor who finds
 * the Bukhara caravanserai should be able to paste a link that opens on the
 * caravanserai, not on the top of a page with a note to scroll to chapter IV
 * and search for it. Deep-linking also makes the browser's back button do what
 * it looks like it should: close the sheet.
 *
 * `?project=` is written with pushState when a project is opened by hand, so
 * each one is a history entry, and replaced rather than pushed when the opening
 * came from the URL itself.
 */
const PROJECT_PARAM = 'project';
export const openProjectId = ref<string | null>(
  new URLSearchParams(window.location.search).get(PROJECT_PARAM),
);

function writeProjectParam(id: string | null, push: boolean): void {
  const url = new URL(window.location.href);
  if (id) url.searchParams.set(PROJECT_PARAM, id);
  else url.searchParams.delete(PROJECT_PARAM);
  if (url.href === window.location.href) return;
  if (push) window.history.pushState({ [PROJECT_PARAM]: id }, '', url);
  else window.history.replaceState({ [PROJECT_PARAM]: id }, '', url);
}

export function openProject(id: string | null): void {
  if (openProjectId.value === id) return;
  openProjectId.value = id;
  writeProjectParam(id, true);
}

/** Moving between projects inside the sheet replaces rather than stacks. */
export function replaceProject(id: string | null): void {
  openProjectId.value = id;
  writeProjectParam(id, false);
}

window.addEventListener('popstate', () => {
  openProjectId.value = new URLSearchParams(window.location.search).get(PROJECT_PARAM);
});

/* ------------------------------------------------- the enquiry subject */

/**
 * The project an enquiry is about, carried from chapter IV to the epilogue.
 *
 * Until now the catalogue and the enquiry form were two things on one page
 * with nothing between them: a visitor who had just read a project sheet had
 * to scroll to the bottom and describe it again from memory. This hands the
 * form what they were looking at.
 */
export const enquirySubject = ref<{ id: string; title: string; segment: string | null } | null>(
  null,
);

/**
 * The event dates as one string: "25–27 noyabr 2026".
 *
 * Every date the page shows comes through here, so that moving the forum is
 * one edit in the admin — `event.dateStart` and `event.dateEnd` — rather
 * than a search through the source for "25".
 *
 * `withYear: false` is for the page title, which has already said 2026.
 * `at` is for the head, which is written for the language being switched to
 * before the ref has caught up.
 */
export function eventDates(withYear = true, at: Lang = lang.value): string {
  const event = forum.value?.event;
  if (!event) return '';
  return formatDateRange(`${event.dateStart}/${event.dateEnd}`, at, withYear);
}

export function setLang(next: Lang): void {
  lang.value = next;
}

/**
 * The meta description quotes the size of the portfolio, which grows every time
 * the organiser sends another batch of sheets. The counts are therefore filled
 * in from the data rather than written into the string — and because the
 * language can change before the data has loaded, this watches both.
 */
function describe(next: Lang): string {
  const p = forum.value?.portfolio;
  return UI_META[next].description
    .replace('{count}', String(p?.projects.length ?? ''))
    .replace('{regions}', String(Object.keys(p?.regionCounts ?? {}).length || ''));
}

/** The page title, with the dates the data gives — or the built-in default. */
function headline(next: Lang): string {
  return UI_META[next].title.replace('{dates}', eventDates(false, next) || DEFAULT_DATES[next]);
}

watch(
  [lang, forum],
  ([next]) => {
    i18n.global.locale.value = next;
    localStorage.setItem(LANG_KEY, next);
    applyDocumentSeo({ lang: next, title: headline(next), description: describe(next) });
    const url = new URL(window.location.href);
    url.searchParams.set('lang', next);
    window.history.replaceState(null, '', url);
  },
  { immediate: true },
);

/**
 * The refs are handed out as-is rather than wrapped in `readonly()`: Vue's
 * DeepReadonly rewrites every array in Content to `readonly T[]`, which then no
 * longer matches the shared helper signatures. Mutation is discouraged by there
 * being no reason to — `setLang` and `retry` are the only writers.
 */
export const useStore = () => ({
  content,
  forum,
  error,
  lang,
  isReady: computed(() => content.value !== null && forum.value !== null),
});

/** Read a {uz, ru, en} triple with the active language. */
export function tr(text: { uz: string; ru: string; en: string } | undefined): string {
  if (!text) return '';
  return text[lang.value] || text.en || text.uz;
}

/** Read a `prefix_uz` / `prefix_ru` / `prefix_en` family with the active language. */
export function trSuffixed<T>(row: Record<string, unknown>, prefix: string): T {
  return (row[`${prefix}_${lang.value}`] ?? row[`${prefix}_en`] ?? row[`${prefix}_uz`]) as T;
}
