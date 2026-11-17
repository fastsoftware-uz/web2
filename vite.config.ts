import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

/**
 * Preload the two self-hosted variable fonts.
 *
 * Without this the browser cannot know they exist until it has downloaded and
 * parsed the stylesheet, so the first paint of the page's own type waits on
 * two requests that only start once everything else has: the hero's heading
 * is the largest element on the screen, which puts that wait straight into
 * Largest Contentful Paint.
 *
 * Only the Latin subset of each family. Fontsource ships nine files per
 * build — Cyrillic, Greek, Latin-ext, Vietnamese — and preloading all of them
 * would pull about two hundred kilobytes the page does not use and make the
 * thing it is meant to fix worse. Latin-ext is left out too: it is fetched on
 * demand if a glyph needs it, and the two files here are the ones the first
 * screen is set in. The page is served in Uzbek Latin; a reader
 * who switches to Russian fetches the Cyrillic subset then, which is what the
 * unicode-range split is for.
 *
 * The filenames are hashed at build time, so they are read out of the bundle
 * rather than written down. `crossorigin` is required even for same-origin
 * fonts — a preload without it is fetched again by the font loader.
 *
 * The href is relative, and the two pages do not sit at the same depth:
 * `/register/` is a directory, so its preloads need one `../` that the site's
 * own index must not have. `ctx.path` is the page being written, and the
 * number of slashes in it is the number of levels to climb.
 */
function preloadFonts() {
  return {
    name: 'preload-fonts',
    enforce: 'post' as const,
    transformIndexHtml(html: string, ctx: { path?: string; bundle?: Record<string, unknown> }) {
      const fonts = Object.keys(ctx.bundle ?? {}).filter((name) =>
        /-latin-wght-normal-[^/]*\.woff2$/.test(name),
      );
      const depth = (ctx.path ?? '/index.html').replace(/^\/+/, '').split('/').length - 1;
      const up = depth > 0 ? '../'.repeat(depth) : './';
      return {
        html,
        tags: fonts.map((file) => ({
          tag: 'link',
          attrs: {
            rel: 'preload',
            as: 'font',
            type: 'font/woff2',
            href: `${up}${file}`,
            crossorigin: '',
          },
          injectTo: 'head-prepend' as const,
        })),
      };
    },
  };
}

/**
 * The two numbers in the static description, and a head start on the data.
 *
 * ## The numbers
 *
 * `<meta name="description">` is what a crawler and a chat app's link
 * preview read, and neither runs the JavaScript that keeps the live page's
 * description accurate. Written by hand it said "74 projects from 13
 * regions" for the four months the portfolio grew to 260 from 14. So the
 * markup carries `{count}` and `{regions}` and they are filled in here from
 * the same file the page itself reads.
 *
 * ## The head start
 *
 * Everything on the page comes out of `data/forum-2026.json`, and the fetch
 * for it cannot begin until the bundle has been downloaded, parsed and run —
 * which on a throttled phone put the title card's own first line nearly two
 * seconds behind the first paint. So the request is started here, while the
 * HTML is still being parsed, in parallel with the script instead of behind
 * it. Only on the site's own page: the registration page does not read it.
 *
 * This was a `<link rel="preload" as="fetch">` until 17 November 2026. It
 * had to stop being one. The file is the single thing the site loads whose
 * name never changes — everything else carries a content hash — and the
 * server sends it with no `Cache-Control`, so browsers cache it
 * heuristically and a reader who had been to the page before was served a
 * day-old copy under the new build's JavaScript. The fix is to revalidate
 * (`cache: 'no-cache'`, see loadForum), and a preload link cannot express
 * that: the preloaded response and the revalidating fetch are different
 * requests, so the page would have downloaded 800 kB twice.
 *
 * An inline fetch can say it, and hands the app the very same promise.
 */
function forumFacts() {
  /* The site's own copy, not the monorepo's: `npm run export` writes this
     project out as a standalone repository with no `shared/` above it, and
     a build that reads outside its own directory fails there. `npm run
     sync` puts the same file here before every build. */
  const data = JSON.parse(
    readFileSync(new URL('./public/data/forum-2026.json', import.meta.url), 'utf8'),
  ) as { portfolio: { projects: unknown[]; regionCounts: Record<string, number> } };
  const count = String(data.portfolio.projects.length);
  const regions = String(Object.keys(data.portfolio.regionCounts).length);

  return {
    name: 'forum-facts',
    transformIndexHtml(html: string, ctx: { path?: string }) {
      const filled = html.replaceAll('{count}', count).replaceAll('{regions}', regions);
      const isSite = (ctx.path ?? '/index.html').replace(/^\/+/, '') === 'index.html';
      if (!isSite) return filled;
      return {
        html: filled,
        tags: [
          {
            tag: 'script',
            /* Deliberately not a module: a module is deferred, and deferred
               is exactly the wait this is here to remove. The promise is
               given a no-op catch so a failure before the app wakes up is
               not an unhandled rejection in the console; loadForum attaches
               the handlers that matter to the same promise. */
            children:
              "try{var p=fetch('data/forum-2026.json',{cache:'no-cache'});" +
              'p.catch(function(){});window.__forum=p}catch(e){}',
            injectTo: 'head-prepend' as const,
          },
        ],
      };
    },
  };
}

export default defineConfig({
  base: './',
  plugins: [vue(), preloadFonts(), forumFacts()],
  build: {
    target: 'es2022',
    rollupOptions: {
      /**
       * Two pages, not one.
       *
       * The registration page shares the stylesheet and the forum mark with
       * the site and nothing else, so it is a second entry rather than a
       * route: somebody who came to register is not made to download a
       * scroll-driven narrative and a WebGL globe first.
       *
       * `register/index.html`, not `register.html`, so the address reads
       * `/register/`. A file extension in a URL somebody is asked to type or
       * is sent in an invitation is an implementation detail on show.
       */
      input: {
        index: fileURLToPath(new URL('./index.html', import.meta.url)),
        register: fileURLToPath(new URL('./register/index.html', import.meta.url)),
      },
      output: {
        // The globe and its three.js came off the title card on 15 November
        // 2026, so only GSAP is still worth a chunk of its own.
        manualChunks(id: string) {
          if (id.includes('node_modules/gsap')) return 'gsap';
          return undefined;
        },
      },
    },
  },
  server: { port: 5172 },
  preview: { port: 5172 },
});
