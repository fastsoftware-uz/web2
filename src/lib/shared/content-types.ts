/**
 * Types for shared/data/content.json — the single source of truth for all three
 * sites. Generated nothing: this file is hand-written to mirror the JSON exactly.
 *
 * Copied into each site by scripts/sync-shared.mjs. Do not edit the copies.
 */

export const LANGS = ['uz', 'ru', 'en'] as const;
export type Lang = (typeof LANGS)[number];

export function isLang(value: unknown): value is Lang {
  return typeof value === 'string' && (LANGS as readonly string[]).includes(value);
}

/** A translatable string triple as it appears in the JSON. */
export interface I18nText {
  uz: string;
  ru: string;
  en: string;
}

export interface SourceRef {
  id: string;
  title: string;
  url: string;
  accessed: string;
  /**
   * When the issuing body published it, where that is knowable and different
   * from when it was read. A statistics release is only as current as its own
   * date, and a reader checking a figure needs to know which release it came
   * out of, not only when somebody looked.
   */
  published?: string;
  type: 'official' | 'big4' | 'international' | 'media';
}

/** One country's arrivals, named through `Tourism.country_names`. */
export interface TourismMarket {
  id: string;
  value: number;
}

/** One count of arrivals over a stated period, with its own source. */
export interface TourismArrivals {
  source: string;
  published: string;
  /** "2025", or "2026-01/2026-08" where the count is part of a year. */
  period: string;
  total: number;
  growth_abs?: number;
  growth_pct: number;
  markets: TourismMarket[];
}

/**
 * Tourism, as the Statistics Agency and the 2030 strategy state it.
 *
 * Two counts of arrivals rather than one: the last complete year, and the
 * most recent eight months, which are not comparable with each other and are
 * therefore kept apart instead of being spliced into a series.
 *
 * `services_export_2026_8m` carries shares and no sum for travel, because the
 * release carries shares and no sum for travel. Multiplying one by the other
 * would produce a number no official body has published.
 */
export interface Tourism {
  sources: string[];
  as_of: string;
  country_names: Record<string, I18nText>;
  arrivals_2025: TourismArrivals;
  arrivals_2026_8m: TourismArrivals;
  services_export_2026_8m: {
    source: string;
    published: string;
    period: string;
    preliminary: boolean;
    total_usd: number;
    share_of_exports_pct: number;
    growth_pct: number;
    mix: { id: string; share: number; label: I18nText }[];
    /** The caveat the release carries, in the release's own terms. */
    note: I18nText;
  };
  targets_2030: {
    source: string;
    /** The act the targets are written in, for anybody who wants to read it. */
    document: string;
    goal: number;
    items: Kpi[];
  };
}

export interface Kpi {
  id: string;
  value: number;
  prefix?: string;
  suffix?: string;
  label: I18nText;
}

export interface GdpSlice {
  id?: string;
  share: number;
  label: I18nText;
  children?: GdpSlice[];
}

export interface GrowthDriver {
  id: string;
  value: string;
  label: I18nText;
}

export interface ServiceItem extends I18nText {
  id: string;
  icon: string;
}

export interface RegionProfile {
  population_k: number;
  births_2025_k: number;
  employed_k: number;
  working_age_k: number;
  employment_rate: number;
  unemployment_rate: number;
  universities: number;
  students_k: number;
  specializations: string[];
}

export interface Region extends I18nText {
  id: string;
  avg_salary: number;
  profile?: RegionProfile;
}

export interface Sector extends I18nText {
  id: string;
  url: string;
  icon: string;
}

export interface Priority extends I18nText {
  n: string;
  goals?: number;
  targets_uz?: string[];
  targets_ru?: string[];
  targets_en?: string[];
}

export interface SezPoint extends I18nText {
  source: string;
}

export interface JourneyStep extends I18nText {
  step: number;
  desc_uz: string;
  desc_ru: string;
  desc_en: string;
}

export interface TimelineEntry {
  year: number;
  uz: string;
  ru: string;
  en: string;
  source: string;
}

export interface FaqEntry {
  q_uz: string;
  q_ru: string;
  q_en: string;
  a_uz: string;
  a_ru: string;
  a_en: string;
  source: string;
}

export interface Content {
  meta: {
    version: string;
    collected_at: string;
    note: string;
    languages: Lang[];
    default_language: Lang;
    disclaimer_enabled: boolean;
    ru_machine?: boolean;
    ru_note?: string;
  };
  organization: {
    short_name: string;
    name: I18nText;
    founded: number;
    legal_basis: { text: string; url: string };
    tagline: I18nText;
    mission: I18nText;
    functions: I18nText[];
    services: ServiceItem[];
    contacts: {
      phone: string;
      phone_href: string;
      emails: string[];
      address: I18nText;
      map_url: string;
      website: string;
    };
    source: string;
  };
  economy_2025: {
    source: string;
    as_of: string;
    kpis: Kpi[];
    gdp_structure: GdpSlice[];
    growth_drivers: GrowthDriver[];
  };
  human_capital: {
    source: string;
    as_of: string;
    kpis: Kpi[];
    note_population: string;
  };
  regions: {
    source: string;
    salary_unit: string;
    national_avg_salary: number;
    items: Region[];
    todo: string;
  };
  sectors: { source: string; items: Sector[]; note: string };
  tourism: Tourism;
  strategy_2030: {
    source: string;
    /** Kept as the canonical value; the *_uz/_ru/_en family is what the UI shows. */
    adopted: string;
    adopted_uz: string;
    adopted_ru: string;
    adopted_en: string;
    goals_total: number;
    summary: I18nText;
    progress_since_adoption: I18nText[];
    priorities: Priority[];
    verify_note: string;
    verify: boolean;
  };
  tiif_2026: {
    source: string;
    name: I18nText;
    edition: string;
    first_held: number;
    dates: string;
    status: 'past' | 'upcoming';
    city: string;
    stats: Kpi[];
    notable_attendees_text_only: string[];
    official_site: string;
    agency_page: string;
    note: string;
  };
  sez_and_incentives: {
    sources: string[];
    legal_basis: { text: string; url: string };
    points: SezPoint[];
    zone_types_uz: string[];
    zone_types_ru: string[];
    zone_types_en: string[];
    disclaimer: I18nText;
    optional_tifc: { verify: boolean; source: string; uz: string; ru: string; en: string };
  };
  investor_journey: JourneyStep[];
  timeline: TimelineEntry[];
  faq: FaqEntry[];
  useful_links: { label: string; url: string }[];
  live_data: {
    fx: {
      endpoint: string;
      currencies: string[];
      fallback: { note: string; date: string | null; rates: Record<string, number> };
    };
  };
  sources: SourceRef[];
}
