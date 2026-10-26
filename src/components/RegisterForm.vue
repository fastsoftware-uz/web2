<script setup lang="ts">
/**
 * The registration form.
 *
 * One form in two places: its own page, which is where somebody who has come
 * to register arrives, and the last chapter of the site, which is where
 * somebody who has just read the portfolio ends up. It replaced a three-step
 * enquiry wizard there — the forum needs the same nine answers from both, and
 * two different forms asking for overlapping things was a worse answer than
 * one form asked twice.
 *
 * Validation is done twice on purpose. Here, so that the reader is told which
 * field is wrong without a round trip; and again in the API, because anything
 * a browser checks is a suggestion.
 */
import { computed, reactive, ref, watch } from 'vue';

import {
  DIRECTION_IDS,
  MESSAGES,
  ORGANISER_EMAIL,
  ROLES,
  type Lang,
  type Role,
} from '../lib/register-copy';

const props = withDefaults(
  defineProps<{
    lang: Lang;
    /**
     * The project the reader arrived from, when chapter IV sent them here.
     * Its direction fills the select and its name opens the note, so the
     * organiser knows what the registration is about without asking the
     * reader to type the page back to it.
     */
    subject?: { id: string; title: string; segment?: string | null } | null;
    /** `h2` inside a chapter that already has one; `h1` on the page. */
    headingTag?: 'h1' | 'h2' | 'h3';
  }>(),
  { subject: null, headingTag: 'h2' },
);

const emit = defineEmits<{ sent: [id: number] }>();

const t = computed(() => MESSAGES[props.lang]);

/**
 * Where a registration is sent.
 *
 * Relative by default, which is right when nginx serves the built site and
 * the API from the same host — see server/README.md. Set
 * VITE_REGISTER_ENDPOINT to an absolute URL while the site is still on
 * GitHub Pages and the API is on the organiser's server.
 */
const ENDPOINT = import.meta.env.VITE_REGISTER_ENDPOINT || '/api/register';

const REQUIRED = ['full_name', 'organisation', 'position', 'country', 'email', 'phone'] as const;
type Required = (typeof REQUIRED)[number];

const form = reactive({
  role: 'visitor' as Role,
  full_name: '',
  organisation: '',
  position: '',
  country: '',
  email: '',
  phone: '',
  direction: '',
  note: '',
  /** The honeypot. No human sees this; a bot that fills every input does. */
  website: '',
});

const errors = reactive<Partial<Record<Required | 'role', boolean>>>({});
const state = ref<'idle' | 'sending' | 'done' | 'failed'>('idle');
const failure = ref<'network' | 'rate' | 'server' | 'fields' | ''>('');
const reference = ref(0);
/** The badge the backend issues with the registration, when it issues one. */
const ticket = ref<{ url: string; number: string } | null>(null);
const root = ref<HTMLElement | null>(null);

watch(
  () => props.subject,
  (next) => {
    if (!next) return;
    if (next.segment && (DIRECTION_IDS as readonly string[]).includes(next.segment)) {
      form.direction = next.segment;
    }
    // Only when the reader has not written something of their own.
    if (!form.note.trim()) form.note = `${t.value.aboutProject}: ${next.title}`;
  },
  { immediate: true },
);

/** The same rule the API applies, so the two never disagree about an address. */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

function validate(): boolean {
  for (const key of REQUIRED) {
    const value = form[key].trim();
    errors[key] = key === 'email' ? !EMAIL.test(value) : value.length < 2;
  }
  errors.role = !ROLES.includes(form.role);
  return !Object.values(errors).some(Boolean);
}

/** Re-checks a field once it has been wrong, so the mark clears as you type. */
function recheck(key: Required): void {
  if (!errors[key]) return;
  const value = form[key].trim();
  errors[key] = key === 'email' ? !EMAIL.test(value) : value.length < 2;
}

const firstBadField = computed(() => REQUIRED.find((key) => errors[key]));

async function submit(): Promise<void> {
  if (state.value === 'sending') return;
  failure.value = '';

  if (!validate()) {
    failure.value = 'fields';
    // Taken to the first field that needs fixing rather than left to hunt.
    const id = firstBadField.value;
    if (id) document.getElementById(id)?.focus();
    return;
  }

  state.value = 'sending';
  try {
    const res = await fetch(ENDPOINT, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ...form, project: props.subject?.id ?? null, lang: props.lang }),
    });
    const body = (await res.json().catch(() => ({}))) as {
      ok?: boolean;
      id?: number;
      fields?: string[];
      error?: string;
      ticket?: { number?: string; url?: string };
    };

    if (res.ok && body.ok) {
      reference.value = Number(body.id) || 0;
      /* The badge, when the backend issued one. It is not a certainty: a
         server whose data directory has gone read-only still stores the
         registration and says so, and the organiser hands the ticket over
         from the admin afterwards. */
      ticket.value =
        body.ticket?.url && body.ticket?.number
          ? { url: body.ticket.url, number: body.ticket.number }
          : null;
      state.value = 'done';
      emit('sent', reference.value);
      // Inside a chapter the confirmation can be off-screen; on the page it
      // is already at the top and this does nothing.
      root.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }

    if (res.status === 422 && Array.isArray(body.fields)) {
      // The server found something the browser let through; mark the same
      // fields so the reader sees one consistent story.
      for (const key of body.fields) {
        if ((REQUIRED as readonly string[]).includes(key)) errors[key as Required] = true;
      }
      failure.value = 'fields';
    } else if (res.status === 429) {
      failure.value = 'rate';
    } else {
      failure.value = 'server';
    }
    state.value = 'failed';
  } catch {
    // Offline, DNS, a blocked request: all the same thing to the reader.
    failure.value = 'network';
    state.value = 'failed';
  }
}

function again(): void {
  for (const key of REQUIRED) form[key] = '';
  form.direction = '';
  form.note = '';
  reference.value = 0;
  ticket.value = null;
  state.value = 'idle';
  failure.value = '';
}

/**
 * The same answers as an email, offered when the API could not be reached.
 *
 * Which is not a hypothetical: while the site is published on GitHub Pages
 * and the API is on the organiser's own server, a build that has not been
 * given VITE_REGISTER_ENDPOINT posts into nothing. Somebody who has just
 * typed nine fields should not be told only that it failed.
 */
const mailHref = computed(() => {
  const lines = [
    `${t.value.roleLabel}: ${t.value.roles[form.role]}`,
    `${t.value.fields.full_name}: ${form.full_name}`,
    `${t.value.fields.organisation}: ${form.organisation}`,
    `${t.value.fields.position}: ${form.position}`,
    `${t.value.fields.country}: ${form.country}`,
    `${t.value.fields.email}: ${form.email}`,
    `${t.value.fields.phone}: ${form.phone}`,
  ];
  if (form.direction) {
    lines.push(`${t.value.fields.direction}: ${t.value.directions[form.direction]}`);
  }
  if (form.note.trim()) lines.push(`${t.value.fields.note}: ${form.note.trim()}`);
  return (
    `mailto:${ORGANISER_EMAIL}` +
    `?subject=${encodeURIComponent(t.value.mailSubject)}` +
    `&body=${encodeURIComponent(lines.join('\n'))}`
  );
});
</script>

<template>
  <div ref="root">
<!-- Done ---------------------------------------------------------- -->
<section v-if="state === 'done'" class="reg__done card">
  <component :is="headingTag">{{ t.doneTitle }}</component>
  <p>{{ t.doneBody }}</p>
  <p v-if="reference" class="reg__ref">
    {{ t.doneRef }}: <strong>№ {{ reference }}</strong>
  </p>

  <!-- The e-ticket. A guest who has just filled in a form leaves with
       something in their hand rather than with a promise of a mail. -->
  <!-- A VIP and a speaker are invited rather than registered, so the badge
       waits until the committee has been in touch. Saying so here is the
       difference between "later" and "something went wrong". -->
  <p v-if="!ticket" class="reg__ticketLater">{{ t.ticketLater }}</p>
  <div v-else class="reg__ticket">
    <p class="reg__ticketTitle">{{ t.ticketTitle }}</p>
    <p class="reg__ticketBody">{{ t.ticketBody }}</p>
    <a class="btn btn--solid reg__ticketOpen" :href="ticket.url" target="_blank" rel="noopener">
      {{ t.ticketOpen }}
    </a>
    <p class="reg__ticketNo">{{ t.ticketNumber }}: <code>{{ ticket.number }}</code></p>
  </div>
  <div class="reg__doneActions">
    <button type="button" class="btn btn--solid" @click="again">{{ t.again }}</button>
    <!-- The page adds "back to the site" here; the chapter has the rest
         of the site under it already. -->
    <slot name="done-extra" />
  </div>
</section>

<!-- The form ------------------------------------------------------ -->
<template v-else>
  <slot name="intro" />

  <div v-if="failure" class="reg__alert" role="alert">
    <p>{{ failure === 'fields' ? t.errors.summary : t.errors[failure] }}</p>
    <!-- Only when the request itself did not land. A wrong field is
         the reader's to fix here, not something to email about. -->
    <p v-if="failure === 'network' || failure === 'server'" class="reg__alertMail">
      {{ t.byEmail }}
      <a :href="mailHref">{{ ORGANISER_EMAIL }}</a>
    </p>
  </div>

  <form class="reg__form" novalidate @submit.prevent="submit">
    <fieldset class="reg__roles">
      <legend>{{ t.roleLabel }}</legend>
      <label v-for="role in ROLES" :key="role" class="reg__role">
        <input v-model="form.role" type="radio" name="role" :value="role" />
        <strong>{{ t.roles[role] }}</strong>
      </label>
    </fieldset>

    <div class="reg__grid">
      <p class="field field--wide">
        <label for="full_name">{{ t.fields.full_name }}</label>
        <input
          id="full_name"
          v-model="form.full_name"
          type="text"
          autocomplete="name"
          :aria-invalid="errors.full_name ? 'true' : undefined"
          :aria-describedby="errors.full_name ? 'e-full_name' : undefined"
          @blur="recheck('full_name')"
        />
        <small v-if="errors.full_name" id="e-full_name" class="field__err">
          {{ t.errors.full_name }}
        </small>
      </p>

      <p class="field">
        <label for="organisation">{{ t.fields.organisation }}</label>
        <input
          id="organisation"
          v-model="form.organisation"
          type="text"
          autocomplete="organization"
          :aria-invalid="errors.organisation ? 'true' : undefined"
          :aria-describedby="errors.organisation ? 'e-organisation' : undefined"
          @blur="recheck('organisation')"
        />
        <small v-if="errors.organisation" id="e-organisation" class="field__err">
          {{ t.errors.organisation }}
        </small>
      </p>

      <p class="field">
        <label for="position">{{ t.fields.position }}</label>
        <input
          id="position"
          v-model="form.position"
          type="text"
          autocomplete="organization-title"
          :aria-invalid="errors.position ? 'true' : undefined"
          :aria-describedby="errors.position ? 'e-position' : undefined"
          @blur="recheck('position')"
        />
        <small v-if="errors.position" id="e-position" class="field__err">
          {{ t.errors.position }}
        </small>
      </p>

      <p class="field">
        <label for="country">{{ t.fields.country }}</label>
        <input
          id="country"
          v-model="form.country"
          type="text"
          autocomplete="country-name"
          :aria-invalid="errors.country ? 'true' : undefined"
          :aria-describedby="errors.country ? 'e-country' : undefined"
          @blur="recheck('country')"
        />
        <small v-if="errors.country" id="e-country" class="field__err">
          {{ t.errors.country }}
        </small>
      </p>

      <p class="field">
        <label for="phone">{{ t.fields.phone }}</label>
        <input
          id="phone"
          v-model="form.phone"
          type="tel"
          inputmode="tel"
          autocomplete="tel"
          :aria-invalid="errors.phone ? 'true' : undefined"
          :aria-describedby="errors.phone ? 'e-phone' : undefined"
          @blur="recheck('phone')"
        />
        <small v-if="errors.phone" id="e-phone" class="field__err">
          {{ t.errors.phone }}
        </small>
      </p>

      <p class="field field--wide">
        <label for="email">{{ t.fields.email }}</label>
        <input
          id="email"
          v-model="form.email"
          type="email"
          inputmode="email"
          autocomplete="email"
          :aria-invalid="errors.email ? 'true' : undefined"
          :aria-describedby="errors.email ? 'e-email' : undefined"
          @blur="recheck('email')"
        />
        <small v-if="errors.email" id="e-email" class="field__err">
          {{ t.errors.email }}
        </small>
      </p>

      <p class="field field--wide">
        <label for="direction">
          {{ t.fields.direction }} <span class="field__opt">({{ t.optional }})</span>
        </label>
        <select id="direction" v-model="form.direction">
          <option value="">{{ t.directionNone }}</option>
          <option v-for="id in DIRECTION_IDS" :key="id" :value="id">
            {{ t.directions[id] }}
          </option>
        </select>
      </p>

      <p class="field field--wide">
        <label for="note">
          {{ t.fields.note }} <span class="field__opt">({{ t.optional }})</span>
        </label>
        <textarea id="note" v-model="form.note" rows="4" maxlength="1200" />
      </p>
    </div>

    <!-- Not display:none — a bot that reads the stylesheet skips that.
         Off-screen, not reachable by tab, and announced to nobody. -->
    <div class="reg__trap" aria-hidden="true">
      <label for="website">Website</label>
      <input id="website" v-model="form.website" type="text" tabindex="-1" autocomplete="off" />
    </div>

    <div class="reg__actions">
      <button type="submit" class="btn btn--solid" :disabled="state === 'sending'">
        {{ state === 'sending' ? t.sending : t.submit }}
      </button>
      <small class="reg__privacy">{{ t.privacy }}</small>
    </div>
  </form>
</template>
  </div>
</template>

<style scoped>
.reg__alert {
  margin-block-start: 1.5rem;
  padding: 0.8rem 1rem;
  border: 1px solid var(--danger);
  border-radius: 0.3rem;
  background: color-mix(in srgb, var(--danger) 12%, transparent);
  color: var(--ink);
  font-size: 0.9rem;
}

.reg__alert p {
  margin: 0;
}

.reg__alert p + p {
  margin-block-start: 0.5rem;
}

.reg__alertMail {
  color: var(--ink-soft);
}

.reg__alertMail a {
  color: var(--gold);
  overflow-wrap: anywhere;
}

.reg__form {
  margin-block-start: 2rem;
}

/* The kinds of participant, as cards rather than bare radios: a row of
   16px dots with a name beside each reads as a settings screen, and this is
   the first choice a guest makes. The cards carry the name alone — the
   client took the explanatory line under each one off on 26 October 2026. */
.reg__roles {
  display: grid;
  gap: 0.6rem;
  margin: 0 0 1.75rem;
  padding: 0;
  border: 0;
}
.reg__roles legend {
  padding: 0;
  margin-block-end: 0.6rem;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--gold);
}
@media (min-width: 34rem) {
  .reg__roles {
    grid-template-columns: 1fr 1fr;
  }
  .reg__roles legend {
    grid-column: 1 / -1;
  }
}
.reg__role {
  display: flex;
  gap: 0.7rem;
  align-items: center;
  padding: 0.9rem 1rem;
  border: 1px solid var(--hairline);
  border-radius: 0.3rem;
  background: var(--raise);
  cursor: pointer;
  transition: border-color 0.18s, background-color 0.18s;
}
.reg__role:has(input:checked) {
  border-color: var(--gold-leaf);
  background: color-mix(in srgb, var(--gold-leaf) 10%, transparent);
}
.reg__role:has(input:focus-visible) {
  outline: 2px solid var(--accent-soft);
  outline-offset: 2px;
}
.reg__role input {
  accent-color: var(--gold-leaf);
  inline-size: 1.05rem;
  block-size: 1.05rem;
  flex: 0 0 auto;
}
.reg__role strong {
  color: var(--ink);
}

.reg__grid {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 1rem;
}
@media (min-width: 34rem) {
  .reg__grid {
    /* minmax(0, …) so a long value cannot widen its column past the card. */
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
  .field--wide {
    grid-column: 1 / -1;
  }
}

.field {
  display: grid;
  gap: 0.35rem;
  margin: 0;
}
.field label {
  font-size: 0.85rem;
  font-weight: 600;
  color: var(--ink);
}
.field__opt {
  font-weight: 400;
  color: var(--ink-dim);
}
.field input,
.field select,
.field textarea {
  /* A select is as wide as its widest option unless it is told otherwise, and
     "Mehmonxonalar va turizm infratuzilmasi" is wider than a phone. Both of
     these are needed: the width to stop it using its intrinsic size, and the
     zero minimum so the grid column does not adopt that size either. */
  inline-size: 100%;
  min-inline-size: 0;
  min-block-size: var(--tap);
  padding: 0.6rem 0.75rem;
  border: 1px solid var(--hairline);
  border-radius: 0.3rem;
  background: var(--raise);
  color: var(--ink);
  font: inherit;
}
.field textarea {
  resize: vertical;
}

/**
 * The options of a select, which the page's own colours do not reach.
 *
 * The form sits on the lit stage, so `color` is near-white; the list a native
 * select drops down is painted by the platform on its own white, and the two
 * together made every direction but the highlighted one invisible. The option
 * list is the one place a dark theme has to say the light colours out loud.
 */
.field select option {
  background: #ffffff;
  color: #15193a;
}
.field input:focus-visible,
.field select:focus-visible,
.field textarea:focus-visible {
  outline: 2px solid var(--accent-soft);
  outline-offset: 1px;
}
.field [aria-invalid='true'] {
  border-color: var(--danger);
}
.field__err {
  font-size: 0.8rem;
  color: var(--danger);
}

.reg__trap {
  position: absolute;
  inline-size: 1px;
  block-size: 1px;
  overflow: hidden;
  clip-path: inset(50%);
  white-space: nowrap;
}

.reg__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.9rem 1.25rem;
  align-items: center;
  margin-block-start: 2rem;
}
.reg__privacy {
  flex: 1 1 16rem;
  font-size: 0.8rem;
  color: var(--ink-dim);
}

.reg__done {
  padding: clamp(1.5rem, 5vw, 2.5rem);
}
.reg__done h1 {
  font-size: clamp(1.75rem, 4vw, 2.5rem);
}
.reg__done p {
  margin-block-start: 0.9rem;
  max-width: 34rem;
}
.reg__ref strong {
  color: var(--gold);
  font-variant-numeric: tabular-nums;
}
/* The e-ticket block. It is the one thing on this card a reader has to act
   on, so it is the one thing that carries the forum's gold. */
.reg__ticketLater {
  margin-block: 1.25rem 0;
  padding-block-start: 1rem;
  border-block-start: 1px solid var(--hairline);
  color: var(--ink-soft);
  font-size: 0.95rem;
}
.reg__ticket {
  margin-block: 1.5rem 0;
  padding: 1.1rem 1.2rem 1.3rem;
  border: 1px solid var(--hairline-strong);
  border-radius: 0.6rem;
  background: var(--raise);
}
.reg__ticketTitle {
  margin: 0;
  font-weight: 700;
}
.reg__ticketBody {
  margin: 0.4rem 0 1rem;
  color: var(--ink-soft);
  font-size: 0.95rem;
}
.reg__ticketOpen {
  display: inline-block;
}
.reg__ticketNo {
  margin: 0.9rem 0 0;
  font-size: 0.85rem;
  color: var(--ink-dim);
}
.reg__ticketNo code {
  font-size: 0.9rem;
  letter-spacing: 0.04em;
  color: var(--ink-soft);
}

.reg__doneActions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-block-start: 1.75rem;
}
</style>
