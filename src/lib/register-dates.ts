/**
 * The forum's dates, for the registration page.
 *
 * That page is its own Vite entry and carries none of the site's store — it
 * is a form, and a form should not wait for a globe. But the dates are the
 * organiser's to change from the admin, and a page that printed the ones it
 * was built with would be the first thing on the site to go stale.
 *
 * So it reads the one field it needs out of the same file the site reads,
 * and says the built-in default until that arrives or if it never does. The
 * file is the admin's output: `event.dateStart` and `event.dateEnd`.
 */
import { formatDateRange } from './shared/content';
import type { Lang } from './shared/content-types';

/** `../`: the page is served as /register/, so the data is one level up. */
const FORUM_URL = '../data/forum-2026.json';

/** "2026-11-25/2026-11-27", or null if the file is missing or malformed. */
export async function fetchEventRange(signal?: AbortSignal): Promise<string | null> {
  try {
    const res = await fetch(FORUM_URL, { signal: signal ?? null });
    if (!res.ok) return null;
    const data = (await res.json()) as { event?: { dateStart?: unknown; dateEnd?: unknown } };
    const { dateStart, dateEnd } = data.event ?? {};
    if (typeof dateStart !== 'string' || typeof dateEnd !== 'string') return null;
    return `${dateStart}/${dateEnd}`;
  } catch {
    // No data file, no network, or a page opened from the file system. The
    // default is right until somebody moves the forum, and a registration
    // form is not worth failing over a subtitle.
    return null;
  }
}

export function sayRange(range: string, lang: Lang): string {
  return formatDateRange(range, lang);
}
