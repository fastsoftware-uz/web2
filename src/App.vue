<script setup lang="ts">
import { onBeforeUnmount, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

import SiteChrome from './components/SiteChrome.vue';
import ChPrologue from './chapters/ChPrologue.vue';
import ChProgramme from './chapters/ChProgramme.vue';
import ChSpeakers from './chapters/ChSpeakers.vue';
import ChThemes from './chapters/ChThemes.vue';
import ChPortfolio from './chapters/ChPortfolio.vue';
import ChPath from './chapters/ChPath.vue';
import ChEpilogue from './chapters/ChEpilogue.vue';
import { ScrollTrigger, startSmoothScroll, stopSmoothScroll } from './lib/motion';
import { retry, startLoading, useStore } from './lib/state';

const { t } = useI18n();
const { content, error, isReady } = useStore();

/**
 * Chapter order; SiteChrome uses this for the counter and the menu.
 *
 * The two chapters that opened the page — the Silk Road corridor and the
 * macroeconomic ground under it — came off at the client's request on
 * 15 November 2026: a visitor arriving at a forum site wants the day's
 * agenda and the people on the stage, not two chapters of argument first.
 * The agenda took the first slot and the speakers the second, and the
 * numbering in lib/i18n.ts follows the new order rather than keeping gaps.
 */
const CHAPTERS = [
  { id: 'prologue', key: 'prologue' },
  { id: 'programme', key: 'programme' },
  { id: 'speakers', key: 'speakers' },
  { id: 'themes', key: 'themes' },
  { id: 'portfolio', key: 'portfolio' },
  { id: 'path', key: 'path' },
  { id: 'epilogue', key: 'epilogue' },
];

startLoading();

onMounted(() => {
  startSmoothScroll();
  // Chapters mount after the fetch resolves, so their pinned heights only exist
  // once the content is in. One refresh then settles every trigger.
  const settle = () => ScrollTrigger.refresh();
  const timer = window.setTimeout(settle, 200);
  window.addEventListener('load', settle);
  onBeforeUnmount(() => {
    window.clearTimeout(timer);
    window.removeEventListener('load', settle);
  });
});

onBeforeUnmount(stopSmoothScroll);
</script>

<template>
  <!-- The pointed arch of a portal, once, for everything that is seen through
       one: a project's photograph, the niche a direction's icon stands in, the
       empty slot where a sheet carried no picture. Bounding-box units, so the
       one path fits whatever box it is applied to — which also means the curve
       is read through that box's aspect, and a near-square box gives the
       sharpest point. Decoration; it carries no meaning. -->
  <svg width="0" height="0" aria-hidden="true" focusable="false" class="defs">
    <defs>
      <clipPath id="uz-arch" clipPathUnits="objectBoundingBox">
        <path d="M0 1V0.34C0 0.20 0.27 0.04 0.5 0C0.73 0.04 1 0.20 1 0.34V1Z" />
      </clipPath>
    </defs>
  </svg>

  <a class="skip-link" href="#prologue">{{ t('skip') }}</a>

  <SiteChrome v-if="isReady" :chapters="CHAPTERS" />

  <main>
    <div v-if="error" class="state" role="alert">
      <h1>{{ t('state.errorTitle') }}</h1>
      <p>{{ t('state.errorBody') }}</p>
      <p class="state__detail">{{ error }}</p>
      <button type="button" @click="retry">{{ t('state.retry') }}</button>
    </div>

    <div v-else-if="!content" class="state" aria-busy="true">
      <span class="state__pulse" aria-hidden="true" />
      <p>{{ t('state.loading') }}</p>
    </div>

    <template v-else>
      <ChPrologue />
      <ChProgramme />
      <ChSpeakers />
      <ChThemes />
      <ChPortfolio />
      <ChPath />
      <ChEpilogue />
    </template>
  </main>
</template>

<style scoped>
/* Takes no space and paints nothing; it exists so `clip-path: url(#uz-arch)`
   has something to resolve against on every page state. */
.defs {
  position: absolute;
}

.state {
  min-height: 100svh;
  display: grid;
  align-content: center;
  justify-items: start;
  gap: 0.75rem;
  width: var(--shell);
  margin-inline: auto;
}
.state h1 {
  font-size: clamp(1.75rem, 4vw, 2.75rem);
}
.state__detail {
  font-family: ui-monospace, monospace;
  font-size: 0.8rem;
  color: var(--ink-dim);
  word-break: break-all;
}
.state button {
  margin-block-start: 1rem;
  padding: 0.85rem 1.6rem;
  border: 0;
  border-radius: 0.25rem;
  background: var(--ink);
  color: var(--surface);
  font: inherit;
  font-weight: 600;
  cursor: pointer;
}
.state__pulse {
  width: 3rem;
  height: 3rem;
  border-radius: 999px;
  background: var(--glow);
  opacity: 0.35;
  animation: pulse 1.6s ease-in-out infinite;
}
@keyframes pulse {
  0%,
  100% {
    transform: scale(0.85);
    opacity: 0.25;
  }
  50% {
    transform: scale(1);
    opacity: 0.5;
  }
}
</style>
