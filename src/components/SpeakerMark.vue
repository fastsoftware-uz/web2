<script setup lang="ts">
/**
 * What stands in the arch when a speaker's portrait has not arrived.
 *
 * It used to be their initials. With four speakers that read as a
 * placeholder; with thirty-eight it read as a page of empty boxes, and the
 * client asked on 17 November 2026 for something drawn instead — and for a
 * woman to be drawn as a woman.
 *
 * So it is a figure, not a photograph and not pretending to be one: a head
 * and a pair of shoulders on the same 24-unit grid the theme and action
 * icons are drawn on, in the forum's gold, under the same pointed arch a
 * real portrait is seen through. Three of them — a woman, a man, and the
 * one used when nobody has said which, which is the default and is drawn
 * without the hair that distinguishes the first.
 *
 * Which one a speaker gets is a field on the speaker, set in the admin and
 * never guessed from the name at runtime: a name is not evidence, and a
 * list of international guests is exactly where guessing goes wrong.
 */
import type { SpeakerIcon } from '../lib/forum-types';

defineProps<{ kind: SpeakerIcon }>();
</script>

<template>
  <svg class="mark" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    <!-- The head. The same circle in all three; only what is around it
         changes, so the three sit at the same height in the arch. -->
    <circle class="mark__head" cx="12" cy="8.1" r="3.5" />

    <!-- Hair, for the woman: it meets the head at the crown and falls just
         past the jaw on both sides. Drawn behind the head, so the face
         stays the shape the other two have. -->
    <path
      v-if="kind === 'woman'"
      class="mark__hair"
      d="M12 3.1c-3.5 0-5.9 2.4-5.9 5.7 0 2.2.3 4 .7 5.4.2.7 1.2.6 1.3-.1.2-1.1.3-2.3.3-3.4 0-.7.1-1.2.4-1.6.6-.9 1.6-1.3 3.2-1.3s2.6.4 3.2 1.3c.3.4.4.9.4 1.6 0 1.1.1 2.3.3 3.4.1.7 1.1.8 1.3.1.4-1.4.7-3.2.7-5.4 0-3.3-2.4-5.7-5.9-5.7z"
    />

    <!-- The shoulders. A shade narrower for the woman, which is the only
         other difference between the three. -->
    <path
      v-if="kind === 'woman'"
      class="mark__body"
      d="M12 13.2c-3.7 0-6.6 2.3-6.6 5.7 0 .9.1 1.6.3 2.1h12.6c.2-.5.3-1.2.3-2.1 0-3.4-2.9-5.7-6.6-5.7z"
    />
    <path
      v-else
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
.mark__body,
.mark__hair {
  fill: currentColor;
}
.mark__hair {
  opacity: 0.75;
}
</style>
