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

export default defineConfig({
  base: './',
  plugins: [vue(), preloadFonts()],
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
        // three.js is only needed by the hero globe, which is loaded on idle and
        // skipped entirely when WebGL is missing or motion is reduced.
        manualChunks(id: string) {
          if (id.includes('node_modules/three')) return 'three';
          if (id.includes('node_modules/gsap')) return 'gsap';
          return undefined;
        },
      },
    },
  },
  server: { port: 5172 },
  preview: { port: 5172 },
});
