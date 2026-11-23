/**
 * Types for public/data/forum-2026.json.
 *
 * Written by hand to mirror what scripts/build-forum-data.mjs emits. Only site 2
 * reads this file, so it lives here rather than in shared/lib.
 */
import type { I18nText } from './shared/content-types';

export interface ForumEvent {
  name: I18nText;
  shortName: I18nText;
  edition: I18nText;
  dateStart: string;
  dateEnd: string;
  city: I18nText;
  country: I18nText;
  within: I18nText;
  organiser: I18nText;
  motto: { line1: I18nText; line2: I18nText };
  format: I18nText;
  source: string;
}

export interface ForumTheme {
  id: string;
  icon: string;
  title: I18nText;
  body: I18nText;
}

/**
 * One speaker on the forum's stage.
 *
 * Kept entirely in the admin's overlay: the build ships an empty list, the
 * organiser fills it in the weeks before the forum, and no later build can
 * overwrite what they typed. `photo` is a stem under `photos/`, the same
 * contract a project's pictures use.
 */
export interface ForumSpeaker {
  id: string;
  name: I18nText;
  /**
   * Null when the organiser sent a name and an organisation and no title.
   *
   * Several of the speakers who arrived on 20 November 2026 came as a folder
   * of portraits, each file named for the person and the body they come from
   * and nothing else. Rather than invent a title, the card prints the name
   * and the organisation and leaves the middle line out.
   */
  role: I18nText | null;
  org: I18nText;
  photo: string | null;
  /**
   * Which figure stood in the arch when there was no portrait.
   *
   * There was a choice of three for two days, set per speaker in the admin.
   * There is one figure now — see SpeakerMark.vue — so nothing reads this;
   * it is declared because older records still carry it and a data file is
   * not rewritten to suit a type.
   */
  icon?: string | null;
}

/** One of the pictures the title card turns between. */
export interface ForumHeroShot {
  /** The picture itself; a stem under `photos/`. */
  photo: string;
  /** Whose photograph it is, printed under the card while it is showing. */
  credit?: string | null;
  /**
   * True while the picture is one made for this page rather than a
   * photograph of the venue. The card then says so, in the reader's own
   * language, so nobody takes it for the building they are coming to.
   */
  illustrative?: boolean;
}

export interface ForumMedia {
  /**
   * The pictures behind the title card, in the order they come round.
   *
   * A string is accepted too: that is the shape the file had before the
   * card turned between several, and a server may still be publishing one
   * written by an older build. ChPrologue.vue reads both shapes.
   */
  hero: ForumHeroShot[] | string | null;
}

export interface ForumParticipant {
  id: string;
  category: I18nText;
  who: I18nText;
}

/** One line of a session's own timetable. */
export interface SessionItem {
  start: string;
  /** Absent where the document leaves it absent, or states it impossibly. */
  end?: string;
  title: I18nText;
  speaker?: I18nText;
}

export interface ForumSession {
  id: string;
  number: number;
  title: I18nText;
  workingTitle: string;
  topics: I18nText;
  /** The hours the session occupies, as one string: "14:05–18:00". */
  time?: string;
  items?: SessionItem[];
}

export interface ProgrammeItem {
  start: string;
  /** The last item of the day has a start and no end. */
  end?: string;
  kind:
    | 'registration'
    | 'break'
    | 'opening'
    | 'speech'
    | 'keynote'
    | 'plenary'
    | 'parallel'
    | 'showcase'
    | 'b2b'
    | 'closing';
  plenary?: number;
  title: I18nText;
  body?: I18nText;
  bullets?: I18nText[];
}

/** A room in the venue, with the floor area and capacity the leaflet prints. */
export interface ProgrammeHall {
  id: string;
  name: I18nText;
  /** Square metres, or null for a place the documents only name. */
  area: number | null;
  /** Absent for the hall that runs one all-day activity rather than seating. */
  seats: number | null;
  /**
   * What to call it.
   *
   * A zone is the open-space B2B floor and the second floor: the programme
   * says things happen there and gives neither an area nor a seat count, so
   * the page names it a zone and prints no measurements.
   */
  kind?: 'hall' | 'zone';
}

/**
 * A named slot inside a long item.
 *
 * Two documents fill these in and they say different amounts. The leaflet's
 * media campus is three titles, numbered 3.1, a break, 3.3 — no hours of
 * their own. ICCA's education programme for 26 November is an hour-by-hour
 * timetable with the people who run each hour, inside what the leaflet knew
 * only as one all-day block. So everything but the title is optional, and
 * what is printed follows what the source actually said.
 */
export interface ProgrammePart {
  /** "3.1" — the leaflet's own numbering, which skips 3.2. */
  n?: string;
  /** Where the source gives the part its own hours. */
  start?: string;
  end?: string;
  kind?: 'break';
  title: I18nText;
  /** Who runs it, where the source names them. */
  by?: I18nText;
  /** One line of detail the title cannot carry. */
  note?: I18nText;
}

export interface DayItem {
  start: string;
  end?: string;
  kind:
    | 'break'
    | 'opening'
    | 'session'
    | 'forum'
    | 'commission'
    | 'award'
    | 'closing'
    | 'hackathon'
    | 'b2b'
    /* ICCA's day on the 26th, which the leaflet knew only as one block and
       the association's own programme sets out hour by hour. */
    | 'registration'
    | 'keynote'
    | 'training'
    | 'workshop'
    | 'networking';
  /** The session's number within its hall, as the leaflet prints it. */
  n?: number;
  title: I18nText;
  /** What the session is, where the source says more than the title does. */
  note?: I18nText;
  /** Who runs it, where the source names them. */
  by?: I18nText;
  parts?: ProgrammePart[];
}

/** One hall's day. */
export interface ProgrammeTrack {
  hall: string;
  items: DayItem[];
}

/** The signing zone, which is not a hall and opens on its own hours. */
export interface SigningSlot {
  start: string;
  end?: string;
  title: I18nText;
  body: I18nText;
}

/**
 * The evening, which is not a hall either.
 *
 * Every one of the three days ends with a dinner in the organiser's
 * programme, each for a different set of guests and in a different building,
 * so the venue is part of the line rather than a detail left out.
 */
export interface DinnerSlot {
  start: string;
  end?: string;
  title: I18nText;
  venue?: I18nText;
}

export interface ProgrammeDay {
  /** 1, 2, 3 — what the leaflet calls «1-кун». */
  n: number;
  /** ISO, so the page can tell which day is today without parsing a label. */
  date: string;
  label: I18nText;
  weekday: I18nText;
  /** True on the day the investment forum itself is held. */
  forumDay?: boolean;
  headline: I18nText;
  tracks: ProgrammeTrack[];
  signings: SigningSlot[];
  /** Absent in the data files that predate the dinners being published. */
  dinners?: DinnerSlot[];
}

export interface ForumProgramme {
  note: I18nText;
  halls: ProgrammeHall[];
  days: ProgrammeDay[];
  source: string;
  /** The invitation's preliminary day one — see the generator for why it stays. */
  day: I18nText;
  items: ProgrammeItem[];
  supersededSource?: string;
}

export interface StandardBlock {
  n: number;
  block: I18nText;
  body: I18nText;
}

export interface MechanismStep {
  n: number;
  title: I18nText;
  body: I18nText;
}

export interface PreparationStage extends MechanismStep {
  roman: string;
}

/** A label and value exactly as the project sheet states them. */
export interface Stat {
  label: string;
  value: string;
  /**
   * Set only where the value states a sum of money, so the page can print it
   * in one format instead of the five the sheets use. `value` still holds the
   * sheet's own wording; see `pairs` in scripts/build-forum-data.mjs.
   */
  amount?: number;
  currency?: string;
}

/**
 * One investment project, as parsed out of its sheet.
 *
 * The organiser has sent three generations of the sheet template with different
 * field lists, so everything beyond the title and the money is carried as
 * label/value lists in the sheet's own words rather than squeezed into a fixed
 * schema. A project that states its number of beds keeps it.
 */
export interface Project {
  id: string;
  region: string | null;
  segment: string;
  title: string;
  displayTitle?: string;
  subtitle: string | null;
  /**
   * Set on the projects that did not arrive as a sheet and were transcribed by
   * hand. They sort to the front and carry their text in all three languages,
   * because their source document was not in English to begin with.
   */
  featured?: boolean;
  titleI18n?: I18nText;
  subtitleI18n?: I18nText;
  overviewI18n?: I18nText;
  /** Image stems under public/photos/, as `<stem>-thumb.webp` and `-full.webp`. */
  photos: string[];
  /** Normalised to whole units of `currency`; null when the sheet left it open. */
  investment: number | null;
  currency: 'USD' | 'UZS' | null;
  /**
   * The same figure in dollars, for a project stated in soums.
   *
   * Converted at the Central Bank's rate for the day of the build — see
   * `portfolio.fx`, which carries the rate and its date. It exists so a reader
   * can compare a soum project with a dollar one and filter by ticket size
   * across both; the stated figure is still `investment` and is what the page
   * prints first. Null for everything already in dollars, and for a project
   * with no figure at all.
   */
  usdEquivalent: number | null;
  /** The figure exactly as the sheet writes it — including "By agreement". */
  investmentDisplay: string | null;
  /**
   * What kind of sum the figure is, when it is not a budget.
   *
   * Bukhara's sixteen auction plots state a starting price — what it costs to
   * begin bidding, not what building on the plot would take. The number is the
   * region's own; this changes the word printed above it, because an investor
   * reading "Investment: USD 509,000" against a hotel plot is reading the
   * wrong fact. Absent on every project that states a budget.
   */
  priceKind?: 'starting' | null;
  /** The headline chips: INVESTMENT, LAND AREA, CAPACITY, BEDS, OPERATOR… */
  metrics: Stat[];
  /** What the project consists of. */
  concept: Stat[];
  /** The INVESTMENT OPPORTUNITY table. */
  opportunity: Stat[];
  /** The WHY INVEST argument, point by point. */
  whyInvest: Stat[];
  /** Access and surroundings. */
  place: Stat[];
  overview: string | null;
  statusNote: string | null;
  /** Every sheet this project was found in. */
  sheets: string[];
  /** Machine-detected problems and applied corrections; for QA, not for display. */
  issues: string[];
}

/** The organising committee's own contact details. */
export interface ForumContact {
  organisation: I18nText;
  email: string;
  phone: string;
  phoneHref: string;
  shortPhone: string;
  postcode: string;
  address: I18nText;
  lat: number;
  lon: number;
  mapUrl: string;
}

export interface ForumSourceRef {
  id: string;
  title: I18nText;
  publisher: I18nText;
  document: string;
  asOf: string;
}

export interface ForumData {
  meta: {
    version: string;
    collected_at: string;
    languages: string[];
    default_language: string;
    disclaimer_enabled: boolean;
    note: string;
    generated_by: string;
  };
  event: ForumEvent;
  /**
   * Where the registration buttons go. Optional, because a data file built
   * before 18 November 2026 does not have it and the page still has to
   * work — see register-link.ts for what is done without it.
   */
  registration?: { external: boolean; url: string };
  intro: { purpose: I18nText; paragraphs: I18nText[]; source: string };
  goals: I18nText[];
  themes: ForumTheme[];
  participants: ForumParticipant[];
  speakers: ForumSpeaker[];
  media: ForumMedia;
  sessions: ForumSession[];
  programme: ForumProgramme;
  standard: StandardBlock[];
  criteria: { items: I18nText[]; note: I18nText };
  mechanism: MechanismStep[];
  stages: PreparationStage[];
  outcomes: {
    quantitative: I18nText[];
    quantitativeNote: I18nText;
    qualitative: I18nText[];
    source: string;
  };
  promotion: I18nText[];
  contact: ForumContact;
  portfolio: {
    projects: Project[];
    regionNames: Record<string, I18nText>;
    regionCounts: Record<string, number>;
    segmentCounts: Record<string, number>;
    totalUsd: number;
    statedUsdCount: number;
    statedUzsCount: number;
    openCount: number;
    /**
     * The rate the soum figures are also shown in dollars at.
     *
     * The Central Bank's own published figure with the day it was published,
     * so the page can say so rather than present a conversion as a fact of
     * the project. Null if no rate was stored, in which case no equivalent is
     * computed either and the soum figures stand alone, as they used to.
     */
    fx: { uzsPerUsd: number; date: string; source: string } | null;
    contributedBy: I18nText[];
  };
  sources: ForumSourceRef[];
}

export const FORUM_URL = 'data/forum-2026.json';

/**
 * Always ask whether this file has changed.
 *
 * Everything else the page loads is named with a content hash — a new build
 * is a new filename, so a stale copy can never be served. This file is the
 * one exception: its name never changes, and it has to be that way, because
 * the admin rewrites it between builds and a deploy is not required for an
 * edit to appear.
 *
 * Which is exactly what bit us. The server sends no `Cache-Control` for it,
 * and a response with no freshness information is cached *heuristically* —
 * browsers keep it for a fraction of how long it had already been
 * unchanged. On 17 November 2026 that meant a reader who had opened the
 * page before got the new JavaScript, the new stylesheet and the new
 * layout, over a day-old portfolio and a four-name speaker list. It looked
 * for all the world like the deploy had not happened.
 *
 * `cache: 'no-cache'` does not mean "do not cache" — it means "revalidate
 * before using". The browser still keeps the file and still sends
 * `If-None-Match`; when nothing has changed the server answers 304 with no
 * body, which costs one round trip and no bandwidth. When something has
 * changed, the reader gets it. That is the only correct behaviour for a
 * file whose whole purpose is to change without a build.
 */
export async function loadForum(signal?: AbortSignal): Promise<ForumData> {
  /* index.html starts this request while it is still being parsed — see
     `forumFacts` in vite.config.ts. Taken once: a Response body can only be
     read once, so a retry after a failure makes its own request. */
  const early = (window as unknown as { __forum?: Promise<Response> }).__forum;
  if (early) delete (window as unknown as { __forum?: Promise<Response> }).__forum;
  const res = early
    ? await early
    : await fetch(FORUM_URL, { signal: signal ?? null, cache: 'no-cache' });
  if (!res.ok) throw new Error(`forum-2026.json responded ${res.status}`);
  return (await res.json()) as ForumData;
}
