/**
 * Where "Roʻyxatdan oʻtish" sends a reader.
 *
 * The forum takes its registrations on its own registration page. The site's
 * form was taken down on 18 November 2026 at the organiser's asking, and
 * every button that said "register" now opens that page instead.
 *
 * The address comes out of the data file — `registration.url` — so the
 * operator can change it from the admin without a deploy. FALLBACK is what
 * is used in the seconds before the file lands, if an older file is being
 * served, or if the fetch fails altogether: a registration button that does
 * nothing is worse than one that goes to the address this build knew.
 *
 * Only http(s) is accepted. The value travels through the admin, and a
 * `javascript:` address in an `href` the page writes is the one way a text
 * field in an operator's form turns into script running on the site.
 */
import type { ForumData } from './forum-types';

/** The address this build was made with. */
export const FALLBACK_REGISTER_URL = 'https://titf.uz/en/online-registration';

/** True for an address a link may be given. */
export function isSafeHttpUrl(value: unknown): value is string {
  if (typeof value !== 'string') return false;
  try {
    const url = new URL(value);
    return url.protocol === 'https:' || url.protocol === 'http:';
  } catch {
    return false;
  }
}

/** The registration address the data file names, or the built-in one. */
export function registerUrl(forum?: Pick<ForumData, 'registration'> | null): string {
  const url = forum?.registration?.url;
  return isSafeHttpUrl(url) ? url : FALLBACK_REGISTER_URL;
}

/**
 * Whether registration happens somewhere else.
 *
 * Absent from an older data file, and the answer there is yes: the form it
 * was built with is no longer in the page, so the only honest thing a
 * registration button can do is send the reader onward.
 */
export function registersElsewhere(forum?: Pick<ForumData, 'registration'> | null): boolean {
  return forum?.registration?.external !== false;
}

/** The host, for the line that tells a reader where they are being sent. */
export function registerHost(forum?: Pick<ForumData, 'registration'> | null): string {
  try {
    return new URL(registerUrl(forum)).host.replace(/^www\./, '');
  } catch {
    return '';
  }
}
