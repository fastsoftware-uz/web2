<script setup lang="ts">
/**
 * What stands in the arch when a speaker's portrait has not arrived.
 *
 * It used to be their initials. With four speakers that read as a
 * placeholder; with fifty-nine it read as a page of empty boxes, so on
 * 17 November 2026 it became a figure — a head and a pair of shoulders on
 * the same 24-unit grid the theme and action icons are drawn on, in the
 * forum's gold, under the same pointed arch a real portrait is seen
 * through.
 *
 * One figure, for everybody.
 *
 * It was three for a day: a woman with hair, a man, and a default. The
 * client asked on the 18th for one figure for all of them, and on the 19th
 * asked again, because a speaker added from the admin had been given the
 * woman by hand and the hair was back on the page. So the variants are
 * gone from here and the choice is gone from the admin: there is nothing
 * left to pick, which is the only way the answer stays the same.
 *
 * A stored `icon` on an older speaker record is simply ignored.
 */
</script>

<template>
  <svg class="mark" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <circle class="mark__head" cx="12" cy="8.1" r="3.5" />
    <path
      class="mark__body"
      d="M12 13.2c-4.1 0-7.1 2.4-7.1 5.8 0 .9.1 1.6.3 2h13.6c.2-.4.3-1.1.3-2 0-3.4-3-5.8-7.1-5.8z"
    />
  </svg>
</template>

<style scoped>
.mark {
  inline-size: 52%;
  block-size: auto;
  /* Lifted, so the initials below it sit in the arch rather than on its
     edge. */
  translate: 0 -6%;
  /* The gold the chapter marks and the arches are drawn in, held back so
     the figure reads as a placeholder rather than as an illustration
     competing with the photographs beside it. */
  color: var(--gold);
  opacity: 0.42;
}
.mark__head,
.mark__body {
  fill: currentColor;
}
</style>
