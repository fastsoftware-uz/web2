/**
 * Where "Roʻyxatdan oʻtish" sends a reader.
 *
 * Normally to this site's own registration page, `./register/`, carrying
 * the language they are reading in. The data file can send them somewhere
 * else instead — `registration.external` with `registration.url` — which is
 * what happened for a day, 18 to 19 November 2026, while the forum was
 * registering people on titf.uz.
 *
 * Both directions are live, because the switch is the organiser's and they
 * have used it twice. FALLBACK is the address this build knew, for the
 * seconds before the data file lands and if it never does: a registration
 * button that goes nowhere is worse than one that goes somewhere stale.
 *
 * Only http(s) is accepted for the outside address. It travels through the
 * admin, and a `javascript:` URL in an `href` the page writes is the one
 * way a text field in an operator's form turns into script on the site.
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
 * Absent from a data file older than 18 November 2026, and the answer there
 * is no: those builds had the form and nothing else.
 */
export function registersElsewhere(forum?: Pick<ForumData, 'registration'> | null): boolean {
  return forum?.registration?.external === true;
}

/**
 * The address a registration button should carry.
 *
 * This site's own page while registration is taken here, and relative, so
 * it keeps working under whatever base path the site is served from —
 * /web2/ on Pages, / on the organiser's own server. The outside address
 * when the data file says registration has moved.
 */
export function registerHref(
  forum: Pick<ForumData, 'registration'> | null | undefined,
  lang: string,
): string {
  return registersElsewhere(forum) ? registerUrl(forum) : `./register/?lang=${lang}`;
}

/** The host, for the line that tells a reader where they are being sent. */
export function registerHost(forum?: Pick<ForumData, 'registration'> | null): string {
  try {
    return new URL(registerUrl(forum)).host.replace(/^www\./, '');
  } catch {
    return '';
  }
}
